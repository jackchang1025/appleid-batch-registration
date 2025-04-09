<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IpInfoController extends Controller
{
    public function index()
    {
        return response()->json([
            'ip' => request()->ip(),
            'headers' => request()->header(),
            'body' => request()->all(),
        ]);
    }
}
