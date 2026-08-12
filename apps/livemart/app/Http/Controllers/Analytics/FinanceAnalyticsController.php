<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\ShopeeFinancialTransaction;
use App\Models\TiktokFinancialTransaction;
use App\Models\Shopee2FinancialTransaction;
use App\Models\Tiktok2FinancialTransaction;
use App\Exports\ShopeeFinanceAnalyticsExport;
use App\Exports\TiktokFinanceAnalyticsExport;
use App\Exports\Shopee2FinanceAnalyticsExport;
use App\Exports\Tiktok2FinanceAnalyticsExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class FinanceAnalyticsController extends Controller
{
    /**
     * Display Shopee analytics
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function shopeeAnalytics(Request $request)
    {
        $platform = 'shopee'; // Tetapkan platform
        
        $query = ShopeeFinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date
        if ($request->filled('payment_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range
        if ($request->filled('min_nominal')) {
            $query->where('nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                $query->where('outstanding', 0);
            } elseif ($request->outstanding_status === '1') {
                $query->where(function($q) {
                    $q->where('outstanding', '>', 0)
                      ->orWhere('outstanding', '<', 0);
                });
            }
        }

        // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
        $query->where(function($q) {
            // Keep transactions whose order still has remaining qty (partial return) or has no retur
            $q->whereHas('order.orderItems', function($itemQuery) {
                $itemQuery->where('quantity', '>', 0);
            })->orWhereDoesntHave('order.returPenjualan', function($returQuery) {
                $returQuery->whereIn('status', ['draft', 'selesai']);
            });
        });

        // Calculate totals for cards from filtered data
        $totalCount = $query->count();
        $totalNominalFix = $query->sum('nominal_fix');
        $totalSaldoMasuk = $query->sum('saldo_masuk');
        $totalOutstanding = $query->sum('outstanding');
        
        // Get all transactions with orders to ensure no empty data
        $transactions = clone $query;
        $transactions = $transactions->orderBy('tanggal_order', 'desc')->paginate(15);
        
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');

        return view('analytics.finance.shopee', compact(
            'transactions', 
            'groupedTransactions', 
            'platform',
            'totalCount',
            'totalNominalFix',
            'totalSaldoMasuk',
            'totalOutstanding'
        ));
    }

    /**
     * Display TikTok analytics
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function tiktokAnalytics(Request $request)
    {
        $platform = 'tiktok'; // Tetapkan platform
        
        $query = TiktokFinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date
        if ($request->filled('payment_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range
        if ($request->filled('min_nominal')) {
            $query->where('nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                $query->where('outstanding', 0);
            } elseif ($request->outstanding_status === '1') {
                $query->where(function($q) {
                    $q->where('outstanding', '>', 0)
                      ->orWhere('outstanding', '<', 0);
                });
            }
        }
        
        // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
        $query->where(function($q) {
            // Keep transactions whose order still has remaining qty (partial return) or has no retur
            $q->whereHas('order.orderItems', function($itemQuery) {
                $itemQuery->where('quantity', '>', 0);
            })->orWhereDoesntHave('order.returPenjualan', function($returQuery) {
                $returQuery->whereIn('status', ['draft', 'selesai']);
            });
        });

        // Calculate totals for cards from filtered data
        $totalCount = $query->count();
        $totalNominalFix = $query->sum('nominal_fix');
        $totalSaldoMasuk = $query->sum('saldo_masuk');
        $totalOutstanding = $query->sum('outstanding');
        
        // Get all transactions with orders to ensure no empty data
        $transactions = clone $query;
        $transactions = $transactions->orderBy('tanggal_order', 'desc')->paginate(15);
        
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');

        return view('analytics.finance.tiktok', compact(
            'transactions', 
            'groupedTransactions', 
            'platform',
            'totalCount',
            'totalNominalFix',
            'totalSaldoMasuk',
            'totalOutstanding'
        ));
    }

    /**
     * Display Shopee 2 analytics
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function shopee2Analytics(Request $request)
    {
        $platform = 'shopee2'; 
        
        $query = Shopee2FinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date
        if ($request->filled('payment_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range
        if ($request->filled('min_nominal')) {
            $query->where('nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                $query->where('outstanding', 0);
            } elseif ($request->outstanding_status === '1') {
                $query->where(function($q) {
                    $q->where('outstanding', '>', 0)
                      ->orWhere('outstanding', '<', 0);
                });
            }
        }

        // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
        $query->where(function($q) {
            // Keep transactions whose order still has remaining qty (partial return) or has no retur
            $q->whereHas('order.orderItems', function($itemQuery) {
                $itemQuery->where('quantity', '>', 0);
            })->orWhereDoesntHave('order.returPenjualan', function($returQuery) {
                $returQuery->whereIn('status', ['draft', 'selesai']);
            });
        });

        // Calculate totals for cards from filtered data
        $totalCount = $query->count();
        $totalNominalFix = $query->sum('nominal_fix');
        $totalSaldoMasuk = $query->sum('saldo_masuk');
        $totalOutstanding = $query->sum('outstanding');
        
        // Get all transactions with orders to ensure no empty data
        $transactions = clone $query;
        $transactions = $transactions->orderBy('tanggal_order', 'desc')->paginate(15);
        
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');

        return view('analytics.finance.shopee2', compact(
            'transactions', 
            'groupedTransactions', 
            'platform',
            'totalCount',
            'totalNominalFix',
            'totalSaldoMasuk',
            'totalOutstanding'
        ));
    }

    /**
     * Display TikTok 2 analytics
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function tiktok2Analytics(Request $request)
    {
        $platform = 'tiktok2'; 
        
        $query = Tiktok2FinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date
        if ($request->filled('payment_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range
        if ($request->filled('min_nominal')) {
            $query->where('nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                $query->where('outstanding', 0);
            } elseif ($request->outstanding_status === '1') {
                $query->where(function($q) {
                    $q->where('outstanding', '>', 0)
                      ->orWhere('outstanding', '<', 0);
                });
            }
        }
        
        // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
        $query->where(function($q) {
            // Keep transactions whose order still has remaining qty (partial return) or has no retur
            $q->whereHas('order.orderItems', function($itemQuery) {
                $itemQuery->where('quantity', '>', 0);
            })->orWhereDoesntHave('order.returPenjualan', function($returQuery) {
                $returQuery->whereIn('status', ['draft', 'selesai']);
            });
        });

        // Calculate totals for cards from filtered data
        $totalCount = $query->count();
        $totalNominalFix = $query->sum('nominal_fix');
        $totalSaldoMasuk = $query->sum('saldo_masuk');
        $totalOutstanding = $query->sum('outstanding');
        
        // Get all transactions with orders to ensure no empty data
        $transactions = clone $query;
        $transactions = $transactions->orderBy('tanggal_order', 'desc')->paginate(15);
        
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');

        return view('analytics.finance.tiktok2', compact(
            'transactions', 
            'groupedTransactions', 
            'platform',
            'totalCount',
            'totalNominalFix',
            'totalSaldoMasuk',
            'totalOutstanding'
        ));
    }

    /**
     * Export Shopee analytics to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportShopeeAnalytics(Request $request)
    {
        $filename = 'shopee_finance_analytics_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new ShopeeFinanceAnalyticsExport($request->all()), $filename);
    }

    /**
     * Export Shopee 2 analytics to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportShopee2Analytics(Request $request)
    {
        $filename = 'shopee2_finance_analytics_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new Shopee2FinanceAnalyticsExport($request->all()), $filename);
    }

    /**
     * Export TikTok analytics to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportTiktokAnalytics(Request $request)
    {
        $filename = 'tiktok_finance_analytics_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new TiktokFinanceAnalyticsExport($request->all()), $filename);
    }

    /**
     * Export TikTok 2 analytics to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportTiktok2Analytics(Request $request)
    {
        $filename = 'tiktok2_finance_analytics_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new Tiktok2FinanceAnalyticsExport($request->all()), $filename);
    }
} 