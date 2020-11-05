<?php
namespace Aligent\FredhopperIndexer\Model\Export\Upload;

abstract class AbstractUpload
{
    protected const FAS_ENDPOINT = 'fas';
    protected const SUGGEST_ENDPOINT = 'suggest';
    protected const FAS_TRIGGER_ENDPOINT = 'load-data';
    protected const SUGGEST_TRIGGER_ENDPOINT = 'generate';

    /**
     * @var \Zend\Http\Client
     */
    protected $httpClient;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\GeneralConfig
     */
    protected $generalConfig;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Zend\Http\Client $httpClient,
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $generalConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->generalConfig = $generalConfig;
        $this->logger = $logger;
    }

    public function uploadZipFile($zipFilePath)
    {
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Uploading zip file: {$zipFilePath}");
        }
        $zipContent = file_get_contents($zipFilePath);
        $checksum = md5($zipContent);
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Checksum of file: {$checksum}");
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

    protected function triggerDataLoad($dataIdString)
    {
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Triggering load of data: {$dataIdString}");
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

    protected function generateRequest($url, $parameters, $method = \Zend\Http\Request::METHOD_PUT)
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

    protected function getUploadUrl($filePath)
    {
        $fileName = basename($filePath);
        return $this->getBaseUrl() . '/data/input/' .$fileName;
    }

    protected function getTriggerUrl()
    {
        return $this->getBaseUrl() . '/trigger/' . $this->getFredhopperTriggerEndpoint();
    }

    protected function getBaseUrl()
    {
        return 'https://my.' . $this->generalConfig->getEndpointName() . '.fredhopperservices.com/' .
            $this->getFredhopperUploadEndpoint() .':' . $this->generalConfig->getEnvironmentName();
    }

    /**
     * @return string
     */
    protected abstract function getFredhopperUploadEndpoint() : string;

    /**
     * @return string
     */
    protected abstract function getFredhopperTriggerEndpoint() : string;

    protected function sendRequest($request)
    {
        $auth = $this->getAuth();
        $this->httpClient->setAuth($auth['username'], $auth['password']);

        $response = $this->httpClient->send($request);
        if ($this->generalConfig->getDebugLogging()) {
            $this->logger->debug("Request response:\n {$response}");
        }
        // clear client for next request
        $this->httpClient->reset();
        return [
            'status_code' => $response->getStatusCode(),
            'body' => $response->getBody()
        ];
    }

    protected function getAuth()
    {
        return [
            'username' => $this->generalConfig->getUsername(),
            'password' => $this->generalConfig->getPassword()
        ];
    }
}
