"""
Analytics microservice for the quiz_quizanalytics Moodle plugin.

This is a thin HTTP wrapper around your EXISTING `analytics/` package from
Interactive-quiz-analytics — nothing in that package is modified. The only
new logic here is `_records_to_moodle_df()`, which rebuilds the same column
shape a Moodle CSV export has (Last name / Response 1 / Grade/10.00 / ...)
out of the JSON the Moodle plugin sends, so `analytics.parser.build_response_rows`
can run completely unchanged.

Run it with:
    uvicorn app:app --host 127.0.0.1 --port 8600

Deliberately bound to 127.0.0.1 by default (see the systemd unit / Docker
notes in the README) — this must never be reachable from outside the server
Moodle itself runs on.
"""

from __future__ import annotations

import json
import logging
import re
from collections import Counter
from typing import Any

import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

# --- Import the existing analytics package unchanged. -----------------------
# Deliberately NOT importing analytics.data_loader / upload_ui / pdf_ui here:
# those are the Streamlit-upload-specific modules. Everything below is the
# pure-computation half of the package, which has no Streamlit runtime
# dependency (see README for how this was verified).
from analytics.parser import build_response_rows, get_attempt_pools
from analytics.question_analytics import build_question_analytics
from analytics.question_details import build_question_detail, build_error_drilldown
from analytics.question_charts import (
    build_difficulty_bar_figure,
    build_score_boxplot_figure,
    build_response_outcome_figure,
    build_valid_invalid_figure,
    build_student_matrix,
    build_student_matrix_figure,
    build_question_metrics_table,
)
from analytics.prt_analysis import build_prt_pass_heatmap, build_prt_pass_heatmap_figure
from analytics.latex_utils import extract_stack_answer_latex, split_stack_debug_dump
from analytics.validation import audit_question_data
from analytics.quiz_metrics import (
    build_quiz_attempt_frame,
    compute_quiz_stats,
    build_boxplot_figure,
    build_engagement_figure,
)
from analytics.prt_transitions import (
    count_question_parts,
    build_aggregate_graph,
    build_transition_graph_figure,
    compute_network_features,
    build_student_node_sequence,
    build_transition_pairs,
)
from analytics.spv_charts import build_centrality_bar_figures
from analytics.solution_distance import (
    CROSS_ATTEMPT_METRICS,
    build_prt_distance_3d_figure,
    build_ted_distance_3d_figure,
    compute_cross_attempt_comparison,
    classify_cross_attempt_trends,
    build_cross_attempt_figure,
    build_single_student_attempt_figure,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("quizanalytics")

app = FastAPI(title="Quiz Analytics microservice")


# --- Request schemas, matching the PHP plugin's api_client.php exactly. -----

class AnalyzeRequest(BaseModel):
    quiz_name: str
    records: list[dict[str, Any]] = Field(default_factory=list)
    colorblind_mode: bool = False


class AnalyzeCourseRequest(BaseModel):
    course_name: str
    quizzes: dict[str, list[dict[str, Any]]] = Field(default_factory=dict)


class SolutionProcessMetaRequest(BaseModel):
    quiz_name: str
    records: list[dict[str, Any]] = Field(default_factory=list)


class SolutionProcessRequest(BaseModel):
    quiz_name: str
    records: list[dict[str, Any]] = Field(default_factory=list)
    question: str
    part_index: int = 1
    student_id: str | None = None
    colorblind_mode: bool = False


# --- The one piece of genuinely new code: JSON -> Moodle-CSV-shaped frame. --

_QUESTION_TEXT_KEY = re.compile(r"^question_(\d+)_text$")


def _records_to_moodle_df(records: list[dict[str, Any]]) -> pd.DataFrame:
    """Rebuild the exact column layout `build_response_rows()` already knows
    how to parse from a real Moodle CSV export, so no changes are needed on
    the analytics side — only the input source changes."""
    if not records:
        return pd.DataFrame()

    # All attempts in one quiz share the same max_grade; grab it from any record.
    max_grade = next((r.get("max_grade") for r in records if r.get("max_grade") is not None), 0)
    grade_col = f"Grade/{float(max_grade):.2f}"

    question_numbers = sorted({
        int(m.group(1))
        for rec in records
        for key in rec
        if (m := _QUESTION_TEXT_KEY.match(key))
    })

    rows = []
    for rec in records:
        row = {
            "Last name": rec.get("last_name", ""),
            "First name": rec.get("first_name", ""),
            "Email address": rec.get("email", ""),
            # build_response_rows() keeps only rows where State == "Finished"
            # exactly, so this must match that literal string.
            "State": "Finished" if rec.get("state") == "finished" else rec.get("state", ""),
            "Started on": rec.get("started_on", ""),
            "Completed": rec.get("completed", ""),
            "Time taken": rec.get("time_taken_secs", ""),
            grade_col: rec.get("grade", ""),
        }
        for n in question_numbers:
            row[f"Question {n}"] = rec.get(f"question_{n}_text", "")
            row[f"Response {n}"] = rec.get(f"response_{n}", "")
            row[f"Right answer {n}"] = rec.get(f"right_answer_{n}", "")
        rows.append(row)

    return pd.DataFrame(rows)


def _fig_to_json(fig) -> dict[str, Any]:
    """Plotly figure -> plain dict, matching what render.js expects
    (`fig.plotly_json.data` / `.layout`)."""
    return json.loads(fig.to_json())


def _q_num(q_name: str) -> int:
    """Natural sort key for question names ("Q10" after "Q2", not before) —
    matches report_sections.py's identically-named helper."""
    m = re.search(r"\d+", str(q_name))
    return int(m.group(0)) if m else 0


def _df_to_table(df: pd.DataFrame) -> dict[str, Any]:
    """DataFrame -> {"columns": [...], "rows": [[...], ...]}, matching what
    sections-renderer.js's renderDataTable() expects. Goes via pandas' own
    to_json() (orient="split") rather than df.values.tolist() directly —
    pandas' JSON encoder correctly converts numpy int64/float64/NaT/NaN into
    plain JSON-safe types (null for NaN/NaT), which the stdlib json module
    (and FastAPI's default encoder) does not do for numpy scalars."""
    if df is None or df.empty:
        return {"columns": list(df.columns) if df is not None else [], "rows": []}
    split = json.loads(df.to_json(orient="split", date_format="iso"))
    return {"columns": split["columns"], "rows": split["data"]}


# --- Endpoints ----------------------------------------------------------

@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/analyze")
def analyze(req: AnalyzeRequest) -> dict[str, Any]:
    """Single-quiz Question Analysis — called from report.php's Interactive
    Analytics tab and from local_quizanalytics's per-quiz drill-down.

    Response shape (the generic {summary, sections} contract shared with the
    /analyze-course and future /solution-process routes — see
    sections-renderer.js on the PHP side):

        {
          "quiz_name": str,
          "summary": {...compute_question_summary() scalars...},
          "sections": [
            {"id": "difficulty", "title": "2. ...", "table": {...}, "charts": [...]},
            {"id": "response-distribution", "title": "4. ...", "table": {...}, "charts": [...]},
            {"id": "student-matrix", "title": "5. ...", "table": {...}, "charts": [...]},
            {"id": "metrics", "title": "6. ...", "table": {...}},
          ],
          "questions": {
            "Question 1": {
              "question_text_html": str, "right_answer_html": str,
              "error_drilldown": {"columns": [...], "rows": [...]},
            },
            ...
          },
          "audit": {"checks": {...}, "issues": [...], "is_valid": bool},
        }

    Section "3. Question Item Details & Error Drill-Down" (from
    report_sections.QUESTION_MODULES) is deliberately NOT a flat section here
    — it's inherently per-question (the Streamlit page gates it behind a
    question selector too), so it's represented as the `questions` map
    instead, which the PHP side turns into a <select>-driven show/hide UI
    with no extra request needed. "1. Question Summary" is `summary` itself.
    """
    moodle_df = _records_to_moodle_df(req.records)
    if moodle_df.empty:
        raise HTTPException(status_code=400, detail="No records supplied.")

    response_df = build_response_rows(moodle_df, quiz_name=req.quiz_name)
    if response_df.empty:
        raise HTTPException(
            status_code=422,
            detail="No finished, gradable attempts parsed from the supplied records.",
        )

    qa = build_question_analytics(response_df, quiz_name=req.quiz_name)
    question_metrics = qa["question_metrics"]
    difficulty_metrics = qa["difficulty_metrics"]
    ranked_difficulty = qa["ranked_difficulty"]
    response_outcomes = qa["response_outcomes"]
    repeated_wrong_answers = qa["repeated_wrong_answers"]
    prt_pass_rates = qa["prt_pass_rates"]
    prt_frame = qa["prt_frame"]
    pool_b_df = qa["pool_b_df"].copy()

    summary: dict[str, Any] = {"quiz_name": req.quiz_name, **qa["question_summary"]}

    sections: list[dict[str, Any]] = []
    question_order = question_metrics["question"].tolist() if not question_metrics.empty else []

    # --- 2. Question Difficulty Analysis ------------------------------------
    if not pool_b_df.empty:
        pool_b_df["scaled_score"] = pool_b_df["grade"] * 10.0

    difficulty_charts = []
    if not ranked_difficulty.empty:
        difficulty_charts.append({
            "id": "difficulty-bar", "title": "Top Difficult Questions by Average Score",
            "plotly_json": _fig_to_json(build_difficulty_bar_figure(ranked_difficulty, req.colorblind_mode)),
        })
    if not pool_b_df.empty:
        difficulty_charts.append({
            "id": "score-box", "title": "Score Distribution by Question (Best Attempt per Student)",
            "plotly_json": _fig_to_json(build_score_boxplot_figure(pool_b_df, req.colorblind_mode)),
        })
    sections.append({
        "id": "difficulty",
        "title": "2. Question Difficulty Analysis",
        "table": _df_to_table(difficulty_metrics),
        "charts": difficulty_charts,
    })

    # --- 4. Question Response Distribution ----------------------------------
    has_prt_data = bool(response_df["response_text"].astype(str).str.strip().any()) if "response_text" in response_df.columns else False
    distribution_charts = []
    if has_prt_data and not response_outcomes.empty:
        distribution_charts.append({
            "id": "response-outcomes", "title": "Response Outcome Percentages (Best Attempts)",
            "plotly_json": _fig_to_json(build_response_outcome_figure(response_outcomes, req.colorblind_mode)),
        })
        distribution_charts.append({
            "id": "valid-invalid", "title": "Valid vs Invalid Attempts (All Attempts)",
            "plotly_json": _fig_to_json(build_valid_invalid_figure(question_metrics, req.colorblind_mode)),
        })
        heatmap_df = build_prt_pass_heatmap(prt_pass_rates, question_order, prt_frame)
        if not heatmap_df.empty and len(heatmap_df.columns):
            distribution_charts.append({
                "id": "prt-pass-heatmap", "title": "PRT Pass Heatmap",
                "plotly_json": _fig_to_json(build_prt_pass_heatmap_figure(heatmap_df, colorblind_mode=req.colorblind_mode)),
            })

    distribution_table = response_outcomes
    if not response_outcomes.empty:
        distribution_table = response_outcomes.merge(
            repeated_wrong_answers.drop(columns=["top_wrong_expressions"], errors="ignore"),
            on="question", how="left",
        )
    sections.append({
        "id": "response-distribution",
        "title": "4. Question Response Distribution",
        "table": _df_to_table(distribution_table),
        "charts": distribution_charts,
    })

    # --- 5. Student Performance Matrix --------------------------------------
    student_matrix_charts = []
    student_matrix_table = pd.DataFrame()
    if not pool_b_df.empty and question_order:
        student_matrix = build_student_matrix(pool_b_df, question_order)
        student_matrix_table = student_matrix.reset_index()
        student_matrix_charts.append({
            "id": "student-heatmap", "title": None,
            "plotly_json": _fig_to_json(build_student_matrix_figure(student_matrix)),
        })
    sections.append({
        "id": "student-matrix",
        "title": "5. Student Performance Matrix",
        "table": _df_to_table(student_matrix_table),
        "charts": student_matrix_charts,
    })

    # --- 6. Question Metrics -------------------------------------------------
    metrics_table = pd.DataFrame()
    if not question_metrics.empty and not difficulty_metrics.empty:
        metrics_table = build_question_metrics_table(question_metrics, difficulty_metrics)
    sections.append({
        "id": "metrics",
        "title": "6. Question Metrics",
        "table": _df_to_table(metrics_table),
    })

    # --- Per-question detail (drives the PHP question <select>) ------------
    questions: dict[str, Any] = {}
    for q in question_order:
        detail = build_question_detail(pool_b_df, q)
        clean_text, _dump = split_stack_debug_dump(detail["question_text"])
        clean_answer, _ = split_stack_debug_dump(detail["right_answer_text"])

        drilldown = build_error_drilldown(pool_b_df, q)
        if not drilldown.empty:
            drilldown = drilldown.copy()
            drilldown["Submitted Response"] = drilldown["Submitted Response"].apply(extract_stack_answer_latex)
            drilldown["Right Answer"] = drilldown["Right Answer"].apply(extract_stack_answer_latex)

        questions[q] = {
            # Trusted, teacher-authored content (from Moodle's own question
            # rendering) — kept mostly as-is (real HTML + STACK's native
            # \( \)/\[ \] delimiters, which is exactly what the KaTeX
            # auto-render config on the PHP side expects) rather than run
            # through latex_utils.clean_moodle_latex(), which converts those
            # delimiters to $ .. $ for Streamlit's markdown renderer instead.
            "question_text_html": clean_text,
            "right_answer_html": clean_answer,
            "error_drilldown": _df_to_table(drilldown),
        }

    audit = audit_question_data(response_df)

    return {
        "quiz_name": req.quiz_name,
        "summary": summary,
        "sections": sections,
        "questions": questions,
        "audit": audit,
    }


@app.post("/analyze-course")
def analyze_course(req: AnalyzeCourseRequest) -> dict[str, Any]:
    """Cross-quiz analysis for a whole course — for the course-level entry
    point described as a next step in the plugin README. Not yet called by
    the PHP plugin included so far, but ready for it."""
    frames = []
    for quiz_name, records in req.quizzes.items():
        moodle_df = _records_to_moodle_df(records)
        if moodle_df.empty:
            continue
        rdf = build_response_rows(moodle_df, quiz_name=quiz_name)
        if not rdf.empty:
            frames.append(rdf)

    if not frames:
        raise HTTPException(status_code=422, detail="No gradable attempts parsed for any quiz.")

    combined = pd.concat(frames, ignore_index=True)
    attempt_frame = build_quiz_attempt_frame(combined)
    stats_df = compute_quiz_stats(
        attempt_frame,
        selected_stats=["student_count", "mean_grade", "grade_variance", "attempt_count", "attempt_rate"],
    )

    figures = [
        {"title": "Grade distribution by quiz", "plotly_json": _fig_to_json(build_boxplot_figure(attempt_frame))},
    ]
    engagement_fig = build_engagement_figure(attempt_frame)
    if engagement_fig is not None:
        figures.append({"title": "Engagement over time", "plotly_json": _fig_to_json(engagement_fig)})

    return {
        "summary": {"course_name": req.course_name, "quizzes_analyzed": stats_df.to_dict(orient="records")},
        "figures": figures,
    }


@app.post("/solution-process/meta")
def solution_process_meta(req: SolutionProcessMetaRequest) -> dict[str, Any]:
    """Cheap metadata for populating quiz_solutionprocess's question/part/
    student selectors — no graph or tree-edit-distance computation, safe to
    call on every page load unlike POST /solution-process itself."""
    moodle_df = _records_to_moodle_df(req.records)
    if moodle_df.empty:
        raise HTTPException(status_code=400, detail="No records supplied.")

    response_df = build_response_rows(moodle_df, quiz_name=req.quiz_name)
    if response_df.empty:
        raise HTTPException(
            status_code=422,
            detail="No finished, gradable attempts parsed from the supplied records.",
        )

    pool_a_df, _ = get_attempt_pools(response_df)
    question_names = sorted(pool_a_df["question"].unique(), key=_q_num)
    questions = [{"name": q, "parts": count_question_parts(pool_a_df, q)} for q in question_names]

    student_rows = pool_a_df[["student_id", "student_name"]].drop_duplicates().sort_values("student_name")
    students = [{"id": r.student_id, "name": r.student_name} for r in student_rows.itertuples(index=False)]

    return {"questions": questions, "students": students}


@app.post("/solution-process")
def solution_process(req: SolutionProcessRequest) -> dict[str, Any]:
    """Solution Process Visualization for one (question, part) of a quiz —
    the class-wide transition graph, per-node network features, PRT/TED 3D
    distance charts, and cross-attempt comparison. Optionally a single
    student's own drill-down (their transition path + their own metric
    trend across attempts) when `student_id` is supplied.

    Uses Pool A (every attempt, not just each student's best) throughout —
    unlike Question Analysis, seeing retries is the whole point here.
    """
    moodle_df = _records_to_moodle_df(req.records)
    if moodle_df.empty:
        raise HTTPException(status_code=400, detail="No records supplied.")

    response_df = build_response_rows(moodle_df, quiz_name=req.quiz_name)
    if response_df.empty:
        raise HTTPException(
            status_code=422,
            detail="No finished, gradable attempts parsed from the supplied records.",
        )

    pool_a_df, _ = get_attempt_pools(response_df)
    if req.question not in set(pool_a_df["question"]):
        raise HTTPException(status_code=422, detail=f"Unknown question: {req.question!r}")

    # A part the caller asked for may not exist on this question — fall back
    # to the last one that does, same as report_sections.build_spv_pdf_sections.
    part_index = min(req.part_index, count_question_parts(pool_a_df, req.question))

    sections: list[dict[str, Any]] = []

    agg_nodes, agg_edges = build_aggregate_graph(pool_a_df, req.question, part_index)
    if agg_edges:
        transition_fig = build_transition_graph_figure(
            agg_nodes, agg_edges, colorblind_mode=req.colorblind_mode,
            title=f"Class-wide Answer Transitions — {req.question} (part {part_index})",
        )
        sections.append({
            "id": "transition-graph",
            "title": "Class-Wide Transition Graph",
            "caption": "Edge thickness and color scale with how many students made each transition.",
            "charts": [{"id": "agg-graph", "title": None, "plotly_json": _fig_to_json(transition_fig)}],
        })

        network_features = compute_network_features(agg_nodes, agg_edges)
        centrality_charts = [
            {"id": c["metric"], "title": c["label"], "plotly_json": _fig_to_json(c["figure"])}
            for c in build_centrality_bar_figures(network_features)
        ]
        sections.append({
            "id": "network-features",
            "title": "Network Features per Node",
            "table": _df_to_table(network_features),
            "charts": centrality_charts,
        })

    prt_fig = build_prt_distance_3d_figure(pool_a_df, req.question, part_index)
    sections.append({
        "id": "prt-distance-3d",
        "title": "PRT-Distance 3D Chart",
        "charts": [{"id": "prt-3d", "title": None, "plotly_json": _fig_to_json(prt_fig)}],
    })

    ted_fig = build_ted_distance_3d_figure(pool_a_df, req.question, part_index)
    sections.append({
        "id": "ted-distance-3d",
        "title": "Tree Edit Distance 3D Chart",
        "charts": [{"id": "ted-3d", "title": None, "plotly_json": _fig_to_json(ted_fig)}],
    })

    # Grade matches report_sections._DEFAULT_CROSS_ATTEMPT_METRIC — the report
    # has no page-side control for which metric to compare by either, and
    # Grade has the advantage of not depending on which part is selected.
    metric = "Grade"
    higher_is_better = bool(CROSS_ATTEMPT_METRICS[metric]["higher_is_better"])
    comparison = compute_cross_attempt_comparison(pool_a_df, req.question, metric, part_index)
    trends = classify_cross_attempt_trends(comparison, higher_is_better)
    if not comparison.empty:
        cross_fig = build_cross_attempt_figure(comparison, trends, metric, req.colorblind_mode)
        counts = trends["trend"].value_counts()
        ranking_table = trends.rename(columns={
            "student_name": "Student Name", "attempt_count": "Attempts",
            "first_value": "First Attempt", "last_value": "Last Attempt",
            "change": "Change", "trend": "Trend",
        })[["Student Name", "Attempts", "First Attempt", "Last Attempt", "Change", "Trend"]]
        sections.append({
            "id": "cross-attempt",
            "title": f"Cross-Attempt Comparison ({metric})",
            "caption": (
                f"{int(counts.get('Improved', 0))} improved, "
                f"{int(counts.get('Flat', 0))} flat, {int(counts.get('Regressed', 0))} regressed "
                "among students with 2+ attempts."
            ),
            "table": _df_to_table(ranking_table),
            "charts": [{"id": "cross-attempt-fig", "title": None, "plotly_json": _fig_to_json(cross_fig)}],
        })

    student_drilldown = None
    if req.student_id:
        name_rows = pool_a_df.loc[pool_a_df["student_id"] == req.student_id, "student_name"]
        student_name = name_rows.iloc[0] if not name_rows.empty else req.student_id
        student_sections: list[dict[str, Any]] = []

        seq = build_student_node_sequence(pool_a_df, req.question, req.student_id, part_index)
        if not seq.empty:
            seq_nodes = seq["node"].tolist()
            student_edges = Counter(build_transition_pairs(seq_nodes))
            student_nodes = sorted(set(seq_nodes) | {"0", "c"})
            student_fig = build_transition_graph_figure(
                student_nodes, student_edges, colorblind_mode=req.colorblind_mode,
                title=f"{student_name} — {req.question} (part {part_index})",
            )
            student_sections.append({
                "id": "student-transition",
                "title": "This Student's Transition Path",
                "charts": [{"id": "student-graph", "title": None, "plotly_json": _fig_to_json(student_fig)}],
            })

        student_comparison = comparison[comparison["student_id"] == req.student_id]
        if len(student_comparison) >= 2:
            trend_rows = trends[trends["student_id"] == req.student_id]
            trend = trend_rows["trend"].iloc[0] if not trend_rows.empty else "Flat"
            student_cross_fig = build_single_student_attempt_figure(
                student_comparison, student_name, metric, trend, req.colorblind_mode,
            )
            student_sections.append({
                "id": "student-cross-attempt",
                "title": f"This Student's {metric} Across Attempts",
                "charts": [{"id": "student-cross-fig", "title": None, "plotly_json": _fig_to_json(student_cross_fig)}],
            })

        student_drilldown = {
            "student_id": req.student_id,
            "student_name": student_name,
            "sections": student_sections,
        }

    return {
        "question": req.question,
        "part_index": part_index,
        "sections": sections,
        "student_drilldown": student_drilldown,
    }
