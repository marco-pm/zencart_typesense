<?php

namespace Support\DatabaseFixtures;

use Tests\Support\DatabaseFixtures\DatabaseFixture;
use Tests\Support\DatabaseFixtures\FixtureContract;

class TypesenseSyncStatusFixture extends DatabaseFixture implements FixtureContract
{
    public function createTable()
    {
        $sql = "
            DROP TABLE IF EXISTS typesense_sync_status;
            CREATE TABLE typesense_sync_status (
                id int NOT NULL DEFAULT '1',
                status varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
                start_time datetime DEFAULT NULL,
                end_time datetime DEFAULT NULL,
                is_next_run_full tinyint(1) NOT NULL DEFAULT '0',
                last_full_sync_end_time datetime DEFAULT NULL,
                products_ids_to_delete text,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB;
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
