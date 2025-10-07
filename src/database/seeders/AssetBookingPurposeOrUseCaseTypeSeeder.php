<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetBookingPurposeOrUseCaseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        DB::table('asset_booking_purpose_or_use_case_type')->truncate();

        $data = [
            [
                'name' => 'Other',
                'description' => 'Miscellaneous or unspecified booking purposes.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Meeting',
                'description' => 'General meeting bookings for employees or clients.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Training',
                'description' => 'Assets booked for training and development programs.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Conference',
                'description' => 'Company-wide or external conferences.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Workshop',
                'description' => 'Hands-on workshops, seminars, or skill-building sessions.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Event',
                'description' => 'Corporate events, celebrations, or customer events.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Client Visit',
                'description' => 'Assets reserved for hosting external client visits or meetings.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Interview',
                'description' => 'Assets booked for recruitment and candidate interviews.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Project Work',
                'description' => 'Assets reserved for specific project-related activities.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Research & Development',
                'description' => 'Bookings for R&D activities, prototypes, or experiments.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Testing',
                'description' => 'Assets used for product or software testing activities.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Production Shoot',
                'description' => 'Media production, filming, or photography purposes.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Presentation',
                'description' => 'Assets used for executive or stakeholder presentations.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Scheduled maintenance or servicing of assets.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Repair',
                'description' => 'Assets taken out of circulation for repairs.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Audit',
                'description' => 'Assets used during audits, inspections, or compliance checks.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Facility Booking',
                'description' => 'Meeting rooms, halls, or other facility assets.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Travel',
                'description' => 'Vehicles, equipment, or other travel-related assets.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Logistics',
                'description' => 'Assets reserved for logistics and supply chain purposes.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Storage',
                'description' => 'Temporary asset booking for storage purposes.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Emergency Use',
                'description' => 'Assets allocated for emergency or disaster recovery use.',
                'isactive' => true,
                'tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('asset_booking_purpose_or_use_case_type')->insert($data);
    }
}