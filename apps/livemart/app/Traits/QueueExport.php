<?php

namespace App\Traits;

use App\Models\ExportJob;
use App\Jobs\Export\ExportExcelJob;
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

        $exportJob = ExportJob::create([
            'type' => 'export',
            'name' => $name,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'payload' => [
                'export_class' => $exportClass,
                'export_params' => $constructorParams,
                'filename' => $filename,
            ],
            'file_name' => $filename,
        ]);

        ExportExcelJob::dispatch($exportJob->id, $exportClass, $constructorParams, $filename);

        return response()->json([
            'success' => true,
            'job_id' => $exportJob->id,
            'message' => "Export '{$name}' sedang diproses di background.",
        ]);
    }
}
