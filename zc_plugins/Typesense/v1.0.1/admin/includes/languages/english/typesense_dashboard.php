<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.1
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

define('HEADING_TITLE', 'Typesense Dashboard');
define('TEXT_TYPESENSE_NOT_ENABLED_OR_CONFIGURED', 'Typesense is not enabled or configured in the plugin settings.');
define('TYPESENSE_DASHBOARD_TITLE', 'Typesense Dashboard');
define('TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1', 'Error while retrieving data');
define('TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2', 'Check the Typesense configuration and the error logs');
define('TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_TITLE', 'Server Status');
define('TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_ERROR', 'Health not OK');
define('TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_OK', 'Health OK');
define('TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_MEMORY_USAGE', 'Memory usage');
define('TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_DISK_USAGE', 'Disk usage');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_TITLE', 'Sync Status');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FIRST_SYNC_TEXT', 'Waiting for first Full-Sync...');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1', 'Sync is <strong>running</strong>...');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1_SUFFIX', ' (started %s seconds ago)');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_OVER_TIMEMOUT_TEXT_2', 'It\'s taking too long (more than the sync timeout). Check the sync log.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_TEXT_1', 'Last Sync <strong>completed</strong> succesfully.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_OVER_FULL_FREQUENCY_2', 'More than the Full-Sync frequency hours have passed since the last Full-Sync. Check your crontab and the sync log.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_1', 'Last Sync <strong>failed</strong>. Check the sync log.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_RETRY_SYNC', 'Sync will be retried with the next run.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_NO_RETRY_SYNC', 'Sync after failure is disabled. Manually run a Full-Sync.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_NEXT_RUN_FULL_TEXT', 'Next sync has been scheduled as Full-Sync.');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_INCREMENTAL_SYNC_TEXT', 'Last Incremental-Sync run:');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_FULL_SYNC_TEXT', 'Last Full-Sync run:');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_GRACEFUL_SYNC_BUTTON_TEXT', 'Graceful Full-Sync');
define('TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FORCE_SYNC_BUTTON_TEXT', 'Forced Full-Sync');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_TITLE', 'Collections');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_COLLECTION', 'Collection');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_NO_DOCUMENTS', '# Documents');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_1', 'Some collections are missing');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_2', 'Run a Full-Sync');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_1', 'No collections found');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_2', 'Run a Full-Sync');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_1', 'The products collection is empty');
define('TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_2', 'Run a Full-Sync');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_TITLE', 'Synonyms');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_ADD_SYNONYM_BUTTON_TEXT', 'Add Synonym');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_COLLECTION_LABEL', 'Collection');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_SYNONYMS_LABEL', 'Synonyms');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_ROOT_LABEL', 'Root (optional)');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_SYNONYMS_PLACEHOLDER', 'Type a synonym and press Enter');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_SAVE_BUTTON_TEXT', 'Save');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_CANCEL_BUTTON_TEXT', 'Cancel');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_ROOT_LABEL', 'Root');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_EMPTY', 'No synonyms.');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_EDIT_BUTTON_LABEL', 'Edit');
define('TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_DELETE_BUTTON_LABEL', 'Delete');
