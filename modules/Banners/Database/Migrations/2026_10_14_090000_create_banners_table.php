<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The image is stored by medialibrary (the "image" collection).
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('highlight')->nullable();
            $table->json('text')->nullable();
            $table->json('badge_label')->nullable();
            $table->json('badge_text')->nullable();
            $table->json('button_label')->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->string('chip_1_icon', 64)->nullable();
            $table->json('chip_1_text')->nullable();
            $table->string('chip_2_icon', 64)->nullable();
            $table->json('chip_2_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['status', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
