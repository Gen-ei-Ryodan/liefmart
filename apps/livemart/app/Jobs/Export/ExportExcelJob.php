<?php

namespace App\Jobs\Export;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ExportJob;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

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

            set_time_limit(600);
            ini_set('memory_limit', '512M');

            $resolvedParams = $this->resolveExportParams($this->exportParams);
            $exportInstance = new $this->exportClass(...$resolvedParams);
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

    /**
     * Resolve serialized export params back to their original types.
     */
    private function resolveExportParams(array $params): array
    {
        $resolved = [];
        foreach ($params as $param) {
            $resolved[] = $this->resolveParam($param);
        }
        return $resolved;
    }

    /**
     * Resolve a single param from its serialized form.
     */
    private function resolveParam($param)
    {
        if (!is_array($param)) {
            return $param;
        }

        if (!isset($param['__type'])) {
            return array_map(fn($v) => $this->resolveParam($v), $param);
        }

        switch ($param['__type']) {
            case 'query_builder':
                return $this->rebuildQueryBuilder(
                    $param['model_class'],
                    $param['query_constraints'] ?? [],
                    $param['eager_loads'] ?? []
                );

            case 'collection':
                $modelClass = $param['model_class'] ?? \Illuminate\Database\Eloquent\Model::class;
                $items = collect($param['items'])->map(function ($attrs) use ($modelClass) {
                    return new $modelClass($attrs);
                });
                return $items;

            case 'model':
                $modelClass = $param['model_class'];
                return new $modelClass($param['attributes']);

            default:
                return $param;
        }
    }

    /**
     * Rebuild an Eloquent query builder from stored constraints.
     */
    private function rebuildQueryBuilder(string $modelClass, array $constraints, array $eagerLoads)
    {
        $query = $modelClass::query();

        foreach ($eagerLoads as $relation) {
            $query->with($relation);
        }

        $ref = new \ReflectionClass($query);

        if (!empty($constraints['wheres'])) {
            $wheresProp = $ref->getProperty('wheres');
            $wheresProp->setAccessible(true);
            $wheresProp->setValue($query, $constraints['wheres']);
        }

        if (!empty($constraints['orders'])) {
            $ordersProp = $ref->getProperty('orders');
            $ordersProp->setAccessible(true);
            $ordersProp->setValue($query, $constraints['orders']);
        }

        if (isset($constraints['limit'])) {
            $limitProp = $ref->getProperty('limit');
            $limitProp->setAccessible(true);
            $limitProp->setValue($query, $constraints['limit']);
        }

        if (isset($constraints['offset'])) {
            $offsetProp = $ref->getProperty('offset');
            $offsetProp->setAccessible(true);
            $offsetProp->setValue($query, $constraints['offset']);
        }

        if (!empty($constraints['joins'])) {
            $joinsProp = $ref->getProperty('joins');
            $joinsProp->setAccessible(true);
            $joinsProp->setValue($query, $constraints['joins']);
        }

        if (!empty($constraints['columns'])) {
            $columnsProp = $ref->getProperty('columns');
            $columnsProp->setAccessible(true);
            $columnsProp->setValue($query, $constraints['columns']);
        }

        if (!empty($constraints['removed_scopes'])) {
            $removedScopesProp = $ref->getProperty('removedScopes');
            $removedScopesProp->setAccessible(true);
            $removedScopesProp->setValue($query, $constraints['removed_scopes']);
        }

        return $query;
    }

    public function failed(\Throwable $exception)
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if ($exportJob) {
            $exportJob->markFailed($exception->getMessage());
        }
    }
}
