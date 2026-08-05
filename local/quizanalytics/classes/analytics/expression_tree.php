<?php
/**
 * PHP port of analytics-service/analytics/expression_tree.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

/**
 * One node of a parsed CAS expression tree: label is the operator/function/
 * atom name, children are its operands in left-to-right order (empty for a
 * leaf). A real class (not an array) so spl_object_id() gives each node a
 * stable identity, matching Python's id(node) usage in tree_edit_distance.py.
 */
class expr_node {
    public string $label;
    /** @var expr_node[] */
    public array $children;

    public function __construct(string $label, array $children = []) {
        $this->label = $label;
        $this->children = $children;
    }
}

/**
 * Recursive-descent parser for a Maxima-style arithmetic expression — see
 * expression_tree.py's _Parser docstring for the full grammar and the
 * n-ary +/* flattening / unary-minus-on-literal rules this replicates.
 */
class expr_parser_state {
    /** @var array{0: string, 1: string}[] */
    private array $tokens;
    private int $pos = 0;

    public function __construct(array $tokens) {
        $this->tokens = $tokens;
    }

    public function pos(): int {
        return $this->pos;
    }

    public function count(): int {
        return count($this->tokens);
    }

    private function peek(int $offset = 0): ?array {
        $index = $this->pos + $offset;
        return $this->tokens[$index] ?? null;
    }

    private function peek_is(string $value, int $offset = 0): bool {
        $token = $this->peek($offset);
        return $token !== null && $token[1] === $value;
    }

    private function advance(): array {
        $token = $this->peek();
        if ($token === null) {
            throw new \Exception('Unexpected end of expression');
        }
        $this->pos++;
        return $token;
    }

    private function expect(string $value): void {
        if (!$this->peek_is($value)) {
            throw new \Exception("Expected '{$value}' at position {$this->pos}");
        }
        $this->advance();
    }

    public function parse_expr(): expr_node {
        $operands = [$this->parse_term()];
        $ops = [];
        while ($this->peek_is('+') || $this->peek_is('-')) {
            $ops[] = $this->advance()[1];
            $operands[] = $this->parse_term();
        }
        if (empty($ops)) {
            return $operands[0];
        }
        if (count(array_filter($ops, fn($op) => $op === '+')) === count($ops)) {
            return new expr_node('+', $operands);
        }
        $acc = $operands[0];
        for ($i = 0; $i < count($ops); $i++) {
            $acc = new expr_node($ops[$i], [$acc, $operands[$i + 1]]);
        }
        return $acc;
    }

    public function parse_term(): expr_node {
        $operands = [$this->parse_factor()];
        $ops = [];
        while ($this->peek_is('*') || $this->peek_is('/')) {
            $ops[] = $this->advance()[1];
            $operands[] = $this->parse_factor();
        }
        if (empty($ops)) {
            return $operands[0];
        }
        if (count(array_filter($ops, fn($op) => $op === '*')) === count($ops)) {
            return new expr_node('*', $operands);
        }
        $acc = $operands[0];
        for ($i = 0; $i < count($ops); $i++) {
            $acc = new expr_node($ops[$i], [$acc, $operands[$i + 1]]);
        }
        return $acc;
    }

    public function parse_factor(): expr_node {
        if ($this->peek_is('-')) {
            $this->advance();
            $next_token = $this->peek();
            if ($next_token !== null && $next_token[0] === 'NUMBER' && !$this->peek_is('^', 1)) {
                $value = $this->advance()[1];
                return new expr_node("-{$value}");
            }
            return new expr_node('-', [$this->parse_factor()]);
        }
        return $this->parse_power();
    }

    public function parse_power(): expr_node {
        $base = $this->parse_atom();
        if ($this->peek_is('^')) {
            $this->advance();
            $exponent = $this->parse_factor();
            return new expr_node('^', [$base, $exponent]);
        }
        return $base;
    }

    public function parse_atom(): expr_node {
        $token = $this->peek();
        if ($token === null) {
            throw new \Exception('Unexpected end of expression');
        }
        [$kind, $value] = $token;
        if ($kind === 'NUMBER') {
            $this->advance();
            return new expr_node($value);
        }
        if ($kind === 'IDENT') {
            $this->advance();
            if ($this->peek_is('(')) {
                $this->advance();
                $args = $this->parse_arglist();
                $this->expect(')');
                return new expr_node($value, $args);
            }
            return new expr_node($value);
        }
        if ($kind === 'OP' && $value === '(') {
            $this->advance();
            $node = $this->parse_expr();
            $this->expect(')');
            return $node;
        }
        throw new \Exception("Unexpected token '{$value}'");
    }

    /** @return expr_node[] */
    public function parse_arglist(): array {
        if ($this->peek_is(')')) {
            return [];
        }
        $args = [$this->parse_expr()];
        while ($this->peek_is(',')) {
            $this->advance();
            $args[] = $this->parse_expr();
        }
        return $args;
    }
}

class expression_tree {

    // A NUMBER, an IDENT (variable/function name, including Maxima's
    // %-prefixed constants like %pi/%e/%i), or a single-character
    // operator/punctuation. \G anchors each match to continue from the
    // previous match's end (PHP's equivalent of re.Pattern.match(text, pos)).
    private const TOKEN_RE =
        '/\G\s*(?:(?<NUMBER>\d+\.\d+|\d+)|(?<IDENT>[A-Za-z_%][A-Za-z0-9_%]*)|(?<OP>[+\-*\/^,()]))/';

    private static array $cache = [];

    /** @return array{0: string, 1: string}[]|null */
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
