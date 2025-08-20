<?php
declare(strict_types=1);

/**
 * run.php
 * Standalone modular runner.
 * Usage:
 *   php ifday-tests/run.php [--quiet|-q] [--with-blank] [--no-label] [--ascii] [--color|--no-color]
 */

$argv = $argv ?? [];
$quiet = in_array('--quiet', $argv, true) || in_array('-q', $argv, true) || (getenv('QUIET') === '1');
$noBlank = !in_array('--with-blank', $argv, true) && getenv('NOBLANK') !== '0';
$label   = !in_array('--no-label', $argv, true)   && getenv('LABEL')   !== '0';
$ascii = in_array('--ascii', $argv, true) || (getenv('ASCII') === '1');
$colorEnabled = (in_array('--color', $argv, true) || getenv('COLOR') === '1')
    || (!in_array('--no-color', $argv, true) && getenv('COLOR') !== '0'
        && function_exists('posix_isatty') && defined('STDOUT') && @posix_isatty(STDOUT));

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/reflect.php';
require_once __DIR__ . '/lib/tests.php';
require_once __DIR__ . '/lib/runner.php';
require_once __DIR__ . '/lib/output.php';

$allTestsPass = true;
$daysOfWeek = days_of_week();

// === Boolean/Parse Tests ===
log_line(colorize("=== Boolean/Parse Tests ===\n", 'bold', $colorEnabled), $quiet);
$tests = load_boolean_tests();
if ($tests) {
    $allTestsPass = runDayConditionTests($tests, $daysOfWeek, $method, $evaluator, $quiet, $colorEnabled) && $allTestsPass;
    tt_run_boolean_rows($tests, $daysOfWeek, $method, $evaluator, $quiet, $ascii, $colorEnabled, $allTestsPass);
    tt_run_error_rows($tests, $method, $evaluator, $quiet, $colorEnabled, $allTestsPass);
} else {
    log_line(colorize("No boolean tests loaded.\n", 'yellow', $colorEnabled), $quiet);
}

// === Extra error tests (month/year parse-only) ===
$err = load_error_tests();
if ($err) {
    tt_run_error_rows($err, $method, $evaluator, $quiet, $colorEnabled, $allTestsPass);
}

// === Rendered Examples ===
$examples = load_rendered_examples();
$finalDoc = '';
if ($examples) {
    log_line(colorize("\n=== Rendered Examples (rendered) ===\n", 'bold', $colorEnabled), $quiet);
    run_rendered_examples($examples, $daysOfWeek, $method, $evaluator, $quiet, $colorEnabled, $noBlank, $label, $finalDoc, $allTestsPass);
    if (!$quiet) {
        log_line(colorize("\nFinal Rendered Output (concatenated across all days)\n", 'bold', $colorEnabled), $quiet);
        log_line(colorize("====================================================\n", 'dim', $colorEnabled), $quiet);
        log_line($finalDoc, $quiet);
    }
}

// === Snapshot Blocks (fixed IFDAY_TEST_DATE) ===
$snapshots = load_snapshot_sets();
if ($snapshots) {
    foreach ($snapshots as $snap) {
        $date = $snap['date'];
        $name = $snap['name'];
        $set = $snap['tests'];
        withTestDate($date, function() use ($date, $name, $set, $daysOfWeek, $method, $evaluator, $quiet, $ascii, $colorEnabled, &$allTestsPass) {
            log_line(colorize("\n=== {$name} Snapshot @ {$date} ===\n", 'bold', $colorEnabled), $quiet);
            $allTestsPass = runSpecificDateTests($set, $method, $evaluator, $quiet, $colorEnabled) && $allTestsPass;
            tt_run_boolean_rows($set, $daysOfWeek, $method, $evaluator, $quiet, $ascii, $colorEnabled, $allTestsPass);
            tt_run_error_rows($set, $method, $evaluator, $quiet, $colorEnabled, $allTestsPass);
        });
    }
}

if ($quiet) {
    echo $allTestsPass
        ? colorize("SUMMARY: PASS\n", 'green', $colorEnabled)
        : colorize("SUMMARY: FAIL\n", 'red', $colorEnabled);
}
exit($allTestsPass ? 0 : 1);
