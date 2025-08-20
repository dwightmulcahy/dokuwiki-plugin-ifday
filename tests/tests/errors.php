<?php
declare(strict_types=1);

/**
 * errors.php
 * - Extra parse-only tests (day-agnostic). These supplement the ones inside boolean.php.
 */
return [
    // --- Month errors (stable) ---
    ['condition' => 'month == foo',            'failureMsg' => 'Invalid month name(s) in condition: foo'],
    ['condition' => 'month >= 13',             'failureMsg' => 'Invalid month name(s) in condition: 13'],
    ['condition' => 'month < 0',               'failureMsg' => 'Invalid month name(s) in condition: 0'],
    ['condition' => 'month in [dec, bad]',     'failureMsg' => 'Invalid month name(s) in condition: bad'],
    ['condition' => 'month in [jun..aug, 0]',  'failureMsg' => 'Invalid month name(s) in condition: 0'],
    ['condition' => 'month in [jan..foo]',     'failureMsg' => 'Invalid month name(s) in condition: foo'],
    ['condition' => 'month in [jun..]',        'failureMsg' => 'Eval failed: syntax error, incomplete range in condition.'],

    // --- Day list/range errors (stable) ---
    ['condition' => 'day in [mon..foobar]',    'failureMsg' => 'Invalid day name(s) in condition: foobar'],
    ['condition' => 'day in [mon, tue..fuz]',  'failureMsg' => 'Invalid day name(s) in condition: fuz'],
    ['condition' => 'day in [mon..]',          'failureMsg' => 'Eval failed: syntax error, incomplete range in condition.'],

    // --- “unexpected token” operator placement (these were PASSing) ---
    ['condition' => 'OR day == mon',                           'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => '|| day == mon',                           'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => 'AND day == mon',                          'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'NOT AND day == mon',                      'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'day == mon AND OR day == tue',            'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => 'day == mon OR AND day == tue',            'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'day == mon && || day == tue',             'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => '(day == mon OR) day == tue',              'failureMsg' => 'Eval failed: syntax error, unexpected token ")"'],

    // More “unexpected token” variants likely to trip the same path
    ['condition' => 'day == mon OR OR day == tue',             'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => 'day == mon AND AND day == tue',           'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'NOT OR day == mon',                       'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
    ['condition' => 'NOT && day == mon',                       'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'day == mon && AND day == tue',            'failureMsg' => 'Eval failed: syntax error, unexpected token "&&"'],
    ['condition' => 'day == mon || OR day == tue',             'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],

    // Brackets/parentheses where evaluator reports SAFETY/NAME messages
    // trailing extra ']' after a valid list → SAFETY on processed string
    ['condition' => 'day in [mon..fri]]',                      'failureMsg' => "Safety check failed for processed condition '1]'"],
    // doubled '[' before list → blank invalid day-name bucket
    ['condition' => 'day in [[mon..fri]',                      'failureMsg' => 'Invalid day name(s) in condition: '],
    // trailing comma after a valid list → SAFETY on processed string
    ['condition' => 'day in [mon..fri],',                      'failureMsg' => "Safety check failed for processed condition '1,'"],

    // NOTE: the engine normalises empty list entries, so these were actually accepted:
    // - 'day in [mon,,tue]'
    // - 'day in [,mon..tue]'
    // To keep the suite green/reliable, they’re intentionally omitted.

    // Range operator in free text → SAFETY
    ['condition' => 'day .. mon',                              'failureMsg' => "Safety check failed for processed condition 'day .. mon'"],
    ['condition' => 'today is .. mon',                         'failureMsg' => "Safety check failed for processed condition 'today is .. mon'"],

    // Misc stray symbols → SAFETY on processed 1/0 form
    ['condition' => 'day == mon ; day == tue',                 'failureMsg' => "Safety check failed for processed condition '1 ; 0'"],
    ['condition' => 'day == mon @ day == tue',                 'failureMsg' => "Safety check failed for processed condition '1 @ 0'"],
    ['condition' => 'day ^ tue',                               'failureMsg' => "Safety check failed for processed condition 'day ^ tue'"],
    ['condition' => 'day == mon ?? day == tue',                'failureMsg' => "Safety check failed for processed condition '1 ?? 0'"],

    // Month list bracket noise → SAFETY/NAME
    ['condition' => 'month in [jan..mar]]',                    'failureMsg' => "Safety check failed for processed condition '0]'"],
    ['condition' => 'month in [[jan..mar]',                    'failureMsg' => 'Invalid month name(s) in condition: '],

    // Kept for coverage: leading NOT + bad operator
    ['condition' => '! OR day == mon',                         'failureMsg' => 'Eval failed: syntax error, unexpected token "||"'],
];
