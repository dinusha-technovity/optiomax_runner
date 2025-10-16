<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class AssetCategoryCsvImportService
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
        
        // Define required columns for asset category CSV
        $this->requiredColumns = [
            'name'
        ];

        // CSV column mapping for asset categories
        $this->csvColumnMapping = [
            'name' => 'name',
            'description' => 'description',
            'asset_type_name' => 'asset_type_name', // Will lookup asset type by name
            'reading_parameters' => 'reading_parameters' // JSON field
        ];
    }

    /**
     * Process CSV file for asset category bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting Asset Category CSV processing', [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'options' => $options
            ]);

            // Create import job record
            $jobId = DB::table('import_jobs')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'asset_categories_csv',
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
                    'message' => 'Asset Category CSV validation completed successfully',
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
            $result = $this->bulkInsertAssetCategories(
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

            Log::error('Asset Category CSV Import Error: ' . $e->getMessage(), [
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
     * Use PostgreSQL function for bulk insert asset categories
     */
    private function bulkInsertAssetCategories(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Prepare data for PostgreSQL function
            $itemsJson = json_encode($transformedData);
            
            // Call the PostgreSQL function for asset category bulk insert
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_asset_categories_with_relationships(?, ?, ?, ?, ?, ?)',
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
            Log::error('Asset Category Bulk Insert Function Error: ' . $e->getMessage(), [
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
     * Generate CSV template for asset categories
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'name' => 'Electronics',
            'description' => 'Electronic devices and equipment including computers, phones, and accessories',
            'asset_type_name' => 'Tangible assets',
            'reading_parameters' => '{"temperature": {"unit": "celsius", "range": "-10 to 60"}, "humidity": {"unit": "percentage", "range": "5 to 95"}}'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'name' => 'Category name (UNIQUE per tenant)',
                'asset_type_name' => 'EXACT asset type names: Tangible assets, Intangible assets, Operating assets, Non-operating assets, Current assets, Fixed assets',
                'reading_parameters' => 'JSON format for sensor/monitoring parameters (optional)',
                'validation_notes' => 'Asset type names must match exactly. Category names must be unique within tenant.'
            ]
        ];
    }

    // Helper methods (similar structure to other CSV import services)
    private function updateJobStatus($jobId, $status, $message = null, $data = null, $progress = null) {
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

    private function validateCsvFile($filePath) {
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

    private function readCsvFile($filePath) {
        try {
            $stream = Storage::disk('s3')->readStream($filePath);
            
            if (!$stream) {
                return [
                    'success' => false,
                    'message' => 'Unable to read CSV file',
                    'error_code' => 'FILE_READ_ERROR'
                ];
            }

            $csv = Reader::createFromStream($stream);
            $csv->setHeaderOffset(0);
            $csv->setDelimiter(',');
            $csv->setEnclosure('"');
            $csv->setEscape('\\');
            
            $records = iterator_to_array($csv->getRecords());
            fclose($stream);

            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'CSV file is empty or contains no data rows',
                    'error_code' => 'EMPTY_FILE'
                ];
            }

            if (count($records) > 20000) {
                return [
                    'success' => false,
                    'message' => 'CSV file contains more than 20,000 rows. Please split into smaller files.',
                    'error_code' => 'TOO_MANY_ROWS'
                ];
            }

            return [
                'success' => true,
                'data' => $records,
                'total_rows' => count($records)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error reading CSV file: ' . $e->getMessage(),
                'error_code' => 'CSV_PARSE_ERROR'
            ];
        }
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

    private function transformCsvData($csvData, $tenantId, $jobId = null) {
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

        // Validate asset type if provided
        if (!empty($row['asset_type_name'])) {
            $validAssetTypes = ['Tangible assets', 'Intangible assets', 'Operating assets', 'Non-operating assets', 'Current assets', 'Fixed assets'];
            if (!in_array($row['asset_type_name'], $validAssetTypes)) {
                $errors[] = "Invalid asset type '{$row['asset_type_name']}'. Valid types are: " . implode(', ', $validAssetTypes);
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
}
