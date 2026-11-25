<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->unsignedInteger('remaining_seconds')->nullable()->after('end_time');
            $table->unsignedInteger('current_question')->default(0)->after('remaining_seconds');
            $table->json('session_answers')->nullable()->after('current_question');
            $table->json('session_answer_data')->nullable()->after('session_answers');
            $table->json('flagged_questions')->nullable()->after('session_answer_data');
            $table->json('revealed_questions')->nullable()->after('flagged_questions');
            $table->json('committed_questions')->nullable()->after('revealed_questions');
        });
    }

    public function down(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->dropColumn([
                'remaining_seconds',
                'current_question',
                'session_answers',
                'session_answer_data',
                'flagged_questions',
                'revealed_questions',
                'committed_questions',
            ]);
        });
    }
};
