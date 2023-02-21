<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

namespace Tests\Typesense\Integration;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\Traits\DatabaseConcerns;
use Tests\Support\zcUnitTestCase;

class TypesenseIntegrationTest extends zcUnitTestCase
{
    use DatabaseConcerns;

    protected MockObject $typesenseZencartMock;

    public array $databaseFixtures = [
        'typesenseSyncStatus' => ['typesense_sync_status'],
    ];

    // Addition to the DatabaseConcerns setUp()
    public function typesenseSetup(): void
    {
        parent::setUp();

        $classLoader = new ClassLoader();
        $classLoader->addPsr4("Zencart\\Plugins\\Catalog\\Typesense\\", "zc_plugins/Typesense/v1.0.0/classes/", true);
        $classLoader->register();

        define('TABLE_TYPESENSE_SYNC', 'typesense_sync_status');

        define('TYPESENSE_FULL_SYNC_FREQUENCY_HOURS', '12');
        define('TYPESENSE_FULL_SYNC_AFTER_CATEGORY_CHANGE', 'true');
        define('TYPESENSE_SYNC_TIMEOUT_MINUTES', '30');
        define('TYPESENSE_ENABLE_SYNC_LOG', 'false');

        $this->typesenseZencartMock = $this->getMockBuilder('Zencart\Plugins\Catalog\Typesense\TypesenseZencart')
                                           ->disableOriginalConstructor()
                                           ->onlyMethods(['syncFull', 'syncIncremental'])
                                           ->getMock();
    }

    public function testFullSyncAfterPluginInstall(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'false');

        $this->typesenseSetup();

        $this->setSyncStatus('completed', 0, 'NULL', 'NULL');

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testSyncAfterFailIsDisabled(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'false');

        $this->typesenseSetup();

        $this->setSyncStatus('failed', 0, 'NULL', 'NULL');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testSyncAfterFailIsEnabled(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus('failed', 0, 'NULL', 'NULL');

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testLastSyncIsStillRunningWithinTimeout(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus('running', 0, 'DATE_SUB(NOW(), INTERVAL 25 MINUTE)', 'NULL');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testLastSyncHasTimedOutSetFailedAndRunSync(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus('running', 0, 'DATE_SUB(NOW(), INTERVAL 35 MINUTE)', 'NULL');

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();

        $syncStatus = $this->getSyncStatus();

        $this->assertEquals('failed', $syncStatus->fields['status']);
    }

    public function testLastSyncHasTimedOutSetFailedAndDoNothing(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'false');

        $this->typesenseSetup();

        $this->setSyncStatus('running', 0, 'DATE_SUB(NOW(), INTERVAL 35 MINUTE)', 'NULL');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();

        $syncStatus = $this->getSyncStatus();

        $this->assertEquals('failed', $syncStatus->fields['status']);
    }

    public function testFullSyncIfNextRunMarkesAsFull(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus(
            'completed',
            1,
            'DATE_SUB(NOW(), INTERVAL 60 MINUTE)',
            'DATE_SUB(NOW(), INTERVAL 120 MINUTE)'
        );

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testFullSyncIfLastOneEndedMoreThanFrequency(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus(
            'completed',
            0,
            'DATE_SUB(NOW(), INTERVAL 60 MINUTE)',
            'DATE_SUB(NOW(), INTERVAL 13 HOUR)'
        );

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testIncrementalSyncIfNextRunNotMarkesAsFull(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus(
            'completed',
            0,
            'DATE_SUB(NOW(), INTERVAL 60 MINUTE)',
            'DATE_SUB(NOW(), INTERVAL 120 MINUTE)'
        );

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    public function testIncrementalSyncIfLastFullEndedWithinFrequency(): void
    {
        define('TYPESENSE_SYNC_AFTER_FAILED', 'true');

        $this->typesenseSetup();

        $this->setSyncStatus(
            'completed',
            0,
            'DATE_SUB(NOW(), INTERVAL 60 MINUTE)',
            'DATE_SUB(NOW(), INTERVAL 11 HOUR)'
        );

        $this->typesenseZencartMock->expects($this->never())
                                   ->method('syncFull');

        $this->typesenseZencartMock->expects($this->once())
                                   ->method('syncIncremental');

        $this->typesenseZencartMock->runSync();
    }

    private function setSyncStatus(
        string $status,
        int $isNextRunFull,
        string|null $startTime,
        string|null $lastFullSyncEndTime
    ): void {
        $sql = "
            UPDATE
                typesense_sync_status
            SET
                status = '" . $status . "',
                start_time = " . $startTime . ",
                is_next_run_full = " . $isNextRunFull . ",
                last_full_sync_end_time = " . $lastFullSyncEndTime . "
            WHERE
                id = 1
        ";
        $this->db->Execute($sql);
    }

    private function getSyncStatus(): \queryFactoryResult
    {
        $sql = "
            SELECT
                status,
                start_time,
                is_next_run_full,
                last_full_sync_end_time
            FROM
                typesense_sync_status
            WHERE
                id = 1
        ";
        return $this->db->Execute($sql);
    }

}
