<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class AssetSubCategoryCsvImportService
{
    private $batchSize;
    private $maxFileSize;
    private $allowedMimeTypes;
    private $requiredColumns;
    private $csvColumnMapping;

    public function __construct()
    {
        $this->batchSize = config('app.csv_batch_size', 2000); // Enterprise performance
        $this->maxFileSize = config('app.csv_max_file_size', 100 * 1024 * 1024); // 100MB
        $this->allowedMimeTypes = ['text/csv', 'application/csv', 'text/plain'];
        
        // Define required columns for asset sub-category CSV
        $this->requiredColumns = [
            'name',
            'asset_category_name'
        ];

        // CSV column mapping for asset sub-categories
        $this->csvColumnMapping = [
            'name' => 'name',
            'asset_category_name' => 'asset_category_name', // Will lookup category by name
            'description' => 'description',
            'reading_parameters' => 'reading_parameters' // JSON field
        ];
    }

    /**
     * Process CSV file for asset sub-category bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting Asset Sub-Category CSV processing', [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'options' => $options
            ]);

            // Create import job record
            $jobId = DB::table('import_jobs')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'asset_sub_categories_csv',
                'status' => 'pending',
                'file_name' => basename($filePath),
                'file_path' => $filePath,
                'file_size' => Storage::disk('s3')->size($filePath),
                'options' => json_encode($options),
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update job status to processing
            $this->updateJobStatus($jobId, 'processing', 'Starting file validation...');

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
            DB::table('import_jobs')->where('id', $jobId)->update([
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
                    'message' => 'Asset Sub-Category CSV validation completed successfully',
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
            $result = $this->bulkInsertAssetSubCategories(
                $transformedData['data'], 
                $userId, 
                $tenantId,
                $jobId
            );

            // Update final job status
            if ($result['success']) {
                $this->updateJobStatus($jobId, 'completed', $result['message'], [
                    'total_processed' => $result['data']['total_processed'],
                    'total_inserted' => $result['data']['total_inserted'],
                    'total_updated' => $result['data']['total_updated'],
                    'total_errors' => $result['data']['total_errors'],
                    'error_details' => $result['data']['error_details']
                ], 100);
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

            Log::error('Asset Sub-Category CSV Import Error: ' . $e->getMessage(), [
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
     * Update job status in database
     */
    private function updateJobStatus(int $jobId, string $status, string $message = null, array $data = null, float $progress = null): void
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
                }
            }
        }

        if ($status === 'completed' || $status === 'failed') {
            $updates['completed_at'] = now();
        }

        DB::table('import_jobs')->where('id', $jobId)->update($updates);
    }

    /**
     * Validate uploaded CSV file
     */
    private function validateCsvFile(string $filePath): array
    {
        // Check if file exists
        if (!Storage::disk('s3')->exists($filePath)) {
            return [
                'success' => false,
                'message' => 'CSV file not found',
                'error_code' => 'FILE_NOT_FOUND'
            ];
        }

        // Check file size
        $fileSize = Storage::disk('s3')->size($filePath);
        if ($fileSize > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB',
                'error_code' => 'FILE_TOO_LARGE'
            ];
        }

        // Check MIME type
        $mimeType = Storage::disk('s3')->mimeType($filePath);
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only CSV files are allowed',
                'error_code' => 'INVALID_FILE_TYPE'
            ];
        }

        return ['success' => true];
    }

    /**
     * Read CSV file and return data
     */
    private function readCsvFile(string $filePath): array
    {
        try {
            // Get file stream from MinIO
            $stream = Storage::disk('s3')->readStream($filePath);
            
            if (!$stream) {
                return [
                    'success' => false,
                    'message' => 'Unable to read CSV file',
                    'error_code' => 'FILE_READ_ERROR'
                ];
            }

            // Create CSV reader from stream
            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0); // First row contains headers
            
            // Set delimiter and enclosure for better parsing
            $csv->setDelimiter(',');
            $csv->setEnclosure('"');
            $csv->setEscape('\\');
            
            // Convert to array
            $records = iterator_to_array($csv->getRecords());
            
            // Close stream
            fclose($stream);

            // Check if file is empty
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'CSV file is empty or contains no data rows',
                    'error_code' => 'EMPTY_FILE'
                ];
            }

            // Check row count limit
            if (count($records) > 20000) {
                return [
                    'success' => false,
                    'message' => 'CSV file contains more than 20,000 rows. Please split into smaller files.',
                    'error_code' => 'TOO_MANY_ROWS'
                ];
            }

            // Log successful read for debugging
            Log::info('CSV file read successfully', [
                'file_path' => $filePath,
                'total_rows' => count($records),
                'first_row_keys' => !empty($records) ? array_keys(reset($records)) : []
            ]);

            return [
                'success' => true,
                'data' => $records,
                'total_rows' => count($records)
            ];

        } catch (Exception $e) {
            Log::error('CSV Read Error', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error reading CSV file: ' . $e->getMessage(),
                'error_code' => 'CSV_PARSE_ERROR'
            ];
        }
    }

    /**
     * Validate CSV structure and required columns
     */
    private function validateCsvStructure(array $csvData): array
    {
        if (empty($csvData)) {
            return [
                'success' => false,
                'message' => 'No data found in CSV file',
                'error_code' => 'NO_DATA'
            ];
        }

        // Get headers from first available row (League CSV may start from index 1)
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

        // Check for required columns
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

    /**
     * Transform CSV data to database format
     */
    private function transformCsvData(array $csvData, int $tenantId, int $jobId = null): array
    {
        $transformedData = [];
        $errors = [];
        $totalRows = count($csvData);

        foreach ($csvData as $originalRowNumber => $row) {
            // Use the original row number from CSV or increment counter
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

                // Update progress periodically
                if ($jobId && ($rowNumber % 100 === 0 || $rowNumber === $totalRows)) {
                    $progress = 20 + (($rowNumber / $totalRows) * 20); // 20-40% range for transformation
                    $this->updateJobStatus($jobId, 'processing', "Transforming row {$rowNumber} of {$totalRows}...", null, $progress);
                }

            } catch (Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['Transformation error: ' . $e->getMessage()]
                ];
            }
        }

        // If we have too many errors, return error response
        if (count($errors) > (count($csvData) * 0.1)) { // More than 10% errors
            return [
                'success' => false,
                'message' => 'Too many validation errors found in CSV data',
                'error_code' => 'TOO_MANY_VALIDATION_ERRORS',
                'errors' => array_slice($errors, 0, 100) // Return first 100 errors
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

    /**
     * Transform a single CSV row to database format
     */
    private function transformSingleRow(array $row, int $tenantId, int $rowNumber): array
    {
        $transformedData = [];
        $errors = [];

        // Add tenant ID to all records
        $transformedData['tenant_id'] = $tenantId;

        // Validate required fields
        foreach ($this->requiredColumns as $required) {
            if (empty($row[$required])) {
                $errors[] = "Required field '{$required}' is empty";
            }
        }

        // Validate JSON format for reading_parameters
        if (!empty($row['reading_parameters'])) {
            $decoded = json_decode($row['reading_parameters'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Invalid JSON format for reading_parameters: " . json_last_error_msg();
            }
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

    /**
     * Call PostgreSQL bulk insert function
     */
    private function bulkInsertAssetSubCategories(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Prepare data for PostgreSQL function
            $itemsJson = json_encode($transformedData);
            
            // Call the PostgreSQL function for asset sub-category bulk insert
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_asset_sub_categories_with_relationships(?, ?, ?, ?, ?, ?)',
                [
                    $userId,           // _created_by_user_id
                    $tenantId,         // _tenant_id
                    $jobId,            // _job_id
                    now(),             // _current_time
                    $itemsJson,        // _items JSON
                    $this->batchSize   // _batch_size
                ]
            );

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
            Log::error('Asset Sub-Category Bulk Insert Function Error: ' . $e->getMessage(), [
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
     * Generate CSV template for asset sub-categories
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'name' => 'Laptops',
            'asset_category_name' => 'Electronics',
            'description' => 'Portable computers including notebooks and ultrabooks for business use',
            'reading_parameters' => '{"battery": {"unit": "hours", "range": "4 to 12"}, "temperature": {"unit": "celsius", "range": "0 to 50"}}'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'name' => 'Sub-category name (UNIQUE per category and tenant)',
                'asset_category_name' => 'EXACT asset category name that must exist in the system',
                'reading_parameters' => 'JSON format for sensor/monitoring parameters (optional)',
                'validation_notes' => 'Asset category must exist before creating sub-categories. Sub-category names must be unique within each category.'
            ]
        ];
    }
}