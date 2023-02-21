<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

if (!defined('USE_PCONNECT')) {
    define('USE_PCONNECT', 'false');
}
$autoLoadConfig[0][]  = [
    'autoType' => 'require',
    'loadFile' => DIR_FS_CATALOG . DIR_WS_INCLUDES . 'version.php'
];
$autoLoadConfig[0][]  = [
    'autoType' => 'class',
    'loadFile'  => 'class.notifier.php'
];
$autoLoadConfig[0][]  = [
    'autoType'   => 'classInstantiate',
    'className'  => 'notifier',
    'objectName' => 'zco_notifier'
];
$autoLoadConfig[0][]  = [
    'autoType' => 'class',
    'loadFile' => 'sniffer.php'
];
$autoLoadConfig[0][]  = [
    'autoType' => 'class',
    'loadFile' => 'object_info.php',
    'classPath' => DIR_WS_CLASSES
];
$autoLoadConfig[20][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_db_config_read.php'
];
$autoLoadConfig[30][] = [
    'autoType' => 'classInstantiate',
    'className' => 'sniffer',
    'objectName' => 'sniffer'
];
$autoLoadConfig[40][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_general_funcs.php'
];
$autoLoadConfig[60][] = [
    'autoType' => 'require',
    'loadFile' => DIR_FS_CATALOG . DIR_WS_FUNCTIONS . 'sessions.php'
];
$autoLoadConfig[70][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_languages.php'
];
$autoLoadConfig[80][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_templates.php'
];
$autoLoadConfig[90][] = [
    'autoType' => 'require',
    'loadFile' => DIR_FS_CATALOG . DIR_WS_FUNCTIONS . 'functions_exchange_rates.php'
];
$autoLoadConfig[120][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_special_funcs.php'
];
$autoLoadConfig[170][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_admin_history.php'
];
$autoLoadConfig[180][] = [
    'autoType' => 'require',
    'loadFile' => DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php'
];
$autoLoadConfig[1][] = [
    'autoType' => 'class',
    'loadFile' => 'class.admin.zcObserverLogEventListener.php',
    'classPath' => DIR_WS_CLASSES
];
$autoLoadConfig[40][] = [
    'autoType' => 'classInstantiate',
    'className' => 'zcObserverLogEventListener',
    'objectName' => 'zcObserverLogEventListener'
];
$autoLoadConfig[180][] = [
    'autoType' => 'classInstantiate',
    'className' => 'currencies',
    'objectName' => 'currencies'
];


if (!function_exists('zen_count_products_in_category')) { // for ZC v1.5.7
    /**
     * Return the number of products in a category
     * @param int $category_id
     * @param bool $include_inactive
     * @return int|mixed
     */
    function zen_count_products_in_category($category_id, $include_inactive = false)
    {
        global $db;
        $products_count = 0;

        $sql = "SELECT count(*) as total
                FROM " . TABLE_PRODUCTS . " p
                LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c USING (products_id)
                WHERE p2c.categories_id = " . (int)$category_id;

        if (!$include_inactive) {
            $sql .= " AND p.products_status = 1";

        }
        $products = $db->Execute($sql);
        $products_count += $products->fields['total'];

        $sql = "SELECT categories_id
                FROM " . TABLE_CATEGORIES . "
                WHERE parent_id = " . (int)$category_id;

        $child_categories = $db->Execute($sql);

        foreach ($child_categories as $result) {
            $products_count += zen_count_products_in_category($result['categories_id'], $include_inactive);
        }

        return $products_count;
    }
}

