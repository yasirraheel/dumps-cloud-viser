<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->text('instructions')->nullable()->after('description');
            $table->string('exam_type', 30)->default('practice')->after('subjects');
            $table->boolean('randomize_questions')->default(false)->after('exam_type');
            $table->boolean('randomize_options')->default(false)->after('randomize_questions');
            $table->boolean('show_answers')->default(true)->after('randomize_options');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_type', 30)->default('single_choice')->after('title');
            $table->longText('explanation')->nullable()->after('question_type');
            $table->string('exhibit_image')->nullable()->after('explanation');
            $table->string('original_source_image')->nullable()->after('exhibit_image');
            $table->longText('cli_snippet')->nullable()->after('original_source_image');
            $table->json('answer_data')->nullable()->after('cli_snippet');
            $table->json('metadata')->nullable()->after('answer_data');
            $table->unsignedInteger('points')->default(10)->after('metadata');
            $table->unsignedInteger('sort_order')->default(0)->after('points');
        });

        // The original script used VARCHAR(255), while exhibit-based CCNA
        // questions and CLI answers can be substantially longer.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY title TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'instructions', 'exam_type',
                'randomize_questions', 'randomize_options', 'show_answers',
            ]);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn([
                'question_type', 'explanation', 'exhibit_image',
                'original_source_image', 'cli_snippet', 'answer_data',
                'metadata', 'points', 'sort_order',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY title VARCHAR(255) NOT NULL');
        }
    }
};
