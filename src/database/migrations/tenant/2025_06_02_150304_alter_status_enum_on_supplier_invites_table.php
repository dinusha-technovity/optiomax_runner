<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop and re-add the enum constraint
        DB::statement("ALTER TABLE supplier_invites DROP CONSTRAINT IF EXISTS supplier_invites_status_check");
        DB::statement("ALTER TABLE supplier_invites ADD CONSTRAINT supplier_invites_status_check CHECK (status IN ('pending', 'accepted', 'EXPIRED'))");
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE supplier_invites DROP CONSTRAINT IF EXISTS supplier_invites_status_check");
        DB::statement("ALTER TABLE supplier_invites ADD CONSTRAINT supplier_invites_status_check CHECK (status IN ('pending', 'accepted'))");
    }
};

