# Quick Start: Enable Excel Import Support

## 🚀 Quick Setup (5 Minutes)

### 1. Rebuild Container with PHP Extensions
```bash
cd docker-for-Laravel
make down
make up
```

### 2. Install Dependencies
```bash
make shell
composer install
exit
```

### 3. Verify Installation
```bash
make shell
php -m | grep -E "zip|xml"
composer show phpoffice/phpspreadsheet
exit
```

## ✅ What's Already Done

- ✅ Docker configuration updated (zip & xml extensions)
- ✅ PhpSpreadsheet library added to composer.json
- ✅ ExcelReaderService created
- ✅ SpreadsheetImportTrait created (reusable code)
- ✅ CsvChunkingService updated for Excel support
- ✅ AssetAvailabilityTermTypeCsvImportService updated (example)

## 📋 What You Need to Do

Update the remaining 7 CSV import services:

### Option 1: Automatic (Recommended)
```bash
cd docker-for-Laravel
./update-import-services.sh
```

Then manually update each service's methods as shown below.

### Option 2: Manual Update

For each of these services:
- `SupplierCsvImportService.php`
- `AssetItemsCsvImportService.php`
- `AssetCategoryCsvImportService.php`
- `AssetCsvImportService.php`
- `ItemCsvImportService.php`
- `CustomerCsvImportService.php`
- `AssetSubCategoryCsvImportService.php`

Make these 4 changes:

#### Change 1: Add trait at top of class
```php
class YourService
{
    use SpreadsheetImportTrait;  // ADD THIS LINE
    
    private $batchSize;
    // ...
```

#### Change 2: Simplify constructor
```php
// REMOVE these properties:
// private $allowedMimeTypes;
// private $excelReaderService;

public function __construct()  // Remove parameters
{
    $this->batchSize = config('app.csv_batch_size', 2000);
    $this->maxFileSize = config('app.csv_max_file_size', 100 * 1024 * 1024);
    
    // REMOVE: $this->allowedMimeTypes = [...]
    // REMOVE: $this->excelReaderService = $excelReaderService;
    
    // Keep: column mappings and other config
}
```

#### Change 3: Replace validateCsvFile
```php
private function validateCsvFile($filePath) {
    return $this->validateSpreadsheetFile($filePath);
}
```

#### Change 4: Replace readCsvFile
```php
private function readCsvFile($filePath) {
    return $this->readSpreadsheetFile($filePath, 20000);
}
```

## 🧪 Testing

### Test with Postman/cURL:

**CSV File (existing):**
```bash
curl -X POST http://localhost:8005/api/v1/bulk_data_uploading/upload_asset_items_csv \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@data.csv"
```

**Excel File (new):**
```bash
curl -X POST http://localhost:8005/api/v1/bulk_data_uploading/upload_asset_items_csv \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@data.xlsx"
```

### Test Checklist:
- [ ] Upload .csv file (should work as before)
- [ ] Upload .xlsx file (Excel 2007+)
- [ ] Upload .xls file (Excel 97-2003)
- [ ] Upload large file (~100MB)
- [ ] Upload file with 20,000+ rows (should reject)
- [ ] Upload invalid file type (should reject)

## 📄 Supported File Formats

| Format | Extension | Description |
|--------|-----------|-------------|
| CSV | .csv | Comma-separated values |
| Excel Modern | .xlsx | Excel 2007 and later |
| Excel Legacy | .xls | Excel 97-2003 |

## 🔧 Troubleshooting

**"Class 'ZipArchive' not found"**
```bash
# Rebuild container
make down && make up
```

**"Failed to read Excel file"**
- Check file isn't corrupted
- Try opening in Excel/LibreOffice first
- Check file size < 100MB

**Changes not taking effect**
```bash
# Clear cache and restart
make shell
php artisan cache:clear
php artisan config:clear
exit
make restart
```

## 📚 Documentation

- Full setup guide: `EXCEL_IMPORT_SETUP.md`
- Code reference: 
  - `src/app/Services/ExcelReaderService.php`
  - `src/app/Services/SpreadsheetImportTrait.php`
  - `src/app/Services/AssetAvailabilityTermTypeCsvImportService.php` (example)

## 🎯 Benefits

- ✅ Users can upload Excel files directly (no conversion needed)
- ✅ Supports both modern (.xlsx) and legacy (.xls) formats
- ✅ Same API endpoints work for all file types
- ✅ Automatic file type detection
- ✅ Existing CSV functionality unchanged
- ✅ Consistent error handling across all formats

## ⏱️ Time Required

- Docker rebuild: ~3 minutes
- Composer install: ~1 minute
- Update each service: ~2 minutes × 7 services = ~14 minutes
- Testing: ~10 minutes

**Total: ~30 minutes**
