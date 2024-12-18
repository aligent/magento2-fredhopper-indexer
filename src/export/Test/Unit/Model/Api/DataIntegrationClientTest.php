<?php

namespace Aligent\FredhopperExport\Test\Unit\Model\Api;

use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Laminas\Http\Client;
use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Stdlib\Parameters;
use Magento\Framework\Filesystem\Driver\File as FilesystemDriver;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Exception\FileSystemException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @group Aligent_Unit
 */
class DataIntegrationClientTest extends TestCase
{
    /**
     * @var FilesystemDriver|MockObject
     */
    private FilesystemDriver|MockObject $filesystemDriverMock;
    /**
     * @var File|MockObject
     */
    private File|MockObject $fileMock;
    /**
     * @var Client|MockObject
     */
    private Client|MockObject $httpClientMock;
    /**
     * @var DataIntegrationClient|null
     */
    private ?DataIntegrationClient $dataIntegrationClient = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $generalConfigMock = $this->createMock(GeneralConfig::class);
        $this->filesystemDriverMock = $this->createMock(FilesystemDriver::class);
        $this->fileMock = $this->createMock(File::class);
        $this->httpClientMock = $this->createMock(Client::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->any())->method('info');

        $this->dataIntegrationClient = new DataIntegrationClient(
            $generalConfigMock,
            $this->filesystemDriverMock,
            $this->fileMock,
            $this->httpClientMock,
            $loggerMock
        );
    }

    /**
     * Test uploading FAS data
     *
     * @dataProvider uploadDataProvider
     *
     * @param \Throwable|null $fileGetContentsException
     * @param int $httpStatus
     * @param string|null $expectedResult
     */
    public function testUploadFasData(
        ?\Throwable $fileGetContentsException,
        int $httpStatus,
        ?string $expectedResult
    ): void {
        $zipFilePath = '/path/to/file.zip';

        $this->setupMocksForUpload($fileGetContentsException, $httpStatus, $zipFilePath, $expectedResult);

        $actualResult = $this->dataIntegrationClient->uploadFasData($zipFilePath);
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test uploading Suggest Data
     *
     * @dataProvider uploadDataProvider
     *
     * @param \Throwable|null $fileGetContentsException
     * @param int $httpStatus
     * @param string|null $expectedResult
     */
    public function testUploadSuggestData(
        ?\Throwable $fileGetContentsException,
        int $httpStatus,
        ?string $expectedResult
    ): void {
        $zipFilePath = '/path/to/file.zip';

        $this->setupMocksForUpload($fileGetContentsException, $httpStatus, $zipFilePath, $expectedResult);

        $actualResult = $this->dataIntegrationClient->uploadSuggestData($zipFilePath);
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test triggering FAS data load
     *
     * @dataProvider dataLoadProvider
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     */
    public function testTriggerFasDataLoad(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupMocksForTriggerDataLoad($httpStatus, $expectedResult);

        $actualResult = $this->dataIntegrationClient->triggerFasDataLoad('anyDataId');
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test triggering of Suggest data load
     *
     * @dataProvider dataLoadProvider
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    public function testTriggerSuggestDataLoad(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupMocksForTriggerDataLoad($httpStatus, $expectedResult);

        $actualResult = $this->dataIntegrationClient->triggerSuggestDataLoad('anyDataId');
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test getting FAS data status
     *
     * @dataProvider statusProvider
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    public function testGetFasDataStatus(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupMocksForStatus($httpStatus, $expectedResult);

        $actualResult = $this->dataIntegrationClient->getFasDataStatus('anyTriggerId');
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test getting suggest data status
     *
     * @dataProvider statusProvider
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    public function testGetSuggestDataStatus(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupMocksForStatus($httpStatus, $expectedResult);

        $actualResult = $this->dataIntegrationClient->getSuggestDataStatus('anyTriggerId');
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Test getting data quality report
     *
     * @dataProvider qualityReportProvider
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    public function testGetDataQualityReport(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupMocksForDataQualityReport($httpStatus, $expectedResult);

        $actualResult = $this->dataIntegrationClient->getDataQualityReport('anyTriggerId');
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Setup mock objects
     *
     * @param \Throwable|null $fileGetContentsException
     * @param int $httpStatus
     * @param string $zipFilePath
     * @param string|null $expectedResult
     * @return void
     */
    private function setupMocksForUpload(
        ?\Throwable $fileGetContentsException,
        int $httpStatus,
        string $zipFilePath,
        ?string $expectedResult
    ): void {
        $this->fileMock->expects($this->any())->method('getPathInfo')->with($this->equalTo($zipFilePath))
            ->willReturn(['basename' => 'file.zip']);
        $fileGetContentsMethod = $this->filesystemDriverMock->expects($this->once())->method('fileGetContents')
            ->with($this->equalTo($zipFilePath));

        $mockBody = 'mocked content';
        if ($fileGetContentsException) {
            $fileGetContentsMethod->willThrowException($fileGetContentsException);
        } else {
            $fileGetContentsMethod->willReturn($mockBody);
        }

        $this->setupClientMock($httpStatus, $expectedResult);
    }

    /**
     * Setup mock objects
     *
     * @param int $httpStatus
     * @param string|null $triggerId
     * @return void
     */
    private function setupMocksForTriggerDataLoad(int $httpStatus, ?string $triggerId): void
    {
        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn($httpStatus);
        $response->method('getHeaders')->willReturn(
            ['location' => 'https://my.eu1.fredhopperservices.com/fas:test1/trigger/load-data/' . $triggerId]
        );
        $requestMock = $this->getRequestMock();
        $this->httpClientMock->expects($this->any())->method('getRequest')->willReturn($requestMock);

        $this->httpClientMock->expects($this->once())->method('send')->willReturn($response);
    }

    /**
     * Setup mock objects
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    private function setupMocksForStatus(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupClientMock($httpStatus, $expectedResult);
    }

    /**
     * Setup mock objects
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    private function setupMocksForDataQualityReport(int $httpStatus, ?string $expectedResult): void
    {
        $this->setupClientMock($httpStatus, $expectedResult);
    }

    /**
     * Setup client mock object
     *
     * @param int $httpStatus
     * @param string|null $expectedResult
     * @return void
     */
    private function setupClientMock(int $httpStatus, ?string $expectedResult): void
    {
        $requestMock = $this->getRequestMock();
        $this->httpClientMock->expects($this->any())->method('getRequest')->willReturn($requestMock);

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn($httpStatus);
        $response->method('getBody')->willReturn((string)$expectedResult);

        $this->httpClientMock->expects($this->any())->method('send')->willReturn($response);
    }

    /**
     * Get mock object for Request
     *
     * @return Request|MockObject
     */
    private function getRequestMock(): Request|MockObject
    {
        $requestMock = $this->createMock(Request::class);
        $headersMock = $this->createMock(Headers::class);
        $requestMock->method('getHeaders')->willReturn($headersMock);

        $parametersMock = $this->createMock(Parameters::class);
        $requestMock->method('getQuery')->willReturn($parametersMock);
        return $requestMock;
    }

    /**
     * Data provider for upload
     *
     * @return array[]
     */
    public function uploadDataProvider(): array
    {
        return [
            'Successful upload' => [null, 200, 'dataId'],
            'Failed to read file' => [new FileSystemException(__('Failed to read file.')), 200, null],
            'HTTP status not 2xx' => [null, 400, null],
        ];
    }

    /**
     * Data provider for data load trigger
     *
     * @return array[]
     */
    public function dataLoadProvider(): array
    {
        return [
            'Successful trigger data load' => [200, 'triggerId'],
            'HTTP status not 2xx' => [400, null],
        ];
    }

    /**
     * Data provider for status check
     *
     * @return array[]
     */
    public function statusProvider(): array
    {
        return [
            'Successful status check' => [200, 'Pending'],
            'HTTP status not 2xx' => [400, null]
        ];
    }

    /**
     * Data provider for fetching quality report
     *
     * @return array[]
     */
    public function qualityReportProvider(): array
    {
        return [
            'Successful report' => [200, 'report content'],
            'HTTP status not 2xx' => [400, null]
        ];
    }
}
