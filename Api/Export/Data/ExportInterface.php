<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Export\Data;

interface ExportInterface
{
    public const FIELD_EXPORT_ID = 'export_id';
    public const FIELD_EXPORT_TYPE = 'export_type';
    public const FIELD_PRODUCT_COUNT = 'product_count';
    public const FIELD_VARIANT_COUNT = 'variant_count';
    public const FIELD_PRODUCT_ADD_COUNT = 'product_add_count';
    public const FIELD_PRODUCT_UPDATE_COUNT = 'product_update_count';
    public const FIELD_PRODUCT_DELETE_COUNT = 'product_delete_count';
    public const FIELD_VARIANT_ADD_COUNT = 'variant_add_count';
    public const FIELD_VARIANT_UPDATE_COUNT = 'variant_update_count';
    public const FIELD_VARIANT_DELETE_COUNT = 'variant_delete_count';
    public const FIELD_DIRECTORY = 'directory';
    public const FIELD_CREATED_AT = 'created_at';
    public const FIELD_STATUS = 'status';
    public const FIELD_ERROR = 'error';
    public const FIELD_DATA_ID = 'data_id';
    public const FIELD_VERSION_ID = 'version_id';

    public const EXPORT_TYPE_FULL = 'f';
    public const EXPORT_TYPE_INCREMENTAL = 'i';

    public const STATUS_PENDING = 'p';
    public const STATUS_COMPLETE = 'c';
    public const STATUS_ERROR = 'e';
    public const STATUS_INVALID = 'i';

    /**
     * Retrieves the export ID
     *
     * @return int
     */
    public function getExportId(): int;

    /**
     * Sets the export ID
     *
     * @param int $exportId
     * @return void
     */
    public function setExportId(int $exportId): void;

    /**
     * Retrieves the export type for the given data.
     *
     * @return string
     */
    public function getExportType(): string;

    /**
     * Sets the export type
     *
     * @param string $exportType
     * @return void
     */
    public function setExportType(string $exportType): void;

    /**
     * Retrieves the total product count for the export
     *
     * @return int
     */
    public function getProductCount(): int;

    /**
     * Sets the total product count for the export
     *
     * @param int $productCount
     * @return void
     */
    public function setProductCount(int $productCount): void;

    /**
     * Retrieves the total variant count for the export
     *
     * @return int
     */
    public function getVariantCount(): int;

    /**
     * Sets the total variant count for the export
     *
     * @param int $variantCount
     * @return void
     */
    public function setVariantCount(int $variantCount): void;

    /**
     * Retrieves the total number of added products
     *
     * @return int
     */
    public function getProductAddCount(): int;

    /**
     * Sets the total number of added products
     *
     * @param int $productAddCount
     * @return void
     */
    public function setProductAddCount(int $productAddCount): void;

    /**
     * Retrieves the total number of updated products
     *
     * @return int
     */
    public function getProductUpdateCount(): int;

    /**
     * Sets the total number of updated products
     *
     * @param int $productUpdateCount
     * @return void
     */
    public function setProductUpdateCount(int $productUpdateCount): void;

    /**
     * Retrieves the total number of deleted products
     *
     * @return int
     */
    public function getProductDeleteCount(): int;

    /**
     * Sets the total number of deleted products
     *
     * @param int $productDeleteCount
     * @return void
     */
    public function setProductDeleteCount(int $productDeleteCount): void;

    /**
     * Retrieves the total number of added variants
     *
     * @return int
     */
    public function getVariantAddCount(): int;

    /**
     * Sets the total number of added variants
     *
     * @param int $variantAddCount
     * @return void
     */
    public function setVariantAddCount(int $variantAddCount): void;

    /**
     * Retrieves the total number of updated variants
     *
     * @return int
     */
    public function getVariantUpdateCount(): int;

    /**
     * Sets the total number of updated variants
     *
     * @param int $variantUpdateCount
     * @return void
     */
    public function setVariantUpdateCount(int $variantUpdateCount): void;

    /**
     * Retrieves the total number of deleted variants
     *
     * @return int
     */
    public function getVariantDeleteCount(): int;

    /**
     * Sets the total number of deleted variants
     *
     * @param int $variantDeleteCount
     * @return void
     */
    public function setVariantDeleteCount(int $variantDeleteCount): void;

    /**
     * Retrieves the directory of the export on the filesystem
     *
     * @return string
     */
    public function getDirectory(): string;

    /**
     * Sets the directory of the export on the filesystem
     *
     * @param string $directory
     * @return void
     */
    public function setDirectory(string $directory): void;

    /**
     * Retrieves the creation time of the export
     *
     * @return int
     */
    public function getCreatedAt(): int;

    /**
     * Sets the creation time of the export
     *
     * @param int $createdAt
     * @return void
     */
    public function setCreatedAt(int $createdAt): void;

    /**
     * Retrieves the export's status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Sets the export's status
     *
     * @param string $status
     * @return void
     */
    public function setStatus(string $status): void;

    /**
     * Retrieves the error message for the export (if one exists)
     *
     * @return string|null
     */
    public function getError(): ?string;

    /**
     * Sets the error message for the export
     *
     * @param string $error
     * @return void
     */
    public function setError(string $error): void;

    /**
     * Retrieves the Fredhopper Data ID associated with the export
     *
     * @return string
     */
    public function getDataId(): string;

    /**
     * Sets the Fredhopper Data ID associated with the export
     *
     * @param string $dataId
     * @return void
     */
    public function setDataId(string $dataId): void;

    /**
     * Retrieves the version ID associated with the export
     *
     * @return int
     */
    public function getVersionId(): int;

    /**
     * Sets the version ID associated with the export
     *
     * @param int $versionId
     * @return void
     */
    public function setVersionId(int $versionId): void;

}