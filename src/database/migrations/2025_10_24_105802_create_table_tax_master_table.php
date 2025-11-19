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
        Schema::create('tax_master', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tax_code', 50)->unique();
            $table->string('tax_name', 100);
            $table->string('tax_type', 50);  // Defines how tax is calculated: 'PERCENTAGE', 'FIXED', or 'COMPOUND'
            $table->decimal('rate', 10, 4)->default(0);
            $table->decimal('amount', 12, 2)->default(0); // Used when tax_type = 'FIXED'
            $table->boolean('is_compound')->default(false); // TRUE = applies on top of other tax(es) (e.g., VAT on NBT)
            $table->text('compound_on')->nullable();  // Specifies which other taxes this tax compounds with (if is_compound = TRUE)
            $table->string('applicable_to', 50)->nullable(); // Examples: 'ASSET_PURCHASE', 'MAINTENANCE', 'DISPOSAL', etc.
            // $table->char('country_code', 2)->nullable(); // need to ingegrade separately with our country table
            // $table->char('currency_code', 3)->nullable(); // need to ingegrade separately with our currency table
            $table->string('jurisdiction', 100)->nullable();
            $table->string('tax_authority', 100)->nullable();
            $table->integer('calculation_order')->default(1);// Controls order of calculation when multiple taxes apply. ex:It tells the system “which tax should be applied first, second, third…
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->boolean('isactive')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_master');
    }
};
