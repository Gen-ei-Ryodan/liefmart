<?php

namespace Shared\Helpers;

class MainCategoryHelper
{
    public static function belongsToSelectedCategory($mainCategoryId): bool
    {
        if (!session()->has('main_category_id')) {
            return false;
        }

        $selectedMainCategoryId = session('main_category_id');
        
        return $mainCategoryId == $selectedMainCategoryId;
    }
    
    public static function getSelectedMainCategoryId()
    {
        return session('main_category_id');
    }
    
    public static function getSelectedMainCategoryName()
    {
        return session('main_category_name');
    }

    /**
     * Cari ID kategori kosmetik secara dinamis dari database.
     * Mencocokkan beberapa penamaan (Kosmetik, SKINCARE, Skincare),
     * fallback ke kategori aktif pertama jika tidak ditemukan.
     */
    public static function getCosmeticCategoryId()
    {
        $names = ['Kosmetik', 'SKINCARE', 'Skincare', 'COSMETIC', 'Cosmetic'];

        foreach ($names as $name) {
            $category = \App\Models\MainCategory::where('name', $name)
                ->where('is_active', true)
                ->first();
            if ($category) {
                return $category->id;
            }
        }

        $fallback = \App\Models\MainCategory::where('is_active', true)->first();
        return $fallback ? $fallback->id : null;
    }
} 
