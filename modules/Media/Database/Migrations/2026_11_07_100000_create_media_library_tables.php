<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The library's own tables: medialibrary's "media" table keeps the images
     * attached to records (a post's photo, …); these hold the files uploaded to the library.
     */
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->restrictOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->restrictOnDelete();
            $table->string('name');
            $table->string('disk', 32);
            $table->string('path', 1024);
            $table->string('extension', 16);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['folder_id', 'created_at']);
            $table->index('extension');
        });
    }

    /**
     * Reverse the migrations. The files on the disk stay (uninstalling a core module is not possible).
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_folders');
    }
};
