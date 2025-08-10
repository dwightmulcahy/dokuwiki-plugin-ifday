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
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
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
     * Handle matched syntax block, extract condition and content
     * @param string $match Full matched string
     * @param int $state Lexer state
     * @param int $pos Position
     * @param Doku_Handler $handler
     * @return array Condition string and content string
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        if (!preg_match('/^<ifday\s+(.*?)>(.*?)<\/ifday>$/is', $match, $m)) {
            return ['', ''];
        }
        return [trim($m[1]), $m[2]];
    }

    /**
     * Render the content based on evaluated condition
     * @param string $mode Render mode, e.g. 'xhtml'
     * @param Doku_Renderer $R Renderer object
     * @param array $data Array with [condition, content]
     * @return bool True if rendering was handled
     */
    public function render($mode, Doku_Renderer $R, $data) {
        if ($mode !== 'xhtml') return false;

        list($condition, $content) = $data;

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

        if ($evalResult) {
            $R->doc .= $content;
            dbglog("ifday: Condition '$condition' was TRUE. Content will be displayed.");
        } else {
            dbglog("ifday: Condition '$condition' was FALSE. Content will be hidden.");
        }
        return true;
    }

    /**
     * Evaluate the condition string and return a tuple [success, boolean result or error message]
     * @param string $cond The condition expression string
     * @return array [bool success, bool|string result or error message]
     */
    private function evaluateCondition($cond) {
        $now = new DateTime();
        $dayName = strtolower($now->format('l')); // full day name in lowercase, e.g. "monday"
        $weekday = !in_array($dayName, ['saturday', 'sunday']);
        $weekend = !$weekday;

        // Normalize whitespace: trim and collapse multiple spaces to single space
        $cond = trim(preg_replace('/\s+/', ' ', $cond));

        // Remove quotes early for simpler processing
        $cond = str_replace(['"', '\''], '', $cond);

        // --- SHORTHAND: If condition is a single day name or abbreviation, rewrite it as "day == [day]" ---
        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $dayAbbr = ['mon','tue','wed','thu','fri','sat','sun'];

        if (in_array(strtolower($cond), $days)) {
            $cond = 'day == ' . strtolower($cond);
        } elseif (in_array(strtolower($cond), $dayAbbr)) {
            $map = array_combine($dayAbbr, $days);
            $cond = 'day == ' . $map[strtolower($cond)];
        }

        // --- Improved day comparison with invalid day name detection ---
        $cond = preg_replace_callback(
            '/\bday\s*([!=]=)\s*([a-z]+)\b/i',
            function ($m) use ($dayName, $days, $dayAbbr) {
                $inputDay = strtolower($m[2]);
                $map = array_combine($dayAbbr, $days);

                // Validate day name or abbreviation
                if (in_array($inputDay, $days)) {
                    $fullDay = $inputDay;
                } elseif (isset($map[$inputDay])) {
                    $fullDay = $map[$inputDay];
                } else {
                    // Invalid day detected — return special token to signal error
                    return '__INVALID_DAY__:' . $inputDay;
                }

                $isEqual = ($m[1] === '==');
                return ($isEqual ? $dayName === $fullDay : $dayName !== $fullDay) ? 'true' : 'false';
            },
            $cond
        );

        // Check if invalid day tokens are present after replacement
        if (strpos($cond, '__INVALID_DAY__') !== false) {
            preg_match_all('/__INVALID_DAY__:(\w+)/', $cond, $invalidDays);
            $invalidList = implode(', ', $invalidDays[1]);
            $msg = "Invalid day name(s) in condition: $invalidList";
            dbglog("ifday: $msg");
            return [false, $msg];
        }

        // Replace standalone 'weekday' or 'weekend' (without comparison operators) with true/false
        $cond = preg_replace('/\bweekday\b(?!\s*[!=]=)/i', $weekday ? 'true' : 'false', $cond);
        $cond = preg_replace('/\bweekend\b(?!\s*[!=]=)/i', $weekend ? 'true' : 'false', $cond);

        // Replace "is" with "==", only when used as comparison operator
        $cond = preg_replace('/\bis\b/i', '==', $cond);

        // Replace logical operators
        $cond = preg_replace('/\bAND\b/i', '&&', $cond);
        $cond = preg_replace('/\bOR\b/i', '||', $cond);

        // Support negation: replace NOT with !
        $cond = preg_replace('/\bNOT\b/i', '!', $cond);

        // Replace boolean strings with 1 and 0 for eval
        $cond = str_replace(['true', 'false'], ['1', '0'], $cond);

        // Safety check: allow only safe characters to prevent code injection
        if (!preg_match('/^[\s\(\)0-9<>=!&|]+$/i', $cond)) {
            $msg = "Safety check failed for processed condition '$cond'";
            dbglog("ifday: $msg");
            return [false, $msg];
        }

        // Evaluate the final condition expression safely
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
     * Extend this if your plugin supports config settings.
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
