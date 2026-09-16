<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class SalesDetailReportExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomValueBinder
{
    protected $items;
    protected $summary;
    protected $startDate;
    protected $endDate;
    protected $selectedPlatform;
    protected $sortBy;

    public function __construct($items, $summary, $startDate, $endDate, $selectedPlatform = null, $sortBy = 'date_newest')
    {
        $this->items = $items;
        $this->summary = $summary;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->selectedPlatform = $selectedPlatform;
        $this->sortBy = $sortBy;
    }

    public function collection()
    {
        return $this->items;
    }

    public function bindValue(Cell $cell, $value)
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn());
        
        if ($columnIndex === 4 || $columnIndex === 14) {
            $cell->setValueExplicit((string)$value, DataType::TYPE_STRING);
            return true;
        }
        
        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Hari',
            'No Order',
            'Platform',
            'Nama Barang',
            'Varian',
            'Qty',
            'QTY Retur',
            'Harga',
            'Total Item',
            'Qty Total',
            'Total Invoice',
            'No Resi',
        ];
    }

    public function map($item): array
    {
        static $index = 0;
        $index++;

        $order = $item->order;
        $platform = $order->platform ?? null;

        $tanggal = $order && $order->tanggal
            ? \Carbon\Carbon::parse($order->tanggal)->format('d-m-Y')
            : '-';

        $hari = $order && $order->tanggal
            ? ($order->hari ?? \Carbon\Carbon::parse($order->tanggal)->locale('id')->isoFormat('dddd'))
            : '-';

        $orderNumber = $order ? (string)$order->order_number : '';
        $platformName = $platform ? $platform->name : '-';
        $productName = $item->platformProduct ? $item->platformProduct->platform_product_name : 'Data produk tidak tersedia';
        $variant = $item->platformProduct && $item->platformProduct->variant ? $item->platformProduct->variant : '-';
        $trackingNumber = $item->tracking_number ? (string)$item->tracking_number : '-';

        $quantity = (float) ($item->export_original_qty ?? 0);
        $qtyRetur = (float) ($item->export_qty_retur ?? 0);
        $price = (float) ($item->price_after_discount ?? 0);
        $totalItem = $price * $quantity;
        $qtyTotal = (float) ($item->export_qty_total ?? 0);
        $totalInvoice = (float) ($item->export_total_invoice ?? 0);

        return [
            $index,
            $tanggal ?: '-',
            $hari ?: '-',
            "'" . ($orderNumber ?: '-'),
            $platformName ?: '-',
            $productName ?: '-',
            $variant ?: '-',
            (string)$quantity,
            (string)$qtyRetur . ' pcs',
            (string)$price,
            (string)$totalItem,
            (string)$qtyTotal,
            (string)$totalInvoice,
            "'" . ($trackingNumber ?: '-'),
        ];
    }
}
