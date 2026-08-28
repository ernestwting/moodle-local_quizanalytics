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
 * Part of the PHP port of analytics-service/analytics/expression_tree.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Recursive-descent parser for a Maxima-style arithmetic expression — see
 * expression_tree.py's _Parser docstring for the full grammar and the
 * n-ary +/* flattening / unary-minus-on-literal rules this replicates.
 */
class expr_parser_state {
    /** @var array{0: string, 1: string}[] */
    private array $tokens;

    /** @var int Index of the next token to consume. */
    private int $pos = 0;

    /**
     * Wraps a token stream (as produced by expression_tree::tokenize()) for parsing.
     */
    public function __construct(array $tokens) {
        $this->tokens = $tokens;
    }

    /**
     * Current position (index of the next token to consume) in the token stream.
     */
    public function pos(): int {
        return $this->pos;
    }

    /**
     * Total number of tokens in the stream.
     */
    public function count(): int {
        return count($this->tokens);
    }

    /**
     * The token at $offset positions ahead of the current position, or null past the end.
     */
    private function peek(int $offset = 0): ?array {
        $index = $this->pos + $offset;
        return $this->tokens[$index] ?? null;
    }

    /**
     * True if the token at $offset positions ahead has the given literal value.
     */
    private function peek_is(string $value, int $offset = 0): bool {
        $token = $this->peek($offset);
        return $token !== null && $token[1] === $value;
    }

    /**
     * Consumes and returns the current token, throwing if the stream is exhausted.
     */
    private function advance(): array {
        $token = $this->peek();
        if ($token === null) {
            throw new \Exception('Unexpected end of expression');
        }
        $this->pos++;
        return $token;
    }

    /**
     * Consumes the current token if it matches $value, throwing otherwise.
     */
    private function expect(string $value): void {
        if (!$this->peek_is($value)) {
            throw new \Exception("Expected '{$value}' at position {$this->pos}");
        }
        $this->advance();
    }

    /**
     * Parses a sum/difference of terms (grammar's lowest-precedence level).
     */
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

    /**
     * Parses a product/quotient of factors.
     */
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

    /**
     * Parses an optionally-negated power expression (handles unary minus).
     */
    public function parse_factor(): expr_node {
        if ($this->peek_is('-')) {
            $this->advance();
            $nexttoken = $this->peek();
            if ($nexttoken !== null && $nexttoken[0] === 'NUMBER' && !$this->peek_is('^', 1)) {
                $value = $this->advance()[1];
                return new expr_node("-{$value}");
            }
            return new expr_node('-', [$this->parse_factor()]);
        }
        return $this->parse_power();
    }

    /**
     * Parses an atom optionally raised to a power (right-associative).
     */
    public function parse_power(): expr_node {
        $base = $this->parse_atom();
        if ($this->peek_is('^')) {
            $this->advance();
            $exponent = $this->parse_factor();
            return new expr_node('^', [$base, $exponent]);
        }
        return $base;
    }

    /**
     * Parses a number, a (possibly function-call) identifier, or a parenthesized sub-expression.
     */
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

    /**
     * Parses a comma-separated function-call argument list.
     *
     * @return expr_node[]
     */
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
