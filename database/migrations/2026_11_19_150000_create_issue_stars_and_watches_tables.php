<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['issue_stars', 'issue_watches'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'issue_id']);
                $table->index('issue_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_watches');
        Schema::dropIfExists('issue_stars');
    }
};
