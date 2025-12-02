# Assets CSV Import Guide

## File Structure for AssetCsvImportService

### Required Columns (6 total)

1. **name** - Asset name (REQUIRED, must be unique per tenant)
2. **asset_type_name** - Asset type (REQUIRED)
3. **category_name** - Asset category (REQUIRED)
4. **sub_category_name** - Asset sub-category (REQUIRED)
5. **asset_description** - Description text (OPTIONAL)
6. **asset_details** - JSON array (OPTIONAL)

---

## Valid Asset Types (from seeder)

- Tangible assets
- Intangible assets
- Operating assets
- Non-operating assets
- Current assets
- Fixed assets

---

## asset_details Column Format

**IMPORTANT:** This column must be a JSON array with objects containing `details` and `detailtype` keys.

### Format:
```json
[
  {
    "details": "Standard configuration with all required components",
    "detailtype": "Specification"
  },
  {
    "details": "Extended warranty coverage included",
    "detailtype": "Warranty Info"
  }
]
```

### Valid detailtype values:
- Specification
- Configuration
- Maintenance
- Warranty Info
- Installation
- License

---

## Files Generated

1. **CSV/assets_5000.csv** - 5000 sample rows for testing
2. **assets_template.csv** - 5 sample rows as template

Both files match the AssetCsvImportService requirements exactly.

---

## Import Process

1. Categories and sub-categories will be created automatically if they don't exist
2. Asset type must be valid (from the list above)
3. Asset names must be unique within the tenant
4. JSON fields must be valid JSON format
5. PostgreSQL function `bulk_insert_assets_with_relationships` handles the import

---

## Sample CSV Row

```csv
name,asset_type_name,category_name,sub_category_name,asset_description,asset_details
Office Workstation Setup,Tangible assets,Electronics,Desktops,Complete office workstation,"[{""details"": ""Standard configuration"", ""detailtype"": ""Specification""}]"
```

**Note:** In CSV, double quotes inside JSON must be escaped as `""`
