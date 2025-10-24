<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CountryCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

     
    public function run(): void
    {
        $jsonPath = storage_path('app/json_files/country_data.json');
        if (!file_exists($jsonPath)) {
            throw new \Exception("country_data.json file not found at $jsonPath");
        }
        $countries = file_get_contents($jsonPath);
        $arrayData = json_decode($countries, true); 
        if (!is_array($arrayData)) {
            throw new \Exception("country_data.json is empty or invalid JSON");
        }
        foreach ($arrayData as $country) {
            $iddRoot = $country['idd']['root'] ?? null;
            $iddSuffixes = $country['idd']['suffixes'] ?? [];
        
            // Ensure idd_suffixes is an array and convert it into a JSON array
            if (!is_array($iddSuffixes)) {
                $iddSuffixes = [$iddSuffixes];
            }
        
            // If no suffixes, insert with only root
            if (empty($iddSuffixes)) {
                DB::table('countries')->insert([
                    'name_common' => $country['name']['common'] ?? null,
                    'name_official' => $country['name']['official'] ?? null,
                    'name_native_eng_common' => $country['name']['nativeName']['eng']['common'] ?? null,
                    'name_native_eng_official' => $country['name']['nativeName']['eng']['official'] ?? null,
                    'cca2' => $country['cca2'] ?? null,
                    'cca3' => $country['cca3'] ?? null,
                    'ccn3' => $country['ccn3'] ?? null,
                    'capital' => json_encode($country['capital'] ?? []),
                    'idd_root' => $iddRoot,
                    'idd_suffixes' => json_encode([]), // Store as JSON array
                    'phone_code' => $iddRoot, // Only root
                    'region' => $country['region'] ?? null,
                    'languages' => json_encode($country['languages'] ?? []),
                    'latlng' => json_encode($country['latlng'] ?? []),
                    'area' => $country['area'] ?? null,
                    'population' => $country['population'] ?? null,
                    'demonyms_eng_m' => $country['demonyms']['eng']['m'] ?? null,
                    'demonyms_eng_f' => $country['demonyms']['eng']['f'] ?? null,
                    'flag' => $country['flags']['svg'] ?? null,
                    'maps_google' => $country['maps']['googleMaps'] ?? null,
                    'maps_openStreetMap' => $country['maps']['openStreetMaps'] ?? null,
                    'timezones' => json_encode($country['timezones'] ?? []),
                    'continents' => json_encode($country['continents'] ?? []),
                    'startOfWeek' => $country['startOfWeek'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Insert a row for each suffix
                foreach ($iddSuffixes as $suffix) {
                    DB::table('countries')->insert([
                        'name_common' => $country['name']['common'] ?? null,
                        'name_official' => $country['name']['official'] ?? null,
                        'name_native_eng_common' => $country['name']['nativeName']['eng']['common'] ?? null,
                        'name_native_eng_official' => $country['name']['nativeName']['eng']['official'] ?? null,
                        'cca2' => $country['cca2'] ?? null,
                        'cca3' => $country['cca3'] ?? null,
                        'ccn3' => $country['ccn3'] ?? null,
                        'capital' => json_encode($country['capital'] ?? []),
                        'idd_root' => $iddRoot,
                        'idd_suffixes' => json_encode([$suffix]), // Store as JSON array
                        'phone_code' => $iddRoot . $suffix, // Concatenating root + suffix
                        'region' => $country['region'] ?? null,
                        'languages' => json_encode($country['languages'] ?? []),
                        'latlng' => json_encode($country['latlng'] ?? []),
                        'area' => $country['area'] ?? null,
                        'population' => $country['population'] ?? null,
                        'demonyms_eng_m' => $country['demonyms']['eng']['m'] ?? null,
                        'demonyms_eng_f' => $country['demonyms']['eng']['f'] ?? null,
                        'flag' => $country['flags']['svg'] ?? null,
                        'maps_google' => $country['maps']['googleMaps'] ?? null,
                        'maps_openStreetMap' => $country['maps']['openStreetMaps'] ?? null,
                        'timezones' => json_encode($country['timezones'] ?? []),
                        'continents' => json_encode($country['continents'] ?? []),
                        'startOfWeek' => $country['startOfWeek'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
              

    }
}
