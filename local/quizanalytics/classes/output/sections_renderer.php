<?php
/**
 * Shared rendering scaffolding for the {summary, sections} JSON contract,
 * used across every view local_quizanalytics renders: course-wide
 * comparison, per-quiz Question Analytics, and per-quiz Solution Process
 * Visualization.
 *
 * Deliberately NOT using $PAGE->requires->js()/->css() for the vendored
 * libraries (Plotly, KaTeX): that path routes every file through
 * /lib/javascript.php, which re-minifies it with core_minify
 * (MatthiasMullie\Minify\JS) even though these are already minified — this
 * was confirmed to corrupt the ~3.6MB Plotly bundle (a real syntax error in
 * the re-minified output, verified with `node --check`), producing
 * "Uncaught SyntaxError" in the browser. Echoing raw <script src>/<link>
 * tags serves the plugin files directly, unmodified. KaTeX's CSS is
 * similarly kept off Moodle's CSS pipeline since it references its fonts
 * via relative url(...) paths that a combining/rewriting step could break
 * the same way.
 *
 * Namespaced, autoloaded class — no MOODLE_INTERNAL guard needed (that
 * convention is for classic directly-included files; this is loaded via
 * Moodle's PSR-4 autoloader from classes/output/sections_renderer.php).
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\output;

class sections_renderer {

    /**
     * Echoes the empty container divs a payload gets rendered into.
     * Callers must use a unique $prefix per page when more than one
     * independent result is rendered on the same page.
     *
     * @param string $prefix DOM id prefix, e.g. "qa", "spv", "qw".
     * @return string
     */
    public static function render_containers(string $prefix): string {
        $html  = \html_writer::div('', '', ['id' => $prefix . '-summary']);
        $html .= \html_writer::div('', '', ['id' => $prefix . '-sections']);
        return $html;
    }

    /**
     * Echoes the vendored-library <script>/<link> tags, the JSON payload as
     * an inline script variable, and the shared sections-renderer.js — in
     * that guaranteed document order, so plain synchronous script execution
     * order does the right thing with no event-listener ordering games.
     *
     * @param string $prefix Must match the prefix passed to render_containers().
     * @param array $result The API response to render (summary/sections or
     *        the legacy summary/figures shape).
     * @param bool $include_vendor Set false to skip re-emitting the vendored
     *        library tags when multiple payloads are rendered on one page
     *        (only the first render_vendor_and_payload() call on a page
     *        needs $include_vendor = true).
     * @return string
     */
    public static function render_vendor_and_payload(string $prefix, array $result, bool $include_vendor = true): string {
        $html = '';

        if ($include_vendor) {
            $plotlyurl = new \moodle_url('/local/quizanalytics/js/vendor/plotly.min.js');
            $katexcssurl = new \moodle_url('/local/quizanalytics/js/vendor/katex/katex.min.css');
            $katexjsurl = new \moodle_url('/local/quizanalytics/js/vendor/katex/katex.min.js');
            $katexautorenderurl = new \moodle_url('/local/quizanalytics/js/vendor/katex/contrib/auto-render.min.js');
            $sectionsrendererurl = new \moodle_url('/local/quizanalytics/js/vendor-shared/sections-renderer.js');

            $html .= \html_writer::empty_tag('link', ['rel' => 'stylesheet', 'href' => $katexcssurl->out(false)]);
            $html .= \html_writer::tag('script', '', ['src' => $plotlyurl->out(false)]);
            $html .= \html_writer::tag('script', '', ['src' => $katexjsurl->out(false)]);
            $html .= \html_writer::tag('script', '', ['src' => $katexautorenderurl->out(false)]);
            $html .= \html_writer::tag('script', '', ['src' => $sectionsrendererurl->out(false)]);
        }

        $safejson = json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html .= \html_writer::tag('script',
            "QuizAnalyticsRenderer.render(" . json_encode($prefix) . ", {$safejson});");

        return $html;
    }

    /**
     * Reads (and, if a new value was submitted, persists) the colorblind
     * display preference. Shared across every view so a teacher's choice is
     * consistent everywhere rather than set per-page.
     *
     * @return bool
     */
    public static function resolve_colorblind_mode(): bool {
        $param = optional_param('colorblind', null, PARAM_INT);
        if ($param !== null) {
            \set_user_preference('local_quizanalytics_colorblind', (bool) $param);
            return (bool) $param;
        }
        // Note: Moodle's getter is the plural get_user_preferences(), despite
        // the setter being the singular set_user_preference() above — a real
        // asymmetry in core's own API, not a typo.
        return (bool) \get_user_preferences('local_quizanalytics_colorblind', false);
    }

    /**
     * A small GET-reload checkbox form for the colorblind toggle, preserving
     * every other current query parameter (quiz id, question/part selectors,
     * etc.) so every distinct view stays a cacheable URL.
     *
     * @return string
     */
    public static function render_colorblind_toggle(bool $current): string {
        global $PAGE;

        $html = \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $PAGE->url->out_omit_querystring(),
            'class'  => 'mb-3',
        ]);
        foreach ($PAGE->url->params() as $name => $value) {
            if ($name === 'colorblind') {
                continue;
            }
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        // An unchecked HTML checkbox submits nothing at all, which would
        // leave $param null and fall through to the stored (possibly still
        // "true") preference — this hidden 0, submitted first, is overridden
        // by the checkbox's own value only when it's actually checked
        // (PHP keeps the last of two same-named query params), so unchecking
        // the box and submitting genuinely clears it.
        $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'colorblind', 'value' => '0']);
        $checkboxattrs = ['type' => 'checkbox', 'name' => 'colorblind', 'value' => '1', 'id' => 'qa-colorblind-toggle'];
        if ($current) {
            $checkboxattrs['checked'] = 'checked';
        }
        $html .= \html_writer::empty_tag('input', $checkboxattrs);
        $html .= ' ' . \html_writer::label(\get_string('colorblindmode', 'local_quizanalytics'), 'qa-colorblind-toggle');
        $html .= ' ' . \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => \get_string('apply', 'moodle'), 'class' => 'btn btn-secondary btn-sm',
        ]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }

    /**
     * The per-quiz "View:" selector between Question Analytics and Solution
     * Process Visualization — a plain GET-reload, like everything else here,
     * so only the selected view's data ever gets fetched/computed (picking a
     * view is a page reload with &view=..., not a client-side tab swap that
     * would need both already computed).
     *
     * @param string $current 'question' or 'solutionprocess'
     * @return string
     */
    public static function render_view_selector_form(string $current): string {
        global $PAGE;

        $options = [
            'question'        => \get_string('viewquestionanalytics', 'local_quizanalytics'),
            'solutionprocess' => \get_string('viewsolutionprocess', 'local_quizanalytics'),
        ];

        $html = \html_writer::start_tag('form', [
            'method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'mb-3',
        ]);
        foreach ($PAGE->url->params() as $name => $value) {
            if ($name === 'view') {
                continue;
            }
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $html .= \html_writer::label(\get_string('viewselectlabel', 'local_quizanalytics'), 'qa-view-select');
        $html .= ' ' . \html_writer::select($options, 'view', $current, false, ['id' => 'qa-view-select']);
        $html .= ' ' . \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => \get_string('gobutton', 'local_quizanalytics'), 'class' => 'btn btn-secondary',
        ]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }

    /**
     * The Solution Process Visualization question/part/student selector — a
     * plain GET-reload form, matching the "view" selector above.
     *
     * @param \moodle_url $url
     * @param array $meta {questions: [{name, parts}], students: [{id, name}]}
     * @param string $question
     * @param int $partsforquestion
     * @param int $part
     * @param string $studentid
     * @return string
     */
    public static function render_solutionprocess_selector_form(
        \moodle_url $url,
        array $meta,
        string $question,
        int $partsforquestion,
        int $part,
        string $studentid
    ): string {
        $ownparams = ['spvquestion', 'spvpart', 'spvstudent'];

        $html = \html_writer::start_tag('form', [
            'method' => 'get', 'action' => $url->out_omit_querystring(), 'class' => 'mb-3',
        ]);
        foreach ($url->params() as $name => $value) {
            if (in_array($name, $ownparams, true)) {
                continue;
            }
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }

        $questionoptions = [];
        foreach ($meta['questions'] as $q) {
            $questionoptions[$q['name']] = $q['name'];
        }
        $html .= \html_writer::label(\get_string('selectquestion', 'local_quizanalytics'), 'spv-question-select');
        $html .= ' ' . \html_writer::select($questionoptions, 'spvquestion', $question, false, ['id' => 'spv-question-select']);
        $html .= ' ';

        $partoptions = [];
        for ($i = 1; $i <= $partsforquestion; $i++) {
            $partoptions[$i] = $i;
        }
        $html .= \html_writer::label(\get_string('selectpart', 'local_quizanalytics'), 'spv-part-select');
        $html .= ' ' . \html_writer::select($partoptions, 'spvpart', $part, false, ['id' => 'spv-part-select']);
        $html .= ' ';

        $studentoptions = ['' => \get_string('selectstudentnone', 'local_quizanalytics')];
        foreach ($meta['students'] as $s) {
            $studentoptions[$s['id']] = $s['name'];
        }
        $html .= \html_writer::label(\get_string('selectstudent', 'local_quizanalytics'), 'spv-student-select');
        $html .= ' ' . \html_writer::select($studentoptions, 'spvstudent', $studentid, false, ['id' => 'spv-student-select']);
        $html .= ' ';

        $html .= \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => \get_string('gobutton', 'local_quizanalytics'), 'class' => 'btn btn-secondary',
        ]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }

    /**
     * A "Generate PDF Report" form: one checkbox per available section (all
     * checked by default, matching Streamlit's export-everything default),
     * plus whatever hidden params the target pdf.php needs to re-derive the
     * quiz/course/selection server-side. Deliberately GET, like every other
     * form in this plugin, so each distinct export stays a plain link.
     *
     * If $sectionnames is empty (e.g. the report-sections lookup itself
     * failed), the form still renders with just the submit button — pdf.php
     * treats a request with no `sections[]` at all as "export every section",
     * so this degrades gracefully rather than blocking the download.
     *
     * @param \moodle_url $action    The pdf.php endpoint to submit to.
     * @param array $hiddenparams    name => value pairs pdf.php needs (id, colorblind, etc).
     * @param string[] $sectionnames Section names from GET /report-sections/{kind}.
     * @param string $buttonlabel
     * @param string $idprefix       Unique per rendered form, so checkbox ids never collide
     *        when this is called more than once on one page.
     * @return string
     */
    public static function render_pdf_form(
        \moodle_url $action,
        array $hiddenparams,
        array $sectionnames,
        string $buttonlabel,
        string $idprefix
    ): string {
        $html = \html_writer::start_tag('form', [
            'method' => 'get', 'action' => $action->out(false), 'class' => 'mb-3',
        ]);
        foreach ($hiddenparams as $name => $value) {
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        foreach ($sectionnames as $section) {
            $id = $idprefix . '-' . md5($section);
            $html .= \html_writer::empty_tag('input', [
                'type' => 'checkbox', 'name' => 'sections[]', 'value' => $section, 'id' => $id, 'checked' => 'checked',
            ]);
            $html .= ' ' . \html_writer::label($section, $id) . \html_writer::empty_tag('br');
        }
        $html .= \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => $buttonlabel, 'class' => 'btn btn-primary mt-2',
        ]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }
}
