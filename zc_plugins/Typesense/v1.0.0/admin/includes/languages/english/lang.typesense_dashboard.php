<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

$define = [
    'HEADING_TITLE' => 'Typesense Dashboard',
    'TEXT_TYPESENSE_NOT_ENABLED_OR_CONFIGURED' => 'Typesense is not enabled or configured in the plugin settings.',
    'TYPESENSE_DASHBOARD_TITLE' => 'Typesense Dashboard',
    'TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1' => 'Error while retrieving data',
    'TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2' => 'Check the Typesense configuration and the error logs',
    'TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_TITLE' => 'Server Status',
    'TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_ERROR' => 'Health not OK',
    'TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_OK' => 'Health OK',
    'TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_MEMORY_USAGE' => 'Memory usage',
    'TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_DISK_USAGE' => 'Disk usage',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_TITLE' => 'Sync Status',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FIRST_SYNC_TEXT' => 'Waiting for first Full-Sync...',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1' => 'Sync is <strong>running</strong>...',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1_SUFFIX' => ' (started %s seconds ago)',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_OVER_TIMEMOUT_TEXT_2' => 'It\'s taking too long (more than the sync timeout). Check the sync log.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_TEXT_1' => 'Last Sync <strong>completed</strong> succesfully.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_OVER_FULL_FREQUENCY_2' => 'More than the full-sync frequency hours have passed since the last Full-Sync. Check your crontab and the sync log.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_1' => 'Last Sync <strong>failed</strong>. Check the sync log.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_RETRY_SYNC' => 'Sync will be retried with the next run.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_NO_RETRY_SYNC' => 'Sync after failure is disabled. Manually run a Full-Sync.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_NEXT_RUN_FULL_TEXT' => 'Next sync has been manually scheduled as Full-Sync.',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_INCREMENTAL_SYNC_TEXT' => 'Last Incremental-Sync run:',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_FULL_SYNC_TEXT' => 'Last Full-Sync run:',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_GRACEFUL_SYNC_BUTTON_TEXT' => 'Graceful Full-Sync',
    'TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FORCE_SYNC_BUTTON_TEXT' => 'Forced Full-Sync',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_TITLE' => 'Collections',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_COLLECTION' => 'Collection',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_NO_DOCUMENTS' => '# Documents',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_1' => 'Some collections are missing',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_2' => 'Run a full-sync',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_1' => 'No collections found',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_2' => 'Run a full-sync',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_1' => 'The products collection is empty',
    'TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_2' => 'Run a full-sync',
];

return $define;
