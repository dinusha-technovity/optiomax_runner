<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;
 
class AssetItemsCsvImportService
{
    use SpreadsheetImportTrait;

    private $batchSize;
    private $maxFileSize;
    private $requiredColumns;
    private $csvColumnMapping;

    public function __construct()
    {
        $this->batchSize = config('app.csv_batch_size', 1000);
        $this->maxFileSize = config('app.csv_max_file_size', 50 * 1024 * 1024); // 50MB
        
        // Define required columns for asset items CSV - Updated for name-based lookups
        $this->requiredColumns = [
            'asset_name',      // Changed from asset_id to asset_name
            'serial_number',
            'model_number'
        ];

        // CSV column mapping - Updated for relationship lookups
        $this->csvColumnMapping = [
            // Basic Information
            'asset_name' => 'asset_name',                           // Will lookup/create asset
            'asset_type_name' => 'asset_type_name',               // Will lookup asset type by name
            'model_number' => 'model_number',
            'serial_number' => 'serial_number',
            'asset_tag' => 'asset_tag',
            'qr_code' => 'qr_code',
            
            // Financial Information
            'item_value' => 'item_value',
            'item_value_currency_code' => 'item_value_currency_code',     // Will lookup currency
            'purchase_cost' => 'purchase_cost',
            'purchase_cost_currency_code' => 'purchase_cost_currency_code', // Will lookup currency
            'purchase_type_name' => 'purchase_type_name',           // Will lookup purchase type
            'purchase_order_number' => 'purchase_order_number',
            'other_purchase_details' => 'other_purchase_details',
            'supplier_data' => 'supplier_data',                     // JSON object for supplier
            'salvage_value' => 'salvage_value',
            
            // Warranty & Insurance
            'warranty' => 'warranty',
            'warranty_condition_type_name' => 'warranty_condition_type_name', // Will lookup type
            'warranty_expiry_date' => 'warranty_expiry_date',
            'warranty_usage_name' => 'warranty_usage_name',
            'warranty_usage_value' => 'warranty_usage_value',
            'insurance_number' => 'insurance_number',
            'insurance_expiry_date' => 'insurance_expiry_date',
            
            // Depreciation
            'expected_life_time' => 'expected_life_time',
            'expected_life_time_unit_name' => 'expected_life_time_unit_name', // Will lookup time period
            'depreciation_value' => 'depreciation_value',
            'depreciation_method_name' => 'depreciation_method_name', // Will lookup method
            'depreciation_start_date' => 'depreciation_start_date',
            'decline_rate' => 'decline_rate',
            'total_estimated_units' => 'total_estimated_units',
            
            // Location & Assignment
            'manufacturer' => 'manufacturer',
            'responsible_person_name' => 'responsible_person_name', // Will lookup user
            'department_name' => 'department_name',                 // Will lookup department
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'received_condition_id' => 'received_condition_id',
            
            // Classification
            'asset_category_name' => 'asset_category_name',         // Will lookup/create category
            'asset_sub_category_name' => 'asset_sub_category_name', // Will lookup/create sub-category
            'asset_tags' => 'asset_tags',
            
            // Procurement
            'asset_requisition_item_id' => 'asset_requisition_item_id',
            'asset_requisition_id' => 'asset_requisition_id',
            'procurement_id' => 'procurement_id',
        ];
    }

    /**
     * Process CSV file for asset items bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting CSV processing', [
                'file_path' => $filePath,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'options' => $options
            ]);

            // Check if job_id is provided in options (from controller)
            if (isset($options['job_id']) && !empty($options['job_id'])) {
                $jobId = $options['job_id'];
                Log::info('Using existing job_id from options', ['job_id' => $jobId]);
            } else {
                // Create import job record
                $jobId = DB::connection('tenant')->table('import_jobs')->insertGetId([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => 'asset_items_csv',
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
            DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
                'total_rows' => $csvData['total_rows'],
                'updated_at' => now()
            ]);

            Log::info('CSV data read successfully', [
                'total_rows' => count($csvData['data']),
                'data_keys' => array_keys($csvData['data']),
                'first_row_sample' => !empty($csvData['data']) ? array_slice(reset($csvData['data']), 0, 5, true) : []
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
                    'message' => 'CSV validation completed successfully',
                    'data' => [
                        'job_id' => $jobId,
                        'validation_only' => true,
                        'total_rows' => count($transformedData['data']),
                        'error_rows' => count($transformedData['errors']),
                        'errors' => $transformedData['errors']
                    ]
                ];
            }

            // Process through database operations
            $this->updateJobStatus($jobId, 'processing', 'Inserting data into database...', null, 60);
            $result = $this->bulkInsertAssetItems(
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

            Log::error('CSV Import Error: ' . $e->getMessage(), [
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

        DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update($updates);
    }

    /**
     * Validate uploaded CSV file
     */
    private function validateCsvFile(string $filePath): array
    {
        return $this->validateSpreadsheetFile($filePath);
    }

    /**
     * Read CSV file and return data
     */
    private function readCsvFile(string $filePath): array
    {
        return $this->readSpreadsheetFile($filePath, 20000);
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
    public function transformCsvData(array $csvData, int $tenantId, int $jobId = null): array
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

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        // Transform each field according to mapping
        foreach ($this->csvColumnMapping as $csvColumn => $dbField) {
            if (isset($row[$csvColumn]) && $row[$csvColumn] !== '') {
                $value = trim($row[$csvColumn]);
                
                // Apply data type conversions and validations
                $transformedValue = $this->transformFieldValue($csvColumn, $value, $rowNumber);
                
                if ($transformedValue['success']) {
                    // Handle special cases for classification and location
                    if ($csvColumn === 'asset_category_id' || $csvColumn === 'asset_sub_category_id' || $csvColumn === 'asset_tags') {
                        if (!isset($transformedData['asset_classification'])) {
                            $transformedData['asset_classification'] = [];
                        }
                        if ($csvColumn === 'asset_tags') {
                            $tags = array_map('trim', explode(',', $value));
                            $transformedData['asset_classification']['asset_tags'] = json_encode($tags);
                        } else {
                            $transformedData['asset_classification'][$csvColumn] = $transformedValue['value'];
                        }
                    } else {
                        $transformedData[$dbField] = $transformedValue['value'];
                    }
                } else {
                    $errors[] = $transformedValue['error'];
                }
            }
        }

        // Validate JSON fields
        $jsonFields = [
            'supplier_data',
            // add any other JSON fields here, e.g. 'asset_tags', etc.
        ];
        foreach ($jsonFields as $field) {
            if (isset($row[$field])) {
                $value = trim($row[$field]);
                if ($value === '' || $value === null) {
                    $row[$field] = '{}';
                } else {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Invalid JSON format for {$field}: " . json_last_error_msg();
                        // Optionally, replace with empty object to avoid DB error
                        $row[$field] = '{}';
                    } else {
                        // Re-encode to ensure valid JSON string
                        $row[$field] = json_encode($decoded);
                    }
                }
            }
        }

        return [
            'success' => empty($errors),
            'data' => $transformedData,
            'errors' => $errors
        ];
    }

    /**
     * Transform and validate individual field values
     */
    private function transformFieldValue(string $fieldName, string $value, int $rowNumber): array
    {
        try {
            switch ($fieldName) {
                case 'asset_id':
                case 'item_value_currency_id':
                case 'purchase_cost_currency_id':
                case 'purchase_type':
                case 'supplier':
                case 'warranty_condition_type_id':
                case 'depreciation_method':
                case 'responsible_person_id':
                case 'department_id':
                case 'received_condition_id':
                case 'asset_category_id':
                case 'asset_sub_category_id':
                case 'expected_life_time_unit':
                case 'asset_requisition_item_id':
                case 'asset_requisition_id':
                case 'procurement_id':
                    // Integer fields
                    // if (!is_numeric($value)) {
                    //     return [
                    //         'success' => false,
                    //         'error' => "Field '{$fieldName}' must be a valid number, got '{$value}'"
                    //     ];
                    // }
                    // return ['success' => true, 'value' => (int)$value];

                case 'item_value':
                case 'purchase_cost':
                case 'salvage_value':
                case 'depreciation_value':
                case 'decline_rate':
                case 'total_estimated_units':
                    // Decimal/Numeric fields
                    if (!is_numeric($value)) {
                        return [
                            'success' => false,
                            'error' => "Field '{$fieldName}' must be a valid decimal number, got '{$value}'"
                        ];
                    }
                    return ['success' => true, 'value' => (float)$value];

                case 'warranty_expiry_date':
                case 'insurance_expiry_date':
                case 'depreciation_start_date':
                    // Date fields
                    $date = $this->validateAndFormatDate($value);
                    if (!$date) {
                        return [
                            'success' => false,
                            'error' => "Field '{$fieldName}' must be a valid date (YYYY-MM-DD), got '{$value}'"
                        ];
                    }
                    return ['success' => true, 'value' => $date];

                case 'latitude':
                case 'longitude':
                    // Coordinate validation
                    if (!is_numeric($value)) {
                        return [
                            'success' => false,
                            'error' => "Field '{$fieldName}' must be a valid coordinate, got '{$value}'"
                        ];
                    }
                    $coordinate = (float)$value;
                    if ($fieldName === 'latitude' && ($coordinate < -90 || $coordinate > 90)) {
                        return [
                            'success' => false,
                            'error' => "Latitude must be between -90 and 90, got '{$value}'"
                        ];
                    }
                    if ($fieldName === 'longitude' && ($coordinate < -180 || $coordinate > 180)) {
                        return [
                            'success' => false,
                            'error' => "Longitude must be between -180 and 180, got '{$value}'"
                        ];
                    }
                    return ['success' => true, 'value' => $coordinate];

                case 'serial_number':
                    // Serial number validation
                    if (strlen($value) > 255) {
                        return [
                            'success' => false,
                            'error' => "Serial number is too long (max 255 characters)"
                        ];
                    }
                    return ['success' => true, 'value' => $value];

                default:
                    // String fields - basic validation
                    return ['success' => true, 'value' => $value];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "Error processing field '{$fieldName}': " . $e->getMessage()
            ];
        }
    }

    /**
     * Validate and format date
     */
    private function validateAndFormatDate(string $dateString): ?string
    {
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }

    /**
     * Call PostgreSQL bulk insert function
     */
    public function bulkInsertAssetItems(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Log the bulk insert operation
            Log::info('Calling bulk_insert_asset_items_with_relationships', [
                'job_id' => $jobId,
                'job_id_is_null' => is_null($jobId),
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'data_count' => count($transformedData),
                'current_connection' => DB::connection('tenant')->getDatabaseName()
            ]);

            // Check if job exists in tenant database
            if ($jobId) {
                $jobExists = DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->exists();
                Log::info('Job existence check in tenant database', [
                    'job_id' => $jobId,
                    'exists' => $jobExists,
                    'database' => DB::connection('tenant')->getDatabaseName()
                ]);
            }

            // Prepare data for PostgreSQL function
            $itemsJson = json_encode($transformedData);
             
            // Call the PostgreSQL function with relationship handling
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_asset_items_with_relationships(?, ?, ?, ?, ?, ?)',
                [
                    $userId,           // _registered_by_user_id
                    $tenantId,         // _tenant_id
                    $jobId,
                    now(),             // _current_time
                    $itemsJson,        // _items JSON
                    $this->batchSize   // _batch_size
                ]
            );

            Log::info('PostgreSQL function completed', [
                'job_id' => $jobId,
                'status' => $result->status ?? 'unknown',
                'result' => $result
            ]);

            if ($result->status === 'SUCCESS') {
                // Update job progress
                if ($jobId) {
                    $this->updateJobStatus($jobId, 'processing', 'Finalizing import...', [
                        'total_processed' => $result->total_processed,
                        'total_inserted' => $result->total_inserted,
                        'total_updated' => $result->total_updated,
                        'total_errors' => $result->total_errors
                    ], 95);
                }

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
            Log::error('Bulk Insert Function Error: ' . $e->getMessage(), [
                'job_id' => $jobId,
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
     * Generate CSV template with updated field names for relationship lookups
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        // Updated example data with relationship-based fields
        $exampleRow = [
            'asset_name' => 'HP Elite Laptop',
            'asset_type_name' => 'Tangible assets',               // Added asset type
            'model_number' => 'HP-LAPTOP-001',
            'serial_number' => 'SN123456789',
            'asset_tag' => 'AT001',
            'qr_code' => 'QR001',
            'item_value' => '1500.00',
            'item_value_currency_code' => 'USD',
            'purchase_cost' => '1400.00',
            'purchase_cost_currency_code' => 'USD',
            'purchase_type_name' => 'Purchase',
            'purchase_order_number' => 'PO-2025-001',
            'other_purchase_details' => 'Purchased for IT department',
            'supplier_data' => '{"name":"Tech Supplier Inc","email":"contact@techsupplier.com","type":"COMPANY"}',
            'salvage_value' => '150.00',
            'warranty' => '2 years',
            'warranty_condition_type_name' => 'Time based',
            'warranty_expiry_date' => '2027-10-10',
            'warranty_usage_name' => 'Hours',
            'warranty_usage_value' => '8760',
            'insurance_number' => 'INS001',
            'insurance_expiry_date' => '2026-10-10',
            'expected_life_time' => '5',
            'expected_life_time_unit_name' => 'Year',
            'depreciation_value' => '300.00',
            'depreciation_method_name' => 'Straight Line',
            'depreciation_start_date' => '2025-10-10',
            'decline_rate' => '20.00',
            'total_estimated_units' => '1000',
            'manufacturer' => 'HP Inc.',
            'responsible_person_name' => 'John Doe',
            'department_name' => 'IT Department',
            'latitude' => '6.9271',
            'longitude' => '79.8612',
            'received_condition_id' => '1',
            'asset_category_name' => 'Electronics',
            'asset_sub_category_name' => 'Laptops',
            'asset_tags' => 'IT,Laptop,Portable',
            'asset_requisition_item_id' => '',
            'asset_requisition_id' => '',
            'procurement_id' => ''
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'asset_name' => 'Asset name - will create if not exists',
                'asset_type_name' => 'Use names from asset types: Tangible assets, Intangible assets, Operating assets, Non-operating assets, Current assets, Fixed assets',
                'currency_codes' => 'Use 3-letter codes: ' . implode(', ', ['USD', 'EUR', 'GBP', 'LKR']),
                'purchase_type_name' => 'Use names from asset requisition availability types',
                'supplier_data' => 'JSON: {"name":"Supplier Name","email":"email@domain.com","type":"Individual|COMPANY"}',
                'warranty_condition_type_name' => 'Options: ' . implode(', ', ['Time based', 'Usage based', 'Combined']),
                'expected_life_time_unit_name' => 'Options: ' . implode(', ', ['Day', 'Month', 'Year', 'Hour', 'Minute', 'Second']),
                'depreciation_method_name' => 'Use system depreciation method names',
                'responsible_person_name' => 'Use existing user names from system',
                'department_name' => 'Use existing department names',
                'category_names' => 'Will create categories/sub-categories if not exist'
            ]
        ];
    }
}