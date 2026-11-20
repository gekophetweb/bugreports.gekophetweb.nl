<?php

use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            // Cloudflare Radar Zero Wire tags whose briefings belong to this language ("PHP", "nginx").
            $table->jsonb('zero_wire_tags')->default('[]');
        });

        // Installed languages are kept by the seeder, so they get their tags here.
        foreach (LanguageSeeder::ZERO_WIRE_TAGS as $slug => $tags) {
            DB::table('languages')->where('slug', $slug)->update(['zero_wire_tags' => json_encode($tags)]);
        }
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('zero_wire_tags');
        });
    }
};
