<?php

namespace App\Exports;

use App\Models\Shopee2FinancialTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Http\Request;

class Shopee2FinanceAnalyticsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Shopee2FinancialTransaction::with([
            'order.orderItems.platformProduct.mappingBarang',
            'order.orderItems.warehouseStock.tax',
            'order.mainCategory'
        ]);

        if ($this->request) {
            // Apply filters from request
            if ($this->request->filled('from_date')) {
                $query->whereDate('tanggal_masuk_pembayaran', '>=', $this->request->from_date);
            }
            
            if ($this->request->filled('to_date')) {
                $query->whereDate('tanggal_masuk_pembayaran', '<=', $this->request->to_date);
            }
            
            if ($this->request->filled('from_order_date')) {
                $query->whereDate('tanggal_order', '>=', $this->request->from_order_date);
            }
            
            if ($this->request->filled('to_order_date')) {
                $query->whereDate('tanggal_order', '<=', $this->request->to_order_date);
            }
            
            if ($this->request->filled('order_number')) {
                $query->where('no_order', 'like', '%' . $this->request->order_number . '%');
            }
            
            if ($this->request->filled('invoice_number')) {
                $query->where('no_invoice', 'like', '%' . $this->request->invoice_number . '%');
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

        return $query->orderBy('tanggal_order', 'desc')
            ->orderBy('no_order', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Order',
            'Hari Order',
            'No Order',
            'No Invoice',
            'Nominal Harga',
            'DPP',
            'PPN',
            'Diskon 1',
            'Diskon 2',
            'Diskon 3',
            'Diskon 4',
            'Diskon 5',
            'Diskon 6',
            'Adjustment',
            'Nominal Fix',
            'Saldo Masuk',
            'Tanggal Masuk Pembayaran',
            'Hari Masuk Pembayaran',
            'Outstanding',
            'Persentase Diskon 1',
            'Persentase Diskon 2',
            'Persentase Diskon 3',
            'Persentase Diskon 4',
            'Persentase Diskon 5',
            'Persentase Diskon 6',
            'Persentase Diskon 7',
            'Persentase Diskon 8',
            'Persentase Diskon 9',
            'Persentase Diskon 10',
            'Persentase Diskon 11',
            'Persentase Diskon 12',
            'Total Persentase',
        ];
    }

    public function map($transaction): array
    {
        // Determine tax status
        $taxId = null;
        if (strpos($transaction->no_invoice, 'HPNSDA-OLK/01') !== false) {
            $taxId = 1; // PKP - Coffee
        } elseif (strpos($transaction->no_invoice, 'HPNSDA-OLK/02') !== false) {
            $taxId = 2; // Non PKP - Coffee
        } elseif (strpos($transaction->no_invoice, 'AMP/01') !== false) {
            $taxId = 3; // PKP - Skincare
        } elseif (strpos($transaction->no_invoice, 'AMP/02') !== false) {
            $taxId = 4; // Non PKP - Skincare
        } else {
            // Extract last two digits if possible
            if (preg_match('/\/(\d{2})/', $transaction->no_invoice, $matches)) {
                $taxId = (int)$matches[1];
            }
        }
        $isPKP = in_array($taxId, [1, 3, 5, 7]);
        
        $dpp = $transaction->nominal_harga;
        $ppn = 0;
        
        if ($isPKP && $dpp > 0) {
            $dppVal = $dpp / 1.11;
            $dpp = round($dppVal, 2);
            $ppn = round($transaction->nominal_harga - $dpp, 2);
        }

        return [
            $transaction->tanggal_order ? $transaction->tanggal_order->format('Y-m-d') : '',
            $transaction->hari_order,
            $transaction->no_order,
            $transaction->no_invoice,
            $transaction->nominal_harga,
            $dpp,
            $ppn,
            $transaction->nominal_diskon1,
            $transaction->nominal_diskon2,
            $transaction->nominal_diskon3,
            $transaction->nominal_diskon4,
            $transaction->nominal_diskon5,
            $transaction->nominal_diskon6,
            $transaction->adjustment,
            $transaction->nominal_fix,
            $transaction->saldo_masuk,
            $transaction->tanggal_masuk_pembayaran ? $transaction->tanggal_masuk_pembayaran->format('Y-m-d') : '',
            $transaction->hari_masuk_pembayaran,
            $transaction->outstanding,
            $transaction->persentase_diskon1,
            $transaction->persentase_diskon2,
            $transaction->persentase_diskon3,
            $transaction->persentase_diskon4,
            $transaction->persentase_diskon5,
            $transaction->persentase_diskon6,
            $transaction->persentase_diskon7,
            $transaction->persentase_diskon8,
            $transaction->persentase_diskon9,
            $transaction->persentase_diskon10,
            $transaction->persentase_diskon11,
            $transaction->persentase_diskon12,
            $transaction->total_persentase,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Tanggal Order
            'B' => 12, // Hari Order
            'C' => 20, // No Order
            'D' => 20, // No Invoice
            'E' => 15, // Nominal Harga
            'F' => 12, // Diskon 1
            'G' => 12, // Diskon 2
            'H' => 12, // Diskon 3
            'I' => 12, // Diskon 4
            'J' => 12, // Diskon 5
            'K' => 12, // Diskon 6
            'L' => 12, // Adjustment
            'M' => 15, // Nominal Fix
            'N' => 15, // Saldo Masuk
            'O' => 20, // Tanggal Masuk Pembayaran
            'P' => 18, // Hari Masuk Pembayaran
            'Q' => 15, // Outstanding
            'R' => 18, // Persentase Diskon 1
            'S' => 18, // Persentase Diskon 2
            'T' => 18, // Persentase Diskon 3
            'U' => 18, // Persentase Diskon 4
            'V' => 18, // Persentase Diskon 5
            'W' => 18, // Persentase Diskon 6
            'X' => 18, // Persentase Diskon 7
            'Y' => 18, // Persentase Diskon 8
            'Z' => 18, // Persentase Diskon 9
            'AA' => 18, // Persentase Diskon 10
            'AB' => 18, // Persentase Diskon 11
            'AC' => 18, // Persentase Diskon 12
            'AD' => 18, // Total Persentase
        ];
    }
}
