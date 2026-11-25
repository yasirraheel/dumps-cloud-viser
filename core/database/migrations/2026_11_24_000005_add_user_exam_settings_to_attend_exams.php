<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->json('exam_settings')->nullable();
            $table->json('question_order')->nullable();
            $table->json('option_orders')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->dropColumn(['exam_settings', 'question_order', 'option_orders']);
        });
    }
};
