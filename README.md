# Aligent Fredhopper Indexer

## Overview
This module is intended to replace the core Magento 2 CatalogSearch indexer (`catalogsearch_fulltext`) with one that prepares product data for export to Fredhopper.

## Architecture

- New database table, `fredhopper_product_data_index`
    - Stores attribute data for both products and variants in JSON format
- New search engine, `fredhopper`, to be used in place of Elasticsearch (or other).
    - Note that search capability is not part of this module, and should be handled by another module (e.g. Aligent_DataProxy). This module is only concerned with the flow of data from Magento to Fredhopper.
- 3 new cron jobs for exporting data to Fredhopper. All three jobs use the JSON data format.
    - `fredhopper_full_export` - Exports the current attribute data for all products/variants. This will also export meta information about attributes.
    - `fredhopper_incremental_export` - Exports product/variants that have changed since the last run.
        - This job is added to the `index` cron group, so that it will not be affected by ongoing indexing processes.
    - `fredhopper_suggest_export` - Exports data to be used in 'instant search' functionality.
    
## Configuration
This module provides a number of configurable settings for controlling which data is sent to Fredhopper and in what format:
- Use Variant Products?
    - Should variant products be sent separately from their parent, or will their data be aggregated and sent with the parent instead?
- Product-Level Attributes
    - Which attributes are sent at the product level, the attribute type, and if site variants are to be appended.
        - Only attributes meeting certain criteria (e.g. searchable, visible in front-end, etc.) will be able to be selected.
- Variant-Level Attributes
    - Which attributes are either sent at the variant level (if using variant products) or aggregated to the parent product.
- Pricing Attributes
    - Send pricing attributes per site / per customer group? Include min/max pricing?
- Stock Attributes
    - Send in-stock indicator and/or stock count?
- Product Age Attributes
    - Send attributes indicating if the product is new and/or the number of days online?
- Allowed/Disallowed Search Terms
    - Force or prevent certain search results in instant search functionality.
- Cron Schedules
    - Change schedule of each cron job if desired.

## Customisation
There are a number of customisation points in the module, allowing for data to be added/modified.
- Additional Fields Provider
    -  A virtual type, `additionalFieldsProviderForFredhopper`, is provided with a number of common fields. Additional classes implementing `\Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface` can be added as needed.
```
<virtualType name="additionalFieldsProviderForFredhopper" type="Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProvider">
    <arguments>
        <argument name="fieldsProviders" xsi:type="array">
            <item name="categories" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\CategoryFieldsProvider</item>
            <item name="prices" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\PriceFieldsProvider</item>
            <item name="stock" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\StockFieldsProvider</item>
            <item name="images" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\ImageFieldsProvider</item>
            <item name="age" xsi:type="object">Aligent\FredhopperIndexer\Model\Indexer\Data\AgeFieldsProvider</item>
        </argument>
    </arguments>
</virtualType>
```
- Document Pre/Post-Processors
    - `\Aligent\FredhopperIndexer\Model\Indexer\DataHandler` class has 2 arguments, `documentPreProcessors` and `documentPostProcessors`, which handle an array of classes implementing `\Aligent\\FredhopperIndexer\Api\Indexer\Data\DocumentProcessorInterface`
        - These will be run before and after "processing" of documents as a whole (i.e. after all attribute information has been added). As such, this is a good place to handle any custom aggregation or functionality relating to the product as a whole (as opposed to individual attributes).
- Meta
    - Custom attributes added via code will need to be added to the meta information that is sent to Fredhopper. This is done by adding to the `customAttributeData` argument of the `\Aligent\FredhopperIndexer\Model\Export\Data\Meta` class:
```
<type name="Aligent\FredhopperIndexer\Model\Export\Data\Meta">
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
    - The `\Aligent\FredhopperIndexer\Model\Export\SuggestExporter` class provides the `fileGenerators` argument by which custom data feeds can be added to the suggest export.
        - Each class added to the array must implement `\Aligent\FredhopperIndexer\Api\Export\FileGeneratorInterface`
```
<type name="Aligent\FredhopperIndexer\Model\Export\SuggestExporter">
    <arguments>
        <argument name="fileGenerators" xsi:type="array">
            <item name="blacklist" xsi:type="object">Aligent\FredhopperIndexer\Model\Export\Data\BlacklistFileGenerator</item>
            <item name="whitelist" xsi:type="object">Aligent\FredhopperIndexer\Model\Export\Data\WhitelistFileGenerator</item>
        </argument>
    </arguments>
</type>
```