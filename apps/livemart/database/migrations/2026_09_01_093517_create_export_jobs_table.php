<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'export' or 'import'
            $table->string('name'); // e.g. 'MonthlySalesSummary', 'TiktokImport'
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('payload')->nullable(); // JSON encoded parameters
            $table->text('result')->nullable(); // JSON encoded result (filename, summary, etc.)
            $table->text('error')->nullable(); // Error message if failed
            $table->string('file_path')->nullable(); // Path to generated file
            $table->string('file_name')->nullable(); // Original filename
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
