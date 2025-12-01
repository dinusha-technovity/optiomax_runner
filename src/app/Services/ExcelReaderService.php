<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use Exception;

class ExcelReaderService
{
    /**
     * Read Excel file and convert to array format (similar to CSV)
     * 
     * @param string $filePath Path to file in storage
     * @return array Array of rows with headers as keys
     */
    public function readExcelFile(string $filePath): array
    {
        try {
            // Get file content from storage
            $fileContent = Storage::disk('s3')->get($filePath);
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempFile, $fileContent);

            try {
                // Detect file type and create appropriate reader
                $reader = IOFactory::createReaderForFile($tempFile);
                $reader->setReadDataOnly(true);
                
                // Load spreadsheet
                $spreadsheet = $reader->load($tempFile);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Get all data as array
                $rows = $worksheet->toArray(null, true, true, true);
                
                if (empty($rows)) {
                    throw new Exception('Excel file is empty');
                }

                // First row as headers
                $headers = array_shift($rows);
                
                // Clean headers (remove null values and trim)
                $headers = array_map(function($header) {
                    return trim($header ?? '');
                }, $headers);
                
                // Convert to associative array with headers as keys
                $data = [];
                foreach ($rows as $row) {
                    // Skip completely empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }
                    
                    $rowData = [];
                    foreach ($headers as $colKey => $header) {
                        if (!empty($header)) {
                            $rowData[$header] = $row[$colKey] ?? null;
                        }
                    }
                    
                    // Only add row if it has data
                    if (!empty(array_filter($rowData))) {
                        $data[] = $rowData;
                    }
                }

                return [
                    'success' => true,
                    'data' => $data,
                    'headers' => array_filter($headers),
                    'total_rows' => count($data)
                ];

            } finally {
                // Clean up temporary file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

        } catch (Exception $e) {
            Log::error('Excel file reading failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to read Excel file: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Detect file type from MIME type or extension
     * 
     * @param string $filePath
     * @return string 'csv', 'xlsx', 'xls', or 'unknown'
     */
    public function detectFileType(string $filePath): string
    {
        try {
            $mimeType = Storage::disk('s3')->mimeType($filePath);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Check by MIME type first
            $mimeTypeMap = [
                'text/csv' => 'csv',
                'application/csv' => 'csv',
                'text/plain' => 'csv',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-excel' => 'xls',
            ];

            if (isset($mimeTypeMap[$mimeType])) {
                return $mimeTypeMap[$mimeType];
            }

            // Fallback to extension
            $extensionMap = [
                'csv' => 'csv',
                'xlsx' => 'xlsx',
                'xls' => 'xls',
            ];

            return $extensionMap[$extension] ?? 'unknown';

        } catch (Exception $e) {
            Log::error('File type detection failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return 'unknown';
        }
    }

    /**
     * Validate if file is a supported spreadsheet format
     * 
     * @param string $filePath
     * @return array ['success' => bool, 'message' => string, 'type' => string]
     */
    public function validateSpreadsheetFile(string $filePath): array
    {
        $fileType = $this->detectFileType($filePath);

        if ($fileType === 'unknown') {
            return [
                'success' => false,
                'message' => 'Unsupported file format. Please upload CSV, XLSX, or XLS files.',
                'type' => 'unknown'
            ];
        }

        return [
            'success' => true,
            'message' => 'File format is supported',
            'type' => $fileType
        ];
    }

    /**
     * Get file statistics (row count, column count)
     * 
     * @param string $filePath
     * @return array
     */
    public function getFileStatistics(string $filePath): array
    {
        try {
            $fileType = $this->detectFileType($filePath);
            
            if ($fileType === 'csv') {
                return $this->getCsvStatistics($filePath);
            } else {
                return $this->getExcelStatistics($filePath);
            }

        } catch (Exception $e) {
            Log::error('File statistics failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get file statistics: ' . $e->getMessage()
            ];
        }
    }

    private function getCsvStatistics(string $filePath): array
    {
        $stream = Storage::disk('s3')->readStream($filePath);
        $rowCount = 0;
        $columnCount = 0;

        if ($stream) {
            while (($line = fgets($stream)) !== false) {
                $rowCount++;
                if ($rowCount === 1) {
                    $columnCount = count(str_getcsv($line));
                }
            }
            fclose($stream);
        }

        return [
            'success' => true,
            'total_rows' => max(0, $rowCount - 1), // Exclude header
            'total_columns' => $columnCount,
            'file_size' => Storage::disk('s3')->size($filePath)
        ];
    }

    private function getExcelStatistics(string $filePath): array
    {
        $fileContent = Storage::disk('s3')->get($filePath);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_stats_');
        file_put_contents($tempFile, $fileContent);

        try {
            $reader = IOFactory::createReaderForFile($tempFile);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tempFile);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();
            $columnCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            return [
                'success' => true,
                'total_rows' => max(0, $highestRow - 1), // Exclude header
                'total_columns' => $columnCount,
                'file_size' => Storage::disk('s3')->size($filePath)
            ];

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
