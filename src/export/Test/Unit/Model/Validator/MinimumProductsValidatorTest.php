<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Test\Unit\Model\Validator;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Config\SanityCheckConfig;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Validator\MinimumProductsValidator;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Aligent_Unit
 */
class MinimumProductsValidatorTest extends TestCase
{
    /**
     * @var MinimumProductsValidator
     */
    private MinimumProductsValidator $minimumProductsValidator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $sanityCheckConfig = $this->createMock(SanityCheckConfig::class);
        $sanityCheckConfig->method('getMinTotalProducts')->willReturn(100);
        $this->minimumProductsValidator = new MinimumProductsValidator($sanityCheckConfig);
    }

    /**
     * Test state validation
     *
     * @dataProvider exportProvider
     *
     * @param ExportInterface $export
     * @param bool $isValid
     * @return void
     */
    public function testValidateState(ExportInterface $export, bool $isValid): void
    {
        $totalProducts = $export->getProductCount();
        if (!$isValid) {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage(
                'Total number of products (' . $totalProducts . ') does not meet threshold (100)'
            );
            $this->minimumProductsValidator->validateState($export);
        } else {
            try {
                $this->minimumProductsValidator->validateState($export);
                $this->assertTrue(true);
            } catch (ValidationException) {
                $this->fail('Validation failed when it should have succeeded');
            }

        }
    }

    /**
     * Data provider for export validation
     *
     * @return array[]
     */
    public function exportProvider(): array
    {
        return [
            'Valid export' => [
                $this->getExportMock(101, ExportInterface::EXPORT_TYPE_FULL),
                true
            ],
            'Min products for valid' => [
                $this->getExportMock(100, ExportInterface::EXPORT_TYPE_FULL),
                true
            ],
            'Invalid export' => [
                $this->getExportMock(99, ExportInterface::EXPORT_TYPE_FULL),
                false
            ],
            'No check for incremental export' => [
                $this->getExportMock(0, ExportInterface::EXPORT_TYPE_INCREMENTAL),
                true
            ]
        ];
    }

    /**
     * Get mock object for Export
     *
     * @param int $totalProductCount
     * @param string $exportType
     * @return Export|MockObject
     */
    private function getExportMock(int $totalProductCount, string $exportType): Export|MockObject
    {
        $export = $this->createMock(Export::class);
        $export->method('getExportType')->willReturn($exportType);
        $export->method('getProductCount')->willReturn($totalProductCount);
        return $export;
    }
}
