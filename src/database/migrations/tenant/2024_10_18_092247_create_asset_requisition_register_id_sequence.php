<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE asset_requisition_register_id_seq START 1;'); 
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS asset_requisition_register_id_seq;');
    }
};
