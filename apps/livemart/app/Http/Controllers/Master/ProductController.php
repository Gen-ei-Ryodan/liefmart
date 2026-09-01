<?php

namespace App\Http\Controllers\Master;

use App\Exports\ProductsExport;
use App\Traits\QueueExport;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSize;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\SubBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use QueueExport;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = $this->buildProductQuery($request);
        
        // Paginate with all current query parameters preserved
        $perPage = $request->per_page ?? 15;
        $products = $query->paginate($perPage)->withQueryString();
        
        // Get data for filter dropdowns
        $mainCategories = MainCategory::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();
        
        return view('master.products.index', compact('products', 'mainCategories', 'brands'));
    }

    public function export(Request $request, string $format)
    {
        $query = $this->buildProductQuery($request);
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($format === 'xlsx') {
            return $this->queueExcelExport(
                new ProductsExport($query),
                "master_products_{$timestamp}.xlsx",
                'Export Master Products XLSX'
            );
        }

        if ($format === 'csv') {
            return $this->queueExcelExport(
                new ProductsExport($query),
                "master_products_{$timestamp}.csv",
                'Export Master Products CSV'
            );
        }

        if ($format === 'pdf') {
            $products = $query->get();

            $filterMainCategory = null;
            if ($request->filled('main_category_id')) {
                $filterMainCategory = MainCategory::find($request->main_category_id)?->name;
            }

            $filterBrand = null;
            if ($request->filled('brand_id')) {
                $filterBrand = Brand::find($request->brand_id)?->name;
            }

            $filters = [
                'search' => $request->input('search'),
                'main_category' => $filterMainCategory,
                'brand' => $filterBrand,
                'status' => $request->input('status'),
                'order_by' => $request->input('order_by'),
                'order_direction' => $request->input('order_direction'),
            ];

            $pdf = Pdf::loadView('master.products.export.pdf', compact('products', 'filters'))
                ->setPaper('a4', 'landscape');

            return $pdf->download("master_products_{$timestamp}.pdf");
        }

        abort(404);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Get the main category ID from session that was selected at login
        $mainCategoryId = session('main_category_id');
        
        // If there's no main category ID in session, redirect back with error
        if (!$mainCategoryId) {
            return redirect()->back()->with('error', 'Main Category tidak ditemukan. Silakan login ulang.');
        }
        
        // Get only the main category that was selected at login
        $mainCategory = MainCategory::find($mainCategoryId);
        $mainCategories = collect([$mainCategory]);
        
        // Get brands for this main category
        $brands = Brand::where('is_active', true)
                       ->where('main_category_id', $mainCategoryId)
                       ->orderBy('name')
                       ->get();
        
        // Get all related data to handle form validation and re-population
        $subBrands = SubBrand::where('is_active', true)
            ->whereHas('brand', function($query) use ($mainCategoryId) {
                $query->where('main_category_id', $mainCategoryId);
            })
            ->when(old('brand_id'), function($query) {
                return $query->where('brand_id', old('brand_id'));
            })
            ->orderBy('name')
            ->get();
            
        $productCategories = ProductCategory::where('is_active', true)
            ->whereHas('subBrand.brand', function($query) use ($mainCategoryId) {
                $query->where('main_category_id', $mainCategoryId);
            })
            ->when(old('sub_brand_id'), function($query) {
                return $query->where('sub_brand_id', old('sub_brand_id'));
            })
            ->orderBy('name')
            ->get();
            
        $productTypes = ProductType::where('is_active', true)
            ->whereHas('productCategory.subBrand.brand', function($query) use ($mainCategoryId) {
                $query->where('main_category_id', $mainCategoryId);
            })
            ->when(old('product_category_id'), function($query) {
                return $query->where('product_category_id', old('product_category_id'));
            })
            ->orderBy('name')
            ->get();
            
        $productSizes = ProductSize::where('is_active', true)->orderBy('name')->get();
        $productVariants = ProductVariant::where('is_active', true)->orderBy('name')->get();
        
        return view('master.products.create', compact(
            'mainCategories',
            'brands',
            'subBrands',
            'productCategories',
            'productTypes',
            'productSizes',
            'productVariants',
            'mainCategoryId'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Log data yang diterima untuk debugging
            \Log::info('Product Store Request', $request->all());
            
            // Validasi data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'main_category_id' => 'required|exists:main_categories,id',
                'brand_id' => 'required|exists:brands,id',
                'sub_brand_id' => 'required|exists:sub_brands,id',
                'product_category_id' => 'required|exists:product_categories,id',
                'product_type_id' => 'required|exists:product_types,id',
                'product_size_id' => 'required|exists:product_sizes,id',
                'product_variant_id' => 'nullable|exists:product_variants,id',
                'sku' => 'nullable|string|max:50|unique:products,sku',
                'barcode' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'initial_price' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            // Validasi hierarki - pastikan semua relasi konsisten
            $this->validateHierarchy($validated);

            // Pastikan value is_active selalu ada
            // Jika hidden input ada dan checkbox tidak di-check, gunakan nilai 0
            // Jika checkbox di-check, gunakan nilai 1
            $validated['is_active'] = $request->has('is_active') ? 1 : 0;
            
            \Log::info('Is Active Value: ' . $validated['is_active']);
            
            // Pastikan product_variant_id null jika kosong
            if (empty($validated['product_variant_id'])) {
                $validated['product_variant_id'] = null;
            }
            
            // Generate SKU automatically if not provided
            if (empty($validated['sku'])) {
                $brand = \App\Models\Brand::find($validated['brand_id']);
                $brandName = $brand ? $brand->name : 'UNKNOWN';
                $prefix = strtoupper(substr($brandName, 0, 1) . substr($validated['name'], 0, 1));
                
                // Get the next available number
                $lastProduct = Product::where('sku', 'like', $prefix . '%')
                    ->orderBy('sku', 'desc')
                    ->first();
                
                $nextNumber = 1;
                if ($lastProduct && preg_match('/' . preg_quote($prefix) . '(\d+)/', $lastProduct->sku, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
                
                $validated['sku'] = $prefix . sprintf('%04d', $nextNumber);
            }
            
            // Log data yang akan disimpan
            \Log::info('Data Product yang akan disimpan:', $validated);
            
            // Coba buat produk
            $product = Product::create($validated);
            
            // Log produk yang berhasil dibuat
            \Log::info('Product berhasil dibuat dengan ID:', ['id' => $product->id]);
            
            // Redirect dengan pesan sukses
            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil ditambahkan.');
                
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error saat membuat produk:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Redirect dengan pesan error
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $product = Product::with([
            'mainCategory', 
            'brand', 
            'subBrand', 
            'productCategory', 
            'productType', 
            'productSize', 
            'productVariant'
        ])->findOrFail($id);
        
        return view('master.products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $product = Product::findOrFail($id);
        
        $mainCategories = MainCategory::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $subBrands = SubBrand::where('is_active', true)->get();
        $productCategories = ProductCategory::where('is_active', true)->get();
        $productTypes = ProductType::where('is_active', true)->get();
        $productSizes = ProductSize::where('is_active', true)->get();
        $productVariants = ProductVariant::where('is_active', true)->get();
        
        return view('master.products.edit', compact(
            'product',
            'mainCategories',
            'brands',
            'subBrands',
            'productCategories',
            'productTypes',
            'productSizes',
            'productVariants'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);
            
            // Log data yang diterima untuk debugging
            \Log::info('Product Update Request', $request->all());
            
            // Validasi data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'main_category_id' => 'required|exists:main_categories,id',
                'brand_id' => 'required|exists:brands,id',
                'sub_brand_id' => 'required|exists:sub_brands,id',
                'product_category_id' => 'required|exists:product_categories,id',
                'product_type_id' => 'required|exists:product_types,id',
                'product_size_id' => 'required|exists:product_sizes,id',
                'product_variant_id' => 'nullable|exists:product_variants,id',
                'sku' => 'nullable|string|max:50|unique:products,sku,' . $id,
                'barcode' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'initial_price' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
            ]);
            
            // Validasi hierarki - pastikan semua relasi konsisten
            $this->validateHierarchy($validated);
            
            // Pastikan value is_active selalu ada
            $validated['is_active'] = $request->has('is_active') ? 1 : 0;
            
            \Log::info('Is Active Value (update): ' . $validated['is_active']);
            
            // Pastikan product_variant_id null jika kosong
            if (empty($validated['product_variant_id'])) {
                $validated['product_variant_id'] = null;
            }
            
            // Generate SKU automatically if not provided
            if (empty($validated['sku'])) {
                $brand = \App\Models\Brand::find($validated['brand_id']);
                $brandName = $brand ? $brand->name : 'UNKNOWN';
                $prefix = strtoupper(substr($brandName, 0, 1) . substr($validated['name'], 0, 1));
                
                // Get the next available number
                $lastProduct = Product::where('sku', 'like', $prefix . '%')
                    ->where('id', '!=', $id)
                    ->orderBy('sku', 'desc')
                    ->first();
                
                $nextNumber = 1;
                if ($lastProduct && preg_match('/' . preg_quote($prefix) . '(\d+)/', $lastProduct->sku, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
                
                $validated['sku'] = $prefix . sprintf('%04d', $nextNumber);
            }
            
            // Log data yang akan diupdate
            \Log::info('Data Product yang akan diupdate:', $validated);
            
            // Update produk
            $product->update($validated);
            
            // Log produk yang berhasil diupdate
            \Log::info('Product berhasil diupdate dengan ID:', ['id' => $product->id]);
            
            // Redirect dengan pesan sukses
            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil diperbarui.');
                
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error saat mengupdate produk:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Redirect dengan pesan error
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        
        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
    
    /**
     * Get sub brands for a specific brand
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getSubBrands(Request $request)
    {
        $brandId = $request->input('brand_id');
        $subBrands = SubBrand::where('brand_id', $brandId)
            ->where('is_active', true)
            ->get();
            
        return response()->json($subBrands);
    }
    
    /**
     * Get product categories for a specific sub brand
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductCategories(Request $request)
    {
        $subBrandId = $request->input('sub_brand_id');
        $productCategories = ProductCategory::where('sub_brand_id', $subBrandId)
            ->where('is_active', true)
            ->get();
            
        return response()->json($productCategories);
    }
    
    /**
     * Get product types for a specific product category
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductTypes(Request $request)
    {
        $productCategoryId = $request->input('product_category_id');
        $productTypes = ProductType::where('product_category_id', $productCategoryId)
            ->where('is_active', true)
            ->get();
            
        return response()->json($productTypes);
    }
    
    /**
     * Validate hierarchy consistency
     *
     * @param  array  $validated
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateHierarchy($validated)
    {
        // Validasi Main Category -> Brand (cross-category inconsistency)
        $brand = Brand::find($validated['brand_id']);
        if (!$brand || $brand->main_category_id != $validated['main_category_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'brand_id' => 'Brand tidak sesuai dengan Kategori Utama yang dipilih.'
            ]);
        }

        // Validasi Brand -> Sub Brand
        $subBrand = SubBrand::find($validated['sub_brand_id']);
        if (!$subBrand || $subBrand->brand_id != $validated['brand_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sub_brand_id' => 'Sub Brand tidak sesuai dengan Brand yang dipilih.'
            ]);
        }
        
        // Validasi Sub Brand -> Product Category
        $productCategory = ProductCategory::find($validated['product_category_id']);
        if (!$productCategory || $productCategory->sub_brand_id != $validated['sub_brand_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_category_id' => 'Kategori Produk tidak sesuai dengan Sub Brand yang dipilih.'
            ]);
        }
        
        // Validasi Product Category -> Product Type
        $productType = ProductType::find($validated['product_type_id']);
        if (!$productType || $productType->product_category_id != $validated['product_category_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_type_id' => 'Tipe Produk tidak sesuai dengan Kategori Produk yang dipilih.'
            ]);
        }
        
        // Validasi Product Type -> Product Size
        $productSize = ProductSize::find($validated['product_size_id']);
        if (!$productSize || $productSize->product_type_id != $validated['product_type_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_size_id' => 'Ukuran Produk tidak sesuai dengan Tipe Produk yang dipilih.'
            ]);
        }
        
        // Validasi Product Size -> Product Variant (jika ada)
        if (!empty($validated['product_variant_id'])) {
            $productVariant = ProductVariant::find($validated['product_variant_id']);
            if (!$productVariant || $productVariant->product_size_id != $validated['product_size_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'product_variant_id' => 'Varian Produk tidak sesuai dengan Ukuran Produk yang dipilih.'
                ]);
            }
        }
    }

    private function buildProductQuery(Request $request): Builder
    {
        $query = Product::with([
            'mainCategory',
            'brand',
            'subBrand',
            'productCategory',
            'productType',
            'productSize',
            'productVariant',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('main_category_id')) {
            $query->where('main_category_id', $request->main_category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        $allowedOrderBy = [
            'name',
            'sku',
            'barcode',
            'initial_price',
            'discount_percentage',
            'is_active',
            'created_at',
            'updated_at',
        ];

        $orderBy = $request->input('order_by', 'created_at');
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'created_at';
        }

        $orderDirection = strtolower((string) $request->input('order_direction', 'desc'));
        if (!in_array($orderDirection, ['asc', 'desc'], true)) {
            $orderDirection = 'desc';
        }

        $query->orderBy($orderBy, $orderDirection);

        return $query;
    }
} 
