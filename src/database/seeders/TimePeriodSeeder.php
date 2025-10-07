<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TimePeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $dateTypes = [
            [
                'name' => 'Day',
                'slug' => Str::slug('Day'),
                'is_date_type' => true,
                'is_time_type' => false,
            ],
            [
                'name' => 'Month',
                'slug' => Str::slug('Month'),
                'is_date_type' => true,
                'is_time_type' => false,
            ],
            [
                'name' => 'Year',
                'slug' => Str::slug('Year'),
                'is_date_type' => true,
                'is_time_type' => false,
            ]
        ];

        $timeTypes = [
            [
                'name' => 'Hour',
                'slug' => Str::slug('Hour'),
                'is_date_type' => false,
                'is_time_type' => true,
            ],
            [
                'name' => 'Minute',
                'slug' => Str::slug('Minute'),
                'is_date_type' => false,
                'is_time_type' => true,
            ],
            [
                'name' => 'Second',
                'slug' => Str::slug('Second'),
                'is_date_type' => false,
                'is_time_type' => true,
            ]
        ];

        DB::table('time_period_entries')->insert(array_merge($dateTypes, $timeTypes));
    }
    
}
