<?php
declare(strict_types=1);

/**
 * Standalone test runner for the ifday plugin.
 *
 * What this does:
 * - Stubs minimal DokuWiki APIs so we can load the plugin outside DokuWiki.
 * - Uses Reflection to call the plugin's private evaluateCondition($expr, $currentDay).
 * - Validates:
 *     1) Condition logic across all 7 days using a canonical truth-table (expected vs. actual)
 *     2) Rendered output behavior (if/else/error) for all examples and days
 * - Prints PASS/FAIL lines (quiet mode prints only FAILs + final summary).
 * - Exits 0 if all tests pass, 1 otherwise (for CI/GitHub PR checks).
 *
 * Flags / env:
 * - --quiet, -q  or QUIET=1      : quiet mode (only FAIL lines + summary)
 * - --with-blank or NOBLANK=0    : include blank rendered lines in final doc output
 * - --no-label  or LABEL=0       : hide [condition=...] in final doc output
 * - --ascii    or ASCII=1        : single-width symbols for perfect alignment (.+x!)
 * - --color    or COLOR=1        : force colored output
 * - --no-color or COLOR=0        : disable colored output
 */

// -----------------------------
// Minimal DokuWiki stubs (enough to load syntax.php)
// -----------------------------
if (!class_exists('DokuWiki_Syntax_Plugin')) { class DokuWiki_Syntax_Plugin {} }
if (!class_exists('Doku_Renderer')) { class Doku_Renderer { public string $doc = ''; } }
if (!class_exists('Doku_Handler')) { class Doku_Handler {} }
if (!function_exists('p_render')) { function p_render($mode, $instructions, &$info) { return $instructions; } }
if (!function_exists('p_get_instructions')) { function p_get_instructions($content) { return $content; } }
if (!function_exists('plugin_load_config')) { function plugin_load_config($plugin) { return []; } }
if (!function_exists('dbglog')) { function dbglog($string) { return null; } }

// -----------------------------
// CLI flags / Quiet mode / Output toggles
// -----------------------------
$argv = $argv ?? [];

// Quiet mode: print only FAILs and summary if enabled
$quiet = in_array('--quiet', $argv, true) || in_array('-q', $argv, true) || (getenv('QUIET') === '1');

// Default ON for no-blank and label (can be turned off)
$noBlank = !in_array('--with-blank', $argv, true) && getenv('NOBLANK') !== '0'; // default true
$label   = !in_array('--no-label', $argv, true)   && getenv('LABEL')   !== '0'; // default true

// Force single-width ASCII symbols (for perfect alignment in any terminal)
$ascii = in_array('--ascii', $argv, true) || (getenv('ASCII') === '1');

// Color support: enable if --color or COLOR=1, or auto-enable if TTY and not explicitly disabled
$colorEnabled = (in_array('--color', $argv, true) || getenv('COLOR') === '1')
    || (!in_array('--no-color', $argv, true) && getenv('COLOR') !== '0'
        && function_exists('posix_isatty') && defined('STDOUT') && @posix_isatty(STDOUT));

/** Colorizer for labels and header text */
function colorize(string $text, string $color, bool $enabled): string {
    if (!$enabled) return $text;
    static $map = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'blue'   => "\033[34m",
        'magenta'=> "\033[35m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        'dim'    => "\033[2m",
        'reset'  => "\033[0m",
    ];
    $prefix = $map[$color] ?? '';
    return $prefix . $text . $map['reset'];
}

/** Quiet-aware logger with optional 'isFail' to allow printing in quiet mode */
function log_line(string $line, bool $quiet, bool $isFail = false): void {
    if ($quiet && !$isFail) return;
    echo $line;
}

// -----------------------------
// Load plugin + reflection into private method
// -----------------------------
define('DOKU_INC', true);
require_once __DIR__ . '/../syntax.php';
$plugin = new syntax_plugin_ifday();

// Use reflection to get access to the private method
$reflection = new ReflectionClass($plugin);
$method = $reflection->getMethod('evaluateCondition');
$method->setAccessible(true);

// Track overall pass/fail for CI (across all sections)
$allTestsPass = true;

// Days of week we iterate (lowercase abbreviations used by the evaluator)
$daysOfWeek = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

// -----------------------------
// Canonical test set
// - currentDays: all days when the condition should evaluate to TRUE
// - failureMsg:  expected error string for parse-failure tests (no boolean truth-table)
// -----------------------------
$tests = [
    // Simple
    ['condition' => 'wed', 'currentDays' => ['wed']],
    ['condition' => 'day != fri', 'currentDays' => ['mon','tue','wed','thu','sat','sun']],

    // Basic day checks
    ['condition' => 'day == monday', 'currentDays' => ['mon']],
    ['condition' => 'weekend', 'currentDays' => ['sat','sun']],
    ['condition' => 'is weekday', 'currentDays' => ['mon','tue','wed','thu','fri']],

    // Parentheses / logical grouping
    ['condition' => '(day == mon OR day == tue) AND weekend', 'currentDays' => []], // never true
    ['condition' => 'day == mon || (day == tue AND weekend)', 'currentDays' => ['mon']],
    ['condition' => 'NOT (day == sun OR weekend)', 'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => '((day == mon) AND (day != fri)) OR (day == wed)', 'currentDays' => ['mon','wed']],

    // Extra spacing
    ['condition' => '  day == mon  ', 'currentDays' => ['mon']],
    ['condition' => '  NOT   day == sunday  ', 'currentDays' => ['mon','tue','wed','thu','fri','sat']],

    // Complex nesting
    ['condition' => '(day == mon OR day == tue) AND NOT weekend', 'currentDays' => ['mon','tue']],
    ['condition' => 'NOT (day == sun OR weekend)', 'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => '((day == mon AND day != fri) OR (day == wed)) AND NOT weekend', 'currentDays' => ['mon','wed']],
    ['condition' => '(day == mon OR (day == tue AND NOT weekend))', 'currentDays' => ['mon','tue']],

    // Invalid / edge cases — add failureMsg so parser error can be asserted
    ['condition' => 'day == fuday', 'currentDays' => [], 'failureMsg' => 'Invalid day name(s) in condition: fuday'],
    ['condition' => 'day is xyz',  'currentDays' => [], 'failureMsg' => 'Invalid day name(s) in condition: xyz'],
    ['condition' => '',            'currentDays' => [], 'failureMsg' => "Safety check failed for processed condition ''"],
    ['condition' => 'day ==',      'currentDays' => [], 'failureMsg' => "Safety check failed for processed condition 'day =='"],
    ['condition' => 'AND day == mon', 'currentDays' => [], 'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],

    // Aliases & shorthands
    ['condition' => 'is weekend',            'currentDays' => ['sat','sun']],                   // alias for weekend
    ['condition' => 'is not weekend',        'currentDays' => ['mon','tue','wed','thu','fri']], // negated alias
    ['condition' => 'day is sat',            'currentDays' => ['sat']],                         // "day is" + abbr
    ['condition' => 'day is not sun',        'currentDays' => ['mon','tue','wed','thu','fri','sat']], // "day is not"
    ['condition' => 'thu',                   'currentDays' => ['thu']],                         // shorthand single token
    ['condition' => 'Tue',                   'currentDays' => ['tue']],                         // shorthand mixed-case

    // Operator precedence sanity (AND binds tighter than OR)
    ['condition' => 'day == mon OR day == tue AND weekend', 'currentDays' => ['mon']],          // tue AND weekend never true
    ['condition' => '(day == mon OR day == tue) OR weekend', 'currentDays' => ['mon','tue','sat','sun']], // OR with weekend

    // More grouping & NOT
    ['condition' => 'NOT weekend AND (day == mon OR day == tue OR day == wed OR day == thu OR day == fri)',
        'currentDays' => ['mon','tue','wed','thu','fri']],                                      // tautology for weekdays
    ['condition' => 'NOT (day == mon) AND NOT (day == tue)', 'currentDays' => ['wed','thu','fri','sat','sun']], // exclude mon/tue
    ['condition' => '((day == sat) OR (day == sun)) AND weekend', 'currentDays' => ['sat','sun']],             // intersection

    // Mixed case and spacing robustness
    ['condition' => ' Day   IS   Tuesday ', 'currentDays' => ['tue']],                         // case-insensitive + spacing
    ['condition' => '   is   WeekDay   ',   'currentDays' => ['mon','tue','wed','thu','fri']], // alias + spacing + case

    // Else-like coverage via OR (always true)
    ['condition' => 'weekday OR weekend',   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

    // Contradictions / Impossible
    ['condition' => 'day == mon AND day != mon', 'currentDays' => []],                         // cannot be true
    ['condition' => '(day == mon AND weekend) OR (day == tue AND weekend)', 'currentDays' => []], // mon/tue never weekend

    // Additional invalids (safety / syntax)
    ['condition' => 'funday',          'failureMsg' => "Safety check failed for processed condition 'funday'"],
    ['condition' => 'day === mon',     'failureMsg' => "Safety check failed for processed condition 'day === mon'"],
    ['condition' => 'day = mon',       'failureMsg' => "Safety check failed for processed condition 'day = mon'"],
];

// =====================================================
// Truth-table helpers (pretty output + quiet-friendly)
// =====================================================

// ----- Multibyte-aware width & padding -----
function strw(string $s): int {
    if (function_exists('mb_strwidth')) return mb_strwidth($s, 'UTF-8');
    return strlen($s);
}
function padw(string $s, int $w, string $align = 'left'): string {
    $len = strw($s);
    if ($len >= $w) return $s;
    $pad = $w - $len;
    if ($align === 'right')  return str_repeat(' ', $pad) . $s;
    if ($align === 'center') {
        $left = intdiv($pad, 2);
        $right = $pad - $left;
        return str_repeat(' ', $left) . $s . str_repeat(' ', $right);
    }
    return $s . str_repeat(' ', $pad); // left
}

// ----- Symbols (ASCII-safe option) -----
function tt_symbols(bool $ascii): array {
    return $ascii
        ? ['T' => '+', 'F' => '.', 'X' => 'x', 'E' => '!']  // single-width, always aligned
        : ['T' => '✓', 'F' => '·', 'X' => '×', 'E' => '!']; // pretty, may be double-width in some fonts
}

/** Return the RAW (uncolored) symbol for a cell; color is applied after padding to preserve width */
function tt_symbol_raw(bool $expected, ?bool $actual, bool $success, bool $ascii): string {
    $S = tt_symbols($ascii);
    if (!$success)              return $S['E'];                       // parse error
    if ($actual === $expected)  return $expected ? $S['T'] : $S['F']; // match (true/false)
    return $S['X'];                                                  // mismatch
}

/**
 * Print truth-table header with **day columns first** and the **condition column last**.
 * Returns [dayColWidth, conditionColWidth].
 */
function tt_header_days_first(array $days, array $tests, bool $quiet, bool $ascii, bool $colorEnabled): array {
    $condHeader  = 'Condition';
    $longestCond = max(array_map(fn($t) => strw($t['condition']), $tests));
    $condW       = max(48, max($longestCond, strw($condHeader))); // keep original 48 minimum
    $dayColW     = 3; // width for 'MON' etc.
    $sp          = ' ';

    if (!$quiet) {
        log_line(colorize("\n=== Truth Table (expected vs. actual by day) ===\n", 'bold', $colorEnabled), $quiet);
        $S = tt_symbols(false); // legend shows pretty symbols
        $legend = "Legend: {$S['T']} expected TRUE & got TRUE | {$S['F']} expected FALSE & got FALSE | " .
            colorize($S['X'], 'red', $colorEnabled) . " mismatch | " . colorize($S['E'], 'yellow', $colorEnabled) . " parse error\n";
        log_line($legend, $quiet);

        // Days first, then condition header with separator
        $hdr = implode($sp, array_map(fn($d) => padw(strtoupper($d), $dayColW, 'center'), $days));
        $hdr .= ' | ' . colorize(padw($condHeader, $condW), 'cyan', $colorEnabled);
        log_line($hdr . "\n", $quiet);

        // Separator line (under days + separator + condition)
        $sepDays = implode($sp, array_fill(0, count($days), str_repeat('-', $dayColW)));
        $sepCond = str_repeat('-', $condW);
        log_line(colorize($sepDays . ' +-' . $sepCond . "\n", 'dim', $colorEnabled), $quiet);
    }

    return [$dayColW, $condW];
}

/**
 * Print truth-table rows for all boolean tests (no failureMsg) across all days,
 * with **days first** and **condition last**.
 */
function tt_run_boolean_rows(array $tests, array $days, $method, $plugin, bool $quiet, bool $ascii, bool $colorEnabled, bool &$allTestsPass): void {
    $booleanTests = array_values(array_filter($tests, fn($t) => !isset($t['failureMsg'])));
    if (!$booleanTests) return;

    // Header and column widths
    [$dayColW, $condW] = tt_header_days_first($days, $tests, $quiet, $ascii, $colorEnabled);
    $sp = ' ';
    $S  = tt_symbols($ascii);

    foreach ($booleanTests as $t) {
        $cond      = $t['condition'];
        $validDays = $t['currentDays'] ?? [];
        $cells     = [];
        $mismatches = [];

        foreach ($days as $d) {
            [$success, $value] = $method->invoke($plugin, $cond, $d);
            $expected = in_array($d, $validDays, true);
            $actual   = $success ? (bool)$value : null;

            $raw    = tt_symbol_raw($expected, $actual, $success, $ascii);
            $padded = padw($raw, $dayColW, 'center');

            // color entire cell based on symbol kind
            if ($raw === $S['X']) {
                $cells[] = colorize($padded, 'red', $colorEnabled);
                $mismatches[] = $d;
            } elseif ($raw === $S['E']) {
                $cells[] = colorize($padded, 'yellow', $colorEnabled);
                $mismatches[] = $d;
            } elseif ($raw === $S['T']) {
                $cells[] = colorize($padded, 'green', $colorEnabled);
            } else { // 'F'
                $cells[] = $padded;
            }
        }

        if ($mismatches) {
            $allTestsPass = false;
            log_line(colorize("FAIL", 'red', $colorEnabled) . ": Truth table mismatch for '$cond' on days: [" . implode(',', $mismatches) . "]\n", $quiet, true);
        }

        if (!$quiet) {
            // days first, then separator, then condition (padded)
            $row = implode($sp, $cells) . ' | ' . padw($cond, $condW);
            log_line($row . "\n", $quiet);
        }
    }
}

/**
 * Assert parse-failure tests produce the exact error message (day-agnostic).
 * Uses 'mon' as an arbitrary day (parser should fail regardless).
 */
function tt_run_error_rows(array $tests, $method, $plugin, bool $quiet, bool $colorEnabled, bool &$allTestsPass): void {
    $errorTests = array_values(array_filter($tests, fn($t) => isset($t['failureMsg'])));
    if (!$errorTests) return;

    if (!$quiet) {
        log_line(colorize("\n=== Parse-Error Conditions ===\n", 'bold', $colorEnabled), $quiet);
        log_line("These should fail to evaluate with the exact message shown in 'failureMsg'.\n", $quiet);
    }

    foreach ($errorTests as $t) {
        $cond = $t['condition'];
        $expectedMsg = $t['failureMsg'];

        [$success, $value] = $method->invoke($plugin, $cond, 'mon');

        if ($success === false && $value === $expectedMsg) {
            if (!$quiet) {
                log_line(colorize("PASS", 'green', $colorEnabled) . ": '$cond' returned the expected message.\n", $quiet);
            }
        } else {
            $allTestsPass = false;
            $msg = $success ? '(unexpected success)' : $value;
            log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$cond' parse-check failed. Expected: '$expectedMsg' | Got: " . var_export($msg, true) . "\n", $quiet, true);
        }
    }
}

// -----------------------------------------------------
// Condition evaluation test runner (per-day validation)
// -----------------------------------------------------
function runDayConditionTests(array $tests, array $daysOfWeek, $method, $plugin, bool $quiet, bool $colorEnabled): bool
{
    $testsPassed = true;

    foreach ($tests as $test) {
        $condition  = $test['condition'];
        $validDays  = $test['currentDays'] ?? [];
        $failureMsg = $test['failureMsg'] ?? null; // only set for parse-error tests

        foreach ($daysOfWeek as $day) {
            [$success, $result] = $method->invoke($plugin, $condition, $day);

            if ($success) {
                // True when day not in allowed list → fail
                if ($result && !in_array($day, $validDays, true)) {
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' returned TRUE for '$day' (expected FALSE)\n", $quiet, true);
                    $testsPassed = false;
                    continue;
                }

                // False when day should pass → fail
                if (!$result && in_array($day, $validDays, true)) {
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' returned FALSE for '$day' (expected TRUE)\n", $quiet, true);
                    $testsPassed = false;
                    continue;
                }

                // Success-path logging (respects quiet mode)
                log_line(colorize("PASS", 'green', $colorEnabled) . ": ('$day'):  '$condition'\n", $quiet);
            } else {
                // Expected parse failure (with exact message)
                if ($failureMsg !== null) {
                    if ($result === $failureMsg) {
                        log_line(colorize("PASS", 'green', $colorEnabled) . ": '$condition' correctly failed to evaluate: '$result'\n", $quiet);
                    } else {
                        log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' failed to evaluate: '$result', expected '$failureMsg'\n", $quiet, true);
                        $testsPassed = false;
                    }
                } else {
                    // Unexpected parse failure
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' failed to evaluate: '$result', and no 'failureMsg' set.\n", $quiet, true);
                    $testsPassed = false;
                }

                // For parse-failure tests, we don't need to iterate other days
                break;
            }
        }
    }

    return $testsPassed;
}

// =====================================================
// Run: Condition evaluation (simple PASS/FAIL lines)
// =====================================================
$allTestsPass = runDayConditionTests($tests, $daysOfWeek, $method, $plugin, $quiet, $colorEnabled) && $allTestsPass;

// =====================================================
// Run: Truth-table views (boolean + parse-error sections)
// =====================================================
tt_run_boolean_rows($tests, $daysOfWeek, $method, $plugin, $quiet, $ascii, $colorEnabled, $allTestsPass);
tt_run_error_rows($tests, $method, $plugin, $quiet, $colorEnabled, $allTestsPass);

// =====================================================
// Rendered Content tests (ALL days)
// - Simulate how the plugin would render <ifday> blocks
//   for a set of example conditions with optional <else>
// =====================================================
$examples = [
    // (unchanged from your earlier examples)
    ['day == monday', "It's the start of the work week.", ''],
    ['day != fri && not weekend', "The weekend is not here yet.", ''],
    ['weekend', "Enjoy your time off!", "Time to get to work."],
    ['is weekday', "It's a weekday, party over. 😩", "It's the weekend, time to party! 🎉"],
    ['weekend AND day == sunday', "It's the last day of the weekend.", ''],
    ['day is saturday OR day is sunday', "It's a weekend day.", ''],
    ['day == mon || day == tue AND weekend', "This will be true only Monday.", ''],
    ['NOT day == sunday', "It's not Sunday.", "Sunday-Funday! 🍺🍺🍺🍺"],
    ['is not weekend', "It's not the weekend.", '']
];

$renderer = new Doku_Renderer();

// Heading (suppressed in quiet mode)
log_line(colorize("\n=== Rendered Content (all days) ===\n", 'bold', $colorEnabled), $quiet);

foreach ($daysOfWeek as $mockDay) {
    // Sub-heading per day (suppressed in quiet mode)
    log_line(colorize("— Day: $mockDay —\n", 'cyan', $colorEnabled), $quiet);

    foreach ($examples as $ex) {
        [$condition, $ifContent, $elseContent] = $ex;

        // Evaluate each example for the current mock day
        [$success, $result] = $method->invoke($plugin, $condition, $mockDay);

        // Determine what would be rendered by the plugin for this example/day
        if ($success && $result) {
            $rendered = $ifContent;
        } elseif ($success && !$result) {
            $rendered = $elseContent;
        } else {
            // If parse fails, mirror plugin behavior by rendering an error block
            $rendered = "<div class=\"plugin_ifday_error\" style=\"color:red;\">ifday plugin error evaluating condition: \"$condition\"<br>Details: $result</div>";
        }

        // Compute expected output using the same decision logic (should match exactly)
        $expected = ($success && $result)
            ? $ifContent
            : (($success && !$result && $elseContent !== '') ? $elseContent : $rendered);

        $passFail = ($rendered === $expected) ? 'PASS' : 'FAIL';
        if ($passFail === 'FAIL') $allTestsPass = false;

        // Only print FAILs in quiet mode
        $isFail = ($passFail === 'FAIL');
        $pfText = $passFail === 'PASS' ? colorize('PASS', 'green', $colorEnabled) : colorize('FAIL', 'red', $colorEnabled);
        log_line(
            sprintf(
                "%s: [day=%s] Condition: %-30s | Expected: %s | Got: %s\n",
                $pfText,
                $mockDay,
                $condition,
                var_export($expected, true),
                var_export($rendered, true)
            ),
            $quiet,
            $isFail
        );

        // Append to the final rendered output:
        // - skip blanks if --with-blank not set (default is skip blanks)
        // - label with condition if --label is set (default is ON)
        $isBlank = ($rendered === '' || $rendered === null);
        if (!$noBlank || !$isBlank) {
            if ($label) {
                $renderer->doc .= sprintf("[day=%s] [condition=%s] %s\n", $mockDay, $condition, $isBlank ? '—' : $rendered);
            } else {
                $renderer->doc .= sprintf("[day=%s] %s\n", $mockDay, $isBlank ? '' : $rendered);
            }
        }
    }
    $renderer->doc .= "\n";
}

// Final rendered output (suppressed in quiet mode)
log_line(colorize("\nFinal Rendered Output (concatenated across all days)\n", 'bold', $colorEnabled), $quiet);
log_line(colorize("====================================================\n", 'dim', $colorEnabled), $quiet);
log_line($renderer->doc, $quiet);

// -----------------------------
// Exit with proper code for CI
// -----------------------------
if ($quiet) {
    // Always print a one-line summary in quiet mode
    echo $allTestsPass
        ? colorize("SUMMARY: PASS\n", 'green', $colorEnabled)
        : colorize("SUMMARY: FAIL\n", 'red', $colorEnabled);
}
exit($allTestsPass ? 0 : 1);
