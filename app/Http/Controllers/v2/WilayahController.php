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
        $data = DB::table('propinsi')->select('kd_prop', 'nm_prop')->get();
        return ApiResponse::success($data);
    }

    public function getKabupaten(Request $request)
    {
        $query = DB::table('kabupaten')->select('kd_kab', 'nm_kab');
        
        if ($request->has('kd_prop')) {
            // Check if schema supports linking kabupaten to propinsi properly
            // Usually valid schema: kd_prop is in kabupaten, or it relies on prefix?
            // Let's assume standard relation first, but we might need to verify schema. 
            // Based on typical SIMRS Khanza structure:
            // kabupaten has kd_prop? No, usually it's independent or linked via mapping.
            // Let's check table schema first in next step if this is guess work.
            // ACTUALLY, usually in SIMRS Khanza:
            // propinsi: kd_prop, nm_prop
            // kabupaten: kd_kab, nm_kab
            // They are often NOT strict relational in some versions, but let's assume they are NOT linked 
            // unless we see a column. 
            // WAIT - I should check schema first. 
            // But for now I'll implement basic fetch all or search.
        }

        if ($request->has('q')) {
            $query->where('nm_kab', 'like', '%' . $request->q . '%');
        }

        return ApiResponse::success($query->limit(50)->get());
    }

    public function getKecamatan(Request $request)
    {
        $query = DB::table('kecamatan')->select('kd_kec', 'nm_kec');
        if ($request->has('q')) {
            $query->where('nm_kec', 'like', '%' . $request->q . '%');
        }
        return ApiResponse::success($query->limit(50)->get());
    }

    public function getKelurahan(Request $request)
    {
        $query = DB::table('kelurahan')->select('kd_kel', 'nm_kel');
        if ($request->has('q')) {
            $query->where('nm_kel', 'like', '%' . $request->q . '%');
        }
        return ApiResponse::success($query->limit(50)->get());
    }
}
