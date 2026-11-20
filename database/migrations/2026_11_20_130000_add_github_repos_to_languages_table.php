<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            // GitHub repositories whose own security advisories we read ("php/php-src").
            $table->jsonb('github_repos')->default('[]');
        });

        // Installed languages are kept by the seeder, so PHP gets its repository here.
        DB::table('languages')->where('slug', 'php')->update(['github_repos' => json_encode(['php/php-src'])]);
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('github_repos');
        });
    }
};
