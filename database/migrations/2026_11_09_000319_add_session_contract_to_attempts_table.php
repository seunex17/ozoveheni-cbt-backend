<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->string('access_token_hash', 64)->nullable();
            $table->string('login_key_hash', 64)->nullable();
            $table->unique(['student_id', 'login_key_hash']);
            $table->dateTime('expires_at')->nullable();
            $table->json('question_order')->nullable();
            $table->uuid('submission_id')->nullable();
            $table->string('submission_payload_hash', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'login_key_hash']);
            $table->dropColumn(['access_token_hash', 'login_key_hash', 'expires_at', 'question_order', 'submission_id', 'submission_payload_hash']);
        });
    }
};
