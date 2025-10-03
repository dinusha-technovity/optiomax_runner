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
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_movements', 'movement_reason')) {
                $table->string('movement_reason')->nullable()->after('movement_type');
            }
            if (!Schema::hasColumn('inventory_movements', 'lot_number')) {
                $table->string('lot_number')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('inventory_movements', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('lot_number');
            }
            $table->index(['tenant_id','item_id','lot_number'], 'idx_inv_mov_tenant_item_lot');
        });
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('idx_inv_mov_tenant_item_lot');
            $table->dropColumn(['movement_reason','lot_number','expiration_date']);
        });
    }
};
