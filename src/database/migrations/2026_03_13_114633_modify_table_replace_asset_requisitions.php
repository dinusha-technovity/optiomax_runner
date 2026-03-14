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
        Schema::table('replace_asset_requisitions', function (Blueprint $table) {
          
            $table->dropColumn('replace_requisition_number');
            $table->dropColumn('is_disposal_recommended');
            $table->dropColumn('disposal_recommended_type');
            $table->dropColumn('other_docs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
