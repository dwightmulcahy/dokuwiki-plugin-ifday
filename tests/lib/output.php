<?php
declare(strict_types=1);

/**
 * output.php
 * - Shared output helpers (TTY color, padding, legend, quiet-aware logging)
 */

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

function log_line(string $line, bool $quiet, bool $isFail = false): void {
    if ($quiet && !$isFail) return;
    echo $line;
}

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

function tt_symbols(bool $ascii): array {
    return $ascii
        ? ['T' => '+', 'F' => '.', 'X' => 'x', 'E' => '!']
        : ['T' => '✓', 'F' => '·', 'X' => '×', 'E' => '!'];
}
function tt_symbol_raw(bool $expected, ?bool $actual, bool $success, bool $ascii): string {
    $S = tt_symbols($ascii);
    if (!$success)              return $S['E'];
    if ($actual === $expected)  return $expected ? $S['T'] : $S['F'];
    return $S['X'];
}

function tt_header_days_first(array $days, array $tests, bool $quiet, bool $ascii, bool $colorEnabled): array {
    $condHeader  = 'Condition';
    $longestCond = max(array_map(fn($t) => strw($t['condition']), $tests));
    $condW       = max(48, max($longestCond, strw($condHeader)));
    $dayColW     = 3;
    $sp          = ' ';

    if (!$quiet) {
        log_line(colorize("\n=== Truth Table (expected vs. actual by day) ===\n", 'bold', $colorEnabled), $quiet);
        $S = tt_symbols(false);
        $legend = "Legend: {$S['T']} expected TRUE & got TRUE | {$S['F']} expected FALSE & got FALSE | " .
            colorize($S['X'], 'red', $colorEnabled) . " mismatch | " . colorize($S['E'], 'yellow', $colorEnabled) . " parse error\n";
        log_line($legend, $quiet);

        $hdr = implode($sp, array_map(fn($d) => padw(strtoupper($d), $dayColW, 'center'), $days));
        $hdr .= ' | ' . colorize(padw($condHeader, $condW), 'cyan', $colorEnabled);
        log_line($hdr . "\n", $quiet);

        $sepDays = implode($sp, array_fill(0, count($days), str_repeat('-', $dayColW)));
        $sepCond = str_repeat('-', $condW);
        log_line(colorize($sepDays . ' +-' . $sepCond . "\n", 'dim', $colorEnabled), $quiet);
    }

    return [$dayColW, $condW];
}
