<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE supplier_invites DROP CONSTRAINT IF EXISTS supplier_invites_status_check");
        DB::statement("ALTER TABLE supplier_invites ADD CONSTRAINT supplier_invites_status_check CHECK (status IN ('pending', 'accepted', 'EXPIRED', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE supplier_invites DROP CONSTRAINT IF EXISTS supplier_invites_status_check");
        DB::statement("ALTER TABLE supplier_invites ADD CONSTRAINT supplier_invites_status_check CHECK (status IN ('pending', 'accepted', 'EXPIRED'))");
    }
};
