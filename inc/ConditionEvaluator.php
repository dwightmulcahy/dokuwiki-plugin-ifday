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

        $info = []; // Dokuwiki renderer info array
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

        // *** NEW: if the expression contains an ordinal/last weekday phrase,
        // bail out so processOrdinalWeekdayOfMonth() can handle it first.
        // Examples we want to avoid rewriting here:
        //   "2nd monday", "last fri", "5th tue of month"
        if (preg_match('/\b(?:\d+(?:st|nd|rd|th)|last)\s+[a-z]+(?:\s+of\s+month)?\b/i', $s)) {
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

            // allow ordinals like 1st/2nd/3rd/4th/5th and 'last'
            // NOTE: We now bail earlier if an ordinal phrase exists, so this
            // branch will rarely be used; keep it to avoid changing behavior.
            if (preg_match('/^(?:\d+(?:st|nd|rd|th)|last)$/i', $tok)) {
                $out[] = strtolower($tok);
                continue;
            }

            // allow filler words for expressions like "2nd monday of month"
            if (in_array(strtolower($tok), ['of','month'], true)) {
                $out[] = strtolower($tok);
                continue;
            }

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
        // We use two "clocks":
        //  - $nowDay:    per-row date (varies across the truth table week)
        //  - $nowAnchor: snapshot base date from IFDAY_TEST_DATE if present;
        //                used for month/year and (is) weekday/weekend tokens
        //
        // Rationale: tests expect week snapshots to keep month/year and
        // “is weekday/weekend” anchored to the snapshot date, while
        // day/today/tomorrow/yesterday/ordinals vary per row.

        $envStr = getenv('IFDAY_TEST_DATE') ?: null;

        if ($testDate !== null) {
            $nowDay = $testDate;
        } elseif ($envStr) {
            $nowDay = new DateTime($envStr);
        } else {
            $nowDay = new DateTime();
        }

        // Anchor prefers the environment date when provided
        $nowAnchor = $envStr ? new DateTime($envStr) : $nowDay;

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

        // 1) first try day-only shorthand (pure cases)
        $maybeExpanded = $this->expandDayOnlySyntax($cond);
        if ($maybeExpanded !== null) {
            $cond = $maybeExpanded;
        }

        $cond = trim(preg_replace('/\s+/', ' ', $cond));
        $cond = str_replace(['"', '\''], '', $cond);
        $cond = $this->normalizeBracketLists($cond);

        // 2) Resolve "2nd monday", "last friday", etc. to 1/0  (per-row clock)
        $cond = $this->processOrdinalWeekdayOfMonth($cond, $nowDay);

        // 3) Now that ordinals are collapsed, try shorthand again for mixed cases like "2nd monday or tue"
        $maybeExpanded2 = $this->expandDayOnlySyntax($cond);
        if ($maybeExpanded2 !== null) {
            $cond = $maybeExpanded2;
        }

        // Process conditions in a specific order of precedence
        $cond = $this->processShorthand($cond);
        $cond = $this->processDayComparisons($cond, $nowDay, $nowAnchor); // per-row for day; anchored for weekday/weekend tokens
        $cond = $this->processDayRanges($cond, $nowDay);                  // per-row
        $cond = $this->processMonthComparisons($cond, $nowAnchor);        // anchored
        $cond = $this->processMonthRanges($cond, $nowAnchor);             // anchored
        $cond = $this->processYearComparisons($cond, $nowAnchor);         // anchored
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

    private function processOrdinalWeekdayOfMonth(string $cond, DateTime $now): string {
        $dayMap = Ifday_Utils::getDayAbbrMap();  // mon->monday, ...
        $days   = Ifday_Utils::getDays();        // ['monday',..., 'sunday']

        // Helper: full day -> ISO-8601 index (Mon=1..Sun=7)
        $dayIndex = function(string $fullDay): ?int {
            static $map = [
                'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,
                'friday'=>5,'saturday'=>6,'sunday'=>7
            ];
            return $map[$fullDay] ?? null;
        };

        // Helper: nth occurrence day-of-month (or null if it doesn't exist)
        $nthDom = function(DateTime $base, int $isoDow, int $n): ?int {
            $y = (int)$base->format('Y');
            $m = (int)$base->format('n');
            $first = (clone $base)->setDate($y, $m, 1);
            $firstIso = (int)$first->format('N');            // Mon=1..Sun=7
            $delta = ($isoDow - $firstIso + 7) % 7;
            $firstHit = 1 + $delta;                           // first target weekday in month
            $dom = $firstHit + 7 * ($n - 1);
            $daysInMonth = (int)$first->format('t');
            return ($dom >= 1 && $dom <= $daysInMonth) ? $dom : null;
        };

        // Helper: last occurrence day-of-month
        $lastDom = function(DateTime $base, int $isoDow): int {
            $y = (int)$base->format('Y');
            $m = (int)$base->format('n');
            $last = (clone $base)->setDate($y, $m, (int)$base->format('t'));
            $lastIso = (int)$last->format('N');
            $deltaBack = ($lastIso - $isoDow + 7) % 7;
            return (int)$last->format('j') - $deltaBack;
        };

        // Core evaluator for a phrase like "2nd monday" or "last fri"
        $evalPhrase = function(string $phrase) use ($dayMap, $days, $dayIndex, $nthDom, $lastDom, $now) {
            $phrase = strtolower(trim($phrase));
            // normalize multiple spaces (e.g., "2nd   monday  of month")
            $phrase = preg_replace('/\s+/', ' ', $phrase);
            // strip optional " of month"
            $phrase = preg_replace('/\s+of\s+month$/', '', $phrase);

            if (!preg_match('/^(?<ord>\d+(?:st|nd|rd|th)|last)\s+(?<day>[a-z]+)$/i', $phrase, $mm)) {
                return null; // not our pattern
            }

            $ordRaw = strtolower($mm['ord']);
            $dayRaw = strtolower($mm['day']);
            $full   = $dayMap[$dayRaw] ?? $dayRaw;
            if (!in_array($full, $days, true)) {
                return self::TOKEN_INVALID_DAY . ':' . $dayRaw;
            }

            $iso = $dayIndex($full); // 1..7
            if ($iso === null) return self::TOKEN_INVALID_DAY . ':' . $dayRaw;

            $todayDom = (int)$now->format('j');
            $todayIso = (int)$now->format('N');

            if ($ordRaw === 'last') {
                $targetDom = $lastDom($now, $iso);
            } else {
                // convert 1st/2nd/3rd/4th/5th -> 1..5
                $n = (int)preg_replace('/(st|nd|rd|th)$/', '', $ordRaw);
                $targetDom = $nthDom($now, $iso, $n);
                if ($targetDom === null) {
                    // "5th monday" in a month with only four Mondays → always false
                    return '0';
                }
            }

            // match iff today's iso and dom match the computed occurrence
            return ($todayIso === $iso && $todayDom === $targetDom) ? '1' : '0';
        };

        // (A) today/day comparisons: "(today|day) is [not|==|!=] <ord day>"
        $cond = preg_replace_callback(
            '/\b(today|day)\s+(?:is\s+(not\s+)?|([!=]=)\s*)((?:\d+(?:st|nd|rd|th)|last)\s+[a-z]+(?:\s+of\s+month)?)\b/i',
            function($m) use ($evalPhrase) {
                $neg = !empty($m[2]);
                $op  = $m[3] ?: '==';
                $val = $evalPhrase($m[4]);
                if ($val === null) return $m[0];          // not our pattern; leave untouched
                if (str_starts_with($val, self::TOKEN_INVALID_DAY)) return $val;
                $bool = ($val === '1');
                $res  = ($op === '==') ? $bool : !$bool;   // today == phrase  → bool ; today != phrase → !bool
                return ($neg ? !$res : $res) ? '1' : '0';
            },
            $cond
        );

        // (B) bare (or negated) ordinal phrases: "[not] <ord day>"
        $cond = preg_replace_callback(
            '/\b(?:not\s+)?((?:\d+(?:st|nd|rd|th)|last)\s+[a-z]+(?:\s+of\s+month)?)\b/i',
            function($m) use ($cond, $evalPhrase) {
                // respect preceding 'not ' if present
                $full = $m[0];
                $hasNot = (bool)preg_match('/^\s*not\s+/i', $full);
                // extract the phrase without 'not '
                $phrase = preg_replace('/^\s*not\s+/i', '', $full);
                $val = $evalPhrase($phrase);
                if ($val === null) return $m[0];                // not our pattern
                if (str_starts_with($val, Ifday_ConditionEvaluator::TOKEN_INVALID_DAY)) return $val;
                $bool = ($val === '1');
                return ($hasNot ? !$bool : $bool) ? '1' : '0';
            },
            $cond
        );

        return $cond;
    }

    private function processDayComparisons(string $cond, DateTime $now, ?DateTime $anchor = null): string {
        $anchor = $anchor ?? $now;

        $dayNameNow = strtolower($now->format('l'));          // per-row clock
        $dayNameAnchor = strtolower($anchor->format('l'));    // anchored clock

        $dayMap = Ifday_Utils::getDayAbbrMap();
        $days = Ifday_Utils::getDays();

        // today|tomorrow|yesterday comparisons -> 1/0 (per-row)
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

        // day±N ==/!= <day> or bare "day±N" (per-row)
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

        // "is (not) weekday/weekend/workday/businessday"  — anchored to snapshot
        $cond = preg_replace_callback(
            '/\bis\s+(not\s+)?(weekday|weekend|workday|businessday)\b/i',
            function($m) use ($dayNameAnchor) {
                $neg = !empty($m[1]);
                $tok = strtolower($m[2]);
                $isWeekend = in_array($dayNameAnchor, ['saturday', 'sunday'], true);
                $isWeekday = !$isWeekend;
                $val = in_array($tok, ['weekday','workday','businessday'], true) ? $isWeekday : $isWeekend;
                return ($neg ? !$val : $val) ? '1' : '0';
            }, $cond
        );

        // "day is X" / "day is not X" (per-row)
        $cond = preg_replace_callback(
            '/\bday\s+is\s+(not\s+)?([a-z]+)\b/i',
            function($m) use ($dayNameNow, $dayMap, $days) {
                $negate = !empty($m[1]);
                $inputDay = strtolower($m[2]);
                $fullDay = $dayMap[$inputDay] ?? $inputDay;
                if (!in_array($fullDay, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $inputDay;
                $result = ($dayNameNow === $fullDay);
                return ($negate ? !$result : $result) ? '1' : '0';
            }, $cond
        );

        // "day ==/!= X" (per-row)
        $cond = preg_replace_callback(
            '/\bday\s*([!=]=)\s*([a-z]+)\b/i',
            function($m) use ($dayNameNow, $dayMap, $days) {
                $op = $m[1];
                $inputDay = strtolower($m[2]);
                $inputDay = $dayMap[$inputDay] ?? $inputDay;
                if (!in_array($inputDay, $days, true)) return self::TOKEN_INVALID_DAY . ':' . $inputDay;
                $result = ($op === '==') ? ($dayNameNow === $inputDay) : ($dayNameNow !== $inputDay);
                return $result ? '1' : '0';
            }, $cond
        );

        // Standalone tokens — anchored to snapshot
        $isWeekendA = in_array($dayNameAnchor, ['saturday', 'sunday'], true);
        $isWeekdayA = !$isWeekendA;
        $cond = preg_replace('/\b(weekday|workday|businessday)\b/i', $isWeekdayA ? '1' : '0', $cond);
        $cond = preg_replace('/\bweekend\b/i', $isWeekendA ? '1' : '0', $cond);

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
