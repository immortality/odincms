<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // "{section}.{field}", e.g. "site.name", "blog.posts_per_page"
            $table->string('key')->unique();
            $table->json('value')->nullable();
            // The settings section ("site", "blog", ...) and the module that owns it (null = core).
            $table->string('group', 64)->index();
            $table->string('module', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
