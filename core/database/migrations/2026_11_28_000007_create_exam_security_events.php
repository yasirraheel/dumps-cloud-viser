<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_security_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attend_exam_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('event', 64)->index();
            $table->text('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['attend_exam_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_security_events');
    }
};
