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
        // TODO: check that Instant Search is installed and has a version >= 4.0.0

        $sql = $this->dbConn->Execute("
            SELECT configuration_group_id
            FROM " . TABLE_CONFIGURATION_GROUP . "
            WHERE configuration_group_title = '" . self::CONFIGURATION_GROUP_TITLE . "'
            LIMIT 1
        ");
        if ($sql->RecordCount() === 0) {
            // todo print error message
            return false;
        }

        $configurationGroupId = (int)($sql->fields['configuration_group_id']);

        $this->createConfigurationSettings($configurationGroupId);

        return true;
    }

    /**
     * Uninstall the plugin.
     *
     * @return bool
     */
    public function doUninstall(): bool
    {
        // TODO

        $sql = "DELETE FROM " . TABLE_CONFIGURATION . "
                WHERE configuration_key LIKE 'INSTANT_SEARCH_TYPESENSE_%'
                OR configuration_key = 'INSTANT_SEARCH_ENGINE'";
        $this->executeInstallerSql($sql);

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
                ('Search Engine', 'INSTANT_SEARCH_ENGINE', 'MySQL', 'TODO', $configurationGroupId, now(), 0, NULL, 'zen_cfg_select_option(array(\'MySQL\', \'Typesense\'),', NULL),
                ('[Typesense] Host', 'INSTANT_SEARCH_TYPESENSE_HOST', 'typesense', 'Typesense Host', $configurationGroupId, now(), 5000, NULL, NULL, NULL),
                ('[Typesense] Port', 'INSTANT_SEARCH_TYPESENSE_PORT', '8108', 'Typesense Port', $configurationGroupId, now(), 5100, NULL, NULL, '{\"error\":\"TEXT_INSTANT_SEARCH_CONFIGURATION_INT_VALIDATE\",\"id\":\"FILTER_VALIDATE_INT\",\"options\":{\"options\":{\"min_range\":0}}}'),
                ('[Typesense] Protocol', 'INSTANT_SEARCH_TYPESENSE_PROTOCOL', 'http', 'Typesense Protocol', $configurationGroupId, now(), 5200, NULL, 'zen_cfg_select_option(array(\'http\', \'https\'),', NULL),
                ('[Typesense] Key', 'INSTANT_SEARCH_TYPESENSE_KEY', 'Ffuz6GfmAkDsNzKexGRf2tni4wivhTMq', 'Typesense Key', $configurationGroupId, now(), 5300, NULL, NULL, '{\"error\":\"ERROR\",\"id\":\"FILTER_SANITIZE_URL\",\"options\":{\"options\":{}}}')
        ";
        $this->executeInstallerSql($sql);
    }
}
