<?php
declare(strict_types=1);

function is_tty(): bool {
    return function_exists('posix_isatty') && defined('STDOUT') && @posix_isatty(STDOUT);
}

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
    $p = $map[$color] ?? '';
    return $p . $text . $map['reset'];
}

function strw(string $s): int {
    return function_exists('mb_strwidth') ? mb_strwidth($s, 'UTF-8') : strlen($s);
}
function padw(string $s, int $w, string $align = 'left'): string {
    $len = strw($s);
    if ($len >= $w) return $s;
    $pad = $w - $len;
    if ($align === 'right')  return str_repeat(' ', $pad) . $s;
    if ($align === 'center') {
        $l = intdiv($pad, 2);
        $r = $pad - $l;
        return str_repeat(' ', $l) . $s . str_repeat(' ', $r);
    }
    return $s . str_repeat(' ', $pad);
}

function tt_symbols(bool $ascii): array {
    return $ascii ? ['T'=>'+','F'=>'.','X'=>'x','E'=>'!'] : ['T'=>'✓','F'=>'·','X'=>'×','E'=>'!'];
}
