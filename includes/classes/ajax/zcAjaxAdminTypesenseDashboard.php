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
     * Gets the Typesense collections.
     *
     * @return array
     */
    public function getCollections(): array
    {
        try {
            return $this->client->collections->retrieve();
        } catch (Exception|\Http\Client\Exception $e) {
            $this->logger->writeErrorLog("Error while retrieving Typesense collections", $e);
            http_response_code(500);
            exit();
        }
    }
}
