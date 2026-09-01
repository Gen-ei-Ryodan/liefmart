<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'user_id',
        'status',
        'payload',
        'result',
        'error',
        'file_path',
        'file_name',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function markProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $result = [])
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'file_path' => $result['file_path'] ?? null,
            'file_name' => $result['file_name'] ?? null,
            'completed_at' => now(),
        ]);
    }

    public function markFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
