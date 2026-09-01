<?php

namespace App\Jobs\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ExportJob;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    protected $exportJobId;
    protected $exportClass;
    protected $exportParams;
    protected $filename;

    public function __construct($exportJobId, $exportClass, $exportParams, $filename)
    {
        $this->exportJobId = $exportJobId;
        $this->exportClass = $exportClass;
        $this->exportParams = $exportParams;
        $this->filename = $filename;
    }

    public function handle()
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (!$exportJob) {
            Log::error('ExportExcelJob: ExportJob not found: ' . $this->exportJobId);
            return;
        }

        try {
            $exportJob->markProcessing();

            // Increase limits for large exports
            set_time_limit(600);
            ini_set('memory_limit', '512M');

            $exportInstance = new $this->exportClass(...$this->exportParams);
            $filePath = 'exports/' . $this->filename;

            Excel::store($exportInstance, $filePath);

            $exportJob->markCompleted([
                'file_path' => $filePath,
                'file_name' => $this->filename,
            ]);
        } catch (\Exception $e) {
            Log::error('ExportExcelJob failed: ' . $e->getMessage());
            $exportJob->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $exception)
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if ($exportJob) {
            $exportJob->markFailed($exception->getMessage());
        }
    }
}
