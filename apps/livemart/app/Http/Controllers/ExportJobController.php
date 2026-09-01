<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ExportJob;
use App\Jobs\Export\ExportExcelJob;
use Maatwebsite\Excel\Facades\Excel;

class ExportJobController extends Controller
{
    /**
     * Dispatch an export job to queue
     */
    public function dispatchExport(Request $request)
    {
        $exportClass = $request->input('export_class');
        $exportParams = $request->input('export_params', []);
        $filename = $request->input('filename', 'export-' . date('Y-m-d-His') . '.xlsx');
        $name = $request->input('name', 'Export');

        if (!$exportClass || !class_exists($exportClass)) {
            return response()->json(['error' => 'Invalid export class'], 400);
        }

        $exportJob = ExportJob::create([
            'type' => 'export',
            'name' => $name,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'payload' => [
                'export_class' => $exportClass,
                'export_params' => $exportParams,
                'filename' => $filename,
            ],
            'file_name' => $filename,
        ]);

        ExportExcelJob::dispatch($exportJob->id, $exportClass, $exportParams, $filename);

        return response()->json([
            'success' => true,
            'job_id' => $exportJob->id,
            'message' => 'Export sedang diproses di background. Anda akan mendapat notifikasi jika sudah selesai.',
        ]);
    }

    /**
     * Check job status
     */
    public function status($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'name' => $job->name,
            'error' => $job->error,
            'result' => $job->result,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }

    /**
     * Download exported file
     */
    public function download($id)
    {
        $job = ExportJob::findOrFail($id);

        if (!$job->isCompleted()) {
            return redirect()->back()->with('error', 'File belum selesai diproses.');
        }

        $filePath = $job->file_path;
        if (!$filePath || !Storage::exists($filePath)) {
            return redirect()->back()->with('error', 'File tidak ditemukan.');
        }

        return Storage::download($filePath, $job->file_name);
    }

    /**
     * Get all jobs for current user
     */
    public function index(Request $request)
    {
        $jobs = ExportJob::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($jobs);
    }

    /**
     * Dispatch export using Maatwebsite Excel directly via queue
     * This is a simpler approach - just store the Excel and return the path
     */
    public function dispatchMaatwebsiteExport(Request $request)
    {
        $request->validate([
            'export_class' => 'required|string',
            'filename' => 'required|string',
            'name' => 'required|string',
            'params' => 'nullable|array',
        ]);

        $exportClass = $request->input('export_class');
        $filename = $request->input('filename');
        $name = $request->input('name');
        $params = $request->input('params', []);

        if (!class_exists($exportClass)) {
            return response()->json(['error' => 'Export class not found'], 400);
        }

        $exportJob = ExportJob::create([
            'type' => 'export',
            'name' => $name,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'payload' => $request->all(),
            'file_name' => $filename,
        ]);

        // Dispatch to queue
        ExportExcelJob::dispatch($exportJob->id, $exportClass, $params, $filename);

        return response()->json([
            'success' => true,
            'job_id' => $exportJob->id,
        ]);
    }
}
