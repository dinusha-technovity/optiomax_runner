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
        Schema::create('assignable_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // e.g., 'Asset', 'AssetItem'
            $table->string('model_class')->unique(); // e.g., 'App\Models\Asset'
            $table->string('table_name', 100); // e.g., 'assets', 'asset_items'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default assignable types
        DB::table('assignable_types')->insert([
            [
                'name' => 'Asset',
                'model_class' => 'App\Models\Asset',
                'table_name' => 'assets',
                'description' => 'Asset group/parent level',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'AssetItem',
                'model_class' => 'App\Models\AssetItem',
                'table_name' => 'asset_items',
                'description' => 'Individual asset item level',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignable_types');
    }
};
