<?php

namespace App\Traits;

use App\Models\ExportJob;
use App\Jobs\Export\ExportExcelJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait QueueExport
{
    /**
     * Export to Excel — sync by default, queue if $useQueue=true
     *
     * @param object $exportInstance The Maatwebsite Excel export instance
     * @param string $filename The download filename
     * @param string $name Human-readable job name
     * @param bool $useQueue Dispatch to queue instead of synchronous
     * @return BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    protected function queueExcelExport($exportInstance, string $filename, string $name = 'Export', bool $useQueue = false)
    {
        if (!$useQueue) {
            return Excel::download($exportInstance, $filename);
        }

        $exportClass = get_class($exportInstance);
        $reflection = new \ReflectionClass($exportInstance);
        $constructorParams = [];

        if ($reflection->getConstructor()) {
            foreach ($reflection->getConstructor()->getParameters() as $param) {
                $propName = $param->getName();
                $prop = $reflection->getProperty($propName);
                $prop->setAccessible(true);
                $constructorParams[] = $prop->getValue($exportInstance);
            }
        }

        $safeParams = $this->convertExportParamsToSafe($constructorParams);

        $exportJob = ExportJob::create([
            'type' => 'export',
            'name' => $name,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'payload' => [
                'export_class' => $exportClass,
                'export_params' => $safeParams,
                'filename' => $filename,
            ],
            'file_name' => $filename,
        ]);

        ExportExcelJob::dispatch($exportJob->id, $exportClass, $safeParams, $filename);

        return response()->json([
            'success' => true,
            'job_id' => $exportJob->id,
            'message' => "Export '{$name}' sedang diproses di background.",
        ]);
    }

    /**
     * Convert export constructor params to a format safe for serialization.
     * Eloquent Builders → array of constraints, Collections → array of model arrays.
     */
    private function convertExportParamsToSafe(array $params): array
    {
        $safe = [];
        foreach ($params as $param) {
            $safe[] = $this->convertToSafe($param);
        }
        return $safe;
    }

    /**
     * Convert a single value to a queue-safe format.
     */
    private function convertToSafe($value)
    {
        if ($value instanceof Builder) {
            return [
                '__type' => 'query_builder',
                'model_class' => get_class($value->getModel()),
                'eager_loads' => $value->getEagerLoads() ? array_keys($value->getEagerLoads()) : [],
                'query_constraints' => $this->extractBuilderConstraints($value),
            ];
        }

        if ($value instanceof Collection) {
            return [
                '__type' => 'collection',
                'model_class' => $value->first() ? get_class($value->first()) : null,
                'items' => $value->map(fn($m) => $m->toArray())->values()->all(),
            ];
        }

        if ($value instanceof Model) {
            return [
                '__type' => 'model',
                'model_class' => get_class($value),
                'attributes' => $value->toArray(),
            ];
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->convertToSafe($v), $value);
        }

        return $value;
    }

    /**
     * Extract query constraints from an Eloquent Builder into a serializable array.
     */
    private function extractBuilderConstraints(Builder $query): array
    {
        $constraints = [];

        $ref = new \ReflectionClass($query);
        $wheresProp = $ref->getProperty('wheres');
        $wheresProp->setAccessible(true);
        $constraints['wheres'] = $wheresProp->getValue($query);

        $ordersProp = $ref->getProperty('orders');
        $ordersProp->setAccessible(true);
        $constraints['orders'] = $ordersProp->getValue($query);

        $limitProp = $ref->getProperty('limit');
        $limitProp->setAccessible(true);
        $constraints['limit'] = $limitProp->getValue($query);

        $offsetProp = $ref->getProperty('offset');
        $offsetProp->setAccessible(true);
        $constraints['offset'] = $offsetProp->getValue($query);

        $joinsProp = $ref->getProperty('joins');
        if ($joinsProp) {
            $joinsProp->setAccessible(true);
            $constraints['joins'] = $joinsProp->getValue($query);
        }

        $selectProp = $ref->getProperty('columns');
        if ($selectProp) {
            $selectProp->setAccessible(true);
            $constraints['columns'] = $selectProp->getValue($query);
        }

        $globalScopesProp = $ref->getProperty('removedScopes');
        if ($globalScopesProp) {
            $globalScopesProp->setAccessible(true);
            $constraints['removed_scopes'] = $globalScopesProp->getValue($query);
        }

        return $constraints;
    }
}
