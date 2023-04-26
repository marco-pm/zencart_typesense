<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.1
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

namespace Zencart\Plugins\Catalog\Typesense;

require __DIR__ . '/../vendor/autoload.php';

use Http\Client\Exception as HttpClientException;
use queryFactoryResult;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;
use Zencart\Plugins\Catalog\InstantSearch\InstantSearchLogger;
use Zencart\Plugins\Catalog\Typesense\Exceptions\TypesenseIndexProductsException;

class TypesenseZencart
{
    /**
     * The Typesense products collection name (alias).
     */
    public const PRODUCTS_COLLECTION_NAME = DB_DATABASE . "_products";

    /**
     * The Typesense categories collection name (alias).
     */
    public const CATEGORIES_COLLECTION_NAME = DB_DATABASE . "_categories";

    /**
     * The Typesense brands collection name (alias).
     */
    public const BRANDS_COLLECTION_NAME = DB_DATABASE . "_brands";

    /**
     * The Typesense PHP client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Array of Zen Cart language ids and codes.
     *
     * @var queryFactoryResult
     */
    protected queryFactoryResult $languages;

    /**
     * Array of Zen Cart currency ids and codes.
     *
     * @var queryFactoryResult
     */
    protected queryFactoryResult $currencies;

    /**
     * @var bool
     */
    protected bool $writeSyncLog = false;

    /**
     * @var InstantSearchLogger
     */
    protected InstantSearchLogger $logger;

    /**
     * Constructor.
     *
     * @throws ConfigError
     */
    public function __construct()
    {
        global $db;

        $this->client = new Client(
            [
                'api_key' => TYPESENSE_KEY,
                'nodes' => [
                    [
                        'host'     => TYPESENSE_HOST,
                        'port'     => TYPESENSE_PORT,
                        'protocol' => TYPESENSE_PROTOCOL,
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );

        $this->languages = $db->Execute("SELECT languages_id, code FROM " . TABLE_LANGUAGES);

        $this->currencies = $db->Execute("SELECT currencies_id, code FROM " . TABLE_CURRENCIES);

        if (defined('TYPESENSE_ENABLE_SYNC_LOG') && TYPESENSE_ENABLE_SYNC_LOG === 'true') {
            $this->logger = new InstantSearchLogger('typesense-sync');
            $this->writeSyncLog = true;
        }
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }


    /**
     * Checks if Typesense is enabled and configured.
     *
     * @return bool
     */
    public static function isTypesenseEnabledAndConfigured(): bool
    {
        return defined('INSTANT_SEARCH_ENGINE') &&
            INSTANT_SEARCH_ENGINE === 'Typesense' &&
            !empty(TYPESENSE_HOST) &&
            !empty(TYPESENSE_PORT) &&
            !empty(TYPESENSE_KEY);
    }

    /**
     * Run the sync process, if it's not already running and other conditions are met.
     * Run a Full-Sync if one of the following conditions is true (otherwise, run an Incremental-Sync):
     * - the "is_next_run_full" db field is set to 1 (the store owner requested a Full-Sync through the admin interface,
     *   or there have been changes to categories, brands, languages or currencies)
     * - the last Full-Sync completed more than TYPESENSE_FULL_SYNC_FREQUENCY_HOURS hours ago, or has never been launched
     *
     * @return void
     * @throws HttpClientException|TypesenseClientError|\JsonException|TypesenseIndexProductsException
     */
    public function runSync(): void
    {
        global $db;

        $sql = "
            SELECT
                status,
                start_time,
                is_next_run_full,
                last_full_sync_end_time,
                TIMESTAMPDIFF(MINUTE, start_time, NOW()) AS minutes_since_last_start,
                TIMESTAMPDIFF(HOUR, last_full_sync_end_time, NOW()) AS hours_since_last_full_sync
            FROM
                " . TABLE_TYPESENSE_SYNC . "
            WHERE
                id = 1
        ";
        $result = $db->Execute($sql);

        if ($result->fields['status'] === 'running') {
            if (
                $result->fields['minutes_since_last_start'] === null ||
                $result->fields['minutes_since_last_start'] > TYPESENSE_SYNC_TIMEOUT_MINUTES
            ) {
                $this->writeSyncLog('Last sync is still running, but has been running for more than ' . TYPESENSE_SYNC_TIMEOUT_MINUTES . ' minutes. Aborting.');
                $sql = "
                    UPDATE
                        " . TABLE_TYPESENSE_SYNC . "
                    SET
                        status = 'failed'
                    WHERE
                        id = 1
                ";
                $db->Execute($sql);
                $result->fields['status'] = 'failed';
            } else {
                $this->writeSyncLog('Last sync is still running. Exiting.');
                return;
            }
        }

        if ($result->fields['status'] === 'failed' && TYPESENSE_SYNC_AFTER_FAILED === 'false') {
            $this->writeSyncLog('Last sync has failed, and sync-after-failed is disabled. Exiting.');
            return;
        }

        if (
            $result->fields['is_next_run_full'] === '1' ||
            $result->fields['last_full_sync_end_time'] === null ||
            $result->fields['hours_since_last_full_sync'] === null ||
            $result->fields['hours_since_last_full_sync'] >= TYPESENSE_FULL_SYNC_FREQUENCY_HOURS
        ) {
            $this->syncFull();

            if ($result->fields['is_next_run_full'] === '1') {
                $sql = "
                    UPDATE
                        " . TABLE_TYPESENSE_SYNC . "
                    SET
                        is_next_run_full = 0
                    WHERE
                        id = 1
                ";
                $db->Execute($sql);
            }

            return;
        }

        $this->syncIncremental();
    }

    /**
     * Sets the next sync to be a Full-Sync.
     *
     * @return void
     */
    public static function setFullSyncGraceful(): void
    {
        global $db;

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                is_next_run_full = 1
            WHERE
                id = 1
        ";
        $db->Execute($sql);
    }

    /**
     * Ignores the last sync status and forces the Full-Sync to run on the next cron run.
     *
     * @return void
     */
    public static function setFullSyncForced(): void
    {
        global $db;

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                status = 'completed',
                is_next_run_full = 1
            WHERE
                id = 1
        ";
        $db->Execute($sql);
    }

    /**
     * Adds a product ID to the list of products to be deleted in the next run.
     *
     * @param int $productsId
     * @return void
     */
    public static function addProductsIdToBeDeleted(int $productsId): void
    {
        global $db;

        $sql = "
            SELECT
                products_ids_to_delete
            FROM
                " . TABLE_TYPESENSE_SYNC . "
            WHERE
                id = 1
        ";
        $result = $db->Execute($sql);
        if (empty($result->fields['products_ids_to_delete'])) {
            $productsIdsToDelete = [];
        } else {
            $productsIdsToDelete = unserialize($result->fields['products_ids_to_delete']);
        }
        if (!is_array($productsIdsToDelete)) {
            $productsIdsToDelete = [];
        }
        $productsIdsToDelete[] = $productsId;
        $productsIdsToDelete = serialize($productsIdsToDelete);
        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                products_ids_to_delete = '" . $productsIdsToDelete . "'
            WHERE
                id = 1
        ";
        $db->Execute($sql);
    }

    /**
     * Recreates the collections, indexes the documents and updates the aliases.
     * This is a full re-index that should run only once a day, or immediately after changes that require the
     * re-creation of the collection(s).
     *
     * @return void
     * @throws HttpClientException|TypesenseClientError|\JsonException|TypesenseIndexProductsException
     */
    protected function syncFull(): void
    {
        global $db;

        $this->writeSyncLog('--- Starting Full-Sync of Typesense collections ---');

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                start_time = NOW(),
                status = 'running'
            WHERE
                id = 1
        ";
        $db->Execute($sql);

        try {
            $productsCollectionName = self::PRODUCTS_COLLECTION_NAME . '_' . time();
            $this->writeSyncLog('Creating products collection ' . $productsCollectionName);
            $this->createProductsCollection($productsCollectionName);
            $this->writeSyncLog('Begin indexing products collection ' . $productsCollectionName);
            $this->indexProductsCollection($productsCollectionName, true);
            $this->writeSyncLog('End indexing products collection ' . $productsCollectionName);
            $this->writeSyncLog('Copying products collection synonyms ' . $productsCollectionName);
            $this->copySynonyms(self::PRODUCTS_COLLECTION_NAME, $productsCollectionName);
            $this->writeSyncLog('End copying products collection synonyms ' . $productsCollectionName);
            $this->writeSyncLog('Updating products alias ' . $productsCollectionName);
            $this->updateAlias(self::PRODUCTS_COLLECTION_NAME, $productsCollectionName);

            $categoriesCollectionName = self::CATEGORIES_COLLECTION_NAME . '_' . time();
            $this->writeSyncLog('Creating categories collection ' . $categoriesCollectionName);
            $this->createCategoriesCollection($categoriesCollectionName);
            $this->writeSyncLog('Indexing categories collection ' . $categoriesCollectionName);
            $this->indexFullCategoriesCollection($categoriesCollectionName);
            $this->writeSyncLog('Copying categories collection synonyms ' . $categoriesCollectionName);
            $this->copySynonyms(self::CATEGORIES_COLLECTION_NAME, $categoriesCollectionName);
            $this->writeSyncLog('Updating categories alias ' . $categoriesCollectionName);
            $this->updateAlias(self::CATEGORIES_COLLECTION_NAME, $categoriesCollectionName);

            $brandsCollectionName = self::BRANDS_COLLECTION_NAME . '_' . time();
            $this->writeSyncLog('Creating brands collection ' . $brandsCollectionName);
            $this->createBrandsCollection($brandsCollectionName);
            $this->writeSyncLog('Indexing brands collection ' . $brandsCollectionName);
            $this->indexFullBrandsCollection($brandsCollectionName);
            $this->writeSyncLog('Copying brands collection synonyms ' . $brandsCollectionName);
            $this->copySynonyms(self::BRANDS_COLLECTION_NAME, $brandsCollectionName);
            $this->writeSyncLog('Updating brands alias ' . $brandsCollectionName);
            $this->updateAlias(self::BRANDS_COLLECTION_NAME, $brandsCollectionName);
        } catch (\Exception $e) {
            $this->writeSyncLog('ERROR: ' . $e->getMessage());
            $this->writeSyncLog('--- Full-sync failed ---');

            $sql = "
                UPDATE
                    " . TABLE_TYPESENSE_SYNC . "
                SET
                    status = 'failed'
                WHERE
                    id = 1
            ";
            $db->Execute($sql);

            throw $e;
        }

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                end_time = NOW(),
                last_full_sync_start_time = start_time,
                last_full_sync_end_time = end_time,
                status = 'completed'
            WHERE
                id = 1
        ";
        $db->Execute($sql);

        $this->writeSyncLog('--- Full-sync completed ---');
    }

    /**
     * Re-indexes the product documents that have been updated since the last sync.
     *
     * @return void
     * @throws HttpClientException|TypesenseClientError|\JsonException
     */
    protected function syncIncremental(): void
    {
        global $db;

        $this->writeSyncLog('--- Starting Incremental-Sync of Typesense products collection ---');

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                start_time = NOW(),
                status = 'running'
            WHERE
                id = 1
        ";
        $db->Execute($sql);

        try {
            $productsCollectionName = $this->client->aliases[self::PRODUCTS_COLLECTION_NAME]->retrieve();
        } catch (ObjectNotFound $e) {
            $this->writeSyncLog('ERROR: alias ' . self::PRODUCTS_COLLECTION_NAME . ' not found. Run a Full-Sync to create it. Exiting...');
            $this->writeSyncLog('--- Incremental-sync failed ---');
            return;
        }

        try {
            $this->indexProductsCollection($productsCollectionName['collection_name']);
        } catch (\Exception $e) {
            $this->writeSyncLog('ERROR: ' . $e->getMessage());
            $this->writeSyncLog('--- Incremental-sync failed ---');

            $sql = "
                UPDATE
                    " . TABLE_TYPESENSE_SYNC . "
                SET
                    status = 'failed'
                WHERE
                    id = 1
            ";
            $db->Execute($sql);

            throw $e;
        }

        $sql = "
            UPDATE
                " . TABLE_TYPESENSE_SYNC . "
            SET
                end_time = NOW(),
                last_incremental_sync_start_time = start_time,
                last_incremental_sync_end_time = end_time,
                status = 'completed'
            WHERE
                id = 1
        ";
        $db->Execute($sql);

        $this->writeSyncLog('--- Incremental-sync completed ---');
    }

    /**
     * Creates/updates the collection alias and drops the old collection(s).
     *
     * @param string $aliasName
     * @param string $newCollectionName
     * @return void
     * @throws HttpClientException|TypesenseClientError
     */
    protected function updateAlias(string $aliasName, string $newCollectionName): void
    {
        $this->client->aliases->upsert($aliasName, ['collection_name' => $newCollectionName]);

        $collections = $this->client->collections->retrieve();
        foreach ($collections as $collection) {
            // If the collection's name begins with the alias name, it's an old collection that needs to be dropped
            // (this way we also drop "unfinished" collections created during failed syncs)
            if (strpos($collection['name'], $aliasName) === 0 && $collection['name'] !== $newCollectionName) {
                $this->client->collections[$collection['name']]->delete();
            }
        }
    }

    /**
     * Copies the synonyms from the old collection to the new one.
     *
     * @param string $aliasName
     * @param string $newCollectionName
     * @return void
     * @throws HttpClientException|TypesenseClientError
     */
    protected function copySynonyms(string $aliasName, string $newCollectionName): void
    {
        try {
            $collectionName = $this->client->aliases[$aliasName]->retrieve();
        } catch (ObjectNotFound $e) {
            // If the Full-Sync is run for the first time, the alias doesn't exist yet
            return;
        }

        $synonyms = $this->client->collections[$collectionName['collection_name']]->synonyms->retrieve();
        foreach ($synonyms['synonyms'] as $synonym) {
            $this->client->collections[$newCollectionName]->synonyms->upsert($synonym['id'], $synonym);
        }
    }

    /**
     * Indexes the product documents.
     * If $fullSync is false, only the products that have been created/updated/deleted since the last sync are indexed.
     *
     * @param string $productsCollectionName
     * @param bool $fullSync
     * @throws TypesenseClientError|HttpClientException|\JsonException|TypesenseIndexProductsException
     */
    protected function indexProductsCollection(string $productsCollectionName, bool $fullSync = false): void
    {
        global $db, $currencies;

        if (!$fullSync) {
            $sql = "
                SELECT
                    last_incremental_sync_start_time,
                    last_full_sync_start_time
                FROM
                    " . TABLE_TYPESENSE_SYNC . "
                WHERE
                    id = 1
            ";
            $lastSyncStartTime = $db->Execute($sql)->fields['last_incremental_sync_start_time'];
            if (!$lastSyncStartTime) {
                $lastSyncStartTime = $db->Execute($sql)->fields['last_full_sync_start_time'];
            }
            if (!$lastSyncStartTime) {
                throw new TypesenseIndexProductsException('Last sync start time not found. Run a Full-Sync first.');
            }
        }

        $sql = "
            SELECT
                p.products_id,
                p.products_model,
                p.products_price_sorter,
                p.products_quantity,
                p.products_weight,
                p.products_image,
                p.master_categories_id,
                p.products_sort_order,
                m.manufacturers_name
            FROM
                " . TABLE_PRODUCTS . " p
                LEFT JOIN " . TABLE_MANUFACTURERS . " m ON (m.manufacturers_id = p.manufacturers_id)
            WHERE
                p.products_status <> 0" .
                ($fullSync ? "" : " AND (
                    p.products_last_modified >= :lastSyncStartTime
                    OR p.products_date_added >= :lastSyncStartTime
                )")
        ;
        if (!$fullSync) {
            $sql = $db->bindVars($sql, ':lastSyncStartTime', $lastSyncStartTime, 'date');
        }
        $products = $db->Execute($sql);

        if ($products->RecordCount() === 0) {
            $this->writeSyncLog($fullSync ? 'No products to index' : 'No products to insert/update');
        } else {
            $productsToIndex = [];
            $i = 1;
            foreach ($products as $product) {
                $productData = [
                    'id' => (string)$product['products_id'],
                    'model' => $product['products_model'],
                    'price' => (float)$product['products_price_sorter'],
                    'quantity' => (float)$product['products_quantity'],
                    'weight' => (float)$product['products_weight'],
                    'image' => $product['products_image'] ?? '',
                    'sort-order' => (int)$product['products_sort_order'],
                    'manufacturer' => $product['manufacturers_name'] ?? '',
                ];

                $productData['rating'] = $this->getProductRating((int)$product['products_id']);

                foreach ($this->languages as $language) {
                    $productAdditionalData = $this->getProductAdditionalData((int)$product['products_id'], (int)$language['languages_id']);

                    if ($productAdditionalData->RecordCount() > 0) {
                        $productLanguageData = [
                            'name_' . $language['code'] => $productAdditionalData->fields['products_name'],
                            'description_' . $language['code'] => $productAdditionalData->fields['products_description'],
                            'meta-keywords_' . $language['code'] => $productAdditionalData->fields['metatags_keywords'] ?? '',
                            'views_' . $language['code'] => (int)$productAdditionalData->fields['total_views'],
                        ];

                        $productData = array_merge($productData, $productLanguageData);
                    }

                    // Get the parent categories names
                    $parentCategories = $this->getParentCategories((int)$product['master_categories_id'], (int)$language['languages_id']);
                    $parentCategoriesNames = [];
                    foreach ($parentCategories as $parentCategory) {
                        $parentCategoriesNames[] = $parentCategory['categories_name'];
                    }
                    $parentCategoriesNames = implode(' ', array_reverse($parentCategoriesNames));
                    $productData['category_' . $language['code']] = $parentCategoriesNames ?? '';
                }

                $baseCurrency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
                foreach ($this->currencies as $currency) {
                    $_SESSION['currency'] = $currency['code'];
                    $productData['displayed-price_' . $currency['code']] = zen_get_products_display_price($product['products_id']);
                }
                $_SESSION['currency'] = $baseCurrency;

                $productsToIndex[] = $productData;

                // Index the products in batches of 100
                if ($i % 100 === 0 || $i === count($products)) {
                    $startIndex = ($i === count($products)) ? ($i - ($i % 100) + 1) : $i - 99;
                    $this->writeSyncLog("Indexing products $startIndex to $i (of " . count($products) . ")");
                    $this->client->collections[$productsCollectionName]->documents->import($productsToIndex, ['action' => $fullSync ? 'create' : 'upsert']);
                    $productsToIndex = [];
                }

                $i++;
            }
        }

        if ($fullSync) {
            // No need to keep track of the products to delete anymore, since we just full-synced the collection
            $sql = "
                UPDATE
                    " . TABLE_TYPESENSE_SYNC . "
                SET
                    products_ids_to_delete = NULL
                WHERE
                    id = 1
            ";
            $db->Execute($sql);
        } else {
            $sql = "
                SELECT
                    products_ids_to_delete
                FROM
                    " . TABLE_TYPESENSE_SYNC
            ;
            $productsIdsToDelete = $db->Execute($sql);
            if (!empty($productsIdsToDelete->fields['products_ids_to_delete'])) {
                $productsIdsToDelete = unserialize($productsIdsToDelete->fields['products_ids_to_delete']);
                if (is_array($productsIdsToDelete)) {
                    $productsIdsToDeleteList = implode(', ', $productsIdsToDelete);
                    $this->writeSyncLog("Deleting product IDs " . $productsIdsToDeleteList);
                    $this->client->collections[$productsCollectionName]->documents->delete(['filter_by' => 'id:[' . $productsIdsToDeleteList . ']']);
                }

                $sql = "
                    UPDATE
                        " . TABLE_TYPESENSE_SYNC . "
                    SET
                        products_ids_to_delete = NULL
                    WHERE
                        id = 1
                ";
                $db->Execute($sql);
            } else {
                $this->writeSyncLog('No products to delete');
            }
        }
    }

    /**
     * Full-indexes the category documents.
     *
     * @param string $categoriesCollectionName
     * @throws TypesenseClientError|HttpClientException|\JsonException
     */
    protected function indexFullCategoriesCollection(string $categoriesCollectionName): void
    {
        global $db;

        $categoriesToImport = [];

        $sql = "
            SELECT
                c.categories_id,
                c.categories_image
            FROM
                " . TABLE_CATEGORIES . " c
            WHERE
                c.categories_status <> 0
        ";
        $categories = $db->Execute($sql);

        foreach ($categories as $category) {
            $categoryData['id'] = (string)$category['categories_id'];
            $categoryData['image'] = $category['categories_image'] ?? '';
            $categoryData['products-count'] = $this->countProductsInCategory((int)$category['categories_id']);

            foreach ($this->languages as $language) {
                $categoryAdditionalData = $db->Execute("
                    SELECT
                        categories_name
                    FROM
                        " . TABLE_CATEGORIES_DESCRIPTION . "
                    WHERE
                        categories_id = " . (int)$category['categories_id'] . "
                        AND language_id = " . (int)$language['languages_id']
                );

                if ($categoryAdditionalData->RecordCount() > 0) {
                    $categoryData['name_' . $language['code']] = $categoryAdditionalData->fields['categories_name'];
                }
            }

            $categoriesToImport[] = $categoryData;
        }

        if (!empty($categoriesToImport)) {
            $this->client->collections[$categoriesCollectionName]->documents->import($categoriesToImport, ['action' => 'create']);
        }
    }

    /**
     * Full-indexes the brands documents.
     *
     * @param string $brandsCollectionName
     * @throws TypesenseClientError|HttpClientException|\JsonException
     */
    protected function indexFullBrandsCollection(string $brandsCollectionName): void
    {
        global $db;

        $brandsToImport = [];

        $sql = "
            SELECT
                m.manufacturers_id,
                m.manufacturers_name,
                m.manufacturers_image
            FROM
                " . TABLE_MANUFACTURERS . " m
        ";
        $brands = $db->Execute($sql);

        foreach ($brands as $brand) {
            $brandData['id'] = (string)$brand['manufacturers_id'];
            $brandData['name'] = $brand['manufacturers_name'];
            $brandData['image'] = $brand['manufacturers_image'] ?? '';

            $manufacturerAdditionalData = $db->Execute("
                SELECT
                    COUNT(*) AS products_count
                FROM
                    " . TABLE_PRODUCTS . "
                WHERE
                    manufacturers_id = " . (int)$brand['manufacturers_id'] . "
                    AND products_status = 1
            ");
            $brandData['products-count'] = (int)$manufacturerAdditionalData->fields['products_count'];

            $brandsToImport[] = $brandData;
        }

        if (!empty($brandsToImport)) {
            $this->client->collections[$brandsCollectionName]->documents->import($brandsToImport, ['action' => 'upsert']);
        }
    }

    /**
     * Creates the products collection.
     *
     * @param string $collectionName
     * @throws TypesenseClientError|HttpClientException
     */
    protected function createProductsCollection(string $collectionName): void
    {
        $schema = [
            'name'      =>  $collectionName,
            'fields'    => [
                [
                    'name'     => 'id',
                ],
                [
                    'name'     => 'model',
                    'type'     => 'string',
                    'infix'    => true
                ],
                [
                    'name'     => 'price',
                    'type'     => 'float',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'quantity',
                    'type'     => 'float',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'weight',
                    'type'     => 'float',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'image',
                    'type'     => 'string',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'manufacturer',
                    'type'     => 'string',
                    'infix'    => true
                ],
                [
                    'name'     => 'sort-order',
                    'type'     => 'int32',
                    'optional' => true
                ],
                [
                    'name'     => 'rating',
                    'type'     => 'float',
                    'index'    => false,
                    'optional' => true
                ],
            ]
        ];

        foreach ($this->languages as $language) {
            $schema['fields'][] = [
                'name'     => 'name_' . $language['code'],
                'type'     => 'string',
                'infix'    => true
            ];
            $schema['fields'][] = [
                'name'     => 'description_' . $language['code'],
                'type'     => 'string',
                'infix'    => true
            ];
            $schema['fields'][] = [
                'name'     => 'meta-keywords_' . $language['code'],
                'type'     => 'string',
                'infix'    => true
            ];
            $schema['fields'][] = [
                'name'     => 'views_' . $language['code'],
                'type'     => 'int32',
                'optional' => true
            ];
            $schema['fields'][] = [
                'name'     => 'category_' . $language['code'],
                'type'     => 'string',
                'infix'    => true
            ];
        }

        foreach ($this->currencies as $currency) {
            $schema['fields'][] = [
                'name'     => 'displayed-price_' . $currency['code'],
                'type'     => 'string',
                'index'    => false,
                'optional' => true
            ];
        }

        $this->client->collections->create($schema);
    }

    /**
     * Creates the categories collection.
     *
     * @param string $categoriesCollectionName
     * @throws TypesenseClientError|HttpClientException
     */
    protected function createCategoriesCollection(string $categoriesCollectionName): void
    {
        $schema = [
            'name'      => $categoriesCollectionName,
            'fields'    => [
                [
                    'name'     => 'id',
                ],
                [
                    'name'     => 'image',
                    'type'     => 'string',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'products-count',
                    'type'     => 'int32',
                    'index'    => false,
                    'optional' => true
                ]
            ]
        ];

        foreach ($this->languages as $language) {
            $schema['fields'][] = [
                'name'  => 'name_' . $language['code'],
                'type'  => 'string',
                'sort'  => true,
                'infix' => true
            ];
        }

        $this->client->collections->create($schema);
    }

    /**
     * Creates the brands collection.
     *
     * @param string $brandsCollectionName
     * @throws TypesenseClientError|HttpClientException
     */
    protected function createBrandsCollection(string $brandsCollectionName): void
    {
        $schema = [
            'name'      => $brandsCollectionName,
            'fields'    => [
                [
                    'name'     => 'id',
                ],
                [
                    'name'     => 'name',
                    'type'     => 'string',
                    'sort'     => true,
                    'infix'    => true
                ],
                [
                    'name'     => 'image',
                    'type'     => 'string',
                    'index'    => false,
                    'optional' => true
                ],
                [
                    'name'     => 'products-count',
                    'type'     => 'int32',
                    'index'    => false,
                    'optional' => true
                ]
            ]
        ];

        $this->client->collections->create($schema);
    }

    /**
     * Returns the parent categories of the given category.
     *
     * @param int $categoriesId
     * @param int $languageId
     * @param array $parentCategories
     * @return array
     */
    private function getParentCategories(int $categoriesId, int $languageId, array $parentCategories = []): array
    {
        global $db;

        $sql = "
            SELECT
                c.categories_id,
                cd.categories_name,
                c.parent_id
            FROM
                " . TABLE_CATEGORIES . " c
                LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON (
                    cd.categories_id = c.categories_id
                    AND cd.language_id = :languages_id
                )
            WHERE
                c.categories_id = :categories_id
        ";
        $sql = $db->bindVars($sql, ':categories_id', $categoriesId, 'integer');
        $sql = $db->bindVars($sql, ':languages_id', $languageId, 'integer');
        $category = $db->Execute($sql);

        if ($category->RecordCount() > 0) {
            $parentCategories[] = [
                'categories_id'   => $category->fields['categories_id'],
                'categories_name' => $category->fields['categories_name'],
            ];

            if ($category->fields['parent_id'] > 0) {
                $parentCategories = $this->getParentCategories((int)$category->fields['parent_id'], $languageId, $parentCategories);
            }
        }

        return $parentCategories;
    }

    /**
     * Returns the number of products in the given category.
     * Note: it's the same as the function zen_count_products_in_category(), but because the function is loaded
     * differently in ZC157 vs ZC158, we're adding it here too in order to avoid issues when running the typesense
     * sync via crontab.
     *
     * @param int $categoryId
     * @return int
     */
    private function countProductsInCategory(int $categoryId): int
    {
        global $db;

        $productsCount = 0;

        $sql = "
            SELECT
                count(*) AS total
            FROM
                " . TABLE_PRODUCTS . " p
                LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON p.products_id = p2c.products_id
            WHERE
                p2c.categories_id = :categories_id
                AND p.products_status = 1
        ";

        $sql = $db->bindVars($sql, ':categories_id', $categoryId, 'integer');
        $products = $db->Execute($sql);
        $productsCount += (int)$products->fields['total'];

        $sql = "
            SELECT
                categories_id
            FROM
                " . TABLE_CATEGORIES . "
            WHERE
                parent_id = :categories_id
        ";
        $sql = $db->bindVars($sql, ':categories_id', $categoryId, 'integer');
        $childCategories = $db->Execute($sql);
        foreach ($childCategories as $childCategory) {
            $productsCount += $this->countProductsInCategory((int)$childCategory['categories_id']);
        }

        return $productsCount;
    }

    /**
     * Returns the average rating of the given product.
     *
     * @param int $productId
     * @return float
     */
    private function getProductRating(int $productId): float
    {
        global $db;

        $sql = "
            SELECT
                AVG(r.reviews_rating) AS average_rating
            FROM
                " . TABLE_REVIEWS . " r
            WHERE
                r.products_id = :products_id
                AND r.status = 1
        ";
        $sql = $db->bindVars($sql, ':products_id', $productId, 'integer');
        $productRating = $db->Execute($sql);

        return (float)$productRating->fields['average_rating'];
    }

    /**
     * Returns the product's name, description, meta tags keywords and total views.
     *
     * @param int $productId
     * @param int $languageId
     * @return queryFactoryResult
     */
    private function getProductAdditionalData(int $productId, int $languageId): QueryFactoryResult
    {
        global $db;

        $sql = "
            SELECT
                pd.products_name,
                pd.products_description,
                mtpd.metatags_keywords,
                SUM(cpv.views) AS total_views
            FROM
                " . TABLE_PRODUCTS_DESCRIPTION . " pd
                LEFT JOIN " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " mtpd ON (
                    mtpd.products_id = pd.products_id
                    AND mtpd.language_id = pd.language_id
                )
                LEFT JOIN " . TABLE_COUNT_PRODUCT_VIEWS . " cpv ON (
                    cpv.product_id = pd.products_id
                    AND cpv.language_id = pd.language_id
                )
            WHERE
                pd.products_id = :products_id
                AND pd.language_id = :languages_id
            GROUP BY
                pd.products_id, pd.products_name, pd.products_description, mtpd.metatags_keywords
        ";

        $sql = $db->bindVars($sql, ':products_id', $productId, 'integer');
        $sql = $db->bindVars($sql, ':languages_id', $languageId, 'integer');

        return $db->Execute($sql);
    }

    /**
     * Writes a message to the sync log, if enabled.
     *
     * @param string $message
     * @return void
     */
    protected function writeSyncLog(string $message): void
    {
        if ($this->writeSyncLog) {
            $this->logger->writeDebugLog($message);
        }
    }
}
