<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHP port of analytics-service/analytics/latex_utils.py — see that file for the
 * full rationale behind each transformation; comments here focus on anything that
 * changed in translation, not on re-explaining logic that ported over unchanged.
 *
 * Byte-based string indexing (substr()/strlen(), not the mb_* family) is used
 * throughout, matching Python's codepoint-based indexing exactly for the ASCII-only
 * content this operates on (Maxima expression syntax, LaTeX macros, STACK's own
 * castext markup) — multibyte-safety would be unnecessary complexity for content a
 * CAS engine guarantees is ASCII.
 *
 * Namespaced, autoloaded class — no MOODLE_INTERNAL guard needed (loaded via
 * Moodle's PSR-4 autoloader from classes/analytics/latex_utils.php).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * LaTeX/math text cleanup: STACK debug-dump stripping, KaTeX-safe normalization, and plain-text math rendering.
 */
class latex_utils {
    /**
     * Detects a Maxima "question variables" debug/error dump that has leaked into
     * rendered question text — see latex_utils.py's own comment on _MAXIMA_DUMP_RE
     * for the full story on why chain length 2+ is the signal.
     *
     * @var string
     */
    const MAXIMA_ASSIGNMENT = '[a-zA-Z_][a-zA-Z0-9_]*\s*:?=\s*[^;=]*';

    /**
     * Splits off a leaked Maxima debug dump from the end of question text, if present.
     *
     * @return array{0: string, 1: string} [clean_text, dump_text]
     */
    public static function split_stack_debug_dump(string $text): array {
        if ($text === '') {
            return [$text, ''];
        }
        $pattern = '/(?:' . self::MAXIMA_ASSIGNMENT . ';\s*){2,}(?:' . self::MAXIMA_ASSIGNMENT . ';?)?/';
        if (!preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [$text, ''];
        }
        $start = $matches[0][1];
        return [
            trim(substr($text, 0, $start)),
            trim(substr($text, $start)),
        ];
    }

    /** @var string Inline-styled stand-in for a STACK answer box — no live form behind it, just a visual echo of Moodle's own input styling (see render_question_html()). */
    const INPUT_PLACEHOLDER_HTML = '<span style="display:inline-block;min-width:4em;padding:2px 8px;'
        . 'margin:0 2px;border:1px solid #ccd2da;border-radius:4px;background:#f8f9fa;'
        . 'font-family:monospace;font-size:0.9em;color:#6a737d;">(student\'s answer)</span>';

    /**
     * Replaces STACK's [[input:...]]/[[validation:...]] answer-box markup with a
     * plain "(student's answer)" marker, and drops [[feedback:...]] entirely — for
     * displaying question text in a report, where there's no live form to render
     * those slots into.
     */
    public static function strip_stack_input_placeholders(string $text): string {
        if ($text === '') {
            return $text;
        }
        $cleaned = preg_replace(
            '/\[\[input:\w+\]\]\s*(?:\[\[validation:\w+\]\])?/',
            self::INPUT_PLACEHOLDER_HTML,
            $text
        );
        // A [[validation:ansN]] tag that isn't immediately adjacent to its
        // own [[input:ansN]] (real STACK authoring commonly puts other
        // content — closing math delimiters, a unit label — between the
        // two) never matched the combined pattern above, so it survived
        // untouched: visible, confusing literal "[[validation:ans3]]" text
        // in the rendered report. Strips whatever's left on its own.
        $cleaned = preg_replace('/\[\[validation:\w+\]\]/', '', $cleaned);
        $cleaned = preg_replace('/\[\[feedback:\w+\]\]/', '', $cleaned);
        // Text arrives here already run through parser::clean_html_text(),
        // which turned each <br>/</p>/</tr> boundary into exactly one <br>
        // — but only where it could tell those boundaries apart. A
        // [[validation:ansN]] or [[feedback:prtN]] tag sitting between two
        // such boundaries (common right after a question's last input, e.g.
        // "...<br>[[validation:ans14]]<br>[[feedback:prt14]]") counted as
        // real content at that point, so the boundaries on either side of it
        // stayed as two separate <br> instead of collapsing into one; once
        // stripped above, the run of now-empty <br> tags is left dangling.
        // Collapses any such run (blank ones in the middle, or hanging at
        // either end) the same way clean_html_text() already does for
        // ordinary HTML boundaries.
        $cleaned = preg_replace('/\s*(?:<br\s*\/?>\s*)+/i', '<br>', $cleaned);
        return trim(preg_replace('/^(?:<br>\s*)+|(?:<br>\s*)+$/i', '', $cleaned));
    }

    /**
     * Renders a STACK question's *raw* (un-flattened) instantiated question text
     * for the Question Review panel, preserving the author's actual HTML — lists,
     * `<code>`, tables, emphasis — instead of parser::clean_html_text()'s
     * everything-to-<br> flattening (built for compact response-cell/table text,
     * not a full question preview).
     *
     * Runs Moodle's own format_text() purely for its HTMLPurifier cleaning pass
     * (safe-tag allowlisting, same one Moodle's real question renderer relies on)
     * — cheap, plain string work, no CAS/Maxima call involved. Moodle's content
     * *filters* are deliberately skipped ('filter' => false): this plugin already
     * renders \\(...\\) / $$...$$ math client-side via KaTeX (see
     * sections-renderer.js's renderMathInElement() call), and letting Moodle's own
     * TeX/MathJax filter touch the same raw delimiters first would mangle them
     * before KaTeX ever sees the text.
     *
     * @param string $rawhtml the CAS-rendered question text before any flattening
     */
    public static function render_question_html(string $rawhtml): string {
        if (trim($rawhtml) === '') {
            return $rawhtml;
        }
        global $PAGE;
        $context = $PAGE->context ?? \context_system::instance();
        $cleaned = format_text($rawhtml, FORMAT_HTML, [
            'context' => $context,
            'filter' => false,
            'noclean' => false,
            'para' => false,
        ]);
        return self::strip_stack_input_placeholders(trim($cleaned));
    }

    /**
     * Normalize raw STACK/Moodle LaTeX so KaTeX renders it as math instead of
     * literal escape sequences or broken `$$` collisions. See latex_utils.py's
     * docstring for the full explanation of the adjacent-inline-run collision this
     * guards against.
     */
    public static function clean_moodle_latex(string $text, bool $isheader = false): string {
        if ($text === '') {
            return $text;
        }

        $cleaned = $text;
        $cleaned = preg_replace('/<[^>]+>/', ' ', $cleaned);
        $cleaned = preg_replace('/\\\\displaystyle\s*/', '', $cleaned);

        // 1. Merge adjacent inline math runs so `\)\(` can't collide into `$$` below.
        $cleaned = preg_replace('/\\\\\)\s*\\\\\(/', ' ', $cleaned);

        // 2. Display block math: `\[ ... \]` -> `$$ ... $$` (or `$ ... $` for headers).
        // preg_replace_callback rather than preg_replace's own $1 backreference
        // syntax: PHP's replacement-string backslash/dollar escaping rules for
        // literal "$$" adjacent to a backreference are easy to get subtly wrong
        // (verified the hard way while writing this) — a callback's return value
        // is used verbatim, with no special-character reinterpretation at all.
        if ($isheader) {
            $cleaned = preg_replace_callback('/\\\\\[(.*?)\\\\\]/s', fn($m) => '$' . $m[1] . '$', $cleaned);
        } else {
            $cleaned = preg_replace_callback('/\\\\\[(.*?)\\\\\]/s', fn($m) => '$$' . $m[1] . '$$', $cleaned);
        }

        // 3. Inline math: `\( ... \)` -> `$ ... $`.
        $cleaned = preg_replace_callback('/\\\\\((.*?)\\\\\)/s', fn($m) => '$' . $m[1] . '$', $cleaned);

        // 4. Clean up leftover collisions from adjacency types step 1 doesn't cover
        // (e.g. a display block immediately followed by an inline run, `\]\(`,
        // converts to a run of 3-4 `$`). A legitimate lone display-block boundary
        // is exactly 2 `$` and must survive this — only collapse runs of 3+.
        $cleaned = preg_replace('/(?:\$\s*){3,}/', '$$', $cleaned);

        if ($isheader) {
            // Headers can't render multi-line display math or contain raw newlines.
            $cleaned = str_replace('$$', '$', $cleaned);
            $cleaned = str_replace("\n", ' ', $cleaned);
        }

        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Balanced-paren replacement of Maxima `sqrt(...)` with LaTeX `\sqrt{...}`,
     * recursing into the inner content so nested sqrt()s convert correctly.
     */
    protected static function convert_sqrt(string $expr): string {
        $out = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if (substr($expr, $i, 5) === 'sqrt(') {
                $depth = 1;
                $j = $i + 5;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '(') {
                        $depth++;
                    } else if ($expr[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);
                $out .= '\sqrt{' . self::convert_sqrt($inner) . '}';
                $i = $j;
            } else {
                $out .= $expr[$i];
                $i++;
            }
        }
        return $out;
    }

    /**
     * Balanced-paren replacement of Maxima `^(...)` with LaTeX `^{...}` — LaTeX
     * superscripts only the single token immediately after `^`, so wrapping the
     * parenthesized exponent in braces (recursing for nested parens) is needed for
     * the rest of the exponent to actually render raised.
     */
    protected static function convert_pow_parens(string $expr): string {
        $out = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if ($expr[$i] === '^' && ($i + 1 < $n) && $expr[$i + 1] === '(') {
                $depth = 1;
                $j = $i + 2;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '(') {
                        $depth++;
                    } else if ($expr[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);
                $out .= '^{' . self::convert_pow_parens($inner) . '}';
                $i = $j;
            } else {
                $out .= $expr[$i];
                $i++;
            }
        }
        return $out;
    }

    /**
     * Balanced-paren replacement of a Maxima `func_name(...)` call (e.g. `abs(...)`,
     * `floor(...)`) with `openwrap ... closewrap`, recursing into the inner content
     * so nested calls of the same function convert correctly.
     */
    protected static function convert_bracket_call(string $expr, string $funcname, string $openwrap, string $closewrap): string {
        $marker = $funcname . '(';
        $markerlen = strlen($marker);
        $out = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if (substr($expr, $i, $markerlen) === $marker) {
                $depth = 1;
                $j = $i + $markerlen;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '(') {
                        $depth++;
                    } else if ($expr[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);
                $out .= $openwrap . self::convert_bracket_call($inner, $funcname, $openwrap, $closewrap) . $closewrap;
                $i = $j;
            } else {
                $out .= $expr[$i];
                $i++;
            }
        }
        return $out;
    }

    /**
     * Balanced-paren replacement of Maxima `nthroot(x, n)` with LaTeX `\sqrt[n]{x}`.
     */
    protected static function convert_nthroot(string $expr): string {
        $marker = 'nthroot(';
        $markerlen = strlen($marker);
        $out = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if (substr($expr, $i, $markerlen) === $marker) {
                $depth = 1;
                $j = $i + $markerlen;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '(') {
                        $depth++;
                    } else if ($expr[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);
                $args = self::split_top_level($inner, ',');
                if (count($args) === 2) {
                    [$value, $degree] = $args;
                    $out .= '\sqrt[' . self::convert_nthroot(trim($degree)) . ']{' . self::convert_nthroot(trim($value)) . '}';
                } else {
                    // Not the expected 2-argument shape — leave it for the generic
                    // operatorname fallback rather than guessing at a malformed call.
                    $out .= $marker . self::convert_nthroot($inner) . ')';
                }
                $i = $j;
            } else {
                $out .= $expr[$i];
                $i++;
            }
        }
        return $out;
    }

    /**
     * Maxima/STACK function names that map onto a *standard* LaTeX operator macro
     * (confirmed to render in KaTeX on-screen). Deliberately excludes names that
     * double as plausible plain variable names in this quiz domain (min, max, det,
     * gcd, lim, sup, arg, deg, ...) — those are left for the generic-call fallback
     * below, which only fires when the name is actually *called*, so a bare
     * variable named e.g. `min` renders unchanged instead of being forced into
     * operator form.
     *
     * @var string[]
     */
    const FUNCTION_NAMES = [
        'sin', 'cos', 'tan', 'sinh', 'cosh', 'tanh', 'sec', 'csc', 'cot',
        'arcsin', 'arccos', 'arctan',
        'log', 'ln', 'exp',
    ];

    /**
     * Maxima spells the inverse trig functions without the "arc" prefix; `\asin`/
     * `\acos`/`\atan` are not valid LaTeX macros, so rename to the standard
     * `arcsin`/`arccos`/`arctan` spelling before the backslash-prefixing pass below.
     *
     * @var array<string, string>
     */
    const RENAMED_FUNCTIONS = ['asin' => 'arcsin', 'acos' => 'arccos', 'atan' => 'arctan'];

    /**
     * Bare Maxima constants/keywords that read as nonsense if left as literal
     * math-mode variables (e.g. "true" would otherwise render as the product t*r*u*e).
     *
     * @var array<string, string>
     */
    const WORD_REPLACEMENTS = [
        'true' => '\text{true}',
        'false' => '\text{false}',
        'unknown' => '\text{unknown}',
        'infinity' => '\infty',
        'minf' => '-\infty',
        'inf' => '\infty',
        'und' => '\text{undefined}',
        'ind' => '\text{indeterminate}',
    ];

    /**
     * Convert a Maxima expression that does not itself contain a `matrix(...)` call.
     */
    protected static function convert_term(string $expr): string {
        $out = $expr;
        $out = str_replace('%i', '\mathrm{i}', $out);
        $out = str_replace('%pi', '\pi', $out);
        $out = str_replace('%e', '\mathrm{e}', $out);
        $out = str_replace('%phi', '\varphi', $out);
        $out = str_replace('%gamma', '\gamma', $out);
        // Any remaining bare `%` (an unhandled Maxima constant/label like `%o1`) is a
        // LaTeX comment marker to KaTeX and silently truncates everything after it
        // when rendered unescaped — escape defensively instead of losing the rest.
        $out = str_replace('%', '\%', $out);

        // Longest-word-first so e.g. "infinity" matches before a hypothetical
        // shorter alternation prefix would — mirrors sorted(..., key=len, reverse=True).
        $words = array_keys(self::WORD_REPLACEMENTS);
        usort($words, fn($a, $b) => strlen($b) - strlen($a));
        $wordpattern = '/\b(' . implode('|', array_map('preg_quote', $words)) . ')\b/';
        $out = preg_replace_callback($wordpattern, function ($m) {
            return self::WORD_REPLACEMENTS[$m[1]];
        }, $out);

        // Maxima's not-equal operator; comparisons/inequalities read better with the
        // proper math symbols than the plain ASCII Maxima emits.
        $out = str_replace('#', ' \neq ', $out);
        $out = str_replace('<=', ' \leq ', $out);
        $out = str_replace('>=', ' \geq ', $out);

        $out = self::convert_sqrt($out);
        $out = self::convert_nthroot($out);
        $out = self::convert_bracket_call($out, 'abs', '|', '|');
        $out = self::convert_bracket_call($out, 'floor', '\lfloor ', ' \rfloor');
        $out = self::convert_bracket_call($out, 'ceiling', '\lceil ', ' \rceil');
        $out = self::convert_pow_parens($out);

        foreach (self::RENAMED_FUNCTIONS as $src => $dst) {
            $out = preg_replace('/\b' . $src . '\b/', $dst, $out);
        }

        // Bare "sin"/"cos"/etc. render in math mode as italicized variable products
        // (s*i*n) unless flagged as LaTeX operator names.
        foreach (self::FUNCTION_NAMES as $name) {
            // Callback, not a literal replacement string: a backslash immediately
            // followed by a letter isn't a backreference, but getting the exact
            // single-vs-double backslash right in a quoted replacement string is
            // easy to fumble — a callback's return value needs no such care.
            $out = preg_replace_callback('/\b' . $name . '\b/', fn($m) => '\\' . $name, $out);
        }

        // Catch-all for any other Maxima function call left unconverted above — a
        // bare identifier directly followed by "(" is, in Maxima's plain-text
        // output, always a function call (Maxima always writes multiplication with
        // an explicit `*`, never juxtaposition). Skips names already
        // backslash-prefixed by an earlier pass (negative lookbehind).
        $out = preg_replace('/(?<!\\\\)\b([A-Za-z_][A-Za-z0-9_]*)\(/', '\operatorname{$1}(', $out);

        $out = str_replace('*', ' \cdot ', $out);
        return $out;
    }

    /**
     * Split on $sep only where it isn't nested inside `()`/`[]`/`{}` — a plain
     * explode() would break a matrix row like `[1,2]` (or a function-call element
     * `sqrt(2,3)`) apart at the wrong comma.
     *
     * @return string[]
     */
    protected static function split_top_level(string $s, string $sep = ','): array {
        $parts = [];
        $depth = 0;
        $current = '';
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $ch = $s[$i];
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
            } else if ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
            }
            if ($ch === $sep && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $parts[] = $current;
        return $parts;
    }

    /**
     * Convert every Maxima `matrix([r1c1,r1c2,...],[r2c1,...],...)` call in $expr
     * into a LaTeX matrix, converting each cell (and any text outside the matrix
     * call) through convert_term() so %pi/sqrt(...)/etc. inside a matrix cell still
     * render correctly.
     *
     * Deliberately avoids `\begin{bmatrix}...\end{bmatrix}` — see latex_utils.py's
     * own docstring for why (renders fine in KaTeX but Matplotlib's mathtext, used
     * for the Python version's PDF export, has no support for LaTeX environments at
     * all). `\left[\substack{row \\ row}\right]` works in both.
     */
    protected static function convert_matrix(string $expr): string {
        $out = '';
        $buffer = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if (substr($expr, $i, 7) === 'matrix(') {
                if ($buffer !== '') {
                    $out .= self::convert_term($buffer);
                    $buffer = '';
                }
                $depth = 1;
                $j = $i + 7;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '(') {
                        $depth++;
                    } else if ($expr[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);

                $latexrows = [];
                foreach (self::split_top_level($inner, ',') as $row) {
                    $row = trim($row);
                    if (str_starts_with($row, '[') && str_ends_with($row, ']')) {
                        $row = substr($row, 1, -1);
                    }
                    $elements = array_map(
                        fn($e) => self::convert_term(trim($e)),
                        self::split_top_level($row, ',')
                    );
                    $latexrows[] = implode(' \quad ', $elements);
                }

                $out .= '\left[\substack{' . implode(' \\\\ ', $latexrows) . '}\right]';
                $i = $j;
            } else {
                $buffer .= $expr[$i];
                $i++;
            }
        }
        if ($buffer !== '') {
            $out .= self::convert_term($buffer);
        }
        return $out;
    }

    /**
     * Best-effort conversion of a Maxima/STACK CAS expression (e.g.
     * `9*%i*sin((7*%pi)/12)+9*cos((7*%pi)/12)`, or a matrix like
     * `matrix([8],[24],[20])`) into LaTeX math, for display purposes only — not a
     * full CAS parser, just the patterns STACK commonly emits.
     */
    public static function maxima_expr_to_latex(string $expr): string {
        if ($expr === '') {
            return $expr;
        }
        if (str_contains($expr, 'matrix(')) {
            return self::convert_matrix($expr);
        }
        return self::convert_term($expr);
    }

    /**
     * Extract just the `ansN: <expression>` parts from a raw STACK response/right-
     * answer dump (which also carries `Seed: ...` and `prtN: ...` diagnostic noise
     * a teacher doesn't need), converting each expression to rendered LaTeX math
     * wrapped in `$...$`.
     *
     * Falls back to clean_moodle_latex() on the whole string when no `ansN:`
     * pattern is found, e.g. a plain (non-STACK) right-answer value.
     */
    public static function extract_stack_answer_latex(string $rawtext): string {
        if ($rawtext === '') {
            return $rawtext;
        }

        $pattern = '/ans(\w+):\s*(.*?)\s*\[(score|valid|invalid)\]/';
        if (!preg_match_all($pattern, $rawtext, $matches, PREG_SET_ORDER)) {
            return self::clean_moodle_latex($rawtext);
        }

        $parts = [];
        foreach ($matches as $m) {
            $idx = $m[1];
            $expr = $m[2];
            $parts[] = "ans{$idx}: $" . self::maxima_expr_to_latex($expr) . '$';
        }
        // One answer per line rather than a single "; "-joined run — a
        // multi-part question can have a dozen-plus ansN values, and
        // reading them off as one long line (in the "Right answer" section,
        // and in each row of the error drill-down table) was hard to scan.
        // Both consumers (sections-renderer.js's qText/aText/error-drilldown
        // table cells) assign this through innerHTML, so a real <br> here
        // renders as an actual line break, not literal text.
        return implode('<br>', $parts);
    }

    /**
     * Balanced-brace replacement of `\macro{...}` with $openwrap ... $closewrap,
     * recursing into the inner content so nested occurrences of the same macro
     * convert correctly — the `{...}` counterpart of convert_bracket_call()'s
     * `(...)` scanning.
     */
    protected static function convert_braced_call(string $expr, string $macro, string $openwrap, string $closewrap): string {
        $marker = $macro . '{';
        $markerlen = strlen($marker);
        $out = '';
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            if (substr($expr, $i, $markerlen) === $marker) {
                $depth = 1;
                $j = $i + $markerlen;
                $start = $j;
                while ($j < $n && $depth > 0) {
                    if ($expr[$j] === '{') {
                        $depth++;
                    } else if ($expr[$j] === '}') {
                        $depth--;
                    }
                    $j++;
                }
                $inner = substr($expr, $start, $j - 1 - $start);
                $out .= $openwrap . self::convert_braced_call($inner, $macro, $openwrap, $closewrap) . $closewrap;
                $i = $j;
            } else {
                $out .= $expr[$i];
                $i++;
            }
        }
        return $out;
    }

    /**
     * Simple, no-braces LaTeX-macro -> plain-text/Unicode substitutions for
     * latex_to_plain_text() below, applied after the balanced-brace macros
     * (\sqrt, ^, \operatorname, \text, \mathrm) have already been unwrapped —
     * order matters here since e.g. \pi nested inside a \sqrt{...} is only
     * reachable as plain text once the braces around it are gone.
     *
     * @var array<string, string>
     */
    const PLAIN_TEXT_REPLACEMENTS = [
        '\cdot' => "\u{00B7}", '\pi' => "\u{03C0}", '\varphi' => "\u{03C6}",
        '\gamma' => "\u{03B3}", '\infty' => "\u{221E}", '\neq' => "\u{2260}",
        '\leq' => "\u{2264}", '\geq' => "\u{2265}", '\%' => '%',
        '\lfloor ' => "\u{230A}", ' \rfloor' => "\u{230B}",
        '\lceil ' => "\u{2308}", ' \rceil' => "\u{2309}",
    ];

    /**
     * Best-effort rendering of one LaTeX math fragment (the content between a
     * pair of `$` delimiters, e.g. `9 \cdot \mathrm{i} \cdot
     * \sin((7 \cdot \pi)/12)`) as plain, readable text/Unicode — used only for
     * the PDF export, which has no LaTeX typesetting engine of its own
     * (KaTeX renders the same `$...$` string as real math on screen). Not a
     * full LaTeX parser, same "best-effort, common STACK patterns only"
     * scope as maxima_expr_to_latex() itself — this is that function's
     * approximate inverse.
     */
    protected static function convert_latex_fragment_to_plain(string $expr): string {
        $out = $expr;
        // Handle \sqrt[n]{x} first (a regex, not convert_braced_call, since its
        // degree argument sits in its own `[...]` before the `{...}`) —
        // fine for STACK's typical simple-degree output; see this
        // function's own "best-effort" scope note above.
        $out = preg_replace('/\\\\sqrt\[(.*?)\]\{(.*?)\}/s', 'root($2, $1)', $out);

        $out = self::convert_braced_call($out, '\sqrt', "\u{221A}(", ')');
        $out = self::convert_braced_call($out, '\operatorname', '', '');
        $out = self::convert_braced_call($out, '\text', '', '');
        $out = self::convert_braced_call($out, '\mathrm', '', '');
        $out = self::convert_braced_call($out, '^', '^(', ')');

        // Converts a substack-style bracketed matrix into "[cell, cell; cell, cell]" notation.
        $out = preg_replace_callback('/\\\\left\[\\\\substack\{(.*?)\}\\\\right\]/s', function ($m) {
            $rows = array_map(function ($row) {
                $cells = array_map('trim', explode('\quad', $row));
                return implode(', ', $cells);
            }, explode(' \\\\ ', $m[1]));
            return '[' . implode('; ', $rows) . ']';
        }, $out);

        foreach (self::PLAIN_TEXT_REPLACEMENTS as $macro => $replacement) {
            $out = str_replace($macro, $replacement, $out);
        }

        // Trig/log/exp operator macros: \arcsin(, \sin(, ... -> just drop the
        // backslash — longest-alternative-first isn't needed here (\bsinh\b
        // and \bsin\b never both match the same position, since a word
        // boundary can't fall between "sin" and the "h" of "sinh").
        $out = preg_replace(
            '/\\\\(arcsin|arccos|arctan|sinh|cosh|tanh|sin|cos|tan|sec|csc|cot|log|ln|exp)\b/',
            '$1',
            $out
        );

        return $out;
    }

    /**
     * Renders every `$...$`-delimited math fragment in $text as plain,
     * readable text via convert_latex_fragment_to_plain(), leaving
     * surrounding non-math text (e.g. "ans1: ", "; ", a frequency count in
     * parens) untouched. For PDF cell display only — the on-screen page
     * still gets the real `$...$` string for KaTeX to typeset.
     */
    public static function latex_to_plain_text(string $text): string {
        if (!str_contains($text, '$')) {
            return $text;
        }
        return preg_replace_callback('/\$(.*?)\$/s', function ($m) {
            return self::convert_latex_fragment_to_plain($m[1]);
        }, $text);
    }
}
