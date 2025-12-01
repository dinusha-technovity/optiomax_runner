#!/bin/bash

# Script to update all CSV Import Services to support Excel files
# This script adds the SpreadsheetImportTrait to all CSV import services

SERVICES=(
    "SupplierCsvImportService"
    "AssetItemsCsvImportService"
    "AssetCategoryCsvImportService"
    "AssetCsvImportService"
    "ItemCsvImportService"
    "CustomerCsvImportService"
    "AssetSubCategoryCsvImportService"
)

SERVICE_DIR="../src/app/Services"

echo "============================================"
echo "Excel Import Support - Batch Update Script"
echo "============================================"
echo ""
echo "This script will update the following services:"
for service in "${SERVICES[@]}"; do
    echo "  - $service"
done
echo ""
read -p "Continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Starting updates..."
echo ""

for service in "${SERVICES[@]}"; do
    SERVICE_FILE="$SERVICE_DIR/${service}.php"
    
    if [ ! -f "$SERVICE_FILE" ]; then
        echo "❌ File not found: $SERVICE_FILE"
        continue
    fi
    
    echo "📝 Processing: $service"
    
    # Create backup
    cp "$SERVICE_FILE" "$SERVICE_FILE.backup"
    echo "   ✓ Backup created: ${service}.php.backup"
    
    # Check if trait is already added
    if grep -q "use SpreadsheetImportTrait;" "$SERVICE_FILE"; then
        echo "   ⚠️  SpreadsheetImportTrait already added, skipping..."
        rm "$SERVICE_FILE.backup"
        continue
    fi
    
    # Add trait after class declaration
    sed -i '/^class '"$service"'/a\    use SpreadsheetImportTrait;\n' "$SERVICE_FILE"
    echo "   ✓ Added SpreadsheetImportTrait"
    
    echo "   ✅ $service updated successfully"
    echo ""
done

echo "============================================"
echo "Update Complete!"
echo "============================================"
echo ""
echo "Next steps:"
echo "1. Review the changes in each service file"
echo "2. Manually update constructor methods (remove allowedMimeTypes)"
echo "3. Replace validateCsvFile() with: return \$this->validateSpreadsheetFile(\$filePath);"
echo "4. Replace readCsvFile() with: return \$this->readSpreadsheetFile(\$filePath, 20000);"
echo "5. Test with CSV, XLSX, and XLS files"
echo ""
echo "Backup files created (*.backup) - remove them after verification"
echo ""
