<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
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

class TypesenseZencart
{
    /**
     * The Typesense products collection name.
     */
    public const PRODUCTS_COLLECTION_NAME = DB_DATABASE . "_products";

    /**
     * The Typesense categories collection name.
     */
    public const CATEGORIES_COLLECTION_NAME = DB_DATABASE . "_categories";

    /**
     * The Typesense brands collection name.
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
            $this->writeSyncLog = true;
            $this->logger = new InstantSearchLogger('typesense-sync');
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
     * Recreates the collections, indexes the documents and updates the aliases.
     * This is a full re-index that should run only once a day, or immediately after changes that require the
     * re-creation of the collection(s).
     *
     * @return void
     * @throws HttpClientException|TypesenseClientError|\JsonException
     */
    public function syncFull(): void
    {
        $this->writeSyncLog('-------------------------------------------');
        $this->writeSyncLog('Starting Full-sync of Typesense collections');
        $this->writeSyncLog('-------------------------------------------');

        $productsCollectionName = self::PRODUCTS_COLLECTION_NAME . '_' . time();
        $this->writeSyncLog('Creating products collection ' . $productsCollectionName);
        $this->createProductsCollection($productsCollectionName);
        $this->writeSyncLog('Begin indexing products collection ' . $productsCollectionName);
        $this->indexFullProductsCollection($productsCollectionName);
        $this->writeSyncLog('End indexing products collection ' . $productsCollectionName);
        $this->writeSyncLog('Updating products alias ' . $productsCollectionName);
        $this->updateAlias(self::PRODUCTS_COLLECTION_NAME, $productsCollectionName);

        $categoriesCollectionName = self::CATEGORIES_COLLECTION_NAME . '_' . time();
        $this->writeSyncLog('Creating categories collection ' . $categoriesCollectionName);
        $this->createCategoriesCollection($categoriesCollectionName);
        $this->writeSyncLog('Indexing categories collection ' . $categoriesCollectionName);
        $this->indexFullCategoriesCollection($categoriesCollectionName);
        $this->writeSyncLog('Updating categories alias ' . $categoriesCollectionName);
        $this->updateAlias(self::CATEGORIES_COLLECTION_NAME, $categoriesCollectionName);

        $brandsCollectionName = self::BRANDS_COLLECTION_NAME . '_' . time();
        $this->writeSyncLog('Creating brands collection ' . $brandsCollectionName);
        $this->createBrandsCollection($brandsCollectionName);
        $this->writeSyncLog('Indexing brands collection ' . $brandsCollectionName);
        $this->indexFullBrandsCollection($brandsCollectionName);
        $this->writeSyncLog('Updating brands alias ' . $brandsCollectionName);
        $this->updateAlias(self::BRANDS_COLLECTION_NAME, $brandsCollectionName);

        $this->writeSyncLog('----------------------------------');
        $this->writeSyncLog('Full-sync of collections completed');
        $this->writeSyncLog('----------------------------------');
    }

    public function syncChanges(): void
    {

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
            // (this way we also drop "unfinished" collections created during aborted syncs)
            if (strpos($collection['name'], $aliasName) === 0 && $collection['name'] !== $newCollectionName) {
                $this->client->collections[$collection['name']]->delete();
            }
        }
    }

    /**
     * Full-indexes the product documents.
     *
     * @param string $productsCollectionName
     * @throws TypesenseClientError|HttpClientException|\JsonException
     */
    protected function indexFullProductsCollection(string $productsCollectionName): void
    {
        global $db, $currencies;

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
                p.products_status <> 0
        ";
        $products = $db->Execute($sql);

        $productsToImport = [];
        $i = 1;
        foreach ($products as $product) {
            $productData = [
                'id'           => (string)$product['products_id'],
                'model'        => $product['products_model'],
                'price'        => (float)$product['products_price_sorter'],
                'quantity'     => (float)$product['products_quantity'],
                'weight'       => (float)$product['products_weight'],
                'image'        => $product['products_image'] ?? '',
                'sort-order'   => (int)$product['products_sort_order'],
                'manufacturer' => $product['manufacturers_name'] ?? '',
            ];

            $productData['rating'] = $this->getProductRating((int)$product['products_id']);

            foreach ($this->languages as $language) {
                $productAdditionalData = $this->getProductAdditionalData((int)$product['products_id'], (int)$language['languages_id']);

                if ($productAdditionalData->RecordCount() > 0) {
                    $productLanguageData = [
                        'name_' . $language['code']          => $productAdditionalData->fields['products_name'],
                        'description_' . $language['code']   => $productAdditionalData->fields['products_description'],
                        'meta-keywords_' . $language['code'] => $productAdditionalData->fields['metatags_keywords'] ?? '',
                        'views_' . $language['code']         => (int)$productAdditionalData->fields['total_views'],
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

            $productsToImport[] = $productData;

            // Index the products in batches of 100
            if ($i % 100 === 0 || $i === count($products)) {
                $startIndex = ($i === count($products)) ? ($i - ($i % 100) + 1) : $i - 99;
                $this->writeSyncLog("Indexing products $startIndex to $i (of " . count($products) . ")");
                $this->client->collections[$productsCollectionName]->documents->import($productsToImport, ['action' => 'create']);
                $productsToImport = [];
            }

            $i++;
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
            $categoryData['products-count'] = zen_count_products_in_category($category['categories_id']);

            foreach ($this->languages as $language) {
                $categoryAdditionalData = $db->Execute(
                    "SELECT categories_name
                     FROM " . TABLE_CATEGORIES_DESCRIPTION . "
                     WHERE categories_id = " . (int)$category['categories_id'] . "
                     AND language_id = " . (int)$language['languages_id']
                );

                if ($categoryAdditionalData->RecordCount() > 0) {
                    $categoryData['name_' . $language['code']] = $categoryAdditionalData->fields['categories_name'];
                }
            }

            $categoriesToImport[] = $categoryData;
        }

        $this->client->collections[$categoriesCollectionName]->documents->import($categoriesToImport, ['action' => 'create']);
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

            $manufacturerAdditionalData = $db->Execute(
                "SELECT COUNT(*) AS products_count
                 FROM " . TABLE_PRODUCTS . "
                 WHERE manufacturers_id = " . (int)$brand['manufacturers_id'] .
                " AND products_status = 1"
            );
            $brandData['products-count'] = (int)$manufacturerAdditionalData->fields['products_count'];

            $brandsToImport[] = $brandData;
        }

        $this->client->collections[$brandsCollectionName]->documents->import($brandsToImport, ['action' => 'upsert']);
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
