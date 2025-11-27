<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;
 
class SupplierCsvImportService
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
        
        // Define required columns for supplier CSV
        $this->requiredColumns = [
            'name',
            'email'
        ];

        // CSV column mapping for suppliers
        $this->csvColumnMapping = [
            'name' => 'name',
            'email' => 'email',
            'supplier_primary_email' => 'supplier_primary_email',
            'supplier_secondary_email' => 'supplier_secondary_email',
            'supplier_type' => 'supplier_type',
            'supplier_business_name' => 'supplier_business_name',
            'supplier_business_register_no' => 'supplier_business_register_no',
            'contact_no' => 'contact_no',
            'address' => 'address',
            'description' => 'description',
            'supplier_website' => 'supplier_website',
            'supplier_tel_no' => 'supplier_tel_no',
            'supplier_mobile' => 'supplier_mobile',
            'supplier_fax' => 'supplier_fax',
            'supplier_city' => 'supplier_city',
            'supplier_location_latitude' => 'supplier_location_latitude',
            'supplier_location_longitude' => 'supplier_location_longitude',
            'asset_categories' => 'asset_categories' // Comma-separated category names
        ];
    }

    /**
     * Process CSV file for supplier bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting Supplier CSV processing', [
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
                    'type' => 'suppliers_csv',
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
                    'message' => 'Supplier CSV validation completed successfully',
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
            $result = $this->bulkInsertSuppliers(
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

            Log::error('Supplier CSV Import Error: ' . $e->getMessage(), [
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
     * Use PostgreSQL function for bulk insert suppliers
     */
    public function bulkInsertSuppliers(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Log the bulk insert operation
            Log::info('Calling bulk_insert_suppliers_with_relationships', [
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
            
            // Call the PostgreSQL function for supplier bulk insert
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_suppliers_with_relationships(?, ?, ?, ?, ?, ?)',
                [
                    $userId,           // _created_by_user_id
                    $tenantId,         // _tenant_id
                    $jobId,            // _job_id
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
            Log::error('Supplier Bulk Insert Function Error: ' . $e->getMessage(), [
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
     * Generate CSV template for suppliers
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'name' => 'TechCorp Solutions Ltd',
            'email' => 'contact@techcorp.com',
            'supplier_primary_email' => 'primary@techcorp.com',
            'supplier_secondary_email' => 'secondary@techcorp.com',
            'supplier_type' => 'COMPANY',
            'supplier_business_name' => 'TechCorp Solutions Limited',
            'supplier_business_register_no' => 'REG123456789',
            'contact_no' => '+94771234567',
            'address' => '123 Business Street, Colombo 03, Sri Lanka',
            'description' => 'Leading technology supplier specializing in computer hardware and software',
            'supplier_website' => 'https://www.techcorp.com',
            'supplier_tel_no' => '+94112345678',
            'supplier_mobile' => '+94771234567',
            'supplier_fax' => '+94112345679',
            'supplier_city' => 'Colombo',
            'supplier_location_latitude' => '6.9271',
            'supplier_location_longitude' => '79.8612',
            'asset_categories' => 'Electronics,Office Equipment,Software'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'email' => 'Primary email address for supplier identification (UNIQUE)',
                'supplier_type' => 'Options: Individual, COMPANY',
                'asset_categories' => 'Comma-separated list of category names - will create if not exists',
                'contact_no' => 'Phone number with country code',
                'validation_notes' => 'Asset categories will be created automatically if they do not exist. Email addresses must be unique.'
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
                    $transformedData[$dbField] = $transformedValue['value'];
                } else {
                    $errors[] = $transformedValue['error'];
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
                case 'supplier_primary_email':
                case 'supplier_secondary_email':
                    // Email validation
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return [
                            'success' => false,
                            'error' => "Field '{$fieldName}' must be a valid email address, got '{$value}'"
                        ];
                    }
                    return ['success' => true, 'value' => $value];

                case 'contact_no':
                    // Phone number validation (basic)
                    if (strlen($value) < 10) {
                        return [
                            'success' => false,
                            'error' => "Field '{$fieldName}' must be a valid phone number, got '{$value}'"
                        ];
                    }
                    return ['success' => true, 'value' => $value];

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
}