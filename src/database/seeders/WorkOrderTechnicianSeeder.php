<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderTechnicianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $technicians = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@example.com',
                'phone' => '555-0101',
                'mobile' => '555-0102',
                'address' => '123 Main St, Anytown, USA',
                'employee_id' => 'TECH-1001',
                'job_title' => 'Senior Maintenance Technician',
                'specialization' => 'Electrical Systems',
                'certifications' => json_encode([
                    'Certified Maintenance Technician',
                    'Electrical Safety Certification',
                    'OSHA 30-Hour'
                ]),
                'hourly_rate' => 35.50,
                'is_contractor' => false,
                'is_available' => true,
                'unavailable_reason' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.j@example.com',
                'phone' => '555-0201',
                'mobile' => '555-0202',
                'address' => '456 Oak Ave, Somewhere, USA',
                'employee_id' => 'TECH-1002',
                'job_title' => 'HVAC Specialist',
                'specialization' => 'Heating and Cooling Systems',
                'certifications' => json_encode([
                    'EPA 608 Universal Certification',
                    'HVAC Excellence Certification'
                ]),
                'hourly_rate' => 42.75,
                'is_contractor' => false,
                'is_available' => true,
                'unavailable_reason' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mike Rodriguez',
                'email' => 'mike.r@example.com',
                'phone' => '555-0301',
                'mobile' => '555-0302',
                'address' => '789 Pine Rd, Nowhere, USA',
                'employee_id' => 'TECH-1003',
                'job_title' => 'Plumbing Technician',
                'specialization' => 'Water Systems',
                'certifications' => json_encode([
                    'Journeyman Plumber License',
                    'Backflow Prevention Certification'
                ]),
                'hourly_rate' => 38.25,
                'is_contractor' => false,
                'is_available' => false,
                'unavailable_reason' => 'On medical leave until 2023-12-15',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Alex Chen',
                'email' => 'alex.chen@contractor.com',
                'phone' => '555-0401',
                'mobile' => '555-0402',
                'address' => '321 Elm St, Elsewhere, USA',
                'employee_id' => 'CONT-2001',
                'job_title' => 'General Contractor',
                'specialization' => 'Building Maintenance',
                'certifications' => json_encode([
                    'General Contractor License',
                    'LEED Green Associate'
                ]),
                'hourly_rate' => 65.00,
                'is_contractor' => true,
                'is_available' => true,
                'unavailable_reason' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emily Wilson',
                'email' => 'emily.w@example.com',
                'phone' => '555-0501',
                'mobile' => '555-0502',
                'address' => '654 Maple Dr, Anywhere, USA',
                'employee_id' => 'TECH-1004',
                'job_title' => 'Facilities Technician',
                'specialization' => 'General Maintenance',
                'certifications' => json_encode([
                    'Facility Management Professional',
                    'CPR/First Aid Certified'
                ]),
                'hourly_rate' => 32.00,
                'is_contractor' => false,
                'is_available' => true,
                'unavailable_reason' => null,
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('work_order_technicians')->insert($technicians);
    }
}
