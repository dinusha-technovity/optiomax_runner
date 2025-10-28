<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Sets PostgreSQL timezone to UTC for consistent global datetime handling.
     * This ensures all timestamps are stored and displayed with +00:00 offset.
     */
    public function up(): void
    {
        // Set the default timezone for the current database to UTC
        DB::statement("ALTER DATABASE " . DB::getDatabaseName() . " SET timezone TO 'UTC'");
        
        // Also set for the current session
        DB::statement("SET timezone = 'UTC'");
        
        // Verify the change
        $timezone = DB::select("SHOW timezone")[0];
        $currentTz = $timezone->TimeZone ?? $timezone->timezone ?? 'Unknown';
        
        if ($currentTz !== 'UTC') {
            throw new \Exception("Failed to set PostgreSQL timezone to UTC. Current timezone: {$currentTz}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to Asia/Colombo (original timezone)
        DB::statement("ALTER DATABASE " . DB::getDatabaseName() . " SET timezone TO 'Asia/Colombo'");
        DB::statement("SET timezone = 'Asia/Colombo'");
    }
};
