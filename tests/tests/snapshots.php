<?php
declare(strict_types=1);

/**
 * snapshots.php
 * - Month/Year snapshot tests keyed by a fixed IFDAY_TEST_DATE.
 * - Return an array of snapshots: [ ['date' => 'YYYY-MM-DD', 'name' => 'Label', 'tests' => [...]] ]
 *
 * Notes:
 * - For snapshot tests we treat 'currentDays' as a boolean flag:
 *     - non-empty (usually all 7 days)  => expected TRUE for that fixed date
 *     - empty array []                  => expected FALSE for that fixed date
 * - These sets deliberately mix:
 *     * named and numeric months
 *     * wrap-around ranges (e.g., [nov..feb], [2..1])
 *     * extra spacing and mixed case
 *     * conjunctions with weekday/weekend that are deterministic for the fixed dates below
 */

return [
    [
        'date' => '2025-12-15', // Monday
        'name' => 'December 2025',
        'tests' => [
            // Basic truthy/falsey month checks
            ['condition' => 'month == dec',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => '   month   ==   DeC  ',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']], // spacing + case
            ['condition' => 'month != january',       'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == 12',            'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month != 12',            'currentDays' => []],
            ['condition' => 'month >= nov',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <= nov',           'currentDays' => []],
            ['condition' => 'month >  6',             'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <  12',            'currentDays' => []],
            ['condition' => 'month <= 12',            'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Membership and ranges (including wrap-around)
            ['condition' => 'month in [jun,jul,aug]',                  'currentDays' => []],
            ['condition' => 'month in [jul..aug] AND weekend',         'currentDays' => []], // LHS false → overall false
            ['condition' => 'year == 2025 AND (month > 6 AND month < 9)', 'currentDays' => []],
            ['condition' => 'month in [jul..jul]',                     'currentDays' => []],
            ['condition' => 'month in [11..11]',                       'currentDays' => []],
            ['condition' => 'month in [nov..dec]',                     'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [dec, 1..3]',                    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [10..12, jan]',                  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [1..12]',                        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']], // full span
            ['condition' => 'month in [nov..jan, mar]',                'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']], // wrap-through
            ['condition' => 'month in [oct..feb, mar]',                'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Month + weekday/weekend (Dec 15, 2025 is Monday)
            ['condition' => 'month == dec AND is weekday',             'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == dec AND weekend',                'currentDays' => []],

            // Year checks
            ['condition' => 'year == 2025',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year != 1999',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year >  2024',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year >= 2025',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year <  2030',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year <= 2025',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year == 2024',  'currentDays' => []],
            ['condition' => 'year <  2025',  'currentDays' => []],
            ['condition' => 'year >  2025',  'currentDays' => []],
            ['condition' => 'year <= 2024',  'currentDays' => []],
            ['condition' => 'year >= 2026',  'currentDays' => []],
            ['condition' => 'year != 2025',  'currentDays' => []],

            // Invalids kept here to ensure month parser paths are exercised in snapshot mode
            ['condition' => 'month in [jun..foobar]', 'failureMsg' => 'Invalid month name(s) in condition: foobar'],
            ['condition' => 'month in [jan,feb,mar..fuz]', 'failureMsg' => 'Invalid month name(s) in condition: fuz'],
            ['condition' => 'month in [13]', 'failureMsg' => 'Invalid month name(s) in condition: 13'],
            ['condition' => 'month in [nov..]', 'failureMsg' => 'Eval failed: syntax error, incomplete range in condition.'],
            ['condition' => 'month == foo',             'failureMsg' => 'Invalid month name(s) in condition: foo'],
            ['condition' => 'month in [dec, bad]',      'failureMsg' => 'Invalid month name(s) in condition: bad'],
            ['condition' => 'month >= 13',              'failureMsg' => 'Invalid month name(s) in condition: 13'],
            ['condition' => 'month in [0, 1]',          'failureMsg' => 'Invalid month name(s) in condition: 0'],
        ]
    ],

    // === Ordinal/Last Weekday tests ===

    [
        'date' => '2025-02-14', // Monday, week of 2025-02-10..16
        'name' => 'Week of February 14, 2025',
        'tests' => [
            ['condition' => '2nd monday',   'currentDays' => ['mon']],
            ['condition' => '2nd mon',      'currentDays' => ['mon']],
            ['condition' => 'last friday',  'currentDays' => []],      // last Friday (Feb 28) is not in this week
            ['condition' => 'last fri',     'currentDays' => []],      // last Friday (Feb 28) is not in this week
            ['condition' => '5th monday',   'currentDays' => []],      // Feb 2025 has only 4 Mondays
            ['condition' => '5th mon',      'currentDays' => []],      // Feb 2025 has only 4 Mondays
        ]
    ],

    [
        'date' => '2025-02-28', // Monday, week of 2025-02-24..03-02
        'name' => 'Week of February 28, 2025',
        'tests' => [
            ['condition' => 'last friday',  'currentDays' => ['fri']],
            ['condition' => 'last fri',     'currentDays' => ['fri']],
            ['condition' => 'last monday',  'currentDays' => ['mon']],
            ['condition' => 'last mon',     'currentDays' => ['mon']],
         ]
    ],

    [
        /* March 2025 has five Mondays */
        'date' => '2025-03-31', // Monday, week of 2025-03-31..04-06
        'name' => 'Week of March 31, 2025',
        'tests' => [
            ['condition' => '5th monday',   'currentDays' => ['mon']],
            ['condition' => '5th mon',      'currentDays' => ['mon']],
            ['condition' => 'last monday',  'currentDays' => ['mon']],
            ['condition' => 'last mon',     'currentDays' => ['mon']],
            ['condition' => '5th friday',   'currentDays' => []],      // March 2025 has only 4 Fridays
            ['condition' => '5th fri',      'currentDays' => []],
        ]
    ],

    [
        /* November 2025 has five Saturdays */
        'date' => '2025-11-29', // Monday, week of 2025-11-24..11-30
        'name' => 'Week of November 29, 2025',
        'tests' => [
            ['condition' => 'last saturday',    'currentDays' => ['sat']],
            ['condition' => 'last sat',         'currentDays' => ['sat']],
            ['condition' => '5th saturday',     'currentDays' => ['sat']],
            ['condition' => '5th sat',          'currentDays' => ['sat']],
        ]
    ],

    [
        /* January 2026 has five Fridays */
        'date' => '2026-01-30', // Monday, week of 2026-01-26..02-01
        'name' => 'Week of January 30, 2026',
        'tests' => [
            ['condition' => '5th friday',   'currentDays' => ['fri']],
            ['condition' => '5th fri',      'currentDays' => ['fri']],
            ['condition' => 'last friday',  'currentDays' => ['fri']],
            ['condition' => 'last fri',     'currentDays' => ['fri']],
        ]
    ],

    [
        'date' => '2025-07-10', // Monday, week of 2025-07-07..07-13
        'name' => 'Week of July 10, 2025',
        'tests' => [
            ['condition' => '2nd thursday', 'currentDays' => ['thu']],
            ['condition' => '2nd thu',      'currentDays' => ['thu']],
        ]
    ],

    [
        'date' => '2025-07-31', // Monday, week of 2025-07-28..08-03
        'name' => 'Week of July 31, 2025',
        'tests' => [
            ['condition' => 'last thursday',    'currentDays' => ['thu']],
            ['condition' => 'last thu',         'currentDays' => ['thu']],
            ['condition' => '5th thursday',     'currentDays' => ['thu']],
            ['condition' => '5th thu',          'currentDays' => ['thu']],
        ]
    ],

    [
        'date' => '2025-12-15', // Monday, week of 2025-12-15..12-21
        'name' => 'Week of December 15, 2025',
        'tests' => [
            ['condition' => '3rd monday',   'currentDays' => ['mon']],
            ['condition' => '3rd mon',      'currentDays' => ['mon']],
        ]
    ],

    [
        'date' => '2025-03-31', // Monday, week of 2025-03-31..04-06
        'name' => 'Week of March 31, 2025',
        'tests' => [
            ['condition' => '5th monday',   'currentDays' => ['mon']],
            ['condition' => '5th mon',      'currentDays' => ['mon']],
            ['condition' => 'last monday',  'currentDays' => ['mon']],
            ['condition' => 'last mon',     'currentDays' => ['mon']],
            ['condition' => '5th friday',   'currentDays' => []],    // March 2025 has only 4 Fridays
            ['condition' => '5th fri',      'currentDays' => []],
        ]
    ],

    [
        'date' => '2025-07-15', // Tuesday
        'name' => 'July 2025 (A)',
        'tests' => [
            ['condition' => 'month in [jun,jul,aug]',  'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => '   month  in  [  jun .. aug  ]  ', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']], // spacing
            ['condition' => 'month == july',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == 7',              'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            ['condition' => 'month < jun',             'currentDays' => []],
            ['condition' => 'month > aug',             'currentDays' => []],
            ['condition' => 'month <= 7',              'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month >= 7',              'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month < 7',               'currentDays' => []],
            ['condition' => 'month > 7',               'currentDays' => []],

            ['condition' => 'month in [may..jul]',     'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [aug..sep]',     'currentDays' => []],

            // July 15, 2025 is a weekday (Tue)
            ['condition' => 'month in [jul,aug] AND is weekday', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jul,aug] AND weekend',    'currentDays' => []],

            ['condition' => 'year == 2025 AND (month > 6 AND month < 9)', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year == 2025',    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
        ]
    ],

    [
        'date' => '2025-11-15', // Saturday
        'name' => 'November 2025',
        'tests' => [
            ['condition' => 'month in [nov..feb]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [11..2]',    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [dec..jan]', 'currentDays' => []],
            ['condition' => 'month == nov',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == 11',         'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            ['condition' => 'month <= nov',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month >= nov',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <  nov',        'currentDays' => []],
            ['condition' => 'month >  nov',        'currentDays' => []],

            ['condition' => 'month in [sep..oct]', 'currentDays' => []],
            ['condition' => 'month in [oct..nov]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [nov..nov]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Nov 15, 2025 is a weekend (Sat)
            ['condition' => 'month == nov AND weekend',   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == nov AND is weekday','currentDays' => []],

            ['condition' => 'year == 2025',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
        ]
    ],

    [
        'date' => '2026-01-15', // Thursday
        'name' => 'January 2026 (A)',
        'tests' => [
            ['condition' => 'month in [nov..feb]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [11..2]',    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [dec..jan]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == jan',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == 1',          'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Wrap-around that effectively spans all months
            ['condition' => 'month in [2..1]',     'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Jan 15, 2026 is a weekday (Thu)
            ['condition' => 'month == jan AND is weekday', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            ['condition' => 'month in [feb..mar]', 'currentDays' => []],

            // Year checks for 2026
            ['condition' => 'year == 2026', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year <  2026', 'currentDays' => []],
            ['condition' => 'year <= 2026', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'year >  2025', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
        ]
    ],

    [
        'date' => '2026-01-15', // Thursday
        'name' => 'January 2026 (B)',
        'tests' => [
            ['condition' => 'month in [nov..feb]',                   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [11..2]',                      'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [dec..jan]',                   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == jan',                          'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jan,mar,may]',                'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => '   month  in [  jan .. jan ]',          'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']], // spacing
            ['condition' => 'month in [mar..may]',                   'currentDays' => []],
            ['condition' => 'month in [jun..aug, 12]',               'currentDays' => []],
            ['condition' => 'month in [jan..feb, may, jul..aug]',    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month != jan',                          'currentDays' => []],
        ]
    ],

    [
        'date' => '2025-03-15', // Saturday
        'name' => 'March 2025',
        'tests' => [
            ['condition' => 'month in [mar..may]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [3..5]',     'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == mar',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <= mar',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month >= mar',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <  mar',        'currentDays' => []],
            ['condition' => 'month >  mar',        'currentDays' => []],
            ['condition' => 'month in [feb..apr]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            // Wrap-around that excludes Mar (Apr..Feb)
            ['condition' => 'month in [apr..feb]', 'currentDays' => []],

            // Mar 15, 2025 is weekend (Sat)
            ['condition' => 'month == mar AND weekend',   'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == mar AND is weekday','currentDays' => []],

            ['condition' => 'month in [nov..feb]', 'currentDays' => []],
            ['condition' => 'month in [dec..jan]', 'currentDays' => []],
            ['condition' => 'year == 2025',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
        ]
    ],

    [
        'date' => '2025-07-15', // Tuesday
        'name' => 'July 2025 (B)',
        'tests' => [
            ['condition' => 'month in [jun..aug]',                'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [6..8]',                    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jun..aug, 12]',            'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jan..feb, may, jul..aug]', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [nov..feb]',                'currentDays' => []],
            ['condition' => 'month in [dec..jan]',                'currentDays' => []],
            ['condition' => 'month in [jul..jul]',                'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            // Wrap-around that excludes July (Aug..Jun)
            ['condition' => 'month in [aug..jun]',                'currentDays' => []],

            // July 15, 2025 is weekday (Tue)
            ['condition' => 'month == jul AND is weekday',        'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            // Numeric with spacing
            ['condition' => '   month in [ 6 .. 8 ]',             'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],

            ['condition' => 'year != 2024',                       'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
        ]
    ],

    // lightweight snapshots to exercise more edges

    [
        'date' => '2025-02-14', // Friday
        'name' => 'February 2025',
        'tests' => [
            ['condition' => 'month == feb',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jan..mar]',    'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            // Wrap-around that excludes Feb (Mar..Jan)
            ['condition' => 'month in [mar..jan]',    'currentDays' => []],
            ['condition' => 'month <= feb',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month >= feb',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month <  feb',           'currentDays' => ['mon','tue','wed','thu','fri','sat','sun'] ? [] : []], // explicit false
            ['condition' => 'month >  feb',           'currentDays' => []],
            // Feb 14, 2025 is a weekday (Fri)
            ['condition' => 'month == feb AND is weekday', 'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == feb AND weekend',    'currentDays' => []],
        ]
    ],

    [
        'date' => '2024-02-29', // Thursday (leap day)
        'name' => 'Leap Day 2024',
        'tests' => [
            ['condition' => 'year == 2024',              'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == feb',              'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [jan..mar]',       'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month in [dec..jan]',       'currentDays' => []],
            ['condition' => 'month in [nov..feb]',       'currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            // Feb 29, 2024 is a weekday (Thu)
            ['condition' => 'month == feb AND is weekday','currentDays' => ['mon','tue','wed','thu','fri','sat','sun']],
            ['condition' => 'month == feb AND weekend',   'currentDays' => []],
        ]
    ],
];
