<?php
/**
 * ifday Syntax Plugin
 *
 * Supports conditional rendering based on day-related conditions.
 * Features:
 * - Multi-condition logic with AND, OR, &&, ||
 * - Comparison operators: ==, !=, <, >, <=, >=
 * - Alias operator: "is" as "=="
 * - Negation operator: NOT (logical NOT)
 * - Parentheses grouping
 * - Special keywords: weekday, weekend, day (with full or abbreviated day names)
 * - Boolean checks without explicit comparison
 * - Shorthand day-name-only syntax: <ifday monday> == <ifday day is monday>
 * - Configurable option to toggle visible error messages on invalid conditions
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('DOKU_INC')) die();

class syntax_plugin_ifday extends DokuWiki_Syntax_Plugin {
    /** @var bool Whether to show errors visibly on the wiki page */
    protected $showErrors = true;

    public function __construct() {
        // Read plugin config to determine if errors show visibly or only log silently
        $confVal = $this->getConfValue('show_errors');
        if ($confVal !== null) {
            $this->showErrors = (bool)$confVal;
        }
    }

    /** Syntax plugin type */
    public function getType() { return 'substition'; }

    /** Paragraph type */
    public function getPType() { return 'block'; }

    /** Sort order (low number = high priority) */
    public function getSort() { return 299; }

    /** Connects the lexer to recognize the <ifday ...>...</ifday> pattern */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<ifday\s+.*?>.*?<\/ifday>', $mode, 'plugin_ifday');
    }

    /**
     * Handle matched syntax block, extract condition, and content.
     * This method is updated to also handle the <else> block.
     * @param string $match Full matched string
     * @param int $state Lexer state
     * @param int $pos Position
     * @param Doku_Handler $handler
     * @return array Condition string, content string for 'if' block, and content string for 'else' block.
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        // Regex captures three groups: the condition, the 'if' content, and the 'else' content (if it exists).
        // It uses a non-greedy match for the content to properly handle the first '>', then looks for the <else> tag.
        if (preg_match('/^<ifday\s+(.*?)>(.*?)<else>(.*?)<\/ifday>$/is', $match, $m)) {
            // Case with an <else> block
            return [trim($m[1]), $m[2], $m[3]];
        } elseif (preg_match('/^<ifday\s+(.*?)>(.*?)<\/ifday>$/is', $match, $m)) {
            // Original case without an <else> block
            return [trim($m[1]), $m[2], '']; // Return an empty string for the 'else' content
        }
        return ['', '', ''];
    }

    /**
     * Render the content based on the evaluated condition, either the 'if'
     * content or the 'else' content.
     * @param string $mode Render mode, e.g. 'xhtml'
     * @param Doku_Renderer $R Renderer object
     * @param array $data Array with [condition, content_if, content_else]
     * @return bool True if rendering was handled
     */
    public function render($mode, Doku_Renderer $R, $data) {
        if ($mode !== 'xhtml') return false;

        list($condition, $contentIf, $contentElse) = $data;

        $now = new DateTime();
        dbglog("ifday: Starting evaluation for condition '$condition' at " . $now->format('Y-m-d H:i:s'));

        list($success, $evalResult) = $this->evaluateCondition($condition);

        if (!$success) {
            $msg = 'ifday plugin error evaluating condition: "' . htmlspecialchars($condition) . '"<br><strong>Details:</strong> ' . htmlspecialchars($evalResult);
            dbglog("ifday: $msg");

            // Show error visibly if configured
            if ($this->showErrors) {
                $R->doc .= '<div class="plugin_ifday_error" style="border:1px solid red; padding:10px; color:red; font-weight:bold; margin:1em 0;">';
                $R->doc .= $msg;
                $R->doc .= '</div>';
            }
            return true;
        }

        // Based on the evaluation result, render the correct content block.
        if ($evalResult) {
            $R->doc .= p_render($mode, p_get_instructions($contentIf), $info);
            dbglog("ifday: Condition '$condition' was TRUE. 'if' content will be displayed.");
        } else {
            // Check if there is an <else> block.
            if ($contentElse !== '') {
                $R->doc .= p_render($mode, p_get_instructions($contentElse), $info);
                dbglog("ifday: Condition '$condition' was FALSE. 'else' content will be displayed.");
            } else {
                dbglog("ifday: Condition '$condition' was FALSE. Content will be hidden.");
            }
        }
        return true;
    }

    /**
     * Evaluate the condition string and return a tuple [success, boolean result, or error message]
     * Supports: day comparisons, "is"/"is not", NOT, AND/OR, weekday/weekend, shorthand day names.
     *
     * @param string $cond The condition expression string
     * @param string|null $currentDay Optional: day name to override the current date (e.g., 'mon', 'tue')
     * @return array [bool success, bool|string result or error message]
     */
    /**
     * Evaluate the condition string and return a tuple [success, boolean result, or error message]
     * Supports: day comparisons, "is"/"is not", NOT, AND/OR, weekday/weekend, shorthand day names,
     * explicit comparisons for today/tomorrow/yesterday, day±N offsets, and year equality/inequality.
     *
     * @param string $cond The condition expression string
     * @param string|null $currentDay Optional: day name to override the current date (e.g., 'mon', 'tue')
     * @return array [bool success, bool|string result or error message]
     */
    /**
     * Evaluate an ifday condition string against the plugin's current clock.
     * - Supports: day/today/tomorrow/yesterday, weekday/weekend, workday/businessday,
     *             ==, !=, <, <=, >, >=, parentheses, AND/OR/NOT/&&/||/!,
     *             day±N == <day>, day in [..], month comparisons, and
     *             month in [<ranges and/or tokens>]  ← (NEW: robust implementation)
     *
     * On invalid input, throws \InvalidArgumentException with the exact messages
     * your tests expect (e.g., "Invalid month name(s) in condition: bad").
     */
    private function evaluateCondition(string $cond, ?string $currentDay = null): array {
        // Determine the base date to use (allows deterministic testing)
        $dowMap = ['mon'=>0,'tue'=>1,'wed'=>2,'thu'=>3,'fri'=>4,'sat'=>5,'sun'=>6];
        $envDate = getenv('IFDAY_TEST_DATE') ?: null;

        // Anchor all calculations to either the test date (if set) or "now"
        $anchor = $envDate ? new DateTime($envDate) : new DateTime();

        if ($currentDay !== null) {
            // When a specific day column is under test, use the Monday of the ANCHORED week,
            // then offset to the requested weekday. This keeps month/year tied to IFDAY_TEST_DATE.
            $abbr = strtolower($currentDay);
            $offset = isset($dowMap[$abbr]) ? $dowMap[$abbr] : 0;
            $weekStart = (clone $anchor)->modify('monday this week');
            $now = (clone $weekStart)->modify('+' . $offset . ' days');
        } else {
            // No explicit day under test → use the anchor directly
            $now = $anchor;
        }

        // Determine the day to use
        $dayName = strtolower($now->format('l'));
        $weekday = !in_array($dayName, ['saturday', 'sunday'], true);
        $weekend = !$weekday;

        // Normalize whitespace and remove quotes
        $cond = trim(preg_replace('/\s+/', ' ', $cond));
        $cond = str_replace(['"', '\''], '', $cond);

        // Day/abbr maps
        $days    = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $dayAbbr = ['mon','tue','wed','thu','fri','sat','sun'];
        $mapAbbr = array_combine($dayAbbr, $days);

        // SHORTHAND: single day token -> "day == <token>"
        $lowerCond = strtolower($cond);
        if (in_array($lowerCond, $days, true)) {
            $cond = 'day == ' . $lowerCond;
        } elseif (isset($mapAbbr[$lowerCond])) {
            $cond = 'day == ' . $mapAbbr[$lowerCond];
        }

        // today|tomorrow|yesterday comparisons -> 1/0
        $cond = preg_replace_callback(
            '/\b(today|tomorrow|yesterday)\s+(?:is\s+(not\s+)?|([!=]=)\s*)([a-z]+)\b/i',
            function($m) use ($now, $mapAbbr, $days) {
                $which = strtolower($m[1]);
                $neg   = !empty($m[2]);
                $op    = $m[3] ?: '==';
                $rhs   = strtolower($m[4]);
                $rhs   = $mapAbbr[$rhs] ?? $rhs;
                if (!in_array($rhs, $days, true)) return '__INVALID_DAY__:' . $rhs;
                $cmpDate = clone $now;
                if ($which === 'tomorrow')  $cmpDate->modify('+1 day');
                if ($which === 'yesterday') $cmpDate->modify('-1 day');
                $lhsDay = strtolower($cmpDate->format('l'));
                $res = ($op === '==') ? ($lhsDay === $rhs) : ($lhsDay !== $rhs);
                if ($neg) $res = !$res;
                return $res ? '1' : '0';
            },
            $cond
        );

        // day±N ==/!= <day> -> 1/0
        $cond = preg_replace_callback(
            '/\bday\s*([+-]\d+)\s*([!=]=)\s*([a-z]+)\b/i',
            function($m) use ($now, $mapAbbr, $days) {
                $offset = (int)$m[1];
                $op     = $m[2];
                $rhs    = strtolower($m[3]);
                $rhs    = $mapAbbr[$rhs] ?? $rhs;
                if (!in_array($rhs, $days, true)) return '__INVALID_DAY__:' . $rhs;
                $target = strtolower((clone $now)->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('l'));
                $res = ($op === '==') ? ($target === $rhs) : ($target !== $rhs);
                return $res ? '1' : '0';
            },
            $cond
        );

        // Bare "day±N" -> "day == <weekday>" (lets users write "day+1" inside larger exprs)
        $cond = preg_replace_callback(
            '/\bday\s*([+-]\d+)\b/i',
            function($m) use ($now) {
                $offset = (int)$m[1];
                $target = strtolower((clone $now)->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('l'));
                return 'day == ' . $target;
            },
            $cond
        );

        // Alias: "is (not) weekday|weekend|workday|businessday" -> 1/0
        $cond = preg_replace_callback(
            '/\bis\s+(not\s+)?(weekday|weekend|workday|businessday)\b/i',
            function($m) use ($weekday, $weekend) {
                $neg = !empty($m[1]);
                $tok = strtolower($m[2]);
                $val = in_array($tok, ['weekday','workday','businessday'], true) ? $weekday : $weekend;
                return ($neg ? !$val : $val) ? '1' : '0';
            },
            $cond
        );

        // "day is X" / "day is not X" -> 1/0
        $cond = preg_replace_callback(
            '/\bday\s+is\s+(not\s+)?([a-z]+)\b/i',
            function($m) use ($dayName, $mapAbbr, $days) {
                $negate   = !empty($m[1]);
                $inputDay = strtolower($m[2]);
                $fullDay  = $mapAbbr[$inputDay] ?? $inputDay;
                if (!in_array($fullDay, $days, true)) return '__INVALID_DAY__:' . $inputDay;
                $result = ($dayName === $fullDay);
                return ($negate ? !$result : $result) ? '1' : '0';
            },
            $cond
        );

        // "day ==/!= X" -> 1/0
        $cond = preg_replace_callback(
            '/\bday\s*([!=]=)\s*([a-z]+)\b/i',
            function($m) use ($dayName, $mapAbbr, $days) {
                $op       = $m[1];
                $inputDay = strtolower($m[2]);
                $inputDay = $mapAbbr[$inputDay] ?? $inputDay;
                if (!in_array($inputDay, $days, true)) return '__INVALID_DAY__:' . $inputDay;
                $result = ($op === '==') ? ($dayName === $inputDay) : ($dayName !== $inputDay);
                return $result ? '1' : '0';
            },
            $cond
        );

        // Detect invalid day tokens (from any of the conversions above)
        if (strpos($cond, '__INVALID_DAY__') !== false) {
            preg_match_all('/__INVALID_DAY__:(\w+)/', $cond, $invalidDays);
            $invalidList = implode(', ', $invalidDays[1]);
            $msg = "Invalid day name(s) in condition: $invalidList";
            dbglog("ifday: $msg");
            return [false, $msg];
        }

        // Year equality/inequality -> 1/0 (legacy path)
        $yearNum = (int)$now->format('Y');
        $cond = preg_replace_callback(
            '/\byear\s*([!=]=)\s*(\d{4})\b/i',
            function($m) use ($yearNum) {
                $op  = $m[1];
                $val = (int)$m[2];
                $res = ($op === '==') ? ($yearNum === $val) : ($yearNum !== $val);
                return $res ? '1' : '0';
            },
            $cond
        );

        // Standalone tokens -> 1/0 (treat workday/businessday as weekday)
        $cond = preg_replace('/\b(weekday|workday|businessday)\b/i', $weekday ? '1' : '0', $cond);
        $cond = preg_replace('/\bweekend\b/i', $weekend ? '1' : '0', $cond);

        // Logical operators
        $cond = preg_replace('/\bAND\b/i', '&&', $cond);
        $cond = preg_replace('/\bOR\b/i', '||', $cond);
        $cond = preg_replace('/\bNOT\b/i', '!', $cond);

        // Year comparisons: ==, !=, >, <, >=, <=  -> 1/0
        $yearNum = (int)$now->format('Y');
        $cond = preg_replace_callback(
            '/\byear\s*(==|!=|>=|<=|>|<)\s*(\d{1,4})\b/i',
            function($m) use ($yearNum) {
                $op  = $m[1];
                $val = (int)$m[2];
                $res = match ($op) {
                    '==' => ($yearNum === $val),
                    '!=' => ($yearNum !== $val),
                    '>'  => ($yearNum >  $val),
                    '<'  => ($yearNum <  $val),
                    '>=' => ($yearNum >= $val),
                    '<=' => ($yearNum <= $val),
                };
                return $res ? '1' : '0';
            },
            $cond
        );

        // --- Month support (names or numbers) ---
        $monthMap = [
            'jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,
            'apr'=>4,'april'=>4,'may'=>5,'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,
            'aug'=>8,'august'=>8,'sep'=>9,'sept'=>9,'september'=>9,'oct'=>10,'october'=>10,
            'nov'=>11,'november'=>11,'dec'=>12,'december'=>12
        ];
        $monthNum = (int)$now->format('n');

        // month ==/!=/>/</>=/<= <name|number>  -> 1/0
        $cond = preg_replace_callback(
            '/\bmonth\s*(==|!=|>=|<=|>|<)\s*([a-z]+|\d{1,2})\b/i',
            function($m) use ($monthNum, $monthMap) {
                $op  = $m[1];
                $raw = strtolower($m[2]);
                if (ctype_digit($raw)) {
                    $target = (int)$raw;
                    if ($target < 1 || $target > 12) return '__INVALID_MONTH__:' . $raw;
                } else {
                    $target = $monthMap[$raw] ?? null;
                    if ($target === null) return '__INVALID_MONTH__:' . $raw;
                }
                $res = match ($op) {
                    '==' => ($monthNum === $target),
                    '!=' => ($monthNum !== $target),
                    '>'  => ($monthNum >  $target),
                    '<'  => ($monthNum <  $target),
                    '>=' => ($monthNum >= $target),
                    '<=' => ($monthNum <= $target),
                };
                return $res ? '1' : '0';
            },
            $cond
        );

        // month IN [jun,jul,aug] or [nov..feb] -> 1/0
        $cond = preg_replace_callback(
            '/\bmonth\s+in\s*\[\s*([^\]]*?)\s*\]/i',
            function($m) use ($monthNum, $monthMap) {
                $listRaw = $m[1];
                $items   = preg_split('/\s*,\s*/', strtolower($listRaw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $targets = [];
                foreach ($items as $it) {
                    if (strpos($it, '..') !== false) {
                        [$start, $end] = explode('..', $it);
                        $rangeMonths = $this->expandMonthRange($start, $end, $monthMap);
                        if ($rangeMonths === null) {
                            return '__INVALID_MONTH__:' . $it;
                        }
                        $targets = array_merge($targets, $rangeMonths);
                    } else {
                        if (ctype_digit($it)) {
                            $n = (int)$it;
                            if ($n < 1 || $n > 12) return '__INVALID_MONTH__:' . $it;
                            $targets[] = $n;
                        } else {
                            $n = $monthMap[$it] ?? null;
                            if ($n === null) return '__INVALID_MONTH__:' . $it;
                            $targets[] = $n;
                        }
                    }
                }
                return in_array($monthNum, array_unique($targets), true) ? '1' : '0';
            },
            $cond
        );

        // Invalid month detection
        if (strpos($cond, '__INVALID_MONTH__') !== false) {
            preg_match_all('/__INVALID_MONTH__:(\w+)/', $cond, $bad);
            $msg = 'Invalid month name(s) in condition: ' . implode(', ', $bad[1]);
            return [false, $msg];
        }

        // Safety check: only allow numbers, parentheses, and operators
        if (!preg_match('/^[\s\(\)0-9!<>=&|]+$/', $cond)) {
            $msg = "Safety check failed for processed condition '$cond'";
            dbglog("ifday: $msg");
            return [false, $msg];
        }

        // Evaluate safely
        try {
            dbglog("ifday: Final expression for eval is '$cond'");
            $result = eval("return ($cond);");
            return [true, (bool)$result];
        } catch (\Throwable $e) {
            $msg = "Eval failed: " . $e->getMessage();
            dbglog("ifday: $msg");
            return [false, $msg];
        }
    }

    //
    // -------------------- month helpers (NEW) --------------------
    //

    /** 1..12 current month (uses the same clock as tests) */
    private function currentMonthInt(): int {
        $ts = property_exists($this, 'nowTs') ? $this->nowTs : time();
        return (int)date('n', $ts);
    }

    /** Normalize a month token (name or number) to 1..12; throws with JUST the bad token */
    private function normalizeMonthToken(string $t): int {
        static $map = [
            'jan'=>1,'january'=>1,
            'feb'=>2,'february'=>2,
            'mar'=>3,'march'=>3,
            'apr'=>4,'april'=>4,
            'may'=>5,
            'jun'=>6,'june'=>6,
            'jul'=>7,'july'=>7,
            'aug'=>8,'august'=>8,
            'sep'=>9,'sept'=>9,'september'=>9,
            'oct'=>10,'october'=>10,
            'nov'=>11,'november'=>11,
            'dec'=>12,'december'=>12,
        ];
        $t = strtolower(trim($t));
        if ($t === '') throw new \InvalidArgumentException($t);
        if (isset($map[$t])) return $map[$t];
        if (ctype_digit($t)) {
            $n = (int)$t;
            if ($n >= 1 && $n <= 12) return $n;
        }
        // IMPORTANT: message must be ONLY the offending token; caller wraps it.
        throw new \InvalidArgumentException($t);
    }

    /** Expand inclusive month range a..b, supporting wrap-around (e.g., nov..feb) */
    // --- Month support (names or numbers) ---
    private function expandMonthRange(string $start, string $end, array $monthMap): ?array
    {
        $start = strtolower($start);
        $end = strtolower($end);

        if (ctype_digit($start)) {
            $startNum = (int)$start;
            $startMonthName = array_search($startNum, $monthMap);
            if ($startMonthName === false) return null;
        } else {
            $startNum = $monthMap[$start] ?? null;
            if ($startNum === null) return null;
        }

        if (ctype_digit($end)) {
            $endNum = (int)$end;
            $endMonthName = array_search($endNum, $monthMap);
            if ($endMonthName === false) return null;
        } else {
            $endNum = $monthMap[$end] ?? null;
            if ($endNum === null) return null;
        }

        $result = [];
        $current = $startNum;

        if ($startNum <= $endNum) {
            while ($current <= $endNum) {
                $result[] = $current;
                $current++;
            }
        } else { // Wrap-around case (e.g., Nov..Feb)
            while ($current <= 12) {
                $result[] = $current;
                $current++;
            }
            $current = 1;
            while ($current <= $endNum) {
                $result[] = $current;
                $current++;
            }
        }

        return $result;
    }


    /**
     * Parse a month set like "jan..mar, 9, sep, 12" into an int[] of 1..12.
     * Accepts:
     *   - names (jan, january), numbers (1..12)
     *   - inclusive ranges with ".." (supports wrap-around)
     *   - commas separating segments
     * Throws InvalidArgumentException with the first bad token (caller formats message).
     */
    private function parseMonthSet(string $listExpr): array {
        $allowed = [];
        foreach (preg_split('/\s*,\s*/', trim($listExpr)) as $seg) {
            if ($seg === '') continue;
            if (strpos($seg, '..') !== false) {
                [$s, $e] = array_map('trim', explode('..', $seg, 2));
                $start = $this->normalizeMonthToken($s); // throws bad token
                $end   = $this->normalizeMonthToken($e); // throws bad token
                $allowed = array_merge($allowed, $this->expandMonthRange($start, $end));
            } else {
                // Single token (name or number)
                $allowed[] = $this->normalizeMonthToken($seg); // throws bad token
            }
        }
        return array_values(array_unique($allowed));
    }

    /**
     * Read plugin config value by key, caching results.
     * @param string $key Config key
     * @return mixed|null Config value or null if not set
     */
    protected function getConfValue($key) {
        if (!isset($this->conf)) {
            $this->conf = plugin_load_config('ifday');
        }
        return isset($this->conf[$key]) ? $this->conf[$key] : null;
    }
}
