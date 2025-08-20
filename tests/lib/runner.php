<?php
declare(strict_types=1);

/**
 * runner.php
 * - Core test execution functions.
 * - Uses $method (ReflectionMethod) and $evaluator from reflect.php.
 */

require_once __DIR__ . '/output.php';

/** Evaluate "boolean" tests across all days with PASS/FAIL lines and truth-table view */
function runDayConditionTests(array $tests, array $daysOfWeek, $method, $evaluator, bool $quiet, bool $colorEnabled): bool {
    $testsPassed = true;
    foreach ($tests as $test) {
        $condition  = $test['condition'];
        $validDays  = $test['currentDays'] ?? [];
        $failureMsg = $test['failureMsg'] ?? null;
        foreach ($daysOfWeek as $day) {
            [$success, $result] = $method->invoke($evaluator, $condition, new DateTime('last ' . $day));
            if ($success) {
                if ($result && !in_array($day, $validDays, true)) {
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' returned TRUE for '$day' (expected FALSE)\n", $quiet, true);
                    $testsPassed = false;
                    continue;
                }
                if (!$result && in_array($day, $validDays, true)) {
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' returned FALSE for '$day' (expected TRUE)\n", $quiet, true);
                    $testsPassed = false;
                    continue;
                }
                log_line(colorize("PASS", 'green', $colorEnabled) . ": ('$day'):  '$condition'\n", $quiet);
            } else {
                if ($failureMsg !== null) {
                    if ($result === $failureMsg) {
                        log_line(colorize("PASS", 'green', $colorEnabled) . ": '$condition' correctly failed to evaluate: '$result'\n", $quiet);
                    } else {
                        log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' failed to evaluate: '$result', expected '$failureMsg'\n", $quiet, true);
                        $testsPassed = false;
                    }
                } else {
                    log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' failed to evaluate: '$result', and no 'failureMsg' set.\n", $quiet, true);
                    $testsPassed = false;
                }
                break;
            }
        }
    }
    return $testsPassed;
}

/** Print truth-table rows (days first, condition last) */
function tt_run_boolean_rows(array $rowTests, array $days, $method, $evaluator, bool $quiet, bool $ascii, bool $colorEnabled, bool &$allTestsPass): void {
    $booleanTests = array_values(array_filter($rowTests, fn($t) => !isset($t['failureMsg'])));
    if (!$booleanTests) return;
    [$dayColW, $condW] = tt_header_days_first($days, $rowTests, $quiet, $ascii, $colorEnabled);
    $sp = ' ';
    $S  = tt_symbols($ascii);
    foreach ($booleanTests as $t) {
        $cond      = $t['condition'];
        $validDays = $t['currentDays'] ?? [];
        $cells     = [];
        $mismatches = [];
        foreach ($days as $d) {
            [$success, $value] = $method->invoke($evaluator, $cond, new DateTime('last ' . $d));
            $expected = in_array($d, $validDays, true);
            $actual   = $success ? (bool)$value : null;
            $raw    = tt_symbol_raw($expected, $actual, $success, $ascii);
            $padded = padw($raw, $dayColW, 'center');
            if ($raw === $S['X']) {
                $cells[] = colorize($padded, 'red', $colorEnabled);
                $mismatches[] = $d;
            } elseif ($raw === $S['E']) {
                $cells[] = colorize($padded, 'yellow', $colorEnabled);
                $mismatches[] = $d;
            } elseif ($raw === $S['T']) {
                $cells[] = colorize($padded, 'green', $colorEnabled);
            } else {
                $cells[] = $padded;
            }
        }
        if ($mismatches) {
            $allTestsPass = false;
            log_line(colorize("FAIL", 'red', $colorEnabled) . ": Truth table mismatch for '$cond' on days: [" . implode(',', $mismatches) . "]\n", $quiet, true);
        }
        if (!$quiet) {
            $row = implode($sp, $cells) . ' | ' . padw($cond, $condW);
            log_line($row . "\n", $quiet);
        }
    }
}

/** Parse-error tests: assert exact error string (single-day, day-agnostic) */
function tt_run_error_rows(array $tests, $method, $evaluator, bool $quiet, bool $colorEnabled, bool &$allTestsPass): void {
    $errorTests = array_values(array_filter($tests, fn($t) => isset($t['failureMsg'])));
    if (!$errorTests) return;
    if (!$quiet) {
        log_line(colorize("\n=== Parse-Error Conditions ===\n", 'bold', $colorEnabled), $quiet);
        log_line("These should fail to evaluate with the exact message shown in 'failureMsg'.\n", $quiet);
    }
    foreach ($errorTests as $t) {
        $cond = $t['condition'];
        $expectedMsg = $t['failureMsg'];
        [$success, $value] = $method->invoke($evaluator, $cond, new DateTime('last mon'));
        if ($success === false && $value === $expectedMsg) {
            if (!$quiet) log_line(colorize("PASS", 'green', $colorEnabled) . ": '$cond' returned the expected message.\n", $quiet);
        } else {
            $allTestsPass = false;
            $msg = $success ? '(unexpected success)' : $value;
            log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$cond' parse-check failed. Expected: '$expectedMsg' | Got: " . var_export($msg, true) . "\n", $quiet, true);
        }
    }
}

/** Swap IFDAY_TEST_DATE for a scoped test block */
function withTestDate(string $date, callable $fn): void {
    $prev = getenv('IFDAY_TEST_DATE');
    putenv("IFDAY_TEST_DATE=$date");
    try { $fn(); } finally {
        if ($prev === false || $prev === null) putenv('IFDAY_TEST_DATE');
        else putenv("IFDAY_TEST_DATE=$prev");
    }
}

/** Run tests that depend on a specific, non-looping date (true/false only, no per-day loop) */
function runSpecificDateTests(array $tests, $method, $evaluator, bool $quiet, bool $colorEnabled): bool {
    $testsPassed = true;
    foreach ($tests as $test) {
        $condition  = $test['condition'];
        $failureMsg = $test['failureMsg'] ?? null;
        $expectedResult = ($test['currentDays'] ?? []) !== [];
        [$success, $result] = $method->invoke($evaluator, $condition);
        if ($success) {
            if ($result !== $expectedResult) {
                log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' returned " . var_export($result, true) . " (expected " . var_export($expectedResult, true) . ")\n", $quiet, true);
                $testsPassed = false;
            } else {
                log_line(colorize("PASS", 'green', $colorEnabled) . ": '$condition'\n", $quiet);
            }
        } else {
            if ($failureMsg !== null && $result === $failureMsg) {
                log_line(colorize("PASS", 'green', $colorEnabled) . ": '$condition' correctly failed: '$result'\n", $quiet);
            } else {
                log_line(colorize("FAIL", 'red', $colorEnabled) . ": '$condition' failed unexpectedly: '$result' (expected '$failureMsg')\n", $quiet, true);
                $testsPassed = false;
            }
        }
    }
    return $testsPassed;
}

/** Rendered-content examples across all days */
function run_rendered_examples(array $examples, array $daysOfWeek, $method, $evaluator, bool $quiet, bool $colorEnabled, bool $noBlank, bool $label, string &$finalDoc, bool &$allTestsPass): void {
    if (!$quiet) log_line(colorize("\n=== Rendered Content (all days) ===\n", 'bold', $colorEnabled), $quiet);
    foreach ($daysOfWeek as $mockDay) {
        if (!$quiet) log_line(colorize("— Day: $mockDay —\n", 'cyan', $colorEnabled), $quiet);
        foreach ($examples as $ex) {
            [$condition, $ifContent, $elseContent] = $ex;
            [$success, $result] = $method->invoke($evaluator, $condition, new DateTime('last ' . $mockDay));
            if ($success && $result)       $rendered = $ifContent;
            elseif ($success && !$result)  $rendered = $elseContent;
            else                            $rendered = "<div class=\"plugin_ifday_error\" style=\"color:red;\">ifday plugin error evaluating condition: \"$condition\"<br>Details: $result</div>";

            $expected = ($success && $result) ? $ifContent : (($success && !$result && $elseContent !== '') ? $elseContent : $rendered);
            $passFail = ($rendered === $expected) ? 'PASS' : 'FAIL';
            if ($passFail === 'FAIL') $allTestsPass = false;
            $isFail = ($passFail === 'FAIL');
            $pfText = $passFail === 'PASS' ? colorize('PASS', 'green', $colorEnabled) : colorize('FAIL', 'red', $colorEnabled);
            log_line(sprintf("%s: [day=%s] Condition: %-30s | Expected: %s | Got: %s\n",
                $pfText, $mockDay, $condition, var_export($expected, true), var_export($rendered, true)), $quiet, $isFail);

            $isBlank = ($rendered === '' || $rendered === null);
            if (!$noBlank || !$isBlank) {
                if ($label) $finalDoc .= sprintf("[day=%s] [condition=%s] %s\n", $mockDay, $condition, $isBlank ? '—' : $rendered);
                else        $finalDoc .= sprintf("[day=%s] %s\n", $mockDay, $isBlank ? '' : $rendered);
            }
        }
        $finalDoc .= "\n";
    }
}
