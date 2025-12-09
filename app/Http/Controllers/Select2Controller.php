<?php

namespace App\Http\Controllers;

use App\Helpers\Select2 as Select2Helper;
use Illuminate\Http\Request;

class Select2Controller extends Controller
{
    public function karyawanUsers(Request $request)
    {
        return Select2Helper::karyawanUsers($request);
    }

    public function companies(Request $request)
    {
        return Select2Helper::companies($request);
    }
}
