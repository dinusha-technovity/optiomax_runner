<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name_common');
            $table->string('name_official');
            $table->string('name_native_eng_common')->nullable(); 
            $table->string('name_native_eng_official')->nullable(); // Native official name in English
            $table->string('cca2'); // 2-letter country code
            $table->string('cca3')->nullable(); ; // 3-letter country code
            $table->string('ccn3')->nullable(); ; // Numeric country code
            $table->json('capital')->nullable(); // Capital city of the country
            $table->float('area')->nullable(); // Area in square kilometers
            $table->integer('population')->nullable(); // Population count
            $table->json('languages'); // Languages spoken, stored as JSON
            $table->json('currencies')->nullable(); // Currencies used, stored as JSON
            $table->string('idd_root')->nullable(); 
            $table->json('idd_suffixes')->nullable(); 
            $table->string('phone_code')->nullable();
            $table->string('region')->nullable(); 
            $table->string('flag')->nullable(); 
            $table->string('maps_google')->nullable();
            $table->string('maps_openStreetMap')->nullable(); 
            $table->json('timezones')->nullable(); // Timezones of the country, stored as JSON
            $table->json('continents')->nullable(); // Continent(s) the country belongs to, stored as JSON
            $table->json('latlng')->nullable(); // Latitude and longitude, stored as JSON
            $table->string('demonyms_eng_m')->nullable(); // Demonym (Male) in English
            $table->string('demonyms_eng_f')->nullable();
            $table->string('startOfWeek')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
