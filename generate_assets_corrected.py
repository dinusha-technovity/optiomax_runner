import csv
import random
import json

# Asset types from seeder
asset_types = ['Tangible assets', 'Intangible assets', 'Operating assets', 'Non-operating assets', 'Current assets', 'Fixed assets']
categories = ['Electronics', 'IT Equipment', 'Software', 'Furniture', 'Machinery', 'Network Equipment', 'Tools', 'Vehicles', 'Office Equipment']
sub_categories = ['Laptops', 'Desktops', 'Monitors', 'Printers', 'Servers', 'Routers', 'Tablets', 'Switches', 'Phones']

def generate_asset_details():
    """Generate asset_details in the format: [{"details": "...", "detailtype": "..."}]"""
    num_details = random.randint(1, 3)
    details_list = []
    
    detail_types = ['Specification', 'Configuration', 'Maintenance', 'Warranty Info', 'Installation', 'License']
    detail_contents = [
        'Standard configuration with all required components',
        'Extended warranty coverage included',
        'Professional installation and setup completed',
        'Licensed for enterprise use',
        'Regular maintenance schedule established',
        'Certified by manufacturer',
        'Compliant with industry standards',
        'Optimized for performance',
        'Energy efficient model',
        'Compatible with existing infrastructure'
    ]
    
    for i in range(num_details):
        details_list.append({
            "details": random.choice(detail_contents),
            "detailtype": random.choice(detail_types)
        })
    
    return json.dumps(details_list)

def generate_assets_csv(filename, num_rows=5000):
    with open(filename, 'w', newline='') as csvfile:
        # Only 6 columns now (removed thumbnail_image, asset_classification, reading_parameters)
        fieldnames = [
            'name', 'asset_type_name', 'category_name', 'sub_category_name',
            'asset_description', 'asset_details'
        ]
        
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()
        
        for i in range(1, num_rows + 1):
            asset_type = random.choice(asset_types)
            category = random.choice(categories)
            sub_category = random.choice(sub_categories)
            
            row = {
                'name': f'Asset {i:05d}',
                'asset_type_name': asset_type,
                'category_name': category,
                'sub_category_name': sub_category,
                'asset_description': f'Description for Asset {i:05d}, {asset_type} in {category} category',
                'asset_details': generate_asset_details()
            }
            
            writer.writerow(row)
    
    print(f"✓ Generated {filename} with {num_rows} rows")
    print(f"  - 6 columns (removed thumbnail_image, asset_classification, reading_parameters)")
    print(f"  - asset_type_name: From seeder values")
    print(f"  - asset_details: Array format with 'details' and 'detailtype' keys")

# Generate the CSV
generate_assets_csv('/home/chamod-randeni/Documents/optiomax project/optiomax_runner/CSV/assets_5000.csv', 5000)
