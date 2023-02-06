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

use Typesense\Exceptions\ConfigError;
use Zencart\Plugins\Catalog\InstantSearch\Exceptions\InstantSearchEngineInitException;
use Zencart\Plugins\Catalog\InstantSearch\InstantSearch;
use Zencart\Plugins\Catalog\InstantSearch\SearchEngineProviders\SearchEngineProviderInterface;
use Zencart\Plugins\Catalog\Typesense\SearchEngineProviders\TypesenseSearchEngineProvider;

class TypesenseInstantSearch extends InstantSearch
{
    /**
     * Constructor.
     * @throws InstantSearchEngineInitException
     */
    public function __construct()
    {
        try {
            parent::__construct();
        } catch (\Exception $e) {
            throw new InstantSearchEngineInitException("Error while initializing Typesense, check the configuration parameters", 0, $e);
        }
    }

    /**
     * Factory method that returns the Typesense Search engine provider.
     *
     * @return SearchEngineProviderInterface
     * @throws ConfigError
     */
    public function getSearchEngineProvider(): SearchEngineProviderInterface
    {
        return new TypesenseSearchEngineProvider();
    }
}
