<?php

namespace Tests\Support\DatabaseFixtures;

class TypesenseSyncStatusFixture extends DatabaseFixture implements FixtureContract
{
    public function createTable()
    {
        $sql = "
            DROP TABLE IF EXISTS typesense_sync_status;
            CREATE TABLE typesense_sync_status (
                id int NOT NULL DEFAULT '1',
                status varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
                is_next_run_full tinyint(1) NOT NULL DEFAULT '0',
                start_time datetime DEFAULT NULL,
                last_full_sync_end_time datetime DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=MyISAM;
        ";

        $this->connection->query($sql);
    }

    public function seeder()
    {
        $sql = "
            INSERT INTO typesense_sync_status(id, status)
            VALUES (1, 'completed');
        ";

        $this->connection->query($sql);
    }
}
