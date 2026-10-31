<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consumption across a meter swap is now derived from each meter's own readings,
     * so the stored running total is no longer needed.
     */
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('retired_total_consumption');
        });
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->double('retired_total_consumption')->nullable()->after('installed_at');
        });
    }
};
