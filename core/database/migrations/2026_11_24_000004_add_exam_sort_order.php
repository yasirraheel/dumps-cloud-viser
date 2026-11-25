<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('title');
        });

        $bankOrder = [
            'Exam Bank A' => 1,
            'Exam Bank B' => 2,
            'Exam Bank C' => 3,
            'Exam Bank D' => 4,
            'Drag & Drop Special Bank' => 5,
        ];

        foreach ($bankOrder as $title => $order) {
            DB::table('exams')->where('title', $title)->update(['sort_order' => $order]);
        }

        DB::table('exams')->whereNotIn('title', array_keys($bankOrder))
            ->update(['sort_order' => DB::raw('100 + id')]);

        DB::table('exams')->where('title', 'Exam Bank B (Legacy Demo)')
            ->update(['sort_order' => 999999]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
