# Aligent Fredhopper Indexer

## Overview
This package provides indexing of product data, as well as the subsequent exporting of that data to Fredhopper, a merchandising platform.

## Installation
This package provides 3 related modules, and can be installed via composer:
```shell
composer require aligent/magento2-fredhopper-indexer
bin/magento module:enable Aligent_FredhopperIndexer Aligent_FredhopperExport Aligent_FredhopperCommon
bin/magento setup:upgrade
```

## Architecture

- New database table, `fredhopper_product_data_index`
    - Stores attribute data for both products and variants in JSON format
    - Associated new database table, `fredhopper_changelog`
        - Keeps track of which products have been added, updated or deleted from the index. This allows for incremental exports. 
- New `fredhopper` indexer, responsible for indexing the required product data.
- New `Export` entity, which allows for tracking of exports to Fredhopper. Information such as the number of products added/updated/deleted, as well as status is maintained.
- 8 new cron jobs for generating and maintaining exports.
    - `fredhopper_full_export` - Generates a full export of the current attribute data for all products/variants. This will also include meta information about attributes.
    - `fredhopper_incremental_export` - Generates an export of product/variants that have changed since the last data set was exported.
    - `fredhopper_suggest_export` - Generates an export for data to be used in 'instant search' functionality.
    - `fredhopper_upload_export` - Uploads any waiting export to Fredhopper
    - `fredhopper_trigger_export` - Triggers an uploaded export to be loaded into Fredhopper
    - `fredhopper_invalidate_exports` - Marks any exports that have not been uploaded, and been superseded by another export as invalid
    - `fredhopper_update_data_status` - Checks and updates the status of exports based on information returned from Fredhopper.
    - `fredhopper_clean_exports` - Removes exports older than a configured age (default 3 days)
    
## Configuration
This module provides a number of configurable settings for controlling which data is sent to Fredhopper and in what format:
- Use Variant Products?
    - Should variant products be sent separately from their parent, or will their data be aggregated and sent with the parent instead?
- Product-Level Attributes
    - Which attributes are sent at the product level, the attribute type, and if site variants are to be appended.
        - Only attributes meeting certain criteria (e.g. searchable, visible in front-end, etc.) will be able to be selected.
- Variant-Level Attributes
    - Which attributes are either sent at the variant level (if using variant products) or aggregated to the parent product.
- Attribute name mapping
    - Maps Magento attribute codes to Fredhopper attribute ids
- Allowed/Disallowed Search Terms
    - Force or prevent certain search results in instant search functionality.
- Cron Schedules
    - Change schedule of each cron job that generates an export if desired.

## Customisation
There are a number of customisation points in the module, allowing for data to be added/modified.
- Additional Fields Provider
    -  A virtual type, `additionalFieldsProviderForFredhopper`, is provided with a number of common fields. Additional classes implementing `\Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface` can be added as needed.
```
<virtualType name="additionalFieldsProviderForFredhopper" type="Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProvider">
    <arguments>
        <argument name="fieldsProviders" xsi:type="array">
            <item name="categories" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\CategoryFieldsProvider</item>
            <item name="images" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\ImageFieldsProvider</item>
        </argument>
    </arguments>
</virtualType>
```
- Document Pre/Post-Processors
    - `\Aligent\FredhopperIndexer\Model\DataHandler` class has 2 arguments, `documentPreProcessors` and `documentPostProcessors`, which handle an array of classes implementing `\Aligent\FredhopperIndexer\Api\Indexer\Data\DocumentProcessorInterface`
        - These will be run before and after "processing" of documents as a whole (i.e. after all attribute information has been added). As such, this is a good place to handle any custom aggregation or functionality relating to the product as a whole (as opposed to individual attributes).
- Meta
    - Custom attributes added via code will need to be added to the meta information that is sent to Fredhopper. This is done by adding to the `customAttributeData` argument of the `\Aligent\FredhopperCommon\Model\Config\CustomAttributeConfig` class:
```
<type name="Aligent\FredhopperCommon\Model\Config\CustomAttributeConfig">
    <arguments>
        <argument name="customAttributeData" xsi:type="array">
            <item name="reviews_count" xsi:type="array">
                <item name="attribute_code" xsi:type="string">reviews_count</item>
                <item name="fredhopper_type" xsi:type="string">int</item>
                <item name="label" xsi:type="string">Review Count</item>
            </item>
        </argument>
    </arguments>
</type>
```
- Custom Suggest Data
    - The `\Aligent\FredhopperExport\Model\GenerateSuggestExport` class provides the `fileGenerators` argument by which custom data feeds can be added to the "suggest" export.
        - Each class added to the array must implement `\Aligent\FredhopperExport\Api\FileGeneratorInterface`
```
<virtualType name="BlacklistFileGenerator" type="Aligent\FredhopperExport\Model\Data\Suggest\SearchTermsFileGenerator">
    <arguments>
        <argument name="isBlacklist" xsi:type="boolean">true</argument>
    </arguments>
</virtualType>
<virtualType name="WhitelistFileGenerator" type="Aligent\FredhopperExport\Model\Data\Suggest\SearchTermsFileGenerator">
    <arguments>
        <argument name="isBlacklist" xsi:type="boolean">false</argument>
    </arguments>
</virtualType>
<type name="Aligent\FredhopperExport\Model\GenerateSuggestExport">
    <arguments>
        <argument name="fileGenerators" xsi:type="array">
            <item name="blacklist" xsi:type="object">BlacklistFileGenerator</item>
            <item name="whitelist" xsi:type="object">WhitelistFileGenerator</item>
        </argument>
    </arguments>
</type>
```
- Export Validation
    - The `\Aligent\FredhopperExport\Model\UploadExport` class provides the `validators` argument by which any pre-export validation can be added.
        - Each class added to the array must implement `\Aligent\FredhopperExport\Api\PreExportValidatorInterface`
```
<type name="Aligent\FredhopperExport\Model\UploadExport">
    <arguments>
        <argument name="validators" xsi:type="array">
            <item name="deletedProductsValidator" xsi:type="object">Aligent\FredhopperExport\Model\Validator\DeletedProductsValidator</item>
            <item name="minimumProductsValidator" xsi:type="object">Aligent\FredhopperExport\Model\Validator\MinimumProductsValidator</item>
        </argument>
    </arguments>
</type>
```

## Administration
A view of exports is provided in the Adobe Commerce admin area. This view lists all generated exports, including  information such as the number of added, updated and deleted products, as well as the export's current status in the system.
