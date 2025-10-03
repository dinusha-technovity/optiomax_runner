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
        Schema::table('workflow_condition_query_tag', function (Blueprint $table) {
            $table->jsonb('options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_condition_query_tag', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
