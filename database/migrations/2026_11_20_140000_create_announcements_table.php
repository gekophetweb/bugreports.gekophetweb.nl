<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);                    // php.net
            $table->string('external_id')->unique();         // the feed entry id
            $table->string('kind', 20)->index();             // upcoming | advisory | informational | release
            $table->string('title', 500);
            $table->text('body');
            $table->string('url', 500);
            $table->timestamp('published_at')->index();
            $table->timestamp('scheduled_at')->nullable();   // upcoming: start of the release window (UTC)
            $table->timestamp('scheduled_until')->nullable();
            $table->string('severity', 10)->nullable();      // upcoming: highest severity php.net named
            $table->string('version', 20)->nullable();       // release: 8.5.13
            $table->timestamp('notified_at')->nullable();    // heads-up or advisory email sent
            $table->timestamp('release_notified_at')->nullable(); // upcoming: "it's out" email sent
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('advance_warnings')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('advance_warnings');
        });
        Schema::dropIfExists('announcements');
    }
};
