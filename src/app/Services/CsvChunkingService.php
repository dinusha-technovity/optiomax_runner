<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;
use Exception;

class CsvChunkingService
{
    private $chunkSize;
    private $maxFileSize;
    
    public function __construct()
    {
        $this->chunkSize = config('app.csv_chunk_size', 2500); // Optimized for 25k+ rows
        $this->maxFileSize = config('app.csv_max_file_size', 500 * 1024 * 1024); // 500MB
    }

    /**
     * Determine if file should be chunked and return chunking strategy
     */
    public function analyzeFile(string $filePath): array
    {
        try {
            $fileSize = Storage::disk('s3')->size($filePath);
            $stream = Storage::disk('s3')->readStream($filePath);
            
            if (!$stream) {
                throw new Exception('Unable to read file');
            }

            // Quick row count estimation
            $sampleSize = min($fileSize, 1024 * 1024); // 1MB sample
            $sample = fread($stream, $sampleSize);
            $lineCount = substr_count($sample, "\n");
            
            // Estimate total rows (excluding header)
            $estimatedRows = (int) (($lineCount / $sampleSize) * $fileSize) - 1;
            
            fclose($stream);

            return [
                'file_size' => $fileSize,
                'estimated_rows' => $estimatedRows,
                'should_chunk' => $estimatedRows > 5000, // Chunk if > 5k rows
                'recommended_chunks' => max(1, ceil($estimatedRows / $this->chunkSize)),
                'chunk_size' => $this->chunkSize
            ];

        } catch (Exception $e) {
            Log::error('File analysis failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'file_size' => 0,
                'estimated_rows' => 0,
                'should_chunk' => false,
                'recommended_chunks' => 1,
                'chunk_size' => $this->chunkSize,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Read and chunk CSV file data
     */
    public function chunkCsvData(string $filePath, string $importType, int $tenantId): array
    {
        try {
            $stream = Storage::disk('s3')->readStream($filePath);
            if (!$stream) {
                throw new Exception('Unable to read CSV file');
            }

            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0);
            $csv->setDelimiter(',');
            $csv->setEnclosure('"');
            $csv->setEscape('\\');

            $records = iterator_to_array($csv->getRecords());
            fclose($stream);

            if (empty($records)) {
                throw new Exception('CSV file is empty');
            }

            // Get service for data transformation
            $service = $this->getServiceInstance($importType);
            $chunks = [];
            $totalRows = count($records);
            $chunkIndex = 0;

            // Process in chunks
            for ($i = 0; $i < $totalRows; $i += $this->chunkSize) {
                $chunkData = array_slice($records, $i, $this->chunkSize, true);
                
                // Transform chunk data
                $transformedChunk = [];
                foreach ($chunkData as $row) {
                    $transformedRow = $this->transformRowForImportType($row, $importType, $tenantId);
                    if ($transformedRow['success']) {
                        $transformedChunk[] = $transformedRow['data'];
                    }
                }

                if (!empty($transformedChunk)) {
                    // Cache chunk data with expiration
                    $cacheKey = "csv_chunk_{$importType}_{$tenantId}_" . time() . "_{$chunkIndex}";
                    Cache::put($cacheKey, $transformedChunk, 7200); // 2 hours

                    $chunks[] = [
                        'cache_key' => $cacheKey,
                        'chunk_index' => $chunkIndex,
                        'row_count' => count($transformedChunk),
                        'start_row' => $i + 1,
                        'end_row' => min($i + $this->chunkSize, $totalRows)
                    ];
                }

                $chunkIndex++;
            }

            return [
                'success' => true,
                'total_chunks' => count($chunks),
                'total_rows' => $totalRows,
                'chunks' => $chunks
            ];

        } catch (Exception $e) {
            Log::error('CSV chunking failed', [
                'file_path' => $filePath,
                'import_type' => $importType,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getServiceInstance(string $importType)
    {
        $serviceMap = [
            'asset_items_csv' => \App\Services\AssetItemsCsvImportService::class,
            'suppliers_csv' => \App\Services\SupplierCsvImportService::class,
            'customers_csv' => \App\Services\CustomerCsvImportService::class,
            'asset_categories_csv' => \App\Services\AssetCategoryCsvImportService::class,
            'asset_sub_categories_csv' => \App\Services\AssetSubCategoryCsvImportService::class,
            'assets_csv' => \App\Services\AssetCsvImportService::class,
            'items_csv' => \App\Services\ItemCsvImportService::class,
            'asset_availability_term_types_csv' => \App\Services\AssetAvailabilityTermTypeCsvImportService::class,
        ];

        $serviceClass = $serviceMap[$importType] ?? null;
        
        if (!$serviceClass) {
            throw new Exception("Unknown import type: {$importType}");
        }

        return app($serviceClass);
    }

    private function transformRowForImportType(array $row, string $importType, int $tenantId): array
    {
        // Basic transformation - each service will handle detailed transformation
        return [
            'success' => true,
            'data' => array_merge($row, ['tenant_id' => $tenantId])
        ];
    }
}
