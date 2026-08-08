<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinanceController extends Controller
{


    public function index()
    {
        return view('financial.choose');
    }

    public function choose()
    {
        return view('financial.choose');
    }
} 