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
