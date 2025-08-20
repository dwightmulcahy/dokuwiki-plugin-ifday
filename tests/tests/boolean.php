<?php
declare(strict_types=1);

/**
 * boolean.php
 * - Canonical day/logic + parse-error tests (iterated across all 7 days)
 * - Return an array of test specs:
 *     ['condition' => string, 'currentDays' => array<string>|[], 'failureMsg' => string?]
 */
return [
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
    ['condition' => 'is weekend',            'currentDays' => ['sat','sun']],
    ['condition' => 'is not weekend',        'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'day is sat',            'currentDays' => ['sat']],
    ['condition' => 'day is not sun',        'currentDays' => ['mon','tue','wed','thu','fri','sat']],
    ['condition' => 'thu',                   'currentDays' => ['thu']],
    ['condition' => 'Tue',                   'currentDays' => ['tue']],

    // Operator precedence sanity (AND binds tighter than OR)
    ['condition' => 'day == mon OR day == tue AND weekend', 'currentDays' => ['mon']],
    ['condition' => '(day == mon OR day == tue) OR weekend', 'currentDays' => ['mon','tue','sat','sun']],

    // More grouping & NOT
    ['condition' => 'NOT weekend AND (day == mon OR day == tue OR day == wed OR day == thu OR day == fri)',
        'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'NOT (day == mon) AND NOT (day == tue)', 'currentDays' => ['wed','thu','fri','sat','sun']],
    ['condition' => '((day == sat) OR (day == sun)) AND weekend', 'currentDays' => ['sat','sun']],

    // Mixed case and spacing robustness
    ['condition' => ' Day   IS   Tuesday ', 'currentDays' => ['tue']],
    ['condition' => '   is   WeekDay   ',   'currentDays' => ['mon','tue','wed','thu','fri']],

    // Else-like coverage via OR (always true)
    ['condition' => 'weekday OR weekend',   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

    // Contradictions / Impossible
    ['condition' => 'day == mon AND day != mon', 'currentDays' => []],
    ['condition' => '(day == mon AND weekend) OR (day == tue AND weekend)', 'currentDays' => []],

    // Additional invalids (safety / syntax)
    ['condition' => 'funday',          'failureMsg' => "Safety check failed for processed condition 'funday'"],
    ['condition' => 'day === mon',     'failureMsg' => "Safety check failed for processed condition 'day === mon'"],
    ['condition' => 'day = mon',       'failureMsg' => "Safety check failed for processed condition 'day = mon'"],

    // "today" comparisons
    ['condition' => 'today is monday', 'currentDays' => ['mon']],
    ['condition' => 'today == fri',    'currentDays' => ['fri']],
    ['condition' => 'today is not sun','currentDays' => ['mon','tue','wed','thu','fri','sat']],

    // Workday / businessday aliases
    ['condition' => 'workday',         'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'businessday',     'currentDays' => ['mon','tue','wed','thu','fri']],

    // Mixed usage
    ['condition' => '(today is mon AND workday)',       'currentDays' => ['mon']],
    ['condition' => '(today is tue) OR (is weekend)',   'currentDays' => ['tue','sat','sun']],

    // tomorrow/yesterday
    ['condition' => '(tomorrow is tue)',   'currentDays' => ['mon']],
    ['condition' => '(yesterday == sun)',  'currentDays' => ['mon']],

    // Today / Tomorrow / Yesterday (explicit)
    ['condition' => 'today is mon',                 'currentDays' => ['mon']],
    ['condition' => 'today == fri',                 'currentDays' => ['fri']],
    ['condition' => 'today is not sun',             'currentDays' => ['mon','tue','wed','thu','fri','sat']],
    ['condition' => 'tomorrow is tue',              'currentDays' => ['mon']],
    ['condition' => 'tomorrow != sat',              'currentDays' => ['mon','tue','wed','thu','sat','sun']],
    ['condition' => 'yesterday is sun',             'currentDays' => ['mon']],
    ['condition' => 'yesterday != mon',             'currentDays' => ['mon','wed','thu','fri','sat','sun']],

    // Mixed-case & spacing robustness
    ['condition' => 'ToDay IS MonDay',              'currentDays' => ['mon']],
    ['condition' => '   tomorrow    is    WED  ',   'currentDays' => ['tue']],

    // Multi-part relative logic
    ['condition' => 'yesterday is fri or tomorrow is mon',               'currentDays' => ['sat','sun']],
    ['condition' => '(yesterday is mon AND tomorrow is wed)',            'currentDays' => ['tue']],
    ['condition' => '((yesterday is fri) AND (tomorrow is sun))',        'currentDays' => ['sat']],
    ['condition' => '(today is mon AND day+1 == wed)',                   'currentDays' => []],
    ['condition' => '(today is mon AND today is tue)',                   'currentDays' => []],

    // Day offsets
    ['condition' => 'day+1 == sat',                 'currentDays' => ['fri']],
    ['condition' => 'day-1 == sun',                 'currentDays' => ['mon']],
    ['condition' => 'day+2 == wed',                 'currentDays' => ['mon']],
    ['condition' => 'day+3 == sun',                 'currentDays' => ['thu']],
    ['condition' => 'day-2 == sun',                 'currentDays' => ['tue']],
    ['condition' => 'day+7 == mon',                 'currentDays' => ['mon']],
    ['condition' => 'day-14 == wed',                'currentDays' => ['wed']],
    ['condition' => '(day+1 == sat OR day-1 == sat)', 'currentDays' => ['fri','sun']],
    ['condition' => 'is weekday AND (day+1 == sat)',  'currentDays' => ['fri']],
    ['condition' => 'is weekend AND (day-1 == fri)',  'currentDays' => ['sat']],

    // Aliases & business-day synonyms
    ['condition' => 'is workday',                    'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'is not businessday',            'currentDays' => ['sat','sun']],
    ['condition' => 'workday AND (today is fri)',    'currentDays' => ['fri']],
    ['condition' => 'day == wed AND is businessday', 'currentDays' => ['wed']],
    ['condition' => 'is not weekend AND (today is tue OR today is thu)', 'currentDays' => ['tue','thu']],

    // Operator precedence sanity (again)
    ['condition' => 'today is fri OR today is sat AND weekend',          'currentDays' => ['fri','sat']],
    ['condition' => 'day == mon OR tomorrow is tue AND weekend',         'currentDays' => ['mon']],

    // New day range tests
    ['condition' => 'day in [mon..fri]',         'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'day in [wed..fri]',         'currentDays' => ['wed','thu','fri']],
    ['condition' => 'day in [sat..sun]',         'currentDays' => ['sat','sun']],

    // Wrap-around ranges
    ['condition' => 'day in [sat..mon]',         'currentDays' => ['sat','sun','mon']],
    ['condition' => 'day in [sun..tue]',         'currentDays' => ['sun','mon','tue']],

    // Mixed syntax
    ['condition' => 'day in [mon..tue, thu, sat..sun]', 'currentDays' => ['mon','tue','thu','sat','sun']],

    // Full names
    ['condition' => 'day in [monday..friday]',   'currentDays' => ['mon','tue','wed','thu','fri']],

    // Wrap-around same-day range
    ['condition' => 'day in [wed..tue]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

    // Single-day ranges
    ['condition' => 'day in [mon..mon]', 'currentDays' => ['mon']],
    ['condition' => 'day in [thu..thu]', 'currentDays' => ['thu']],

    // Mixed full and abbreviated names in a list
    ['condition' => 'day in [mon,tuesday,wed,thursday]', 'currentDays' => ['mon','tue','wed','thu']],

    // Ranges mixed with single days
    ['condition' => 'day in [mon..tue, thu, sat..sun]', 'currentDays' => ['mon','tue','thu','sat','sun']],
    ['condition' => 'day in [fri..mon, wed]', 'currentDays' => ['mon','wed','fri','sat','sun']],

    // Wrap-around ranges
    ['condition' => 'day in [sun..mon]', 'currentDays' => ['sun','mon']],
    ['condition' => 'day in [sat..tue]', 'currentDays' => ['sat','sun','mon','tue']],

    // Complex logic with day ranges
    ['condition' => 'day in [mon..fri] AND is weekday', 'currentDays' => ['mon','tue','wed','thu','fri']],
    ['condition' => 'day in [sat..sun] OR day in [mon..tue]', 'currentDays' => ['mon','tue','sat','sun']],

    // Invalid tokens within a list
    ['condition' => 'day in [mon, tue, fuz]', 'failureMsg' => 'Invalid day name(s) in condition: fuz'],
    ['condition' => 'day in [thu, fri, sun..mon, foo]', 'failureMsg' => 'Invalid day name(s) in condition: foo'],

    // Invalid syntax and tokens
    ['condition' => 'day in [mon..foobar]',      'failureMsg' => 'Invalid day name(s) in condition: foobar'],
    ['condition' => 'day in [mon, tue..fuz]',    'failureMsg' => 'Invalid day name(s) in condition: fuz'],
    ['condition' => 'day in [mon..]',            'failureMsg' => 'Eval failed: syntax error, incomplete range in condition.'],

    // invalid / parse-error expectations
    ['condition' => 'today is blarg',       'failureMsg' => 'Invalid day name(s) in condition: blarg'],
    ['condition' => 'tomorrow == fRiYay',   'failureMsg' => 'Invalid day name(s) in condition: friyay'],
    ['condition' => 'day+1 == tues',        'failureMsg' => 'Invalid day name(s) in condition: tues'],
    ['condition' => 'today === mon',        'failureMsg' => "Safety check failed for processed condition 'today === mon'"],
    ['condition' => 'today',                'failureMsg' => "Safety check failed for processed condition 'today'"],
    ['condition' => 'tomorrow',             'failureMsg' => "Safety check failed for processed condition 'tomorrow'"],

    // More "Safety check failed" tests
    ['condition' => 'is fri',               'failureMsg' => "Safety check failed for processed condition 'is fri'"],
    ['condition' => 'NOT mon',              'failureMsg' => "Safety check failed for processed condition '! mon'"],
    ['condition' => 'day + 1 == tue',       'failureMsg' => "Safety check failed for processed condition 'day + 1 == tue'"],
    ['condition' => 'day <= mon',           'failureMsg' => "Safety check failed for processed condition 'day <= mon'"],
    ['condition' => 'today <= tue',         'failureMsg' => "Safety check failed for processed condition 'today <= tue'"],
    ['condition' => 'day+1 = tue',          'failureMsg' => "Safety check failed for processed condition '0 = tue'"],
    ['condition' => 'day == mon || tue',    'failureMsg' => "Safety check failed for processed condition '1 || tue'"],
    ['condition' => '(tue)',                'failureMsg' => "Safety check failed for processed condition '(tue)'"],
    ['condition' => 'is not fri',           'failureMsg' => "Safety check failed for processed condition 'is ! fri'"],
    ['condition' => 'day < tue',            'failureMsg' => "Safety check failed for processed condition 'day < tue'"],
    ['condition' => 'today is',             'failureMsg' => "Safety check failed for processed condition 'today is'"],
    ['condition' => '((day == mon))foo',    'failureMsg' => "Safety check failed for processed condition '((1))foo'"],
];
