<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_halls', function (Blueprint $table) {
            $table->string('monitor_token_hash', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('current_question_id')->nullable();
            $table->unsignedSmallInteger('current_question_number')->nullable();
            $table->unsignedSmallInteger('total_questions')->nullable();
            $table->unsignedInteger('last_sequence')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('exam_halls', function (Blueprint $table) {
            $table->dropColumn([
                'monitor_token_hash', 'last_seen_at', 'current_question_id',
                'current_question_number', 'total_questions', 'last_sequence',
            ]);
        });
    }
};
