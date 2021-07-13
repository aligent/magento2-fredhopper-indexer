<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateProductExport extends \Symfony\Component\Console\Command\Command
{
    protected $maxLength = 200;

    protected function configure()
    {
        $this->setName('fredhopper:indexer:validate_export')
            ->setDescription('Validate export of one or more products');

        $desc = 'SKU(s), e.g. ABC123';
        $this->addArgument('sku', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $desc);
    }

    protected function getDirs()
    {
        $files = glob('/tmp/fh_export_*', GLOB_NOSORT);
        $incremental = $full = [];
        foreach ($files as $file) {
            $time = (int)filemtime($file);
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

    protected function extractSkus($filePath, $skus, OutputInterface $output)
    {
        $data = @json_decode(file_get_contents($filePath), true);
        if (empty($data['products'])) {
            $output->writeln("Unable to read JSON from {$filePath}");
            return;
        }
        $skuProducts = [];
        foreach ($data['products'] as $idx => $product) {
            $productId = $product['product_id'] ?? "unknown; (index {$idx})";
            if (empty($product['attributes'])) {
                $output->writeln("Missing attributes for product {$productId}");
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
        }
        return $skuProducts;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $requestedSkus = $input->getArgument('sku');
        $skus = [];
        foreach ($requestedSkus as $sku) {
            $skus[$sku] = true;
        }

        $groupedDirs = $this->getDirs();
        $processedSkus = [];
        foreach ($groupedDirs as $group => $dirs) {
            $groupSkus = $skus;
            foreach ($dirs as $dir) {
                $files = glob($dir . '/products-*.json');
                foreach ($files as $file) {
                    $products = $this->extractSkus($file, $skus, $output);
                    if (count($products) == 0) {
                        continue;
                    }
                    $msg = "In file {$file}";
                    $delimitLine = str_repeat('=', strlen($msg));
                    $output->writeln($delimitLine);
                    $output->writeln($msg);
                    foreach ($products as $sku => $product) {
                        unset($groupSkus[$sku]);
                        $processedSkus[] = $sku;
                        $output->writeln("=== {$sku} ===");
                        foreach ($product as $attr => $vals) {
                            // Pretty print attributes that are JSON blobs
                            $isJson = false;
                            if (strlen($vals) > 0 && ($vals[0] == '{' || $vals[0] == '[')) {
                                $decodedVals = @json_decode($vals, true);
                                if ($decodedVals !== null) {
                                    $isJson = true;
                                    $vals = json_encode($decodedVals, JSON_PRETTY_PRINT);
                                }
                            }
                            if (!$isJson && strlen($vals) > $this->maxLength) {
                                $vals = substr($vals, 0, $this->maxLength) . ' ... ';
                            }
                            $output->writeln("{$attr}: {$vals}");
                        }
                    }
                    $output->writeln($delimitLine);
                    if (count($groupSkus) == 0) {
                        continue 3;
                    }
                }
            }
        }
        foreach ($processedSkus as $sku) {
            unset($skus[$sku]);
        }
        if (count($skus) == 0) {
            $output->writeln("All SKUs found");
        } else {
            $output->writeln("SKUs not found: " . implode(', ', array_keys($skus)));
        }
    }
}
