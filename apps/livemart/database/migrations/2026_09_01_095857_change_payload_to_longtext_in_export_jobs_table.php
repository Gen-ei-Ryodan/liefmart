<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->longText('payload')->nullable()->change();
            $table->longText('result')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('export_jobs', function (Blueprint $table) {
            $table->text('payload')->nullable()->change();
            $table->text('result')->nullable()->change();
        });
    }
};
