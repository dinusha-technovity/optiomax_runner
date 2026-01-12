<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates sequence for ISO-compliant audit session numbering
     * Format: ADT-YYYYMMDD-NNNNNN (e.g., ADT-20260111-000001)
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Create sequence for audit session numbers
        CREATE SEQUENCE IF NOT EXISTS audit_session_number_seq
            START WITH 1
            INCREMENT BY 1
            NO MAXVALUE
            NO CYCLE
            CACHE 1;

        -- Function to generate ISO-compliant audit session number
        CREATE OR REPLACE FUNCTION generate_audit_session_number()
        RETURNS VARCHAR(100)
        LANGUAGE plpgsql
        AS $$
        DECLARE
            next_val BIGINT;
            date_part TEXT;
            number_part TEXT;
            session_number TEXT;
        BEGIN
            -- Get next sequence value
            next_val := nextval('audit_session_number_seq');
            
            -- Get current date in YYYYMMDD format (ISO 8601 date format)
            date_part := to_char(CURRENT_DATE, 'YYYYMMDD');
            
            -- Format number with leading zeros (6 digits)
            number_part := lpad(next_val::TEXT, 6, '0');
            
            -- Combine with prefix "ADT" (Audit)
            -- Format: ADT-YYYYMMDD-NNNNNN
            session_number := 'ADT-' || date_part || '-' || number_part;
            
            RETURN session_number;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP FUNCTION IF EXISTS generate_audit_session_number();
        DROP SEQUENCE IF EXISTS audit_session_number_seq CASCADE;
        SQL);
    }
};
