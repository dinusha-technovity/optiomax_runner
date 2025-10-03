<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_condition_query_tag', function (Blueprint $table) {
            $table->id();
            $table->text('name')->unique();
            $table->text('value')->nullable();
            $table->text('query');
            $table->text('type');
            $table->jsonb('params')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        // Add check constraint for 'type' using raw SQL
        DB::statement("ALTER TABLE workflow_condition_query_tag ADD CONSTRAINT type_check CHECK (type IN ('query', 'function', 'procedure'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraint before dropping the table
        DB::statement("ALTER TABLE workflow_condition_query_tag DROP CONSTRAINT IF EXISTS type_check");
        Schema::dropIfExists('workflow_condition_query_tag');
    }
};