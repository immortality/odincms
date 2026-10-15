<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            // A page with subpages cannot be deleted (PageController), so the cascade only guards the data.
            $table->foreignId('parent_id')->nullable()->constrained('pages')->cascadeOnDelete();
            $table->json('title');
            $table->string('slug');
            // The full URL path, kept in sync from the slugs: "about/team".
            $table->string('path')->unique();
            $table->json('body')->nullable();
            $table->string('layout', 64)->default('default');
            $table->string('status', 16)->default('draft');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['parent_id', 'slug']);
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
