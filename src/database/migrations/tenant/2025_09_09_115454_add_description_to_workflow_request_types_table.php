<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToWorkflowRequestTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('workflow_request_types', function (Blueprint $table) {
            $table->text('description')->nullable()->after('request_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('workflow_request_types', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}