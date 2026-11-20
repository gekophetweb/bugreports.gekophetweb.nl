<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A language can be followed at several frequencies: Critical straight away, High in the weekly digest. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'language_id']);
            $table->unique(['user_id', 'language_id', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'language_id', 'frequency']);
            $table->unique(['user_id', 'language_id']);
        });
    }
};
