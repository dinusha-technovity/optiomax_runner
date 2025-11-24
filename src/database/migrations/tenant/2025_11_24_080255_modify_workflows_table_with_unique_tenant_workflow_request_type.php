<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    { 
        Schema::table('workflows', function (Blueprint $table) {
            $table->unique(['tenant_id', 'workflow_request_type_id'], 'unique_tenant_workflow_request_type');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique('unique_tenant_workflow_request_type');
        });
    }
};