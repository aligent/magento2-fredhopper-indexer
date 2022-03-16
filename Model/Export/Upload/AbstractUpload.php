<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Upload;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface as FilesystemDriverInterface;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;
use Laminas\Http\Client;
use Laminas\Http\Request;

abstract class AbstractUpload
{
    protected const FAS_ENDPOINT = 'fas';
    protected const SUGGEST_ENDPOINT = 'suggest';
    protected const FAS_TRIGGER_ENDPOINT = 'load-data';
    protected const SUGGEST_TRIGGER_ENDPOINT = 'generate';

    private Client $httpClient;
    private GeneralConfig $generalConfig;
    private FilesystemDriverInterface $filesystemDriver;
    private File $file;
    private LoggerInterface $logger;

    private bool $dryRun = false;

    public function __construct(
        Client $httpClient,
        GeneralConfig $generalConfig,
        File $file,
        FilesystemDriverInterface $filesystemDriver,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->generalConfig = $generalConfig;
        $this->file = $file;
        $this->filesystemDriver = $filesystemDriver;
        $this->logger = $logger;
    }

    /**
     * @param bool $isDryRun
     * @return void
     */
    public function setDryRun(bool $isDryRun): void
    {
        $this->dryRun = $isDryRun;
    }

    /**
     * @param string $zipFilePath
     * @return bool
     * @throws FileSystemException
     */
    public function uploadZipFile(string $zipFilePath): bool
    {
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Uploading zip file: $zipFilePath");
        }
        $zipContent = $this->filesystemDriver->fileGetContents($zipFilePath);
        // md5 used for checksum, not for hashing password or secret information
        // phpcs:ignore Magento2.Security.InsecureFunction.FoundWithAlternative
        $checksum = md5($zipContent);
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Checksum of file: $checksum");
        }
        $url = $this->getUploadUrl($zipFilePath);
        $parameters = [
            'headers' => [
                'Content-Type: application/zip'
            ],
            'body' => $zipContent,
            'query' => [
                'checksum' => $checksum
            ]
        ];
        $request = $this->generateRequest($url, $parameters);

        $response = $this->sendRequest($request);
        if (!$response) {
            return false;
        }

        // get data id from the response body
        $dataIdString = $response['body'];
        if ($dataIdString) {
            return $this->triggerDataLoad($dataIdString);
        }

        return false;
    }

    /**
     * @param $dataIdString
     * @return bool
     */
    private function triggerDataLoad($dataIdString): bool
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
        $request = $this->generateRequest($this->getTriggerUrl(), $parameters);
        $response = $this->sendRequest($request);
        // check that the data load was triggered correctly
        return (isset($response['status_code']) && $response['status_code'] == 201);
    }

    /**
     * @param string $url
     * @param array $parameters
     * @return Request
     */
    private function generateRequest(string $url, array $parameters): Request
    {
        $request = $this->httpClient->getRequest();
        $request->setMethod(Request::METHOD_PUT);
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

    private function getUploadUrl($filePath): string
    {
        $fileInfo = $this->file->getPathInfo($filePath);
        $fileName = $fileInfo['basename'];
        return $this->getBaseUrl() . '/data/input/' .$fileName;
    }

    private function getTriggerUrl(): string
    {
        return $this->getBaseUrl() . '/trigger/' . $this->getFredhopperTriggerEndpoint();
    }

    private function getBaseUrl(): string
    {
        return 'https://my.' . $this->generalConfig->getEndpointName() . '.fredhopperservices.com/' .
            $this->getFredhopperUploadEndpoint() .':' . $this->generalConfig->getEnvironmentName();
    }

    /**
     * @return string
     */
    abstract protected function getFredhopperUploadEndpoint() : string;

    /**
     * @return string
     */
    abstract protected function getFredhopperTriggerEndpoint() : string;

    /**
     * @param $request
     * @return array|false
     */
    private function sendRequest($request)
    {
        if ($this->dryRun) {
            $this->logger->info("Dry run; not exporting");
            return false;
        }
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
            'body' => $response->getBody()
        ];
    }

    /**
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
