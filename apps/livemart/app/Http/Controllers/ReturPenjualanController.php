<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturPenjualan;
use App\Models\ReturPenjualanDetail;
use App\Models\WarehouseStock;
use App\Models\PenerimaanDetail;
use App\Models\MappingBarang;
use App\Services\ReturFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReturPenjualanController extends Controller
{
    /**
     * Calculate the correct price per individual product from order item
     * For paket products, this divides the package price by total package quantity
     * 
     * @param OrderItem $orderItem
     * @return float
     */
    private function calculateIndividualProductPrice($orderItem)
    {
        if (!$orderItem) {
            return 0;
        }
        
        $platformProduct = $orderItem->platformProduct;
        if (!$platformProduct) {
            return $orderItem->price_after_discount;
        }
        
        // Calculate total quantity in the package using correct version logic
        $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
        $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
        $totalPackageQty = $mappings->sum('quantity');
        
        // Calculate price per individual product
        return $totalPackageQty > 0 ? 
            $orderItem->price_after_discount / $totalPackageQty : 
            $orderItem->price_after_discount;
    }

    /**
     * Display a listing of the retur penjualan.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ReturPenjualan::with(['order', 'order.orderItems', 'user', 'details.product', 'details.orderItem.platformProduct.mappingBarang']);

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('kode_retur', 'like', '%' . $search . '%')
                  ->orWhereHas('order', function($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'like', '%' . $search . '%')
                                ->orWhereHas('orderItems', function($itemQuery) use ($search) {
                                    $itemQuery->where('tracking_number', 'like', '%' . $search . '%');
                                });
                  })
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Platform filter
        if ($request->filled('platform_id')) {
            $query->whereHas('order', function($orderQuery) use ($request) {
                $orderQuery->where('platform_id', $request->platform_id);
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_retur', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_retur', '<=', $request->date_to);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $returPenjualans = $query->orderBy('created_at', 'desc')->paginate(10);

        // Get filter options
        $platforms = \App\Models\Platform::orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('retur.penjualan.index', compact('returPenjualans', 'platforms', 'users'));
    }

    /**
     * Show the form for creating a new retur penjualan.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $orderQuery = Order::withoutGlobalScope('mainCategory')
            ->with(['platform'])
            ->whereHas('orderItems')
            ->where('tanggal', '>=', now()->subMonths(3));

        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $orderQuery->where(function ($query) use ($mainCategoryId) {
                $query->whereHas('orderItems.warehouseStock.product', function ($q) use ($mainCategoryId) {
                    $q->where('main_category_id', $mainCategoryId);
                })->orWhereDoesntHave('orderItems.warehouseStock');
            });
        }

        $orderList = $orderQuery
            ->orderBy('tanggal', 'desc')
            ->limit(1000)
            ->get();

        return view('retur.penjualan.create', compact('orderList'));
    }

    public function searchOrders(Request $request)
    {
        $term = trim((string) $request->get('q', $request->get('term', '')));

        $query = Order::withoutGlobalScope('mainCategory')
            ->with(['platform'])
            ->whereHas('orderItems');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', '%' . $term . '%')
                    ->orWhereHas('orderItems', function ($itemQuery) use ($term) {
                        $itemQuery->where('tracking_number', 'like', '%' . $term . '%');
                    });
            });
        }

        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->where(function ($query) use ($mainCategoryId) {
                $query->whereHas('orderItems.warehouseStock.product', function ($q) use ($mainCategoryId) {
                    $q->where('main_category_id', $mainCategoryId);
                })->orWhereDoesntHave('orderItems.warehouseStock');
            });
        }

        $orders = $query
            ->orderBy('tanggal', 'desc')
            ->limit(20)
            ->get();

        $results = $orders->map(function ($order) {
            $platformName = $order->platform->name ?? 'Tidak ada platform';
            $orderNumber = $order->order_number ?: 'Tanpa No Order';
            $tanggal = $order->tanggal ? $order->tanggal->format('d/m/Y') : null;

            $text = $tanggal ? ($orderNumber . ' - ' . $platformName . ' - ' . $tanggal) : ($orderNumber . ' - ' . $platformName);

            return [
                'id' => $order->id,
                'text' => $text,
            ];
        })->values();

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => false],
        ]);
    }

    /**
     * Get a specific order with its details for the retur form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOrder($id)
    {
        try {
            // Get the order with eager loading including barang keluar
            $order = Order::withoutGlobalScope('mainCategory')->with([
                'orderItems' => function($query) {
                    $query->with([
                        'barangKeluar' => function($q) {
                            $q->with(['warehouseStock.product']);
                        },
                        'platformProduct' => function($q) {
                            $q->with(['mappingBarang' => function($mq) {
                                $mq->with('product');
                            }]);
                        }
                    ]);
                },
                'platform'
            ])->findOrFail($id);
            
            // Keep the order items simple - barang keluar data will be used in background during processing
            // No need to transform the display data
            
            // Log the order data for debugging
            \Log::info('Order for retur: Order ' . $id . ' has ' . $order->orderItems->count() . ' items');
            \Log::info('Order date: ' . $order->tanggal);
            
            return response()->json($order);
        } catch (\Exception $e) {
            \Log::error('Error loading order for retur: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Gagal memuat data order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created retur penjualan in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \Log::info('ReturPenjualan store method called with data: ' . json_encode($request->all()));
        
        try {
            \Log::info('Starting validation');
            $validated = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'tanggal_retur' => 'required|date',
                'catatan' => 'nullable|string',
                'details' => 'required|array',
                'details.*.order_item_id' => 'required|exists:order_items,id',
                'details.*.qty' => 'required|numeric|min:0',
                'details.*.kondisi' => 'required|string|in:BAGUS,RUSAK,HILANG',
                'details.*.alasan' => 'nullable|string',
            ]);
            \Log::info('Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed: ' . json_encode($e->errors()));
            throw $e;
        }

        try {
            DB::beginTransaction();
            \Log::info('Starting transaction for retur penjualan');

            // Deduplicate details by order_item_id + product_id + kondisi
            $uniqueDetails = [];
            foreach ($request->details as $detail) {
                if (empty($detail['qty']) || floatval($detail['qty']) <= 0) {
                    continue;
                }
                $key = $detail['order_item_id'] . '_' . ($detail['product_id'] ?? '') . '_' . $detail['kondisi'];
                if (isset($uniqueDetails[$key])) {
                    $uniqueDetails[$key]['qty'] = (float) $uniqueDetails[$key]['qty'] + (float) $detail['qty'];
                } else {
                    $uniqueDetails[$key] = $detail;
                }
            }
            $request->merge(['details' => array_values($uniqueDetails)]);

            // Create the retur penjualan header
            $returPenjualan = ReturPenjualan::create([
                'kode_retur' => ReturPenjualan::generateKodeRetur(),
                'order_id' => $request->order_id,
                'user_id' => Auth::id() ?? null,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'status' => 'selesai',
            ]);
            
            \Log::info('Created retur penjualan header with ID: ' . $returPenjualan->id);

            // Process each detail item
            foreach ($request->details as $index => $detail) {
                \Log::info('Processing detail item with index: ' . $index . ': ' . json_encode($detail));
                
                // Skip if qty is zero or null
                if (empty($detail['qty']) || floatval($detail['qty']) <= 0) {
                    \Log::info('Skipping item with zero or negative qty');
                    continue;
                }

                // Get the order item
                $orderItem = OrderItem::with(['platformProduct.mappingBarang.product'])->findOrFail($detail['order_item_id']);
                \Log::info('Found order item: ' . $orderItem->id . ' with quantity: ' . $orderItem->quantity);
                
                // Validate return quantity doesn't exceed original quantity
                if (floatval($detail['qty']) > $orderItem->quantity) {
                    \Log::warning('Return quantity exceeds original quantity for item ID ' . $orderItem->id);
                    throw new \Exception("Jumlah retur untuk item ID {$orderItem->id} melebihi jumlah asli");
                }

                // Get barang keluar records for this order item
                $barangKeluarItems = $orderItem->barangKeluar()->with('warehouseStock.product')->get();
                if ($barangKeluarItems->isEmpty()) {
                    throw new \Exception("Order item {$orderItem->id} tidak memiliki barang keluar yang dapat diretur");
                }

                // PERBAIKAN: Validasi dan distribusi qty retur sesuai dengan qty yang diminta user
                // Pastikan tidak ada duplikasi item dan qty sesuai dengan yang diretur
                $returnQty = floatval($detail['qty']); // Qty yang diinput user (dalam satuan paket)
                
                // PERBAIKAN: Konversi qty paket ke qty individual jika ada mapping barang
                // Untuk produk dengan mapping barang (1 paket = X pcs), qty retur harus dikonversi ke individual
                $platformProduct = $orderItem->platformProduct;
                $packageQuantity = 1; // Default untuk produk tanpa mapping
                
                if ($platformProduct) {
                    // Gunakan logika yang benar: cari version yang dibuat sebelum/sama dengan tanggal order dibuat
                    $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                    $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                    if ($mappings->count() > 0) {
                        $packageQuantity = $mappings->sum('quantity');
                    }
                }
                
                // Konversi qty retur dari paket ke individual
                // Jika retur 1 paket yang isinya 12 pcs, maka returnQtyIndividual = 1 * 12 = 12 pcs
                $returnQtyIndividual = $returnQty * $packageQuantity;
                
                // PERBAIKAN: Update order item quantity dengan konversi yang benar
                // $returnQty sudah dalam satuan paket, jadi langsung kurangi dari order item quantity
                // Order item quantity juga dalam satuan paket
                // PERBAIKAN: Gunakan round() untuk menghindari masalah presisi floating point
                $newQuantity = round($orderItem->quantity - $returnQty, 4);
                $orderItem->update([
                    'quantity' => $newQuantity
                ]);
                \Log::info('Updated order item quantity from ' . ($orderItem->quantity + $returnQty) . ' to ' . $orderItem->quantity . ' (reduced by ' . $returnQty . ' package)');
                
                \Log::info('Retur qty paket: ' . $returnQty . ', Package quantity: ' . $packageQuantity . ', Return qty individual: ' . $returnQtyIndividual);
                
                // Hitung total qty dari semua barang keluar (dalam satuan individual)
                $totalBarangKeluarQty = $barangKeluarItems->sum('qty');
                \Log::info('Total barang keluar qty (individual): ' . $totalBarangKeluarQty . ', Return qty requested (individual): ' . $returnQtyIndividual);
                
                // Validasi: total qty barang keluar harus >= qty retur individual
                if ($totalBarangKeluarQty < $returnQtyIndividual) {
                    throw new \Exception("Total qty barang keluar ({$totalBarangKeluarQty}) kurang dari qty retur yang diminta ({$returnQtyIndividual} individual = {$returnQty} paket) untuk order item ID {$orderItem->id}");
                }
                
                // Kelompokkan barang keluar berdasarkan product_id untuk menghindari duplikasi
                $barangKeluarByProduct = [];
                foreach ($barangKeluarItems as $barangKeluar) {
                    $productId = $barangKeluar->warehouseStock->product_id;
                    if (!isset($barangKeluarByProduct[$productId])) {
                        $barangKeluarByProduct[$productId] = [];
                    }
                    $barangKeluarByProduct[$productId][] = $barangKeluar;
                }
                
                // Urutkan barang keluar berdasarkan tanggal keluar (FIFO) untuk setiap product
                foreach ($barangKeluarByProduct as $productId => $items) {
                    usort($barangKeluarByProduct[$productId], function($a, $b) {
                        return $a->tanggal_keluar <=> $b->tanggal_keluar;
                    });
                }
                
                // PERBAIKAN: Gunakan mapping barang untuk menentukan qty individual yang dikembalikan
                // Proses setiap mapping barang, bukan distribusi proporsional
                // Ambil mapping barang berdasarkan version yang dibuat sebelum/sama dengan tanggal order dibuat
                $mappings = collect();
                if ($platformProduct) {
                    // Gunakan logika yang benar: cari version yang dibuat sebelum/sama dengan tanggal order dibuat
                    $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                    $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                }
                
                // Jika tidak ada mapping, cek jumlah item dalam order
                // Jika order hanya 1 item, boleh fallback ke barang keluar
                // Jika order lebih dari 1 item, tidak boleh fallback (harus ada mapping)
                if ($mappings->isEmpty()) {
                    $orderItemsCount = $orderItem->order->orderItems->count() ?? 0;
                    
                    if ($orderItemsCount == 1 && !$barangKeluarItems->isEmpty()) {
                        // Order hanya 1 item, boleh fallback ke barang keluar
                        $firstProductId = $barangKeluarItems->first()->warehouseStock->product_id;
                        $mappings = collect([(object)[
                            'product_id' => $firstProductId,
                            'quantity' => 1
                        ]]);
                        \Log::info('Fallback to barang keluar for single-item order, product ID: ' . $firstProductId);
                    } else {
                        // Order lebih dari 1 item, tidak boleh fallback
                        throw new \Exception("Mapping tidak ditemukan untuk platform product ID {$platformProduct->id} pada order item ID {$orderItem->id}. Untuk order dengan lebih dari 1 item, mapping wajib ada.");
                    }
                }
                
                \Log::info('Retur qty paket: ' . $returnQty . ', Mappings count: ' . $mappings->count());
                
                // Proses setiap mapping barang
                // Untuk setiap product di mapping, hitung qty individual yang harus dikembalikan
                foreach ($mappings as $mapping) {
                    $productId = $mapping->product_id;
                    if (!$productId) continue;
                    
                    $mappingQty = (float) $mapping->quantity;
                    
                    // Hitung qty individual yang harus dikembalikan untuk product ini
                    // Jika retur 1 paket dengan mapping 12, maka kembalikan 12 item
                    // Jika retur 2 paket dengan mapping 12, maka kembalikan 24 item
                    // Jika retur 2 paket dengan mapping 1, maka kembalikan 2 item
                    $qtyForThisProduct = $returnQty * $mappingQty;
                    
                    \Log::info('Product ID: ' . $productId . ', Mapping qty: ' . $mappingQty . ', Qty to return: ' . $qtyForThisProduct);
                    
                    // Cek apakah ada barang keluar untuk product ini
                    if (!isset($barangKeluarByProduct[$productId]) || empty($barangKeluarByProduct[$productId])) {
                        \Log::warning('No barang keluar found for product ID: ' . $productId);
                        continue;
                    }
                    
                    $barangKeluarList = $barangKeluarByProduct[$productId];
                    $totalProductQty = collect($barangKeluarList)->sum('qty');
                    
                    // Validasi: total qty barang keluar harus >= qty yang akan dikembalikan
                    if ($totalProductQty < $qtyForThisProduct) {
                        throw new \Exception("Total qty barang keluar untuk product ID {$productId} ({$totalProductQty}) kurang dari qty retur yang diminta ({$qtyForThisProduct} individual = {$returnQty} paket x {$mappingQty} mapping) untuk order item ID {$orderItem->id}");
                    }
                    
                    if ($qtyForThisProduct > 0) {
                        // Distribusikan qty ke barang keluar untuk product ini secara FIFO
                        $remainingProductQty = $qtyForThisProduct;
                        $totalStockQty = 0;
                        $taxId = null;
                        
                        foreach ($barangKeluarList as $barangKeluar) {
                            if ($remainingProductQty <= 0) {
                                break;
                            }
                            
                            $qtyFromThisBarangKeluar = min($remainingProductQty, $barangKeluar->qty);
                            $remainingProductQty -= $qtyFromThisBarangKeluar;
                            $totalStockQty += $qtyFromThisBarangKeluar;
                            
                            // Ambil tax_id dari barang keluar pertama (untuk konsistensi)
                            if ($taxId === null) {
                                $taxId = $barangKeluar->warehouseStock->tax_id;
                            }
                            
                            // Tambahkan ke stock dengan qty yang tepat
                            if ($detail['kondisi'] === 'BAGUS') {
                                $this->addBackToStock($productId, $qtyFromThisBarangKeluar, false, $request->tanggal_retur, $returPenjualan->id, $taxId);
                            } else if ($detail['kondisi'] === 'RUSAK') {
                                $this->addBackToStock($productId, $qtyFromThisBarangKeluar, true, $request->tanggal_retur, $returPenjualan->id, $taxId);
                            }
                        }
                        
                        // Cek apakah sudah ada retur detail untuk product_id ini
                        $existingDetail = ReturPenjualanDetail::where('retur_penjualan_id', $returPenjualan->id)
                            ->where('order_item_id', $detail['order_item_id'])
                            ->where('product_id', $productId)
                            ->first();
                        
                        if ($existingDetail) {
                            // Jika sudah ada, update qty-nya (seharusnya tidak terjadi jika logika benar)
                            $existingDetail->qty += $totalStockQty;
                            $existingDetail->save();
                            \Log::warning('Updated existing retur detail ID: ' . $existingDetail->id . ' for product ID: ' . $productId . ', added qty: ' . $totalStockQty . ', total qty: ' . $existingDetail->qty);
                        } else {
                            // Buat retur detail baru untuk product ini (hanya satu record per product)
                            $returDetail = ReturPenjualanDetail::create([
                                'retur_penjualan_id' => $returPenjualan->id,
                                'order_item_id' => $detail['order_item_id'],
                                'product_id' => $productId,
                                'qty' => $totalStockQty,
                                'kondisi' => $detail['kondisi'],
                                'alasan' => $detail['alasan'] ?? null,
                            ]);
                            \Log::info('Created retur detail with ID: ' . $returDetail->id . ' for product ID: ' . $productId . ' with qty: ' . $totalStockQty);
                        }
                    }
                }
                
                \Log::info('Completed processing retur for order item ID: ' . $orderItem->id . ', requested qty (package): ' . $returnQty);
            }

            // Handle finance logic for return (Calculate refund and update financial transaction)
            // This ensures that partial returns automatically update the financial records
            try {
                $financeService = new ReturFinanceService();
                $financeService->handleOnlineReturFinance($returPenjualan);
                \Log::info('Finance processed for retur penjualan ID: ' . $returPenjualan->id);
            } catch (\Exception $e) {
                // Log error but don't fail the whole transaction? 
                // Ideally finance error should roll back everything to ensure consistency.
                // So we throw to catch block below.
                throw $e;
            }

            DB::commit();
            \Log::info('Transaction committed successfully');

            return redirect()->route('retur-penjualan.show', $returPenjualan->id)
                ->with('success', 'Retur penjualan berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in retur penjualan store: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified retur penjualan.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $returPenjualan = ReturPenjualan::with(['order', 'user', 'details.product', 'details.orderItem.platformProduct.mappingBarang'])
            ->findOrFail($id);

        return view('retur.penjualan.show', compact('returPenjualan'));
    }

    /**
     * Show the form for editing the specified retur penjualan.
     * Only allowed if status is 'draft'
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $returPenjualan = ReturPenjualan::with(['order', 'details.product'])
            ->findOrFail($id);

        // Only draft status can be edited
        if ($returPenjualan->status !== 'draft') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diedit.');
        }

        $orderList = Order::with(['platform'])
            ->whereHas('orderItems')
            ->get();

        return view('retur.penjualan.edit', compact('returPenjualan', 'orderList'));
    }

    /**
     * Update the specified retur penjualan in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $returPenjualan = ReturPenjualan::findOrFail($id);

        // Only draft status can be updated
        if ($returPenjualan->status !== 'draft') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diupdate.');
        }

        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'tanggal_retur' => 'required|date',
            'catatan' => 'nullable|string',
            'details' => 'required|array',
            'details.*.order_item_id' => 'required|exists:order_items,id',
            'details.*.qty' => 'required|numeric|min:0',
            'details.*.kondisi' => 'required|string|in:BAGUS,RUSAK,HILANG',
            'details.*.alasan' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Update the retur penjualan
            $returPenjualan->update([
                'order_id' => $request->order_id,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
            ]);

            // Delete existing details
            $returPenjualan->details()->delete();

            // Create new details
            foreach ($request->details as $detail) {
                // Skip if qty is zero or null
                if (empty($detail['qty']) || floatval($detail['qty']) <= 0) {
                    continue;
                }

                // Get the order item with barang keluar
                $orderItem = OrderItem::with(['barangKeluar.warehouseStock.product', 'platformProduct.mappingBarang'])->findOrFail($detail['order_item_id']);
                
                // Get barang keluar records for this order item
                $barangKeluarItems = $orderItem->barangKeluar;
                if ($barangKeluarItems->isEmpty()) {
                    throw new \Exception("Order item {$orderItem->id} tidak memiliki barang keluar yang dapat diretur");
                }

                // PERBAIKAN: Validasi dan distribusi qty retur sesuai dengan qty yang diminta user
                // Pastikan tidak ada duplikasi item dan qty sesuai dengan yang diretur
                $returnQty = floatval($detail['qty']);
                
                // PERBAIKAN: Gunakan mapping barang untuk menentukan qty individual yang dikembalikan
                // Retur qty dalam satuan paket/platform product, kembalikan ke stok sesuai mapping
                $platformProduct = $orderItem->platformProduct;
                
                // Ambil mapping barang berdasarkan version yang dibuat sebelum/sama dengan tanggal order dibuat
                $mappings = collect();
                if ($platformProduct) {
                    // Gunakan logika yang benar: cari version yang dibuat sebelum/sama dengan tanggal order dibuat
                    $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                    $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                }
                
                // Jika tidak ada mapping, cek jumlah item dalam order
                // Jika order hanya 1 item, boleh fallback ke barang keluar
                // Jika order lebih dari 1 item, tidak boleh fallback (harus ada mapping)
                if ($mappings->isEmpty()) {
                    $orderItemsCount = $orderItem->order->orderItems->count() ?? 0;
                    
                    if ($orderItemsCount == 1 && !$barangKeluarItems->isEmpty()) {
                        // Order hanya 1 item, boleh fallback ke barang keluar
                        $firstProductId = $barangKeluarItems->first()->warehouseStock->product_id;
                        $mappings = collect([(object)[
                            'product_id' => $firstProductId,
                            'quantity' => 1
                        ]]);
                        \Log::info('Fallback to barang keluar for single-item order, product ID: ' . $firstProductId);
                    } else {
                        // Order lebih dari 1 item, tidak boleh fallback
                        throw new \Exception("Mapping tidak ditemukan untuk platform product ID {$platformProduct->id} pada order item ID {$orderItem->id}. Untuk order dengan lebih dari 1 item, mapping wajib ada.");
                    }
                }
                
                \Log::info('Update - Retur qty paket: ' . $returnQty . ', Mappings count: ' . $mappings->count());
                
                // Kelompokkan barang keluar berdasarkan product_id untuk distribusi FIFO
                $barangKeluarByProduct = [];
                foreach ($barangKeluarItems as $barangKeluar) {
                    $productId = $barangKeluar->warehouseStock->product_id;
                    if (!isset($barangKeluarByProduct[$productId])) {
                        $barangKeluarByProduct[$productId] = [];
                    }
                    $barangKeluarByProduct[$productId][] = $barangKeluar;
                }
                
                // Urutkan barang keluar berdasarkan tanggal keluar (FIFO) untuk setiap product
                foreach ($barangKeluarByProduct as $productId => $items) {
                    usort($barangKeluarByProduct[$productId], function($a, $b) {
                        return $a->tanggal_keluar <=> $b->tanggal_keluar;
                    });
                }
                
                // Proses setiap mapping barang
                // Untuk setiap product di mapping, hitung qty individual yang harus dikembalikan
                foreach ($mappings as $mapping) {
                    $productId = $mapping->product_id;
                    if (!$productId) continue;
                    
                    $mappingQty = (float) $mapping->quantity;
                    
                    // Hitung qty individual yang harus dikembalikan untuk product ini
                    // Jika retur 1 paket dengan mapping 12, maka kembalikan 12 item
                    // Jika retur 2 paket dengan mapping 12, maka kembalikan 24 item
                    // Jika retur 2 paket dengan mapping 1, maka kembalikan 2 item
                    $qtyToReturn = $returnQty * $mappingQty;
                    
                    \Log::info('Update - Product ID: ' . $productId . ', Mapping qty: ' . $mappingQty . ', Qty to return: ' . $qtyToReturn);
                    
                    // Cek apakah ada barang keluar untuk product ini
                    if (!isset($barangKeluarByProduct[$productId]) || empty($barangKeluarByProduct[$productId])) {
                        \Log::warning('Update - No barang keluar found for product ID: ' . $productId);
                        continue;
                    }
                    
                    $barangKeluarList = $barangKeluarByProduct[$productId];
                    $totalProductQty = collect($barangKeluarList)->sum('qty');
                    
                    // Validasi: total qty barang keluar harus >= qty yang akan dikembalikan
                    if ($totalProductQty < $qtyToReturn) {
                        throw new \Exception("Total qty barang keluar untuk product ID {$productId} ({$totalProductQty}) kurang dari qty retur yang diminta ({$qtyToReturn} individual = {$returnQty} paket x {$mappingQty} mapping) untuk order item ID {$orderItem->id}");
                    }
                    
                    // Distribusikan qty ke barang keluar untuk product ini secara FIFO
                    $remainingProductQty = $qtyToReturn;
                    $totalReturQty = 0;
                    $taxId = null;
                    
                    foreach ($barangKeluarList as $barangKeluar) {
                        if ($remainingProductQty <= 0) {
                            break;
                        }
                        
                        $qtyFromThisBarangKeluar = min($remainingProductQty, $barangKeluar->qty);
                        $remainingProductQty -= $qtyFromThisBarangKeluar;
                        $totalReturQty += $qtyFromThisBarangKeluar;
                        
                        // Ambil tax_id dari barang keluar pertama (untuk konsistensi)
                        if ($taxId === null) {
                            $taxId = $barangKeluar->warehouseStock->tax_id;
                        }
                        
                        // Tambahkan ke stock dengan qty yang tepat
                        if ($detail['kondisi'] === 'BAGUS') {
                            $this->addBackToStock($productId, $qtyFromThisBarangKeluar, false, $request->tanggal_retur, $returPenjualan->id, $taxId);
                        } else if ($detail['kondisi'] === 'RUSAK') {
                            $this->addBackToStock($productId, $qtyFromThisBarangKeluar, true, $request->tanggal_retur, $returPenjualan->id, $taxId);
                        }
                    }
                    
                    // Buat retur detail baru untuk product ini (hanya satu record per product)
                    ReturPenjualanDetail::create([
                        'retur_penjualan_id' => $returPenjualan->id,
                        'order_item_id' => $detail['order_item_id'],
                        'product_id' => $productId,
                        'qty' => $totalReturQty,
                        'kondisi' => $detail['kondisi'],
                        'alasan' => $detail['alasan'] ?? null,
                    ]);
                    \Log::info('Update - Created retur detail for product ID: ' . $productId . ' with qty: ' . $totalReturQty);
                }
                
                \Log::info('Update - Completed processing retur for order item ID: ' . $orderItem->id . ', requested qty (package): ' . $returnQty);
            }

            DB::commit();

            return redirect()->route('retur-penjualan.show', $returPenjualan->id)
                ->with('success', 'Retur penjualan berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Process a draft retur penjualan, apply stock changes.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function process($id)
    {
        $returPenjualan = ReturPenjualan::with('details')->findOrFail($id);

        // Only draft status can be processed
        if ($returPenjualan->status !== 'draft') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diproses.');
        }

        try {
            DB::beginTransaction();
            \Log::info('Processing retur penjualan ID: ' . $id);

            // Process each detail item
            foreach ($returPenjualan->details as $detail) {
                // Get the order item with barang keluar untuk mendapatkan tax_id asli
                $orderItem = OrderItem::with(['barangKeluar.warehouseStock', 'platformProduct.mappingBarang'])->findOrFail($detail->order_item_id);
                \Log::info('Processing order item ID: ' . $orderItem->id);
                
                // PERBAIKAN: Konversi qty detail (individual) ke qty paket untuk mengurangi order item quantity
                // $detail->qty sudah dalam satuan individual (pcs), sedangkan $orderItem->quantity dalam satuan paket
                $platformProduct = $orderItem->platformProduct;
                $packageQuantity = 1; // Default untuk produk tanpa mapping
                
                if ($platformProduct) {
                    // Gunakan logika yang benar: cari version yang dibuat sebelum/sama dengan tanggal order dibuat
                    $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                    $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                    if ($mappings->count() > 0) {
                        $packageQuantity = $mappings->sum('quantity');
                    }
                }
                
                // Konversi qty individual ke qty paket
                // PERBAIKAN: Gunakan round() untuk menghindari masalah presisi floating point
                $returnQtyPackage = $packageQuantity > 0 ? round(floatval($detail->qty) / $packageQuantity, 4) : floatval($detail->qty);
                
                \Log::info('Retur detail qty (individual): ' . $detail->qty . ', Package quantity: ' . $packageQuantity . ', Return qty (package): ' . $returnQtyPackage);
                
                // Validate return quantity doesn't exceed original quantity (dalam satuan paket)
                if ($returnQtyPackage > $orderItem->quantity) {
                    throw new \Exception("Jumlah retur untuk item ID {$orderItem->id} melebihi jumlah asli");
                }
                
                // Update the order item quantity (dalam satuan paket)
                // PERBAIKAN: Gunakan round() untuk memastikan presisi yang benar
                $newQuantity = round($orderItem->quantity - $returnQtyPackage, 4);
                $orderItem->update([
                    'quantity' => $newQuantity
                ]);
                
                // Handle stock based on condition
                // PERBAIKAN: Untuk process(), kita perlu mendapatkan tax_id dari barang keluar asli
                // Karena detail sudah dibuat, kita perlu trace back ke barang keluar
                $barangKeluar = $orderItem->barangKeluar()->with('warehouseStock')->first();
                $originalTaxId = $barangKeluar && $barangKeluar->warehouseStock ? $barangKeluar->warehouseStock->tax_id : null;
                \Log::info('Process retur - Original tax_id from barang keluar: ' . ($originalTaxId ?? 'null') . ' for product ID: ' . $detail->product_id);
                
                if ($detail->kondisi === 'BAGUS') {
                    $this->addBackToStock($detail->product_id, floatval($detail->qty), false, $returPenjualan->tanggal_retur->format('Y-m-d'), $returPenjualan->id, $originalTaxId);
                } else if ($detail->kondisi === 'RUSAK') {
                    $this->addBackToStock($detail->product_id, floatval($detail->qty), true, $returPenjualan->tanggal_retur->format('Y-m-d'), $returPenjualan->id, $originalTaxId);
                } else {
                    \Log::info('Product marked as HILANG, not adding to stock: product ID ' . $detail->product_id);
                }
            }

            // Update status to 'selesai'
            $returPenjualan->update([
                'status' => 'selesai',
                'user_id' => Auth::id() ?? null,
            ]);

            // Handle finance logic for return
            $financeService = new ReturFinanceService();
            $financeService->handleOnlineReturFinance($returPenjualan);

            DB::commit();
            \Log::info('Successfully processed retur penjualan ID: ' . $id);

            return redirect()->route('retur-penjualan.show', $id)
                ->with('success', 'Retur penjualan berhasil diproses.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error processing retur penjualan: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a retur penjualan
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $returPenjualan = ReturPenjualan::findOrFail($id);

        // Only draft status can be cancelled
        if ($returPenjualan->status !== 'draft') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat dibatalkan.');
        }

        $returPenjualan->update([
            'status' => 'dibatalkan',
        ]);

        return redirect()->route('retur-penjualan.show', $id)
            ->with('success', 'Retur penjualan berhasil dibatalkan.');
    }

    /**
     * Reverse/Cancel a completed retur penjualan (batal retur)
     * This will fully restore the state as if the return never happened
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reverseReturn($id)
    {
        $returPenjualan = ReturPenjualan::with('details')->findOrFail($id);

        // Only completed returns can be reversed
        if ($returPenjualan->status !== 'selesai') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status selesai yang dapat dibatalkan.');
        }

        try {
            DB::beginTransaction();
            \Log::info('Reversing completed retur penjualan ID: ' . $id);

            // PERBAIKAN: Kelompokkan detail retur berdasarkan order_item_id untuk menghindari error presisi
            // Karena detail retur disimpan per product, tapi semua detail untuk order item yang sama
            // seharusnya dihitung sebagai 1 paket yang diretur
            $detailsByOrderItem = $returPenjualan->details->groupBy('order_item_id');
            
            foreach ($detailsByOrderItem as $orderItemId => $details) {
                // Get the order item with platform product to get mapping
                $orderItem = OrderItem::with(['platformProduct.mappingBarang'])->findOrFail($orderItemId);
                
                // Hitung total qty individual yang diretur untuk order item ini
                $totalIndividualQty = $details->sum('qty');
                
                // PERBAIKAN: Konversi total qty individual ke qty paket untuk restore order item quantity
                // $totalIndividualQty sudah dalam satuan individual (pcs), sedangkan $orderItem->quantity dalam satuan paket
                $platformProduct = $orderItem->platformProduct;
                $packageQuantity = 1; // Default untuk produk tanpa mapping
                
                if ($platformProduct) {
                    // Gunakan logika yang benar: cari version yang dibuat sebelum/sama dengan tanggal order dibuat
                    $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                    $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                    if ($mappings->count() > 0) {
                        $packageQuantity = $mappings->sum('quantity');
                    }
                }
                
                // Konversi total qty individual ke qty paket
                // Jika totalIndividualQty = 12 individual dan packageQuantity = 12, maka returnQtyPackage = 1 paket
                // PERBAIKAN: Gunakan round() untuk menghindari masalah presisi floating point
                $returnQtyPackage = $packageQuantity > 0 ? round($totalIndividualQty / $packageQuantity, 4) : $totalIndividualQty;
                
                \Log::info('Restoring order item ID: ' . $orderItem->id . 
                    ' - Total detail qty (individual): ' . $totalIndividualQty . 
                    ', Package quantity: ' . $packageQuantity . 
                    ', Return qty (package): ' . $returnQtyPackage . 
                    ', Current order item qty: ' . $orderItem->quantity);
                
                // Restore the order item quantity (add back the returned quantity in package units)
                // PERBAIKAN: Gunakan round() untuk memastikan presisi yang benar
                $newQuantity = round($orderItem->quantity + $returnQtyPackage, 4);
                $orderItem->update([
                    'quantity' => $newQuantity
                ]);
                
                \Log::info('Order item ID: ' . $orderItem->id . ' restored, new qty: ' . $orderItem->quantity);
                
                // Remove stock that was added during return (only for BAGUS and RUSAK conditions)
                // PERBAIKAN: Hapus warehouse stock entries yang dibuat saat retur, bukan hanya kurangi qty
                foreach ($details as $detail) {
                    if ($detail->kondisi === 'BAGUS') {
                        $this->removeReturnedStock($detail->product_id, floatval($detail->qty), false, $returPenjualan->id);
                    } else if ($detail->kondisi === 'RUSAK') {
                        $this->removeReturnedStock($detail->product_id, floatval($detail->qty), true, $returPenjualan->id);
                    }
                    // For HILANG condition, no stock was added, so nothing to remove
                }
            }

            // Restore finance transaction (kebalikan dari handleOnlineReturFinance)
            // Dipanggil setelah qty & stock direstore agar nilai dihitung dari quantity asli
            $financeService = new ReturFinanceService();
            $financeService->restoreOnlineReturFinance($returPenjualan);

            // Update status to 'dibatalkan'
            $returPenjualan->update([
                'status' => 'dibatalkan',
                'user_id' => Auth::id() ?? null,
            ]);

            DB::commit();
            \Log::info('Successfully reversed retur penjualan ID: ' . $id);

            return redirect()->route('retur-penjualan.show', $id)
                ->with('success', 'Retur penjualan berhasil dibatalkan. Semua perubahan telah dikembalikan ke kondisi semula.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error reversing retur penjualan: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Add stock back to warehouse
     * 
     * @param  int  $productId
     * @param  float  $qty
     * @param  bool  $isDamaged
     * @param  string|null  $returDate
     * @param  int|null  $returPenjualanId
     * @param  int|null  $originalTaxId Tax ID dari barang keluar asli (untuk mempertahankan tax_id yang benar)
     * @return void
     */
    private function addBackToStock($productId, $qty, $isDamaged = false, $returDate = null, $returPenjualanId = null, $originalTaxId = null)
    {
        \Log::info("addBackToStock called for product ID: {$productId}, qty: {$qty}, isDamaged: " . ($isDamaged ? 'yes' : 'no'));
        
        try {
            // Ensure qty is a float
            $qty = floatval($qty);
            
            // Check if the product exists
            $product = Product::where('products.id', $productId)->first();
            if (!$product) {
                throw new \Exception("Product with ID {$productId} not found");
            }
            
            // PERBAIKAN: Prioritaskan menggunakan tax_id dari barang keluar asli
            // Jika originalTaxId tersedia, gunakan itu untuk mempertahankan tax_id yang benar
            $taxId = $originalTaxId;
            
            // Find a suitable warehouse stock to reference for ED dan atribut lainnya
            // Prioritas: cari stock dengan tax_id yang sama dengan originalTaxId (jika ada)
            $referenceStock = null;
            
            if ($originalTaxId) {
                // Cari stock dengan tax_id yang sama dengan originalTaxId
                $referenceStock = WarehouseStock::where('product_id', $productId)
                    ->where('is_damaged', $isDamaged)
                    ->where('tax_id', $originalTaxId)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->first();
            }
            
            // Jika tidak ditemukan dengan tax_id yang sama, cari stock apapun dengan product dan damage status yang sama
            if (!$referenceStock) {
                $referenceStock = WarehouseStock::where('product_id', $productId)
                    ->where('is_damaged', $isDamaged)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('tax_id', 'asc')
                    ->first();
            }
            
            // Jika masih tidak ditemukan, cari stock apapun untuk product ini
            if (!$referenceStock) {
                $referenceStock = WarehouseStock::where('product_id', $productId)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('tax_id', 'asc')
                    ->first();
            }
            
            // Set default values
            $penerimaanDetailId = null;
            $expiredDate = null;
            $statusEd = 'aman';
            
            if ($referenceStock) {
                // Use reference stock's attributes untuk ED dan penerimaan_detail_id
                // TAPI tetap gunakan originalTaxId jika tersedia
                $penerimaanDetailId = $referenceStock->penerimaan_detail_id;
                if (!$taxId) {
                    $taxId = $referenceStock->tax_id; // Fallback jika originalTaxId tidak tersedia
                }
                $expiredDate = $referenceStock->expired_date;
                $statusEd = $referenceStock->status_ed;
                
                \Log::info("Using reference stock ID: {$referenceStock->id} for ED and other attributes", [
                    'penerimaan_detail_id' => $penerimaanDetailId,
                    'tax_id' => $taxId,
                    'original_tax_id' => $originalTaxId,
                    'expired_date' => $expiredDate,
                    'status_ed' => $statusEd
                ]);
            } else {
                // Fallback: find any penerimaan_detail for this product
                $penerimaanDetail = PenerimaanDetail::where('penerimaan_detail.product_id', $productId)
                    ->with('penerimaan')
                    ->orderBy('penerimaan_detail.id', 'desc')
                    ->first();
                    
                if (!$penerimaanDetail) {
                    \Log::warning("No penerimaan_detail found for product ID: {$productId}, using a fallback");
                    
                    // If no penerimaan_detail exists for this product, find any penerimaan_detail
                    // This is a fallback solution to prevent database errors
                    $anyPenerimaanDetail = PenerimaanDetail::with('penerimaan')->orderBy('id', 'desc')->first();
                    
                    if (!$anyPenerimaanDetail) {
                        throw new \Exception("No penerimaan_detail records found in the system. Cannot process return.");
                    }
                    
                    $penerimaanDetailId = $anyPenerimaanDetail->id;
                    \Log::info("Using fallback penerimaan_detail_id: {$penerimaanDetailId}");
                } else {
                    $penerimaanDetailId = $penerimaanDetail->id;
                    \Log::info("Found penerimaan_detail_id: {$penerimaanDetailId} for product");
                }
            }
            
            // PERBAIKAN: Jika tax_id masih null, ambil dari penerimaan_detail->penerimaan->tax_category_id
            // Ini memastikan warehouse stock selalu punya tax_id karena selalu ada penerimaan_detail_id
            if (!$taxId && $penerimaanDetailId) {
                $penerimaanDetailForTax = PenerimaanDetail::with('penerimaan')->find($penerimaanDetailId);
                if ($penerimaanDetailForTax && $penerimaanDetailForTax->penerimaan) {
                    $taxId = $penerimaanDetailForTax->penerimaan->tax_category_id;
                    \Log::info("Using tax_id from penerimaan: {$taxId} for penerimaan_detail_id: {$penerimaanDetailId}");
                }
            }
            
            // FINAL FALLBACK: Jika masih null, ambil dari product.tax_category_id atau default 4
            if (!$taxId) {
                $productTaxCategory = \App\Models\Product::where('id', $productId)->value('tax_category_id');
                $taxId = $productTaxCategory ?? 4;
                \Log::warning("tax_id still NULL after all fallbacks for product #{$productId}, using: {$taxId}");
            }
            
            // Get the retur warehouse location (same as offline)
            $returLocation = \App\Models\Lokasi::where('kode', 'GUDANG_RETUR')->first();
            if (!$returLocation) {
                $returLocation = \App\Models\Lokasi::create([
                    'kode' => 'GUDANG_RETUR',
                    'nama' => 'Gudang Retur',
                    'deskripsi' => 'Tempat penyimpanan barang hasil retur'
                ]);
                \Log::info("Created new retur location with ID: {$returLocation->id}");
            }

            // Create a new warehouse stock entry for returned items
            // PERBAIKAN: Pastikan tax_id yang digunakan adalah originalTaxId (jika tersedia), atau dari reference stock, atau dari penerimaan
            $warehouseStock = WarehouseStock::create([
                'product_id' => $productId,
                'lokasi_id' => $returLocation->id, // Use retur location
                'penerimaan_detail_id' => $penerimaanDetailId,
                'tax_id' => $taxId, // Menggunakan originalTaxId jika tersedia, atau dari reference stock, atau dari penerimaan
                'qty' => $qty,
                'expired_date' => $expiredDate,
                'status_ed' => $statusEd,
                'is_damaged' => $isDamaged,
                'catatan' => 'Retur penjualan pada ' . ($returDate ?? now()->format('Y-m-d')),
                'source_type' => 'retur_penjualan',
                'source_id' => $returPenjualanId,
                'source_date' => $returDate ? \Carbon\Carbon::parse($returDate) : now(),
            ]);
            
            \Log::info("Created new warehouse stock ID: {$warehouseStock->id} with qty: {$qty}", [
                'penerimaan_detail_id' => $penerimaanDetailId,
                'tax_id' => $taxId,
                'original_tax_id_provided' => $originalTaxId !== null,
                'expired_date' => $expiredDate,
                'status_ed' => $statusEd,
                'is_damaged' => $isDamaged
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in addBackToStock: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e; // Re-throw to be caught by the calling method
        }
    }

    /**
     * Remove stock that was added during return processing
     * PERBAIKAN: Hapus warehouse stock entries yang dibuat saat retur (berdasarkan source_type dan source_id)
     * 
     * @param  int  $productId
     * @param  float  $qty
     * @param  bool  $isDamaged
     * @param  int  $returPenjualanId
     * @return void
     */
    private function removeReturnedStock($productId, $qty, $isDamaged, $returPenjualanId)
    {
        \Log::info("removeReturnedStock called for product ID: {$productId}, qty: {$qty}, isDamaged: " . ($isDamaged ? 'yes' : 'no') . ", returPenjualanId: {$returPenjualanId}");
        
        try {
            // Find warehouse stock entries that were created by this return
            // PERBAIKAN: Hapus semua warehouse stock yang dibuat dari retur ini
            $returnedStocks = WarehouseStock::where('product_id', $productId)
                ->where('is_damaged', $isDamaged)
                ->where('source_type', 'retur_penjualan')
                ->where('source_id', $returPenjualanId)
                ->orderBy('created_at', 'desc') // Most recent first
                ->get();

            if ($returnedStocks->isEmpty()) {
                \Log::warning("No specific return stock found for product ID: {$productId}, returPenjualanId: {$returPenjualanId}");
                // Don't throw error, just log warning - mungkin stock sudah dihapus atau tidak ada
                return;
            }

            $remainingQty = floatval($qty);
            $deletedStockIds = [];
            
            foreach ($returnedStocks as $stock) {
                if ($remainingQty <= 0) break;

                $stockQty = floatval($stock->qty);
                
                \Log::info("Checking stock ID: {$stock->id}, qty: {$stockQty}, remaining to delete: {$remainingQty}");
                
                if ($stockQty <= $remainingQty) {
                    // Hapus seluruh stock entry jika qty-nya <= remaining qty
                    \Log::info("Deleting entire stock ID: {$stock->id} with qty: {$stockQty}");
                    $deletedStockIds[] = $stock->id;
                    $stock->delete();
                    $remainingQty -= $stockQty;
                } else {
                    // Jika stock qty > remaining qty, kurangi qty-nya saja
                    \Log::info("Reducing stock ID: {$stock->id} from {$stockQty} to " . ($stockQty - $remainingQty));
                    $stock->qty = $stockQty - $remainingQty;
                    $stock->save();
                    $remainingQty = 0;
                }
            }

            if ($remainingQty > 0) {
                \Log::warning("Could not remove all requested quantity. Remaining: {$remainingQty}");
                // Don't throw error, just log warning - mungkin ada perbedaan karena rounding atau data tidak konsisten
            }

            \Log::info("Successfully removed returned stock for product ID: {$productId}. Deleted stock IDs: " . implode(', ', $deletedStockIds));
            
        } catch (\Exception $e) {
            \Log::error("Error in removeReturnedStock: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e; // Re-throw to be caught by the calling method
        }
    }

    public function export()
    {
        // Increase time and memory limits for large exports
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');

        $filename = 'retur_penjualan_detail_' . date('Y-m-d') . '.xlsx';
        
        return Excel::download(new \App\Exports\ReturPenjualanDetailExport(), $filename);
    }

    /**
     * Print the retur penjualan invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $returPenjualan = ReturPenjualan::with([
            'order',
            'order.platform',
            'order.orderItems',
            'user',
            'details.product',
            'details.orderItem',
            'details.orderItem.platformProduct'
        ])->findOrFail($id);

        // Only allow printing of completed returns
        if ($returPenjualan->status !== 'selesai') {
            return redirect()->route('retur-penjualan.show', $id)
                ->with('error', 'Hanya retur dengan status selesai yang dapat dicetak.');
        }

        return view('retur.penjualan.print', compact('returPenjualan'));
    }
} 
