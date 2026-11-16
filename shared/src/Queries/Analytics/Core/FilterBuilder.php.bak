<?php

namespace Shared\Queries\Analytics\Core;

use Illuminate\Support\Facades\DB;

class FilterBuilder
{
    public static function dateFilter(?string $startDate, ?string $endDate, string $columnName = 'tanggal'): string
    {
        if (!$startDate || !$endDate) {
            return '';
        }
        
        $startQuoted = DB::getPdo()->quote($startDate);
        $endQuoted = DB::getPdo()->quote($endDate);
        
        return " AND {$columnName} BETWEEN {$startQuoted} AND {$endQuoted}";
    }
    
    public static function platformFilter(?int $platformId, string $columnName = 'platform_id'): string
    {
        if (!$platformId) {
            return '';
        }
        
        return " AND {$columnName} = " . intval($platformId);
    }
    
    public static function statusHariFilter(?string $statusHari, string $columnName = 'status_hari'): string
    {
        if (!$statusHari) {
            return '';
        }
        
        $statusQuoted = DB::getPdo()->quote($statusHari);
        
        return " AND (
            {$columnName} = {$statusQuoted}
            OR {$columnName} LIKE " . DB::getPdo()->quote($statusHari . ',%') . "
            OR {$columnName} LIKE " . DB::getPdo()->quote('%,' . $statusHari . ',%') . "
            OR {$columnName} LIKE " . DB::getPdo()->quote('%,' . $statusHari) . "
        )";
    }
    
    public static function returExclusionFilter(array $orderIds, string $columnName = 'order_id'): string
    {
        if (empty($orderIds)) {
            return '';
        }
        
        $ids = array_map('intval', $orderIds);
        $idsString = implode(',', $ids);
        
        return " AND {$columnName} NOT IN ({$idsString})";
    }
    
    public static function buildFilters(array $filters, array $options = []): string
    {
        $dateColumn = $options['date_column'] ?? 'tanggal';
        $platformColumn = $options['platform_column'] ?? 'platform_id';
        $statusColumn = $options['status_column'] ?? 'status_hari';
        
        $sql = '';
        
        $sql .= self::dateFilter(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            $dateColumn
        );
        
        $sql .= self::platformFilter(
            $filters['platform_id'] ?? null,
            $platformColumn
        );
        
        $sql .= self::statusHariFilter(
            $filters['status_hari'] ?? null,
            $statusColumn
        );
        
        return $sql;
    }
    
    public static function paymentDateFilter(?string $fromDate, ?string $toDate, string $tableAlias = ''): string
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $filter = '';
        
        if ($fromDate) {
            $filter .= " AND {$prefix}tanggal_masuk_pembayaran >= " . DB::getPdo()->quote($fromDate);
        }
        if ($toDate) {
            $filter .= " AND {$prefix}tanggal_masuk_pembayaran <= " . DB::getPdo()->quote($toDate);
        }
        
        return $filter;
    }
    
    public static function orderDateFilter(?string $fromDate, ?string $toDate, string $tableAlias = ''): string
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $filter = '';
        
        if ($fromDate) {
            $filter .= " AND {$prefix}tanggal_order >= " . DB::getPdo()->quote($fromDate);
        }
        if ($toDate) {
            $filter .= " AND {$prefix}tanggal_order <= " . DB::getPdo()->quote($toDate);
        }
        
        return $filter;
    }
    
    public static function saleDateFilter(?string $fromDate, ?string $toDate, string $tableAlias = ''): string
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $filter = '';
        
        if ($fromDate && $toDate) {
            $filter .= " AND {$prefix}sale_date BETWEEN " . DB::getPdo()->quote($fromDate) . " AND " . DB::getPdo()->quote($toDate);
        } elseif ($fromDate) {
            $filter .= " AND {$prefix}sale_date >= " . DB::getPdo()->quote($fromDate);
        } elseif ($toDate) {
            $filter .= " AND {$prefix}sale_date <= " . DB::getPdo()->quote($toDate);
        }
        
        return $filter;
    }
    
    public static function yearFilter(?int $year, string $tableAlias = ''): string
    {
        if (!$year) {
            return '';
        }
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        return " AND YEAR({$prefix}sale_date) = " . intval($year);
    }
    
    public static function customerFilter(?int $customerId, string $tableAlias = ''): string
    {
        if (!$customerId) {
            return '';
        }
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        return " AND {$prefix}customer_id = " . intval($customerId);
    }
    
    public static function textSearchFilter(?string $search, array $columns, string $tableAlias = ''): string
    {
        if (!$search) {
            return '';
        }
        
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $searchQuoted = DB::getPdo()->quote('%' . $search . '%');
        $conditions = [];
        
        foreach ($columns as $column) {
            $conditions[] = "{$prefix}{$column} LIKE {$searchQuoted}";
        }
        
        return " AND (" . implode(' OR ', $conditions) . ")";
    }
    
    public static function numericRangeFilter(?float $min, ?float $max, string $column, string $tableAlias = ''): string
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $filter = '';
        
        if ($min !== null) {
            $filter .= " AND {$prefix}{$column} >= " . floatval($min);
        }
        if ($max !== null) {
            $filter .= " AND {$prefix}{$column} <= " . floatval($max);
        }
        
        return $filter;
    }
    
    public static function outstandingFilter(?string $status, string $tableAlias = ''): string
    {
        if (!$status) {
            return '';
        }
        
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        
        if ($status === '0') {
            return " AND {$prefix}outstanding = 0";
        } elseif ($status === '1') {
            return " AND ({$prefix}outstanding > 0 OR {$prefix}outstanding < 0)";
        }
        
        return '';
    }
}
