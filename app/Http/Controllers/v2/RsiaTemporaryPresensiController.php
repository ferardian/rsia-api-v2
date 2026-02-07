<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RsiaTemporaryPresensiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = \App\Models\TemporaryPresensi::query()
            ->join('pegawai', 'temporary_presensi.id', '=', 'pegawai.id')
            ->leftJoin('departemen', 'pegawai.departemen', '=', 'departemen.dep_id')
            ->select([
                'temporary_presensi.*',
                'pegawai.nik',
                'pegawai.nama',
                'departemen.nama as nama_departemen'
            ]);

        // Search by name or nik
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pegawai.nama', 'like', "%{$search}%")
                  ->orWhere('pegawai.nik', 'like', "%{$search}%")
                  ->orWhere('departemen.nama', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('temporary_presensi.status', $request->status);
        }

        $limit = $request->query('limit', 20);
        $data = $query->orderBy('jam_datang', 'desc')->paginate($limit);

        return \App\Helpers\ApiResponse::success('Data retrieved successfully', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
