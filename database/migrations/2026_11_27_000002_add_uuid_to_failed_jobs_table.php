<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Laravel 8+ stores failed jobs by uuid (queue.failed.driver = database-uuids).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable()->after('id');
        });

        foreach (DB::table('failed_jobs')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('failed_jobs')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
