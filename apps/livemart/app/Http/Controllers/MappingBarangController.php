<?php

namespace App\Http\Controllers;

use App\Traits\QueueExport;
use App\Exports\MappingBarangExport;
use App\Models\User;
use App\Models\MappingBarang;
use App\Models\Platform;
use App\Models\PlatformProduct;
use App\Models\Product;
use App\Models\MappingBarangHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MappingBarangController extends Controller
{
    use QueueExport;

    /**
     * Tampilkan daftar mapping produk
     */
    public function index(Request $request)
    {
        // Ambil platform yang dipilih (opsional)
        $platform = $request->input('platform');
        $search = $request->input('search');
        $variant = $request->input('variant');
    
        // Query platform products dengan relasi - bypass semua scope
        // Hanya tampilkan produk yang memiliki active mapping
        $query = PlatformProduct::withoutGlobalScopes()
            ->whereHas('mappingBarang', function($q) {
                $q->where('is_active', true);
            })
            ->with(['platform', 'mappingBarang' => function($q) {
                $q->where('is_active', true);
            }, 'mappingBarang.product']);
    
        // Filter berdasarkan platform jika dipilih
        if ($platform) {
            $query->whereHas('platform', function ($q) use ($platform) {
                $q->where('name', $platform);
            });
        }

        // Filter berdasarkan search - lebih fleksibel dengan normalize spasi dan karakter
        if ($search) {
            // Normalize search term: replace multiple spaces, +, - dengan spasi tunggal
            $normalizedSearch = preg_replace('/[\s\+\-]+/', ' ', trim($search));
            $normalizedSearch = preg_replace('/\s+/', ' ', $normalizedSearch);
            
            $query->where(function($q) use ($search, $normalizedSearch) {
                // Exact match
                $q->where('platform_product_name', 'like', '%' . $search . '%')
                  // Normalized match
                  ->orWhere('platform_product_name', 'like', '%' . $normalizedSearch . '%')
                  // Match dengan normalize spasi di database juga
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(platform_product_name, "+", " "), "-", " "), "  ", " ") LIKE ?', ['%' . $normalizedSearch . '%'])
                  // Case insensitive
                  ->orWhereRaw('LOWER(platform_product_name) LIKE ?', ['%' . strtolower($normalizedSearch) . '%']);
            });
        }

        // Filter berdasarkan variant
        if ($variant) {
            $query->where('variant', 'like', '%' . $variant . '%');
        }
    
        // Ambil semua platform untuk dropdown filter - bypass scope dan pastikan tidak ada duplikat
        $platforms = Platform::withoutGlobalScopes()
            ->whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])
            ->distinct()
            ->get();
    
        // Debug: Cek data di database - bypass scope
        $totalPlatformProducts = PlatformProduct::withoutGlobalScopes()->count();
        $totalPlatforms = Platform::withoutGlobalScopes()->count();
        
        Log::info('Database Debug', [
            'total_platform_products' => $totalPlatformProducts,
            'total_platforms' => $totalPlatforms,
            'platforms_list' => Platform::withoutGlobalScopes()->pluck('name')->toArray()
        ]);

        // Ambil data dengan pagination
        $platformProducts = $query->paginate(50);

        // Debug: Log data untuk troubleshooting
        $normalizedSearch = $search ? preg_replace('/[\s\+\-]+/', ' ', trim($search)) : null;
        Log::info('MappingBarangController Debug', [
            'platform' => $platform,
            'search' => $search,
            'normalized_search' => $normalizedSearch,
            'variant' => $variant,
            'platformProducts_count' => $platformProducts->count(),
            'platformProducts_type' => gettype($platformProducts),
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings()
        ]);
        
        // Debug: Cek apakah ada mapping untuk platform_product_id 4642
        if ($search && (stripos($search, 'BIOAQUA') !== false || stripos($search, 'Cushion') !== false)) {
            $testMapping = MappingBarang::where('platform_product_id', 4642)
                ->where('is_active', true)
                ->with('platformProduct')
                ->first();
            Log::info('Debug: Check mapping for platform_product_id 4642', [
                'mapping_exists' => $testMapping !== null,
                'mapping_id' => $testMapping ? $testMapping->id : null,
                'platform_product_name' => $testMapping && $testMapping->platformProduct ? $testMapping->platformProduct->platform_product_name : null,
                'variant' => $testMapping && $testMapping->platformProduct ? $testMapping->platformProduct->variant : null
            ]);
        }

        // Debug: Pastikan data yang dikirim ke view benar
        Log::info('View Data Debug', [
            'platformProducts_type' => gettype($platformProducts),
            'platformProducts_count' => is_countable($platformProducts) ? count($platformProducts) : 'Not countable',
            'platformProducts_class' => get_class($platformProducts),
            'platforms_type' => gettype($platforms),
            'platforms_count' => is_countable($platforms) ? count($platforms) : 'Not countable'
        ]);

        return view('master.mapping.index', [
            'platformProducts' => $platformProducts,
            'platforms' => $platforms,
            'selectedPlatform' => $platform,
            'search' => $search,
            'variant' => $variant,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $filename = 'mapping-produk-' . now()->format('Y-m-d_His') . '.xlsx';

        return $this->queueExcelExport(new MappingBarangExport($request->all()), $filename, 'Export Mapping Barang');
    }

    /**
     * Get mapping details for a platform product (AJAX)
     */
    public function getMappingDetails($platformProductId)
    {
        try {
            $platformProduct = PlatformProduct::withoutGlobalScopes()
                ->with(['platform', 'mappingBarang.product.productVariant'])
                ->findOrFail($platformProductId);

        $mappings = $platformProduct->mappingBarang()
            ->with(['product' => function($query) {
                $query->withoutGlobalScopes();
            }, 'product.productVariant'])
            ->where('is_active', true)
            ->get();

            return response()->json([
                'success' => true,
                'platformProduct' => $platformProduct,
                'mappings' => $mappings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail mapping: ' . $e->getMessage()
            ], 500);
        }
    }

        public function destroyAll($platformProductId)
    {
        // Check if user has permission to edit/delete mapping
        /** @var User $user */
        $user = auth()->user();
        if (!$user->canEdit()) {
            return redirect()->route('master.mapping.index')
                ->with('error', 'Anda tidak memiliki izin untuk menghapus data.');
        }

        try {
            // Start a database transaction
            DB::beginTransaction();
            
            // Verifikasi platform product exists
            $platformProduct = PlatformProduct::findOrFail($platformProductId);
            
            // Store the name for success message
            $productName = $platformProduct->platform_product_name;
            
            // Log the deletion attempt
            Log::info('Attempting to delete mapping and platform product', [
                'platform_product_id' => $platformProductId,
                'product_name' => $productName,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name
            ]);
            
            // Hapus semua mapping yang terkait
            $mappingsDeleted = MappingBarang::where('platform_product_id', $platformProductId)->delete();
            Log::info('Mappings deleted', ['count' => $mappingsDeleted]);
            
            // Hapus juga platform product itu sendiri
            $platformProduct->delete();
            Log::info('Platform product deleted', ['id' => $platformProductId]);
            
            // Commit the transaction
            DB::commit();
            
            return redirect()->route('master.mapping.index')
                ->with('success', 'Semua mapping untuk produk ' . $productName . ' berhasil dihapus.');
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollBack();
            
            // Log the error
            Log::error('Error deleting mapping', [
                'platform_product_id' => $platformProductId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('master.mapping.index')
                ->with('error', 'Terjadi kesalahan saat menghapus mapping: ' . $e->getMessage());
        }
    }

   /**
 * Tampilkan form untuk membuat mapping baru
 */
public function create(Request $request)
{
    // Ambil semua platform
    $platforms = Platform::all();
    
    // Ambil semua produk yang aktif untuk dropdown
    $products = Product::where('is_active', true)->get();
    
    // Ambil parameter dari request jika ada
    $platformPreselected = $request->get('platform');
    $productNamePreselected = $request->get('product_name');
    $variantPreselected = $request->get('variant');
    
    return view('master.mapping.create', [
        'platforms' => $platforms,
        'products' => $products,
        'platformPreselected' => $platformPreselected,
        'productNamePreselected' => $productNamePreselected,
        'variantPreselected' => $variantPreselected,
        'fromAutoCreate' => false // Ini bukan dari auto-create
    ]);
}

/**
 * Simpan mapping baru ke database
 */
public function store(Request $request)
{
    // Log incoming request data for debugging
    Log::info('Mapping store request data', [
        'platform_id' => $request->platform_id,
        'platform_product_name' => $request->platform_product_name,
        'variant' => $request->variant,
        'product_id' => $request->product_id,
        'quantity' => $request->quantity,
        'all_data' => $request->all(),
        'method' => $request->method(),
        'url' => $request->url(),
        'headers' => $request->headers->all()
    ]);
    
    try {
        $request->validate([
            'platform_id' => 'required|exists:platforms,id',
            'platform_product_name' => 'required|string',
            'variant' => 'nullable|string',
            'product_id' => 'required|array',
            'product_id.*' => 'required|exists:products,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|numeric|min:0.01',
        ]);
        Log::info('Validation passed successfully');
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed', [
            'errors' => $e->errors(),
            'request_data' => $request->all()
        ]);
        throw $e;
    }

    $transactionCommitted = false; // Flag untuk track apakah transaction sudah di-commit
    try {
        // Mulai transaksi database
        DB::beginTransaction();
        
        // Cek apakah platform product sudah ada
        // Handle variant null/empty string properly - trim untuk menghindari masalah spasi
        $variantValue = $request->variant ? trim($request->variant) : null;
        if ($variantValue === '') {
            $variantValue = null;
        }
        
        // Cari platform product dengan exact match dulu
        $platformProduct = PlatformProduct::where('platform_id', $request->platform_id)
            ->where('platform_product_name', $request->platform_product_name)
            ->where(function($query) use ($variantValue) {
                if ($variantValue === null) {
                    $query->where(function($q) {
                        $q->whereNull('variant')->orWhere('variant', '');
                    });
                } else {
                    $query->where('variant', $variantValue);
                }
            })
            ->first();
        
        // Jika tidak ditemukan dengan exact match, coba cari dengan trim variant (untuk handle spasi di database)
        if (!$platformProduct && $variantValue !== null) {
            $platformProduct = PlatformProduct::where('platform_id', $request->platform_id)
                ->where('platform_product_name', $request->platform_product_name)
                ->whereRaw('TRIM(COALESCE(variant, "")) = ?', [trim($variantValue)])
                ->first();
        }
        
        // Debug log dengan informasi lebih detail
        Log::info('Store mapping debug', [
            'platform_id' => $request->platform_id,
            'platform_product_name' => $request->platform_product_name,
            'variant_request' => $request->variant,
            'variant_processed' => $variantValue,
            'platform_product_found' => $platformProduct ? $platformProduct->id : 'not found',
            'platform_product_variant' => $platformProduct ? $platformProduct->variant : null,
            'has_active_mapping' => $platformProduct ? MappingBarang::hasActiveMappingForPlatformVariant($platformProduct->id) : false
        ]);
        
        // Jika platform product sudah ada, cek apakah sudah ada mapping aktif
        if ($platformProduct && MappingBarang::hasActiveMappingForPlatformVariant($platformProduct->id)) {
            $existingMappings = MappingBarang::getAllActiveMappingsForPlatformVariant($platformProduct->id);
            $hasPreviewData = session('preview_data') !== null;
            $fromAutoCreate = $request->has('from_auto_create') && $request->from_auto_create == '1';
            
            Log::info('Platform product already has active mapping', [
                'platform_product_id' => $platformProduct->id,
                'existing_mappings_count' => $existingMappings->count(),
                'has_preview_data' => $hasPreviewData,
                'from_auto_create' => $fromAutoCreate
            ]);
            
            // Jika ini dari auto-create, cek apakah perlu update atau buat versi baru
            // Prioritas: from_auto_create lebih tinggi dari preview_data
            if ($fromAutoCreate) {
                Log::info('[AUTO-MAPPING] Processing auto-create mapping with existing active mapping', [
                    'platform_product_id' => $platformProduct->id,
                    'platform_product_name' => $platformProduct->platform_product_name,
                    'variant' => $platformProduct->variant,
                    'existing_mappings_count' => $existingMappings->count(),
                    'request_product_ids' => $request->product_id,
                    'request_quantities' => $request->quantity,
                    'has_preview_data' => $hasPreviewData
                ]);
                
                // Cek apakah platform product sudah digunakan dalam penjualan
                // Cukup cek satu mapping karena semua mapping share platform_product_id yang sama
                $hasUsedMappings = $existingMappings->isNotEmpty() && $existingMappings->first()->hasBeenUsedInSales();
                
                Log::info('[AUTO-MAPPING] Check if mapping used in sales', [
                    'platform_product_id' => $platformProduct->id,
                    'has_used_mappings' => $hasUsedMappings
                ]);
                
                if ($hasUsedMappings) {
                    // Sudah digunakan dalam penjualan, buat versi baru
                    Log::info('[AUTO-MAPPING] Creating new version (mapping used in sales)', [
                        'platform_product_id' => $platformProduct->id,
                        'platform_product_name' => $platformProduct->platform_product_name,
                        'variant' => $platformProduct->variant,
                        'existing_mappings_count' => $existingMappings->count(),
                        'existing_mappings' => $existingMappings->map(function($m) {
                            return ['id' => $m->id, 'product_id' => $m->product_id, 'quantity' => $m->quantity, 'version' => $m->version];
                        })->toArray()
                    ]);
                    
                    // Dapatkan versi terbaru
                    $latestVersion = MappingBarang::where('platform_product_id', $platformProduct->id)
                        ->max('version');
                    
                    // Deactivate semua mapping aktif
                    MappingBarang::where('platform_product_id', $platformProduct->id)
                        ->where('is_active', true)
                        ->update([
                            'is_active' => false,
                            'valid_until' => now()
                        ]);
                    
                    // Copy semua mapping aktif ke versi baru (kecuali yang akan diganti)
                    $requestProductIds = $request->product_id;
                    foreach ($existingMappings as $activeMapping) {
                        // Jika product_id ini tidak ada di request baru, copy mapping lama
                        if (!in_array($activeMapping->product_id, $requestProductIds)) {
                            $newMapping = new MappingBarang([
                                'platform_product_id' => $activeMapping->platform_product_id,
                                'product_id' => $activeMapping->product_id,
                                'quantity' => $activeMapping->quantity,
                                'version' => $latestVersion + 1,
                                'is_active' => true,
                                'valid_from' => now(),
                                'change_reason' => 'Update dari auto-create mapping',
                            ]);
                            $newMapping->save();
                        }
                    }
                    
                    // Buat mapping baru untuk product_id dari request
                    $createdMappings = [];
                    foreach ($request->product_id as $index => $productId) {
                        $mapping = new MappingBarang([
                            'platform_product_id' => $platformProduct->id,
                            'product_id' => $productId,
                            'quantity' => $request->quantity[$index],
                            'version' => $latestVersion + 1,
                            'is_active' => true,
                            'valid_from' => now(),
                            'change_reason' => 'Update dari auto-create mapping',
                        ]);
                        $mapping->save();
                        $createdMappings[] = [
                            'mapping_id' => $mapping->id,
                            'product_id' => $productId,
                            'quantity' => $request->quantity[$index]
                        ];
                        
                        Log::info('[AUTO-MAPPING] New version mapping created', [
                            'mapping_id' => $mapping->id,
                            'platform_product_id' => $platformProduct->id,
                            'product_id' => $productId,
                            'quantity' => $request->quantity[$index],
                            'version' => $latestVersion + 1
                        ]);
                    }
                    
                    DB::commit();
                    $transactionCommitted = true;
                    
                    Log::info('[AUTO-MAPPING] ✅ Successfully created new version from auto-create', [
                        'platform_product_id' => $platformProduct->id,
                        'platform_product_name' => $platformProduct->platform_product_name,
                        'variant' => $platformProduct->variant,
                        'new_version' => $latestVersion + 1,
                        'created_mappings' => $createdMappings,
                        'total_mappings_created' => count($createdMappings),
                        'has_preview_data' => $hasPreviewData
                    ]);
                    
                    // Jika ada preview_data, hapus dari unmapped list dan redirect ke preview
                    if ($hasPreviewData) {
                        $unmappedProducts = session('unmapped_products', []);
                        $productName = $request->platform_product_name;
                        $variant = $request->variant;
                        $fullProductName = $variant ? $productName . ' - ' . $variant : $productName;
                        
                        $key = false;
                        foreach ($unmappedProducts as $index => $unmappedProduct) {
                            if (is_array($unmappedProduct)) {
                                $productFullName = $unmappedProduct['full_name'] ?? '';
                                if ($productFullName === $fullProductName) {
                                    $key = $index;
                                    break;
                                }
                            } else {
                                if ($unmappedProduct === $fullProductName) {
                                    $key = $index;
                                    break;
                                }
                            }
                        }
                        
                        if ($key !== false) {
                            unset($unmappedProducts[$key]);
                            session(['unmapped_products' => array_values($unmappedProducts)]);
                            Log::info('[AUTO-MAPPING] Removed from unmapped list', ['product' => $fullProductName]);
                        }
                        
                        $platformId = $request->platform_id;
                        if ($platformId == 7) {
                            return redirect()->route('sales.tiktok2.show-preview')
                                ->with('success', 'Mapping berhasil diperbarui dengan versi baru (v' . ($latestVersion + 1) . ').');
                        } elseif ($platformId == 6) {
                            return redirect()->route('sales.shopee2.show-preview')
                                ->with('success', 'Mapping berhasil diperbarui dengan versi baru (v' . ($latestVersion + 1) . ').');
                        }
                    }
                    
                    return redirect()->route('master.mapping.index')
                        ->with('success', 'Mapping berhasil diperbarui dengan versi baru (v' . ($latestVersion + 1) . ') karena mapping sebelumnya sudah digunakan dalam penjualan.');
                } else {
                    // Belum digunakan dalam penjualan, update langsung mapping yang ada
                    Log::info('[AUTO-MAPPING] Updating existing mapping (not used in sales)', [
                        'platform_product_id' => $platformProduct->id,
                        'platform_product_name' => $platformProduct->platform_product_name,
                        'variant' => $platformProduct->variant,
                        'existing_mappings' => $existingMappings->map(function($m) {
                            return ['id' => $m->id, 'product_id' => $m->product_id, 'quantity' => $m->quantity, 'version' => $m->version];
                        })->toArray()
                    ]);
                    
                    // Hapus mapping yang tidak ada di request baru
                    $requestProductIds = $request->product_id;
                    $deletedMappings = MappingBarang::where('platform_product_id', $platformProduct->id)
                        ->where('is_active', true)
                        ->whereNotIn('product_id', $requestProductIds)
                        ->get();
                    
                    if ($deletedMappings->isNotEmpty()) {
                        Log::info('[AUTO-MAPPING] Deleting mappings not in new request', [
                            'deleted_mappings' => $deletedMappings->map(function($m) {
                                return ['id' => $m->id, 'product_id' => $m->product_id, 'quantity' => $m->quantity];
                            })->toArray()
                        ]);
                    }
                    
                    MappingBarang::where('platform_product_id', $platformProduct->id)
                        ->where('is_active', true)
                        ->whereNotIn('product_id', $requestProductIds)
                        ->delete();
                    
                    // Update atau buat mapping untuk setiap product_id di request
                    $updatedMappings = [];
                    $createdMappings = [];
                    foreach ($request->product_id as $index => $productId) {
                        $mapping = MappingBarang::where('platform_product_id', $platformProduct->id)
                            ->where('product_id', $productId)
                            ->where('is_active', true)
                            ->first();
                        
                        if ($mapping) {
                            // Update existing mapping
                            $oldQuantity = $mapping->quantity;
                            $mapping->quantity = $request->quantity[$index];
                            $mapping->save();
                            
                            $updatedMappings[] = [
                                'mapping_id' => $mapping->id,
                                'product_id' => $productId,
                                'old_quantity' => $oldQuantity,
                                'new_quantity' => $request->quantity[$index]
                            ];
                            
                            Log::info('[AUTO-MAPPING] Mapping updated', [
                                'mapping_id' => $mapping->id,
                                'product_id' => $productId,
                                'old_quantity' => $oldQuantity,
                                'new_quantity' => $request->quantity[$index]
                            ]);
                        } else {
                            // Buat mapping baru (tambahan produk)
                            $mapping = new MappingBarang([
                                'platform_product_id' => $platformProduct->id,
                                'product_id' => $productId,
                                'quantity' => $request->quantity[$index],
                                'version' => $existingMappings->first()->version ?? 1,
                                'is_active' => true,
                                'valid_from' => now(),
                            ]);
                            $mapping->save();
                            
                            $createdMappings[] = [
                                'mapping_id' => $mapping->id,
                                'product_id' => $productId,
                                'quantity' => $request->quantity[$index]
                            ];
                            
                            Log::info('[AUTO-MAPPING] New mapping added', [
                                'mapping_id' => $mapping->id,
                                'product_id' => $productId,
                                'quantity' => $request->quantity[$index]
                            ]);
                        }
                    }
                    
                    Log::info('[AUTO-MAPPING] About to commit transaction', [
                        'platform_product_id' => $platformProduct->id,
                        'created_mappings_count' => count($createdMappings),
                        'updated_mappings_count' => count($updatedMappings)
                    ]);
                    
                    DB::commit();
                    $transactionCommitted = true;
                    
                    Log::info('[AUTO-MAPPING] Transaction committed successfully', [
                        'platform_product_id' => $platformProduct->id
                    ]);
                    
                    // Verifikasi data setelah commit
                    $verifyMappings = MappingBarang::where('platform_product_id', $platformProduct->id)
                        ->where('is_active', true)
                        ->get();
                    
                    Log::info('[AUTO-MAPPING] Verifying mappings after commit', [
                        'platform_product_id' => $platformProduct->id,
                        'active_mappings_count' => $verifyMappings->count(),
                        'active_mappings' => $verifyMappings->map(function($m) {
                            return ['id' => $m->id, 'product_id' => $m->product_id, 'quantity' => $m->quantity, 'version' => $m->version, 'is_active' => $m->is_active];
                        })->toArray()
                    ]);
                    
                    Log::info('[AUTO-MAPPING] ✅ Successfully updated mapping from auto-create', [
                        'platform_product_id' => $platformProduct->id,
                        'platform_product_name' => $platformProduct->platform_product_name,
                        'variant' => $platformProduct->variant,
                        'updated_mappings' => $updatedMappings,
                        'created_mappings' => $createdMappings,
                        'deleted_mappings_count' => $deletedMappings->count(),
                        'total_updated' => count($updatedMappings),
                        'total_created' => count($createdMappings),
                        'has_preview_data' => $hasPreviewData,
                        'verified_active_mappings_count' => $verifyMappings->count()
                    ]);
                    
                    // Jika ada preview_data, hapus dari unmapped list dan redirect ke preview
                    if ($hasPreviewData) {
                        $unmappedProducts = session('unmapped_products', []);
                        $productName = $request->platform_product_name;
                        $variant = $request->variant;
                        $fullProductName = $variant ? $productName . ' - ' . $variant : $productName;
                        
                        $key = false;
                        foreach ($unmappedProducts as $index => $unmappedProduct) {
                            if (is_array($unmappedProduct)) {
                                $productFullName = $unmappedProduct['full_name'] ?? '';
                                if ($productFullName === $fullProductName) {
                                    $key = $index;
                                    break;
                                }
                            } else {
                                if ($unmappedProduct === $fullProductName) {
                                    $key = $index;
                                    break;
                                }
                            }
                        }
                        
                        if ($key !== false) {
                            unset($unmappedProducts[$key]);
                            session(['unmapped_products' => array_values($unmappedProducts)]);
                            Log::info('[AUTO-MAPPING] Removed from unmapped list', ['product' => $fullProductName]);
                        }
                        
                        $platformId = $request->platform_id;
                        if ($platformId == 7) {
                            return redirect()->route('sales.tiktok2.show-preview')
                                ->with('success', 'Mapping berhasil diperbarui.');
                        } elseif ($platformId == 6) {
                            return redirect()->route('sales.shopee2.show-preview')
                                ->with('success', 'Mapping berhasil diperbarui.');
                        }
                    }
                    
                    return redirect()->route('master.mapping.index')
                        ->with('success', 'Mapping berhasil diperbarui.');
                }
            }
            
            // Jika ini dari sales import (ada preview_data), redirect ke preview dengan info
            if ($hasPreviewData) {
                $existingMapping = $existingMappings->first();
                DB::rollBack();
                // Hapus produk dari unmapped list karena sudah ada mapping
                $unmappedProducts = session('unmapped_products', []);
                $productName = $request->platform_product_name;
                $variant = $request->variant;
                $fullProductName = $variant ? $productName . ' - ' . $variant : $productName;
                
                // Cari dan hapus dari unmapped list
                $key = false;
                foreach ($unmappedProducts as $index => $unmappedProduct) {
                    if (is_array($unmappedProduct)) {
                        $productFullName = $unmappedProduct['full_name'] ?? '';
                        if ($productFullName === $fullProductName) {
                            $key = $index;
                            break;
                        }
                    } else {
                        if ($unmappedProduct === $fullProductName) {
                            $key = $index;
                            break;
                        }
                    }
                }
                
                if ($key !== false) {
                    unset($unmappedProducts[$key]);
                    session(['unmapped_products' => array_values($unmappedProducts)]);
                }
                
                // Redirect ke preview dengan info bahwa mapping sudah ada
                $platform = Platform::findOrFail($request->platform_id);
                $platformName = strtolower($platform->name);
                $normalizedPlatformName = str_replace(' ', '', $platformName);
                $platformId = $request->platform_id;
                
                // Handle redirect berdasarkan platform ID (prioritas tertinggi)
                if ($platformId == 6) {
                    // Shopee Liefmarket (sebelumnya Shopee2)
                    return redirect()->route('sales.shopee2.show-preview')
                        ->with('info', 'Produk ini sudah memiliki mapping aktif (v' . $existingMapping->version . '). Mapping sudah dihapus dari daftar produk yang perlu dimapping.');
                } elseif ($platformId == 7) {
                    // Tiktok Liefmarket (sebelumnya Tiktok2)
                    return redirect()->route('sales.tiktok2.show-preview')
                        ->with('info', 'Produk ini sudah memiliki mapping aktif (v' . $existingMapping->version . '). Mapping sudah dihapus dari daftar produk yang perlu dimapping.');
                } elseif ($platformId == 3 || (stripos($normalizedPlatformName, 'tiktok') !== false && stripos($normalizedPlatformName, 'troublue') === false && stripos($normalizedPlatformName, 'tiktok2') === false && stripos($normalizedPlatformName, 'tiktok liefmarket') === false)) {
                    // Tiktok Lamourad (sebelumnya Tiktok)
                    return redirect()->route('sales.tiktok.show-preview')
                        ->with('info', 'Produk ini sudah memiliki mapping aktif (v' . $existingMapping->version . '). Mapping sudah dihapus dari daftar produk yang perlu dimapping.');
                } elseif (stripos($normalizedPlatformName, 'shopee') !== false && stripos($normalizedPlatformName, 'troublue') === false && stripos($normalizedPlatformName, 'shopee2') === false && stripos($normalizedPlatformName, 'shopee liefmarket') === false) {
                    // Shopee Lamourad (sebelumnya Shopee)
                    return redirect()->route('sales.shopee.show-preview')
                        ->with('info', 'Produk ini sudah memiliki mapping aktif (v' . $existingMapping->version . '). Mapping sudah dihapus dari daftar produk yang perlu dimapping.');
                }
                
                // Fallback: redirect ke mapping index jika platform tidak dikenali
                Log::warning('Unknown platform for redirect', [
                    'platform_id' => $platformId,
                    'platform_name' => $platformName,
                    'normalized_name' => $normalizedPlatformName
                ]);
                return redirect()->route('master.mapping.index')
                    ->with('info', 'Produk ini sudah memiliki mapping aktif (v' . $existingMapping->version . ').');
            }
            
            // Jika bukan dari sales import dan bukan dari auto-create, redirect ke edit mapping yang sudah ada
            $existingMapping = $existingMappings->first();
            DB::rollBack();
            return redirect()->route('master.mapping.edit', $existingMapping->id)
                ->with('info', 'Platform+variant ini sudah memiliki mapping aktif (v' . $existingMapping->version . ').');
        }
        
        // Jika platform product sudah ada tapi belum ada mapping aktif, lanjutkan untuk membuat mapping baru
        // (TIDAK redirect dengan error, tapi gunakan platform product yang sudah ada)
        if (!$platformProduct) {
            // Jika belum ada, buat platform product baru
            $platformProduct = new PlatformProduct([
                'platform_id' => $request->platform_id,
                'platform_product_name' => $request->platform_product_name,
                'variant' => $variantValue, // Use processed variant value
            ]);
            $platformProduct->save();
            Log::info('Created new platform product', [
                'platform_product_id' => $platformProduct->id,
                'platform_id' => $request->platform_id,
                'platform_product_name' => $request->platform_product_name,
                'variant' => $variantValue
            ]);
        } else {
            Log::info('Using existing platform product', [
                'platform_product_id' => $platformProduct->id,
                'platform_id' => $platformProduct->platform_id,
                'platform_product_name' => $platformProduct->platform_product_name,
                'variant' => $platformProduct->variant
            ]);
        }
        
        // Validasi duplikasi product_id dalam request
        $productIds = $request->product_id;
        $duplicateIds = MappingBarang::getDuplicateProductIds($productIds);
        
        if (!empty($duplicateIds)) {
            DB::rollBack();
            $duplicateNames = Product::whereIn('id', $duplicateIds)->pluck('name')->toArray();
            return redirect()->back()
                ->with('error', 'Terdapat produk master yang duplikat dalam form: ' . implode(', ', $duplicateNames) . '. Pastikan setiap produk master hanya dipilih sekali.');
        }
        
        // Log untuk kasus membuat mapping baru (tidak ada existing active mapping)
        $fromAutoCreate = $request->has('from_auto_create') && $request->from_auto_create == '1';
        if ($fromAutoCreate) {
            Log::info('[AUTO-MAPPING] Creating new mapping (no existing active mapping)', [
                'platform_product_id' => $platformProduct->id,
                'platform_product_name' => $platformProduct->platform_product_name,
                'variant' => $platformProduct->variant,
                'request_product_ids' => $request->product_id,
                'request_quantities' => $request->quantity
            ]);
        }
        
        // Loop melalui semua product_id dan quantity
        $createdMappings = [];
        $updatedMappings = [];
        foreach ($request->product_id as $index => $productId) {
            // Cek apakah mapping sudah ada
            $mapping = MappingBarang::where('platform_product_id', $platformProduct->id)
                ->where('product_id', $productId)
                ->first();
            
            // Jika belum ada, buat baru; jika sudah ada, update
            if (!$mapping) {
                $mapping = new MappingBarang([
                    'platform_product_id' => $platformProduct->id,
                    'product_id' => $productId,
                    'quantity' => $request->quantity[$index],
                ]);
                $mapping->save();
                
                $createdMappings[] = [
                    'mapping_id' => $mapping->id,
                    'product_id' => $productId,
                    'quantity' => $request->quantity[$index]
                ];
                
                Log::info($fromAutoCreate ? '[AUTO-MAPPING] New mapping created' : 'Mapping created', [
                    'mapping_id' => $mapping->id,
                    'platform_product_id' => $platformProduct->id,
                    'product_id' => $productId,
                    'quantity' => $request->quantity[$index]
                ]);
            } else {
                $oldQuantity = $mapping->quantity;
                $mapping->quantity = $request->quantity[$index];
                $mapping->save();
                
                $updatedMappings[] = [
                    'mapping_id' => $mapping->id,
                    'product_id' => $productId,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $request->quantity[$index]
                ];
                
                Log::info($fromAutoCreate ? '[AUTO-MAPPING] Mapping updated' : 'Mapping updated', [
                    'mapping_id' => $mapping->id,
                    'platform_product_id' => $platformProduct->id,
                    'product_id' => $productId,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $request->quantity[$index]
                ]);
            }
        }
        
        // Commit transaksi
        DB::commit();
        $transactionCommitted = true;
        
        // Check if this is from sales import process (has preview_data in session)
        $hasPreviewData = session('preview_data') !== null;
        
        if ($fromAutoCreate) {
            Log::info('[AUTO-MAPPING] ✅ Successfully created new mapping from auto-create', [
                'platform_product_id' => $platformProduct->id,
                'platform_product_name' => $platformProduct->platform_product_name,
                'variant' => $platformProduct->variant,
                'created_mappings' => $createdMappings,
                'updated_mappings' => $updatedMappings,
                'total_created' => count($createdMappings),
                'total_updated' => count($updatedMappings),
                'has_preview_data' => $hasPreviewData
            ]);
        } else {
            Log::info('Mapping store completed successfully', [
                'platform_product_id' => $platformProduct->id,
                'platform_product_name' => $platformProduct->platform_product_name,
                'variant' => $platformProduct->variant,
                'mappings_created' => count($createdMappings),
                'mappings_updated' => count($updatedMappings)
            ]);
        }
        
        Log::info($fromAutoCreate ? '[AUTO-MAPPING] Checking preview data for redirect' : 'Mapping store - checking preview data', [
            'hasPreviewData' => $hasPreviewData,
            'preview_data_exists' => session('preview_data') !== null,
            'platform_id' => $request->platform_id,
            'from_auto_create' => $fromAutoCreate,
            'request_has_from_auto_create' => $request->has('from_auto_create')
        ]);
        
        // Hanya redirect ke preview jika request benar-benar berasal dari auto-create (tombol Mapping di preview)
        // Jika dari sidebar (tambah manual), harus kembali ke index meskipun ada preview data di session
        if ($hasPreviewData && $request->has('from_auto_create')) {
            // Skenario 1: Dari sales import - redirect back to preview
            $platform = Platform::findOrFail($request->platform_id);
            $platformName = strtolower($platform->name);
            
            // Update unmapped products in session
            $unmappedProducts = session('unmapped_products', []);
            $productName = $request->platform_product_name;
            $variant = $request->variant;
            
            // Create full product name with variant if exists
            $fullProductName = $variant ? $productName . ' - ' . $variant : $productName;
            
            Log::info($fromAutoCreate ? '[AUTO-MAPPING] Removing from unmapped products' : 'Removing from unmapped products', [
                'product_name' => $productName,
                'variant' => $variant,
                'full_product_name' => $fullProductName,
                'unmapped_products_before' => $unmappedProducts,
                'from_auto_create' => $fromAutoCreate
            ]);
            
            // Try to find the product in unmapped list
            $key = false;
            foreach ($unmappedProducts as $index => $unmappedProduct) {
                if (is_array($unmappedProduct)) {
                    $productFullName = $unmappedProduct['full_name'] ?? '';
                    if ($productFullName === $fullProductName) {
                        $key = $index;
                        break;
                    }
                } else {
                    if ($unmappedProduct === $fullProductName) {
                        $key = $index;
                        break;
                    }
                }
            }
            
            // If not found directly, try looking for URL-encoded versions
            if ($key === false) {
                foreach ($unmappedProducts as $index => $unmappedProduct) {
                    // Handle both string and array formats
                    if (is_array($unmappedProduct)) {
                        $productFullName = $unmappedProduct['full_name'] ?? '';
                        if ($productFullName === $fullProductName) {
                            $key = $index;
                            break;
                        }
                    } else {
                        // Handle string format
                        if (rawurldecode($unmappedProduct) === $fullProductName) {
                            $key = $index;
                            break;
                        }
                    }
                }
            }
            
            // If still not found, try without variant
            if ($key === false) {
                foreach ($unmappedProducts as $index => $unmappedProduct) {
                    if (is_array($unmappedProduct)) {
                        $productNameFromArray = $unmappedProduct['name'] ?? '';
                        if ($productNameFromArray === $productName) {
                            $key = $index;
                            break;
                        }
                    } else {
                        if ($unmappedProduct === $productName) {
                            $key = $index;
                            break;
                        }
                    }
                }
            }
            
            if ($key !== false) {
                unset($unmappedProducts[$key]);
                session(['unmapped_products' => array_values($unmappedProducts)]);
                
                Log::info($fromAutoCreate ? '[AUTO-MAPPING] Product removed from unmapped list' : 'Product removed from unmapped list', [
                    'removed_product' => $fullProductName,
                    'unmapped_products_after' => array_values($unmappedProducts),
                    'from_auto_create' => $fromAutoCreate
                ]);
            } else {
                Log::warning($fromAutoCreate ? '[AUTO-MAPPING] Product not found in unmapped list' : 'Product not found in unmapped list', [
                    'searched_product' => $fullProductName,
                    'unmapped_products' => $unmappedProducts,
                    'from_auto_create' => $fromAutoCreate
                ]);
            }
            
            // Handle different route names for each platform
            // Normalize platform name: remove spaces, convert to lowercase
            $normalizedPlatformName = str_replace(' ', '', strtolower($platformName));
            
            // Check platform ID for shopee2 and tiktok2
            $platformId = $request->platform_id;
            
            Log::info($fromAutoCreate ? '[AUTO-MAPPING] Redirecting to preview' : 'Mapping store - redirecting to preview', [
                'platformName' => $platformName,
                'normalizedPlatformName' => $normalizedPlatformName,
                'platformId' => $platformId,
                'from_auto_create' => $fromAutoCreate
            ]);
            
            // Handle by platform ID first (most reliable)
            if ($platformId == 6) {
                // Shopee2
                return redirect()->route('sales.shopee2.show-preview')
                    ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
            } elseif ($platformId == 7) {
                // Tiktok2
                return redirect()->route('sales.tiktok2.show-preview')
                    ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
            }
            
            // Handle by platform name (fallback)
            switch ($normalizedPlatformName) {
                case 'shopee':
                case 'shopee2':
                case 'shopeelamourad':
                case 'shopeetroublue':
                case 'shopeeliefmarket':
                    // Check if it's shopee2/shopee liefmarket by ID or name
                    if ($platformId == 6 || stripos($platformName, 'troublue') !== false || stripos($platformName, 'shopee2') !== false || stripos($platformName, 'shopee liefmarket') !== false) {
                        return redirect()->route('sales.shopee2.show-preview')
                            ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
                    }
                    return redirect()->route('sales.shopee.show-preview')
                        ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
                case 'tiktok':
                case 'tiktok2':
                case 'tiktoklamourad':
                case 'tiktoktroublue':
                case 'tiktokliefmarket':
                    // Check if it's tiktok2/tiktok liefmarket by ID or name
                    if ($platformId == 7 || stripos($platformName, 'troublue') !== false || stripos($platformName, 'tiktok2') !== false || stripos($platformName, 'tiktok liefmarket') !== false) {
                        return redirect()->route('sales.tiktok2.show-preview')
                            ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
                    }
                    return redirect()->route('sales.tiktok.show-preview')
                        ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
                default:
                    // Try to construct route name from normalized platform name
                    $routeName = 'sales.' . $normalizedPlatformName . '.show-preview';
                    Log::warning('Using default route for platform', [
                        'platformName' => $platformName,
                        'normalizedPlatformName' => $normalizedPlatformName,
                        'routeName' => $routeName,
                        'platformId' => $platformId
                    ]);
                    try {
                        return redirect()->route($routeName)
                            ->with('success', 'Mapping produk berhasil disimpan. Silakan lanjutkan proses import.');
                    } catch (\Exception $e) {
                        Log::error('Route not found, redirecting to mapping index', [
                            'routeName' => $routeName,
                            'error' => $e->getMessage()
                        ]);
                        return redirect()->route('master.mapping.index')
                            ->with('success', 'Mapping produk berhasil disimpan.');
                    }
            }
        }
        
        Log::info('Mapping store - redirecting to mapping index (no preview data)');
        
        return redirect()->route('master.mapping.index')
            ->with('success', 'Mapping produk berhasil disimpan.');
    } catch (\Exception $e) {
        // Rollback transaksi jika terjadi error DAN belum di-commit
        if (!$transactionCommitted) {
            DB::rollBack();
            Log::info('Transaction rolled back (not committed yet)');
        } else {
            Log::warning('Exception occurred after transaction was committed - data should be saved', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Log the error
        Log::error('Error storing mapping', [
            'platform_id' => $request->platform_id,
            'platform_product_name' => $request->platform_product_name,
            'variant' => $request->variant,
            'product_ids' => $request->product_id ?? [],
            'quantities' => $request->quantity ?? [],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'transaction_committed' => $transactionCommitted
        ]);
        
        // Jika transaction sudah di-commit, redirect dengan success message
        if ($transactionCommitted) {
            return redirect()->route('master.mapping.index')
                ->with('success', 'Mapping berhasil disimpan, namun terjadi error saat redirect: '.$e->getMessage());
        }
        
        return redirect()->back()
            ->with('error', 'Terjadi kesalahan saat menyimpan mapping: '.$e->getMessage())
            ->withInput();
    }
}

/**
 * Hapus mapping (untuk route destroy)
 */
public function destroy($id)
{
    // Debug logging
    Log::info('MappingBarangController@destroy called', [
        'mapping_id' => $id,
        'user_id' => auth()->id(),
        'timestamp' => now()
    ]);

    // Check if user has permission to edit/delete mapping
    /** @var User $user */
    $user = auth()->user();
    if (!$user->canEdit()) {
        Log::warning('User does not have edit permission', [
            'user_id' => auth()->id(),
            'mapping_id' => $id
        ]);
        return redirect()->route('master.mapping.index')
            ->with('error', 'Anda tidak memiliki izin untuk menghapus data.');
    }

    try {
        DB::beginTransaction();
        
        $mapping = MappingBarang::findOrFail($id);
        $platformProductId = $mapping->platform_product_id;
        $productId = $mapping->product_id;
        $quantity = $mapping->quantity;
        $version = $mapping->version;
        
        Log::info('Mapping found for deletion', [
            'mapping_id' => $id,
            'platform_product_id' => $platformProductId,
            'product_id' => $productId,
            'version' => $version,
            'is_active' => $mapping->is_active
        ]);
        
        // Cek apakah mapping sudah pernah digunakan dalam penjualan
        $hasBeenUsed = $mapping->hasBeenUsedInSales();
        
        Log::info('Sales usage check result', [
            'mapping_id' => $id,
            'has_been_used' => $hasBeenUsed
        ]);
        
        if ($hasBeenUsed) {
            // Kondisi 2: Sudah digunakan dalam penjualan, buat versi baru tanpa mapping yang dihapus
            Log::info('Creating new version after deletion (used in sales)', [
                'mapping_id' => $id,
                'version' => $version
            ]);
            
            // Dapatkan versi terbaru
            $latestVersion = MappingBarang::where('platform_product_id', $platformProductId)
                ->max('version');
            
            // Ambil mapping yang masih aktif SEBELUM deactivate (kecuali yang akan dihapus)
            $activeMappings = MappingBarang::where('platform_product_id', $platformProductId)
                ->where('is_active', true)
                ->where('id', '!=', $id) // Exclude the mapping yang akan dihapus
                ->with('product')
                ->get();
            
            // Deactivate semua mapping aktif untuk platform product ini
            MappingBarang::where('platform_product_id', $platformProductId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'valid_until' => now()
                ]);
            
            foreach ($activeMappings as $activeMapping) {
                // Buat mapping baru untuk setiap mapping yang masih aktif
                $newMapping = new MappingBarang([
                    'platform_product_id' => $activeMapping->platform_product_id,
                    'product_id' => $activeMapping->product_id,
                    'quantity' => $activeMapping->quantity,
                    'version' => $latestVersion + 1,
                    'is_active' => true,
                    'valid_from' => now(),
                    'change_reason' => 'Hapus mapping ' . $activeMapping->product->name ?? 'Unknown',
                ]);
                $newMapping->save();
            }
            
            Log::info('New version created after deletion', [
                'new_version' => $latestVersion + 1,
                'remaining_mappings' => $activeMappings->count()
            ]);
            
            // Log to history
            MappingBarangHistory::create([
                'platform_product_id' => $platformProductId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'action' => 'delete',
                'user_id' => auth()->id(),
                'keterangan' => 'Hapus mapping v' . $version . ' - dibuat versi baru v' . ($latestVersion + 1),
            ]);
            
            DB::commit();
            Log::info('Transaction committed for version creation', ['mapping_id' => $id]);
            
            // Redirect ke mapping aktif yang baru
            $newActiveMapping = MappingBarang::where('platform_product_id', $platformProductId)
                ->where('is_active', true)
                ->first();
            
            if ($newActiveMapping) {
                return redirect()->route('master.mapping.edit', $newActiveMapping->id)
                    ->with('success', 'Mapping v' . $version . ' berhasil dihapus. Dibuat versi baru v' . ($latestVersion + 1) . '.');
            } else {
                return redirect()->route('master.mapping.index')
                    ->with('success', 'Mapping v' . $version . ' berhasil dihapus. Dibuat versi baru v' . ($latestVersion + 1) . '.');
            }
        } else {
            // Kondisi 1: Belum digunakan dalam penjualan, hapus langsung
            Log::info('Deleting mapping (not used in sales)', [
                'mapping_id' => $id,
                'version' => $version
            ]);
            
            $mapping->delete();
            
            Log::info('Mapping deleted successfully', ['mapping_id' => $id]);

            // Log to history
            MappingBarangHistory::create([
                'platform_product_id' => $platformProductId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'action' => 'delete',
                'user_id' => auth()->id(),
                'keterangan' => 'Hapus mapping v' . $version,
            ]);
            
            DB::commit();
            Log::info('Transaction committed for deletion', ['mapping_id' => $id]);
            
            // Cek apakah masih ada mapping aktif untuk platform product ini
            $remainingActiveMapping = MappingBarang::where('platform_product_id', $platformProductId)
                ->where('is_active', true)
                ->first();
            
            if ($remainingActiveMapping) {
                // Masih ada mapping aktif, redirect ke mapping aktif
                return redirect()->route('master.mapping.edit', $remainingActiveMapping->id)
                    ->with('success', 'Mapping v' . $version . ' berhasil dihapus.');
            } else {
                // Tidak ada mapping aktif lagi, redirect ke index
                return redirect()->route('master.mapping.index')
                    ->with('success', 'Mapping v' . $version . ' berhasil dihapus.');
            }
        }
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error in MappingBarangController@destroy', [
            'mapping_id' => $id,
            'user_id' => auth()->id(),
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString()
        ]);
        
        return redirect()->back()
            ->with('error', 'Terjadi kesalahan saat menghapus mapping: '.$e->getMessage());
    }
}

    public function edit($id)
    {
        // Ambil mapping berdasarkan ID dengan relasi
        $mapping = MappingBarang::with(['platformProduct.platform', 'product'])->findOrFail($id);

        // Cek apakah mapping sudah pernah digunakan dalam penjualan
        $hasBeenUsed = $mapping->hasBeenUsedInSales();

        // Ambil mapping aktif terbaru untuk platform product ini
        $latestMapping = MappingBarang::where('platform_product_id', $mapping->platform_product_id)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();

        // Jika ada mapping aktif terbaru yang berbeda, gunakan yang terbaru
        if ($latestMapping && $latestMapping->id != $mapping->id) {
            $mapping = $latestMapping;
        }

        // Ambil semua produk untuk dropdown
        $products = Product::where('is_active', true)->get();

        return view('master.mapping.edit', [
            'mapping' => $mapping,
            'products' => $products,
            'hasBeenUsed' => $hasBeenUsed,
        ]);
    }

    /**
     * Edit platform product yang belum termapping
     */
    public function editProduct($platformProductId)
    {
        // Ambil platform product berdasarkan ID
        $platformProduct = PlatformProduct::with(['platform'])->findOrFail($platformProductId);

        // Ambil semua produk untuk dropdown
        $products = Product::where('is_active', true)->get();

        // Ambil mapping yang sudah ada (jika ada)
        $existingMappings = MappingBarang::where('platform_product_id', $platformProductId)
            ->where('is_active', true)
            ->with(['product'])
            ->get();

        return view('master.mapping.edit-product', [
            'platformProduct' => $platformProduct,
            'products' => $products,
            'existingMappings' => $existingMappings,
        ]);
    }

    /**
 * Update mapping yang sudah ada
 */
public function update(Request $request, $id)
{
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity' => 'required|numeric|min:0.01',
        'change_reason' => 'nullable|string|max:500',
    ]);

    try {
        DB::beginTransaction();
        
        $mapping = MappingBarang::findOrFail($id);
        $oldProductId = $mapping->product_id;
        $oldQuantity = $mapping->quantity;
        
        // Cek apakah mapping sudah pernah digunakan dalam penjualan
        $hasBeenUsed = $mapping->hasBeenUsedInSales();
        
        // Cek apakah ada perubahan data
        $hasDataChange = ($mapping->product_id != $request->product_id) || 
                        ($mapping->quantity != $request->quantity);
        
        if ($hasBeenUsed && $hasDataChange) {
            // Kondisi 2: Sudah digunakan dalam penjualan, buat versi baru
            $latestVersion = MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                ->max('version');
            
            // Ambil semua mapping aktif untuk platform product ini
            $activeMappings = MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                ->where('is_active', true)
                ->with('product')
                ->get();
            
            // Deactivate semua mapping aktif untuk platform product ini
            MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'valid_until' => now()
                ]);
            
            // Buat versi baru dengan semua mapping aktif
            foreach ($activeMappings as $activeMapping) {
                // Skip mapping yang sedang diedit jika ada perubahan product_id
                if ($activeMapping->id == $mapping->id && $mapping->product_id != $request->product_id) {
                    continue; // Skip karena akan dibuat mapping baru di bawah
                }
                
                $newMapping = new MappingBarang([
                    'platform_product_id' => $activeMapping->platform_product_id,
                    'product_id' => $activeMapping->product_id,
                    'quantity' => $activeMapping->id == $mapping->id ? $request->quantity : $activeMapping->quantity,
                    'version' => $latestVersion + 1,
                    'is_active' => true,
                    'valid_from' => now(),
                    'change_reason' => $activeMapping->id == $mapping->id ? $request->change_reason : 'Copy dari versi sebelumnya',
                ]);
                
                $newMapping->save();
            }
            
            // Jika ada perubahan product_id, buat mapping baru untuk product_id yang baru
            if ($mapping->product_id != $request->product_id) {
                $newMapping = new MappingBarang([
                    'platform_product_id' => $mapping->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'version' => $latestVersion + 1,
                    'is_active' => true,
                    'valid_from' => now(),
                    'change_reason' => $request->change_reason ?? 'Ubah produk master',
                ]);
                
                $newMapping->save();
            }
            
            // Log to history
            MappingBarangHistory::create([
                'platform_product_id' => $mapping->platform_product_id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'action' => 'version_create',
                'user_id' => auth()->id(),
                'keterangan' => 'Buat versi baru mapping (dari v' . $mapping->version . ' ke v' . $newMapping->version . ') - ' . ($request->change_reason ?? 'Tidak ada alasan'),
            ]);
            
            DB::commit();
            $message = 'Mapping berhasil diperbarui dengan versi baru (v' . ($latestVersion + 1) . '). Mapping lama menjadi history.';
            
            // Cari mapping yang baru dibuat untuk redirect
            $newActiveMapping = MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                ->where('is_active', true)
                ->where('product_id', $request->product_id)
                ->first();
            
            $redirectId = $newActiveMapping ? $newActiveMapping->id : $mapping->id;
        } else {
            // Cek apakah product_id yang baru sudah ada di mapping aktif lainnya (kecuali mapping yang sedang diedit)
            if (MappingBarang::isProductAlreadyMapped($mapping->platform_product_id, $request->product_id, $mapping->id)) {
                DB::rollBack();
                $product = Product::find($request->product_id);
                $productName = $product ? $product->name : 'Unknown';
                return redirect()->back()
                    ->with('error', "Produk master '{$productName}' sudah ada dalam mapping aktif lainnya. Silakan pilih produk master lain.");
            }
            
            // Kondisi 1: Belum digunakan dalam penjualan, edit langsung
            $mapping->update([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);

            // Log to history
            MappingBarangHistory::create([
                'platform_product_id' => $mapping->platform_product_id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'action' => 'update',
                'user_id' => auth()->id(),
                'keterangan' => 'Edit mapping (dari product_id: ' . $oldProductId . ', qty: ' . $oldQuantity . ')',
            ]);
            
            DB::commit();
            $message = 'Mapping berhasil diperbarui.';
            $redirectId = $mapping->id;
        }

        // Redirect ke halaman edit dengan anchor untuk highlight
        return redirect()->route('master.mapping.edit', $redirectId)
            ->with('success', $message);
        } catch (\Exception $e) {
        DB::rollBack();
        
        // Log error untuk debugging
        Log::error('Error updating mapping', [
            'mapping_id' => $id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return redirect()->back()
            ->with('error', 'Terjadi kesalahan saat memperbarui mapping: '.$e->getMessage());
    }
}

/**
 * Tambahkan produk master baru ke mapping yang sudah ada
 */
    public function addProduct(Request $request)
    {
        $request->validate([
            'platform_product_id' => 'required|exists:platform_products,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();
            
            // Cek apakah produk master yang sama sudah ada di mapping AKTIF saja
            $existingActiveMapping = MappingBarang::where('platform_product_id', $request->platform_product_id)
                ->where('product_id', $request->product_id)
                ->where('is_active', true)
                ->first();
                
            if ($existingActiveMapping) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Produk master ini sudah ada dalam mapping aktif. Silakan edit mapping yang sudah ada.');
            }
            
            // Cek apakah ada mapping aktif untuk platform product ini
            $activeMappings = MappingBarang::where('platform_product_id', $request->platform_product_id)
                ->where('is_active', true)
                ->get();
            
            // Jika belum ada mapping aktif, buat mapping baru dengan versi 1
            if ($activeMappings->isEmpty()) {
                $mapping = new MappingBarang([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'version' => 1,
                    'is_active' => true,
                    'valid_from' => now(),
                ]);
                
                $mapping->save();
                
                // Log to history
                MappingBarangHistory::create([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'action' => 'add',
                    'user_id' => auth()->id(),
                    'keterangan' => 'Tambah mapping baru (v1)',
                ]);
                
                DB::commit();
                return redirect()->back()
                    ->with('success', 'Produk master berhasil ditambahkan ke mapping.');
            }
            
            // Jika sudah ada mapping aktif, cek apakah ada yang sudah digunakan dalam penjualan
            $hasUsedMappings = false;
            foreach ($activeMappings as $activeMapping) {
                if ($activeMapping->hasBeenUsedInSales()) {
                    $hasUsedMappings = true;
                    break;
                }
            }
            
            if ($hasUsedMappings) {
                // Kondisi 2: Ada penjualan, buat versi baru (V2)
                $latestVersion = MappingBarang::where('platform_product_id', $request->platform_product_id)
                    ->max('version');
                
                // Ambil semua mapping aktif sebelum deactivate
                $activeMappingsToCopy = MappingBarang::where('platform_product_id', $request->platform_product_id)
                    ->where('is_active', true)
                    ->with('product')
                    ->get();
                
                // Deactivate semua mapping aktif
                MappingBarang::where('platform_product_id', $request->platform_product_id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'valid_until' => now()
                    ]);
                
                // Copy semua mapping aktif ke versi baru
                foreach ($activeMappingsToCopy as $activeMapping) {
                    $newMapping = new MappingBarang([
                        'platform_product_id' => $activeMapping->platform_product_id,
                        'product_id' => $activeMapping->product_id,
                        'quantity' => $activeMapping->quantity,
                        'version' => $latestVersion + 1,
                        'is_active' => true,
                        'valid_from' => now(),
                        'change_reason' => 'Copy dari versi sebelumnya',
                    ]);
                    
                    $newMapping->save();
                }
                
                // Buat mapping baru untuk produk yang ditambahkan
                $mapping = new MappingBarang([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'version' => $latestVersion + 1,
                    'is_active' => true,
                    'valid_from' => now(),
                    'change_reason' => 'Tambah produk master baru',
                ]);
                
                $mapping->save();
                
                // Log to history
                MappingBarangHistory::create([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'action' => 'add',
                    'user_id' => auth()->id(),
                    'keterangan' => 'Tambah mapping baru (v' . ($latestVersion + 1) . ') - mapping lama menjadi history',
                ]);
                
                DB::commit();
                return redirect()->back()
                    ->with('success', 'Produk master berhasil ditambahkan ke mapping versi ' . ($latestVersion + 1) . '. Mapping lama menjadi history.');
            } else {
                // Cek apakah product_id sudah ada di mapping aktif
                if (MappingBarang::isProductAlreadyMapped($request->platform_product_id, $request->product_id)) {
                    DB::rollBack();
                    $product = Product::find($request->product_id);
                    $productName = $product ? $product->name : 'Unknown';
                    return redirect()->back()
                        ->with('error', "Produk master '{$productName}' sudah ada dalam mapping aktif. Silakan edit mapping yang sudah ada atau pilih produk master lain.");
                }
                
                // Kondisi 1: Belum ada penjualan, tambah ke mapping aktif yang sama
                $mapping = new MappingBarang([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'version' => $activeMappings->first()->version, // Gunakan versi yang sama
                    'is_active' => true,
                    'valid_from' => now(),
                ]);
                
                $mapping->save();
                
                // Log to history
                MappingBarangHistory::create([
                    'platform_product_id' => $request->platform_product_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'action' => 'add',
                    'user_id' => auth()->id(),
                    'keterangan' => 'Tambah mapping ke versi ' . $activeMappings->first()->version,
                ]);
                
                DB::commit();
                return redirect()->back()
                    ->with('success', 'Produk master berhasil ditambahkan ke mapping.');
            }

            // Ambil mapping lain untuk mendapatkan ID yang valid untuk redirect
            $anyMapping = MappingBarang::where('platform_product_id', $request->platform_product_id)
            ->first();
        
        return redirect()->route('master.mapping.edit', $anyMapping->id)
            ->with('success', 'Produk master berhasil ditambahkan ke mapping.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', 'Terjadi kesalahan saat menambahkan produk: '.$e->getMessage());
    }
}

    public function show($id)
    {
        $mapping = MappingBarang::with(['platformProduct.platform', 'product'])->findOrFail($id);

        $products = Product::where('is_active', true)->get();

        return view('master.mapping.show', [
            'mapping' => $mapping,
            'products' => $products,
        ]);
    }

    /**
     * Cek produk yang belum dimapping
     */
    public function checkUnmappedProducts(Request $request, $platform)
    {
        // Ambil data produk yang belum dimapping dari session
        $unmappedProducts = session('unmapped_products', []);

        // Jika tidak ada data dari session, redirect ke halaman daftar mapping
        if (empty($unmappedProducts)) {
            return redirect()
                ->route('master.mapping.index', ['platform' => $platform])
                ->with('info', 'Tidak ada produk yang perlu dimapping.');
        }

        return view('master.mapping.check', [
            'platform' => $platform,
            'unmappedProducts' => $unmappedProducts,
        ]);
    }

    /**
     * Auto create mapping by redirecting to create form with prefilled data
     */
    public function autoCreateMapping($platform, $productName, Request $request)
    {
        // Ambil variant dari query parameter jika ada (lebih akurat dari Excel)
        $variantFromQuery = $request->input('variant');
        
        Log::info('[AUTO-MAPPING] Mulai autoCreateMapping', [
            'platform' => $platform,
            'productName' => $productName,
            'variant_from_query' => $variantFromQuery,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        // Decode the URL-encoded product name using rawurldecode to preserve + characters
        $decodedProductName = rawurldecode($productName);
        
        Log::info('[AUTO-MAPPING] Decoded product name', [
            'encoded' => $productName,
            'decoded' => $decodedProductName
        ]);
        
        // PERBAIKAN: Jika variant sudah ada di query parameter (dari Excel), gunakan itu
        // Jangan parsing dari nama produk karena bisa salah
        if (!empty($variantFromQuery)) {
            $variant = rawurldecode($variantFromQuery);
            $productNameOnly = $decodedProductName;
            
            Log::info('[AUTO-MAPPING] Using variant from query parameter (from Excel)', [
                'productName' => $productNameOnly,
                'variant' => $variant
            ]);
        } else {
            // Fallback: Parse product name and variant from the full product name
            // Hanya jika variant tidak ada di query parameter (backward compatibility)
            $productParts = explode(' - ', $decodedProductName, 2);
            $productNameOnly = $productParts[0];
            $variant = isset($productParts[1]) ? $productParts[1] : '';
            
            Log::info('[AUTO-MAPPING] Parsing variant from product name (fallback)', [
                'productName' => $productNameOnly,
                'variant' => $variant
            ]);
            
            // Try to find the correct parsing by checking against existing platform products
            // This helps when the product name itself contains " - "
            // PERBAIKAN: Cek apakah platform adalah ID (numeric) atau nama (string)
            if (is_numeric($platform)) {
                $platform_entity = Platform::find($platform);
            } else {
                $platform_entity = Platform::where('name', $platform)->first();
            }
            if ($platform_entity) {
                // If we have multiple " - " in the string, try different splitting points
                $allParts = explode(' - ', $decodedProductName);
                if (count($allParts) > 2) {
                    // Try different combinations
                    for ($i = 1; $i < count($allParts); $i++) {
                        $altProductName = implode(' - ', array_slice($allParts, 0, $i));
                        $altVariant = implode(' - ', array_slice($allParts, $i));
                        
                        $platformProduct = \App\Models\PlatformProduct::where('platform_id', $platform_entity->id)
                            ->where('platform_product_name', $altProductName)
                            ->where('variant', $altVariant)
                            ->first();
                            
                        if ($platformProduct) {
                            $productNameOnly = $altProductName;
                            $variant = $altVariant;
                            break;
                        }
                    }
                }
            }
        }
        
        Log::info('[AUTO-MAPPING] Parsed product data', [
            'fullProductName' => $decodedProductName,
            'productNameOnly' => $productNameOnly,
            'variant' => $variant,
            'variant_source' => !empty($variantFromQuery) ? 'query_parameter' : 'parsed_from_name'
        ]);
        
        // Ambil platform dari database
        // PERBAIKAN: Cek apakah platform adalah ID (numeric) atau nama (string)
        if (is_numeric($platform)) {
            $platformEntity = Platform::find($platform);
        } else {
            $platformEntity = Platform::where('name', $platform)->first();
        }
        
        if (!$platformEntity) {
            Log::warning('[AUTO-MAPPING] Platform tidak ditemukan', ['platform' => $platform]);
            return redirect()->route('master.mapping.index')->with('error', 'Platform tidak ditemukan di database.');
        }

        // Ambil semua produk yang bisa di-mapping
        $products = Product::where('is_active', true)->get();

        Log::info('[AUTO-MAPPING] Sukses tampilkan form auto-mapping', [
            'platform' => $platform,
            'productName' => $productNameOnly,
            'variant' => $variant,
            'timestamp' => now()->toDateTimeString()
        ]);
        // Redirect ke halaman create dengan parameter yang sudah diisi
        return view('master.mapping.create', [
            'platforms' => Platform::all(),
            'products' => $products,
            'platformPreselected' => $platformEntity->id,
            'productNamePreselected' => $productNameOnly,
            'variantPreselected' => $variant,
            'fromAutoCreate' => true
        ]);
    }

    /**
     * Tampilkan halaman mapping produk
     */
    public function showMapping($platform, $productName)
    {
        // Ambil platform dari database
        // PERBAIKAN: Cek apakah platform adalah ID (numeric) atau nama (string)
        if (is_numeric($platform)) {
            $platformEntity = Platform::find($platform);
        } else {
            $platformEntity = Platform::where('name', $platform)->first();
        }
        
        if (!$platformEntity) {
            return redirect()->route('master.mapping.index')->with('error', 'Platform tidak ditemukan di database.');
        }

        // Decode the URL-encoded product name using rawurldecode to preserve + characters
        $decodedProductName = rawurldecode($productName);

        // Cek apakah produk sudah ada di database
        $platformProduct = PlatformProduct::where('platform_id', $platformEntity->id)
            ->where('platform_product_name', $decodedProductName)
            ->first();

        // Jika platform product tidak ada, redirect ke create mapping
        if (!$platformProduct) {
            return redirect()->route('master.mapping.auto-create', [
                'platform' => $platform,
                'productName' => $productName
            ]);
        }

        // Ambil mapping aktif untuk platform product ini
        $mapping = MappingBarang::where('platform_product_id', $platformProduct->id)
            ->where('is_active', true)
            ->with(['platformProduct.platform', 'product'])
            ->first();

        // Jika tidak ada mapping aktif, redirect ke create mapping (bukan auto-create)
        if (!$mapping) {
            return redirect()->route('master.mapping.create', [
                'platform' => $platformEntity->id,
                'product_name' => $decodedProductName
            ]);
        }

        // Ambil semua produk yang bisa di-mapping
        $products = Product::where('is_active', true)->get();

        return view('master.mapping.show', [
            'mapping' => $mapping,
            'products' => $products,
        ]);
    }

    /**
     * Simpan mapping produk
     */
    public function saveMapping(Request $request)
    {
        $request->validate([
            'platform' => 'required|string',
            'product_name' => 'required|string',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'variant' => 'nullable|string',
        ]);

        try {
            // Ambil platform dari database
            // PERBAIKAN: Cek apakah platform adalah ID (numeric) atau nama (string)
            if (is_numeric($request->platform)) {
                $platform = Platform::find($request->platform);
            } else {
                $platform = Platform::where('name', $request->platform)->first();
            }
            
            if (!$platform) {
                throw new \Exception('Platform tidak ditemukan di database.');
            }

            // Cek apakah platform product sudah ada
            $platformProduct = PlatformProduct::where('platform_id', $platform->id)
                ->where('platform_product_name', $request->product_name)
                ->where('variant', $request->variant ?? '')
                ->first();

            // Jika belum ada, buat baru
            if (!$platformProduct) {
                $platformProduct = new PlatformProduct([
                    'platform_id' => $platform->id,
                    'platform_product_name' => $request->product_name,
                    'variant' => $request->variant,
                ]);
                $platformProduct->save();
            }

            // Cek apakah mapping sudah ada
            $mapping = MappingBarang::where('platform_product_id', $platformProduct->id)
                ->where('product_id', $request->product_id)
                ->where('is_active', true)
                ->first();

            // Jika belum ada, buat baru; jika sudah ada, update
            if (!$mapping) {
                $mapping = new MappingBarang([
                    'platform_product_id' => $platformProduct->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'version' => 1,
                    'is_active' => true,
                    'valid_from' => now(),
                ]);
            } else {
                $mapping->quantity = $request->quantity;
            }

            $mapping->save();

            // Jika dari halaman check, update session unmapped_products
            if ($request->has('from_check') && $request->from_check) {
                $unmappedProducts = session('unmapped_products', []);
                $key = array_search($request->product_name, $unmappedProducts);
                if ($key !== false) {
                    unset($unmappedProducts[$key]);
                    session(['unmapped_products' => array_values($unmappedProducts)]);
                }

                // Jika semua produk sudah dimapping, redirect ke preview import
                if (empty($unmappedProducts)) {
                    return redirect()
                        ->route('sales.' . $request->platform . '.show-preview')
                        ->with('success', 'Semua produk berhasil dimapping. Silakan lanjutkan proses import.');
                }
            }

            return redirect()->back()->with('success', 'Mapping produk berhasil disimpan.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Terjadi kesalahan saat menyimpan mapping: ' . $e->getMessage());
        }
    }

    /**
     * Hapus mapping produk
     */
    public function deleteMapping($id)
    {
        try {
            $mapping = MappingBarang::findOrFail($id);
            $mapping->delete();

            return redirect()->back()->with('success', 'Mapping produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Terjadi kesalahan saat menghapus mapping: ' . $e->getMessage());
        }
    }

    /**
     * Riwayat versi mapping - tampilan ringkas
     */
    public function versionHistory($platformProductId)
    {
        $platformProduct = PlatformProduct::with('platform')->findOrFail($platformProductId);
        
        // Ambil ringkasan versi - hanya versi unik
        // Gunakan COALESCE(valid_from, created_at) untuk konsistensi dengan query analytic
        $versionSummary = MappingBarang::where('platform_product_id', $platformProductId)
            ->selectRaw('version, 
                       COUNT(*) as total_products,
                       MIN(COALESCE(valid_from, created_at)) as created_at,
                       MAX(CASE WHEN is_active = 1 THEN COALESCE(valid_from, created_at) END) as last_active_at')
            ->groupBy('version')
            ->orderBy('version', 'desc')
            ->get();

        // Debug: Cek data yang diambil
        Log::info('Version History Data', [
            'platform_product_id' => $platformProductId,
            'version_count' => $versionSummary->count(),
            'versions' => $versionSummary->pluck('version')->toArray()
        ]);

        return view('master.mapping.version-history', [
            'platformProduct' => $platformProduct,
            'versionSummary' => $versionSummary
        ]);
    }

    /**
     * Detail perubahan versi - saat klik versi tertentu
     */
    public function versionDetail($platformProductId, $version)
    {
        Log::info('Version Detail Method Called', [
            'platform_product_id' => $platformProductId,
            'version' => $version,
            'timestamp' => now()
        ]);
        
        $platformProduct = PlatformProduct::with('platform')->findOrFail($platformProductId);
        
        // Ambil mapping versi ini
        $currentVersionMappings = MappingBarang::where('platform_product_id', $platformProductId)
            ->where('version', $version)
            ->with('product')
            ->get();

        // Ambil mapping versi sebelumnya untuk perbandingan
        // Ambil versi yang langsung sebelumnya (version - 1) - versi sebelumnya sudah tidak aktif
        $previousVersionMappings = MappingBarang::where('platform_product_id', $platformProductId)
            ->where('version', $version - 1)
            ->where('is_active', false) // Versi sebelumnya sudah tidak aktif
            ->with('product')
            ->get();
            
        // Jika tidak ada versi sebelumnya, ambil versi terakhir yang tidak aktif
        if ($previousVersionMappings->isEmpty()) {
            // Ambil versi terakhir yang tidak aktif, tapi hanya yang unik per product_id
            $previousVersionMappings = MappingBarang::where('platform_product_id', $platformProductId)
                ->where('version', '<', $version)
                ->where('is_active', false)
                ->orderBy('version', 'desc')
                ->with('product')
                ->get()
                ->groupBy('product_id')
                ->map(function($group) {
                    return $group->first(); // Ambil yang pertama dari setiap group
                })
                ->values();
        }
        
        // Pastikan tidak ada duplikasi berdasarkan product_id dengan cara yang lebih ketat
        $uniquePreviousMappings = collect();
        $seenProductIds = [];
        
        foreach ($previousVersionMappings as $mapping) {
            if (!in_array($mapping->product_id, $seenProductIds)) {
                $uniquePreviousMappings->push($mapping);
                $seenProductIds[] = $mapping->product_id;
            }
        }
        
        $previousVersionMappings = $uniquePreviousMappings;
        
        // Debug logging untuk melihat data yang sudah di-unique
        Log::info('Unique Previous Mappings Debug', [
            'platform_product_id' => $platformProductId,
            'version' => $version,
            'unique_count' => $previousVersionMappings->count(),
            'unique_data' => $previousVersionMappings->map(function($m) {
                return ['product_id' => $m->product_id, 'quantity' => $m->quantity, 'product_name' => $m->product->name, 'version' => $m->version];
            })
        ]);

        // Analisis perubahan
        $changes = $this->analyzeVersionChanges($currentVersionMappings, $previousVersionMappings);

        // Debug logging
        Log::info('Version Detail Debug', [
            'platform_product_id' => $platformProductId,
            'version' => $version,
            'current_mappings_count' => $currentVersionMappings->count(),
            'previous_mappings_count' => $previousVersionMappings->count(),
            'changes' => $changes,
            'current_mappings' => $currentVersionMappings->map(function($m) {
                return ['product_id' => $m->product_id, 'quantity' => $m->quantity, 'product_name' => $m->product->name, 'version' => $m->version];
            }),
            'previous_mappings' => $previousVersionMappings->map(function($m) {
                return ['product_id' => $m->product_id, 'quantity' => $m->quantity, 'product_name' => $m->product->name, 'version' => $m->version];
            })
        ]);

        return view('master.mapping.version-detail', [
            'platformProduct' => $platformProduct,
            'version' => $version,
            'currentMappings' => $currentVersionMappings,
            'changes' => $changes
        ]);
    }

    /**
     * Analisis perubahan antar versi
     */
    private function analyzeVersionChanges($current, $previous)
    {
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];

        // Debug logging untuk analisis
        Log::info('Analyze Version Changes Debug', [
            'current_count' => $current->count(),
            'previous_count' => $previous->count(),
            'current_data' => $current->map(function($m) {
                return ['product_id' => $m->product_id, 'quantity' => $m->quantity, 'product_name' => $m->product->name];
            }),
            'previous_data' => $previous->map(function($m) {
                return ['product_id' => $m->product_id, 'quantity' => $m->quantity, 'product_name' => $m->product->name];
            })
        ]);

        // Buat array untuk perbandingan
        $currentMap = $current->keyBy('product_id');
        $previousMap = $previous->keyBy('product_id');

        // Cek yang ditambah
        foreach ($currentMap as $productId => $mapping) {
            if (!$previousMap->has($productId)) {
                $changes['added'][] = [
                    'product' => $mapping->product,
                    'quantity' => $mapping->quantity
                ];
            }
        }

        // Cek yang dihapus
        foreach ($previousMap as $productId => $mapping) {
            if (!$currentMap->has($productId)) {
                $changes['removed'][] = [
                    'product' => $mapping->product,
                    'quantity' => $mapping->quantity
                ];
            }
        }

        // Cek yang diubah
        foreach ($currentMap as $productId => $currentMapping) {
            if ($previousMap->has($productId)) {
                $previousMapping = $previousMap[$productId];
                Log::info('Comparing mapping', [
                    'product_id' => $productId,
                    'current_quantity' => $currentMapping->quantity,
                    'previous_quantity' => $previousMapping->quantity,
                    'is_different' => $currentMapping->quantity != $previousMapping->quantity
                ]);
                
                if ($currentMapping->quantity != $previousMapping->quantity) {
                    $changes['modified'][] = [
                        'product' => $currentMapping->product,
                        'old_quantity' => $previousMapping->quantity,
                        'new_quantity' => $currentMapping->quantity
                    ];
                }
            }
        }

        Log::info('Final changes result', [
            'added_count' => count($changes['added']),
            'removed_count' => count($changes['removed']),
            'modified_count' => count($changes['modified']),
            'changes' => $changes
        ]);

        return $changes;
    }
}
