<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Magento\Framework\Model\AbstractModel;

class Export extends AbstractModel implements ExportInterface
{

    /**
     * @inheritDoc
     */
    public function getExportId(): int
    {
        return (int)$this->getData(self::FIELD_EXPORT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setExportId(int $exportId): void
    {
        $this->setData(self::FIELD_EXPORT_ID, $exportId);
    }

    /**
     * @inheritDoc
     */
    public function getExportType(): string
    {
        return (string)$this->getData(self::FIELD_EXPORT_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setExportType(string $exportType): void
    {
        $this->setData(self::FIELD_EXPORT_TYPE, $exportType);
    }

    /**
     * @inheritDoc
     */
    public function getProductCount(): int
    {
        return (int)$this->getData(self::FIELD_PRODUCT_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setProductCount(int $productCount): void
    {
        $this->setData(self::FIELD_PRODUCT_COUNT, $productCount);
    }

    /**
     * @inheritDoc
     */
    public function getVariantCount(): int
    {
        return (int)$this->getData(self::FIELD_VARIANT_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setVariantCount(int $variantCount): void
    {
        $this->setData(self::FIELD_VARIANT_COUNT, $variantCount);
    }

    /**
     * @inheritDoc
     */
    public function getProductAddCount(): ?int
    {
        $count = $this->getData(self::FIELD_PRODUCT_ADD_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setProductAddCount(int $productAddCount): void
    {
        $this->setData(self::FIELD_PRODUCT_ADD_COUNT, $productAddCount);
    }

    /**
     * @inheritDoc
     */
    public function getProductUpdateCount(): ?int
    {
        $count = $this->getData(self::FIELD_PRODUCT_UPDATE_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setProductUpdateCount(int $productUpdateCount): void
    {
        $this->setData(self::FIELD_PRODUCT_UPDATE_COUNT, $productUpdateCount);
    }

    /**
     * @inheritDoc
     */
    public function getProductDeleteCount(): ?int
    {
        $count = $this->getData(self::FIELD_PRODUCT_DELETE_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setProductDeleteCount(int $productDeleteCount): void
    {
        $this->setData(self::FIELD_PRODUCT_DELETE_COUNT, $productDeleteCount);
    }

    /**
     * @inheritDoc
     */
    public function getVariantAddCount(): ?int
    {
        $count = $this->getData(self::FIELD_VARIANT_ADD_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setVariantAddCount(int $variantAddCount): void
    {
        $this->setData(self::FIELD_VARIANT_ADD_COUNT, $variantAddCount);
    }

    /**
     * @inheritDoc
     */
    public function getVariantUpdateCount(): ?int
    {
        $count = $this->getData(self::FIELD_VARIANT_UPDATE_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setVariantUpdateCount(int $variantUpdateCount): void
    {
        $this->setData(self::FIELD_VARIANT_UPDATE_COUNT, $variantUpdateCount);
    }

    /**
     * @inheritDoc
     */
    public function getVariantDeleteCount(): ?int
    {
        $count = $this->getData(self::FIELD_VARIANT_DELETE_COUNT);
        return $count ? (int)$count : null;
    }

    /**
     * @inheritDoc
     */
    public function setVariantDeleteCount(int $variantDeleteCount): void
    {
        $this->setData(self::FIELD_VARIANT_DELETE_COUNT, $variantDeleteCount);
    }

    /**
     * @inheritDoc
     */
    public function getDirectory(): string
    {
        return (string)$this->getData(self::FIELD_DIRECTORY);
    }

    /**
     * @inheritDoc
     */
    public function setDirectory(string $directory): void
    {
        $this->setData(self::FIELD_DIRECTORY, $directory);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return (string)$this->getData(self::FIELD_CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): void
    {
        $this->setData(self::FIELD_CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return (string)$this->getData(self::FIELD_UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): void
    {
        $this->setData(self::FIELD_CREATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string)$this->getData(self::FIELD_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): void
    {
        $this->setData(self::FIELD_STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getDataStatus(): ?string
    {
        return $this->getData(self::FIELD_DATA_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setDataStatus(string $dataStatus): void
    {
        $this->setData(self::FIELD_DATA_STATUS, $dataStatus);
    }

    /**
     * @inheritDoc
     */
    public function getError(): ?string
    {
        return $this->getData(self::FIELD_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function setError(string $error): void
    {
        $this->setData(self::FIELD_ERROR, $error);
    }

    /**
     * @inheritDoc
     */
    public function getDataId(): ?string
    {
        return $this->getData(self::FIELD_DATA_ID);
    }

    /**
     * @inheritDoc
     */
    public function setDataId(string $dataId): void
    {
        $this->setData(self::FIELD_DATA_ID, $dataId);
    }

    /**
     * @inheritDoc
     */
    public function getVersionId(): int
    {
        return $this->getData(self::FIELD_VERSION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setVersionId(int $versionId): void
    {
        $this->setData(self::FIELD_VERSION_ID, $versionId);
    }
}
