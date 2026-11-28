<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->boolean('exam_security_enabled')->default(true)->after('registration');
            $table->unsignedInteger('exam_security_violation_limit')->default(5)->after('exam_security_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn(['exam_security_enabled', 'exam_security_violation_limit']);
        });
    }
};
