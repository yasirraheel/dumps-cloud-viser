<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->json('retake_question_ids')->nullable()->after('committed_questions');
        });
    }

    public function down(): void
    {
        Schema::table('attend_exams', function (Blueprint $table) {
            $table->dropColumn('retake_question_ids');
        });
    }
};
