<?php

namespace App\Jobs\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ExportJob;

class ImportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $exportJobId;
    protected $importClass;
    protected $importParams;

    public function __construct($exportJobId, $importClass, $importParams = [])
    {
        $this->exportJobId = $exportJobId;
        $this->importClass = $importClass;
        $this->importParams = $importParams;
    }

    public function handle()
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (!$exportJob) {
            Log::error('ImportExcelJob: ExportJob not found: ' . $this->exportJobId);
            return;
        }

        try {
            $exportJob->markProcessing();

            $importClass = $this->importClass;
            $import = new $importClass(...array_values($this->importParams));
            $result = $import->processImport();

            $exportJob->markCompleted([
                'success' => $result['success'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('ImportExcelJob failed: ' . $e->getMessage());
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
