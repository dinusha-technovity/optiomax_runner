<?php

namespace App\Http\Controllers;

use App\Services\MasterDocumentService;
use App\Services\AssetItemsCsvImportService;
use App\Services\SupplierCsvImportService;
use App\Services\CustomerCsvImportService;
use App\Services\AssetCategoryCsvImportService;
use App\Services\AssetSubCategoryCsvImportService;
use App\Services\AssetCsvImportService;
use App\Services\ItemCsvImportService;
use App\Services\AssetAvailabilityTermTypeCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseFormatSame;
use App\Models\tenants;
use App\Jobs\ProcessCsvImportJob;
use App\Services\CsvChunkingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class MasterDocumentController extends Controller
{
    protected $MasterDocumentService;
    protected $csvImportService;
    protected $supplierCsvImportService;
    protected $customerCsvImportService;
    protected $assetCategoryCsvImportService;
    protected $assetSubCategoryCsvImportService;
    protected $assetCsvImportService;
    protected $itemCsvImportService;
    protected $assetAvailabilityTermTypeCsvImportService;

    public function __construct(
        MasterDocumentService $MasterDocumentService, 
        AssetItemsCsvImportService $csvImportService,
        SupplierCsvImportService $supplierCsvImportService,
        CustomerCsvImportService $customerCsvImportService,
        AssetCategoryCsvImportService $assetCategoryCsvImportService,
        AssetSubCategoryCsvImportService $assetSubCategoryCsvImportService,
        AssetCsvImportService $assetCsvImportService,
        ItemCsvImportService $itemCsvImportService,
        AssetAvailabilityTermTypeCsvImportService $assetAvailabilityTermTypeCsvImportService
    ) {
        $this->MasterDocumentService = $MasterDocumentService;
        $this->csvImportService = $csvImportService;
        $this->supplierCsvImportService = $supplierCsvImportService;
        $this->customerCsvImportService = $customerCsvImportService;
        $this->assetCategoryCsvImportService = $assetCategoryCsvImportService;
        $this->assetSubCategoryCsvImportService = $assetSubCategoryCsvImportService;
        $this->assetCsvImportService = $assetCsvImportService;
        $this->itemCsvImportService = $itemCsvImportService;
        $this->assetAvailabilityTermTypeCsvImportService = $assetAvailabilityTermTypeCsvImportService;
    }

    /**
     * Upload CSV file for asset items bulk import
     */
    public function uploadAssetItemsCsv(Request $request)
    {
        $authUser = Auth::user();
        $tenantId = $authUser->tenant_id;
        $userId = $authUser->id;

        // Validate the request
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:51200', // 50MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('csv_file');
            
            // Generate unique filename
            $fileName = 'asset_items_csv/' . $tenantId . '/' . time() . '_' . $file->getClientOriginalName();
            
            // Store file in MinIO (S3)
            $path = $file->storeAs('', $fileName, 's3');
            
            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload CSV file'
                ], 500);
            }

            // Return file path for processing
            return response()->json([
                'success' => true,
                'message' => 'CSV file uploaded successfully',
                'data' => [
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize()
                ]
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading CSV file: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Unified CSV processing endpoint for multiple entity types
     */
    // public function processAssetItemsCsv(Request $request)
    // {
    //     $authUser = Auth::user();
    //     $tenantId = $authUser->tenant_id;
    //     $userId = $authUser->id;

    //     // Validate the request - now accepting both file_path and document_id
    //     $validator = Validator::make($request->all(), [
    //         'document_id' => 'required_without:file_path|integer',
    //         'import_type' => 'required|string|in:asset_items,suppliers,customers,asset_categories,asset_sub_categories,assets,items,asset_availability_term_types',
    //         'dry_run' => 'boolean',
    //         'delete_after_import' => 'boolean',
    //         'batch_size' => 'integer|min:100|max:5000'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $filePath = null;
    //         $importType = $request->input('import_type', 'asset_items');
    //         $options = [
    //             'dry_run' => $request->boolean('dry_run', false),
    //             'delete_after_import' => $request->boolean('delete_after_import', false),
    //             'batch_size' => $request->integer('batch_size', 1000)
    //         ];

    //         // Get file path from document ID if provided
    //         if ($request->has('document_id')) {
    //             $documentId = $request->input('document_id');
                
    //             // Get the document using the service
    //             $uploadedDocResponse = $this->MasterDocumentService->getUploadeddocs($documentId);

    //             if (!$uploadedDocResponse['success']) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Document not found'
    //                 ], 404);
    //             }

    //             $filePath = $uploadedDocResponse['data'][0]['get_document_media_file'];
    //         } else {
    //             // Use the provided file path directly
    //             $filePath = $request->input('file_path');
    //         }

    //         // Validate file exists in MinIO
    //         if (!Storage::disk('s3')->exists($filePath)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'CSV file not found in storage'
    //             ], 404);
    //         }

    //         // Validate file type (should be CSV)
    //         $mimeType = Storage::disk('s3')->mimeType($filePath);
    //         $allowedMimeTypes = ['text/csv', 'application/csv', 'text/plain'];
            
    //         if (!in_array($mimeType, $allowedMimeTypes)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid file type. Only CSV files are allowed for processing.'
    //             ], 422);
    //         }

    //         // Process the CSV file based on import type
    //         $result = null;
    //         switch ($importType) {
    //             case 'asset_items':
    //                 $result = $this->csvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'suppliers':
    //                 $result = $this->supplierCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'customers':
    //                 $result = $this->customerCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'asset_categories':
    //                 $result = $this->assetCategoryCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'asset_sub_categories':
    //                 $result = $this->assetSubCategoryCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'assets':
    //                 $result = $this->assetCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'items':
    //                 $result = $this->itemCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             case 'asset_availability_term_types':
    //                 $result = $this->assetAvailabilityTermTypeCsvImportService->processCsvFile($filePath, $tenantId, $userId, $options);
    //                 break;
    //             default:
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Invalid import type specified'
    //                 ], 422);
    //         }

    //         // If this is a dry run, return validation results directly from service
    //         if ($options['dry_run']) {
    //             return response()->json($result);
    //         }

    //         // Add file information to the result
    //         if ($result['success'] && isset($result['data'])) {
    //             $result['data']['file_info'] = [
    //                 'file_path' => $filePath,
    //                 'document_id' => $request->input('document_id'),
    //                 'file_size' => Storage::disk('s3')->size($filePath),
    //                 'mime_type' => $mimeType,
    //                 'import_type' => $importType
    //             ];
    //         }

    //         // Clean up the uploaded file after processing (optional)
    //         if ($result['success'] && 
    //             $options['delete_after_import'] && 
    //             !$request->has('document_id')) {
    //             Storage::disk('s3')->delete($filePath);
    //         }

    //         return response()->json($result);

    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error processing CSV file: ' . $th->getMessage()
    //         ], 500);
    //     }
    // }
    public function processAssetItemsCsv(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'job_id' => 'required|integer',
                'tenant_id' => 'required|integer',
                'user_id' => 'required|integer',
                'import_type' => 'required|string|in:asset_items,suppliers,customers,asset_categories,asset_sub_categories,assets,items,asset_availability_term_types',
                'options' => 'sometimes|array',
                'dry_run' => 'sometimes|boolean'
            ]);

            $jobId = $validated['job_id'];
            $tenantId = $validated['tenant_id'];
            $userId = $validated['user_id'];
            $importType = $validated['import_type'];
            $options = $validated['options'] ?? [];
            $dryRun = $validated['dry_run'] ?? false;

            Log::info('CSV import request received', [
                'import_type' => $importType,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'dry_run' => $dryRun
            ]);

            // Get tenant data and set up database connection
            $tenant = tenants::where('id', $tenantId)
                ->where('activate', true)
                ->where('is_tenant_blocked', false)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found or inactive',
                    'error_code' => 'TENANT_NOT_FOUND'
                ], 404);
            }

            // Configure tenant database connection
            try {
                DB::beginTransaction();
                $this->setupTenantDatabase($tenant);
                DB::commit();

                // Additional verification - log current connection details
                $this->logCurrentDatabaseConnection();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to setup tenant database', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to setup tenant database: ' . $e->getMessage(),
                    'error_code' => 'DATABASE_SETUP_ERROR'
                ], 500);
            }

            $job = DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create import job record',
                    'error_code' => 'JOB_CREATION_ERROR'
                ], 500);
            }

            // Validate file exists in MinIO
            // if (!Storage::disk('s3')->exists($job->file_path)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'CSV file not found in storage',
            //         'error_code' => 'FILE_NOT_FOUND'
            //     ], 404);
            // }
            // dd($job);
            // // Validate file type (should be CSV)
            // $mimeType = Storage::disk('s3')->mimeType($job->file_path);
            // $allowedMimeTypes = ['text/csv', 'application/csv', 'text/plain'];
            
            // if (!in_array($mimeType, $allowedMimeTypes)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Invalid file type. Only CSV files are allowed for processing.'
            //     ], 422);
            // }

            // Analyze file for chunking strategy
            $chunkingService = app(CsvChunkingService::class);
            $fileAnalysis = $chunkingService->analyzeFile($job->file_path);

            Log::info('File analysis completed', [
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'analysis' => $fileAnalysis
            ]);

            // Update job with analysis results
            DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
                'total_rows' => $fileAnalysis['estimated_rows'],
                'statistics' => json_encode([
                    'file_analysis' => $fileAnalysis,
                    'chunking_enabled' => $fileAnalysis['should_chunk'],
                    'tenant_info' => [
                        'tenant_name' => $tenant->tenant_name,
                        'db_name' => $tenant->db_name
                    ]
                ]),
                'updated_at' => now()
            ]);

            if ($fileAnalysis['should_chunk'] && !$dryRun) {
                // Process large files with chunking
                $this->processLargeFile($job->file_path, $importType, $tenantId, $userId, $jobId, $options, $chunkingService, $tenant);
            } else {
                // Process small files or dry run directly
                $this->processSmallFile($job->file_path, $importType, $tenantId, $userId, $jobId, $options, $tenant);
            }

            return response()->json([
                'success' => true,
                'message' => 'CSV import job has been queued successfully',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenant->tenant_name,
                    'estimated_rows' => $fileAnalysis['estimated_rows'],
                    'will_be_chunked' => $fileAnalysis['should_chunk'],
                    'estimated_chunks' => $fileAnalysis['recommended_chunks'],
                    'actual_file_path' => $job->file_path
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('CSV import request failed', [
                'tenant_id' => $tenantId ?? null,
                'file_path' => $validated['file_path'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to queue CSV import: ' . $e->getMessage(),
                'error_code' => 'QUEUE_ERROR'
            ], 500);
        }
    }

    /**
     * Test and configure S3 connection with detailed debugging
     */
    private function testAndConfigureS3Connection(): array
    {
        try {
            // Get S3 configuration
            $s3Config = [
                'driver' => config('filesystems.disks.s3.driver'),
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
                'region' => config('filesystems.disks.s3.region'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'url' => config('filesystems.disks.s3.url'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
            ];

            Log::info('S3 Configuration check', [
                'config' => array_merge($s3Config, [
                    'key' => $s3Config['key'] ? 'SET' : 'NOT SET',
                    'secret' => $s3Config['secret'] ? 'SET' : 'NOT SET'
                ])
            ]);

            // Check required configuration
            if (empty($s3Config['key']) || empty($s3Config['secret']) || empty($s3Config['bucket'])) {
                return [
                    'success' => false,
                    'message' => 'S3 configuration incomplete. Check AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET in .env',
                    'missing_config' => [
                        'key' => empty($s3Config['key']),
                        'secret' => empty($s3Config['secret']),
                        'bucket' => empty($s3Config['bucket'])
                    ]
                ];
            }

            // Test basic S3 operations
            try {
                // Try to list files (this tests connection)
                $files = Storage::disk('s3')->files('', true);
                
                Log::info('S3 connection test successful', [
                    'files_count' => count($files),
                    'sample_files' => array_slice($files, 0, 3)
                ]);

                return [
                    'success' => true,
                    'message' => 'S3 connection verified',
                    'files_found' => count($files)
                ];

            } catch (\Exception $e) {
                Log::error('S3 connection test failed', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ]);

                // Try to provide more specific error information
                $errorMessage = 'S3 connection failed: ' . $e->getMessage();
                
                if (strpos($e->getMessage(), 'InvalidAccessKeyId') !== false) {
                    $errorMessage = 'Invalid S3 Access Key ID. Check AWS_ACCESS_KEY_ID in .env';
                } elseif (strpos($e->getMessage(), 'SignatureDoesNotMatch') !== false) {
                    $errorMessage = 'Invalid S3 Secret Access Key. Check AWS_SECRET_ACCESS_KEY in .env';
                } elseif (strpos($e->getMessage(), 'NoSuchBucket') !== false) {
                    $errorMessage = 'S3 bucket does not exist. Check AWS_BUCKET in .env';
                } elseif (strpos($e->getMessage(), 'Connection') !== false) {
                    $errorMessage = 'Cannot connect to S3/MinIO. Check AWS_ENDPOINT and network connectivity';
                }

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'original_error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'S3 configuration error: ' . $e->getMessage(),
                'error_class' => get_class($e)
            ];
        }
    }

    /**
     * Validate and normalize file path for S3 storage
     */
    private function validateAndNormalizeFilePath(string $filePath): array
    {
        try {
            // Remove any leading slashes for S3 compatibility
            $normalizedPath = ltrim($filePath, '/');
            
            // Log the path transformation
            Log::info('File path normalization', [
                'original' => $filePath,
                'normalized' => $normalizedPath
            ]);
            
            // Basic validation - check if path contains valid characters
            if (empty($normalizedPath)) {
                return [
                    'success' => false,
                    'message' => 'File path cannot be empty'
                ];
            }
            
            return [
                'success' => true,
                'path' => $normalizedPath
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid file path format: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get alternative file path formats to try
     */
    private function getAlternativeFilePaths(string $originalPath): array
    {
        $alternatives = [];
        
        // Try with leading slash
        if (!str_starts_with($originalPath, '/')) {
            $alternatives[] = '/' . $originalPath;
        }
        
        // Try without leading slash (already normalized, but add for completeness)
        $withoutLeadingSlash = ltrim($originalPath, '/');
        if ($withoutLeadingSlash !== $originalPath) {
            $alternatives[] = $withoutLeadingSlash;
        }
        
        // Parse the path components
        $pathParts = explode('/', trim($originalPath, '/'));
        $fileName = end($pathParts);
        
        if (count($pathParts) > 1) {
            // Try flattened path (just filename)
            $alternatives[] = $fileName;
            
            // Try common folder structures
            $alternatives[] = 'uploads/' . $fileName;
            $alternatives[] = 'csv/' . $fileName;
            $alternatives[] = 'imports/' . $fileName;
            
            // Try the immediate parent folder + filename
            if (count($pathParts) >= 2) {
                $parentFolder = $pathParts[count($pathParts) - 2];
                $alternatives[] = $parentFolder . '/' . $fileName;
            }
            
            // For asset_availability_term_types, try variations
            if (strpos($originalPath, 'asset_availability_term_types') !== false || 
                strpos($originalPath, 'assets_availability_terms_type') !== false) {
                $alternatives[] = str_replace('assets_availability_terms_type', 'asset_availability_term_types', $originalPath);
                $alternatives[] = str_replace('asset_availability_term_types', 'assets_availability_terms_type', $originalPath);
            }
        }
        
        // Remove duplicates and the original path
        $alternatives = array_unique($alternatives);
        $alternatives = array_filter($alternatives, function($path) use ($originalPath) {
            return $path !== $originalPath;
        });
        
        Log::info('Generated alternative paths', [
            'original' => $originalPath,
            'alternatives' => $alternatives
        ]);
        
        return array_values($alternatives);
    }

    private function setupTenantDatabase($tenant)
    {
        // Configure tenant connection
        Config::set("database.connections.tenant", [
            'driver' => 'pgsql',
            'host' => $tenant->db_host,
            'port' => env('DB_PORT', '5432'),
            'database' => $tenant->db_name,
            'username' => $tenant->db_user,
            'password' => $tenant->db_password,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        // Test connection
        try {
            DB::connection('tenant')->getPdo();
            Log::info("Tenant database connection successful for: {$tenant->db_name}");
        } catch (\Exception $e) {
            throw new \Exception("Failed to connect to tenant database: " . $e->getMessage());
        }
        
        // Switch to tenant connection for migrations
        $originalDefaultConnection = Config::get('database.default');
        Config::set('database.default', 'tenant');
    }

    /**
     * Configure database connection for the tenant
     */
    private function configureTenantDatabase(tenants $tenant): void
    {
        try {
            // Validate tenant database configuration
            if (empty($tenant->db_name) || empty($tenant->db_host) || empty($tenant->db_user)) {
                throw new \Exception('Tenant database configuration is incomplete');
            }

            // Configure dynamic database connection for tenant
            config([
                'database.connections.tenant' => [
                    'driver' => 'pgsql',
                    'host' => $tenant->db_host,
                    'port' => env('DB_PORT', '5432'),
                    'database' => $tenant->db_name,
                    'username' => $tenant->db_user,
                    'password' => $tenant->db_password,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ]
            ]);

            // Clear any existing tenant connection
            DB::purge('tenant');

            // Test the connection
            DB::connection('tenant')->getPdo();

            // Verify connection details
            $this->verifyTenantDatabaseConnection($tenant);

            Log::info('Tenant database connection established', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->tenant_name,
                'db_host' => $tenant->db_host,
                'db_name' => $tenant->db_name
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to configure tenant database connection', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->tenant_name,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to establish tenant database connection: ' . $e->getMessage());
        }
    }

    /**
     * Verify tenant database connection is working correctly
     */
    private function verifyTenantDatabaseConnection(tenants $tenant): void
    {
        try {
            // Method 1: Check current database name
            $currentDatabase = DB::connection('tenant')->select('SELECT current_database() as db_name')[0]->db_name;
            
            if ($currentDatabase !== $tenant->db_name) {
                throw new \Exception("Database connection mismatch. Expected: {$tenant->db_name}, Connected to: {$currentDatabase}");
            }

            // Method 2: Check connection configuration
            $config = DB::connection('tenant')->getConfig();
            if ($config['database'] !== $tenant->db_name) {
                throw new \Exception("Configuration mismatch. Expected database: {$tenant->db_name}, Config shows: {$config['database']}");
            }

            // Method 3: Test with a simple query to verify we can access tenant tables
            $tableExists = DB::connection('tenant')->select(
                "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = 'import_jobs'
                ) as exists"
            )[0]->exists;

            if (!$tableExists) {
                throw new \Exception("Cannot access tenant-specific tables. Tenant database might not be properly configured.");
            }

            // Method 4: Verify tenant isolation by checking tenant-specific data
            $tenantSpecificCheck = DB::connection('tenant')->select(
                "SELECT COUNT(*) as count FROM information_schema.tables 
                 WHERE table_schema = 'public'"
            )[0]->count;

            Log::info('Tenant database connection verified successfully', [
                'tenant_id' => $tenant->id,
                'connected_database' => $currentDatabase,
                'expected_database' => $tenant->db_name,
                'tables_found' => $tenantSpecificCheck,
                'import_jobs_table_exists' => $tableExists
            ]);

        } catch (\Exception $e) {
            Log::error('Tenant database connection verification failed', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->tenant_name,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Tenant database connection verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create import job with database connection verification
     */
    private function createImportJobWithVerification(tenants $tenant, int $tenantId, int $userId, string $importType, string $filePath, array $options): int
    {
        try {
            // Verify we're still connected to the correct tenant database
            $currentDb = DB::connection('tenant')->select('SELECT current_database() as db_name')[0]->db_name;
            
            if ($currentDb !== $tenant->db_name) {
                throw new \Exception("Database connection lost. Expected: {$tenant->db_name}, Currently connected to: {$currentDb}");
            }

            // Create import job record in tenant database
            $jobId = DB::connection('tenant')->table('import_jobs')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => $importType . '_csv',
                'status' => 'queued',
                'file_name' => basename($filePath),
                'file_path' => $filePath,
                'file_size' => Storage::disk('s3')->size($filePath),
                'options' => json_encode($options),
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Verify the job was created in the correct database
            $verifyJob = DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->first();
            
            if (!$verifyJob) {
                throw new \Exception('Failed to create import job in tenant database');
            }

            Log::info('Import job created successfully in tenant database', [
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->tenant_name,
                'database' => $currentDb,
                'import_type' => $importType
            ]);

            return $jobId;

        } catch (\Exception $e) {
            Log::error('Failed to create import job in tenant database', [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->tenant_name,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Log current database connection details for debugging
     */
    private function logCurrentDatabaseConnection(): void
    {
        try {
            // Check default connection
            $defaultDb = DB::connection()->select('SELECT current_database() as db_name')[0]->db_name ?? 'unknown';
            
            // Check tenant connection
            $tenantDb = DB::connection('tenant')->select('SELECT current_database() as db_name')[0]->db_name ?? 'unknown';

            // Get connection configurations
            $defaultConfig = DB::connection()->getConfig();
            $tenantConfig = DB::connection('tenant')->getConfig();

            Log::info('Current database connections', [
                'default_connection' => [
                    'database' => $defaultDb,
                    'host' => $defaultConfig['host'] ?? 'unknown',
                    'username' => $defaultConfig['username'] ?? 'unknown'
                ],
                'tenant_connection' => [
                    'database' => $tenantDb,
                    'host' => $tenantConfig['host'] ?? 'unknown',
                    'username' => $tenantConfig['username'] ?? 'unknown'
                ]
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to log database connection details', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Debug S3 connection and list files with your specific credentials
     */
    public function debugS3Files(Request $request)
    {
        try {
            $validated = $request->validate([
                'path_prefix' => 'sometimes|string'
            ]);
            
            $pathPrefix = $validated['path_prefix'] ?? '';
            
            // Test basic S3 operations
            $results = [];
            
            // Test 1: Check S3 configuration
            $s3Config = [
                'driver' => config('filesystems.disks.s3.driver'),
                'key' => config('filesystems.disks.s3.key') ? 'SET' : 'NOT SET',
                'secret' => config('filesystems.disks.s3.secret') ? 'SET' : 'NOT SET',
                'region' => config('filesystems.disks.s3.region'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'url' => config('filesystems.disks.s3.url'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint')
            ];
            $results['s3_config'] = $s3Config;
            
            // Test 2: Try to list files in bucket
            try {
                $files = Storage::disk('s3')->files($pathPrefix);
                $results['list_files'] = [
                    'success' => true,
                    'file_count' => count($files),
                    'sample_files' => array_slice($files, 0, 10)
                ];
            } catch (\Exception $e) {
                $results['list_files'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ];
            }
            
            // Test 3: Try to check if specific file exists
            if (isset($validated['file_path'])) {
                try {
                    $testPath = $validated['file_path'];
                    $normalizedPath = ltrim($testPath, '/');
                    $exists = Storage::disk('s3')->exists($normalizedPath);
                    $results['file_check'] = [
                        'success' => true,
                        'path_checked' => $normalizedPath,
                        'exists' => $exists
                    ];
                    
                    if ($exists) {
                        $results['file_info'] = [
                            'size' => Storage::disk('s3')->size($normalizedPath),
                            'last_modified' => Storage::disk('s3')->lastModified($normalizedPath),
                            'mime_type' => Storage::disk('s3')->mimeType($normalizedPath)
                        ];
                    }
                } catch (\Exception $e) {
                    $results['file_check'] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'path_checked' => $normalizedPath ?? $validated['file_path']
                    ];
                }
            }
            
            // Test 4: Try to create a test file
            try {
                $testContent = 'Test file created at ' . now();
                $testFileName = 'test-files/connection-test-' . time() . '.txt';
                Storage::disk('s3')->put($testFileName, $testContent);
                
                // Verify it was created
                $created = Storage::disk('s3')->exists($testFileName);
                
                // Clean up
                if ($created) {
                    Storage::disk('s3')->delete($testFileName);
                }
                
                $results['write_test'] = [
                    'success' => $created,
                    'test_file' => $testFileName
                ];
            } catch (\Exception $e) {
                $results['write_test'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'S3 connection test completed',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'S3 connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test S3 connection specifically for MinIO
     */
    public function testMinIOConnection(Request $request)
    {
        try {
            // Force refresh S3 config
            $this->refreshS3Config();
            
            Log::info('Testing MinIO connection with current config', [
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint')
            ]);
            
            // Test basic MinIO operations
            $results = [];
            
            // Test 1: List buckets/files
            try {
                $files = Storage::disk('s3')->allFiles('');
                $directories = Storage::disk('s3')->allDirectories('');
                
                $results['bucket_access'] = [
                    'success' => true,
                    'total_files' => count($files),
                    'total_directories' => count($directories),
                    'sample_files' => array_slice($files, 0, 5),
                    'sample_directories' => array_slice($directories, 0, 5)
                ];
            } catch (\Exception $e) {
                $results['bucket_access'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ];
                
                // If we can't list files, the connection is definitely failing
                return response()->json([
                    'success' => false,
                    'message' => 'MinIO connection failed: ' . $e->getMessage(),
                    'results' => $results,
                    'suggestions' => [
                        'Check if MinIO server is running at http://192.168.8.114:9000',
                        'Verify bucket "optiomax" exists',
                        'Verify credentials are correct: admin / chamod123',
                        'Check network connectivity to 192.168.8.114'
                    ]
                ], 500);
            }
            
            // Test 2: Create and delete test file
            try {
                $testFileName = 'test-connection-' . time() . '.txt';
                $testContent = 'MinIO connection test at ' . now();
                
                // Create file
                $created = Storage::disk('s3')->put($testFileName, $testContent);
                
                // Check if it exists
                $exists = Storage::disk('s3')->exists($testFileName);
                
                // Read content back
                $readContent = $exists ? Storage::disk('s3')->get($testFileName) : null;
                
                // Delete test file
                $deleted = $exists ? Storage::disk('s3')->delete($testFileName) : false;
                
                $results['file_operations'] = [
                    'success' => true,
                    'created' => $created,
                    'exists_after_create' => $exists,
                    'content_matches' => $readContent === $testContent,
                    'deleted' => $deleted
                ];
                
            } catch (\Exception $e) {
                $results['file_operations'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'MinIO connection test completed successfully',
                'results' => $results,
                'config_used' => [
                    'endpoint' => config('filesystems.disks.s3.endpoint'),
                    'bucket' => config('filesystems.disks.s3.bucket'),
                    'region' => config('filesystems.disks.s3.region'),
                    'credentials_set' => !empty(config('filesystems.disks.s3.key'))
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'MinIO connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'suggestions' => [
                    'Verify MinIO server is running',
                    'Check network connectivity',
                    'Verify bucket exists',
                    'Check credentials'
                ]
            ], 500);
        }
    }

    /**
     * Refresh S3 configuration to ensure latest .env values
     */
    private function refreshS3Config(): void
    {
        // Clear any cached config
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        
        // Manually set S3 config to ensure it's using latest .env values
        Config::set('filesystems.disks.s3', [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ]);
        
        // Purge any existing S3 connections
        Storage::forgetDisk('s3');
    }

    /**
     * Upload CSV file for suppliers bulk import
     */
    public function uploadSuppliersCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for suppliers
    }

    /**
     * Upload CSV file for customers bulk import
     */
    public function uploadCustomersCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for customers
    }

    /**
     * Upload CSV file for asset categories bulk import
     */
    public function uploadAssetCategoriesCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for asset categories
    }

    /**
     * Upload CSV file for asset sub-categories bulk import
     */
    public function uploadAssetSubCategoriesCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for asset sub-categories
    }

    /**
     * Upload CSV file for assets bulk import
     */
    public function uploadAssetsCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for assets
    }

    /**
     * Upload CSV file for items bulk import
     */
    public function uploadItemsCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for items
    }

    /**
     * Upload CSV file for asset availability term types bulk import
     */
    public function uploadAssetAvailabilityTermTypesCsv(Request $request)
    {
        // Similar implementation as uploadAssetItemsCsv, tailored for asset availability term types
    }

    /**
     * Test method to verify tenant database switching (for debugging)
     */
    public function testTenantDatabaseConnection(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenant_id' => 'required|integer'
            ]);

            $tenant = tenants::where('id', $validated['tenant_id'])
                ->where('activate', true)
                ->where('is_tenant_blocked', false)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found or inactive'
                ], 404);
            }

            // Test before configuration
            $beforeDefault = DB::connection()->select('SELECT current_database() as db_name')[0]->db_name;

            // Configure tenant database
            $this->configureTenantDatabase($tenant);

            // Test after configuration
            $afterDefault = DB::connection()->select('SELECT current_database() as db_name')[0]->db_name;
            $tenantDb = DB::connection('tenant')->select('SELECT current_database() as db_name')[0]->db_name;

            // Test table access
            $importJobsExists = DB::connection('tenant')->select(
                "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = 'import_jobs'
                ) as exists"
            )[0]->exists;

            // Count records in import_jobs (if table exists)
            $importJobsCount = 0;
            if ($importJobsExists) {
                $importJobsCount = DB::connection('tenant')->table('import_jobs')->count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant_info' => [
                        'id' => $tenant->id,
                        'name' => $tenant->tenant_name,
                        'db_name' => $tenant->db_name,
                        'db_host' => $tenant->db_host
                    ],
                    'database_connections' => [
                        'default_before' => $beforeDefault,
                        'default_after' => $afterDefault,
                        'tenant_connection' => $tenantDb
                    ],
                    'verification' => [
                        'connection_switched_correctly' => $tenantDb === $tenant->db_name,
                        'import_jobs_table_exists' => $importJobsExists,
                        'import_jobs_count' => $importJobsCount
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import job status
     */
    public function getImportJobStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'job_id' => 'required|integer',
                'tenant_id' => 'required|integer'
            ]);

            // Get tenant and configure database connection
            $tenant = tenants::where('id', $validated['tenant_id'])
                ->where('activate', true)
                ->where('is_tenant_blocked', false)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found or inactive'
                ], 404);
            }

            $this->configureTenantDatabase($tenant);

            $job = DB::connection('tenant')->table('import_jobs')
                ->where('id', $validated['job_id'])
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import job not found'
                ], 404);
            }

            $statistics = json_decode($job->statistics, true) ?? [];
            $errorDetails = json_decode($job->error_details, true) ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $job->id,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->tenant_name,
                    'status' => $job->status,
                    'message' => $job->message,
                    'progress_percentage' => $job->progress_percentage,
                    'total_rows' => $job->total_rows,
                    'total_processed' => $job->total_processed,
                    'total_inserted' => $job->total_inserted,
                    'total_updated' => $job->total_updated,
                    'total_errors' => $job->total_errors,
                    'started_at' => $job->started_at,
                    'completed_at' => $job->completed_at,
                    'error_report_path' => $statistics['error_report_path'] ?? null,
                    'error_details' => array_slice($errorDetails, 0, 10), // Return first 10 errors
                    'statistics' => $statistics
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get job status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download error report
     */
    public function downloadErrorReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'job_id' => 'required|integer',
                'tenant_id' => 'required|integer'
            ]);

            // Get tenant and configure database connection
            $tenant = tenants::where('id', $validated['tenant_id'])
                ->where('activate', true)
                ->where('is_tenant_blocked', false)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found or inactive'
                ], 404);
            }

            $this->configureTenantDatabase($tenant);

            $job = DB::connection('tenant')->table('import_jobs')
                ->where('id', $validated['job_id'])
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import job not found'
                ], 404);
            }

            $statistics = json_decode($job->statistics, true) ?? [];
            $errorReportPath = $statistics['error_report_path'] ?? null;

            if (!$errorReportPath || !Storage::disk('s3')->exists($errorReportPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error report not available'
                ], 404);
            }

            $errorContent = Storage::disk('s3')->get($errorReportPath);
            $fileName = basename($errorReportPath);

            return response($errorContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (Exception $e) {
            Log::error('Error report download failed', [
                'job_id' => $validated['job_id'] ?? null,
                'tenant_id' => $validated['tenant_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download error report: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processLargeFile(
        string $filePath,
        string $importType,
        int $tenantId,
        int $userId,
        int $jobId,
        array $options,
        CsvChunkingService $chunkingService,
        tenants $tenant
    ): void {
        // Update status in tenant database
        DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
            'status' => 'preparing',
            'message' => 'Preparing file for chunked processing...',
            'updated_at' => now()
        ]);

        // Chunk the data
        $chunkResult = $chunkingService->chunkCsvData($filePath, $importType . '_csv', $tenantId);
        
        if (!$chunkResult['success']) {
            DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
                'status' => 'failed',
                'message' => 'Failed to chunk file: ' . $chunkResult['error'],
                'updated_at' => now()
            ]);
            return;
        }

        // Update job with chunking info
        DB::connection('tenant')->table('import_jobs')->where('id', $jobId)->update([
            'status' => 'processing',
            'message' => "Processing {$chunkResult['total_chunks']} chunks...",
            'statistics' => json_encode([
                'total_chunks' => $chunkResult['total_chunks'],
                'chunk_info' => $chunkResult['chunks'],
                'tenant_info' => [
                    'tenant_name' => $tenant->tenant_name,
                    'db_name' => $tenant->db_name
                ]
            ]),
            'updated_at' => now()
        ]);

        // Dispatch chunk processing jobs with tenant information
        foreach ($chunkResult['chunks'] as $chunkInfo) {
            $chunkData = Cache::get($chunkInfo['cache_key']);
            
            ProcessCsvImportJob::dispatch(
                $importType . '_csv',
                $filePath,
                $tenantId,
                $userId,
                $jobId,
                array_merge($options, [
                    'tenant_db_config' => [
                        'host' => $tenant->db_host,
                        'database' => $tenant->db_name,
                        'username' => $tenant->db_user,
                        'password' => $tenant->db_password
                    ]
                ]),
                $chunkData,
                $chunkInfo['chunk_index'],
                $chunkResult['total_chunks']
            )->delay(now()->addSeconds($chunkInfo['chunk_index'] * 2)); // Stagger job execution
            
            // Clean up cache after dispatching
            Cache::forget($chunkInfo['cache_key']);
        }
    }

    private function processSmallFile(
        string $filePath,
        string $importType,
        int $tenantId,
        int $userId,
        int $jobId,
        array $options,
        tenants $tenant
    ): void {
        // Dispatch single job for small files with tenant information
        ProcessCsvImportJob::dispatch(
            $importType . '_csv',
            $filePath,
            $tenantId,
            $userId,
            $jobId,
            array_merge($options, [
                'tenant_db_config' => [
                    'host' => $tenant->db_host,
                    'database' => $tenant->db_name,
                    'username' => $tenant->db_user,
                    'password' => $tenant->db_password
                ]
            ])
        );
    }
}
