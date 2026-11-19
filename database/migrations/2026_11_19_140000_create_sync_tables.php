<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);
            $table->string('feed');
            $table->timestamp('cursor', 6)->nullable();
            $table->timestamps();
            $table->unique(['source', 'feed']);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);
            $table->string('feed');
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('running')->index(); // running | ok | failed
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->text('error')->nullable();
            $table->jsonb('new_issue_ids')->default('[]');
            $table->jsonb('changed_issues')->default('{}');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source', 'feed', 'id']);
        });

        Schema::create('issue_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('fields');
            $table->string('previous_severity', 10);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_changes');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('sync_cursors');
    }
};
