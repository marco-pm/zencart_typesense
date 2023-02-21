<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    /**
     * Configuration group title.
     *
     * @var string
     */
    protected const CONFIGURATION_GROUP_TITLE = 'Instant Search';

    /**
     * Install the plugin for the first time.
     *
     * @return bool
     */
    public function doInstall(): bool
    {
        // Check that Instant Search plugin is installed and has a version >= 4
        $sql = $this->dbConn->Execute("
            SELECT
                version
            FROM
                " . TABLE_PLUGIN_CONTROL . "
            WHERE
                unique_key = 'InstantSearch'
                AND version NOT REGEXP '^v[2-3]'
            LIMIT
                1
        ");
        if ($sql->RecordCount() === 0) {
            $errorMsg = 'The Typesense add-on requires Instant Search plugin v4 (or later) installed. Please install Instant Search plugin first.';
            $this->errorContainer->addError(1, $errorMsg, true, $errorMsg);
            return false;
        }

        // Check that the plugin vendor folder is present and not empty
        $vendorFolder = __DIR__ . '/../vendor';
        if (!is_dir($vendorFolder) || count(scandir($vendorFolder)) <= 2) {
            $errorMsg = '<code>vendor</code> directory not found. Please download/install the vendor files first, as described in the Typesense plugin readme.';
            $this->errorContainer->addError(1, $errorMsg, true, $errorMsg);
            return false;
        }

        $sql = $this->dbConn->Execute("
            SELECT
                configuration_group_id
            FROM
                " . TABLE_CONFIGURATION_GROUP . "
            WHERE
                configuration_group_title = '" . self::CONFIGURATION_GROUP_TITLE . "'
            LIMIT
                1
        ");
        if ($sql->RecordCount() === 0) {
            $errorMsg = 'Instant Search plugin is not installed properly. Please re-install Instant Search plugin first.';
            $this->errorContainer->addError(1, $errorMsg, true, $errorMsg);
            return false;
        }

        $configurationGroupId = (int)($sql->fields['configuration_group_id']);

        $this->createConfigurationSettings($configurationGroupId);

        $this->createSyncTable();

        // Register admin page
        zen_deregister_admin_pages(['typesenseDashboard']);
        zen_register_admin_page('typesenseDashboard', 'BOX_TOOLS_TYPESENSE_DASHBOARD', 'FILENAME_TYPESENSE_DASHBOARD', '', 'tools', 'Y', 2000);

        return true;
    }

    /**
     * Uninstall the plugin.
     *
     * @return bool
     */
    public function doUninstall(): bool
    {
        $sql = "
            DELETE FROM
                " . TABLE_CONFIGURATION . "
            WHERE
                configuration_key LIKE 'TYPESENSE_%'
                OR configuration_key = 'INSTANT_SEARCH_ENGINE'
        ";
        $this->executeInstallerSql($sql);

        $sql = "DROP TABLE IF EXISTS " . DB_PREFIX . "typesense_sync_status";
        $this->executeInstallerSql($sql);

        zen_deregister_admin_pages(['typesenseDashboard']);

        return true;
    }

    /**
     * Install admin settings.
     *
     * @param int $configurationGroupId
     * @return void
     */
    protected function createConfigurationSettings(int $configurationGroupId): void
    {
        $sql = "
            INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function, val_function)
            VALUES
                ('Search Engine', 'INSTANT_SEARCH_ENGINE', 'MySQL', 'The search engine to use for Instant Search.<br>If you choose Typesense, you must first configure its settings (host, port, protocol and key) below.', $configurationGroupId, now(), 0, NULL, 'zen_cfg_select_option(array(\'MySQL\', \'Typesense\'),', NULL),
                ('[Typesense] Host', 'TYPESENSE_HOST', 'typesense', 'Typesense Host', $configurationGroupId, now(), 5000, NULL, NULL, NULL),
                ('[Typesense] Port', 'TYPESENSE_PORT', '8108', 'Typesense Port', $configurationGroupId, now(), 5100, NULL, NULL, '{\"error\":\"TEXT_INSTANT_SEARCH_CONFIGURATION_INT_VALIDATE\",\"id\":\"FILTER_VALIDATE_INT\",\"options\":{\"options\":{\"min_range\":0}}}'),
                ('[Typesense] Protocol', 'TYPESENSE_PROTOCOL', 'http', 'Typesense Protocol', $configurationGroupId, now(), 5200, NULL, 'zen_cfg_select_option(array(\'http\', \'https\'),', NULL),
                ('[Typesense] Key', 'TYPESENSE_KEY', 'xyz', 'Typesense Key', $configurationGroupId, now(), 5300, NULL, NULL, '{\"error\":\"ERROR\",\"id\":\"FILTER_SANITIZE_URL\",\"options\":{\"options\":{}}}'),
                ('[Typesense] Full-Sync Collections every (hours)', 'TYPESENSE_FULL_SYNC_FREQUENCY_HOURS', '12', 'Every how many hours a new Full-Sync will be performed. Maximum recommended value is 24 hours (run it at least once a day).', $configurationGroupId, now(), 5400, NULL, NULL, '{\"error\":\"TEXT_INSTANT_SEARCH_CONFIGURATION_INT_VALIDATE\",\"id\":\"FILTER_VALIDATE_INT\",\"options\":{\"options\":{\"min_range\":1}}}'),
                ('[Typesense] Full-Sync Collections after a Category Change', 'TYPESENSE_FULL_SYNC_AFTER_CATEGORY_CHANGE', 'true', 'Trigger a Full-Sync when a category is created, updated or deleted.', $configurationGroupId, now(), 5500, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', NULL),
                ('[Typesense] Sync Collections even if the last Sync did not complete successfully', 'TYPESENSE_SYNC_AFTER_FAILED', 'true', 'Sync Collections even if the last Sync did not complete successfully. If set to <em>False</em>, after a failed sync no more syncs will be performed until a new Full-Sync will be manually launched (through the Typesense Dashboard).', $configurationGroupId, now(), 5600, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', NULL),
                ('[Typesense] Sync Timeout (minutes)', 'TYPESENSE_SYNC_TIMEOUT_MINUTES', '30', 'Maximum number of minutes allowed for the sync process to complete. If the sync\'s duration reaches the timeout, the process will be aborted.', $configurationGroupId, now(), 5700, NULL, NULL, '{\"error\":\"TEXT_INSTANT_SEARCH_CONFIGURATION_INT_VALIDATE\",\"id\":\"FILTER_VALIDATE_INT\",\"options\":{\"options\":{\"min_range\":1}}}'),
                ('[Typesense] Enable Sync Log', 'TYPESENSE_ENABLE_SYNC_LOG', 'false', 'Enable the Sync log.', $configurationGroupId, now(), 5800, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),', NULL)
        ";
        $this->executeInstallerSql($sql);
    }

    /**
     * Create the sync table.
     *
     * @return void
     */
    protected function createSyncTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "typesense_sync_status (
                id int NOT NULL DEFAULT '1',
                status varchar(20) NOT NULL DEFAULT 'completed',
                is_next_run_full tinyint(1) NOT NULL DEFAULT '0',
                start_time datetime DEFAULT NULL,
                end_time datetime DEFAULT NULL,
                last_incremental_sync_start_time datetime DEFAULT NULL,
                last_incremental_sync_end_time datetime DEFAULT NULL,
                last_full_sync_start_time datetime DEFAULT NULL,
                last_full_sync_end_time datetime DEFAULT NULL,
                products_ids_to_delete text,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB
        ";
        $this->executeInstallerSql($sql);

        $sql = "
            INSERT INTO " . DB_PREFIX . "typesense_sync_status
                (id, status, is_next_run_full)
            VALUES
                (1, 'completed', 0)
            ON DUPLICATE KEY UPDATE
                id = 1
        ";
        $this->executeInstallerSql($sql);
    }
}
