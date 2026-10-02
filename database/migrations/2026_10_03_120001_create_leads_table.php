<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE leads ADD COLUMN source lead_source NOT NULL DEFAULT 'Other'");
        DB::statement("ALTER TABLE leads ADD COLUMN status lead_status NOT NULL DEFAULT 'New'");

        DB::statement('CREATE INDEX leads_owner_status_idx ON leads (owner_id, status)');
        DB::statement('CREATE INDEX leads_created_at_desc_idx ON leads (created_at DESC)');
        DB::statement('CREATE INDEX leads_name_trgm_idx ON leads USING GIN (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
