<?php

namespace App\Jobs\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ExportJob;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    protected $exportJobId;
    protected $importClassName;
    protected $platformId;
    protected $data;
    protected $unmappedProducts;

    public function __construct(
        int $exportJobId,
        string $importClassName,
        int $platformId,
        array $data,
        array $unmappedProducts = []
    ) {
        $this->exportJobId = $exportJobId;
        $this->importClassName = $importClassName;
        $this->platformId = $platformId;
        $this->data = $data;
        $this->unmappedProducts = $unmappedProducts;
    }

    public function handle()
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (!$exportJob) {
            Log::error('ProcessImportJob: ExportJob not found: ' . $this->exportJobId);
            return;
        }

        try {
            $exportJob->markProcessing();

            set_time_limit(600);
            ini_set('memory_limit', '512M');

            // Enable consolidation flag
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = true;

            // Set main category
            session(['main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId()]);
            session(['main_category_name' => 'Kosmetik']);

            $importClass = $this->importClassName;
            $import = new $importClass($this->platformId);
            $import->setData($this->data);
            $import->setUnmappedProducts($this->unmappedProducts);

            $result = $import->processImport();

            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;

            $exportJob->markCompleted([
                'success' => $result['success'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Exception $e) {
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;
            Log::error('ProcessImportJob failed: ' . $e->getMessage());
            $exportJob->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $exception)
    {
        \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;
        $exportJob = ExportJob::find($this->exportJobId);
        if ($exportJob) {
            $exportJob->markFailed($exception->getMessage());
        }
    }
}
