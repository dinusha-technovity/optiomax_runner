import csv
import random
from datetime import datetime, timedelta

# Seeder values
depreciation_methods = ['Straightline', 'Declining Balance', 'Sum of the Years Digits', 'Units of Production']
purchase_types = ['Hire', 'Rent', 'Purchase', 'Lease']
warranty_condition_types = ['Time based', 'Usage based', 'Combined']

asset_types = ['Tangible assets', 'Intangible assets', 'Operating assets']
currencies = ['USD', 'EUR', 'GBP', 'LKR']
manufacturers = ['HP Inc.', 'Dell Inc.', 'Lenovo', 'Apple Inc.', 'Cisco Systems', 'Microsoft', 'Samsung', 'IBM', 'Oracle', 'SAP']
suppliers = [
    {"name": "HP Enterprise", "email": "enterprise@hp.com", "type": "Company"},
    {"name": "Dell Direct", "email": "sales@dell.com", "type": "Company"},
    {"name": "Lenovo Enterprise", "email": "business@lenovo.com", "type": "Company"},
    {"name": "Apple Business", "email": "business@apple.com", "type": "Company"},
    {"name": "Cisco Systems", "email": "sales@cisco.com", "type": "Company"},
    {"name": "Microsoft Corp", "email": "business@microsoft.com", "type": "Company"},
    {"name": "Tech Solutions Inc", "email": "contact@techsolutions.com", "type": "Company"}
]
model_prefixes = ['HP', 'DELL', 'LEN', 'APPLE', 'CISCO']
warranty_usage_names = ['Hours', 'Days', 'Kilometers', 'Cycles']
time_units = ['Hour', 'Day', 'Month', 'Year']
categories = ['Electronics', 'IT Equipment', 'Software', 'Furniture', 'Machinery', 'Network Equipment', 'Tools']
sub_categories = ['Laptops', 'Desktops', 'Monitors', 'Printers', 'Servers', 'Routers', 'Tablets']
tags_options = [['IT', 'Critical', 'Active'], ['Hardware', 'Standard', 'Reserve'], ['Software', 'Low Priority', 'Standby'], ['Network', 'High Priority', 'Maintenance']]

responsible_persons = ['Chamod Randeni', 'ffd']
department = 'Negombo test'

def generate_random_date(start_year=2025, end_year=2030):
    start_date = datetime(start_year, 1, 1)
    end_date = datetime(end_year, 12, 31)
    delta = end_date - start_date
    random_days = random.randint(0, delta.days)
    return (start_date + timedelta(days=random_days)).strftime('%Y-%m-%d')

def generate_asset_items_csv(filename, num_rows=5000):
    with open(filename, 'w', newline='') as csvfile:
        fieldnames = [
            'asset_name', 'asset_type_name', 'model_number', 'serial_number', 'asset_tag', 'qr_code',
            'item_value', 'item_value_currency_code', 'purchase_cost', 'purchase_cost_currency_code',
            'purchase_type_name', 'purchase_order_number', 'other_purchase_details', 'supplier_data',
            'salvage_value', 'warranty', 'warranty_condition_type_name', 'warranty_expiry_date',
            'warranty_usage_name', 'warranty_usage_value', 'insurance_number', 'insurance_expiry_date',
            'expected_life_time', 'expected_life_time_unit_name', 'depreciation_value',
            'depreciation_method_name', 'depreciation_start_date', 'decline_rate', 'total_estimated_units',
            'manufacturer', 'responsible_person_name', 'department_name', 'latitude', 'longitude',
            'received_condition_id', 'asset_category_name', 'asset_sub_category_name', 'asset_tags'
        ]
        
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()
        
        for i in range(1, num_rows + 1):
            item_value = round(random.uniform(500, 10000), 2)
            purchase_cost = round(item_value * random.uniform(0.85, 0.95), 2)
            salvage_value = round(item_value * random.uniform(0.10, 0.15), 2)
            expected_life = random.randint(3, 15)
            depreciation_value = round((item_value - salvage_value) / expected_life, 2)
            
            supplier = random.choice(suppliers)
            supplier_json = f'{{"name":"{supplier["name"]}","email":"{supplier["email"]}","type":"{supplier["type"]}"}}'
            
            # Validation: 4990 valid, 5 invalid person, 5 invalid department
            if i > 4995:
                responsible_person = "Invalid Person Name"
                dept = department
            elif i > 4990:
                responsible_person = random.choice(responsible_persons)
                dept = "Invalid Department Name"
            else:
                responsible_person = random.choice(responsible_persons)
                dept = department
            
            # total_estimated_units: percentage only (1-99)
            total_estimated_units = random.randint(1, 99)
            
            row = {
                'asset_name': f'Asset {i:04d}',
                'asset_type_name': random.choice(asset_types),
                'model_number': f'{random.choice(model_prefixes)}-MODEL-{i:05d}',
                'serial_number': f'SN-{i:010d}',
                'asset_tag': f'TAG-{i:06d}',
                'qr_code': '',  # Empty as requested
                'item_value': item_value,
                'item_value_currency_code': random.choice(currencies),
                'purchase_cost': purchase_cost,
                'purchase_cost_currency_code': random.choice(currencies),
                'purchase_type_name': random.choice(purchase_types),  # From Tenantassest_requisition_availability_typeSeeder
                'purchase_order_number': f'PO-2025-{random.randint(1000, 9999)}',
                'other_purchase_details': f'Purchase details for Asset {i:04d}',
                'supplier_data': supplier_json,
                'salvage_value': salvage_value,
                'warranty': f'{random.randint(1, 5)} years',
                'warranty_condition_type_name': random.choice(warranty_condition_types),  # From WarrentyConditionTypesSeeder
                'warranty_expiry_date': generate_random_date(2027, 2030),
                'warranty_usage_name': random.choice(warranty_usage_names),
                'warranty_usage_value': random.randint(1000, 50000),
                'insurance_number': f'INS{i:06d}',
                'insurance_expiry_date': generate_random_date(2026, 2028),
                'expected_life_time': expected_life,
                'expected_life_time_unit_name': random.choice(time_units),
                'depreciation_value': depreciation_value,
                'depreciation_method_name': random.choice(depreciation_methods),  # From DepreciationMethodSeeder
                'depreciation_start_date': generate_random_date(2024, 2025),
                'decline_rate': round(random.uniform(10, 40), 2),
                'total_estimated_units': total_estimated_units,  # Percentage 1-99
                'manufacturer': random.choice(manufacturers),
                'responsible_person_name': responsible_person,
                'department_name': dept,
                'latitude': round(random.uniform(6.5, 7.5), 4),
                'longitude': round(random.uniform(79.5, 80.5), 4),
                'received_condition_id': random.randint(1, 5),
                'asset_category_name': random.choice(categories),
                'asset_sub_category_name': random.choice(sub_categories),
                'asset_tags': ','.join(random.choice(tags_options))
            }
            
            writer.writerow(row)
    
    print(f"✓ Generated {filename} with {num_rows} rows")
    print(f"  - Valid rows: 4990")
    print(f"  - Invalid person rows: 5 (rows 4991-4995)")
    print(f"  - Invalid department rows: 5 (rows 4996-5000)")
    print(f"  - qr_code: Empty")
    print(f"  - total_estimated_units: 1-99 (percentage)")
    print(f"  - depreciation_method_name: {depreciation_methods}")
    print(f"  - purchase_type_name: {purchase_types}")
    print(f"  - warranty_condition_type_name: {warranty_condition_types}")

# Generate the CSV
generate_asset_items_csv('/home/chamod-randeni/Documents/optiomax project/optiomax_runner/CSV/asset_items_5000.csv', 5000)
