// Generic renderer for the {summary, sections} contract shared by
// quiz_quizanalytics, quiz_solutionprocess, and local_quizanalytics.
//
// Expected shape of a `result` object passed to QuizAnalyticsRenderer.render():
// {
//   "summary": { "student_count": 34, "mean_grade": 7.1, ... },
//   "sections": [
//     {
//       "id": "difficulty",
//       "title": "2. Question Difficulty Analysis",
//       "caption": "...",
//       "table": { "columns": ["question", "difficulty_index"], "rows": [["Q1", 0.62]] },
//       "charts": [ { "id": "difficulty-bar", "title": "Top Difficult Questions", "plotly_json": {"data": [...], "layout": {...}} } ],
//       "notes": ["..."]
//     }
//   ]
// }
//
// Backwards-compatible fallback: if `result.figures` is present instead of
// `result.sections` (the shape /analyze returned before Question Analysis
// enrichment), each figure is rendered flat with its own heading — this
// reproduces the exact pre-enrichment output with no visible change.
//
// After injecting each container's HTML, KaTeX's auto-render extension is
// run over it so any \( \)/\[ \] delimited math in table cells or notes
// renders as real math. Must run per-container (not once globally) since
// content is injected dynamically after this script's own top-level code
// has already executed.
(function (global) {
    'use strict';

    function renderSummaryTable(root, summary) {
        if (!root || !summary) {
            return;
        }
        var table = document.createElement('table');
        table.className = 'generaltable';
        Object.keys(summary).forEach(function (key) {
            var row = table.insertRow();
            row.insertCell().textContent = key;
            var value = summary[key];
            row.insertCell().textContent = (value === null || value === undefined) ? '' : String(value);
        });
        root.appendChild(table);
    }

    function renderDataTable(root, table) {
        if (!table || !table.columns || !table.rows) {
            return;
        }
        var el = document.createElement('table');
        el.className = 'generaltable';
        var thead = el.createTHead();
        var headRow = thead.insertRow();
        table.columns.forEach(function (col) {
            var th = document.createElement('th');
            th.textContent = col;
            headRow.appendChild(th);
        });
        var tbody = el.createTBody();
        table.rows.forEach(function (rowValues) {
            var row = tbody.insertRow();
            rowValues.forEach(function (value) {
                var cell = row.insertCell();
                cell.innerHTML = (value === null || value === undefined) ? '' : String(value);
            });
        });
        root.appendChild(el);
    }

    var chartCounter = 0;

    function renderChart(root, chart) {
        if (!chart || !chart.plotly_json) {
            return;
        }
        if (chart.title) {
            var heading = document.createElement('h5');
            heading.textContent = chart.title;
            root.appendChild(heading);
        }
        var container = document.createElement('div');
        container.id = chart.id ? ('qa-chart-' + chart.id) : ('qa-chart-auto-' + (chartCounter++));
        container.style.marginBottom = '2rem';
        root.appendChild(container);
        global.Plotly.newPlot(container.id, chart.plotly_json.data, chart.plotly_json.layout, {
            responsive: true,
        });
    }

    function renderNotes(root, notes) {
        if (!notes || !notes.length) {
            return;
        }
        var list = document.createElement('ul');
        notes.forEach(function (note) {
            var item = document.createElement('li');
            item.innerHTML = note;
            list.appendChild(item);
        });
        root.appendChild(list);
    }

    function renderSection(root, section) {
        var wrapper = document.createElement('div');
        wrapper.className = 'qa-section';
        if (section.id) {
            wrapper.id = 'qa-section-' + section.id;
        }
        if (section.title) {
            var heading = document.createElement('h4');
            heading.textContent = section.title;
            wrapper.appendChild(heading);
        }
        if (section.caption) {
            var caption = document.createElement('p');
            caption.textContent = section.caption;
            wrapper.appendChild(caption);
        }
        if (section.table) {
            renderDataTable(wrapper, section.table);
        }
        if (section.charts) {
            section.charts.forEach(function (chart) {
                renderChart(wrapper, chart);
            });
        }
        if (section.notes) {
            renderNotes(wrapper, section.notes);
        }
        root.appendChild(wrapper);
        typesetMath(wrapper);
    }

    function renderLegacyFigures(root, figures) {
        figures.forEach(function (fig, i) {
            var heading = document.createElement('h4');
            heading.textContent = fig.title || ('Chart ' + (i + 1));
            root.appendChild(heading);

            var container = document.createElement('div');
            container.id = 'qa-chart-' + i;
            container.style.marginBottom = '2rem';
            root.appendChild(container);

            global.Plotly.newPlot(container.id, fig.plotly_json.data, fig.plotly_json.layout, {
                responsive: true,
            });
        });
    }

    function typesetMath(container) {
        if (typeof global.renderMathInElement !== 'function') {
            return;
        }
        global.renderMathInElement(container, {
            delimiters: [
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true},
            ],
            throwOnError: false,
        });
    }

    /**
     * @param {string} prefix DOM id prefix — e.g. "qa" gives #qa-summary/#qa-sections.
     * @param {object} result The {summary, sections} (or legacy {summary, figures}) payload.
     */
    function render(prefix, result) {
        var summaryRoot = document.getElementById(prefix + '-summary');
        var sectionsRoot = document.getElementById(prefix + '-sections');
        if (!result) {
            return;
        }

        renderSummaryTable(summaryRoot, result.summary);
        typesetMath(summaryRoot);

        if (sectionsRoot && Array.isArray(result.sections)) {
            result.sections.forEach(function (section) {
                renderSection(sectionsRoot, section);
            });
        } else if (sectionsRoot && Array.isArray(result.figures)) {
            renderLegacyFigures(sectionsRoot, result.figures);
        }
    }

    global.QuizAnalyticsRenderer = {render: render};
})(window);
