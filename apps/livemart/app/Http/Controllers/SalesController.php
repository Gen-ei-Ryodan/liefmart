<?php

namespace App\Http\Controllers;

use App\Models\BarangKeluar;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\PlatformProduct;
use App\Models\MappingBarang;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use Shared\Models\Customer;
use App\Models\TaxCategory;
use App\Models\MainCategory;
use Shared\Helpers\NumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('sales.choose-type');
    }

    /**
     * Show the form for creating a new order.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->route('sales.choose-type');
    }

    /**
     * Store a newly created order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50|unique:orders',
            'order_date' => 'required|date',
            'customer_name' => 'required|string|max:100',
            'platform' => 'required|string|max:50',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,paid,cancelled',
        ]);

        Order::create($validated);

        return redirect()->route('sales.index')
                        ->with('status', 'Pesanan berhasil ditambahkan!');
    }

    /**
     * Display the specified order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        return redirect()->route('sales.order.detail', $order);
    }

    /**
     * Show the form for editing the specified order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        return redirect()->route('sales.list', ['order_number' => $order->order_number]);
    }

    /**
     * Update the specified order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50|unique:orders,order_number,' . $order->id,
            'order_date' => 'required|date',
            'customer_name' => 'required|string|max:100',
            'platform' => 'required|string|max:50',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,paid,cancelled',
        ]);

        $order->update($validated);

        return redirect()->route('sales.index')
                        ->with('status', 'Pesanan berhasil diperbarui!');
    }

    /**
     * Remove the specified order from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('sales.index')
                        ->with('status', 'Pesanan berhasil dihapus!');
    }

    /**
     * Print the specified order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function print(Order $order)
    {
        return redirect()->route('sales.order.print', $order);
    }

    /**
     * Tampilkan halaman menu penjualan
     */
    public function chooseType()
    {
        return view('sales.choose-type');
    }

    /**
     * Tampilkan halaman penjualan offline
     */
    public function offline()
    {
        return view('sales.offline');
    }

    /**
     * Tampilkan halaman penjualan online
     */
    public function online()
    {
        $platforms = Platform::all();
        return view('sales.online', compact('platforms'));
    }

    /**
     * Tampilkan daftar penjualan
     */
    public function list(Request $request)
    {
        // Apply filters from request
        $query = Order::withoutGlobalScope('mainCategory')->with([
            'platform',
            'orderItems',
            'orderItems.platformProduct', // Ini akan memuat varian karena termasuk dalam model PlatformProduct
            'orderItems.warehouseStock',
        ]);
        
        // Filter by date range
        if ($request->filled('date_start')) {
            $query->whereDate('tanggal', '>=', $request->date_start);
        }
        
        if ($request->filled('date_end')) {
            $query->whereDate('tanggal', '<=', $request->date_end);
        }
        
        // Filter by platform - menggunakan ID platform langsung
        if ($request->filled('platform') && $request->platform != '') {
            $platformId = $request->platform;
            // Validasi bahwa platform_id adalah numeric
            if (is_numeric($platformId)) {
                $query->where('platform_id', $platformId);
            }
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by main category from session - pre-compute IDs for performance
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            
            // Get order IDs matching the category via a single non-correlated query
            $matchingOrderIds = \App\Models\OrderItem::whereHas('warehouseStock.product', function($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId);
            })->pluck('order_id');
            
            // Get order IDs with incomplete relationship chain (no warehouse stock)
            $noStockOrderIds = \App\Models\OrderItem::whereDoesntHave('warehouseStock')->pluck('order_id');
            
            $allOrderIds = $matchingOrderIds->merge($noStockOrderIds)->unique()->values();
            
            if ($allOrderIds->isNotEmpty()) {
                $query->whereIn('id', $allOrderIds);
            } else {
                // No matching orders at all, return empty result
                $query->whereRaw('1 = 0');
            }
        }
            
        // Fetch paginated orders
        $orders = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Get all platforms for filter dropdown
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->orderBy('name')->get();
        
        return view('sales.list', [
            'orders' => $orders,
            'platforms' => $platforms,
        ]);
    }
    
    /**
     * Tampilkan halaman pilih metode input berdasarkan platform
     */
    public function platform($platform)
    {
        // Validasi platform yang diminta
        if (!in_array($platform, ['shopee', 'shopee2', 'tiktok', 'tiktok2'])) {
            return redirect()->route('sales.online')->with('error', 'Platform tidak valid.');
        }

        // Untuk Shopee, gunakan view khusus
        if ($platform === 'shopee') {
            return view('sales.shopee.platform');
        }

        // Untuk Shopee2, gunakan view khusus
        if ($platform === 'shopee2') {
            return view('sales.shopee2.platform');
        }

        // Untuk Tiktok, gunakan view khusus
        if ($platform === 'tiktok') {
            return view('sales.tiktok.platform');
        }

        // Untuk Tiktok2, gunakan view khusus
        if ($platform === 'tiktok2') {
            return view('sales.tiktok2.platform');
        }

        return redirect()->route('sales.online');
    }

    /**
     * Tampilkan daftar barang keluar terkait penjualan
     */
    public function outgoingItems(Request $request)
    {
        // Query dasar
        $query = BarangKeluar::with([
            'warehouseStock' => function ($query) {
                $query->with([
                    'product', 
                    'penerimaanDetail' => function ($q) { 
                        $q->with('penerimaan');
                    }
                ]);
            },
            'orderItem' => function ($query) {
                $query->with(['platformProduct', 'order']);
            },
        ]);
        
        // Filter berdasarkan tanggal keluar (range)
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_keluar', '>=', $request->tanggal_mulai);
        }
        
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal_keluar', '<=', $request->tanggal_akhir);
        }
        
        // Filter berdasarkan produk
        if ($request->filled('product_id')) {
            $query->whereHas('warehouseStock', function($q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }
        
        // Filter by main category from session
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->whereHas('warehouseStock.product', function($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId);
            });
        }
        
        // Search term pencarian umum
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                // Cari di nomor order
                $q->whereHas('orderItem.order', function($q) use ($searchTerm) {
                    $q->where('order_number', 'like', '%' . $searchTerm . '%');
                })
                // Cari di nama produk
                ->orWhereHas('warehouseStock.product', function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                })
                // Cari di nomor PO
                ->orWhereHas('warehouseStock.penerimaanDetail.penerimaan', function($q) use ($searchTerm) {
                    $q->where('nomor_po', 'like', '%' . $searchTerm . '%');
                })
                // Cari di catatan barang keluar
                ->orWhere('catatan', 'like', '%' . $searchTerm . '%');
            });
        }
        
        // Order by tanggal keluar descending
        $query->orderBy('tanggal_keluar', 'desc');
        
        // Tentukan jumlah baris per halaman
        $perPage = $request->input('per_page', 20);
        
        // Paginate hasil
        $barangKeluar = $query->paginate($perPage)->withQueryString();
        
        // Dapatkan daftar produk untuk dropdown filter
        $products = Product::orderBy('name')->get();
        
        return view('sales.listbarangkeluar', [
            'barangKeluar' => $barangKeluar,
            'products' => $products,
        ]);
    }

    /**
     * Tampilkan halaman input manual penjualan online
     */
    public function onlineInput($platform)
    {
        // Validasi platform yang diminta
        if (!in_array($platform, ['shopee', 'shopee2', 'tiktok', 'tiktok2'])) {
            return redirect()->route('sales.online')->with('error', 'Platform tidak valid.');
        }

        // Dapatkan objek platform dari database dengan dukungan alias
        $platformObj = $this->resolvePlatform($platform);
        if (!$platformObj) {
            return redirect()->route('sales.online')->with('error', "Platform $platform tidak ditemukan.");
        }

        // Dapatkan daftar produk yang sudah di-mapping untuk platform ini
        $mappedProducts = PlatformProduct::where('platform_id', $platformObj->id)
            ->with(['mappingBarang', 'mappingBarang.product'])
            ->orderBy('platform_product_name')
            ->get();

        // Hitung stok tersedia untuk setiap produk platform
        foreach ($mappedProducts as $product) {
            $stokTersedia = [];
            
            // Cek stok untuk setiap barang yang di-mapping
            foreach ($product->mappingBarang as $mapping) {
                // Skip jika product tidak ada (sudah dihapus)
                if (!$mapping->product) {
                    \Log::warning('Mapping references non-existent product', [
                        'platform_product_id' => $product->id,
                        'mapping_id' => $mapping->id,
                        'product_id' => $mapping->product_id,
                    ]);
                    continue;
                }
                
                // Ambil total stok tersedia dari warehouse untuk produk ini
                $totalStok = WarehouseStock::where('product_id', $mapping->product_id)
                    ->where('qty', '>', 0)
                    ->sum('qty');
                
                // Stok efektif berdasarkan jumlah yang dibutuhkan per item
                // Guard terhadap pembagi nol atau quantity tidak valid
                $mappingQty = (int) $mapping->quantity;
                if ($mappingQty <= 0) {
                    \Log::warning('Mapping quantity invalid for stock calculation', [
                        'platform_product_id' => $product->id,
                        'product_id' => $mapping->product_id,
                        'mapping_quantity' => $mapping->quantity,
                    ]);
                    $stokEfektif = 0;
                } else {
                    $stokEfektif = (int) floor($totalStok / $mappingQty);
                }
                
                $stokTersedia[] = [
                    'product_id' => $mapping->product_id,
                    'product_name' => $mapping->product->name,
                    'stok_tersedia' => $totalStok,
                    'stok_efektif' => $stokEfektif
                ];
            }
            
            // Temukan stok efektif paling kecil (bottleneck)
            $minStokEfektif = count($stokTersedia) > 0 ? 
                min(array_column($stokTersedia, 'stok_efektif')) : 0;
            
            // Tambahkan informasi stok ke produk platform
            $product->stok_detail = $stokTersedia;
            $product->stok_tersedia = $minStokEfektif;
        }

        return view('sales.online-input', [
            'platform' => strtolower($platform),
            'platformDisplayName' => $platformObj->name ?? ucfirst($platform),
            'mappedProducts' => $mappedProducts,
        ]);
    }

    /**
     * Simpan transaksi penjualan online manual
     */
    public function saveOnlineTransaction(Request $request)
    {
        // Validasi data input
        $validated = $request->validate([
            'platform' => 'required|string|in:shopee,shopee2,tiktok,tiktok2',
            'no_order' => 'required|string|max:100',
            'order_date' => 'required|date',
            'day_status' => 'nullable|string|max:255', // Support multiple values separated by comma
            'tracking_number' => 'nullable|string|max:100', // Nomor resi untuk seluruh order
            'items' => 'required|array|min:1',
            'items.*.platform_product_id' => 'required|exists:platform_products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        // Dapatkan objek platform dengan dukungan alias
        $platform = $this->resolvePlatform($request->platform);
        if (!$platform) {
            return back()->with('error', "Platform {$request->platform} tidak ditemukan.")->withInput();
        }

        // Cek apakah nomor order sudah ada
        $existingOrder = Order::where('platform_id', $platform->id)
            ->where('order_number', $request->no_order)
            ->first();

        if ($existingOrder) {
            return back()->with('error', 'Nomor order sudah ada di database.')->withInput();
        }

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Set flag konsolidasi order items berdasarkan produk
            // Konsolidasi artinya jika ada barang dengan jenis yang sama tapi berbeda tax_id,
            // tetap dijadikan 1 baris di order_items
            WarehouseStock::$consolidateOrderItemsByProduct = true;
            
            // Buat record order baru
            $orderDate = Carbon::parse($request->order_date);
            $order = new Order([
                'platform_id' => $platform->id,
                'order_number' => $request->no_order,
                'tanggal' => $orderDate->format('Y-m-d'),
                'hari' => $orderDate->format('l'),
                'status_hari' => $request->day_status, // Support multiple values separated by comma
                'status' => 'completed',
            ]);
            $order->save();

            // Proses setiap item
            foreach ($request->items as $item) {
                $platformProduct = PlatformProduct::findOrFail($item['platform_product_id']);
                
                // Buat order item
                $orderItem = new OrderItem([
                    'order_id' => $order->id,
                    'platform_product_id' => $platformProduct->id,
                    'quantity' => $item['qty'],
                    'price_after_discount' => $item['price'],
                    'tracking_number' => $request->tracking_number, // Gunakan nomor resi dari level order
                ]);
                $orderItem->save();
                
                // Kurangi stok dan catat barang keluar
                $this->reduceStockAndRecordOutgoing($platformProduct, $item['qty'], $orderItem, $order);
            }

            // Reset flag konsolidasi 
            WarehouseStock::$consolidateOrderItemsByProduct = false;
            
            DB::commit();
            return redirect()->route('sales.list')->with('success', 'Transaksi penjualan berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            // Pastikan reset flag konsolidasi meskipun gagal
            WarehouseStock::$consolidateOrderItemsByProduct = false;
            return back()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Kurangi stok dan catat barang keluar
     */
    private function reduceStockAndRecordOutgoing($platformProduct, $quantity, $orderItem, $order)
    {
        // Dapatkan semua mapping barang AKTIF untuk platform product ini
        $mappings = MappingBarang::where('platform_product_id', $platformProduct->id)
            ->where('is_active', true)
            ->get();
        
        if ($mappings->isEmpty()) {
            throw new \Exception("Produk {$platformProduct->platform_product_name} belum di-mapping dengan produk master");
        }
        
        // Simpan referensi ke order item asli untuk menghapusnya nanti jika kita membuat baru
        $originalOrderItem = $orderItem;
        $hasCreatedNewItems = false;
        
        // Untuk consolidasi berdasarkan product_id
        $productStockItems = [];
        
        foreach ($mappings as $mapping) {
            // Validasi quantity mapping agar tidak nol/negatif
            if ((int) $mapping->quantity <= 0) {
                $product = Product::find($mapping->product_id);
                $productName = $product ? $product->name : ('ID ' . $mapping->product_id);
                throw new \Exception("Quantity mapping tidak valid (<= 0) untuk produk {$productName} pada {$platformProduct->platform_product_name}");
            }
            // Hitung jumlah yang perlu dikurangi dari stok
            $qtyToReduce = $quantity * $mapping->quantity;
            
            // Ambil stok produk dari warehouse berdasarkan FIFO + prioritas HGN
            // PERBAIKAN: lockForUpdate() mencegah race condition saat 2 order diproses bersamaan
            $stocks = WarehouseStock::where('product_id', $mapping->product_id)
                ->where('qty', '>', 0)
                ->orderBy('created_at') // Layer 1: FIFO berdasarkan tanggal penerimaan
                ->orderBy('tax_id', 'asc') // Layer 2: HGN (tax_id=3) dulu, baru LM (tax_id=4)
                ->lockForUpdate()
                ->get();
            
            // Hitung total stok tersedia
            $totalAvailable = $stocks->sum('qty');
            if ($totalAvailable < $qtyToReduce) {
                $product = Product::find($mapping->product_id);
                throw new \Exception("Stok tidak cukup untuk produk {$product->name}. Tersedia: {$totalAvailable}, Dibutuhkan: {$qtyToReduce}");
            }
            
            $remainingQty = $qtyToReduce;
            $stockItems = [];
            
            // Pertama, kumpulkan semua warehouse_stock yang akan digunakan dan qty masing-masing
            foreach ($stocks as $stock) {
                if ($remainingQty <= 0) {
                    break;
                }
                
                // Hitung quantity yang akan dikurangi dari stok ini
                $qtyToTake = min($remainingQty, $stock->qty);
                
                $stockItems[] = [
                    'stock' => $stock,
                    'qty' => $qtyToTake,
                    'product_id' => $mapping->product_id,
                    'mapping_quantity' => $mapping->quantity
                ];
                
                // Update sisa quantity yang perlu dikurangi
                $remainingQty -= $qtyToTake;
            }
            
            // Simpan semua stock items untuk diproses nanti
            if (WarehouseStock::$consolidateOrderItemsByProduct) {
                foreach ($stockItems as $stockItem) {
                    $productStockItems[] = $stockItem;
                }
            } else {
                // Jika tidak mengkonsolidasi, proses langsung seperti sebelumnya
                $this->processStockItems($stockItems, $orderItem, $order, $platformProduct, $quantity, $mapping->quantity);
                
                if (count($stockItems) > 1) {
                    $hasCreatedNewItems = true;
                }
            }
        }
        
        // Jika mengkonsolidasi produk, proses stock items berdasarkan product_id
        if (WarehouseStock::$consolidateOrderItemsByProduct && !empty($productStockItems)) {
            // Konsolidasi berdasarkan product_id
            $consolidatedStockItems = [];
            
            foreach ($productStockItems as $stockItem) {
                $productId = $stockItem['stock']->product_id;
                
                if (!isset($consolidatedStockItems[$productId])) {
                    $consolidatedStockItems[$productId] = [
                        'product_id' => $productId,
                        'items' => []
                    ];
                }
                
                $consolidatedStockItems[$productId]['items'][] = $stockItem;
            }
            
            // Sekarang proses setiap kelompok produk
            foreach ($consolidatedStockItems as $productId => $data) {
                // Hanya gunakan 1 order item per produk
                $stockItems = $data['items'];
                
                if (count($stockItems) > 0) {
                    // Gunakan orderItem asli jika ini produk pertama
                    $currentOrderItem = $orderItem;
                    
                    // Set warehouse_stock_id ke stock pertama
                    $currentOrderItem->warehouse_stock_id = $stockItems[0]['stock']->id;
                    $currentOrderItem->save();
                    
                    // Catat semua barang keluar untuk produk ini
                    foreach ($stockItems as $stockItem) {
                        $stock = $stockItem['stock'];
                        $qtyToTake = $stockItem['qty'];
                        $mappingQuantity = $stockItem['mapping_quantity'];
                        
                        // Catat barang keluar
                        $kodeBarangKeluar = BarangKeluar::generateKode();
                        BarangKeluar::create([
                            'kode_barang_keluar' => $kodeBarangKeluar,
                            'order_item_id' => $currentOrderItem->id,
                            'warehouse_stock_id' => $stock->id,
                            'qty' => $qtyToTake,
                            'tanggal_keluar' => $order->tanggal,
                            'catatan' => "Penjualan online {$platformProduct->platform->name} - Order #{$order->order_number}",
                        ]);
                        
                        // Kurangi stok
                        $stock->qty -= $qtyToTake;
                        $stock->save();
                    }
                }
            }
        }
        
        // Jika kita membuat order item baru, hapus yang asli
        if ($hasCreatedNewItems && !WarehouseStock::$consolidateOrderItemsByProduct) {
            $originalOrderItem->delete();
        }
    }
    
    /**
     * Proses stock items dan buat order items sesuai kebutuhan
     * Helper method untuk reduceStockAndRecordOutgoing
     */
    private function processStockItems($stockItems, $orderItem, $order, $platformProduct, $orderQuantity, $mappingQuantity)
    {
        foreach ($stockItems as $index => $stockItem) {
            $stock = $stockItem['stock'];
            $qtyToTake = $stockItem['qty'];
            
            if ($index === 0 && count($stockItems) === 1) {
                // Jika hanya ada 1 stock, gunakan order item yang sudah ada
                $currentOrderItem = $orderItem;
                $currentOrderItem->warehouse_stock_id = $stock->id;
                $currentOrderItem->save();
            } else {
                // Jika lebih dari 1 stock, buat order item baru
                
                // Hitung proporsi harga berdasarkan quantity
                $proportionalPrice = ($qtyToTake / ($orderQuantity * $mappingQuantity)) * $orderItem->price_after_discount;
                
                $currentOrderItem = new OrderItem([
                    'order_id' => $order->id,
                    'platform_product_id' => $platformProduct->id,
                    'quantity' => $qtyToTake / $mappingQuantity, // Konversi balik ke unit produk platform
                    'price_after_discount' => $proportionalPrice,
                    'tracking_number' => $orderItem->tracking_number,
                    'warehouse_stock_id' => $stock->id
                ]);
                $currentOrderItem->save();
            }
            
            // Catat barang keluar
            $kodeBarangKeluar = BarangKeluar::generateKode();
            BarangKeluar::create([
                'kode_barang_keluar' => $kodeBarangKeluar,
                'order_item_id' => $currentOrderItem->id,
                'warehouse_stock_id' => $stock->id,
                'qty' => $qtyToTake,
                'tanggal_keluar' => $order->tanggal,
                'catatan' => "Penjualan online {$platformProduct->platform->name} - Order #{$order->order_number}",
            ]);
            
            // Kurangi stok
            $stock->qty -= $qtyToTake;
            $stock->save();
        }
    }
    
    /**
     * API endpoint untuk mengecek apakah nomor order sudah ada
     */
    public function checkOrderExists(Request $request)
    {
        // Validasi input
        $request->validate([
            'platform' => 'required|string|in:shopee,shopee2,tiktok,tiktok2',
            'order_number' => 'required|string',
        ]);
        
        // Dapatkan platform ID dengan dukungan alias
        $platform = $this->resolvePlatform($request->platform);
        
        if (!$platform) {
            return response()->json([
                'exists' => false,
                'message' => 'Platform tidak valid'
            ]);
        }
        
        // Cek apakah nomor order sudah ada
        $orderExists = Order::where('platform_id', $platform->id)
            ->where('order_number', $request->order_number)
            ->exists();
        
        return response()->json([
            'exists' => $orderExists,
            'message' => $orderExists ? 'Nomor order sudah ada' : 'Nomor order tersedia'
        ]);
    }

    /**
     * Resolve platform by slug/name/alias (supports Shopee2/Tiktok2 naming variants)
     */
    protected function resolvePlatform(string $identifier): ?Platform
    {
        $slug = strtolower(trim($identifier));

        if ($slug === '') {
            return null;
        }

        // Exact match (case insensitive)
        $platform = Platform::whereRaw('LOWER(name) = ?', [$slug])->first();

        // Partial match (handles names like "Shopee Troublue")
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%' . $slug . '%'])->first();
        }

        if ($platform) {
            return $platform;
        }

        // Fallback mapping for known aliases/IDs
        $aliasMap = [
            'shopee2' => 6,
            'shopee troublue' => 6,
            'shopee trubleu' => 6,
            'shopee trouble' => 6,
            'tiktok2' => 7,
            'tiktok troublue' => 7,
            'tiktok trouble' => 7,
        ];

        if (isset($aliasMap[$slug])) {
            return Platform::find($aliasMap[$slug]);
        }

        return null;
    }

    /**
     * Show order detail
     */
    public function orderDetail($id)
    {
        $order = Order::with([
            'platform',
            'orderItems',
            'orderItems.platformProduct',
            'orderItems.warehouseStock',
            'orderItems.warehouseStock.product',
        ])->findOrFail($id);
        
        return view('sales.partials.order-detail', compact('order'));
    }
    
    /**
     * Print order
     */
    public function printOrder($id)
    {
        // Skip global scope to ensure order can be printed
        $order = Order::withoutGlobalScope('mainCategory')->with([
            'platform',
            'orderItems',
            'orderItems.platformProduct',
            'orderItems.warehouseStock',
            'orderItems.warehouseStock.product',
        ])->findOrFail($id);
        
        return view('sales.print.order', compact('order'));
    }
    
    /**
     * Delete order
     */
    public function destroyOrder($id)
    {
        // Pastikan hanya superadmin yang bisa menghapus pesanan
        if (auth()->user()->role !== 'superadmin') {
            return redirect()->route('sales.list')
                ->with('error', 'Anda tidak memiliki izin untuk menghapus pesanan.');
        }

        try {
            DB::beginTransaction();
            
            $order = Order::withoutGlobalScope('mainCategory')->findOrFail($id);
            
            // Get all order items
            $orderItems = $order->orderItems;
            
            // Process each order item
            foreach ($orderItems as $item) {
                // Get all barang keluar records for this order item
                $barangKeluarItems = BarangKeluar::where('order_item_id', $item->id)->get();
                
                // Return stock to warehouse
                foreach ($barangKeluarItems as $barangKeluar) {
                    if ($barangKeluar->warehouseStock) {
                        // Increment stock quantity
                        $warehouseStock = $barangKeluar->warehouseStock;
                        $warehouseStock->qty += $barangKeluar->qty;
                        $warehouseStock->save();
                        
                        // Log stock restoration
                        \Log::info('Stock restored', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'warehouse_stock_id' => $warehouseStock->id,
                            'product_id' => $warehouseStock->product_id,
                            'restored_qty' => $barangKeluar->qty,
                            'current_qty' => $warehouseStock->qty
                        ]);
                    }
                    
                    // Delete barang keluar record
                    $barangKeluar->delete();
                }
            }
            
            // Delete order items
            $order->orderItems()->delete();
            
            // Delete order
            $order->delete();
            
            DB::commit();
            
            return redirect()->route('sales.list')
                ->with('success', 'Pesanan berhasil dihapus dan stok telah dikembalikan ke gudang');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('sales.list')
                ->with('error', 'Gagal menghapus pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan daftar penjualan offline
     */
    public function offlineSalesList(Request $request)
    {
        // Apply filters from request
        $query = OfflineSale::with([
            'items',
            'items.product',
            'items.warehouseStock',
        ]);
        
        // Ensure items are loaded for hasReturFull() check
        
        // Filter by date range
        if ($request->filled('date_start')) {
            $query->whereDate('sale_date', '>=', $request->date_start);
        }
        
        if ($request->filled('date_end')) {
            $query->whereDate('sale_date', '<=', $request->date_end);
        }
        
        // Filter by surat jalan number
        if ($request->filled('surat_jalan_number')) {
            $query->where('surat_jalan_number', 'like', '%' . $request->surat_jalan_number . '%');
        }
        
        // Filter by nomor PO
        if ($request->filled('No_PO')) {
            $query->where('No_PO', 'like', '%' . $request->No_PO . '%');
        }
        
        // Filter by main category from session
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->where(function($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId)
                   ->orWhereNull('main_category_id');
            });
        }
        
        // Clone query for summary calculation (before pagination)
        $summaryQuery = clone $query;
        
        // Calculate summary data for all sales (not just paginated) - only affected by filters
        $allSales = $summaryQuery->with([
            'items',
            'items.product',
            'items.warehouseStock',
        ])->get();
        
        // Fetch paginated sales
        $offlineSales = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Calculate total value based on tax status
        // If sale has PPN (tax_amount > 0), use total_amount (DPP + PPN)
        // If sale has no PPN (tax_amount = 0), use subtotal (DPP only)
        // If sale has retur full, total value = 0
        $totalValue = $allSales->sum(function($sale) {
            // If retur full, total value = 0
            if ($sale->hasReturFull()) {
                return 0;
            }
            return $sale->tax_amount > 0 ? $sale->total_amount : $sale->subtotal;
        });
        
        // Calculate status breakdown from all filtered sales (not just paginated)
        $paidCount = 0;
        $partialCount = 0;
        $pendingCount = 0;
        $cancelledCount = 0;
        $returCount = 0;
        
        foreach ($allSales as $sale) {
            if ($sale->status == 'cancelled') {
                $cancelledCount++;
            } elseif ($sale->hasReturns()) {
                // Check retur first (before payment status)
                $returCount++;
            } else {
                $paymentStatus = $sale->getPaymentStatus();
                if ($paymentStatus == 'paid') {
                    $paidCount++;
                } elseif ($paymentStatus == 'partial') {
                    $partialCount++;
                } else {
                    $pendingCount++;
                }
            }
        }
        
        $summary = [
            'total_sales' => $allSales->count(),
            'total_value' => $totalValue,
            'total_volume' => $allSales->sum(function($sale) {
                return $sale->items->sum('quantity');
            }),
            'avg_order_value' => $allSales->count() > 0 ? $totalValue / $allSales->count() : 0,
            'status_breakdown' => [
                'paid' => $paidCount,
                'partial' => $partialCount,
                'pending' => $pendingCount,
                'cancelled' => $cancelledCount,
                'retur' => $returCount,
            ],
        ];
        
        return view('sales.offline.list', [
            'offlineSales' => $offlineSales,
            'summary' => $summary,
        ]);
    }

    /**
     * Tampilkan halaman input penjualan offline
     */
    public function offlineSaleCreate()
    {
        // Get main categories for SJ number generation
        $mainCategories = MainCategory::where('is_active', true)->get();
        
        // Default SJ number (can be updated via AJAX later)
        $suratJalanNumber = OfflineSale::generateSuratJalanNumber();
        
        // Get only products that have stock in warehouse
        $productIds = WarehouseStock::where('qty', '>', 0)
            ->distinct()
            ->pluck('product_id');
            
        $products = Product::whereIn('id', $productIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Get all active customers for dropdown
        $customers = Customer::where('status', 'active')->orderBy('name')->get();
        
        // Flash info message about automatic SJ number generation
        session()->flash('info', 'Perhatian: Sistem akan secara otomatis membuat Surat Jalan terpisah untuk setiap kategori pajak yang ada pada barang yang dipilih dari gudang.');
        
        return view('sales.offline.create', compact('suratJalanNumber', 'products', 'customers', 'mainCategories'));
    }

    /**
     * Generate SJ number based on tax and main category
     */
    public function generateSJNumber(Request $request)
    {
        $mainCategoryId = $request->input('main_category_id');
        
        $sjNumber = OfflineSale::generateSuratJalanNumber(null, $mainCategoryId);
        
        return response()->json(['surat_jalan_number' => $sjNumber]);
    }

    /**
     * Menyimpan data penjualan offline baru
     */
    public function offlineSaleStore(Request $request)
    {
        $request->validate([
            // 'surat_jalan_number' => 'required|string', // Removed - will be auto-generated by backend
            'No_PO' => 'nullable|string',
            'sale_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,paid,cancelled',
            'payment_date' => 'nullable|date|required_if:status,paid',
            'payment_method' => 'nullable|string|required_if:status,paid',
            'product_id' => 'required|array',
            'product_id.*' => 'required|exists:products,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|numeric|min:0.01',
            'unit_price' => 'required|array',
            'unit_price.*' => 'required|numeric|min:0',
            'main_category_id' => 'nullable|exists:main_categories,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Get customer data
            $customer = Customer::findOrFail($request->customer_id);
            
            // Group product items by tax_id
            $productsByTaxId = [];
            $mainCategoryId = $request->main_category_id;
            
            // First, group products by their tax ID
            foreach ($request->product_id as $i => $productId) {
                // Get product to determine tax ID from its warehouse stock
                $product = Product::findOrFail($productId);
                
                // Get all available warehouse stocks for this product
                // PERBAIKAN: lockForUpdate() mencegah race condition saat 2 penjualan berjalan bersamaan
                $warehouseStocks = WarehouseStock::where('product_id', $productId)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at')   // Layer 1: FIFO berdasarkan tanggal penerimaan
                    ->orderBy('tax_id', 'asc') // Layer 2: HGN (tax_id=3) dulu, baru LM (tax_id=4)
                    ->lockForUpdate()
                    ->get();
                
                if ($warehouseStocks->isEmpty()) {
                    return redirect()->back()->with('error', 'Stok tidak tersedia untuk produk dengan ID: ' . $productId)->withInput();
                }
                
                // Check if the total available stock is enough
                $totalAvailableStock = $warehouseStocks->sum('qty');
                $requestedQuantity = $request->quantity[$i];
                
                if ($totalAvailableStock < $requestedQuantity) {
                    return redirect()->back()->with('error', 'Stok tidak mencukupi untuk produk dengan ID: ' . $productId . '. Total stok tersedia: ' . $totalAvailableStock)->withInput();
                }
                
                // Group warehouse stocks by tax_id
                $stocksByTaxId = [];
                $remainingQuantity = $requestedQuantity;
                
                foreach ($warehouseStocks as $stock) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }
                    
                    $quantityToTake = min($remainingQuantity, $stock->qty);
                    $taxId = $stock->tax_id ?: 4; // Default to Non-PKP (tax_id=4) if not found
                    
                    if (!isset($stocksByTaxId[$taxId])) {
                        $stocksByTaxId[$taxId] = [];
                    }
                    
                    $stocksByTaxId[$taxId][] = [
                        'stock' => $stock,
                        'quantity' => $quantityToTake,
                    ];
                    
                    $remainingQuantity -= $quantityToTake;
                }
                
                // Process each tax_id group
                foreach ($stocksByTaxId as $taxId => $stocks) {
                    if (!isset($productsByTaxId[$taxId])) {
                        $productsByTaxId[$taxId] = [];
                    }
                    
                    // Calculate total quantity for this tax group
                    $totalQuantity = array_sum(array_column($stocks, 'quantity'));
                    
                    // Calculate price proportion for this tax group
                    $proportion = $requestedQuantity > 0 ? $totalQuantity / $requestedQuantity : 0;
                    $unitPrice = NumberFormatter::formatForDatabase($request->unit_price[$i]);
                    $itemSubtotal = isset($request->item_subtotal[$i]) ? 
                        NumberFormatter::formatForDatabase($request->item_subtotal[$i] * $proportion) : 
                        NumberFormatter::calculateSubtotal($unitPrice, $totalQuantity);
                    
                    // Add product to this tax group
                    $productsByTaxId[$taxId][] = [
                        'product_id' => $productId,
                        'quantity' => $totalQuantity,
                        'unit_price' => $unitPrice,
                        'item_subtotal' => $itemSubtotal,
                        'notes' => isset($request->item_notes[$i]) ? $request->item_notes[$i] : null,
                        'discount_amount_1' => isset($request->discount_amount_1[$i]) ? NumberFormatter::formatForDatabase($request->discount_amount_1[$i] * $proportion) : 0,
                        'discount_percent_1' => isset($request->discount_percent_1[$i]) ? NumberFormatter::formatForDatabase($request->discount_percent_1[$i]) : 0,
                        'discount_amount_2' => isset($request->discount_amount_2[$i]) ? NumberFormatter::formatForDatabase($request->discount_amount_2[$i] * $proportion) : 0,
                        'discount_percent_2' => isset($request->discount_percent_2[$i]) ? NumberFormatter::formatForDatabase($request->discount_percent_2[$i]) : 0,
                        'discount_amount_3' => isset($request->discount_amount_3[$i]) ? NumberFormatter::formatForDatabase($request->discount_amount_3[$i] * $proportion) : 0,
                        'discount_percent_3' => isset($request->discount_percent_3[$i]) ? NumberFormatter::formatForDatabase($request->discount_percent_3[$i]) : 0,
                        'discount_amount_4' => isset($request->discount_amount_4[$i]) ? NumberFormatter::formatForDatabase($request->discount_amount_4[$i] * $proportion) : 0,
                        'discount_percent_4' => isset($request->discount_percent_4[$i]) ? NumberFormatter::formatForDatabase($request->discount_percent_4[$i]) : 0,
                        'discount_amount_5' => isset($request->discount_amount_5[$i]) ? NumberFormatter::formatForDatabase($request->discount_amount_5[$i] * $proportion) : 0,
                        'discount_percent_5' => isset($request->discount_percent_5[$i]) ? NumberFormatter::formatForDatabase($request->discount_percent_5[$i]) : 0,
                        'stocks' => $stocks,
                    ];
                }
            }
            
            // Create separate OfflineSale records for each tax group
            $createdSales = [];
            
            foreach ($productsByTaxId as $taxId => $products) {
                // Calculate totals for this tax group
                $groupSubtotal = NumberFormatter::formatForDatabase(array_sum(array_column($products, 'item_subtotal')));
                $groupTaxAmount = NumberFormatter::formatForDatabase(0); // Now tax is always zero
                $groupTotalAmount = $groupSubtotal; // Total equals subtotal
                
                // Generate unique SJ number for this tax group with order date
                $suratJalanNumber = OfflineSale::generateSuratJalanNumber($taxId, $mainCategoryId, $request->sale_date);
                
                // Create offline sale for this tax group
                $offlineSale = OfflineSale::create([
                    'surat_jalan_number' => $suratJalanNumber,
                    'No_PO' => $request->No_PO,
                    'sale_date' => $request->sale_date,
                    'customer_name' => $customer->name,
                    'customer_id' => $customer->id,
                    'status' => $request->status,
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'subtotal' => $groupSubtotal,
                    'tax_amount' => $groupTaxAmount,
                    'total_amount' => $groupTotalAmount,
                    'notes' => $request->notes,
                    'created_by' => auth()->id(),
                    'main_category_id' => $mainCategoryId,
                ]);
                
                // Process each product in this tax group
                foreach ($products as $productData) {
                    $productId = $productData['product_id'];
                    $quantity = NumberFormatter::formatForDatabase($productData['quantity']);
                    $unitPrice = NumberFormatter::formatForDatabase($productData['unit_price']);
                    $itemSubtotal = NumberFormatter::formatForDatabase($productData['item_subtotal']);
                    $stocks = $productData['stocks'];
                    
                    // Set up discount data - ambil langsung dari request sesuai index item
                    $discountData = [];
                    for ($j = 1; $j <= 5; $j++) {
                        $discountData["discount_amount_{$j}"] = isset($request->{"discount_amount_{$j}"}[$i]) ? $request->{"discount_amount_{$j}"}[$i] * $proportion : 0;
                        $discountData["discount_percent_{$j}"] = isset($request->{"discount_percent_{$j}"}[$i]) ? $request->{"discount_percent_{$j}"}[$i] : 0;
                    }
                    
                    // Create a mapping to track which discounts were actually entered by the user
                    $discountMapping = [];
                    
                    // Debug: Log the original product data for discounts
                    \Log::debug('Original product data discounts for product ' . $productId, [
                        'discount_amount_1' => $productData['discount_amount_1'] ?? 0,
                        'discount_percent_1' => $productData['discount_percent_1'] ?? 0,
                        'discount_amount_2' => $productData['discount_amount_2'] ?? 0,
                        'discount_percent_2' => $productData['discount_percent_2'] ?? 0,
                    ]);
                    
                    // Process discounts in their original order (1-5)
                    // Only process the discounts that actually exist in the request
                    $discountCount = 0;
                    for ($j = 1; $j <= 5; $j++) {
                        $amountKey = "discount_amount_{$j}";
                        $percentKey = "discount_percent_{$j}";
                        
                        // Check if this specific discount exists in the request data
                        // Only count it if it was actually provided by the user
                        $hasAmount = isset($request->{$amountKey}[$i]) && $request->{$amountKey}[$i] > 0;
                        $hasPercent = isset($request->{$percentKey}[$i]) && $request->{$percentKey}[$i] > 0;
                        
                        if ($hasAmount || $hasPercent) {
                            $discountCount++;
                            // Store the discount in the correct index (starting from 1)
                            $discountData["discount_amount_{$discountCount}"] = $hasAmount ? NumberFormatter::formatForDatabase($productData[$amountKey] ?? 0) : 0;
                            $discountData["discount_percent_{$discountCount}"] = $hasPercent ? NumberFormatter::formatForDatabase($productData[$percentKey] ?? 0) : 0;
                        }
                    }
                    
                    // Debug: Log the processed discount data with more details
                    \Log::debug('Processed discount data for product ' . $productId, [
                        'discountData' => $discountData,
                        'discountCount' => $discountCount,
                        'request_data' => [
                            'discount_percent_1' => $request->discount_percent_1[$i] ?? 'not set',
                            'discount_percent_2' => $request->discount_percent_2[$i] ?? 'not set',
                            'discount_percent_3' => $request->discount_percent_3[$i] ?? 'not set',
                            'discount_percent_4' => $request->discount_percent_4[$i] ?? 'not set',
                            'discount_percent_5' => $request->discount_percent_5[$i] ?? 'not set',
                        ]
                    ]);
                    
                    // Create the sale item with discount data
                    $saleItemData = [
                        'offline_sale_id' => $offlineSale->id,
                        'product_id' => $productId,
                        'warehouse_stock_id' => $stocks[0]['stock']->id, // Save the first warehouse stock as reference
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $itemSubtotal,
                        'notes' => $productData['notes'],
                    ];
                    
                    // Add all discount fields to the sale item data
                    foreach ($discountData as $key => $value) {
                        $saleItemData[$key] = $value;
                    }
                    
                    $offlineSaleItem = OfflineSaleItem::create($saleItemData);
                    
                    // Process each warehouse stock to fulfill the order
                    foreach ($stocks as $stockData) {
                        $stock = $stockData['stock'];
                        $quantityToTake = $stockData['quantity'];
                        
                        // Create barang keluar record
                        BarangKeluar::create([
                            'kode_barang_keluar' => BarangKeluar::generateKode(),
                            'offline_sale_item_id' => $offlineSaleItem->id,
                            'warehouse_stock_id' => $stock->id,
                            'qty' => NumberFormatter::formatForDatabase($quantityToTake),
                            'tanggal_keluar' => $request->sale_date,
                            'catatan' => 'Penjualan Offline: ' . $suratJalanNumber,
                        ]);
                        
                        // Update stock
                        $stock->qty -= NumberFormatter::formatForDatabase($quantityToTake);
                        $stock->save();
                    }
                }
                
                $createdSales[] = $offlineSale;
            }
            
            DB::commit();
            
            // If only one sale was created, redirect to its detail page
            if (count($createdSales) === 1) {
                return redirect()->route('sales.offline.show', $createdSales[0])
                                ->with('status', 'Penjualan offline berhasil ditambahkan!');
            }
            
            // If multiple sales were created, redirect to the list with a message
            return redirect()->route('sales.offline.list')
                            ->with('status', count($createdSales) . ' Penjualan offline dengan surat jalan terpisah berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Tampilkan detail penjualan offline
     */
    public function offlineSaleShow(OfflineSale $offlineSale)
    {
        // Rebuild the query to bypass the mainCategory global scope
        $offlineSale = OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'items.warehouseStock', 'createdBy'])
            ->findOrFail($offlineSale->id);
        
        return view('sales.offline.show', compact('offlineSale'));
    }

    /**
     * Cetak surat jalan penjualan offline
     */
    public function offlineSalePrintSJ(OfflineSale $offlineSale)
    {
        // Rebuild the query to bypass the mainCategory global scope
        $offlineSale = OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'items.warehouseStock'])
            ->findOrFail($offlineSale->id);
        
        return view('sales.offline.print-sj', compact('offlineSale'));
    }

    /**
     * Hapus penjualan offline
     */
    public function offlineSaleDestroy(OfflineSale $offlineSale)
    {
        // Rebuild the query to bypass the mainCategory global scope
        $offlineSale = OfflineSale::withoutGlobalScope('mainCategory')->findOrFail($offlineSale->id);
        
        // Check if there are any paid invoices
        if ($offlineSale->hasAnyPaidInvoice()) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus penjualan yang sudah memiliki pembayaran.');
        }
        
        // Check if there are any invoices (paid or unpaid)
        if ($offlineSale->hasInvoices()) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus penjualan yang sudah memiliki invoice. Silakan hapus invoice terlebih dahulu.');
        }
        
        DB::beginTransaction();
        
        try {
            // Get all items
            $items = $offlineSale->items;
            
            foreach ($items as $item) {
                // Get barang keluar records
                $barangKeluarRecords = BarangKeluar::where('offline_sale_item_id', $item->id)->get();
                
                foreach ($barangKeluarRecords as $barangKeluar) {
                    // Check if this barang keluar has finance_offline_id (should not happen after above check)
                    if ($barangKeluar->finance_offline_id) {
                        throw new \Exception('Tidak dapat menghapus penjualan yang memiliki invoice.');
                    }
                    
                    // Restore stock
                    $warehouseStock = $barangKeluar->warehouseStock;
                    if ($warehouseStock) {
                        $warehouseStock->qty += $barangKeluar->qty;
                        $warehouseStock->save();
                    }
                    
                    // Delete barang keluar record
                    $barangKeluar->delete();
                }
            }
            
            // Delete the offline sale (this will cascade delete items)
            $offlineSale->delete();
            
            DB::commit();
            
            return redirect()->route('sales.offline.list')
                            ->with('status', 'Penjualan offline berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * API to get product stock information
     */
    public function getProductStockInfo($productId)
    {
        $product = Product::findOrFail($productId);
        
        // Get total stock amount across all warehouses
        $totalStock = WarehouseStock::where('product_id', $productId)
                        ->sum('qty');
        
        // Get the latest price (can be modified to get from a price table if you have one)
        $latestStock = WarehouseStock::where('product_id', $productId)
                        ->orderBy('created_at', 'desc')
                        ->first();
        
        // Get warehouse stocks for this product
        $stocks = WarehouseStock::where('product_id', $productId)
                    ->where('qty', '>', 0)
                    ->with(['lokasi'])
                    ->orderBy('created_at')   // Layer 1: FIFO berdasarkan tanggal penerimaan
                    ->orderBy('tax_id', 'asc') // Layer 2: HGN (tax_id=3) dulu, baru LM (tax_id=4)
                    ->get()
                    ->map(function($stock) {
                        return [
                            'id' => $stock->id,
                            'location' => $stock->lokasi->nama,
                            'qty' => $stock->qty,
                            'expired_date' => $stock->expired_date ? $stock->expired_date->format('Y-m-d') : null,
                            'status' => $stock->status_ed
                        ];
                    });
        
        // If we have penerimaan_detail relationship, we can get unit price from there
        $price = 0;
        if ($latestStock && $latestStock->penerimaanDetail) {
            $price = $latestStock->penerimaanDetail->unit_price;
        }
        
        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'total_stock' => $totalStock,
            'price' => $price,
            'warehouse_stocks' => $stocks,
            'max_allowed_qty' => $totalStock // Add this to make clear that total stock is the maximum allowed quantity
        ]);
    }
}
