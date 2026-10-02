<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement("CREATE TYPE lead_status AS ENUM ('New', 'Contacted', 'Qualified', 'Won', 'Lost')");
        DB::statement("CREATE TYPE lead_source AS ENUM ('Website', 'Referral', 'Social', 'Cold Call', 'Event', 'Other')");
        DB::statement("CREATE TYPE lead_action AS ENUM ('created', 'updated', 'status_changed', 'deleted')");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS lead_action');
        DB::statement('DROP TYPE IF EXISTS lead_source');
        DB::statement('DROP TYPE IF EXISTS lead_status');
    }
};
