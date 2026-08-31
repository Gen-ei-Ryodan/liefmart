@extends('layouts.app')

@section('title', 'Database Restore')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-database me-2"></i>Database Restore
                    </h5>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-secondary rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>

                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif


                    <!-- Warning Card -->
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Peringatan Penting
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="text-warning mb-3">⚠️ Tindakan ini akan mengganti seluruh data database!</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-arrow-right text-warning me-2"></i>Semua data saat ini akan dihapus</li>
                                        <li><i class="fas fa-arrow-right text-warning me-2"></i>Data akan diganti dengan data dari SQL file</li>
                                        <li><i class="fas fa-arrow-right text-warning me-2"></i>Pastikan SQL file valid dan berasal dari sistem yang sama</li>
                                        <li><i class="fas fa-arrow-right text-warning me-2"></i>Backup otomatis akan dibuat sebelum restore</li>
                                        <li><i class="fas fa-arrow-right text-warning me-2"></i><strong>Batasan file: Maksimal 2MB (karena PHP limits)</strong></li>
                                    </ul>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-database text-warning" style="font-size: 4rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Options Tabs -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-upload me-2"></i>Upload SQL File
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Nav tabs -->
                            <ul class="nav nav-tabs mb-3" id="uploadTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="normal-upload-tab" data-bs-toggle="tab" 
                                            data-bs-target="#normal-upload" type="button" role="tab">
                                        <i class="fas fa-upload me-1"></i>Normal Upload (≤2MB)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="chunked-upload-tab" data-bs-toggle="tab" 
                                            data-bs-target="#chunked-upload" type="button" role="tab">
                                        <i class="fas fa-layer-group me-1"></i>Chunked Upload (≤50MB)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="server-upload-tab" data-bs-toggle="tab" 
                                            data-bs-target="#server-upload" type="button" role="tab">
                                        <i class="fas fa-server me-1"></i>Server Files (≤100MB)
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab panes -->
                            <div class="tab-content" id="uploadTabContent">
                                <!-- Normal Upload -->
                                <div class="tab-pane fade show active" id="normal-upload" role="tabpanel">
                                    <form action="{{ route('database-restore.restore') }}" method="POST" enctype="multipart/form-data" id="restoreForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group mb-3">
                                                    <label for="sql_file" class="form-control-label">
                                                        <i class="fas fa-file-code me-2"></i>Pilih SQL File
                                                    </label>
                                                    <input type="file" class="form-control @error('sql_file') is-invalid @enderror" 
                                                        id="sql_file" name="sql_file" accept=".sql,.txt" required>
                                                    <small class="form-text text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Format yang didukung: .sql, .txt (Maksimal 2MB)
                                                    </small>
                                                    @error('sql_file')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-end h-100">
                                                    <button type="submit" class="btn btn-primary w-100" 
                                                        onclick="return confirmRestore()">
                                                        <i class="fas fa-upload me-2"></i>Restore Database
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Chunked Upload -->
                                <div class="tab-pane fade" id="chunked-upload" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group mb-3">
                                                <label for="chunked_file" class="form-control-label">
                                                    <i class="fas fa-layer-group me-2"></i>Pilih SQL File (Besar)
                                                </label>
                                                <input type="file" class="form-control" id="chunked_file" accept=".sql,.txt">
                                                <small class="form-text text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Format yang didukung: .sql, .txt (Maksimal 50MB) - Otomatis dipecah menjadi chunks
                                                </small>
                                            </div>
                                            <div id="chunked-progress" class="mb-3" style="display: none;">
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <small class="text-muted">Uploading chunks...</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-end h-100">
                                                <button type="button" class="btn btn-success w-100" id="chunked-upload-btn">
                                                    <i class="fas fa-layer-group me-2"></i>Upload Chunked
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Server Files -->
                                <div class="tab-pane fade" id="server-upload" role="tabpanel">
                                    @if(count($sqlFiles) > 0)
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group mb-3">
                                                    <label for="server_file" class="form-control-label">
                                                        <i class="fas fa-server me-2"></i>Pilih File dari Server
                                                    </label>
                                                    <select class="form-control" id="server_file" name="server_file">
                                                        <option value="">-- Pilih File SQL --</option>
                                                        @foreach($sqlFiles as $file)
                                                            <option value="{{ $file['name'] }}">
                                                                {{ $file['name'] }} ({{ round($file['size'] / 1024 / 1024, 2) }}MB)
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <small class="form-text text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        File yang sudah ada di server (storage/app/sql-uploads/) - Total: {{ count($sqlFiles) }} file
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-end h-100">
                                                    <form action="{{ route('database-restore.server') }}" method="POST" id="serverRestoreForm">
                                                        @csrf
                                                        <input type="hidden" name="server_file" id="server_file_input">
                                                        <button type="submit" class="btn btn-info w-100" 
                                                            onclick="return confirmServerRestore()">
                                                            <i class="fas fa-server me-2"></i>Restore dari Server
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Tidak ada file SQL di server. Upload file ke folder <code>storage/app/sql-uploads/</code> terlebih dahulu.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- File Size Info -->
                    <div class="card mb-4 border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informasi File Size
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="text-info mb-3">📁 File SQL Anda: 3.1MB (Terlalu besar untuk upload)</h6>
                                    <p class="text-muted mb-2">
                                        <strong>✅ File telah dipecah menjadi 2 bagian:</strong>
                                    </p>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-file-code text-primary me-2"></i><strong>part_1.sql</strong> (1.9MB) - Upload terlebih dahulu</li>
                                        <li><i class="fas fa-file-code text-primary me-2"></i><strong>part_2.sql</strong> (1.2MB) - Upload setelah part_1 selesai</li>
                                        <li><i class="fas fa-info-circle text-info me-2"></i><strong>Lokasi:</strong> sql_parts/ folder di project root</li>
                                    </ul>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="fas fa-file-archive text-info" style="font-size: 3rem; opacity: 0.7;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Section -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-download me-2"></i>Download Backup
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-2">Backup Database Saat Ini</h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Klik tombol untuk membuat dan download backup database saat ini
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="{{ route('database-restore.download-backup') }}" class="btn btn-info">
                                        <i class="fas fa-download me-2"></i>Download Backup
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
function confirmRestore() {
    return confirm('⚠️ PERINGATAN!\n\n' +
        'Tindakan ini akan:\n' +
        '• Menghapus SEMUA data saat ini\n' +
        '• Mengganti dengan data dari SQL file\n' +
        '• Tidak dapat di-undo\n\n' +
        'Apakah Anda yakin ingin melanjutkan?');
}

function confirmServerRestore() {
    const selectedFile = document.getElementById('server_file').value;
    if (!selectedFile) {
        alert('Pilih file SQL terlebih dahulu!');
        return false;
    }
    
    // Set the hidden input value
    document.getElementById('server_file_input').value = selectedFile;
    
    return confirm('⚠️ PERINGATAN!\n\n' +
        'Tindakan ini akan:\n' +
        '• Menghapus SEMUA data saat ini\n' +
        '• Mengganti dengan data dari: ' + selectedFile + '\n' +
        '• Tidak dapat di-undo\n\n' +
        'Apakah Anda yakin ingin melanjutkan?');
}

// File input change handler
document.getElementById('sql_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = file.size / 1024 / 1024; // Convert to MB
        if (fileSize > 2) {
            alert('File terlalu besar! Maksimal 2MB');
            e.target.value = '';
            return;
        }
        
        // Show file info
        const fileInfo = document.createElement('div');
        fileInfo.className = 'mt-2 text-success';
        fileInfo.innerHTML = '<i class="fas fa-check-circle me-1"></i>File siap di-upload: ' + file.name + ' (' + fileSize.toFixed(2) + ' MB)';
        
        // Remove previous file info
        const prevInfo = e.target.parentNode.querySelector('.mt-2');
        if (prevInfo) {
            prevInfo.remove();
        }
        
        e.target.parentNode.appendChild(fileInfo);
    }
});

// Chunked upload functionality
document.getElementById('chunked-upload-btn').addEventListener('click', function() {
    const fileInput = document.getElementById('chunked_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Pilih file SQL terlebih dahulu!');
        return;
    }
    
    const fileSize = file.size / 1024 / 1024; // Convert to MB
    if (fileSize > 50) {
        alert('File terlalu besar! Maksimal 50MB untuk chunked upload');
        return;
    }
    
    if (!confirm('⚠️ PERINGATAN!\n\n' +
        'Tindakan ini akan:\n' +
        '• Menghapus SEMUA data saat ini\n' +
        '• Mengganti dengan data dari SQL file\n' +
        '• Tidak dapat di-undo\n\n' +
        'Apakah Anda yakin ingin melanjutkan?')) {
        return;
    }
    
    uploadChunkedFile(file);
});

function uploadChunkedFile(file) {
    const chunkSize = 1024 * 1024; // 1MB chunks
    const totalChunks = Math.ceil(file.size / chunkSize);
    const progressBar = document.querySelector('#chunked-progress .progress-bar');
    const progressContainer = document.getElementById('chunked-progress');
    
    progressContainer.style.display = 'block';
    
    let uploadedChunks = 0;
    
    function uploadChunk(chunkNumber) {
        const start = chunkNumber * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);
        
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_number', chunkNumber);
        formData.append('total_chunks', totalChunks);
        formData.append('file_name', file.name);
        formData.append('file_size', file.size);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        fetch('{{ route("chunked-upload.chunk") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uploadedChunks++;
                const progress = (uploadedChunks / totalChunks) * 100;
                progressBar.style.width = progress + '%';
                progressBar.textContent = Math.round(progress) + '%';
                
                if (uploadedChunks < totalChunks) {
                    uploadChunk(uploadedChunks);
                } else {
                    // All chunks uploaded, now merge
                    mergeChunks(data.file_id, file.name, totalChunks);
                }
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        })
        .catch(error => {
            console.error('Chunk upload error:', error);
            alert('Error uploading chunk: ' + error.message);
            progressContainer.style.display = 'none';
        });
    }
    
    function mergeChunks(fileId, fileName, totalChunks) {
        const formData = new FormData();
        formData.append('file_id', fileId);
        formData.append('file_name', fileName);
        formData.append('total_chunks', totalChunks);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        fetch('{{ route("chunked-upload.merge") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File berhasil di-upload! Sekarang akan melakukan restore database...');
                // Redirect to restore with the merged file
                window.location.href = '{{ route("database-restore.index") }}?restore_file=' + encodeURIComponent(data.file_name);
            } else {
                throw new Error(data.error || 'Merge failed');
            }
        })
        .catch(error => {
            console.error('Merge error:', error);
            alert('Error merging chunks: ' + error.message);
        })
        .finally(() => {
            progressContainer.style.display = 'none';
        });
    }
    
    // Start uploading
    uploadChunk(0);
}
</script>
@endsection
