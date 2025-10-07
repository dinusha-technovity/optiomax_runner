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
        Schema::create('work_order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_item_id'); // FK -> work_orders_related_requested_item.id
            $table->unsignedBigInteger('inventory_movement_id'); // FK -> inventory_movements.id (OUT movement created for WO)
            $table->decimal('allocated_qty', 12, 2);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('work_order_item_id')->references('id')->on('work_orders_related_requested_item')->onDelete('cascade');
            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->onDelete('cascade');
            $table->index(['tenant_id','work_order_item_id']);
        });

        // (Optional) quickly query remaining/fulfilled amounts
        if (!Schema::hasColumn('work_orders_related_requested_item', 'fulfilled_qty')) {
            Schema::table('work_orders_related_requested_item', function (Blueprint $table) {
                $table->decimal('fulfilled_qty', 12, 2)->default(0)->after('requested_qty');
            });
        }
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_item_allocations');
        Schema::table('work_orders_related_requested_item', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders_related_requested_item', 'fulfilled_qty')) {
                $table->dropColumn('fulfilled_qty');
            }
        });
    }
};
