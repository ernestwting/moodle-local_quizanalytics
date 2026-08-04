<?php
/**
 * Shared rendering scaffolding for the {summary, sections} JSON contract,
 * used by quiz_quizanalytics, quiz_solutionprocess, and local_quizanalytics.
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
 * @package quiz_quizanalytics
 */

namespace quiz_quizanalytics\output;

class sections_renderer {

    /**
     * Echoes the empty container divs a payload gets rendered into.
     * Callers must use a unique $prefix per page when more than one
     * independent result is rendered on the same page (e.g. Question
     * Analysis + Solution Process Visualization together on
     * local_quizanalytics's per-quiz view).
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
        global $CFG;

        $base = new \moodle_url('/mod/quiz/report/quizanalytics/js');
        $html = '';

        if ($include_vendor) {
            $plotlyurl = new \moodle_url('/mod/quiz/report/quizanalytics/js/vendor/plotly.min.js');
            $katexcssurl = new \moodle_url('/mod/quiz/report/quizanalytics/js/vendor/katex/katex.min.css');
            $katexjsurl = new \moodle_url('/mod/quiz/report/quizanalytics/js/vendor/katex/katex.min.js');
            $katexautorenderurl = new \moodle_url('/mod/quiz/report/quizanalytics/js/vendor/katex/contrib/auto-render.min.js');
            $sectionsrendererurl = new \moodle_url('/mod/quiz/report/quizanalytics/js/vendor-shared/sections-renderer.js');

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
     * display preference. Shared across Question Analysis, Solution Process
     * Visualization, and the course-wide view — one preference key so a
     * teacher's choice is consistent everywhere rather than set per-page.
     *
     * @return bool
     */
    public static function resolve_colorblind_mode(): bool {
        $param = optional_param('colorblind', null, PARAM_INT);
        if ($param !== null) {
            \set_user_preference('quiz_quizanalytics_colorblind', (bool) $param);
            return (bool) $param;
        }
        return (bool) \get_user_preference('quiz_quizanalytics_colorblind', false);
    }

    /**
     * A small GET-reload checkbox form for the colorblind toggle, preserving
     * every other current query parameter (quiz id, question/part selectors,
     * etc.) — same plain-reload pattern already used for local_quizanalytics's
     * quiz selector, so every distinct view stays a cacheable URL.
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
        $html .= ' ' . \html_writer::label(\get_string('colorblindmode', 'quiz_quizanalytics'), 'qa-colorblind-toggle');
        $html .= ' ' . \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => \get_string('apply', 'moodle'), 'class' => 'btn btn-secondary btn-sm',
        ]);
        $html .= \html_writer::end_tag('form');
        return $html;
    }
}
