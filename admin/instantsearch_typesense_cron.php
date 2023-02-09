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
$loaderPrefix = 'instantsearch_typesense_cron';
$_SERVER['REMOTE_ADDR'] = 'cron';
$_SERVER['REQUEST_URI'] = 'cron';
$result = require('includes/application_top.php');
$_SERVER['HTTP_USER_AGENT'] = 'Zen Cart update';

use Zencart\Plugins\Catalog\InstantSearch\InstantSearchLogger;
use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

$logger = new InstantSearchLogger('typesense-cron');

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

/*$client = $typesense->getClient();

try {
    $suca = $client->collections->retrieve();
} catch (Exception|\Http\Client\Exception $e) {
    $logger->writeErrorLog("Error while retrieving collections, exiting", $e);
    exit();
}*/
