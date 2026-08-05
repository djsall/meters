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
        Schema::table('meters', function (Blueprint $table) {
            $table->foreignUuid('previous_meter_id')->nullable()->unique()->after('type')->constrained('meters')->nullOnDelete();
            $table->date('installed_at')->nullable()->after('previous_meter_id');
            $table->double('retired_total_consumption')->nullable()->after('installed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropForeign(['previous_meter_id']);
            $table->dropUnique(['previous_meter_id']);
            $table->dropColumn(['previous_meter_id', 'installed_at', 'retired_total_consumption']);
        });
    }
};
