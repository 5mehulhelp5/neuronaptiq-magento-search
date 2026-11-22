<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\Serializer\Json;
use NeuronaptiQ\Search\Api\TrackingApiClientInterface;
use NeuronaptiQ\Search\Exception\SearchApiException;
use NeuronaptiQ\Search\Model\Config;
use NeuronaptiQ\Search\Model\CustomerDataProvider;
use NeuronaptiQ\Search\Model\OriginProvider;
use NeuronaptiQ\Search\Model\Session\SidManager;
use Psr\Log\LoggerInterface;

/**
 * REST JSON Client for the tracking endpoint of the NeuronaptiQ Search Service
 *
 * @phpstan-import-type Conversion from TrackingApiClientInterface
 */
class TrackingApiClient implements TrackingApiClientInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var SidManager
     */
    private SidManager $sidManager;

    /**
     * @var RemoteAddress
     */
    private RemoteAddress $remoteAddress;

    /**
     * @var Header
     */
    private Header $httpHeader;

    /**
     * @var CustomerDataProvider
     */
    private CustomerDataProvider $customerDataProvider;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var OriginProvider
     */
    private OriginProvider $originProvider;

    /**
     * @param Config $config
     * @param Curl $curl
     * @param Json $jsonSerializer
     * @param SidManager $sidManager
     * @param RemoteAddress $remoteAddress
     * @param Header $httpHeader
     * @param CustomerDataProvider $customerDataProvider
     * @param LoggerInterface $logger
     * @param OriginProvider $originProvider
     */
    public function __construct(
        Config $config,
        Curl $curl,
        Json $jsonSerializer,
        SidManager $sidManager,
        RemoteAddress $remoteAddress,
        Header $httpHeader,
        CustomerDataProvider $customerDataProvider,
        LoggerInterface $logger,
        OriginProvider $originProvider
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->jsonSerializer = $jsonSerializer;
        $this->sidManager = $sidManager;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->customerDataProvider = $customerDataProvider;
        $this->logger = $logger;
        $this->originProvider = $originProvider;
    }

    /**
     * @inheritDoc
     */
    public function track(array $conversion, ?int $storeId = null): void
    {
        $this->send($this->buildTrackRequestBody($conversion), $storeId);
    }

    /**
     * @inheritDoc
     */
    public function trackCustomer(?int $storeId = null): bool
    {
        $body = $this->buildTrackRequestBody(null);
        if (!isset($body['customer'])) {
            return false;
        }

        $this->send($body, $storeId);
        return true;
    }

    /**
     * Sends the request body to the tracking endpoint and stores the returned SID
     *
     * @param array $body
     * @param int|null $storeId
     * @return void
     * @phpstan-param array<string, mixed> $body
     * @throws SearchApiException
     */
    private function send(array $body, ?int $storeId): void
    {
        $endpoint = trim($this->config->getTrackApiEndpoint($storeId));
        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new SearchApiException('Tracking API endpoint is not configured or invalid.');
        }

        $token = $this->config->getToken($storeId);
        if ($token === '') {
            throw new SearchApiException('API token is not configured.');
        }

        try {
            $jsonPayload = $this->jsonSerializer->serialize($body);

            $this->curl->setTimeout($this->config->getTimeout($storeId));
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->addHeader('Authorization', 'Bearer ' . $token);

            $this->logger->debug(
                sprintf('[NeuronaptiQ_Search] Track request to %s: %s', $endpoint, $jsonPayload)
            );

            $this->curl->post($endpoint, $jsonPayload);

            $statusCode = $this->curl->getStatus();
            $responseBody = (string)$this->curl->getBody();

            $this->logger->debug(
                sprintf('[NeuronaptiQ_Search] Track response (HTTP %s): %s', $statusCode, $responseBody)
            );
        } catch (\Throwable $e) {
            throw new SearchApiException(sprintf('Error querying Tracking API: %s', $e->getMessage()), 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new SearchApiException(
                sprintf('Tracking API returned HTTP %s (see debug log for the response body)', $statusCode)
            );
        }

        $this->storeSid($responseBody);
    }

    /**
     * Builds the JSON request payload for the tracking endpoint
     *
     * Without conversion (e.g. customer login) the "conversion" field is omitted.
     *
     * @param array|null $conversion
     * @return array<string, mixed>
     * @phpstan-param Conversion|null $conversion
     */
    public function buildTrackRequestBody(?array $conversion): array
    {
        $body = [
            'remote' => [
                'remoteAddr' => (string)($this->remoteAddress->getRemoteAddress() ?: '127.0.0.1'),
                'userAgent' => (string)($this->httpHeader->getHttpUserAgent() ?: '')
            ],
            'origin' => $this->originProvider->getOrigin(),
            'sid' => $this->sidManager->getSid()
        ];

        if ($conversion !== null) {
            $body = ['conversion' => $conversion] + $body;
        }

        $customerData = $this->customerDataProvider->getCustomerData();
        if ($customerData !== null) {
            $body['customer'] = $customerData;
        }

        return $body;
    }

    /**
     * Stores the session id returned by the API
     *
     * @param string $responseBody
     * @return void
     */
    private function storeSid(string $responseBody): void
    {
        try {
            $response = $this->jsonSerializer->unserialize($responseBody);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('[NeuronaptiQ_Search] Tracking API returned an invalid response body.');
            return;
        }

        if (is_array($response) && !empty($response['sid']) && is_string($response['sid'])) {
            $this->sidManager->setSid($response['sid']);
        }
    }
}
