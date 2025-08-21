<?php
// ConditionEvaluator.php

if (!defined('DOKU_INC')) die();

class Ifday_ConditionEvaluator {

    private const TOKEN_INVALID_DAY = '__INVALID_DAY__';
    private const TOKEN_INVALID_MONTH = '__INVALID_MONTH__';
    private const TOKEN_INCOMPLETE_RANGE = '__INCOMPLETE_RANGE__';
    private const TOKEN_SYNTAX_ERROR = '__SYNTAX_ERROR__';

    /**
     * Entry point for handling a complete <ifday> syntax block.
     *
     * @param string $match The full matched syntax block from the lexer.
     * @param bool $showErrors Config setting to display errors on page.
     * @return array Returns [bool success, string content_to_render, string|null error_message]
     */
    public function handleCondition(string $match, bool $showErrors): array {
        // Regex to capture condition, if-content, and optional else-content
        if (preg_match('/^<ifday\s+(.*?)>(.*?)<else>(.*?)<\/ifday>$/is', $match, $m)) {
            [$fullMatch, $cond, $contentIf, $contentElse] = $m;
        } elseif (preg_match('/^<ifday\s+(.*?)>(.*?)<\/ifday>$/is', $match, $m)) {
            [$fullMatch, $cond, $contentIf] = $m;
            $contentElse = '';
        } else {
            return [false, '', 'Failed to parse ifday block syntax.'];
        }

        $cond = trim($cond);
        [$success, $evalResult] = $this->evaluateCondition($cond);

        if (!$success) {
            return [false, $cond, $evalResult];
        }

        if ($evalResult) {
            $content = $contentIf;
        } else {
            $content = $contentElse;
        }

        return [true, p_render('xhtml', p_get_instructions($content), $info), null];
    }

    /**
     * Expand shorthand like "mon or tue", "mon,tue", "mon || tue",
     * "(mon and wed) or fri", "not mon" into canonical form:
     *   "(day is monday) OR (day is tuesday)" etc.
     *
     * Returns the expanded string, or null if this isn't a pure shorthand
     * (i.e., it already uses 'day', 'today', '==', 'is', etc.).
     * Unknown word-like tokens are marked with TOKEN_INVALID_DAY:<token>.
     */
    private function expandDayOnlySyntax(string $expr): ?string {
        $s = trim($expr);
        if ($s === '') return null;

        // If it already looks canonical or relative, skip
        if (preg_match('/(==|!=|<=|>=|<|>|\\bis\\b|\\bis\\s+not\\b|\\bday\\b|\\btoday\\b|\\btomorrow\\b|\\byesterday\\b)/i', $s)) {
            return null;
        }

        // Use your existing maps so we don't depend on a new utils API
        $abbr = Ifday_Utils::getDayAbbrMap();   // e.g. mon => monday
        $full = Ifday_Utils::getDays();         // ['monday', ... 'sunday']

        $parts = preg_split(
            '/(\s+|,|\|\||\||&&|&|\(|\)|\bAND\b|\bOR\b|\bNOT\b)/i',
            $s,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if (!$parts) return null;

        $out = [];
        foreach ($parts as $raw) {
            $tok = trim($raw);
            if ($tok === '') continue;

            // parentheses
            if ($tok === '(' || $tok === ')') { $out[] = $tok; continue; }

            // boolean glue
            if (preg_match('/^(,|\|\||\||&&|&|AND|OR|NOT)$/i', $tok)) {
                $map = [
                    ',' => 'OR', '||' => 'OR', '|' => 'OR',
                    '&&' => 'AND', '&' => 'AND',
                    'AND' => 'AND', 'OR' => 'OR', 'NOT' => 'NOT'
                ];
                $up = strtoupper($tok);
                $out[] = $map[$tok] ?? $map[$up] ?? $up;
                continue;
            }

            // normalize potential day tokens
            $k = strtolower($tok);

            if ($k === 'weekday' || $k === 'workday' || $k === 'businessday') {
                $out[] = '(weekday)'; // handled later to 1/0
                continue;
            }
            if ($k === 'weekend') {
                $out[] = '(weekend)'; // handled later to 1/0
                continue;
            }

            // abbreviations and full names
            if (isset($abbr[$k])) {
                $out[] = '(day is ' . $abbr[$k] . ')';
                continue;
            }
            if (in_array($k, $full, true)) {
                $out[] = '(day is ' . $k . ')';
                continue;
            }

            // Any other A–Z word is an invalid day; mark it so evaluateCondition() reports cleanly
            if (preg_match('/^[A-Za-z]+$/', $tok)) {
                $out[] = self::TOKEN_INVALID_DAY . ':' . $k;
                continue;
            }

            // Unknown punctuation means it's not a clean shorthand; let the normal pipeline handle it
            return null;
        }

        $expanded = preg_replace('/\s+/', ' ', trim(implode(' ', $out)));
        return $expanded !== '' ? $expanded : null;
    }

    private function normalizeBracketLists(string $expr): string {
        // 1) Tighten spaces around '[' and ']'
        $expr = preg_replace('/\[\s+/', '[', $expr);
        $expr = preg_replace('/\s+\]/', ']', $expr);

        // 2) Inside any single [...] segment, normalize "a .. b" -> "a..b", and "a , b" -> "a,b"
        //    This keeps commas and '..' semantics but ignores user spacing.
        $expr = preg_replace_callback('/\[[^\[\]]*\]/', function ($m) {
            $inner = substr($m[0], 1, -1);              // strip brackets
            $inner = preg_replace('/\s*\.\.\s*/', '..', $inner); // spaces around '..'
            $inner = preg_replace('/\s*,\s*/', ',', $inner);     // spaces around ','
            $inner = trim($inner);
            return '[' . $inner . ']';
        }, $expr);

        return $expr;
    }

    /**
     * Main function to evaluate a boolean condition based on the current date/time.
     *
     * @param string $cond The boolean condition string.
     * @return array [bool success, string|bool result]
     */
    private function evaluateCondition(string $cond, ?DateTime $testDate = null): array {
        // Check if a specific date is set via environment variable (from `withTestDate` wrapper)
        $testDateStr = getenv('IFDAY_TEST_DATE');
        if ($testDateStr) {
            $now = new DateTime($testDateStr);
        } elseif ($testDate !== null) {
            // Fallback to the original test day parameter if a specific date isn't set
            $now = $testDate;
        } else {
            // Default to the current date if no test date is provided
            $now = new DateTime();
        }

        // Pre-check for lone identifiers and disallow *uppercase* NOT applied to a lone identifier.
        // (lowercase 'not mon' is allowed and will be expanded by expandDayOnlySyntax)
        $pre = trim(preg_replace('/\s+/', ' ', $cond));
        $abbr = Ifday_Utils::getDayAbbrMap();
        $days = Ifday_Utils::getDays();
        $special = ['weekday','weekend','workday','businessday'];

        // A single bare word in parens like "(tue)" should fail
        if (preg_match('/^\(\s*[A-Za-z]+\s*\)$/', $pre)) {
            return [false, "Safety check failed for processed condition '$pre'"];
        }

        // A single bare word:
        if (preg_match('/^[A-Za-z]+$/', $pre)) {
            $w = strtolower($pre);
            // allow if it's a valid day/abbr/special token; otherwise fail fast (e.g., "funday")
            if (!(isset($abbr[$w]) || in_array($w, $days, true) || in_array($w, $special, true))) {
                return [false, "Safety check failed for processed condition '$pre'"];
            }
        }

        // Expand "mon or tue" style shorthand before any other normalization
        $maybeExpanded = $this->expandDayOnlySyntax($cond);
        if ($maybeExpanded !== null) {
            $cond = $maybeExpanded;
        }

        $cond = trim(preg_replace('/\s+/', ' ', $cond));
        $cond = str_replace(['"', '\''], '', $cond);

        // Tolerate spaces in [ ... ] lists/ranges
        $cond = $this->normalizeBracketLists($cond);

        // Process conditions in a specific order of precedence
        $cond = $this->processShorthand($cond);
        $cond = $this->processDayComparisons($cond, $now);
        $cond = $this->processDayRanges($cond, $now);
        $cond = $this->processMonthComparisons($cond, $now);
        $cond = $this->processMonthRanges($cond, $now);
        $cond = $this->processYearComparisons($cond, $now);
        $cond = $this->processLogicalOperators($cond);

        // Check for error tokens before final eval
        if (strpos($cond, self::TOKEN_INVALID_DAY) !== false) {
            preg_match_all('/' . self::TOKEN_INVALID_DAY . ':(\w+)/', $cond, $invalidItems);
            return [false, 'Invalid day name(s) in condition: ' . implode(', ', $invalidItems[1])];
        }
        if (strpos($cond, self::TOKEN_INVALID_MONTH) !== false) {
            preg_match_all('/' . self::TOKEN_INVALID_MONTH . ':(\w+)/', $cond, $invalidItems);
            return [false, 'Invalid month name(s) in condition: ' . implode(', ', $invalidItems[1])];
        }
        if (strpos($cond, self::TOKEN_INCOMPLETE_RANGE) !== false) {
            return [false, 'Eval failed: syntax error, incomplete range in condition.'];
        }

        if (!preg_match('/^[\s\(\)0-9!<>=&|]+$/', $cond)) {
            return [false, "Safety check failed for processed condition '$cond'"];
        }

        try {
            return [true, (bool)eval("return ($cond);")];
        } catch (\Throwable $e) {
            return [false, "Eval failed: " . $e->getMessage()];
        }
    }

    // --- Private methods for each type of condition ---

    private function processShorthand(string $cond): string {
        $lowerCond = strtolower($cond);
        $dayMap = Ifday_Utils::getDayAbbrMap();
        $days = Ifday_Utils::getDays();

        if (in_array($lowerCond, $days, true) || isset($dayMap[$lowerCond])) {
            return 'day == ' . $lowerCond;
        }
        return $cond;
    }

    private function processDayComparisons(string $cond, DateTime $now): string {
        $dayName = strtolower($now->format('l'));
        $dayMap = Ifday_Utils::getDayAbbrMap();
        $days = Ifday_Utils::getDays();

        // today|tomorrow|yesterday comparisons -> 1/0
        $cond = preg_replace_callback(
            '/\b(today|tomorrow|yesterday)\s+(?:is\s+(not\s+)?|([!=]=)\s*)([a-z]+)\b/i',
            function($m) use ($now, $dayMap, $days) {
                $which = strtolower($m[1]);
                $neg = !empty($m[2]);
                $op = $m[3] ?: '==';
                $rhs = strtolower($m[4]);
                $rhs = $dayMap[$rhs] ?? $rhs;
                if (!in_array($rhs, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $rhs;
                $cmpDate = (clone $now);
                if ($which === 'tomorrow') $cmpDate->modify('+1 day');
                if ($which === 'yesterday') $cmpDate->modify('-1 day');
                $lhsDay = strtolower($cmpDate->format('l'));
                $res = ($op === '==') ? ($lhsDay === $rhs) : ($lhsDay !== $rhs);
                return ($neg ? !$res : $res) ? '1' : '0';
            }, $cond
        );

        // day±N ==/!= <day> or bare "day±N"
        $cond = preg_replace_callback(
            '/\bday\s*([+-]\d+)(?:\s*([!=]=)\s*([a-z]+))?\b/i',
            function($m) use ($now, $dayMap, $days) {
                $offset = (int)$m[1];
                $op = $m[2] ?? '==';
                $targetDay = strtolower((clone $now)->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('l'));

                if (count($m) > 2) { // Full comparison
                    $rhs = strtolower($m[3]);
                    $rhs = $dayMap[$rhs] ?? $rhs;
                    if (!in_array($rhs, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $rhs;
                    $res = ($op === '==') ? ($targetDay === $rhs) : ($targetDay !== $rhs);
                    return $res ? '1' : '0';
                }
                // Bare `day+N` is an alias for `day == <target day>`
                return 'day == ' . $targetDay;
            }, $cond
        );

        // "is (not) weekday/weekend"
        $cond = preg_replace_callback(
            '/\bis\s+(not\s+)?(weekday|weekend|workday|businessday)\b/i',
            function($m) use ($dayName) {
                $neg = !empty($m[1]);
                $tok = strtolower($m[2]);
                $isWeekend = in_array($dayName, ['saturday', 'sunday'], true);
                $isWeekday = !$isWeekend;
                $val = in_array($tok, ['weekday','workday','businessday'], true) ? $isWeekday : $isWeekend;
                return ($neg ? !$val : $val) ? '1' : '0';
            }, $cond
        );

        // "day is X" / "day is not X"
        $cond = preg_replace_callback(
            '/\bday\s+is\s+(not\s+)?([a-z]+)\b/i',
            function($m) use ($dayName, $dayMap, $days) {
                $negate = !empty($m[1]);
                $inputDay = strtolower($m[2]);
                $fullDay = $dayMap[$inputDay] ?? $inputDay;
                if (!in_array($fullDay, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $inputDay;
                $result = ($dayName === $fullDay);
                return ($negate ? !$result : $result) ? '1' : '0';
            }, $cond
        );

        // "day ==/!= X"
        $cond = preg_replace_callback(
            '/\bday\s*([!=]=)\s*([a-z]+)\b/i',
            function($m) use ($dayName, $dayMap, $days) {
                $op = $m[1];
                $inputDay = strtolower($m[2]);
                $inputDay = $dayMap[$inputDay] ?? $inputDay;
                if (!in_array($inputDay, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $inputDay;
                $result = ($op === '==') ? ($dayName === $inputDay) : ($dayName !== $inputDay);
                return $result ? '1' : '0';
            }, $cond
        );

        // Standalone tokens
        $isWeekend = in_array($dayName, ['saturday', 'sunday'], true);
        $isWeekday = !$isWeekend;
        $cond = preg_replace('/\b(weekday|workday|businessday)\b/i', $isWeekday ? '1' : '0', $cond);
        $cond = preg_replace('/\bweekend\b/i', $isWeekend ? '1' : '0', $cond);

        return $cond;
    }

    private function processDayRanges(string $cond, DateTime $now): string {
        $dayName = strtolower($now->format('l'));
        $dayMap = Ifday_Utils::getDayAbbrMap();
        $days = Ifday_Utils::getDays();

        $cond = preg_replace_callback(
            '/\bday\s+in\s*\[\s*([^\]]*?)\s*\]/i',
            function($m) use ($dayName, $dayMap, $days) {
                $listRaw = $m[1];
                $items = preg_split('/\s*,\s*/', strtolower($listRaw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $targets = [];
                foreach ($items as $it) {
                    if (strpos($it, '..') !== false) {
                        [$start, $end] = explode('..', $it);
                        if ($start === '' || $end === '') return self::TOKEN_INCOMPLETE_RANGE;
                        $rangeDays = Ifday_Utils::expandDayRange($start, $end);
                        if ($rangeDays === null) {
                            $invalidToken = (in_array($start, array_keys($dayMap)) || in_array($start, $days)) ? $end : $start;
                            return self::TOKEN_INVALID_DAY . ':' . $invalidToken;
                        }
                        $targets = array_merge($targets, $rangeDays);
                    } else {
                        $fullDay = $dayMap[$it] ?? $it;
                        if (!in_array($fullDay, $days, true)) {
                            return self::TOKEN_INVALID_DAY . ':' . $it;
                        }
                        $targets[] = $fullDay;
                    }
                }
                return in_array($dayName, array_unique($targets), true) ? '1' : '0';
            }, $cond
        );
        return $cond;
    }

    private function processMonthComparisons(string $cond, DateTime $now): string {
        $monthNum = (int)$now->format('n');
        $monthMap = Ifday_Utils::getMonthMap();

        $cond = preg_replace_callback(
            '/\bmonth\s*(==|!=|>=|<=|>|<)\s*([a-z]+|\d{1,2})\b/i',
            function($m) use ($monthNum, $monthMap) {
                $op = $m[1];
                $raw = strtolower($m[2]);
                if (ctype_digit($raw)) {
                    $target = (int)$raw;
                    if ($target < 1 || $target > 12) return self::TOKEN_INVALID_MONTH . ':' . $raw;
                } else {
                    $target = $monthMap[$raw] ?? null;
                    if ($target === null) return self::TOKEN_INVALID_MONTH . ':' . $raw;
                }
                $res = match ($op) {
                    '==' => ($monthNum === $target), '!=' => ($monthNum !== $target),
                    '>' => ($monthNum > $target), '<' => ($monthNum < $target),
                    '>=' => ($monthNum >= $target), '<=' => ($monthNum <= $target),
                };
                return $res ? '1' : '0';
            }, $cond
        );
        return $cond;
    }

    private function processMonthRanges(string $cond, DateTime $now): string {
        $monthNum = (int)$now->format('n');
        $monthMap = Ifday_Utils::getMonthMap();

        $cond = preg_replace_callback(
            '/\bmonth\s+in\s*\[\s*([^\]]*?)\s*\]/i',
            function($m) use ($monthNum, $monthMap) {
                $listRaw = $m[1];
                $items = preg_split('/\s*,\s*/', strtolower($listRaw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $targets = [];
                foreach ($items as $it) {
                    if (strpos($it, '..') !== false) {
                        [$start, $end] = explode('..', $it);
                        if ($start === '' || $end === '') return self::TOKEN_INCOMPLETE_RANGE;
                        $rangeMonths = Ifday_Utils::expandMonthRange($start, $end);
                        if ($rangeMonths === null) {
                            $invalidToken = (!isset($monthMap[$start]) && !ctype_digit($start)) ? $start : $end;
                            return self::TOKEN_INVALID_MONTH . ':' . $invalidToken;
                        }
                        $targets = array_merge($targets, $rangeMonths);
                    } else {
                        if (ctype_digit($it)) {
                            $n = (int)$it;
                            if ($n < 1 || $n > 12) return self::TOKEN_INVALID_MONTH . ':' . $it;
                            $targets[] = $n;
                        } else {
                            $n = $monthMap[$it] ?? null;
                            if ($n === null) return self::TOKEN_INVALID_MONTH . ':' . $it;
                            $targets[] = $n;
                        }
                    }
                }
                return in_array($monthNum, array_unique($targets), true) ? '1' : '0';
            }, $cond
        );
        return $cond;
    }

    private function processYearComparisons(string $cond, DateTime $now): string {
        $yearNum = (int)$now->format('Y');

        $cond = preg_replace_callback(
            '/\byear\s*(==|!=|>=|<=|>|<)\s*(\d{1,4})\b/i',
            function($m) use ($yearNum) {
                $op = $m[1];
                $val = (int)$m[2];
                $res = match ($op) {
                    '==' => ($yearNum === $val), '!=' => ($yearNum !== $val),
                    '>' => ($yearNum > $val), '<' => ($yearNum < $val),
                    '>=' => ($yearNum >= $val), '<=' => ($yearNum <= $val),
                };
                return $res ? '1' : '0';
            }, $cond
        );
        return $cond;
    }

    private function processLogicalOperators(string $cond): string {
        $cond = preg_replace('/\bAND\b/i', '&&', $cond);
        $cond = preg_replace('/\bOR\b/i', '||', $cond);
        $cond = preg_replace('/\bNOT\b/i', '!', $cond);
        return $cond;
    }
}
