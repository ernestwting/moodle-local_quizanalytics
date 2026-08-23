// Generic renderer for the {summary, sections} contract used by every view
// local_quizanalytics renders: course-wide comparison, per-quiz Question
// Analytics, and per-quiz Solution Process Visualization.
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

    // A handful of keys/columns come through as raw Python identifiers
    // (quiz_name, total_questions, invalid_rate, ...) since they double as
    // dict keys/DataFrame column names on the Python side — this turns those
    // into presentable labels for both the summary table and generic data
    // tables, without needing to rename anything Python-side code depends on.
    // Applying it to already-nice strings (e.g. "Quiz Name") is a no-op.
    var LABEL_OVERRIDES = {
        'id': 'ID', 'ids': 'IDs', 'prt': 'PRT', 'prts': 'PRTs',
        'ted': 'TED', 'stack': 'STACK', 'url': 'URL',
    };

    // A few raw column names abbreviate in a way that word-by-word
    // capitalization can't fix on its own ("attempt_idx" -> "Attempt Idx",
    // not the "Attempt Number" a reader actually wants) — matched against
    // the whole key, case-insensitively, before falling through to the
    // generic word-splitting logic below.
    var FULL_LABEL_OVERRIDES = {
        'attempt_idx': 'Attempt Number',
        'attempt_count': 'No. of Attempts',
        'completed_dt': 'Completed On',
        'first_grade': 'First Grade',
        'latest_grade': 'Latest Grade',
    };

    function humanizeLabel(key) {
        if (typeof key !== 'string' || !key) {
            return key;
        }
        var fullOverride = FULL_LABEL_OVERRIDES[key.toLowerCase()];
        if (fullOverride) {
            return fullOverride;
        }
        return key
            .replace(/[_\-]+/g, ' ')
            .trim()
            .split(/\s+/)
            .map(function (word) {
                var override = LABEL_OVERRIDES[word.toLowerCase()];
                if (override) {
                    return override;
                }
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join(' ');
    }

    // Matches the ISO-ish timestamps pandas emits for datetime columns
    // (completed_dt, started_on, ...), e.g. "2026-06-03T10:53:29.000" or
    // "2026-06-03 10:53:29" — reformatted to the reader's own locale instead
    // of shown as a raw sortable-but-not-readable timestamp string.
    var ISO_DATETIME_RE = /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?$/;

    function formatDateTimeValue(value) {
        if (typeof value !== 'string' || !ISO_DATETIME_RE.test(value)) {
            return null;
        }
        var parsed = new Date(value.replace(' ', 'T'));
        if (isNaN(parsed.getTime())) {
            return null;
        }
        return parsed.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    }

    // Long floating-point results (grade_variance, attempt_rate, and similar
    // computed stats) come through with full binary-float precision (e.g.
    // 1.2962962963) — displayed values are capped to 2 decimal places.
    // Integers (attempt counts, ids that happen to be numeric, etc.) are
    // left exactly as-is rather than padded to "4.00".
    function formatCellValue(value) {
        if (typeof value === 'number' && !Number.isInteger(value)) {
            return String(Math.round(value * 100) / 100);
        }
        var asDateTime = formatDateTimeValue(value);
        if (asDateTime !== null) {
            return asDateTime;
        }
        return (value === null || value === undefined) ? '' : String(value);
    }

    // Long tables (many student rows especially) get capped to a scrollable
    // viewport instead of pushing the rest of the page down — same idea as
    // the fixed-height, scrollable dataframe viewer in the Streamlit app this
    // mirrors. Width is always scrollable too, independent of row count,
    // since a wide table (e.g. Question Metrics' ~12 columns) can overflow
    // its column even with only a handful of rows.
    var SCROLL_ROW_THRESHOLD = 12;

    function wrapScrollable(el, rowCount) {
        var wrapper = document.createElement('div');
        wrapper.style.overflowX = 'auto';
        if (rowCount > SCROLL_ROW_THRESHOLD) {
            wrapper.style.maxHeight = '420px';
            wrapper.style.overflowY = 'auto';
            wrapper.style.border = '1px solid #dee2e6';
        }
        wrapper.appendChild(el);
        return wrapper;
    }

    function renderSummaryTable(root, summary) {
        if (!root || !summary) {
            return;
        }
        var table = document.createElement('table');
        table.className = 'generaltable';
        Object.keys(summary).forEach(function (key) {
            var row = table.insertRow();
            row.insertCell().textContent = humanizeLabel(key);
            row.insertCell().textContent = formatCellValue(summary[key]);
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
            th.textContent = humanizeLabel(col);
            headRow.appendChild(th);
        });
        var tbody = el.createTBody();
        table.rows.forEach(function (rowValues) {
            var row = tbody.insertRow();
            rowValues.forEach(function (value) {
                var cell = row.insertCell();
                cell.innerHTML = formatCellValue(value);
            });
        });
        root.appendChild(wrapScrollable(el, table.rows.length));
    }

    var chartCounter = 0;

    // A figure whose own layout sets a tall fixed height (e.g. the Student
    // Performance Matrix heatmap, sized to 24px per student row — see
    // question_charts.py::build_student_matrix_figure) gets capped to a
    // scrollable viewport instead of stretching the page, matching the
    // Streamlit app's own fixed-height chart containers.
    var CHART_SCROLL_HEIGHT_THRESHOLD = 500;
    var CHART_SCROLL_MAX_HEIGHT = 500;

    function renderChart(root, chart, prefix) {
        if (!chart || !chart.plotly_json) {
            return;
        }
        if (chart.title) {
            var heading = document.createElement('h5');
            heading.textContent = chart.title;
            root.appendChild(heading);
        }
        var container = document.createElement('div');
        container.id = chart.id ? (prefix + '-chart-' + chart.id) : (prefix + '-chart-auto-' + (chartCounter++));
        container.style.marginBottom = '2rem';

        // 3D scene charts (the PRT/TED distance charts) are exempt: their
        // height is a fixed, deliberate choice giving aspectmode="cube"
        // enough room to render the cube at a reasonable size (see
        // solution_distance.php's own comment on this), not a list that
        // grows unbounded with the data the way the student-performance
        // heatmap's height does — capping it to a small scrollable window
        // would just shrink the visible chart back down to the same
        // "looks tiny" problem that height was set to fix.
        var isScene3d = !!(chart.plotly_json.layout && chart.plotly_json.layout.scene);
        var declaredHeight = chart.plotly_json.layout && chart.plotly_json.layout.height;
        if (!isScene3d && declaredHeight && declaredHeight > CHART_SCROLL_HEIGHT_THRESHOLD) {
            var scrollWrapper = document.createElement('div');
            scrollWrapper.style.maxHeight = CHART_SCROLL_MAX_HEIGHT + 'px';
            scrollWrapper.style.overflowY = 'auto';
            scrollWrapper.style.overflowX = 'auto';
            scrollWrapper.style.border = '1px solid #dee2e6';
            scrollWrapper.style.marginBottom = '2rem';
            container.style.marginBottom = '0';
            scrollWrapper.appendChild(container);
            root.appendChild(scrollWrapper);
        } else {
            root.appendChild(container);
        }

        // Pass the element itself, not container.id: renderSection() appends
        // its wrapper to the live document before populating it (so this
        // container has real, laid-out dimensions for Plotly's "responsive"
        // sizing to measure), but document.getElementById() would still be
        // one more indirection than necessary — passing the element directly
        // also sidesteps a null lookup if that ever regresses.
        global.Plotly.newPlot(container, chart.plotly_json.data, chart.plotly_json.layout, {
            responsive: true,
        });
        return container;
    }

    function renderScatterControls(wrapper, section, prefix) {
        var variants = section.scatter_variants || {};
        var types = Object.keys(variants);
        if (!types.length) {
            return;
        }

        var controls = document.createElement('div');
        controls.className = 'mb-3';
        var label = document.createElement('span');
        label.textContent = 'Compare attempts against: ';
        controls.appendChild(label);

        var initial = section.selected_scatter_type || types[0];
        var chart = section.charts && section.charts[0];
        var chartContainer = renderChart(wrapper, chart, prefix);
        var caption = wrapper.querySelector('p');

        types.forEach(function (type) {
            var id = prefix + '-scatter-' + type.toLowerCase().replace(/[^a-z]+/g, '-');
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = prefix + '-scatter-grade-type';
            radio.id = id;
            radio.value = type;
            radio.checked = type === initial;
            radio.style.marginLeft = '0.75rem';

            var radioLabel = document.createElement('label');
            radioLabel.htmlFor = id;
            radioLabel.textContent = variants[type].label;
            radioLabel.style.marginLeft = '0.25rem';
            controls.appendChild(radio);
            controls.appendChild(radioLabel);

            radio.addEventListener('change', function () {
                var variant = variants[radio.value];
                global.Plotly.react(chartContainer, variant.plotly_json.data, variant.plotly_json.layout, {
                    responsive: true,
                });
                if (caption) {
                    caption.textContent = variant.caption;
                }
            });
        });

        wrapper.insertBefore(controls, chartContainer.parentNode === wrapper ? chartContainer : chartContainer.parentNode);
    }

    function trendMetricLabel(metric, options) {
        return options[metric] || humanizeLabel(metric);
    }

    function buildTrendView(chartJson, selected, options) {
        var traces = (chartJson.data || []).filter(function (trace) {
            return selected.indexOf(trace.meta) !== -1;
        }).map(function (trace) {
            return Object.assign({}, trace);
        });

        var spans = traces.map(function (trace) {
            var values = (trace.y || []).filter(function (value) {
                return typeof value === 'number' && isFinite(value);
            });
            if (!values.length) {
                return 0;
            }
            return Math.max.apply(null, values) - Math.min.apply(null, values);
        }).filter(function (span) {
            return span > 0;
        });
        var maxSpan = spans.length ? Math.max.apply(null, spans) : 0;
        var minSpan = spans.length ? Math.min.apply(null, spans) : 0;
        var separatePanels = selected.length > 1 && minSpan > 0 && maxSpan / minSpan >= 5;
        var layout = Object.assign({}, chartJson.layout, {
            title: {text: 'Quiz Metrics Across Quizzes'},
            yaxis: {title: {text: selected.length === 1 ? trendMetricLabel(selected[0], options) : 'Value'}, autorange: true},
        });

        if (separatePanels) {
            layout.grid = {rows: selected.length, columns: 1, pattern: 'independent', roworder: 'top to bottom'};
            layout.height = Math.max(420, selected.length * 240);
            selected.forEach(function (metric, index) {
                var suffix = index === 0 ? '' : String(index + 1);
                var axis = 'y' + suffix;
                var xaxis = 'x' + suffix;
                layout[axis] = {title: {text: trendMetricLabel(metric, options)}, autorange: true};
                layout[xaxis] = {type: 'category', title: {text: 'Quiz'}, tickangle: 0, tickfont: {size: 10}};
                if (traces[index]) {
                    traces[index].yaxis = axis;
                    traces[index].xaxis = xaxis;
                }
            });
        } else {
            layout.autosize = true;
            delete layout.height;
            traces.forEach(function (trace) {
                trace.yaxis = 'y';
                trace.xaxis = 'x';
            });
        }

        return {data: traces, layout: layout};
    }

    function renderTrendControls(wrapper, section, prefix) {
        var options = section.metric_options || {};
        var metrics = Object.keys(options);
        var chart = section.charts && section.charts[0];
        if (!metrics.length || !chart || !chart.plotly_json) {
            return;
        }

        var selected = (section.selected_metrics || metrics).slice();
        var chartContainer = wrapper.querySelector('[id$="-chart-' + chart.id + '"]');
        if (!chartContainer) {
            return;
        }
        var controls = document.createElement('div');
        controls.className = 'mb-3';
        var label = document.createElement('span');
        label.textContent = 'Show metrics: ';
        controls.appendChild(label);

        metrics.forEach(function (metric) {
            var id = prefix + '-metric-' + metric;
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = id;
            checkbox.checked = selected.indexOf(metric) !== -1;
            checkbox.style.marginLeft = '0.75rem';
            var checkboxLabel = document.createElement('label');
            checkboxLabel.htmlFor = id;
            checkboxLabel.textContent = options[metric];
            checkboxLabel.style.marginLeft = '0.25rem';
            controls.appendChild(checkbox);
            controls.appendChild(checkboxLabel);

            checkbox.addEventListener('change', function () {
                selected = metrics.filter(function (name) {
                    return document.getElementById(prefix + '-metric-' + name).checked;
                });
                if (!selected.length) {
                    selected = [metric];
                    checkbox.checked = true;
                }
                var view = buildTrendView(chart.plotly_json, selected, options);
                global.Plotly.react(chartContainer, view.data, view.layout, {responsive: true});
            });
        });

        var initialView = buildTrendView(chart.plotly_json, selected, options);
        global.Plotly.react(chartContainer, initialView.data, initialView.layout, {responsive: true});
        wrapper.insertBefore(controls, chartContainer);
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
        // Same scrollable-viewport treatment as long data tables (e.g. a
        // grade-mismatch audit can list one line per affected student) —
        // reuses wrapScrollable's row-count threshold, one <li> per row.
        root.appendChild(wrapScrollable(list, notes.length));
    }

    // Turns each row of the Cross-Attempt Comparison table into a link that
    // reloads the current page with that student selected as the drill-down
    // (?spvstudent=...) — same plain GET-reload convention as every other
    // selector in this plugin family, so the result is still a cacheable
    // URL, just reached with one click on a row instead of picking the
    // student from the dropdown above and clicking Go. `studentIds` is
    // section.row_student_ids from the API response, parallel to the
    // table's own rows (see app.py's /solution-process route).
    function makeCrossAttemptRowsClickable(sectionRoot, studentIds) {
        var table = sectionRoot.querySelector('table');
        if (!table || !table.tBodies.length) {
            return;
        }
        var rows = table.tBodies[0].rows;
        Array.prototype.forEach.call(rows, function (row, i) {
            var studentId = studentIds[i];
            var nameCell = row.cells[0]; // "Student Name" is always the first column here.
            if (!studentId || !nameCell) {
                return;
            }
            var url = new URL(global.location.href);
            url.searchParams.set('spvstudent', studentId);

            var link = document.createElement('a');
            link.href = url.toString();
            link.textContent = nameCell.textContent;
            nameCell.textContent = '';
            nameCell.appendChild(link);
        });
    }

    // Every section wrapper gets the same top rule + generous top/bottom
    // spacing, so consecutive sections (and the "Generate PDF Report" block
    // right after the last one) read as clearly separate blocks instead of
    // running straight into each other — there was previously no CSS at all
    // marking this boundary, just whatever margin a section's own last
    // element (a table, a scrollable notes list, ...) happened to have.
    function styleSectionWrapper(wrapper) {
        wrapper.style.borderTop = '1px solid #dee2e6';
        wrapper.style.marginTop = '2rem';
        wrapper.style.paddingTop = '1.5rem';
        wrapper.style.marginBottom = '1rem';
    }

    function renderSection(root, section, prefix) {
        var wrapper = document.createElement('div');
        wrapper.className = 'qa-section';
        styleSectionWrapper(wrapper);
        if (section.id) {
            wrapper.id = prefix + '-section-' + section.id;
        }
        // Attached to the live document BEFORE being populated: a chart drawn
        // into a still-detached container has no real layout width for
        // Plotly's "responsive" sizing to measure (getBoundingClientRect()
        // on a detached element is 0x0), so it falls back to Plotly's small
        // default size and never grows to fill the page afterward — nothing
        // re-triggers that measurement once the wrapper is later attached.
        // This was the cause of charts rendering visibly narrower than their
        // surrounding text.
        root.appendChild(wrapper);
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
            if (section.id === 'cross-attempt' && Array.isArray(section.row_student_ids)) {
                makeCrossAttemptRowsClickable(wrapper, section.row_student_ids);
            }
        }
        if (section.id === 'scatter' && section.scatter_variants) {
            renderScatterControls(wrapper, section, prefix);
        } else if (section.id === 'trend' && section.metric_options) {
            section.charts.forEach(function (chart) {
                renderChart(wrapper, chart, prefix);
            });
            renderTrendControls(wrapper, section, prefix);
        } else if (section.charts) {
            section.charts.forEach(function (chart) {
                renderChart(wrapper, chart, prefix);
            });
        }
        if (section.notes) {
            renderNotes(wrapper, section.notes);
        }
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
        styleSectionWrapper(wrapper);
        wrapper.id = prefix + '-section-questiondetails';

        var heading = document.createElement('h4');
        heading.textContent = '3. Question Item Details & Error Drill-Down';
        wrapper.appendChild(heading);

        var caption = document.createElement('p');
        caption.textContent = 'The question as students see it, the correct answer for ' +
            'each part, and — for students who didn’t get full credit on their best ' +
            'attempt — what they actually submitted.';
        wrapper.appendChild(caption);

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
        styleSectionWrapper(wrapper);
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

    function renderStudentDrilldown(root, prefix, drilldown) {
        if (!drilldown || !drilldown.sections || !drilldown.sections.length) {
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'qa-section';
        styleSectionWrapper(wrapper);
        wrapper.id = prefix + '-section-student-drilldown';
        // Attached before populating — renderSection() below draws charts
        // into it, which need a live (attached) ancestor to size against;
        // see the matching comment inside renderSection() for the full story.
        root.appendChild(wrapper);

        var heading = document.createElement('h4');
        heading.textContent = 'Student drill-down: ' + (drilldown.student_name || drilldown.student_id || '');
        wrapper.appendChild(heading);

        drilldown.sections.forEach(function (section) {
            renderSection(wrapper, section, prefix);
        });

        typesetMath(wrapper);
    }

    function renderLegacyFigures(root, figures, prefix) {
        figures.forEach(function (fig, i) {
            var heading = document.createElement('h4');
            heading.textContent = fig.title || ('Chart ' + (i + 1));
            root.appendChild(heading);

            var container = document.createElement('div');
            container.id = prefix + '-chart-' + i;
            container.style.marginBottom = '2rem';
            root.appendChild(container);

            global.Plotly.newPlot(container, fig.plotly_json.data, fig.plotly_json.layout, {
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
                renderSection(sectionsRoot, section, prefix);
                if (section.id === 'difficulty') {
                    renderQuestionDetails(sectionsRoot, prefix, result.questions);
                }
            });
            renderAudit(sectionsRoot, prefix, result.audit);
            renderStudentDrilldown(sectionsRoot, prefix, result.student_drilldown);
        } else if (sectionsRoot && Array.isArray(result.figures)) {
            renderLegacyFigures(sectionsRoot, result.figures, prefix);
        }
    }

    // --- PDF export: client-side chart capture -----------------------------
    //
    // pdf.php has no headless-browser/rasterization dependency (the whole
    // point of this plugin being pure PHP), so it can't turn a Plotly figure
    // into an image itself. Instead, on "Generate PDF Report" click, every
    // chart already drawn on this page (by renderChart() above, into a
    // "{prefix}-chart-{chart.id}" container) is snapshotted client-side via
    // Plotly's own toImage(), and the resulting PNG data URLs are POSTed
    // alongside the section checkboxes — pdf.php embeds whichever ones match
    // the sections it re-derives server-side, and shows a plain "unavailable"
    // note for any section whose chart wasn't captured (e.g. a chart added
    // to the page after this script last ran).
    function collectChartImages(prefix) {
        var selector = '[id^="' + prefix + '-chart-"]';
        var containers = document.querySelectorAll(selector);
        var ids = [];
        var promises = [];
        Array.prototype.forEach.call(containers, function (container) {
            ids.push(container.id.slice(prefix.length + 7)); // strip "{prefix}-chart-"
            promises.push(global.Plotly.toImage(container, {
                format: 'png',
                width: container.offsetWidth || 800,
                height: container.offsetHeight || 400,
            }));
        });
        return Promise.all(promises).then(function (images) {
            var map = {};
            ids.forEach(function (chartId, i) {
                map[chartId] = images[i];
            });
            return map;
        });
    }

    function setupPdfForm(form) {
        var prefix = form.getAttribute('data-chart-prefix');
        var imagesInput = form.querySelector('input[name="chart_images"]');
        var submitBtn = form.querySelector('input[type="submit"]');
        if (!prefix || !imagesInput || !submitBtn) {
            return;
        }
        form.addEventListener('submit', function (e) {
            if (form.dataset.imagesReady === '1') {
                return; // Images already captured on a previous submit attempt.
            }
            e.preventDefault();
            var originalLabel = submitBtn.value;
            submitBtn.value = 'Generating chart images…';
            submitBtn.disabled = true;
            collectChartImages(prefix).then(function (images) {
                imagesInput.value = JSON.stringify(images);
                form.dataset.imagesReady = '1';
                submitBtn.value = originalLabel;
                submitBtn.disabled = false;
                form.submit();
            }).catch(function (err) {
                submitBtn.value = originalLabel;
                submitBtn.disabled = false;
                global.alert('Could not capture chart images for the PDF: ' +
                    (err && err.message ? err.message : err));
            });
        });
    }

    function initPdfForms() {
        var forms = document.querySelectorAll('form.qa-pdf-form');
        Array.prototype.forEach.call(forms, setupPdfForm);
    }

    // This script tag runs before the PDF form's own HTML exists in the page
    // (render_pdf_form() is echoed later in index.php's document order) —
    // DOMContentLoaded waits for the whole initial HTML response to finish
    // parsing regardless of where this <script> sits within it, so the form
    // is reliably present by the time this fires.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPdfForms);
    } else {
        initPdfForms();
    }

    global.QuizAnalyticsRenderer = {render: render};
})(window);
