# Excel Import Support Setup Guide

## Overview
This setup adds support for Excel files (.xlsx and .xls) in addition to CSV for bulk data imports in the Optiomax system.

## Files Modified/Created

### 1. New Files Created
- `src/app/Services/ExcelReaderService.php` - Service to read Excel files
- `src/app/Services/SpreadsheetImportTrait.php` - Reusable trait for all import services

### 2. Modified Files
- `docker-for-Laravel/dockerfiles/app.Dockerfile` - Added PHP extensions (zip, xml)
- `src/composer.json` - Added phpoffice/phpspreadsheet library
- `src/app/Services/CsvChunkingService.php` - Added Excel file support
- `src/app/Services/AssetAvailabilityTermTypeCsvImportService.php` - Example implementation

## Installation Steps

### Step 1: Rebuild Docker Container
```bash
cd docker-for-Laravel
make down
make up
```

### Step 2: Install PHP Dependencies
```bash
make shell
composer install
exit
```

### Step 3: Update Other CSV Import Services

All other CSV import services need to be updated to use the `SpreadsheetImportTrait`. Here are the services that need updating:

1. SupplierCsvImportService.php
2. AssetItemsCsvImportService.php
3. AssetCategoryCsvImportService.php
4. AssetCsvImportService.php
5. ItemCsvImportService.php
6. CustomerCsvImportService.php
7. AssetSubCategoryCsvImportService.php

For each service, make these changes:

#### A. Add the trait to the class:
```php
class YourCsvImportService
{
    use SpreadsheetImportTrait;
    
    // ... rest of the code
}
```

#### B. Update constructor:
Remove `$allowedMimeTypes` property and any Excel service injection. The trait handles this.

**Before:**
```php
private $allowedMimeTypes;
private $excelReaderService;

public function __construct(ExcelReaderService $excelReaderService)
{
    $this->allowedMimeTypes = ['text/csv', 'application/csv', 'text/plain'];
    $this->excelReaderService = $excelReaderService;
}
```

**After:**
```php
public function __construct()
{
    // No need for allowedMimeTypes or excelReaderService
}
```

#### C. Replace validateCsvFile method:
```php
private function validateCsvFile($filePath) {
    return $this->validateSpreadsheetFile($filePath);
}
```

#### D. Replace readCsvFile method:
```php
private function readCsvFile($filePath) {
    return $this->readSpreadsheetFile($filePath, 20000); // 20000 is max rows
}
```

#### E. Remove readCsvFileInternal method if it exists:
The trait provides this functionality internally.

## Supported File Formats

After this update, the system will support:
- **CSV** (.csv) - Comma-separated values
- **Excel 2007+** (.xlsx) - Modern Excel format
- **Excel 97-2003** (.xls) - Legacy Excel format

## MIME Types Supported

The system will now accept files with these MIME types:
- `text/csv`
- `application/csv`
- `text/plain`
- `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (.xlsx)
- `application/vnd.ms-excel` (.xls)
- `application/octet-stream` (fallback)

## File Validation

The system validates:
1. File exists in storage
2. File size doesn't exceed limit (100MB default)
3. File format is CSV or Excel
4. File contains data
5. File doesn't exceed maximum rows (20,000 default)

## API Usage

The upload endpoints remain the same. Simply upload Excel files instead of CSV:

```bash
POST /api/v1/bulk_data_uploading/upload_asset_items_csv
Content-Type: multipart/form-data

file: your-file.xlsx  # or .xls or .csv
```

## Testing

1. Test with CSV files (existing functionality)
2. Test with .xlsx files (Excel 2007+)
3. Test with .xls files (Excel 97-2003)
4. Test with large files (near 100MB limit)
5. Test with files exceeding row limits
6. Test with invalid file formats

## Troubleshooting

### Issue: "Failed to read Excel file"
- Check PHP extensions are installed: `php -m | grep -E "zip|xml"`
- Ensure PhpSpreadsheet is installed: `composer show phpoffice/phpspreadsheet`

### Issue: "Unsupported file format"
- Verify file extension is .csv, .xlsx, or .xls
- Check MIME type of uploaded file

### Issue: Memory errors with large Excel files
- Increase PHP memory limit in Docker container
- Consider splitting large files into smaller chunks

## Performance Notes

- Excel files are temporarily downloaded and processed
- Large Excel files may take longer to process than CSV
- The system uses streaming where possible to minimize memory usage
- Chunk processing remains the same for all file types

## Security Considerations

- File validation prevents malicious file uploads
- Temporary files are cleaned up immediately after processing
- MIME type and extension checks prevent execution of malicious files
- File size limits prevent resource exhaustion attacks
