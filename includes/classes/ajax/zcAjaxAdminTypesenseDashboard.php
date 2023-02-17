<?php
/**
 * @package  Instant Search Plugin for Zen Cart
 * @author   marco-pm
 * @version  4.0.0
 * @see      https://github.com/marco-pm/zencart_instantsearch
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

use Typesense\Client;
use Zencart\Plugins\Catalog\InstantSearch\InstantSearchLogger;
use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

class zcAjaxAdminTypesenseDashboard extends base
{
    /**
     * The Typesense PHP client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * @var InstantSearchLogger
     */
    protected InstantSearchLogger $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            exit();
        }

        $this->logger = new InstantSearchLogger('typesense-dashboard');

        try {
            $typesense = new TypesenseZencart();
        } catch (Exception $e) {
            $this->logger->writeErrorLog('TypesenseInstantSearch init error, exiting', $e);
            http_response_code(500);
            exit();
        }

        $this->client = $typesense->getClient();
    }

    /**
     * Gets the Typesense server health.
     *
     * @return array
     */
    public function getHealth(): array
    {
        try {
            return $this->client->health->retrieve();
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving Typesense health", $e);
            http_response_code(500);
            exit();
        }
    }

    /**
     * Gets the Typesense server metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        try {
            return $this->client->metrics->retrieve();
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving Typesense metrics", $e);
            http_response_code(500);
            exit();
        }
    }

    /**
     * Gets the Typesense sync status.
     *
     * @return array
     */
    public function getSyncStatus(): array
    {
        $db = $GLOBALS['db'];
        $sql = "
            SELECT
                status,
                is_next_run_full,
                last_incremental_sync_end_time,
                last_full_sync_end_time,
                TIMESTAMPDIFF(MINUTE, start_time, NOW()) AS minutes_since_last_start,
                TIMESTAMPDIFF(SECOND, start_time, NOW()) AS seconds_since_last_start,
                TIMESTAMPDIFF(HOUR, last_full_sync_end_time, NOW()) AS hours_since_last_full_sync
            FROM
                " . TABLE_TYPESENSE_SYNC . "
            WHERE
                id = 1
        ";
        $result = $db->Execute($sql);
        if ($result->RecordCount() === 0) {
            $this->logger->writeErrorLog("Error while retrieving Typesense sync status: the table " . TABLE_TYPESENSE_SYNC . " is empty");
            http_response_code(500);
            exit();
        }
        return $result->fields;
    }

    /**
     * Sets the next sync to be a full-sync.
     *
     * @return void
     */
    public function setFullSyncGraceful(): void
    {
        if (!empty($_GET['isForced']) && $_GET['isForced'] === 'false') {
            TypesenseZencart::setFullSyncGraceful();
        } else {
            TypesenseZencart::setFullSyncForced();
        }
    }

    /**
     * Gets the Typesense server collections and their number of documents.
     *
     * @return array
     */
    public function getCollectionsNoOfDocuments(): array
    {
        $zencartAliases = [
            TypesenseZencart::PRODUCTS_COLLECTION_NAME,
            TypesenseZencart::CATEGORIES_COLLECTION_NAME,
            TypesenseZencart::BRANDS_COLLECTION_NAME,
        ];

        $collectionsNoOfDocuments = [];

        try {
            $aliases = $this->client->aliases->retrieve();
            $collections = $this->client->collections->retrieve();

            if (empty($aliases) || empty($aliases['aliases']) || empty($collections)) {
                return $collectionsNoOfDocuments;
            }

            foreach ($aliases['aliases'] as $alias) {
                if (!in_array($alias['name'], $zencartAliases)) {
                    continue;
                }
                $collectionIndex = array_search($alias['collection_name'], array_column($collections, 'name'));
                if ($collectionIndex === false) {
                    continue;
                }
                $collectionsNoOfDocuments[] = [
                    'alias_name'    => str_replace(DB_DATABASE . '_', '', $alias['name']),
                    'num_documents' => $collections[$collectionIndex]['num_documents'],
                ];
            }
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving Typesense collections", $e);
            http_response_code(500);
            exit();
        }

        return $collectionsNoOfDocuments;
    }
}
