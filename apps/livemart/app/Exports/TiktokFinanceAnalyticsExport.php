<?php

namespace App\Exports;

use App\Models\TiktokFinancialTransaction;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class TiktokFinanceAnalyticsExport extends DefaultValueBinder implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithCustomValueBinder
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Custom rounding function: max 1 decimal place
     * Rule: 5+ rounds up, 4- rounds down, with special case for .45 pattern
     */
    private function customRound($value, $decimals = 1)
    {
        // Convert to string to avoid floating point issues
        $valueStr = number_format($value, 10, '.', '');
        $valueStr = rtrim($valueStr, '0');
        $valueStr = rtrim($valueStr, '.');
        
        $parts = explode('.', $valueStr);
        if (count($parts) < 2 || strlen($parts[1]) <= $decimals) {
            return round($value, $decimals);
        }
        
        $intPart = $parts[0];
        $decPart = $parts[1];
        $keepDigits = substr($decPart, 0, $decimals);
        $nextDigit = isset($decPart[$decimals]) ? (int)$decPart[$decimals] : 0;
        
        
        // Normal rounding: 5+ up, 4- down
        if ($nextDigit >= 5) {
            $keepDigitsInt = (int)$keepDigits + 1;
            if ($keepDigitsInt >= pow(10, $decimals)) {
                $intPart = (int)$intPart + 1;
                $keepDigits = str_repeat('0', $decimals);
            } else {
                $keepDigits = str_pad($keepDigitsInt, $decimals, '0', STR_PAD_LEFT);
            }
        } else {
            $keepDigits = str_pad($keepDigits, $decimals, '0', STR_PAD_RIGHT);
        }
        
        return (float)($intPart . '.' . $keepDigits);
    }

    public function bindValue(Cell $cell, $value)
    {
        // Get the column index (1-based)
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn());
        
        // Column 3 is "No. Order" and Column 4 is "No. Invoice" in the final export - force these to be text
        if (($columnIndex === 3 || $columnIndex === 4) && is_string($value) && !empty($value) && $value !== '-') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        
        // For all other values, use the default behavior
        return parent::bindValue($cell, $value);
    }

    public function query()
    {
        $query = TiktokFinancialTransaction::with(['order'])
        ->select([
            'id', 'tanggal_order', 'hari_order', 'no_order', 'no_invoice', 'order_id',
            'nominal_harga', 'nominal_diskon1', 'nominal_diskon2', 'nominal_diskon3', 'nominal_diskon4', 'nominal_diskon5',
            'nominal_diskon6', 'nominal_diskon7', 'nominal_diskon8', 'nominal_diskon9', 'nominal_diskon10', 'nominal_diskon11', 'nominal_diskon12',
            'persentase_diskon1', 'persentase_diskon2', 'persentase_diskon3', 'persentase_diskon4', 'persentase_diskon5',
            'persentase_diskon6', 'persentase_diskon7', 'persentase_diskon8', 'persentase_diskon9', 'persentase_diskon10', 'persentase_diskon11', 'persentase_diskon12',
            'adjustment', 'adjustment_description', 'total_persentase', 'nominal_fix', 'saldo_masuk',
            'tanggal_masuk_pembayaran', 'hari_masuk_pembayaran', 'outstanding'
        ])
        ->orderBy('tanggal_order', 'desc')
        ->limit(100); // Limit to 100 records to avoid memory issues

        // Apply filters - same as in controller
        // Filter by payment date range
        if (isset($this->request['from_date']) && !empty($this->request['from_date'])) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $this->request['from_date']);
        }
        if (isset($this->request['to_date']) && !empty($this->request['to_date'])) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $this->request['to_date']);
        }
        
        // Filter by order date range
        if (isset($this->request['from_order_date']) && !empty($this->request['from_order_date'])) {
            $query->whereDate('tanggal_order', '>=', $this->request['from_order_date']);
        }
        if (isset($this->request['to_order_date']) && !empty($this->request['to_order_date'])) {
            $query->whereDate('tanggal_order', '<=', $this->request['to_order_date']);
        }
        
        if (isset($this->request['order_number']) && !empty($this->request['order_number'])) {
            $query->where('no_order', 'like', '%' . $this->request['order_number'] . '%');
        }
        if (isset($this->request['invoice_number']) && !empty($this->request['invoice_number'])) {
            $query->where('no_invoice', 'like', '%' . $this->request['invoice_number'] . '%');
        }
        
        // Filter by tax ID (sama seperti di controller)
        if (isset($this->request['tax_id']) && !empty($this->request['tax_id'])) {
            $taxIds = (array) $this->request['tax_id'];
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        if (isset($this->request['payment_date']) && !empty($this->request['payment_date'])) {
            $query->whereDate('tanggal_masuk_pembayaran', $this->request['payment_date']);
        }
        if (isset($this->request['min_nominal']) && !empty($this->request['min_nominal'])) {
            $query->where('nominal_fix', '>=', $this->request['min_nominal']);
        }
        if (isset($this->request['max_nominal']) && !empty($this->request['max_nominal'])) {
            $query->where('nominal_fix', '<=', $this->request['max_nominal']);
        }
        if (isset($this->request['outstanding_status']) && $this->request['outstanding_status'] !== '') {
            if ($this->request['outstanding_status'] === '0') {
                $query->where('outstanding', 0);
            } elseif ($this->request['outstanding_status'] === '1') {
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

        return $query;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Order',
            'No. Order',
            'No. Invoice',
            'Status',
            'Harga (Rp)',
            'DPP (Rp)',
            'PPN (Rp)',
            'Biaya Admin (Rp)',
            'Affiliate (Rp)',
            'Shipping (Rp)',
            'Voucher Fee (Rp)',
            'Cashback (Rp)',
            'Biaya 6 (Rp)',
            'Biaya 7 (Rp)',
            'Biaya 8 (Rp)',
            'Biaya 9 (Rp)',
            'Biaya 10 (Rp)',
            'Biaya 11 (Rp)',
            'Biaya 12 (Rp)',
            'Adjustment (Rp)',
            'Keterangan Adjustment',
            'Nominal Fix (Rp)',
            'Saldo Masuk (Rp)',
            'Tanggal Pembayaran',
            'Outstanding (Rp)',
            'Biaya Admin (%)',
            'Affiliate (%)',
            'Shipping (%)',
            'Voucher Fee (%)',
            'Cashback (%)',
            'Biaya 6 (%)',
            'Biaya 7 (%)',
            'Biaya 8 (%)',
            'Biaya 9 (%)',
            'Biaya 10 (%)',
            'Biaya 11 (%)',
            'Biaya 12 (%)',
            'Adjustment (%)',
            'Total (%)'
        ];
    }

    public function map($transaction): array
    {
        static $no = 1;
        
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
        $taxStatus = $taxId ? ($isPKP ? 'PKP' : 'Non-PKP') : 'N/A';
        
        // Calculate DPP and PPN
        $dpp = $transaction->nominal_harga ?? 0;
        $ppn = 0;
        
        if ($isPKP && $dpp > 0) {
            $dppVal = $dpp / 1.11;
            $dpp = round($dppVal, 2);
            $ppn = round($transaction->nominal_harga - $dpp, 2);
        }
        
        // Calculate adjustment percentage
        $adjustmentPercentage = 0;
        if ($transaction->nominal_harga > 0 && $transaction->adjustment != 0) {
            $adjustmentPercentage = $this->customRound(abs(($transaction->adjustment / $transaction->nominal_harga) * 100));
        }
        
        // Calculate total percentage (same as other platforms)
        $totalPercentage = $this->customRound(($transaction->persentase_diskon1 ?? 0) + 
                          ($transaction->persentase_diskon2 ?? 0) + 
                          ($transaction->persentase_diskon3 ?? 0) + 
                          ($transaction->persentase_diskon4 ?? 0) + 
                          ($transaction->persentase_diskon5 ?? 0) + 
                          ($transaction->persentase_diskon6 ?? 0) + 
                          ($transaction->persentase_diskon7 ?? 0) + 
                          ($transaction->persentase_diskon8 ?? 0) + 
                          ($transaction->persentase_diskon9 ?? 0) + 
                          ($transaction->persentase_diskon10 ?? 0) + 
                          ($transaction->persentase_diskon11 ?? 0) + 
                          ($transaction->persentase_diskon12 ?? 0) + 
                          ($transaction->adjustment < 0 ? $adjustmentPercentage : 0));
        
        return [
            $no++,
            $transaction->tanggal_order ? Carbon::parse($transaction->tanggal_order)->format('d/m/Y') : '-',
            (string)($transaction->no_order ?? '-'),
            (string)($transaction->no_invoice ?? '-'),
            $taxStatus,
            $transaction->nominal_harga ?? 0,
            $dpp,
            $ppn,
            $transaction->nominal_diskon1 ?? 0, // BIAYA ADMIN
            $transaction->nominal_diskon2 ?? 0, // AFFILIATE COMMISSION
            $transaction->nominal_diskon3 ?? 0, // SELLER SHIPPING FEE + SFP SERVICE FEE
            $transaction->nominal_diskon4 ?? 0, // VOUCHER XTRA SERVICE FEE
            $transaction->nominal_diskon5 ?? 0, // CASHBACK FEE
            $transaction->nominal_diskon6 ?? 0, // BIAYA 6
            $transaction->nominal_diskon7 ?? 0, // BIAYA 7
            $transaction->nominal_diskon8 ?? 0, // BIAYA 8
            $transaction->nominal_diskon9 ?? 0, // BIAYA 9
            $transaction->nominal_diskon10 ?? 0, // BIAYA 10
            $transaction->nominal_diskon11 ?? 0, // BIAYA 11
            $transaction->nominal_diskon12 ?? 0, // BIAYA 12
            $transaction->adjustment ?? 0,
            $transaction->adjustment_description ?? '-',
            $transaction->nominal_fix ?? 0,
            $transaction->saldo_masuk ?? 0,
            $transaction->tanggal_masuk_pembayaran ? Carbon::parse($transaction->tanggal_masuk_pembayaran)->format('d/m/Y') : '-',
            $transaction->outstanding ?? 0,
            $this->customRound($transaction->persentase_diskon1 ?? 0),
            $this->customRound($transaction->persentase_diskon2 ?? 0),
            $this->customRound($transaction->persentase_diskon3 ?? 0),
            $this->customRound($transaction->persentase_diskon4 ?? 0),
            $this->customRound($transaction->persentase_diskon5 ?? 0),
            $this->customRound($transaction->persentase_diskon6 ?? 0),
            $this->customRound($transaction->persentase_diskon7 ?? 0),
            $this->customRound($transaction->persentase_diskon8 ?? 0),
            $this->customRound($transaction->persentase_diskon9 ?? 0),
            $this->customRound($transaction->persentase_diskon10 ?? 0),
            $this->customRound($transaction->persentase_diskon11 ?? 0),
            $this->customRound($transaction->persentase_diskon12 ?? 0),
            $adjustmentPercentage,
            $totalPercentage
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style for headers
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D9E1F2']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]
        ];
    }
} 