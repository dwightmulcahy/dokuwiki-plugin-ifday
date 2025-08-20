# ifday-tests (standalone)

Minimal, modular runner that loads your `syntax.php` without DokuWiki and exercises the private
`evaluateCondition()` via reflection (or a dedicated `Ifday_ConditionEvaluator` if you have one).

## Layout
```
ifday-tests/
  run.php           # Standalone modular runner.
  lib/
    bootstrap.php   # Stubs + loads ../syntax.php + exposes $evaluateCondition()
    ui.php          # ANSI colors + width-safe padding
    tests.php       # Small curated test sets
    runner.php      # Truth table, examples, fixed-date snapshots
    output.php      # Shared output helpers (TTY color, padding, legend, quiet-aware logging)
    reflect.php     # Wraps evaluateCondition() to allow running outside of DokuWiki
  tests/
    boolean.php     # Canonical day/logic + parse-error tests
    errors.php      # Extra parse-only tests (day-agnostic) that expect to fail
    rendered.php    # Rendered Content tests (ALL days)
    snapshots.php   # Month/Year snapshot tests keyed by a fixed IFDAY_TEST_DATE
```

## Run
From the plugin root (same dir as `syntax.php`):
```bash
php ifday-tests/run.php --color
# ASCII-safe alignment:
php ifday-tests/run.php --ascii --color
# Ignore php.ini (avoids xdebug warnings):
php -n ifday-tests/run.php --ascii --color
```

Exit code 0 on pass, 1 on any failure. The script always prints a banner and headings.
