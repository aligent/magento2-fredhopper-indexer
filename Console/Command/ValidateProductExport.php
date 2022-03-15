<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface as FilesystemDriverInterface;
use Magento\Framework\Filesystem\Glob;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateProductExport extends Command
{
    /**
     * @var int
     */
    protected $maxLength = 200;

    /**
     * @var Json
     */
    protected $jsonSerializer;
    /**
     * @var FilesystemDriverInterface
     */
    protected $filesystemDriver;

    public function __construct(
        Json $jsonSerializer,
        FilesystemDriverInterface $filesystemDriver,
        string $name = null
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->filesystemDriver = $filesystemDriver;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('fredhopper:indexer:validate_export')
            ->setDescription('Validate export of one or more products');

        $this->setHelp(
            "Searches the history of Fredhopper exports for specified products, and displays " .
            "the most recently exported data for each export type (incremental and full), for " .
            "manual validation by the operator"
        );

        $desc = 'SKU(s), e.g. ABC123';
        $this->addArgument('sku', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $desc);
    }

    /**
     * Get the base directory which contains the Fredhopper exports
     * @return string
     */
    public function getBaseDir(): string
    {
        // TODO: add config value that determines where the exports are stored
        return '/tmp/';
    }

    /**
     * Get two lists of directory paths of FH exports: incremental, and full
     * @return array[]
     * @throws FileSystemException
     */
    protected function getDirs(): array
    {
        $files = Glob::glob($this->getBaseDir() . 'fh_export_*', GLOB_NOSORT);
        $incremental = $full = [];
        foreach ($files as $file) {
            $time = (int)($this->filesystemDriver->stat($file)['mtime'] ?? 0);
            if (strpos($file, 'incremental') !== false) {
                $incremental[$time] = $file;
            } else {
                $full[$time] = $file;
            }

        }
        krsort($incremental);
        krsort($full);
        return [$incremental, $full];
    }

    /**
     * Extract the product definitions for a set of SKUs from a single product export JSON file
     *
     * @param string $filePath
     * @param array $skus
     * @param OutputInterface $output
     * @return array [sku => [attribute_code => value]]
     */
    protected function extractSkus(string $filePath, array $skus, OutputInterface $output): array
    {
        try {
            $data = $this->jsonSerializer->unserialize($this->filesystemDriver->fileGetContents($filePath));
        } catch (\Exception $ex) { // phpcs:ignore
            // No drama, the check for $data['products'] will handle this
        }

        if (empty($data['products'])) {
            $output->writeln("Unable to read JSON from $filePath");
            return [];
        }
        $skuProducts = [];
        foreach ($data['products'] as $idx => $product) {
            $productId = $product['product_id'] ?? "unknown; (index $idx)";
            if (empty($product['attributes'])) {
                $output->writeln("Missing attributes for product $productId");
                continue;
            }
            $formattedAttrs = [];
            $sku = null;
            foreach ($product['attributes'] as $attr) {
                if (empty($attr['attribute_id']) || empty($attr['values'])) {
                    continue;
                }
                $attrId = $attr['attribute_id'];
                $values = [];
                foreach ($attr['values'] as $val) {
                    if (!isset($val['value'])) {
                        continue;
                    }
                    $values[] = $val['value'];
                }

                if ($attrId == 'sku') {
                    $skuMatch = false;
                    foreach ($values as $val) {
                        if (isset($skus[$val])) {
                            $skuMatch = true;
                            $sku = $val;
                            break;
                        }
                    }
                    if (!$skuMatch) {
                        continue 2;
                    }
                }

                $formattedAttrs[$attrId] = implode(' | ', $values);
            }
            if (!$sku) {
                continue;
            }
            $skuProducts[$sku] = $formattedAttrs;

            if (count($skuProducts) == count($skus)) {
                break;
            }
        }
        return $skuProducts;
    }

    protected function formatTime(int $timeDiff): string
    {
        $measures = [
            'd' => 86400, // 24hr
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];
        $formatted = '';
        foreach ($measures as $unit => $length) {
            if ($timeDiff > $length) {
                $num = (int)floor($timeDiff / $length);
                $timeDiff -= $num * $length;
                $formatted .= $num . $unit . ' ';
            }
        }
        return rtrim($formatted);
    }

    /**
     * @throws FileSystemException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $requestedSkus = $input->getArgument('sku');
        $skus = [];
        foreach ($requestedSkus as $sku) {
            $skus[$sku] = true;
        }

        $now = time();
        $groupedDirs = $this->getDirs();
        $processedSkus = [];
        foreach ($groupedDirs as $dirs) {
            $skusToProcess = array_diff($skus, $processedSkus);
            foreach ($dirs as $dir) {
                $processedSkus[] = $this->executeSingleDir($dir, $skus, $skusToProcess, $output, $now);
            }
        }
        $processedSkus = array_merge([], ...$processedSkus);
        foreach ($processedSkus as $sku) {
            unset($skus[$sku]);
        }
        if (count($skus) == 0) {
            $output->writeln("All SKUs found");
            return Cli::RETURN_SUCCESS;
        } else {
            $output->writeln("SKUs not found: " . implode(', ', array_keys($skus)));
            return Cli::RETURN_FAILURE;
        }
    }

    private function executeSingleDir(
        string $dir,
        array $skus,
        array $skusToProcess,
        OutputInterface $output,
        int $now
    ): array {

        $processedSkus = [];

        $files = Glob::glob($dir . '/products-*.json');
        foreach ($files as $file) {
            $products = $this->extractSkus($file, $skus, $output);
            if (count($products) == 0) {
                continue;
            }
            $msg = "In file $file";
            $matches = [];
            preg_match('/[0-9]+/', $file, $matches);
            if (!empty($matches[0])) {
                $fileTime = (int)$matches[0];
                $timeDiff = $now - $fileTime;
                $msg .= ' (' . $this->formatTime($timeDiff) . ' ago)';
            }
            $delimitLine = str_repeat('=', strlen($msg));
            $output->writeln($delimitLine);
            $output->writeln($msg);
            foreach ($products as $sku => $product) {
                $processedSkus[] = $sku;
                $output->writeln("=== $sku ===");
                foreach ($product as $attr => $vals) {
                    // Pretty print attributes that are JSON blobs
                    $isJson = false;
                    if (strlen($vals) > 0 && ($vals[0] == '{' || $vals[0] == '[')) {
                        try {
                            $decodedVals = $this->jsonSerializer->unserialize($vals);

                            // N.B. $this->jsonSerializer has no option for pretty printing
                            $vals = json_encode($decodedVals, JSON_PRETTY_PRINT);
                            $isJson = true;
                        } catch (\Exception $ex) { // phpcs:ignore
                            // looked like JSON, but wasn't valid JSON, so treat as normal string
                        }
                    }
                    if (!$isJson && strlen($vals) > $this->maxLength) {
                        $vals = substr($vals, 0, $this->maxLength) . ' ... ';
                    }
                    $output->writeln("$attr: $vals");
                }
            }
            $output->writeln($delimitLine);
            // we've processed all the needed skus
            if (count($processedSkus) === count($skusToProcess)) {
                break;
            }
        }
        return $processedSkus;
    }
}
