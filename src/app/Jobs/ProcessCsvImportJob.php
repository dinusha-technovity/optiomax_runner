<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;
 
class ProcessCsvImportJob implements ShouldQueue
{ 
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours timeout
    public $tries = 3;
    public $maxExceptions = 1;

    protected $importType;
    protected $filePath;
    protected $tenantId;
    protected $userId;
    protected $jobId;
    protected $options;
    protected $chunkData;
    protected $chunkIndex;
    protected $totalChunks;

    public function __construct(
        string $importType,
        string $filePath,
        int $tenantId,
        int $userId,
        int $jobId,
        array $options = [],
        array $chunkData = null,
        int $chunkIndex = 0,
        int $totalChunks = 1
    ) {
        $this->importType = $importType;
        $this->filePath = $filePath;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->jobId = $jobId;
        $this->options = $options;
        $this->chunkData = $chunkData;
        $this->chunkIndex = $chunkIndex;
        $this->totalChunks = $totalChunks;

        // Set queue based on import type and priority
        $this->onQueue($this->getQueueName($importType));
    }

    public function handle()
    {
        try {
            // Ensure tenant DB connection is set up
            $this->setupTenantDatabaseConnection();

            Log::info("Processing CSV import chunk", [
                'job_id' => $this->jobId,
                'import_type' => $this->importType,
                'chunk_index' => $this->chunkIndex,
                'total_chunks' => $this->totalChunks
            ]);

            // Update job status
            $this->updateJobProgress('processing', "Processing chunk {$this->chunkIndex} of {$this->totalChunks}");

            // Get service instance
            $service = $this->getServiceInstance();
            
            if ($this->chunkData) {
                // Process chunk data directly
                $result = $this->processChunk($service);
            } else {
                // Process entire file (for smaller files or initial processing)
                // Pass job_id in options so service doesn't create a new job
                $optionsWithJobId = array_merge($this->options, ['job_id' => $this->jobId]);
                $result = $service->processCsvFile(
                    $this->filePath,
                    $this->tenantId,
                    $this->userId,
                    $optionsWithJobId
                );
            }

            // Handle result
            $this->handleResult($result);

        } catch (Exception $e) {
            Log::error("CSV import job failed", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->updateJobProgress('failed', $e->getMessage(), [
                'error_details' => [['error' => $e->getMessage(), 'chunk' => $this->chunkIndex]]
            ]);

            throw $e;
        }
    }

    private function processChunk($service): array
    {
        // Transform and validate chunk data using the service's transformCsvData method
        $transformMethod = new \ReflectionMethod($service, 'transformCsvData');
        $transformed = $transformMethod->invoke($service, $this->chunkData, $this->tenantId, $this->jobId);

        if (!$transformed['success']) {
            return [
                'success' => false,
                'message' => $transformed['message'] ?? 'Chunk transformation failed',
                'data' => [
                    'error_details' => $transformed['errors'] ?? []
                ]
            ];
        }

        // Call bulk insert method with transformed data
        $bulkInsertMethod = new \ReflectionMethod($service, 'bulkInsert' . $this->getEntityName());
        return $bulkInsertMethod->invoke(
            $service,
            $transformed['data'],
            $this->userId,
            $this->tenantId,
            $this->jobId
        );
    }

    private function handleResult(array $result): void
    {
        if ($result['success']) {
            // Store chunk results in cache
            $cacheKey = "csv_import_results_{$this->jobId}_{$this->chunkIndex}";
            Cache::put($cacheKey, $result['data'], 3600); // 1 hour cache

            // Update progress
            $progress = (($this->chunkIndex + 1) / $this->totalChunks) * 100;
            $this->updateJobProgress('processing', "Completed chunk {$this->chunkIndex} of {$this->totalChunks}", null, $progress);

            // Check if this is the last chunk
            if ($this->chunkIndex === $this->totalChunks - 1) {
                $this->consolidateResults();
            }
        } else {
            $this->updateJobProgress('failed', $result['message'], [
                'error_details' => $result['data']['error_details'] ?? []
            ]);
        }
    }

    private function consolidateResults(): void
    {
        try {
            $totalProcessed = 0;
            $totalInserted = 0;
            $totalUpdated = 0;
            $totalErrors = 0;
            $allErrorDetails = [];

            // Collect all chunk results
            for ($i = 0; $i < $this->totalChunks; $i++) {
                $cacheKey = "csv_import_results_{$this->jobId}_{$i}";
                $chunkResult = Cache::get($cacheKey);

                if ($chunkResult) {
                    $totalProcessed += $chunkResult['total_processed'] ?? 0;
                    $totalInserted += $chunkResult['total_inserted'] ?? 0;
                    $totalUpdated += $chunkResult['total_updated'] ?? 0;
                    $totalErrors += $chunkResult['total_errors'] ?? 0;
                    
                    if (!empty($chunkResult['error_details'])) {
                        $allErrorDetails = array_merge($allErrorDetails, $chunkResult['error_details']);
                    }

                    // Clean up cache
                    Cache::forget($cacheKey);
                }
            }

            // Generate error report if needed
            $errorReportPath = null;
            if (!empty($allErrorDetails)) {
                $errorReportPath = $this->generateErrorReport($allErrorDetails);
            }

            // Final update using tenant connection
            $this->updateJobProgress('completed', "Import completed successfully", [
                'total_processed' => $totalProcessed,
                'total_inserted' => $totalInserted,
                'total_updated' => $totalUpdated,
                'total_errors' => $totalErrors,
                'error_report_path' => $errorReportPath
            ], 100);

        } catch (Exception $e) {
            Log::error("Failed to consolidate results", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function generateErrorReport(array $errorDetails): ?string
    {
        try {
            $fileName = "import_errors_{$this->importType}_{$this->jobId}_" . date('Y-m-d_H-i-s') . ".csv";
            $filePath = "csv_import_errors/{$fileName}";

            // Create CSV content
            $csvContent = "Row,Error Details,Data\n";
            
            foreach ($errorDetails as $error) {
                $row = $error['row'] ?? 'Unknown';
                $errorMsg = is_array($error['error']) ? implode('; ', $error['error']) : ($error['error'] ?? 'Unknown error');
                $data = isset($error['data']) ? json_encode($error['data']) : '';
                
                $csvContent .= "\"{$row}\",\"{$errorMsg}\",\"{$data}\"\n";
            }

            // Store in S3 or local storage
            \Storage::disk('s3')->put($filePath, $csvContent);

            return $filePath;

        } catch (Exception $e) {
            Log::error("Failed to generate error report", [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getServiceInstance()
    {
        // Ensure import type has _csv suffix for service lookup
        $importType = $this->importType;
        
        // Check if it already ends with '_csv' (using strpos to check last occurrence)
        if (strrpos($importType, '_csv') !== (strlen($importType) - 4)) {
            $importType .= '_csv';
        }

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
            Log::error('Service lookup failed', [
                'original_type' => $this->importType,
                'lookup_type' => $importType,
                'available_types' => array_keys($serviceMap)
            ]);
            throw new Exception("Unknown import type: {$importType} (original: {$this->importType})");
        }

        return app($serviceClass);
    }

    private function getEntityName(): string
    {
        // Ensure import type has _csv suffix
        $importType = $this->importType;
        if (strrpos($importType, '_csv') !== (strlen($importType) - 4)) {
            $importType .= '_csv';
        }

        $entityMap = [
            'asset_items_csv' => 'AssetItems',
            'suppliers_csv' => 'Suppliers',
            'customers_csv' => 'Customers',
            'asset_categories_csv' => 'AssetCategories',
            'asset_sub_categories_csv' => 'AssetSubCategories',
            'assets_csv' => 'Assets',
            'items_csv' => 'Items',
            'asset_availability_term_types_csv' => 'AssetAvailabilityTermTypes',
        ];

        return $entityMap[$importType] ?? 'Unknown';
    }

    private function getQueueName(string $importType): string
    {
        // Ensure import type has _csv suffix
        if (strrpos($importType, '_csv') !== (strlen($importType) - 4)) {
            $importType .= '_csv';
        }

        // Priority queues based on import type
        $priorityMap = [
            'asset_items_csv' => 'high-priority',
            'suppliers_csv' => 'medium-priority',
            'customers_csv' => 'medium-priority',
            'asset_categories_csv' => 'low-priority',
            'asset_sub_categories_csv' => 'low-priority',
            'assets_csv' => 'medium-priority',
            'items_csv' => 'medium-priority',
            'asset_availability_term_types_csv' => 'low-priority',
        ];

        return $priorityMap[$importType] ?? 'default';
    }

    private function updateJobProgress(string $status, string $message = null, array $data = null, float $progress = null): void
    {
        $updates = [
            'status' => $status,
            'updated_at' => now()
        ];

        if ($message) {
            $updates['message'] = $message;
        }

        if ($progress !== null) {
            $updates['progress_percentage'] = $progress;
        }

        if ($data) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['total_processed', 'total_inserted', 'total_updated', 'total_errors'])) {
                    $updates[$key] = $value;
                } elseif ($key === 'error_details') {
                    $updates['error_details'] = json_encode($value);
                } elseif ($key === 'error_report_path') {
                    $updates['statistics'] = json_encode(['error_report_path' => $value]);
                }
            }
        }

        if ($status === 'completed' || $status === 'failed') {
            $updates['completed_at'] = now();
        }

        // Use tenant connection for job updates
        DB::connection('tenant')->table('import_jobs')->where('id', $this->jobId)->update($updates);
    }

    private function setupTenantDatabaseConnection(): void
    {
        // Only configure if tenant_db_config is present
        if (!empty($this->options['tenant_db_config'])) {
            $cfg = $this->options['tenant_db_config'];
            
            // Validate required configuration
            if (empty($cfg['host']) || empty($cfg['database']) || empty($cfg['username'])) {
                throw new Exception('Incomplete tenant database configuration');
            }

            config([
                'database.connections.tenant' => [
                    'driver' => 'pgsql',
                    'host' => $cfg['host'],
                    'port' => env('DB_PORT', '5432'),
                    'database' => $cfg['database'],
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ]
            ]);
            
            // Purge existing connection
            DB::purge('tenant');
            
            // Test connection
            try {
                DB::connection('tenant')->getPdo();
                Log::info('Tenant database connection established in job', [
                    'job_id' => $this->jobId,
                    'database' => $cfg['database'],
                    'host' => $cfg['host']
                ]);
            } catch (\Exception $e) {
                throw new Exception("Failed to connect to tenant database: " . $e->getMessage());
            }
        } else {
            throw new Exception('Tenant database configuration not provided in job options');
        }
    }

    public function failed(\Throwable $exception)
    {
        $this->setupTenantDatabaseConnection();
        Log::error("CSV import job permanently failed", [
            'job_id' => $this->jobId,
            'import_type' => $this->importType,
            'chunk_index' => $this->chunkIndex,
            'error' => $exception->getMessage()
        ]);
        $this->updateJobProgress('failed', "Job failed: " . $exception->getMessage(), [
            'error_details' => [['error' => $exception->getMessage(), 'chunk' => $this->chunkIndex]]
        ]);
    }
}
