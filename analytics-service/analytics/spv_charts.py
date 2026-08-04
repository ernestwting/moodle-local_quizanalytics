from __future__ import annotations

import pandas as pd
import plotly.express as px
import plotly.graph_objects as go

# Chart-construction logic for the Solution Process Visualization section,
# extracted out of report_sections.py::build_spv_pdf_sections (where it was
# duplicated inline) so the on-screen API route (app.py) and the PDF route
# can both call the exact same functions. See report_sections.py for the
# original inline version this was lifted from.

_CENTRALITY_METRICS = (
    ("in_degree_centrality", "In-Degree Centrality"),
    ("out_degree_centrality", "Out-Degree Centrality"),
    ("degree_centrality", "Degree Centrality"),
)


def build_centrality_bar_figures(network_features: pd.DataFrame) -> list[dict[str, object]]:
    """The three in/out/degree-centrality bar charts, one per node, in
    prt_transitions.compute_network_features()'s node order. Returns a list
    of {"metric", "label", "figure"} dicts rather than a single figure since
    there are three independent charts here."""
    node_order = network_features["node"].tolist()
    charts = []
    for metric, label in _CENTRALITY_METRICS:
        fig = px.bar(
            network_features, x="node", y=metric,
            category_orders={"node": node_order},
            labels={"node": "Node", metric: label},
        )
        fig.update_traces(marker_color="#3b82f6")
        fig.update_xaxes(type="category")
        fig.update_layout(title=label, showlegend=False)
        charts.append({"metric": metric, "label": label, "figure": fig})
    return charts
