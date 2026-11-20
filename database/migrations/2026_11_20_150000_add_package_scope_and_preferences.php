<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // core: the language or server itself; package: an open source package built on it.
            $table->string('scope', 10)->default('package')->index();
        });

        // NVD (runtime and server CPEs) and project advisories are core; OSV only for a standard library.
        DB::statement("update issues set scope = 'core' where source in ('nvd', 'ghsa')
            or (jsonb_array_length(packages) > 0 and not exists (
                select 1 from jsonb_array_elements_text(packages) as p(name) where p.name not in ('stdlib', 'toolchain')))");

        Schema::table('users', function (Blueprint $table) {
            // Packages someone uses ("laravel/*"): their issues show although packages are hidden by default.
            $table->jsonb('used_packages')->default('[]');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('all_packages')->default(false);
        });

        // Existing subscribers keep getting the package issues they get today.
        DB::table('subscriptions')->update(['all_packages' => true]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('all_packages'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('used_packages'));
        Schema::table('issues', fn (Blueprint $table) => $table->dropColumn('scope'));
    }
};
