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
    //** cached plugin configuration */
    protected ?array $conf = null;

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
    private function evaluateCondition(string $cond, ?string $currentDay = null): array {
        // Determine the day to use
        if ($currentDay !== null) {
            $currentDay = strtolower($currentDay);
            // Map abbreviation to full name if needed
            $mapAbbr = [
                'mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday',
                'thu' => 'thursday', 'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday'
            ];
            $dayName = $mapAbbr[$currentDay] ?? $currentDay;
        } else {
            $now = new DateTime();
            $dayName = strtolower($now->format('l'));
        }

        $weekday = !in_array($dayName, ['saturday', 'sunday']);
        $weekend = !$weekday;

        // Normalize whitespace and remove quotes
        $cond = trim(preg_replace('/\s+/', ' ', $cond));
        $cond = str_replace(['"', '\''], '', $cond);

        // Map shorthand day names to full day names
        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $dayAbbr = ['mon','tue','wed','thu','fri','sat','sun'];
        $mapAbbr = array_combine($dayAbbr, $days);

        // SHORTHAND: single day names or abbreviations → day == X
        $lowerCond = strtolower($cond);
        if (in_array($lowerCond, $days)) {
            $cond = 'day == ' . $lowerCond;
        } elseif (isset($mapAbbr[$lowerCond])) {
            $cond = 'day == ' . $mapAbbr[$lowerCond];
        }

        // Replace "day is X" / "day is not X" with boolean 1/0
        $cond = preg_replace_callback('/\bday\s+is\s+(not\s+)?([a-z]+)\b/i', function($m) use ($dayName, $mapAbbr, $days) {
            $negate = !empty($m[1]);
            $inputDay = strtolower($m[2]);
            $fullDay = $inputDay;
            if (isset($mapAbbr[$inputDay])) $fullDay = $mapAbbr[$inputDay];
            if (!in_array($fullDay, $days)) return '__INVALID_DAY__:' . $inputDay;
            $result = ($dayName === $fullDay);
            return ($negate ? !$result : $result) ? '1' : '0';
        }, $cond);

        // Replace standalone "is X" / "is not X" for boolean checks
        $cond = preg_replace_callback('/\bis\s+(not\s+)?(weekday|weekend)\b/i', function($m) use ($weekday, $weekend) {
            $negate = !empty($m[1]);
            $value = strtolower($m[2]) === 'weekday' ? $weekday : $weekend;
            return ($negate ? !$value : $value) ? '1' : '0';
        }, $cond);

        // Replace day comparisons "day == X" / "day != X" with 1/0
        $cond = preg_replace_callback('/\bday\s*([!=]=)\s*([a-z]+)\b/i', function ($m) use ($dayName, $mapAbbr, $days) {
            $op = $m[1];
            $inputDay = strtolower($m[2]);
            if (isset($mapAbbr[$inputDay])) $inputDay = $mapAbbr[$inputDay];
            if (!in_array($inputDay, $days)) return '__INVALID_DAY__:' . $inputDay;
            $result = ($op === '==') ? ($dayName === $inputDay) : ($dayName !== $inputDay);
            return $result ? '1' : '0';
        }, $cond);

        // Detect invalid day tokens
        if (strpos($cond, '__INVALID_DAY__') !== false) {
            preg_match_all('/__INVALID_DAY__:(\w+)/', $cond, $invalidDays);
            $invalidList = implode(', ', $invalidDays[1]);
            $msg = "Invalid day name(s) in condition: $invalidList";
            dbglog("ifday: $msg");
            return [false, $msg];
        }

        // Replace remaining standalone 'weekday' or 'weekend' with 1/0
        $cond = preg_replace('/\bweekday\b/i', $weekday ? '1' : '0', $cond);
        $cond = preg_replace('/\bweekend\b/i', $weekend ? '1' : '0', $cond);

        // Replace logical operators
        $cond = preg_replace('/\bAND\b/i', '&&', $cond);
        $cond = preg_replace('/\bOR\b/i', '||', $cond);
        $cond = preg_replace('/\bNOT\b/i', '!', $cond);

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
