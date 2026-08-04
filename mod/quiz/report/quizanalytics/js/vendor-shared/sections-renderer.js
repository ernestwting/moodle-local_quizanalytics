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
        // Pass the element itself, not container.id: sections are built in a
        // detached wrapper div before being appended to the live document
        // (see renderSection), so an ID-based document.getElementById()
        // lookup here would return null and Plotly.newPlot would throw,
        // silently aborting the rest of the section-rendering loop.
        global.Plotly.newPlot(container, chart.plotly_json.data, chart.plotly_json.layout, {
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

    var questionBlockCounter = 0;

    function renderQuestionDetails(root, prefix, questions) {
        var names = Object.keys(questions || {});
        if (!names.length) {
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'qa-section';
        wrapper.id = prefix + '-section-questiondetails';

        var heading = document.createElement('h4');
        heading.textContent = '3. Question Item Details & Error Drill-Down';
        wrapper.appendChild(heading);

        var selectId = prefix + '-question-select-' + (questionBlockCounter++);
        var label = document.createElement('label');
        label.setAttribute('for', selectId);
        label.textContent = 'Question: ';
        wrapper.appendChild(label);

        var select = document.createElement('select');
        select.id = selectId;
        names.forEach(function (name) {
            var option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            select.appendChild(option);
        });
        wrapper.appendChild(select);

        var blocksRoot = document.createElement('div');
        names.forEach(function (name, i) {
            var detail = questions[name];
            var block = document.createElement('div');
            block.className = 'qa-question-block';
            block.setAttribute('data-question', name);
            block.style.display = (i === 0) ? 'block' : 'none';
            block.style.marginTop = '1rem';

            var qHeading = document.createElement('h5');
            qHeading.textContent = 'Question text';
            block.appendChild(qHeading);
            var qText = document.createElement('div');
            qText.innerHTML = detail.question_text_html || '';
            block.appendChild(qText);

            var aHeading = document.createElement('h5');
            aHeading.textContent = 'Right answer';
            block.appendChild(aHeading);
            var aText = document.createElement('div');
            aText.innerHTML = detail.right_answer_html || '';
            block.appendChild(aText);

            var dHeading = document.createElement('h5');
            dHeading.textContent = 'Error drill-down (best attempt, score < 1.0)';
            block.appendChild(dHeading);
            renderDataTable(block, detail.error_drilldown);

            blocksRoot.appendChild(block);
        });
        wrapper.appendChild(blocksRoot);

        select.addEventListener('change', function () {
            var chosen = select.value;
            Array.prototype.forEach.call(blocksRoot.children, function (block) {
                block.style.display = (block.getAttribute('data-question') === chosen) ? 'block' : 'none';
            });
        });

        root.appendChild(wrapper);
        typesetMath(wrapper);
    }

    function renderAudit(root, prefix, audit) {
        if (!audit) {
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'qa-section';
        wrapper.id = prefix + '-section-audit';

        var heading = document.createElement('h4');
        heading.textContent = '7. Interpretation Notes & Export';
        wrapper.appendChild(heading);

        if (audit.issues && audit.issues.length) {
            renderNotes(wrapper, audit.issues);
        } else {
            var ok = document.createElement('p');
            ok.textContent = 'No data-quality issues detected.';
            wrapper.appendChild(ok);
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
                // STACK/Moodle's own question text uses \( \) / \[ \] natively.
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true},
                // analytics.latex_utils.extract_stack_answer_latex() and
                // compute_repeated_wrong_answers() wrap converted Maxima
                // expressions in single/double $ instead (the Streamlit/
                // MathJax convention) — recognized in addition since these
                // strings are entirely programmatically constructed by
                // those two functions, not free-form text where a literal
                // "$" could cause a false-positive match.
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
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
            // Sections 1-2 come first (summary is rendered separately above;
            // "2. Question Difficulty Analysis" is sections[0]), then the
            // per-question drill-down (section "3.") slots in before
            // "4. Question Response Distribution" onward, matching the
            // Streamlit page's section ordering.
            result.sections.forEach(function (section) {
                renderSection(sectionsRoot, section);
                if (section.id === 'difficulty') {
                    renderQuestionDetails(sectionsRoot, prefix, result.questions);
                }
            });
            renderAudit(sectionsRoot, prefix, result.audit);
        } else if (sectionsRoot && Array.isArray(result.figures)) {
            renderLegacyFigures(sectionsRoot, result.figures);
        }
    }

    global.QuizAnalyticsRenderer = {render: render};
})(window);
