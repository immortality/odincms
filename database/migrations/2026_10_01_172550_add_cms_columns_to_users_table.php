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
        Schema::table('users', function (Blueprint $table) {
            // Inactive users cannot log in (Step 2.2) or use the admin panel (Step 2.3).
            $table->boolean('is_active')->default(true)->after('email_verified_at');
            // Preferred admin language; null = the site default.
            $table->string('locale', 10)->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('locale');

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'locale', 'last_login_at']);
        });
    }
};
