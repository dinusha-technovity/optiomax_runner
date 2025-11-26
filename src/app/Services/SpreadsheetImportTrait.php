<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Exception;

trait SpreadsheetImportTrait
{
    protected $excelReaderService;

    /**
     * Get allowed MIME types for spreadsheet files
     */
    protected function getAllowedMimeTypes(): array
    {
        return [
            'text/csv',
            'application/csv',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
            'application/octet-stream' // Sometimes Excel files come with this MIME
        ];
    }

    /**
     * Validate spreadsheet file (CSV or Excel)
     */
    protected function validateSpreadsheetFile($filePath): array
    {
        // Check if file exists
        if (!Storage::disk('s3')->exists($filePath)) {
            return [
                'success' => false,
                'message' => 'File not found in storage',
                'error_code' => 'FILE_NOT_FOUND'
            ];
        }

        // Check file size
        $maxFileSize = $this->maxFileSize ?? (100 * 1024 * 1024); // 100MB default
        $fileSize = Storage::disk('s3')->size($filePath);
        if ($fileSize > $maxFileSize) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . ($maxFileSize / 1024 / 1024) . 'MB',
                'error_code' => 'FILE_TOO_LARGE'
            ];
        }

        // Check MIME type and extension
        $mimeType = Storage::disk('s3')->mimeType($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowedExtensions = ['csv', 'xlsx', 'xls'];
        
        // Log for debugging
        \Log::info('File validation', [
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'allowed_mimes' => $this->getAllowedMimeTypes(),
            'allowed_extensions' => $allowedExtensions
        ]);
        
        // Accept if either MIME type is valid OR extension is valid
        $validMimeType = in_array($mimeType, $this->getAllowedMimeTypes());
        $validExtension = in_array($extension, $allowedExtensions);
        
        if (!$validMimeType && !$validExtension) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only CSV, XLSX, and XLS files are allowed. Detected: ' . $mimeType . ' (.' . $extension . ')',
                'error_code' => 'INVALID_FILE_TYPE'
            ];
        }

        return ['success' => true];
    }

    /**
     * Read spreadsheet file (CSV or Excel) and return records
     */
    protected function readSpreadsheetFile($filePath, int $maxRows = 20000): array
    {
        try {
            if (!isset($this->excelReaderService)) {
                $this->excelReaderService = app(ExcelReaderService::class);
            }

            // Detect file type
            $fileType = $this->excelReaderService->detectFileType($filePath);
            
            // Read file based on type
            if ($fileType === 'csv') {
                $records = $this->readCsvFileInternal($filePath);
            } else if (in_array($fileType, ['xlsx', 'xls'])) {
                $excelData = $this->excelReaderService->readExcelFile($filePath);
                
                if (!$excelData['success']) {
                    return [
                        'success' => false,
                        'message' => $excelData['message'] ?? 'Failed to read Excel file',
                        'error_code' => 'EXCEL_READ_ERROR'
                    ];
                }
                
                $records = $excelData['data'];
            } else {
                return [
                    'success' => false,
                    'message' => 'Unsupported file format. Please upload CSV, XLSX, or XLS files.',
                    'error_code' => 'UNSUPPORTED_FORMAT'
                ];
            }

            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'File is empty or contains no data rows',
                    'error_code' => 'EMPTY_FILE'
                ];
            }

            if (count($records) > $maxRows) {
                return [
                    'success' => false,
                    'message' => "File contains more than {$maxRows} rows. Please split into smaller files.",
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
                'message' => 'Failed to read file: ' . $e->getMessage(),
                'error_code' => 'FILE_READ_ERROR'
            ];
        }
    }

    /**
     * Internal method to read CSV files using League\Csv
     */
    private function readCsvFileInternal($filePath): array
    {
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

        return $records;
    }
}
