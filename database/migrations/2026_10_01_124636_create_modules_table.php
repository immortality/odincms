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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            // Version in module.json, refreshed on every sync.
            $table->string('version', 32);
            // Version that was installed or last upgraded; null while not installed.
            $table->string('installed_version', 32)->nullable();
            $table->boolean('is_core')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->boolean('enabled')->default(false);
            // Set by sync when the module's folder has disappeared.
            $table->boolean('is_missing')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
