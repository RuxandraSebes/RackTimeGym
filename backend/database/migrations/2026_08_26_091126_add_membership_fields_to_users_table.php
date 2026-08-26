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
            $table->unsignedInteger('cancellation_window_minutes')->nullable()->after('role');
            $table->string('membership_status')->default('active')->after('cancellation_window_minutes');
            $table->dateTime('membership_reactivated_at')->nullable()->after('membership_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cancellation_window_minutes', 'membership_status', 'membership_reactivated_at']);
        });
    }
};
