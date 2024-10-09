<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Test\Unit\Model\Validator;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Config\SanityCheckConfig;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Validator\DeletedProductsValidator;
use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group Aligent_Unit
 */
class DeletedProductsValidatorTest extends TestCase
{

    private DeletedProductsValidator $deletedProductsValidator;

    protected function setUp(): void
    {
        $sanityCheckConfig = $this->createMock(SanityCheckConfig::class);
        $sanityCheckConfig->method('getMaxDeleteProducts')->willReturn(100);
        $this->deletedProductsValidator = new DeletedProductsValidator($sanityCheckConfig);
    }

    /**
     * @dataProvider exportProvider
     *
     * @param ExportInterface $export
     * @param bool $isValid
     * @return void
     */
    public function testValidateState(ExportInterface $export, bool $isValid): void
    {
        $deletedProducts = $export->getProductDeleteCount();
        if (!$isValid) {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage(
                'Number of deleted products (' . $deletedProducts . ') exceeds threshold (100)'
            );
            $this->deletedProductsValidator->validateState($export);
        } else {
            try {
                $this->deletedProductsValidator->validateState($export);
                $this->assertTrue(true);
            } catch (ValidationException) {
                $this->fail('Validation failed when it should have succeeded');
            }

        }
    }

    public function exportProvider(): array
    {
        return [
            'Valid export' => [$this->getExportMock(1), true],
            'Max deletes for valid' => [$this->getExportMock(100), true],
            'Invalid export' => [$this->getExportMock(101), false]
        ];
    }

    private function getExportMock(int $deletedProductCount): Export|MockObject
    {
        $export = $this->createMock(Export::class);
        $export->method('getProductDeleteCount')->willReturn($deletedProductCount);
        return $export;
    }
}
