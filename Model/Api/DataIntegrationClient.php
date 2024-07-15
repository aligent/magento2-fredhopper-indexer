<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Api;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Laminas\Uri\UriFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FilesystemDriver;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

class DataIntegrationClient
{

    private const ENDPOINT_FAS = 'fas';
    private const ENDPOINT_SUGGEST = 'suggest';

    private const UPLOAD_ENDPOINT = 'data/input/%s?';
    private const FAS_TRIGGER_ENDPOINT = 'trigger/load-data';
    private const FAS_MONITOR_ENDPOINT = 'trigger/load-data/%s/status';
    private const FAS_DATA_QUALITY_ENDPOINT = 'trigger/analyze/%s-fas_load-data/logs/data-quality-summary-report.txt';
    private const SUGGEST_TRIGGER_ENDPOINT = 'trigger/generate';
    private const SUGGEST_MONITOR_ENDPOINT = 'trigger/generate/%s%/status';

    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly FilesystemDriver $filesystemDriver,
        private readonly File $file,
        private readonly Client $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Upload fas zip file to Fredhopper
     *
     * @param string $zipFilePath
     * @return string|null
     */
    public function uploadFasData(string $zipFilePath): ?string
    {
        return $this->uploadData($zipFilePath, self::ENDPOINT_FAS);
    }

    /**
     * Upload a suggest zip file to Fredhopper
     *
     * @param string $zipFilePath
     * @return string|null
     */
    public function uploadSuggestData(string $zipFilePath): ?string
    {
        return $this->uploadData($zipFilePath, self::ENDPOINT_SUGGEST);
    }

    /**
     * Upload a zip file to Fredhopper
     *
     * @param string $zipFilePath
     * @param string $endpoint
     * @return string|null
     */
    private function uploadData(string $zipFilePath, string $endpoint): ?string
    {
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Uploading zip file: $zipFilePath");
        }

        $fileInfo = $this->file->getPathInfo($zipFilePath);
        $fileName = $fileInfo['basename'];
        try {
            $zipContent = $this->filesystemDriver->fileGetContents($zipFilePath);
        } catch (FileSystemException $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            return null;
        }
        // md5 used for checksum, not for hashing password or secret information
        // phpcs:ignore Magento2.Security.InsecureFunction.FoundWithAlternative
        $checksum = md5($zipContent);
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Checksum of file: $checksum");
        }
        $uri = $this->getBaseUrl($endpoint) . sprintf(self::UPLOAD_ENDPOINT, $fileName);
        $parameters = [
            'headers' => [
                'Content-Type: application/zip'
            ],
            'body' => $zipContent,
            'query' => [
                'checksum' => $checksum
            ]
        ];
        $request = $this->generateRequest($uri, $parameters, Request::METHOD_PUT);

        $response = $this->sendRequest($request);
        if (!$response) {
            return null;
        }

        // Treat any response code other than 2xx as an error
        $statusString = (string)$response['status_code'];
        if (strlen($statusString) < 1 || $statusString[0] !== '2') {
            $this->logger->error("HTTP error: $statusString");
            return null;
        }

        // data id will be the response body
        return $response['body'] ?? null;
    }

    /**
     * Trigger the loading of fas data matching the data id within Fredhopper
     *
     * @param string $dataId
     * @return string
     */
    public function triggerFasDataLoad(string $dataId): string
    {
        return $this->triggerDataLoad($dataId, self::ENDPOINT_FAS);
    }

    /**
     * Trigger the loading of data matching the data id within Fredhopper
     *
     * @param string $dataId
     * @return string
     */
    public function triggerSuggestDataLoad(string $dataId): string
    {
        return $this->triggerDataLoad($dataId, self::ENDPOINT_SUGGEST);
    }

    /**
     * Trigger loading data within Fredhopper
     *
     * @param string $dataIdString
     * @param string $endpoint
     * @return string|null
     */
    private function triggerDataLoad(string $dataIdString, string $endpoint): ?string
    {
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Triggering load of data: $dataIdString");
        }
        $parameters = [
            'headers' => [
                'Content-Type: text/plain'
            ],
            'body' => $dataIdString
        ];
        $triggerUrl = $this->getBaseUrl($endpoint);
        if ($endpoint === self::ENDPOINT_FAS) {
            $triggerUrl = $triggerUrl . self::FAS_TRIGGER_ENDPOINT;
        } else {
            $triggerUrl = $triggerUrl . self::SUGGEST_TRIGGER_ENDPOINT;
        }
        $request = $this->generateRequest($triggerUrl, $parameters, Request::METHOD_PUT);
        $response = $this->sendRequest($request);
        // check that the data load was triggered correctly
        $location = $response['headers']['location'] ?? null;
        if ($location === null) {
            return null;
        }
        // return the path of the location URL - this is the trigger id
        $locationUri = UriFactory::factory($location);
        return ltrim('/', $locationUri->getPath());
    }

    /**
     * Gets the status of the fas data associated with the given trigger within Fredhopper
     *
     * @param string $triggerId
     * @return string|null
     */
    public function getFasDataStatus(string $triggerId): ?string
    {
        return $this->getStatus($triggerId, self::ENDPOINT_FAS);
    }

    /**
     * Gets the status of the suggest data associated with the given trigger within Fredhopper
     *
     * @param string $triggerId
     * @return string|null
     */
    public function getSuggestDataStatus(string $triggerId): ?string
    {
        return $this->getStatus($triggerId, self::ENDPOINT_SUGGEST);
    }

    /**
     * Get data load status from Fredhopper
     *
     * @param string $triggerId
     * @param string $endpoint
     * @return string|null
     */
    private function getStatus(string $triggerId, string $endpoint): ?string
    {
        $statusUrl = $this->getBaseUrl($endpoint);
        if ($endpoint === self::ENDPOINT_FAS) {
            $statusUrl = $statusUrl . sprintf(self::FAS_MONITOR_ENDPOINT, $triggerId);
        } else {
            $statusUrl = $statusUrl . sprintf(self::SUGGEST_MONITOR_ENDPOINT, $triggerId);
        }
        $request = $this->generateRequest($statusUrl, [], Request::METHOD_GET);
        $response = $this->sendRequest($request);

        // status will be the response body
        return $response['body'] ?? null;
    }

    /**
     * Get the data quality summary report content from Fredhopper
     *
     * @param string $triggerId
     * @return string|null
     */
    public function getDataQualityReport(string $triggerId): ?string
    {
        $dataQualityUrl = sprintf(self::FAS_DATA_QUALITY_ENDPOINT, $triggerId);
        $request = $this->generateRequest($dataQualityUrl, [], Request::METHOD_GET);
        $response = $this->sendRequest($request);
        return $response['body'] ?? null;
    }

    /**
     * Generate an HTTP request
     *
     * @param string $url
     * @param array $parameters
     * @param string $method
     * @return Request
     */
    private function generateRequest(string $url, array $parameters, string $method): Request
    {
        $request = $this->httpClient->getRequest();
        $request->setMethod($method);
        $request->setUri($url);
        if (isset($parameters['headers'])) {
            $headers = $request->getHeaders();
            $headers->addHeaders($parameters['headers']);
            $request->setHeaders($headers);
        }
        if (isset($parameters['body'])) {
            $request->setContent($parameters['body']);
        }
        if (isset($parameters['query'])) {
            $queryParams = $request->getQuery();
            foreach ($parameters['query'] as $name => $value) {
                $queryParams->set($name, $value);
            }
            $request->setQuery($queryParams);
        }
        return $request;
    }

    /**
     * Sends the given request
     *
     * @param Request $request
     * @return array
     */
    private function sendRequest(Request $request): array
    {
        $auth = $this->getAuth();
        $this->httpClient->setAuth($auth['username'], $auth['password']);

        $response = $this->httpClient->send($request);

        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Request response:\n $response");
        }
        // clear client for next request
        $this->httpClient->reset();
        return [
            'status_code' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $response->getBody()
        ];
    }

    private function getBaseUrl(string $endpoint): string
    {
        return 'https://my.' . $this->generalConfig->getEndpointName() . '.fredhopperservices.com/'.
            $endpoint . ':' . $this->generalConfig->getEnvironmentName() . '/';

    }

    /**
     * Get authorisation credentials
     *
     * @return array
     */
    private function getAuth(): array
    {
        return [
            'username' => $this->generalConfig->getUsername(),
            'password' => $this->generalConfig->getPassword()
        ];
    }
}
