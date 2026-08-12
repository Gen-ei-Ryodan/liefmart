<?php

namespace App\Exports;

use App\Models\Tiktok2FinancialTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Illuminate\Http\Request;

class Tiktok2FinanceAnalyticsExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithColumnFormatting, WithCustomValueBinder
{
    protected $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Tiktok2FinancialTransaction::with([
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
            'Biaya Admin',
            'Affiliate Commission',
            'Seller Shipping Fee',
            'Voucher Xtra Service Fee',
            'Cashback Fee',
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
            'F' => 15, // Biaya Admin
            'G' => 18, // Affiliate Commission
            'H' => 20, // Seller Shipping Fee
            'I' => 22, // Voucher Xtra Service Fee
            'J' => 15, // Cashback Fee
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
            'X' => 18, // Total Persentase
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        // Column C is "No Order" and Column D is "No Invoice" - force these to be text
        if (in_array($cell->getColumn(), ['C', 'D'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        
        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT, // No Order
            'D' => NumberFormat::FORMAT_TEXT, // No Invoice
        ];
    }
}
