<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'question_no')) {
                $table->string('question_no', 30)->nullable()->after('exam_id');
            }

            if (Schema::hasColumn('questions', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (Schema::hasColumn('questions', 'question_no')) {
                $table->dropColumn('question_no');
            }
        });
    }
};
