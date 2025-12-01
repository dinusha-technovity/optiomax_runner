#!/bin/bash

# Script to update all remaining CSV import services to support Excel files
# This replaces the validateCsvFile and readCsvFile methods to use SpreadsheetImportTrait

SERVICES_DIR="/home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/app/Services"

echo "🔧 Updating CSV Import Services to support Excel files..."
echo "=============================================="

# Function to update a service file
update_service() {
    local file=$1
    local backup="${file}.bak"
    
    echo "Processing: $(basename $file)"
    
    # Create backup
    cp "$file" "$backup"
    
    # Use Python to do the replacement (more reliable than sed for multi-line)
    python3 << 'PYTHON_SCRIPT' - "$file" "$backup"
import sys
import re

file_path = sys.argv[1]
backup_path = sys.argv[2]

with open(file_path, 'r') as f:
    content = f.read()

# Pattern for validateCsvFile method
validate_pattern = r'private function validateCsvFile\([^)]+\):\s*array\s*\{[^}]+// Check if file exists.*?return \[\'success\' => true\];\s*\}'

# Pattern for readCsvFile method  
read_pattern = r'private function readCsvFile\([^)]+\):\s*array\s*\{.*?catch \(Exception \$e\) \{[^}]+\}\s*\}'

# Replacement for validateCsvFile
validate_replacement = '''private function validateCsvFile(string $filePath): array
    {
        return $this->validateSpreadsheetFile($filePath);
    }'''

# Replacement for readCsvFile
read_replacement = '''private function readCsvFile(string $filePath): array
    {
        return $this->readSpreadsheetFile($filePath, 20000);
    }'''

# Perform replacements
content = re.sub(validate_pattern, validate_replacement, content, flags=re.DOTALL)
content = re.sub(read_pattern, read_replacement, content, flags=re.DOTALL)

# Write back
with open(file_path, 'w') as f:
    f.write(content)

print(f"✅ Updated: {file_path}")
PYTHON_SCRIPT
    
    if [ $? -eq 0 ]; then
        echo "  ✅ Successfully updated $(basename $file)"
        # rm "$backup"  # Uncomment to remove backup after successful update
    else
        echo "  ❌ Failed to update $(basename $file) - restoring backup"
        mv "$backup" "$file"
    fi
}

# Update each service
update_service "$SERVICES_DIR/AssetCsvImportService.php"
update_service "$SERVICES_DIR/AssetItemsCsvImportService.php"
update_service "$SERVICES_DIR/AssetSubCategoryCsvImportService.php"
update_service "$SERVICES_DIR/CustomerCsvImportService.php"
update_service "$SERVICES_DIR/ItemCsvImportService.php"
update_service "$SERVICES_DIR/SupplierCsvImportService.php"

echo ""
echo "=============================================="
echo "✨ Update complete!"
echo ""
echo "Next steps:"
echo "1. cd docker-for-Laravel"
echo "2. make down && make up"
echo "3. make shell"
echo "4. composer install"
echo "5. exit"
echo ""
echo "Backups saved with .bak extension"
