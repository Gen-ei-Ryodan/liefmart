<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromIterator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Order;
use App\Models\MappingBarang;
use App\Queries\Analytics\Sales\SalesDetailQuery;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesExportMappedExport implements FromIterator, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
        // Increase memory limit for this export process
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 300); // 5 minutes
    }

    public function iterator(): \Iterator
    {
        return $this->getRows();
    }

    public function getRows(): \Generator
    {
        DB::connection()->disableQueryLog();
        
        $perPage = 100; 
        $page = 1;
        $no = 1;
        
        do {
            $sqlQuery = SalesDetailQuery::build($this->filters, $perPage, $page, false);
            $orderResults = DB::select($sqlQuery);
            
            if (empty($orderResults)) {
                break;
            }
            
            $orderIds = collect($orderResults)->pluck('id')->toArray();
            
            if (!empty($orderIds)) {
                $ordersDict = Order::withoutGlobalScope('mainCategory')
                    ->whereIn('id', $orderIds)
                    ->with([
                        'orderItems' => function($query) {
                            $query->select('id', 'order_id', 'platform_product_id', 'quantity', 'price_after_discount', 'tracking_number')
                                  ->orderBy('id');
                        },
                        'orderItems.platformProduct' => function($query) {
                            $query->select('id', 'platform_product_name', 'variant');
                        },
                        'orderItems.returPenjualanDetails' => function($query) {
                            $query->whereHas('returPenjualan', function($q) {
                                $q->whereIn('status', ['draft', 'selesai']);
                            });
                        },
                        'platform' => function($query) {
                            $query->select('id', 'name');
                        }
                    ])
                    ->get()
                    ->keyBy('id');

                $mappingCache = [];
                foreach ($orderIds as $id) {
                    if (!isset($ordersDict[$id])) continue;
                    $order = $ordersDict[$id];
                    
                    $orderHari = $order->tanggal ? Carbon::parse($order->tanggal)->locale('id')->isoFormat('dddd') : '-';
                    
                    // Pre-calculate order totals
                    $totalOrderValue = 0;
                    $totalOrderVolume = 0;
                    foreach ($order->orderItems as $item) {
                        $totalOrderValue += ($item->price_after_discount * $item->quantity);
                        $totalOrderVolume += $item->quantity;
                    }

                    foreach ($order->orderItems as $item) {
                        $mappings = collect([]);
                        $platformProductId = $item->platform_product_id;
                        if ($platformProductId) {
                            $effectiveVersion = MappingBarang::getEffectiveVersionForOrderCreatedAt($platformProductId, $order->created_at);
                            $cacheKey = $platformProductId . '|' . ($effectiveVersion === null ? 'active' : $effectiveVersion);

                            if (isset($mappingCache[$cacheKey])) {
                                $mappings = $mappingCache[$cacheKey];
                            } else {
                                $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProductId, $order->created_at);
                                $mappings->load([
                                    'product' => function($q) {
                                        $q->select('id', 'name', 'sku');
                                    }
                                ]);
                                $mappingCache[$cacheKey] = $mappings;
                            }
                        }
                        
                        if ($mappings->count() > 0) {
                            // PERBAIKAN: Konversi qty retur individual ke satuan paket agar konsisten dengan view
                            $packageQuantityRetur = $mappings->sum('quantity');
                            $qtyRetur = $packageQuantityRetur > 0 ? round($item->returPenjualanDetails->sum('qty') / $packageQuantityRetur, 4) : $item->returPenjualanDetails->sum('qty');

                            foreach ($mappings as $mapping) {
                                yield [
                                    'No' => $no,
                                    'Tanggal' => $order->tanggal ? Carbon::parse($order->tanggal)->format('d-m-Y') : '-',
                                    'Hari' => $orderHari,
                                    'No Order' => "'" . $order->order_number,
                                    'Platform' => $order->platform ? $order->platform->name : '-',
                                    'Nama Barang (Platform)' => $item->platformProduct ? $item->platformProduct->platform_product_name : '-',
                                    'Varian' => $item->platformProduct ? $item->platformProduct->variant : '-',
                                    'Qty (Platform)' => $item->quantity,
                                    'Qty Retur' => $qtyRetur,
                                    'Harga' => $item->price_after_discount,
                                    'Total Item' => $item->price_after_discount * $item->quantity,
                                    'Nama Barang (Internal)' => $mapping->product ? $mapping->product->name : 'Product Not Found',
                                    'SKU (Internal)' => $mapping->product ? $mapping->product->sku : '-',
                                    'Qty (Internal)' => $mapping->quantity * $item->quantity,
                                    'Qty Total Order' => $totalOrderVolume,
                                    'Total Invoice' => $totalOrderValue,
                                    'No Resi' => "'" . $item->tracking_number,
                                ];
                            }
                        } else {
                            $qtyRetur = $item->returPenjualanDetails->sum('qty');

                            yield [
                                'No' => $no,
                                'Tanggal' => $order->tanggal ? Carbon::parse($order->tanggal)->format('d-m-Y') : '-',
                                'Hari' => $orderHari,
                                'No Order' => "'" . $order->order_number,
                                'Platform' => $order->platform ? $order->platform->name : '-',
                                'Nama Barang (Platform)' => $item->platformProduct ? $item->platformProduct->platform_product_name : '-',
                                'Varian' => $item->platformProduct ? $item->platformProduct->variant : '-',
                                'Qty (Platform)' => $item->quantity,
                                'Qty Retur' => $qtyRetur,
                                'Harga' => $item->price_after_discount,
                                'Total Item' => $item->price_after_discount * $item->quantity,
                                'Nama Barang (Internal)' => 'No Mapping',
                                'SKU (Internal)' => '-',
                                'Qty (Internal)' => 0,
                                'Qty Total Order' => $totalOrderVolume,
                                'Total Invoice' => $totalOrderValue,
                                'No Resi' => "'" . $item->tracking_number,
                            ];
                        }
                    }
                    $no++;
                }
                
                // Clear memory
                $ordersDict = null;
                $orderIds = null;
                $orderResults = null;
                unset($ordersDict, $orderIds, $orderResults);
                gc_collect_cycles();
            }
            
            $page++;
            
        } while (true);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Hari',
            'No Order',
            'Platform',
            'Nama Barang (Platform)',
            'Varian',
            'Qty (Platform)',
            'Qty Retur',
            'Harga',
            'Total Item',
            'Nama Barang (Internal)',
            'SKU (Internal)',
            'Qty (Internal)',
            'Qty Total Order',
            'Total Invoice',
            'No Resi',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
