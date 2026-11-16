<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('external_id');
            $table->string('type', 32);
            $table->timestampTz('occurred_at');
            $table->jsonb('payload');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
            $table->unique(['site_id', 'external_id']);
            $table->index(['site_id', 'type', 'occurred_at']);
        });

        DB::statement('CREATE INDEX events_metadata_gin_idx ON events USING GIN (metadata)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
