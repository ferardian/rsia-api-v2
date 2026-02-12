<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WilayahController extends Controller
{
    public function getPropinsi(Request $request)
    {
        $query = DB::table('propinsi')->select('kd_prop', 'nm_prop')
            ->whereRaw('LENGTH(nm_prop) >= 3')
            ->whereRaw('nm_prop REGEXP "^[a-zA-Z]"');

        if ($request->has('q')) {
            $query->where('nm_prop', 'like', '%' . $request->q . '%');
        }

        return ApiResponse::success($query->orderBy('nm_prop', 'asc')->get());
    }

    public function getKabupaten(Request $request)
    {
        $query = DB::table('kabupaten')->select('kd_kab', 'nm_kab')
            ->whereRaw('LENGTH(nm_kab) >= 3')
            ->whereRaw('nm_kab REGEXP "^[a-zA-Z]"');
        
        if ($request->has('q')) {
            $query->where('nm_kab', 'like', '%' . $request->q . '%');
        }

        return ApiResponse::success($query->orderBy('nm_kab', 'asc')->get());
    }

    public function getKecamatan(Request $request)
    {
        $query = DB::table('kecamatan')->select('kd_kec', 'nm_kec')
            ->whereRaw('LENGTH(nm_kec) >= 3')
            ->whereRaw('nm_kec REGEXP "^[a-zA-Z]"');

        if ($request->has('q')) {
            $query->where('nm_kec', 'like', '%' . $request->q . '%');
        }

        return ApiResponse::success($query->orderBy('nm_kec', 'asc')->get());
    }

    public function getKelurahan(Request $request)
    {
        $query = DB::table('kelurahan')->select('kd_kel', 'nm_kel')
            ->whereRaw('LENGTH(nm_kel) >= 3')
            ->whereRaw('nm_kel REGEXP "^[a-zA-Z]"');

        if ($request->has('q')) {
            $query->where('nm_kel', 'like', '%' . $request->q . '%');
        }

        return ApiResponse::success($query->orderBy('nm_kel', 'asc')->get());
    }
}
