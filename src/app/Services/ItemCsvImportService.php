<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class ItemCsvImportService
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
        
        // Define required columns for items CSV
        $this->requiredColumns = [
            'item_name',
            'purchase_price',
            'selling_price',
            'purchase_price_currency_code',
            'selling_price_currency_code',
            'max_inventory_level',
            'min_inventory_level',
            're_order_level'
        ];

        // CSV column mapping for items
        $this->csvColumnMapping = [
            'item_id' => 'item_id',
            'item_name' => 'item_name',
            'item_description' => 'item_description',
            'category_name' => 'category_name',                           // Will lookup/create category by name
            'item_category_type_name' => 'item_category_type_name',       // Will lookup item category type by name
            'type_name' => 'type_name',                                   // Will lookup item type by name
            'unit_of_measure_name' => 'unit_of_measure_name',             // Will lookup unit of measure by name
            'purchase_price' => 'purchase_price',
            'purchase_price_currency_code' => 'purchase_price_currency_code', // Will lookup currency by code
            'selling_price' => 'selling_price',
            'selling_price_currency_code' => 'selling_price_currency_code', // Will lookup currency by code
            'max_inventory_level' => 'max_inventory_level',
            'min_inventory_level' => 'min_inventory_level',
            're_order_level' => 're_order_level',
            'low_stock_alert' => 'low_stock_alert',
            'over_stock_alert' => 'over_stock_alert',
            'image_links' => 'image_links'                                // JSON field
        ];
    }

    /**
     * Process CSV file for items bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting Items CSV processing', [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'options' => $options
            ]);

            // Create import job record
            $jobId = DB::table('import_jobs')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'items_csv',
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
                    'message' => 'Items CSV validation completed successfully',
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
            $result = $this->bulkInsertItems(
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

            Log::error('Items CSV Import Error: ' . $e->getMessage(), [
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
     * Use PostgreSQL function for bulk insert items
     */
    private function bulkInsertItems(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Prepare data for PostgreSQL function
            $itemsJson = json_encode($transformedData);
            
            // Call the PostgreSQL function for items bulk insert
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_items_with_relationships(?, ?, ?, ?, ?, ?)',
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
            Log::error('Items Bulk Insert Function Error: ' . $e->getMessage(), [
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
     * Generate CSV template for items
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'item_id' => 'ITM001',
            'item_name' => 'Office Paper A4',
            'item_description' => 'High quality white office paper for printing and copying',
            'category_name' => 'Office Supplies',
            'item_category_type_name' => 'Consumables',
            'type_name' => 'Stationery',
            'unit_of_measure_name' => 'Ream',
            'purchase_price' => '15.50',
            'purchase_price_currency_code' => 'USD',
            'selling_price' => '18.00',
            'selling_price_currency_code' => 'USD',
            'max_inventory_level' => '500',
            'min_inventory_level' => '50',
            're_order_level' => '100',
            'low_stock_alert' => 'true',
            'over_stock_alert' => 'false',
            'image_links' => '{"images": [{"url": "/uploads/items/paper.jpg", "alt": "Office Paper"}]}'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'item_name' => 'Item name (UNIQUE per tenant)',
                'category_name' => 'Asset category name - will create if not exist',
                'item_category_type_name' => 'Item category type name that must exist in the system',
                'type_name' => 'Item type name that must exist in the system',
                'unit_of_measure_name' => 'Unit of measure name that must exist in the system',
                'currency_codes' => 'Use 3-letter codes: USD, EUR, GBP, LKR, etc.',
                'inventory_levels' => 'All inventory levels must be positive integers',
                'alerts' => 'low_stock_alert and over_stock_alert: true/false',
                'image_links' => 'JSON format for image data (optional)',
                'validation_notes' => 'Categories will be created automatically if they do not exist. Item names must be unique within tenant.'
            ]
        ];
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

        // Validate currency codes
        if (!empty($row['purchase_price_currency_code'])) {
            $validCurrencies = ['USD', 'EUR', 'GBP', 'LKR', 'JPY', 'CAD', 'AUD', 'INR', 'CNY', 'CHF'];
            if (!in_array(strtoupper($row['purchase_price_currency_code']), $validCurrencies)) {
                $errors[] = "Invalid purchase price currency code '{$row['purchase_price_currency_code']}'";
            }
        }

        if (!empty($row['selling_price_currency_code'])) {
            $validCurrencies = ['USD', 'EUR', 'GBP', 'LKR', 'JPY', 'CAD', 'AUD', 'INR', 'CNY', 'CHF'];
            if (!in_array(strtoupper($row['selling_price_currency_code']), $validCurrencies)) {
                $errors[] = "Invalid selling price currency code '{$row['selling_price_currency_code']}'";
            }
        }

        // Validate numeric fields
        $numericFields = ['purchase_price', 'selling_price', 'max_inventory_level', 'min_inventory_level', 're_order_level'];
        foreach ($numericFields as $field) {
            if (!empty($row[$field]) && !is_numeric($row[$field])) {
                $errors[] = "Field '{$field}' must be a valid number";
            }
        }

        // Validate boolean fields
        $booleanFields = ['low_stock_alert', 'over_stock_alert'];
        foreach ($booleanFields as $field) {
            if (!empty($row[$field])) {
                $value = strtolower($row[$field]);
                if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                    $errors[] = "Field '{$field}' must be true/false, yes/no, or 1/0";
                }
            }
        }

        // Validate JSON format for image_links
        if (!empty($row['image_links'])) {
            $decoded = json_decode($row['image_links'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Invalid JSON format for image_links: " . json_last_error_msg();
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