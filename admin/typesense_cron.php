<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

chdir(__DIR__);
$loaderPrefix = 'typesense_cron';
$_SERVER['REMOTE_ADDR'] = 'cron';
$_SERVER['REQUEST_URI'] = 'cron';
$result = require('includes/application_top.php');
$_SERVER['HTTP_USER_AGENT'] = 'Zen Cart update';

use Zencart\Plugins\Catalog\InstantSearch\InstantSearchLogger;
use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

$logger = new InstantSearchLogger('typesense-cron');

if (!TypesenseZencart::isTypesenseEnabledAndConfigured()) {
    $logger->writeErrorLog("Typesense is not enabled or configured, exiting");
    exit();
}

try {
    $typesense = new TypesenseZencart();
} catch (Exception $e) {
    $logger->writeErrorLog('TypesenseInstantSearch init error, exiting', $e);
    exit();
}

try {
    $typesense->runSync();
} catch (Exception|\Http\Client\Exception $e) {
    $logger->writeErrorLog("Error while syncing the collections, exiting", $e);
    exit();
}

if (!function_exists('zen_count_products_in_category')) { // for ZC v1.5.7
    $a = 1;
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
