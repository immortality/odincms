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
        // A menu: header, footer, ...
        Schema::create('navigation_groups', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug', 64)->unique();
            $table->timestamps();
        });

        // A link in a menu; links form a tree through parent_id.
        Schema::create('navigation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('navigation_groups')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_links')->cascadeOnDelete();
            $table->json('title')->nullable(); // empty for a model link = use the model's label
            $table->string('type', 16); // url | route | model
            $table->string('url', 2048)->nullable();
            $table->string('route_name')->nullable();
            $table->json('route_parameters')->nullable();
            $table->nullableMorphs('linkable');
            $table->string('css_class')->nullable();
            $table->boolean('open_new_tab')->default(false);
            // [] = everyone; "@guests", "@members" and group names otherwise.
            $table->json('visibility')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_links');
        Schema::dropIfExists('navigation_groups');
    }
};
