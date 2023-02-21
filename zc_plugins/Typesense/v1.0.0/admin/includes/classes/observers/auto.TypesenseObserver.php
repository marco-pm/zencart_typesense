<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

class zcObserverTypesenseObserver extends base
{
    public function __construct()
    {
        $this->attach(
            $this,
            [
                'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT',
                'NOTIFY_ADMIN_CATEGORIES_UPDATE_OR_INSERT_FINISH',
                'NOTIFIER_ADMIN_ZEN_REMOVE_CATEGORY',
                'NOTIFY_ADMIN_LANGUAGE_INSERT',
                'NOTIFY_ADMIN_LANGUAGE_DELETE'
            ]
        );
    }

    public function update(&$class, $eventID, $p1, &$p2): void
    {
        switch ($eventID) {
            // When a product is deleted, set it to be deleted from the Typesense collection in the next sync run
            case 'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT':
                $productId = (int)$p2;
                TypesenseZenCart::addProductsIdToBeDeleted($productId);
                break;

            // Trigger a Full-Sync when a category is created, updated or deleted
            case 'NOTIFY_ADMIN_CATEGORIES_UPDATE_OR_INSERT_FINISH':
            case 'NOTIFIER_ADMIN_ZEN_REMOVE_CATEGORY':
                if (TYPESENSE_FULL_SYNC_AFTER_CATEGORY_CHANGE === 'true') {
                    TypesenseZenCart::setFullSyncGraceful();
                }
                break;

            // Trigger a Full-Sync when a language is created or deleted
            case 'NOTIFY_ADMIN_LANGUAGE_INSERT':
            case 'NOTIFY_ADMIN_LANGUAGE_DELETE':
                TypesenseZenCart::setFullSyncGraceful();
                break;

            // Note: there are other admin events that should trigger a Full-Sync, but as of ZC158 they don't have a
            // notifier:
            // - a category status is changed
            // - a category is moved
            // - a manufacturer is created/updated/deleted
            // - a currency is created/updated/deleted
            // - a language is updated

            default:
                break;
        }
    }
}
