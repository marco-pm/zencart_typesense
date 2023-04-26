<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.1
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

declare(strict_types=1);

namespace Zencart\Plugins\Catalog\Typesense\SearchEngineProviders;

use Http\Client\Exception as HttpClientException;
use Typesense\Client;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;
use Zencart\Plugins\Catalog\InstantSearch\SearchEngineProviders\SearchEngineProviderInterface;
use Zencart\Plugins\Catalog\Typesense\Exceptions\TypesenseSearchException;
use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;

class TypesenseSearchEngineProvider extends \base implements SearchEngineProviderInterface
{
    /**
     * Array of product fields (keys) with the corresponding Typesense parameter values for query_by,
     * prefix, infix, num_typos.
     *
     * @var array
     */
    protected const FIELDS_TO_PARAMETERS = [
        'category'         => ['category_<lang>', 'true', 'fallback', 2],
        'manufacturer'     => ['manufacturer', 'true', 'fallback', 2],
        'meta-keywords'    => ['meta-keywords_<lang>', 'true', 'fallback', 2],
        'model-broad'      => ['model', 'true', 'fallback', 2],
        'model-exact'      => ['model', 'false', 'off', 0],
        'name'             => ['name_<lang>', 'true', 'fallback', 2],
        'name-description' => ['name_<lang>,description_<lang>', 'true,true', 'fallback,fallback', 2],
    ];

    /**
     * The Typesense PHP client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Array of search results.
     *
     * @var array
     */
    protected array $results;

    /**
     * Constructor.
     *
     * @throws ConfigError
     */
    public function __construct()
    {
        $typesense = new TypesenseZencart();
        $this->client = $typesense->getClient();
        $this->results = [];
    }

    /**
     * Searches for $queryText and returns the results.
     *
     * @param string $queryText
     * @param array $productFieldsList
     * @param int $productsLimit
     * @param int $categoriesLimit
     * @param int $manufacturersLimit
     * @param int|null $alphaFilter
     * @return array
     * @throws TypesenseSearchException|HttpClientException|TypesenseClientError
     */
    public function search(
        string $queryText,
        array $productFieldsList,
        int $productsLimit,
        int $categoriesLimit = 0,
        int $manufacturersLimit = 0,
        int $alphaFilter = null
    ): array {
        global $db;

        $sql = "SELECT code FROM " . TABLE_LANGUAGES . " WHERE languages_id = :languages_id";
        $sql = $db->bindVars($sql, ':languages_id', (int)$_SESSION['languages_id'], 'integer');
        $languageCode = $db->Execute($sql)->fields['code'];

        $productsSearch = [
            'query_by'              => '',
            'prefix'                => '',
            'infix'                 => '',
            'sort_by'               => "_text_match:desc,views_$languageCode:desc,sort-order:asc",
            'per_page'              => $productsLimit,
            'drop_tokens_threshold' => $productsLimit,
            'typo_tokens_threshold' => $productsLimit,
        ];

        $categoriesSearch = [
            'query_by'              => "name_$languageCode",
            'prefix'                => 'true',
            'infix'                 => 'fallback',
            'sort_by'               => "_text_match:desc,name_$languageCode:asc",
            'per_page'              => $categoriesLimit,
            'drop_tokens_threshold' => $categoriesLimit,
            'typo_tokens_threshold' => $categoriesLimit,
        ];

        $brandsSearch = [
            'query_by'              => "name",
            'prefix'                => 'true',
            'infix'                 => 'fallback',
            'sort_by'               => "_text_match:desc,name:asc",
            'per_page'              => $manufacturersLimit,
            'drop_tokens_threshold' => $manufacturersLimit,
            'typo_tokens_threshold' => $manufacturersLimit,
        ];

        foreach ($productFieldsList as $productField) {
            $productsSearch['query_by'] .= str_replace('<lang>', $languageCode, self::FIELDS_TO_PARAMETERS[$productField][0]) . ',';
            $productsSearch['prefix']   .= self::FIELDS_TO_PARAMETERS[$productField][1] . ',';
            $productsSearch['infix']    .= self::FIELDS_TO_PARAMETERS[$productField][2] . ',';
            $productsSearch['num_typos'] = self::FIELDS_TO_PARAMETERS[$productField][3];
        }

        if ($alphaFilter !== null) {
            $productsSearch['filter_by']   = "name_$languageCode: " . chr($alphaFilter);
        }

        $searchRequests = [
            'searches' => [
                array_merge(
                    [
                        'collection' => TypesenseZencart::PRODUCTS_COLLECTION_NAME,
                    ],
                    $productsSearch
                ),
                array_merge(
                    [
                        'collection' => TypesenseZencart::CATEGORIES_COLLECTION_NAME,
                    ],
                    $categoriesSearch
                ),
                array_merge(
                    [
                        'collection' => TypesenseZencart::BRANDS_COLLECTION_NAME,
                    ],
                    $brandsSearch
                )
            ]
        ];

        $commonSearchParams =  [
            'q' => $queryText,
        ];

        $this->notify(
            'NOTIFY_INSTANT_SEARCH_TYPESENSE_BEFORE_SEARCH',
            $queryText,
            $searchRequests,
            $commonSearchParams,
            $productsLimit,
            $categoriesLimit,
            $manufacturersLimit,
            $alphaFilter
        );

        $typesenseResults = $this->client->multiSearch->perform($searchRequests, $commonSearchParams);

        foreach ($typesenseResults['results'] as $typesenseResult) {
            if (isset($typesenseResult['error'])) {
                throw new TypesenseSearchException('Error while performing search: ' . $typesenseResult['error']);
            }
            if ($typesenseResult['found'] === 0) {
                continue;
            }
            $collectionName = $typesenseResult['request_params']['collection_name'];
            foreach ($typesenseResult['hits'] as $hit) {
                $result = [];
                $document = $hit['document'];

                if (strpos($collectionName, TypesenseZencart::PRODUCTS_COLLECTION_NAME) === 0) {
                    $result['products_id']              = $document['id'];
                    $result['products_name']            = $document["name_$languageCode"];
                    $result['products_image']           = $document['image'];
                    $result['products_model']           = $document['model'];
                    $result['products_price']           = $document['price'];
                    $result['products_displayed_price'] = $document['displayed-price_' . $_SESSION['currency']] ?? '';
                } elseif (strpos($collectionName, TypesenseZencart::CATEGORIES_COLLECTION_NAME) === 0) {
                    $result['categories_id']            = $document['id'];
                    $result['categories_name']          = $document["name_$languageCode"];
                    $result['categories_image']         = $document['image'];
                    $result['categories_count']         = $document['products-count'];
                } elseif (strpos($collectionName, TypesenseZencart::BRANDS_COLLECTION_NAME) === 0) {
                    $result['manufacturers_id']         = $document['id'];
                    $result['manufacturers_name']       = $document['name'];
                    $result['manufacturers_image']      = $document['image'];
                    $result['manufacturers_count']      = $document['products-count'];
                }

                $this->results[] = $result;
            }
        }

        $this->notify('NOTIFY_INSTANT_SEARCH_TYPESENSE_BEFORE_RESULTS', $queryText, $typesenseResults, $this->results);

        return $this->results;
    }
}
