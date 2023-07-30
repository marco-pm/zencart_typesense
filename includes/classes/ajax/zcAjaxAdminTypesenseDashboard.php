<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.2
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

use Typesense\Client;
use Zencart\Plugins\Catalog\InstantSearch\InstantSearchLogger;
use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

class zcAjaxAdminTypesenseDashboard extends base
{
    /**
     * The Typesense collections that are used by the plugin.
     *
     * @var array
     */
    protected const ZENCART_ALIASES = [
        TypesenseZencart::PRODUCTS_COLLECTION_NAME,
        TypesenseZencart::CATEGORIES_COLLECTION_NAME,
        TypesenseZencart::BRANDS_COLLECTION_NAME,
    ];

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
     * Gets the server health.
     *
     * @return array
     */
    public function getHealth(): array
    {
        try {
            return $this->client->health->retrieve();
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving health", $e);
            http_response_code(500);
            exit();
        }
    }

    /**
     * Gets the server metrics.
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
     * Gets the sync status.
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
     * Sets the next sync to be a Full-Sync.
     * If $_GET['isForced'] is set to false, the next sync will be a graceful Full-Sync.
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
     * Gets the collections and their number of documents.
     *
     * @return array
     */
    public function getCollectionsNoOfDocuments(): array
    {
        $collectionsNoOfDocuments = [];

        try {
            $aliases = $this->client->aliases->retrieve();
            $collections = $this->client->collections->retrieve();

            if (empty($aliases) || empty($aliases['aliases']) || empty($collections)) {
                return $collectionsNoOfDocuments;
            }

            usort($aliases['aliases'], function ($a, $b) {
                return strcmp($a['collection_name'], $b['collection_name']);
            });

            foreach ($aliases['aliases'] as $alias) {
                if (!in_array($alias['name'], self::ZENCART_ALIASES)) {
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

    /**
     * Adds/updates a collection's synonym.
     * The synonym is passed via $_GET['synonyms'] and $_GET['collection'].
     * If $_GET['root'] is set, it will be used as the root of the synonym (one-way synonym).
     * If $_GET['id'] is set, the synonym will be updated, otherwise a new synonym will be created.
     *
     * @return array The created/updated synonym
     */
    public function upsertSynonym(): array
    {
        if (empty($_GET['synonyms']) || empty($_GET['collection'])) {
            http_response_code(400);
            exit();
        }

        $synonym = [
            "synonyms" => explode('|', $_GET['synonyms'])
        ];
        if (!empty($_GET['root'])) {
            $synonym['root'] = $_GET['root'];
        }
        $aliasName = DB_DATABASE . '_' . $_GET['collection'];

        try {
            $alias = $this->client->aliases[$aliasName]->retrieve();
            $collection = $alias['collection_name'];
            $synonymId = !empty($_GET['id']) ? $_GET['id'] : uniqid();
            $newSynonym = $this->client->collections[$collection]->synonyms->upsert($synonymId, $synonym);
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while adding/updating synonym", $e);
            http_response_code(500);
            exit();
        }

        $newSynonym['collection'] = $_GET['collection'];
        return $newSynonym;
    }

    /**
     * Deletes a synonym.
     * The synonym ID is passed via $_GET['id'] and $_GET['collection'].
     *
     * @return string The deleted synonym ID
     */
    public function deleteSynonym(): string
    {
        if (empty($_GET['id']) || empty($_GET['collection'])) {
            http_response_code(400);
            exit();
        }

        $aliasName = DB_DATABASE . '_' . $_GET['collection'];

        try {
            $alias = $this->client->aliases[$aliasName]->retrieve();
            $collection = $alias['collection_name'];
            $this->client->collections[$collection]->synonyms[$_GET['id']]->delete();
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while deleting synonym", $e);
            http_response_code(500);
            exit();
        }

        return $_GET['id'];
    }

    /**
     * Gets the list of synonyms.
     *
     * @return array
     */
    public function getSynonyms(): array
    {
        $collectionsSynonyms = [];

        try {
            $aliases = $this->client->aliases->retrieve();
            $collections = $this->client->collections->retrieve();

            if (empty($aliases) || empty($aliases['aliases']) || empty($collections)) {
                return $collectionsSynonyms;
            }

            foreach ($aliases['aliases'] as $alias) {
                if (!in_array($alias['name'], self::ZENCART_ALIASES)) {
                    continue;
                }
                $collectionSynonyms = $this->client->collections[$alias['collection_name']]->synonyms->retrieve();
                $aliasName = str_replace(DB_DATABASE . '_', '', $alias['name']);
                foreach ($collectionSynonyms['synonyms'] as $synonym) {
                    $collectionsSynonyms[] = [
                        'collection' => $aliasName,
                        'id'         => $synonym['id'],
                        'synonyms'   => $synonym['synonyms'],
                        'root'       => $synonym['root'] ?? '',
                    ];
                }
            }
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving synonyms", $e);
            http_response_code(500);
            exit();
        }

        return $collectionsSynonyms;
    }
}
