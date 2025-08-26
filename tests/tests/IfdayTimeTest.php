<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('DOKU_INC')) define('DOKU_INC', __DIR__ . '/../');
require_once DOKU_INC . '../inc/IfdayUtils.php';          // adjust if needed
require_once DOKU_INC . '../inc/ConditionEvaluator.php';   // adjust if needed

final class IfdayTimeTest extends TestCase
{
    /** @var string|null */
    private $origPhpTz;
    /** @var array */
    private $conf;

    protected function setUp(): void
    {
        // snapshot PHP default tz
        $this->origPhpTz = date_default_timezone_get();
        date_default_timezone_set('UTC'); // make the PHP default deterministic for tests

        // minimal $conf baseline
        global $conf;
        $this->conf = $conf ?? [];
        $this->conf['timezone'] = ''; // default to empty unless a test sets it

        // clear env override by default
        putenv('IFDAY_TEST_DATE');
    }

    protected function tearDown(): void
    {
        // restore PHP tz
        if ($this->origPhpTz) {
            date_default_timezone_set($this->origPhpTz);
        }
        // clear env
        putenv('IFDAY_TEST_DATE');
    }

    /** Helper: build a fixed evaluator context the way your handler would */
    private function ctxFromNow(DateTime $now): array
    {
        return [
            'year'  => (int)$now->format('Y'),
            'month' => (int)$now->format('n'),
            'day'   => (int)$now->format('j'),
            'wday'  => (int)$now->format('N'), // 1=Mon..7=Sun
            'iso'   => $now->format('Y-m-d'),
            'tz'    => $now->getTimezone()->getName(),
        ];
    }

    public function test_uses_wiki_timezone_when_set(): void
    {
        global $conf;
        $conf = $this->conf;
        $conf['timezone'] = 'America/Chicago';

        // No env override
        $now = Ifday_Utils::now($conf);
        $this->assertSame('America/Chicago', $now->getTimezone()->getName());

        // And date math behaves as expected
        $ctx = $this->ctxFromNow($now);
        $this->assertSame($conf['timezone'], $ctx['tz']);
    }

    public function test_falls_back_to_php_default_when_wiki_timezone_empty(): void
    {
        global $conf;
        $conf = $this->conf;          // timezone = ''
        date_default_timezone_set('Europe/Paris'); // simulate server default

        $now = Ifday_Utils::now($conf);
        $this->assertSame('Europe/Paris', $now->getTimezone()->getName());
    }

    public function test_invalid_wiki_timezone_falls_back_to_php_default(): void
    {
        global $conf;
        $conf = $this->conf;
        $conf['timezone'] = 'Not/AZone';
        date_default_timezone_set('UTC');

        // IfdayUtils::now should internally catch and fall back
        $now = Ifday_Utils::now($conf);
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    public function test_env_override_sets_anchor_and_now_in_wiki_tz(): void
    {
        global $conf;
        $conf = $this->conf;
        $conf['timezone'] = 'America/Chicago';

        // 2025-07-15 10:05:00 assumed local to resolved tz
        putenv('IFDAY_TEST_DATE=2025-07-15 10:05:00');

        // This mirrors your snippet after the fix:
        $tzName = !empty($conf['timezone']) ? $conf['timezone'] : date_default_timezone_get();
        $tz = new DateTimeZone($tzName);

        $envStr = getenv('IFDAY_TEST_DATE') ?: null;
        $nowDay = $envStr ? new DateTime($envStr, $tz) : new DateTime('now', $tz);
        $nowAnchor = $envStr ? new DateTime($envStr, $tz) : $nowDay;

        $this->assertSame('America/Chicago', $nowDay->getTimezone()->getName());
        $this->assertSame($nowDay->format('c'), $nowAnchor->format('c'));

        // Quick sanity: July 15, 2025 is a Tuesday -> N=2
        $this->assertSame(2, (int)$nowDay->format('N'));
    }

    public function test_condition_evaluation_uses_ctx_clock(): void
    {
        // Example: verify a simple condition at a known date
        $tz = new DateTimeZone('UTC');                                 // or your resolved tz
        $fixed = new DateTime('2025-03-01 12:00:00', $tz);             // Saturday

        $eval = new Ifday_ConditionEvaluator();
        [$ok, $_out] = $eval->evaluateCondition('weekend', $fixed);
        $this->assertTrue($ok);
        $this->assertTrue($_out);

        [$ok1, $_out1] = $eval->evaluateCondition('saturday', $fixed);
        $this->assertTrue($ok1);
        $this->assertTrue($_out1);

        [$ok2, $_out2] = $eval->evaluateCondition('day == monday', $fixed);
        $this->assertTrue($ok2);
        $this->assertFalse($_out2);
    }
}
