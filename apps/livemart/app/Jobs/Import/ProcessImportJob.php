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

            $errors = $result['errors'] ?? [];

            // Jika ada error, processImport sudah melakukan ROLLBACK (tidak ada data
            // yang tersimpan). Ini BUKAN sukses — tandai failed supaya UI tidak
            // menampilkan status completed untuk import yang sebenarnya gagal.
            if (!empty($errors)) {
                $errorMessage = 'Import dibatalkan, tidak ada data yang tersimpan (rollback). '
                    . $this->formatErrors($errors);
                Log::error('ProcessImportJob: ' . $errorMessage);
                $exportJob->markFailed($errorMessage);
                return;
            }

            $exportJob->markCompleted([
                'success' => $result['success'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'errors' => [],
            ]);
        } catch (\Exception $e) {
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;
            Log::error('ProcessImportJob failed: ' . $e->getMessage());
            $exportJob->markFailed($e->getMessage());
        }
    }

    /**
     * Gabungkan error dari processImport menjadi satu pesan yang mudah dibaca.
     *
     * format error bisa berupa list string, atau ['invalidData' => [...]]
     */
    protected function formatErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $key => $value) {
            if (is_array($value)) {
                $messages[] = $key . ': ' . implode(', ', array_map('strval', array_values($value)));
            } else {
                $messages[] = (string) $value;
            }
        }

        return implode(' | ', $messages);
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
