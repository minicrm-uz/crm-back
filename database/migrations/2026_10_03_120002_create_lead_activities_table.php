<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE lead_activities ADD COLUMN action lead_action NOT NULL');
        DB::statement('CREATE INDEX lead_activities_lead_created_idx ON lead_activities (lead_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
