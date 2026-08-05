<?php
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
 */

namespace local_quizanalytics\analytics;

class latex_utils {

    // Detects a Maxima "question variables" debug/error dump that has leaked into
    // rendered question text — see latex_utils.py's own comment on _MAXIMA_DUMP_RE
    // for the full story on why chain length 2+ is the signal.
    const MAXIMA_ASSIGNMENT = '[a-zA-Z_][a-zA-Z0-9_]*\s*:?=\s*[^;=]*';

    /**
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
            "<em>(student's answer)</em>",
            $text
        );
        return preg_replace('/\[\[feedback:\w+\]\]/', '', $cleaned);
    }

    /**
     * Normalize raw STACK/Moodle LaTeX so KaTeX renders it as math instead of
     * literal escape sequences or broken `$$` collisions. See latex_utils.py's
     * docstring for the full explanation of the adjacent-inline-run collision this
     * guards against.
     */
    public static function clean_moodle_latex(string $text, bool $is_header = false): string {
        if ($text === '') {
            return $text;
        }

        $cleaned = $text;
        $cleaned = preg_replace('/<[^>]+>/', ' ', $cleaned);
        $cleaned = preg_replace('/\\\\displaystyle\s*/', '', $cleaned);

        // 1. Merge adjacent inline math runs so `\)\(` can't collide into `$$` below.
        $cleaned = preg_replace('/\\\\\)\s*\\\\\(/', ' ', $cleaned);

        // 2. Display block math: `\[ ... \]` -> `$$ ... $$` (or `$ ... $` for headers).
        //    preg_replace_callback rather than preg_replace's own $1 backreference
        //    syntax: PHP's replacement-string backslash/dollar escaping rules for
        //    literal "$$" adjacent to a backreference are easy to get subtly wrong
        //    (verified the hard way while writing this) — a callback's return value
        //    is used verbatim, with no special-character reinterpretation at all.
        if ($is_header) {
            $cleaned = preg_replace_callback('/\\\\\[(.*?)\\\\\]/s', fn($m) => '$' . $m[1] . '$', $cleaned);
        } else {
            $cleaned = preg_replace_callback('/\\\\\[(.*?)\\\\\]/s', fn($m) => '$$' . $m[1] . '$$', $cleaned);
        }

        // 3. Inline math: `\( ... \)` -> `$ ... $`.
        $cleaned = preg_replace_callback('/\\\\\((.*?)\\\\\)/s', fn($m) => '$' . $m[1] . '$', $cleaned);

        // 4. Clean up leftover collisions from adjacency types step 1 doesn't cover
        //    (e.g. a display block immediately followed by an inline run, `\]\(`,
        //    converts to a run of 3-4 `$`). A legitimate lone display-block boundary
        //    is exactly 2 `$` and must survive this — only collapse runs of 3+.
        $cleaned = preg_replace('/(?:\$\s*){3,}/', '$$', $cleaned);

        if ($is_header) {
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

    // Maxima/STACK function names that map onto a *standard* LaTeX operator macro
    // (confirmed to render in KaTeX on-screen). Deliberately excludes names that
    // double as plausible plain variable names in this quiz domain (min, max, det,
    // gcd, lim, sup, arg, deg, ...) — those are left for the generic-call fallback
    // below, which only fires when the name is actually *called*, so a bare
    // variable named e.g. `min` renders unchanged instead of being forced into
    // operator form.
    const FUNCTION_NAMES = [
        'sin', 'cos', 'tan', 'sinh', 'cosh', 'tanh', 'sec', 'csc', 'cot',
        'arcsin', 'arccos', 'arctan',
        'log', 'ln', 'exp',
    ];

    // Maxima spells the inverse trig functions without the "arc" prefix; `\asin`/
    // `\acos`/`\atan` are not valid LaTeX macros, so rename to the standard
    // `arcsin`/`arccos`/`arctan` spelling before the backslash-prefixing pass below.
    const RENAMED_FUNCTIONS = ['asin' => 'arcsin', 'acos' => 'arccos', 'atan' => 'arctan'];

    // Bare Maxima constants/keywords that read as nonsense if left as literal
    // math-mode variables (e.g. "true" would otherwise render as the product t*r*u*e).
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
    public static function extract_stack_answer_latex(string $raw_text): string {
        if ($raw_text === '') {
            return $raw_text;
        }

        $pattern = '/ans(\d+):\s*(.*?)\s*\[(score|valid|invalid)\]/';
        if (!preg_match_all($pattern, $raw_text, $matches, PREG_SET_ORDER)) {
            return self::clean_moodle_latex($raw_text);
        }

        $parts = [];
        foreach ($matches as $m) {
            $idx = $m[1];
            $expr = $m[2];
            $parts[] = "ans{$idx}: $" . self::maxima_expr_to_latex($expr) . '$';
        }
        return implode('; ', $parts);
    }
}
