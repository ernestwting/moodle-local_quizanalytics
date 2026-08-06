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
 * PHP port of analytics-service/analytics/expression_tree.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Tokenizes and parses Maxima-style CAS expression strings into expr_node trees, with memoization.
 *
 * expr_node and expr_parser_state (this class's supporting node/parser-state types) live in
 * their own files (expr_node.php, expr_parser_state.php) per Moodle's one-class-per-file rule.
 */
class expression_tree {
    /**
     * A NUMBER, an IDENT (variable/function name, including Maxima's
     * %-prefixed constants like %pi/%e/%i), or a single-character
     * operator/punctuation. \G anchors each match to continue from the
     * previous match's end (PHP's equivalent of re.Pattern.match(text, pos)).
     *
     * @var string
     */
    private const TOKEN_RE =
        '/\G\s*(?:(?<NUMBER>\d+\.\d+|\d+)|(?<IDENT>[A-Za-z_%][A-Za-z0-9_%]*)|(?<OP>[+\-*\/^,()]))/';

    /** @var array<string, expr_node|null> Memoized parse_expression() results, keyed by source text. */
    private static array $cache = [];

    /**
     * Splits $text into (kind, value) token pairs, or null if it contains an unrecognized character.
     *
     * @return array{0: string, 1: string}[]|null
     */
    private static function tokenize(string $text): ?array {
        $tokens = [];
        $pos = 0;
        $n = strlen($text);
        while ($pos < $n) {
            if (!preg_match(self::TOKEN_RE, $text, $m, 0, $pos)) {
                if (trim(substr($text, $pos)) === '') {
                    break;
                }
                return null;
            }
            if ($m[0] === '') {
                // No token matched at this position (only leading whitespace
                // consumed, if any) and there's non-whitespace left — same
                // "unexpected character" case as the Python original.
                return null;
            }
            if (($m['NUMBER'] ?? '') !== '') {
                $tokens[] = ['NUMBER', $m['NUMBER']];
            } else if (($m['IDENT'] ?? '') !== '') {
                $tokens[] = ['IDENT', $m['IDENT']];
            } else if (($m['OP'] ?? '') !== '') {
                $tokens[] = ['OP', $m['OP']];
            } else {
                return null;
            }
            $pos += strlen($m[0]);
        }
        return $tokens;
    }

    /**
     * Parse a Maxima/STACK CAS expression string into an expr_node tree, or
     * null if it can't be confidently parsed (empty input, or syntax outside
     * the supported arithmetic/function-call grammar, e.g. matrix(...)) —
     * callers should treat null as "no tree edit distance available for this
     * response" rather than raising.
     */
    public static function parse_expression(string $text): ?expr_node {
        if (trim($text) === '') {
            return null;
        }
        if (array_key_exists($text, self::$cache)) {
            return self::$cache[$text];
        }

        $result = null;
        try {
            $tokens = self::tokenize($text);
            if ($tokens !== null && !empty($tokens)) {
                $parser = new expr_parser_state($tokens);
                $node = $parser->parse_expr();
                if ($parser->pos() === $parser->count()) {
                    $result = $node;
                }
            }
        } catch (\Exception $e) {
            $result = null;
        }

        // Unbounded within one request's lifetime (matches lru_cache's role
        // here — bounding request-scoped memoization, not a persistent
        // cache) — a single Moodle request never parses enough distinct
        // expression strings for this to matter.
        self::$cache[$text] = $result;
        return $result;
    }
}
