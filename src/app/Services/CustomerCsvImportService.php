<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;
 
class CustomerCsvImportService
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
        
        // Define required columns for customer CSV
        $this->requiredColumns = [
            'name',
            'email'
        ];

        // CSV column mapping for customers
        $this->csvColumnMapping = [
            'name' => 'name',
            'national_id' => 'national_id',
            'primary_contact_person' => 'primary_contact_person',
            'designation' => 'designation',
            'phone_mobile' => 'phone_mobile',
            'phone_mobile_country_code' => 'phone_mobile_country_code', // Will lookup country by code
            'phone_landline' => 'phone_landline',
            'phone_landline_country_code' => 'phone_landline_country_code', // Will lookup country by code
            'phone_office' => 'phone_office',
            'phone_office_country_code' => 'phone_office_country_code', // Will lookup country by code
            'email' => 'email',
            'address' => 'address',
            'customer_type_name' => 'customer_type_name', // Will lookup customer type by name
            'billing_address' => 'billing_address',
            'payment_terms' => 'payment_terms',
            'customer_rating' => 'customer_rating',
            'notes' => 'notes',
            'status' => 'status',
            'location_latitude' => 'location_latitude',
            'location_longitude' => 'location_longitude'
        ];
    }

    /**
     * Process CSV file for customer bulk import
     */
    public function processCsvFile(string $filePath, int $tenantId, int $userId, array $options = []): array
    {
        $jobId = null;
        
        try {
            Log::info('Starting Customer CSV processing', [
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
                    'type' => 'customers_csv',
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
                    'message' => 'Customer CSV validation completed successfully',
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
            $result = $this->bulkInsertCustomers(
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

            Log::error('Customer CSV Import Error: ' . $e->getMessage(), [
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
     * Use PostgreSQL function for bulk insert customers
     */
    public function bulkInsertCustomers(array $transformedData, int $userId, int $tenantId, int $jobId = null): array
    {
        try {
            // Prepare data for PostgreSQL function
            $itemsJson = json_encode($transformedData);
            
            // Call the PostgreSQL function for customer bulk insert
            $result = DB::connection('tenant')->selectOne(
                'SELECT * FROM bulk_insert_customers_with_relationships(?, ?, ?, ?, ?, ?)',
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
            Log::error('Customer Bulk Insert Function Error: ' . $e->getMessage(), [
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
     * Generate CSV template for customers
     */
    public function generateCsvTemplate(): array
    {
        $headers = array_keys($this->csvColumnMapping);
        
        $exampleRow = [
            'name' => 'ABC Corporation Ltd',
            'national_id' => '123456789V',
            'primary_contact_person' => 'John Smith',
            'designation' => 'Procurement Manager',
            'phone_mobile' => '+94771234567',
            'phone_mobile_country_code' => '+94',
            'phone_landline' => '+94112345678',
            'phone_landline_country_code' => '+94',
            'phone_office' => '+94112345679',
            'phone_office_country_code' => '+94',
            'email' => 'john.smith@abccorp.com',
            'address' => '123 Business Street, Colombo 03, Sri Lanka',
            'customer_type_name' => 'Company',
            'billing_address' => '123 Business Street, Colombo 03, Sri Lanka',
            'payment_terms' => 'Net 30',
            'customer_rating' => '4',
            'notes' => 'Preferred customer with excellent payment history',
            'status' => 'active',
            'location_latitude' => '6.9271',
            'location_longitude' => '79.8612'
        ];

        return [
            'headers' => $headers,
            'example_data' => $exampleRow,
            'required_columns' => $this->requiredColumns,
            'lookup_instructions' => [
                'email' => 'Primary email address for customer identification (UNIQUE)',
                'customer_type_name' => 'EXACT customer type names only: Individual, Company, Department, Government',
                'country_codes' => 'Use phone codes like: +94, +1, +44, +91, etc.',
                'status' => 'Options: active, inactive, pending',
                'customer_rating' => 'Rating from 0-5',
                'validation_notes' => 'Customer types must match exactly from predefined list. Phone codes must exist in countries table.'
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

        // Validate customer type if provided
        if (!empty($row['customer_type_name'])) {
            $validCustomerTypes = ['Individual', 'Company', 'Department', 'Government'];
            if (!in_array($row['customer_type_name'], $validCustomerTypes)) {
                $errors[] = "Invalid customer type '{$row['customer_type_name']}'. Valid types are: " . implode(', ', $validCustomerTypes);
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
                
                // Special validation for customer rating
                if ($csvColumn === 'customer_rating') {
                    $rating = (int)$value;
                    if ($rating < 0 || $rating > 5) {
                        $errors[] = "Customer rating must be between 0 and 5, got '{$value}'";
                        continue;
                    }
                    $transformedData[$dbField] = $rating;
                } else {
                    $transformedData[$dbField] = $value;
                }
            }
        }

        return [
            'success' => empty($errors),
            'data' => $transformedData,
            'errors' => $errors
        ];
    }
}