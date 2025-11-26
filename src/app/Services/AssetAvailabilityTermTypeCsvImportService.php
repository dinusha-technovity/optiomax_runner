<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;
  
class AssetAvailabilityTermTypeCsvImportService
{
    use SpreadsheetImportTrait;

    private $batchSize;
    private $maxFileSize;
    private $requiredColumns;
    private $csvColumnMapping;

    public function __construct()
    {
        $this->batchSize = config('app.csv_batch_size', 2000); // Enterprise performance
        $this->maxFileSize = config('app.csv_max_file_size', 100 * 1024 * 1024); // 100MB
        
        // Define required columns for asset availability term types CSV
        $this->requiredColumns = [
            'name'
        ];

        // CSV column mapping for asset availability term types
        $this->csvColumnMapping = [
            'name' => 'name',
            'description' => 'description'
        ];
    }

    /**
     * Process CSV file for asset availability term types bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = $options['job_id'] ?? null;
        $createNewJob = $jobId === null;
        
        try {
            Log::info('Starting Asset Availability Term Types CSV processing', [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'job_id' => $jobId,
                'create_new_job' => $createNewJob,
                'options' => $options
            ]);

            // Create import job record only if not provided (for direct calls)
            if ($createNewJob) {
                $jobId = DB::connection('tenant')->table('import_jobs')->insertGetId([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => 'asset_availability_term_types_csv',
                    'status' => 'pending',
                    'file_name' => basename($filePath),
                    'file_path' => $filePath,
                    'file_size' => Storage::disk('s3')->size($filePath),
                    'options' => json_encode($options),
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                Log::info('Created new import job', ['job_id' => $jobId]);
            }

            // Update job status to processing
            if ($jobId) {
                $this->updateJobStatus($jobId, 'processing', 'Starting file validation...');
            }

            // Validate file
            $validationResult = $this->validateCsvFile($filePath);
            if (!$validationResult['success']) {
                $this->updateJobStatus($jobId, 'failed', $validationResult['message'], [
                    'error_details' => [$validationResult]
                ]);
                return $validationResult;
            }

            // Read and parse CSV
            $this->updateJobStatus($jobId, 'processing', 'Reading CSV file...', null, 10);
            $csvData = $this->readCsvFile($filePath);
            if (!$csvData['success']) {
                $this->updateJobStatus($jobId, 'failed', $csvData['message'], [
                    'error_details' => [$csvData]
                ]);
                return $csvData;
            }

            // Update total rows
            DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
                'total_rows' => $csvData['total_rows'],
                'updated_at' => now()
            ]);

            // Validate CSV structure
            $this->updateJobStatus($jobId, 'processing', 'Validating CSV structure...', null, 20);
            $structureValidation = $this->validateCsvStructure($csvData['data']);
            if (!$structureValidation['success']) {
                $this->updateJobStatus($jobId, 'failed', $structureValidation['message'], [
                    'error_details' => [$structureValidation]
                ]);
                return $structureValidation;
            }

            // Transform CSV data to database format
            $this->updateJobStatus($jobId, 'processing', 'Transforming data...', null, 40);
            $transformedData = $this->transformCsvData($csvData['data'], $tenantId, $jobId);
            if (!$transformedData['success']) {
                $this->updateJobStatus($jobId, 'failed', $transformedData['message'], [
                    'error_details' => $transformedData['errors'] ?? []
                ]);
                return $transformedData;
            }

            // If dry run, return validation results
            if ($options['dry_run'] ?? false) {
                $this->updateJobStatus($jobId, 'completed', 'Validation completed successfully', [
                    'total_processed' => count($transformedData['data']),
                    'total_errors' => count($transformedData['errors'])
                ], 100);

                return [
                    'success' => true,
                    'message' => 'Asset Availability Term Types CSV validation completed successfully',
                    'data' => [
                        'job_id' => $jobId,
                        'validation_only' => true,
                        'total_rows' => count($transformedData['data']),
                        'error_rows' => count($transformedData['errors']),
                        'errors' => $transformedData['errors']
                    ]
                ];
            }

            // Process through PostgreSQL function
            $this->updateJobStatus($jobId, 'processing', 'Inserting data into database...', null, 60);
            $result = $this->bulkInsertAssetAvailabilityTermTypes(
                $transformedData['data'], 
                $userId, 
                $tenantId,
                $jobId
            );

            // Update final job status
            if ($result['success']) {
                $totalProcessed = $result['data']['total_processed'] ?? 0;
                $totalInserted = $result['data']['total_inserted'] ?? 0;
                $totalErrors = $result['data']['total_errors'] ?? 0;
                
                // If all rows failed (no inserts), mark as failed
                if ($totalProcessed > 0 && $totalInserted === 0 && $totalErrors === $totalProcessed) {
                    $this->updateJobStatus($jobId, 'failed', 'All rows failed validation or contain duplicate names', [
                        'total_processed' => $totalProcessed,
                        'total_inserted' => $totalInserted,
                        'total_updated' => $result['data']['total_updated'],
                        'total_errors' => $totalErrors,
                        'error_details' => $result['data']['error_details']
                    ], 100);
                    
                    $result['success'] = false;
                    $result['message'] = 'All rows failed validation or contain duplicate names';
                } else {
                    $this->updateJobStatus($jobId, 'completed', $result['message'], [
                        'total_processed' => $totalProcessed,
                        'total_inserted' => $totalInserted,
                        'total_updated' => $result['data']['total_updated'],
                        'total_errors' => $totalErrors,
                        'error_details' => $result['data']['error_details']
                    ], 100);
                }
            } else {
                $this->updateJobStatus($jobId, 'failed', $result['message'], [
                    'error_details' => [$result]
                ]);
            }

            $result['data']['job_id'] = $jobId;
            return $result;

        } catch (Exception $e) {
            if ($jobId) {
                $this->updateJobStatus($jobId, 'failed', 'An error occurred during CSV processing: ' . $e->getMessage(), [
                    'error_details' => [['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]]
                ]);
            }

            Log::error('Asset Availability Term Types CSV Import Error: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'job_id' => $jobId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred during CSV processing: ' . $e->getMessage(),
                'error_code' => 'CSV_PROCESSING_ERROR',
                'data' => ['job_id' => $jobId]
            ];
        }
    }

    /**
     * Use PostgreSQL function for bulk insert asset availability term types
     */
    public function bulkInsertAssetAvailabilityTermTypes(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Log the job_id being passed to the function
            Log::info('Calling bulk_insert_asset_availability_term_types_with_relationships', [
                'job_id' => $jobId,
                'job_id_is_null' => $jobId === null,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'data_count' => count($transformedData),
                'current_connection' => DB::connection('tenant')->getDatabaseName()
            ]);

            // Check if job exists in tenant database
            $jobExists = DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->exists();
            Log::info('Job existence check in tenant database', [
                'job_id' => $jobId,
                'exists' => $jobExists,
                'database' => DB::connection('tenant')->getDatabaseName()
            ]);

            $itemsJson = json_encode($transformedData);

            // Use tenant connection for function call
            // Pass job_id for tracking
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_asset_availability_term_types_with_relationships(?, ?, ?, ?, ?, ?)',
                [
                    $userId,           // _created_by_user_id
                    $tenantId,         // _tenant_id
                    $jobId,            // _job_id for tracking import job
                    now(),             // _current_time
                    $itemsJson,        // _items JSON
                    $this->batchSize   // _batch_size
                ]
            );
            
            Log::info('PostgreSQL function completed', [
                'job_id' => $jobId,
                'status' => $result->status ?? 'unknown'
            ]);

            if ($result->status === 'SUCCESS') {
                return [
                    'success' => true,
                    'message' => $result->message,
                    'data' => [
                        'total_processed' => $result->total_processed,
                        'total_inserted' => $result->total_inserted,
                        'total_updated' => $result->total_updated,
                        'total_errors' => $result->total_errors,
                        'batch_results' => json_decode($result->batch_results, true),
                        'error_details' => json_decode($result->error_details, true)
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result->message,
                    'error_code' => 'DB_FUNCTION_ERROR',
                    'data' => [
                        'error_details' => json_decode($result->error_details ?? '[]', true)
                    ]
                ];
            }

        } catch (Exception $e) {
            Log::error('Asset Availability Term Types Bulk Insert Function Error: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'data_count' => count($transformedData),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Database function error during bulk insert: ' . $e->getMessage(),
                'error_code' => 'DB_FUNCTION_ERROR'
            ];
        }
    }

    /**
     * Generate CSV template for asset availability term types
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'name' => 'Short Term Rental',
            'description' => 'Assets available for short-term rental agreements (1-30 days)'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'name' => 'Asset availability term type name (UNIQUE per tenant)',
                'description' => 'Optional description of the availability term type',
                'validation_notes' => 'Names must be unique within tenant. Empty descriptions are allowed.'
            ]
        ];
    }

    // Helper methods (similar structure to other CSV import services)
    private function updateJobStatus($jobId, $status, $message = null, $data = null, $progress = null) {
        if (!$jobId) {
            return; // Skip if no job ID
        }
        
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
                }
            }
        }

        if ($status === 'completed' || $status === 'failed') {
            $updates['completed_at'] = now();
        }

        DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update($updates);
    }

    private function validateCsvFile($filePath) {
        return $this->validateSpreadsheetFile($filePath);
    }

    private function readCsvFile($filePath) {
        return $this->readSpreadsheetFile($filePath, 20000);
    }

    private function validateCsvStructure($csvData) {
        if (empty($csvData)) {
            return [
                'success' => false,
                'message' => 'No data found in CSV file',
                'error_code' => 'NO_DATA'
            ];
        }

        $firstRow = reset($csvData);
        if (!$firstRow) {
            return [
                'success' => false,
                'message' => 'Unable to read CSV data structure',
                'error_code' => 'INVALID_CSV_STRUCTURE'
            ];
        }

        $headers = array_keys($firstRow);
        $missingColumns = [];

        foreach ($this->requiredColumns as $required) {
            if (!in_array($required, $headers)) {
                $missingColumns[] = $required;
            }
        }

        if (!empty($missingColumns)) {
            return [
                'success' => false,
                'message' => 'Missing required columns: ' . implode(', ', $missingColumns),
                'error_code' => 'MISSING_COLUMNS',
                'missing_columns' => $missingColumns
            ];
        }

        return [
            'success' => true,
            'headers' => $headers
        ];
    }

    public function transformCsvData($csvData, $tenantId, $jobId = null) {
        $transformedData = [];
        $errors = [];
        $totalRows = count($csvData);

        foreach ($csvData as $originalRowNumber => $row) {
            $rowNumber = is_numeric($originalRowNumber) ? $originalRowNumber : count($transformedData) + count($errors) + 1;
            
            try {
                $transformedRow = $this->transformSingleRow($row, $tenantId, $rowNumber);
                
                if ($transformedRow['success']) {
                    $transformedData[] = $transformedRow['data'];
                } else {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $transformedRow['errors']
                    ];
                }

                if ($jobId && ($rowNumber % 100 === 0 || $rowNumber === $totalRows)) {
                    $progress = 20 + (($rowNumber / $totalRows) * 20);
                    $this->updateJobStatus($jobId, 'processing', "Transforming row {$rowNumber} of {$totalRows}...", null, $progress);
                }

            } catch (Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['Transformation error: ' . $e->getMessage()]
                ];
            }
        }

        if (count($errors) > (count($csvData) * 0.1)) {
            return [
                'success' => false,
                'message' => 'Too many validation errors found in CSV data',
                'error_code' => 'TOO_MANY_VALIDATION_ERRORS',
                'errors' => array_slice($errors, 0, 100)
            ];
        }

        return [
            'success' => true,
            'data' => $transformedData,
            'errors' => $errors,
            'total_valid_rows' => count($transformedData),
            'total_error_rows' => count($errors)
        ];
    }

    private function transformSingleRow($row, $tenantId, $rowNumber) {
        $transformedData = [];
        $errors = [];

        $transformedData['tenant_id'] = $tenantId;

        foreach ($this->requiredColumns as $required) {
            if (empty($row[$required])) {
                $errors[] = "Required field '{$required}' is empty";
            }
        }

        // Validate name length
        if (!empty($row['name']) && strlen($row['name']) > 255) {
            $errors[] = "Name is too long (maximum 255 characters)";
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        foreach ($this->csvColumnMapping as $csvColumn => $dbField) {
            if (isset($row[$csvColumn]) && $row[$csvColumn] !== '') {
                $value = trim($row[$csvColumn]);
                $transformedData[$dbField] = $value;
            }
        }

        return [
            'success' => empty($errors),
            'data' => $transformedData,
            'errors' => $errors
        ];
    }
}
