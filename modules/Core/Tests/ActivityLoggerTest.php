<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Config\Logger as LoggerConfig;
use Modules\Core\Activity\ActivityLogger;
use Modules\Core\Activity\SecretMasker;
use Modules\Core\Log\SafeLogger;
use Modules\Core\Tasks\PurgeActivityLog;

/**
 * The activity log writer: secrets masked by key pattern at any depth, values stored as data
 * (summary single-line, file log lines escaped), changed values only, module of a CRUD resource,
 * retention by `Core.activityRetentionDays` in chunks.
 *
 * @internal
 */
final class ActivityLoggerTest extends RbacTestCase
{
    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function lastEntry(): array
    {
        $row = db_connect()->table('activity_log')->orderBy('id', 'desc')->get(1)->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    public function testSecretsAreMaskedByKeyAtAnyDepth(): void
    {
        $masked = SecretMasker::mask([
            'name'          => 'Ana',
            'password_hash' => 'x',
            'PWD'           => 'y',
            'api_key'       => 'z',
            'csrf_test'     => 'c',
            'otp'           => '123456',
            'empty_secret'  => '',
            'nested'        => ['token' => 'abc', 'deeper' => ['client_secret' => 's', 'ok' => 1]],
        ]);

        $this->assertSame('Ana', $masked['name']);
        foreach (['password_hash', 'PWD', 'api_key', 'csrf_test', 'otp'] as $key) {
            $this->assertSame(SecretMasker::MASK, $masked[$key], $key);
        }
        $this->assertSame('', $masked['empty_secret'], 'Clearing a secret stays visible.');
        $this->assertSame(SecretMasker::MASK, $masked['nested']['token']);
        $this->assertSame(SecretMasker::MASK, $masked['nested']['deeper']['client_secret']);
        $this->assertSame(1, $masked['nested']['deeper']['ok']);
    }

    public function testEntriesStoreMaskedChangesAndTheRequestContext(): void
    {
        $id = activity_log('Core', 'update', [
            'user_id'   => 7,
            'resource'  => 'core-things',
            'record_id' => 5,
            'summary'   => 'Thing',
            'before'    => ['title' => 'Old', 'password' => 'old-secret'],
            'after'     => ['title' => 'New', 'password' => 'new-secret'],
        ]);

        $this->assertIsInt($id);
        $row = $this->lastEntry();
        $this->assertSame('activity', $row['type']);
        $this->assertSame(7, (int) $row['user_id']);
        $this->assertSame(['Core', 'core-things', 'update', '5', 'Thing'], [$row['module'], $row['resource'], $row['action'], $row['record_id'], $row['summary']]);
        $this->assertStringNotContainsString('secret', (string) $row['changes']);
        $this->assertSame(['before' => ['title' => 'Old', 'password' => SecretMasker::MASK], 'after' => ['title' => 'New', 'password' => SecretMasker::MASK]], json_decode((string) $row['changes'], true));
        $this->assertNotEmpty($row['created_at']);
    }

    public function testSecurityEntriesHaveTheirType(): void
    {
        security_log('Auth', 'login_failed', ['summary' => 'ana@example.com']);

        $this->assertSame('security', $this->lastEntry()['type']);
    }

    public function testUserInputCannotForgeEntriesOrLines(): void
    {
        security_log('Auth', 'login_failed', ['summary' => "ana@example.com\n2026-01-01 security login admin\r\x00ok"]);

        $summary = (string) $this->lastEntry()['summary'];
        $this->assertStringNotContainsString("\n", $summary);
        $this->assertStringNotContainsString("\r", $summary);
        $this->assertStringNotContainsString("\x00", $summary);
        $this->assertSame(1, db_connect()->table('activity_log')->countAllResults(), 'One entry, not two.');

        $this->assertSame('a\\nb\\r\\tc\\x00\\u2028', SafeLogger::escape("a\nb\r\tc\x00" . mb_chr(0x2028)));
    }

    public function testTheFileLogEscapesLineBreaksInValues(): void
    {
        $logger = new class (config(LoggerConfig::class)) extends SafeLogger {
            /**
             * @param array<string, mixed> $context
             */
            public function render(string $message, array $context): string
            {
                return $this->interpolate($message, $context);
            }
        };

        $line = $logger->render('Login failed for {email}.', ['email' => "x\nCRITICAL - forged line"]);

        $this->assertSame('Login failed for x\nCRITICAL - forged line.', $line);
        $this->assertInstanceOf(SafeLogger::class, service('logger'), 'log_message() uses the safe logger.');
    }

    public function testInvalidCodesAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        activity_log('Core', "update\nforged");
    }

    public function testDiffKeepsOnlyChangedValues(): void
    {
        [$before, $after] = ActivityLogger::diff(
            ['title' => 'A', 'count' => 1, 'flag' => '1', 'note' => null],
            ['title' => 'B', 'count' => '1', 'flag' => true, 'note' => ''],
        );

        $this->assertSame(['title' => 'A', 'note' => null], $before);
        $this->assertSame(['title' => 'B', 'note' => ''], $after);
    }

    public function testCrudWritesFindTheirModuleAndSkipEmptyUpdates(): void
    {
        $logger = new ActivityLogger();

        $this->assertSame('Core', $logger->moduleOf('core-notifications'));
        $this->assertNull($logger->crudWrite('core-notifications', 'update', '3', ['title' => 'Same'], ['title' => 'Same']));

        $logger->crudWrite('core-notifications', 'delete', '3', [], ['title' => 'Gone', 'secret' => 's']);
        $row = $this->lastEntry();
        $this->assertSame(['Core', 'core-notifications', 'delete', '3', 'Gone'], [$row['module'], $row['resource'], $row['action'], $row['record_id'], $row['summary']]);
        $this->assertSame(['before' => ['title' => 'Gone', 'secret' => SecretMasker::MASK]], json_decode((string) $row['changes'], true));
    }

    public function testRetentionDeletesOnlyOldEntriesAndLogsThePurge(): void
    {
        Time::setTestNow('2026-11-07 12:00:00');
        $db = db_connect();

        foreach (['2025-01-01 00:00:00', '2025-11-06 11:59:59', '2025-11-08 00:00:00', '2026-11-07 11:00:00'] as $date) {
            $db->table('activity_log')->insert(['type' => 'activity', 'module' => 'Core', 'action' => 'update', 'created_at' => $date]);
        }

        setting('Core.activityRetentionDays', 365);
        $result = (new PurgeActivityLog())->run();

        $this->assertFalse($result->failed());
        $dates = array_column($db->table('activity_log')->where('action', 'update')->orderBy('id')->get()->getResultArray(), 'created_at');
        $this->assertSame(['2025-11-08 00:00:00', '2026-11-07 11:00:00'], $dates);

        $purge = $this->lastEntry();
        $this->assertSame(['purge', '2'], [$purge['action'], $purge['summary']]);
    }

    public function testRetentionWorksInChunks(): void
    {
        $db = db_connect();

        for ($i = 0; $i < PurgeActivityLog::CHUNK + 5; $i++) {
            $db->table('activity_log')->insert(['type' => 'activity', 'module' => 'Core', 'action' => 'update', 'created_at' => '2020-01-01 00:00:00']);
        }

        $this->assertSame(PurgeActivityLog::CHUNK + 5, (new PurgeActivityLog())->purge('2021-01-01 00:00:00'));
        $this->assertSame(0, $db->table('activity_log')->countAllResults());
    }
}
