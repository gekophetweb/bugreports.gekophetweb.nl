<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('kind', 20)->default('language');
            $table->boolean('enabled')->default(true);
            $table->jsonb('osv_ecosystems')->default('[]');
            $table->jsonb('nvd_cpes')->default('[]');
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);                 // osv | nvd
            $table->string('external_id')->unique();      // GHSA-…, PYSEC-…, CVE-…
            $table->jsonb('aliases')->default('[]');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('severity', 10)->default('unknown')->index();
            $table->decimal('cvss_score', 3, 1)->nullable();
            $table->string('cvss_vector')->nullable();
            $table->jsonb('packages')->default('[]');
            $table->jsonb('references')->default('[]');
            $table->string('source_url', 500);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('modified_at')->nullable()->index();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();
        });
        DB::statement('CREATE INDEX issues_aliases_gin ON issues USING gin (aliases jsonb_path_ops)');

        Schema::create('issue_language', function (Blueprint $table) {
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->primary(['issue_id', 'language_id']);
            $table->index('language_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_language');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('languages');
    }
};
