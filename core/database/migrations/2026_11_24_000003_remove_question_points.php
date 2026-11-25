<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('questions', 'points')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('points');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('questions', 'points')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->unsignedInteger('points')->default(1)->after('answer_data');
            });
        }
    }
};
