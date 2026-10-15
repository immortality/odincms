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
        // A region of a theme layout: "sidebar", "home-top", "footer-1", ...
        Schema::create('widget_areas', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->json('name');
            $table->string('theme', 64)->default('default');
            $table->timestamps();

            $table->unique(['theme', 'slug']);
        });

        // A configured copy of a widget type, placed in an area.
        Schema::create('widget_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('widget_areas')->cascadeOnDelete();
            $table->string('widget', 128)->index(); // widget key, "{module}.{name}"
            $table->json('title')->nullable();
            $table->json('settings')->nullable();
            // {"pages": {"mode": "all|only|except", "patterns": [...]}, "locales": [...], "audience": "...", "groups": [...]}
            $table->json('visibility')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cache_ttl')->nullable(); // seconds; null or 0 = not cached
            $table->timestamps();

            $table->index(['area_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_instances');
        Schema::dropIfExists('widget_areas');
    }
};
