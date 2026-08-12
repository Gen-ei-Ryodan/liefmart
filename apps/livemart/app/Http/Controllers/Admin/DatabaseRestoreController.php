<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class DatabaseRestoreController extends Controller
{
    public function index()
    {
        // Check if user is superadmin (role_id = 1)
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized access. Only superadmin can access this feature.');
        }
        
        // Get list of SQL files in upload directory
        $uploadDir = storage_path('app/sql-uploads');
        $sqlFiles = [];
        
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '/*.sql');
            foreach ($files as $file) {
                $sqlFiles[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'path' => $file
                ];
            }
            
            // Sort by modification time (newest first)
            usort($sqlFiles, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
        }
        
        return view('admin.database-restore.index', compact('sqlFiles'));
    }

    public function restore(Request $request)
    {
        // Check if user is superadmin (role_id = 1)
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized access. Only superadmin can restore database.');
        }
        
        $request->validate([
            'sql_file' => 'required|file|mimes:sql,txt|max:2048', // Max 2MB (PHP limit)
        ]);

        try {
            // Check if file was uploaded successfully
            if (!$request->hasFile('sql_file')) {
                return redirect()->route('database-restore.index')
                    ->with('error', 'Tidak ada file yang di-upload. Silakan pilih file SQL.');
            }
            
            $sqlFile = $request->file('sql_file');
            
            // Check if file upload was successful
            if (!$sqlFile->isValid()) {
                $error = $sqlFile->getError();
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                    UPLOAD_ERR_PARTIAL => 'File hanya ter-upload sebagian',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang di-upload',
                    UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                    UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
                ];
                
                $errorMessage = $errorMessages[$error] ?? 'Error upload tidak diketahui: ' . $error;
                return redirect()->route('database-restore.index')
                    ->with('error', 'Gagal upload file: ' . $errorMessage);
            }
            
            // Simpan file SQL sementara
            $fileName = 'restore_' . time() . '.sql';
            $filePath = storage_path('app/temp/' . $fileName);
            
            // Pastikan direktori temp ada
            if (!File::exists(storage_path('app/temp'))) {
                File::makeDirectory(storage_path('app/temp'), 0755, true);
            }
            
            $sqlFile->move(storage_path('app/temp'), $fileName);
            
            // Validasi format SQL file
            $this->validateSqlFile($filePath);
            
            // Backup database saat ini (opsional)
            $this->backupCurrentDatabase();
            
            // Restore database dari SQL file
            $this->restoreDatabase($filePath);
            
            // Hapus file temporary
            File::delete($filePath);
            
            // Clear cache
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            return redirect()->route('database-restore.index')
                ->with('success', 'Database berhasil di-restore dari SQL file!');
                
        } catch (Exception $e) {
            Log::error('Database restore failed: ' . $e->getMessage());
            
            // Hapus file temporary jika ada
            if (isset($filePath) && File::exists($filePath)) {
                File::delete($filePath);
            }
            
            return redirect()->route('database-restore.index')
                ->with('error', 'Gagal restore database: ' . $e->getMessage());
        }
    }

    private function backupCurrentDatabase()
    {
        try {
            $backupFileName = 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/' . $backupFileName);
            
            // Pastikan direktori backups ada
            if (!File::exists(storage_path('app/backups'))) {
                File::makeDirectory(storage_path('app/backups'), 0755, true);
            }
            
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');

            // Command yang sangat cepat seperti phpMyAdmin
            // Password dikirim via env MYSQL_PWD, bukan di argumen command,
            // supaya tidak bocor ke daftar proses (ps aux) atau shell history.
            $command = [
                'mysqldump', "-h{$host}", "-P{$port}", "-u{$username}",
                '--quick', '--lock-tables=false',
                $database,
            ];

            $process = new Process($command, null, ['MYSQL_PWD' => $password]);
            $process->setTimeout(300);
            $backupFile = fopen($backupPath, 'w');
            $process->run(function ($type, $buffer) use ($backupFile) {
                if ($type === Process::OUT) {
                    fwrite($backupFile, $buffer);
                }
            });
            fclose($backupFile);

            if (!$process->isSuccessful()) {
                throw new Exception('mysqldump gagal: ' . trim($process->getErrorOutput()));
            }

            // Verifikasi file backup dibuat
            if (!File::exists($backupPath) || File::size($backupPath) == 0) {
                throw new Exception('File backup tidak dibuat atau kosong');
            }
            
            Log::info('Database backup created successfully: ' . $backupFileName);
            
        } catch (Exception $e) {
            Log::error('Database backup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function restoreDatabase($sqlFilePath)
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        // Optimized restore command untuk MariaDB dump
        // Menggunakan --force untuk mengabaikan error minor dan --verbose untuk logging
        // Password via env MYSQL_PWD agar tidak bocor ke daftar proses.
        $command = [
            'mysql', "-h{$host}", "-P{$port}", "-u{$username}",
            '--force', '--verbose', $database,
        ];

        $process = new Process($command, null, ['MYSQL_PWD' => $password]);
        $process->setTimeout(300);
        $process->setInput(file_get_contents($sqlFilePath));
        $process->run();
        $output = $process->getOutput() . $process->getErrorOutput();

        // Verifikasi restore berhasil
        if (strpos($output, 'ERROR') !== false && strpos($output, 'ERROR 1062') === false) {
            // ERROR 1062 adalah duplicate key error yang bisa diabaikan untuk restore
            throw new Exception('Gagal restore database: ' . substr($output, -2000));
        }

        // Log success
        Log::info('Database restore completed successfully');
    }
    
    public function downloadBackup()
    {
        // Check if user is superadmin (role_id = 1)
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized access. Only superadmin can download backup.');
        }
        
        $backupPath = storage_path('app/backups');
        
        // Pastikan direktori backups ada
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }
        
        // Buat backup baru setiap kali download
        try {
            $this->backupCurrentDatabase();
            $files = File::files($backupPath);
            
            if (empty($files)) {
                return redirect()->route('database-restore.index')
                    ->with('error', 'Gagal membuat backup database');
            }
            
        } catch (Exception $e) {
            return redirect()->route('database-restore.index')
                ->with('error', 'Gagal membuat backup: ' . $e->getMessage());
        }
        
        // Ambil file backup terbaru
        $latestFile = collect($files)->sortByDesc(function ($file) {
            return $file->getMTime();
        })->first();
        
        return response()->download($latestFile->getPathname());
    }
    
    public function restoreFromServer(Request $request)
    {
        // Check if user is superadmin (role_id = 1)
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized access. Only superadmin can restore database.');
        }
        
        $request->validate([
            'server_file' => 'required|string|max:255',
        ]);
        
        Log::info('Server restore request received', [
            'server_file' => $request->server_file,
            'all_input' => $request->all()
        ]);
        
        try {
            $fileName = basename($request->server_file);
            $uploadDir = realpath(storage_path('app/sql-uploads'));

            // Validate direktori sql-uploads tersedia
            if ($uploadDir === false) {
                Log::error('Upload directory not found for restore');
                return redirect()->route('database-restore.index')
                    ->with('error', 'Direktori upload tidak ditemukan.');
            }

            $filePath = realpath($uploadDir . '/' . $fileName);

            Log::info('Server restore attempt: ' . $fileName . ' at ' . $filePath);

            // Validate file path benar-benar di dalam direktori sql-uploads
            if ($filePath === false || strpos($filePath, $uploadDir . DIRECTORY_SEPARATOR) !== 0) {
                Log::error('Invalid path traversal attempt: ' . $fileName);
                return redirect()->route('database-restore.index')
                    ->with('error', 'Path file tidak valid: ' . $fileName);
            }

            // Validate file exists
            if (!file_exists($filePath)) {
                Log::error('File not found: ' . $filePath);
                return redirect()->route('database-restore.index')
                    ->with('error', 'File tidak ditemukan di server: ' . $fileName);
            }
            
            // Validate file size (max 100MB for server files)
            $fileSize = filesize($filePath);
            Log::info('File size: ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            
            if ($fileSize > 100 * 1024 * 1024) { // 100MB
                return redirect()->route('database-restore.index')
                    ->with('error', 'File terlalu besar (maksimal 100MB): ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            }
            
            // Validate SQL file format
            Log::info('Validating SQL file format...');
            $this->validateSqlFile($filePath);
            Log::info('SQL file validation passed');
            
            // Backup database saat ini
            $this->backupCurrentDatabase();
            
            // Restore database dari file server
            $this->restoreDatabase($filePath);
            
            // Clear cache
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            return redirect()->route('database-restore.index')
                ->with('success', 'Database berhasil di-restore dari file server: ' . $fileName);
                
        } catch (Exception $e) {
            Log::error('Server restore failed: ' . $e->getMessage());
            
            return redirect()->route('database-restore.index')
                ->with('error', 'Gagal restore database: ' . $e->getMessage());
        }
    }
    
    private function validateSqlFile($filePath)
    {
        try {
            // Baca beberapa baris pertama untuk validasi format
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception('Tidak dapat membaca file SQL');
            }
            
            $firstLines = '';
            $lineCount = 0;
            while (($line = fgets($handle)) !== false && $lineCount < 50) {
                $firstLines .= $line;
                $lineCount++;
            }
            fclose($handle);
            
            // Validasi format MariaDB/MySQL dump - lebih fleksibel
            $validPatterns = [
                'MariaDB dump',
                'MySQL dump',
                'SET.*CHARACTER_SET_CLIENT',
                'CREATE TABLE',
                'INSERT INTO',
                'DROP TABLE',
                'CREATE DATABASE'
            ];
            
            $foundPatterns = 0;
            foreach ($validPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $firstLines)) {
                    $foundPatterns++;
                }
            }
            
            // Minimal 1 pattern harus ditemukan (lebih fleksibel)
            if ($foundPatterns < 1) {
                throw new Exception('File SQL tidak valid. Pastikan file adalah MariaDB/MySQL dump yang valid.');
            }
            
            Log::info('SQL file validation passed - found ' . $foundPatterns . ' valid patterns');
            
        } catch (Exception $e) {
            Log::error('SQL file validation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
