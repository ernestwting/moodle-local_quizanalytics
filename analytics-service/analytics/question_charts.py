from __future__ import annotations

import pandas as pd
import plotly.express as px
import plotly.graph_objects as go

from analytics.ui_theme import qualitative_colors

# Chart-construction logic for the Question Analysis section, extracted out of
# report_sections.py::build_question_pdf_sections (where it was duplicated
# inline) so the on-screen API route (app.py) and the PDF route can both call
# the exact same functions and never drift apart. See report_sections.py for
# the original inline versions this was lifted from.


def build_difficulty_bar_figure(ranked_difficulty: pd.DataFrame, colorblind_mode: bool = False) -> go.Figure:
    """"Top Difficult Questions by Average Score" — hardest 10 questions, ranked_difficulty
    is already sorted ascending by avg_score (see question_metrics.compute_ranked_difficulty)."""
    fig = px.bar(
        ranked_difficulty.head(10), x="question", y="avg_score", color="question",
        color_discrete_sequence=qualitative_colors(colorblind_mode, px.colors.qualitative.Set2),
        labels={"avg_score": "Average score", "question": "Question"},
    )
    fig.update_layout(title="Top Difficult Questions by Average Score", showlegend=False, template="plotly")
    return fig


def build_score_boxplot_figure(pool_b_df: pd.DataFrame, colorblind_mode: bool = False) -> go.Figure:
    """"Score Distribution by Question (Best Attempt per Student)" — expects pool_b_df to
    already have a `scaled_score` column (grade * 10.0)."""
    fig = px.box(
        pool_b_df, x="question", y="scaled_score", color="question",
        color_discrete_sequence=qualitative_colors(colorblind_mode, px.colors.qualitative.Set2),
        labels={"scaled_score": "Score (0-10)", "question": "Question"},
    )
    fig.update_layout(title="Score Distribution by Question (Best Attempt per Student)", showlegend=False, template="plotly")
    return fig


def build_response_outcome_figure(response_outcomes: pd.DataFrame, colorblind_mode: bool = False) -> go.Figure:
    """"Response Outcome Percentages (Best Attempts)" — correct_percent/incorrect_percent
    grouped bars per question."""
    fig = px.bar(
        response_outcomes, x="question", y=["correct_percent", "incorrect_percent"],
        barmode="group", color_discrete_sequence=qualitative_colors(colorblind_mode, px.colors.qualitative.Vivid),
        labels={"value": "Percent", "question": "Question"},
    )
    fig.update_layout(title="Response Outcome Percentages (Best Attempts)", template="plotly")
    return fig


def build_valid_invalid_figure(question_metrics: pd.DataFrame, colorblind_mode: bool = False) -> go.Figure:
    """"Valid vs Invalid Attempts (All Attempts)" — percent_valid/percent_invalid grouped
    bars per question, from question_metrics (Pool A based)."""
    valid_invalid_q = pd.DataFrame({
        "question": question_metrics["question"],
        "Valid %": question_metrics["percent_valid"],
        "Invalid/Syntax Error %": question_metrics["percent_invalid"],
    })
    fig = px.bar(
        valid_invalid_q, x="question", y=["Valid %", "Invalid/Syntax Error %"],
        barmode="group", color_discrete_sequence=qualitative_colors(colorblind_mode, px.colors.qualitative.Vivid),
        labels={"value": "Percent", "question": "Question"},
    )
    fig.update_layout(title="Valid vs Invalid Attempts (All Attempts)", template="plotly")
    return fig


def build_student_matrix(pool_b_df: pd.DataFrame, question_order: list[str]) -> pd.DataFrame:
    """Student x Question pivot table (grade, 0.0-1.0), used both for the heatmap figure
    below and as the section's raw data table."""
    return pool_b_df.pivot_table(
        index="student_id", columns="question", values="grade",
        aggfunc="first", fill_value=0.0, dropna=False,
    ).reindex(columns=question_order, fill_value=0.0)


def build_student_matrix_figure(student_matrix: pd.DataFrame) -> go.Figure:
    """"Student-by-Question Performance Matrix (Best Attempts)" heatmap, from the pivot
    table build_student_matrix() produces."""
    fig = px.imshow(
        student_matrix, labels=dict(x="Question", y="Student", color="Score"),
        color_continuous_scale="Viridis",
    )
    fig.update_xaxes(tickmode="array", tickvals=list(range(len(student_matrix.columns))), ticktext=[str(c) for c in student_matrix.columns])
    fig.update_yaxes(tickmode="array", tickvals=list(range(len(student_matrix.index))), ticktext=[str(r) for r in student_matrix.index])
    chart_height = max(400, 24 * len(student_matrix.index))
    fig.update_layout(title="Student-by-Question Performance Matrix (Best Attempts)", height=chart_height, template="plotly")
    return fig


def build_question_metrics_table(question_metrics: pd.DataFrame, difficulty_metrics: pd.DataFrame) -> pd.DataFrame:
    """The "6. Question Metrics" consolidated table — question_metrics merged with a
    handful of difficulty_metrics columns, discrimination_index renamed to discrimination."""
    merged = question_metrics.merge(
        difficulty_metrics[["question", "discrimination_index", "average_marks", "median_marks", "standard_deviation"]],
        on="question", how="left",
    )
    return merged[[
        "question", "attempts", "students", "invalid_rate", "blank_rate",
        "reattempt_share", "facility", "partial_credit_mean",
        "discrimination_index", "average_marks", "median_marks", "standard_deviation",
        "catch_all_share",
    ]].rename(columns={"discrimination_index": "discrimination"})
