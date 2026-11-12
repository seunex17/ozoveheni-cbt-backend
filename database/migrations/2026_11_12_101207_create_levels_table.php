<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 20);
            $table->timestamps();
        });
        DB::table('levels')->insert([
            ['code' => 'nd', 'name' => 'ND', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'hnd', 'name' => 'HND', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach (['students', 'exams'] as $table) {
            DB::table($table)->whereIn(DB::raw('LOWER(TRIM(level))'), ['nd', 'nd1', 'nd2'])->update(['level' => 'nd']);
            DB::table($table)->whereIn(DB::raw('LOWER(TRIM(level))'), ['hnd', 'hnd1', 'hnd2'])->update(['level' => 'hnd']);
        }
    }

    public function down(): void
    {
        // Programme normalization is retained: the original year suffix cannot be reconstructed.
        Schema::dropIfExists('levels');
    }
};
