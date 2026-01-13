<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaRekapInmut;
use App\Models\RsiaMasterInmut;
use App\Models\RsiaAnalisaInmut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringIndikatorMutuController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $bulan = $request->input('bulan', date('Y-m')); // Format Y-m
        $dep_id = $request->input('dep_id');

        // Extract year and month
        $parts = explode('-', $bulan);
        $year = $parts[0];
        $month = $parts[1];

        // Base query for aggregated data from rsia_rekap_inmut
        // We aggregate num and denum by id_inmut for the selected month
        $rekapQuery = RsiaRekapInmut::select(
                'id_inmut',
                DB::raw('SUM(num) as total_num'),
                DB::raw('SUM(denum) as total_denum'),
                DB::raw('MAX(tanggal_input) as last_update')
            )
            ->whereYear('tanggal_inmut', $year)
            ->whereMonth('tanggal_inmut', $month)
            ->groupBy('id_inmut');

        if ($dep_id) {
            $rekapQuery->where('dep_id', $dep_id);
        }

        // We need to join this with Master Indicators to show even those with 0 data?
        // Actually, usually we show ALL active indicators for the unit, and fill in the data.
        
        $indicators = RsiaMasterInmut::select(
                'rsia_master_inmut.*',
                'u.nama_inmut as nama_inmut_utama',
                'u.satuan as satuan_utama', 
                'u.standar as standar_utama',
                'u.rumus as rumus_utama'
            )
            ->leftJoin('rsia_master_inmut_utama as u', 'u.id_master', '=', 'rsia_master_inmut.id_master')
            ->where('rsia_master_inmut.status', '1');

        if ($dep_id) {
            $indicators->where('rsia_master_inmut.dep_id', $dep_id);
        }

        if ($request->has('keyword') && !empty($request->input('keyword'))) {
            $keyword = $request->input('keyword');
            $indicators->where('rsia_master_inmut.nama_inmut', 'like', "%{$keyword}%");
        }

        $indicators = $indicators->paginate($limit);

        // Fetch rekap data for the indicators in current page
        $indicatorIds = $indicators->pluck('id_inmut')->toArray();
        $rekapData = $rekapQuery->whereIn('id_inmut', $indicatorIds)->get()->keyBy('id_inmut');

        // Merge aggregation into indicators
        $indicators->getCollection()->transform(function ($item) use ($rekapData) {
            $rekap = $rekapData->get($item->id_inmut);
            $item->total_num = $rekap ? $rekap->total_num : 0;
            $item->total_denum = $rekap ? $rekap->total_denum : 0;
            
            // Calculate Score
            // Logic depends on formula, but usually (Num / Denum) * 100 or just Num/Denum
            // Using generic percentage for now if denum > 0
            if ($item->total_denum > 0) {
                // Check if formula exists in item or master utama
                $item->score = round(($item->total_num / $item->total_denum) * 100, 2);
            } else {
                $item->score = 0;
            }
            
            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data Monitoring Inmut berhasil diambil',
            'data' => $indicators
        ]);
    }

    public function getUnits()
    {
        // Get distinct departments from active indicators
        $units = RsiaMasterInmut::select('dep_id', 'nama_ruang')
            ->where('status', '1')
            ->distinct()
            ->orderBy('nama_ruang')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }

    public function getAnalisa(Request $request)
    {
        $limit = $request->input('limit', 10);
        $bulan = $request->input('bulan', date('Y-m')); // Format Y-m
        $dep_id = $request->input('dep_id');

        // Extract year and month
        $parts = explode('-', $bulan);
        $year = $parts[0];
        $month = $parts[1];

        // Start Date and End Date for the month
        $startDate = $year . '-' . $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $analisaQuery = RsiaAnalisaInmut::with(['indikator.masterUtama'])
            ->where('tanggal_awal', '>=', $startDate)
            ->where('tanggal_akhir', '<=', $endDate);

        if ($dep_id) {
            $analisaQuery->where('dep_id', $dep_id);
        }

        if ($request->has('keyword') && !empty($request->input('keyword'))) {
            $keyword = $request->input('keyword');
            $analisaQuery->where(function($q) use ($keyword) {
                $q->where('analisa', 'like', "%{$keyword}%")
                  ->orWhere('nama_inmut', 'like', "%{$keyword}%");
            });
        }

        $analisa = $analisaQuery->orderBy('id_analisa', 'DESC')->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Data Analisa Inmut berhasil diambil',
            'data' => $analisa
        ]);
    }

    public function storeAnalisa(Request $request)
    {
        $validated = $request->validate([
            'id_inmut' => 'required|exists:rsia_master_inmut,id_inmut',
            'analisa' => 'required',
            'tindak_lanjut' => 'required',
            'jml_num' => 'required|numeric',
            'jml_denum' => 'required|numeric',
            'bulan' => 'required|date_format:Y-m',
        ]);

        $indicator = RsiaMasterInmut::find($validated['id_inmut']);
        
        $parts = explode('-', $validated['bulan']);
        $year = $parts[0];
        $month = $parts[1];
        $startDate = $year . '-' . $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $score = 0;
        if ($validated['jml_denum'] > 0) {
            $score = round(($validated['jml_num'] / $validated['jml_denum']) * 100, 2);
        }

        $analisa = RsiaAnalisaInmut::create([
            'id_inmut' => $validated['id_inmut'],
            'nama_inmut' => $indicator->nama_inmut,
            'nama_ruang' => $indicator->nama_ruang ?? '',
            'dep_id' => $indicator->dep_id,
            'analisa' => $validated['analisa'],
            'tindak_lanjut' => $validated['tindak_lanjut'],
            'jml_num' => $validated['jml_num'],
            'jml_denum' => $validated['jml_denum'],
            'jumlah' => $score,
            'tanggal_awal' => $startDate,
            'tanggal_akhir' => $endDate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data Analisa Inmut berhasil disimpan',
            'data' => $analisa
        ]);
    }

    public function updateAnalisa(Request $request, $id)
    {
         $validated = $request->validate([
            'analisa' => 'required',
            'tindak_lanjut' => 'required',
        ]);

        $analisa = RsiaAnalisaInmut::find($id);
        if (!$analisa) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $analisa->update([
            'analisa' => $validated['analisa'],
            'tindak_lanjut' => $validated['tindak_lanjut'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data Analisa Inmut berhasil diperbarui',
            'data' => $analisa
        ]);
    }

    public function deleteAnalisa($id)
    {
        $analisa = RsiaAnalisaInmut::find($id);
        if (!$analisa) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $analisa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Analisa Inmut berhasil dihapus'
        ]);
    }

    public function getLaporan(Request $request)
    {
        $limit = $request->input('limit', 10);
        $tahun = $request->input('tahun', date('Y'));
        $tipe = $request->input('tipe'); // triwulan, semester
        $periode = $request->input('periode'); // 1, 2, 3, 4
        $dep_id = $request->input('dep_id');
        $jenis = $request->input('jenis', 'semua'); // semua, group

        $startDate = null;
        $endDate = null;

        if ($tipe == 'triwulan') {
            switch ($periode) {
                case 1: $startDate = "$tahun-01-01"; $endDate = "$tahun-03-31"; break;
                case 2: $startDate = "$tahun-04-01"; $endDate = "$tahun-06-30"; break;
                case 3: $startDate = "$tahun-07-01"; $endDate = "$tahun-09-30"; break;
                case 4: $startDate = "$tahun-10-01"; $endDate = "$tahun-12-31"; break;
            }
        } elseif ($tipe == 'semester') {
            switch ($periode) {
                case 1: $startDate = "$tahun-01-01"; $endDate = "$tahun-06-30"; break;
                case 2: $startDate = "$tahun-07-01"; $endDate = "$tahun-12-31"; break;
            }
        }

        if (!$startDate || !$endDate) {
            return response()->json(['success' => false, 'message' => 'Parameter periode tidak valid'], 400);
        }

        if ($jenis == 'group') {
             // === GROUPED BY MAIN INDICATOR ===
             
             // Aggregate data grouping by id_master
             $rekapQuery = RsiaRekapInmut::join('rsia_master_inmut as m', 'm.id_inmut', '=', 'rsia_rekap_inmut.id_inmut')
                ->select(
                    'm.id_master',
                    DB::raw('SUM(rsia_rekap_inmut.num) as total_num'),
                    DB::raw('SUM(rsia_rekap_inmut.denum) as total_denum')
                )
                ->whereBetween('rsia_rekap_inmut.tanggal_inmut', [$startDate, $endDate])
                ->groupBy('m.id_master');
            
            if ($dep_id) {
                $rekapQuery->where('m.dep_id', $dep_id);
            }

            // Fetch Main Indicators
            $indicators = RsiaMasterInmutUtama::query();
             if ($request->has('keyword') && !empty($request->input('keyword'))) {
                $keyword = $request->input('keyword');
                $indicators->where('nama_inmut', 'like', "%{$keyword}%");
            }
            
            // Note: filtering by dep_id on Utama might be tricky if Utama doesn't have dep_id (it usually doesn't).
            // Usually filtering by dep_id implies filtering the *data source* (as done in rekapQuery).
            // But we should only show indicators that have data or are relevant? 
            // For now, let's list all Utama indicators provided they match keyword.
            
            $indicators = $indicators->paginate($limit);
            
            $indicatorIds = $indicators->pluck('id_master')->toArray();
            $rekapData = $rekapQuery->whereIn('m.id_master', $indicatorIds)->get()->keyBy('id_master');
            
            $indicators->getCollection()->transform(function ($item) use ($rekapData) {
                $rekap = $rekapData->get($item->id_master);
                $item->total_num = $rekap ? $rekap->total_num : 0;
                $item->total_denum = $rekap ? $rekap->total_denum : 0;
                
                // For 'Group', the item IS the master utama, so fields are direct
                $item->nama_inmut_utama = $item->nama_inmut; // map for consistency frontend
                $item->standar_utama = $item->standar;
                $item->rumus_utama = $item->rumus;
                $item->satuan_utama = $item->satuan;
                
                if ($item->total_denum > 0) {
                    $item->score = round(($item->total_num / $item->total_denum) * 100, 2);
                } else {
                    $item->score = 0;
                }
                
                return $item;
            });

        } else {
            // === ALL UNITS (EXISTING LOGIC) ===

            // Aggregate data based on range
            $rekapQuery = RsiaRekapInmut::select(
                    'id_inmut',
                    DB::raw('SUM(num) as total_num'),
                    DB::raw('SUM(denum) as total_denum')
                )
                ->whereBetween('tanggal_inmut', [$startDate, $endDate])
                ->groupBy('id_inmut');

            if ($dep_id) {
                $rekapQuery->where('dep_id', $dep_id);
            }

            $indicators = RsiaMasterInmut::select(
                    'rsia_master_inmut.*',
                    'u.nama_inmut as nama_inmut_utama',
                    'u.satuan as satuan_utama', 
                    'u.standar as standar_utama',
                    'u.rumus as rumus_utama'
                )
                ->leftJoin('rsia_master_inmut_utama as u', 'u.id_master', '=', 'rsia_master_inmut.id_master')
                ->where('rsia_master_inmut.status', '1');

            if ($dep_id) {
                $indicators->where('rsia_master_inmut.dep_id', $dep_id);
            }

            if ($request->has('keyword') && !empty($request->input('keyword'))) {
                $keyword = $request->input('keyword');
                $indicators->where('rsia_master_inmut.nama_inmut', 'like', "%{$keyword}%");
            }

            $indicators = $indicators->paginate($limit);

            $indicatorIds = $indicators->pluck('id_inmut')->toArray();
            $rekapData = $rekapQuery->whereIn('id_inmut', $indicatorIds)->get()->keyBy('id_inmut');

            $indicators->getCollection()->transform(function ($item) use ($rekapData) {
                $rekap = $rekapData->get($item->id_inmut);
                $item->total_num = $rekap ? $rekap->total_num : 0;
                $item->total_denum = $rekap ? $rekap->total_denum : 0;
                
                if ($item->total_denum > 0) {
                    $item->score = round(($item->total_num / $item->total_denum) * 100, 2);
                } else {
                    $item->score = 0;
                }
                
                return $item;
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan Mutu berhasil diambil',
            'data' => $indicators,
            'periode_info' => [
                'start' => $startDate,
                'end' => $endDate,
                'tipe' => $tipe,
                'periode' => $periode,
                'tahun' => $tahun,
                'jenis' => $jenis
            ]
        ]);
    }

    public function getRealisasi(Request $request)
    {
        $request->validate([
            'dep_id' => 'required',
            // 'tgl_transaksi' => 'required|date', // Now optional if month/year provided
        ]);

        $depId = $request->input('dep_id');
        $query = RsiaRekapInmut::where('dep_id', $depId);

        if ($request->has('tgl_transaksi')) {
            $query->whereDate('tanggal_inmut', $request->input('tgl_transaksi'));
        } elseif ($request->has('bulan') && $request->has('tahun')) {
            // Monthly fetch
            $query->whereMonth('tanggal_inmut', $request->input('bulan'))
                  ->whereYear('tanggal_inmut', $request->input('tahun'));
            
            if ($request->has('id_inmut')) {
                $query->where('id_inmut', $request->input('id_inmut'));
            }
        } else {
             // Fallback or error? defaulting to today if nothing provided might be safer or just return empty
             // adhering to previous logic: strict validation likely expected by frontend
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Data realisasi berhasil diambil',
            'data' => $data
        ]);
    }

    public function storeRealisasi(Request $request)
    {
        // ... (existing single store logic) ...
        return $this->processStore($request->all());
    }

    public function storeRealisasiBulk(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.id_inmut' => 'required',
            'data.*.tgl_transaksi' => 'required|date',
            'data.*.dep_id' => 'required',
            'data.*.numerator' => 'required|numeric',
            'data.*.denominator' => 'required|numeric',
        ]);

        $items = $request->input('data');
        
        // Debug Log
        \Log::info("Bulk Store Request: Received " . count($items) . " items.");
        foreach ($items as $itm) {
             if ($itm['numerator'] > 0 || $itm['denominator'] > 0) {
                 \Log::info("Processing Item: " . $itm['tgl_transaksi'] . " | Num: " . $itm['numerator']);
             }
        }

        $results = [];
        $successCount = 0;

        $errors = [];
        
        DB::beginTransaction();
        try {
            foreach ($items as $index => $item) {
                try {
                    // Skip if completely empty (both 0), unless we want to force-save 0? 
                    // Let's assume frontend sends 0 if it wants to save 0.
                    $this->processStore($item);
                    $successCount++;
                    \Log::info("Item " . ($index + 1) . " processed successfully.");
                } catch (\Exception $inner) {
                    \Log::error("Failed processing item " . ($index + 1) . ": " . $inner->getMessage());
                    $errors[] = "Tgl " . ($item['tgl_transaksi'] ?? '?') . ": " . $inner->getMessage();
                    // Do NOT throw. Continue to next item. 
                }
            }
            DB::commit();

            $msg = "Disimpan: $successCount data.";
            if (count($errors) > 0) {
                $msg .= " Gagal: " . count($errors) . ". Rincian: " . implode(", ", $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Bulk Store Transaction Failed: " . $e->getMessage());
             return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processStore($data) 
    {
        $idInmut = $data['id_inmut'];
        $tgl = $data['tgl_transaksi'];
        $depId = $data['dep_id'];
        $num = $data['numerator'];
        $denum = $data['denominator'];

        // Find existing record
        // Find existing record(s) - Handle duplicates if any
        $rekaps = RsiaRekapInmut::where('id_inmut', $idInmut)
            ->where('dep_id', $depId)
            ->whereDate('tanggal_inmut', $tgl)
            ->get();

        if ($rekaps->count() > 0) {
            // Update the first one
            $rekap = $rekaps->first();
            
            // Delete duplicates if any
            if ($rekaps->count() > 1) {
                foreach ($rekaps as $index => $r) {
                    if ($index === 0) continue; // Skip first
                    $r->delete();
                }
            }

            $rekap->num = $num;
            $rekap->denum = $denum;
            $rekap->tanggal_input = now();

            // Should we update names on Update? 
            // Better to fetch master again if we want to be safe, OR just skip name update on 'Update'
            // But previous code didn't update names on 'Update'. Let's stick to that to minimize risk.
            // ONLY UPDATE VALUES.
            
            $rekap->save();
            return $rekap;
        } else {
            // Create New
            $master = RsiaMasterInmut::where('id_inmut', $idInmut)->first();
            $dept = \App\Models\Departemen::where('dep_id', $depId)->first();
            
            if (!$master) {
                throw new \Exception("Indikator ID $idInmut tidak ditemukan");
            }

            $rekap = new RsiaRekapInmut();
            $rekap->id_inmut = $idInmut;
            $rekap->dep_id = $depId;
            $rekap->tanggal_inmut = $tgl;
            $rekap->num = $num;
            $rekap->denum = $denum;
            $rekap->tanggal_input = now();
            
            // Truncate strings to prevent SQL errors if column is too short
            $rekap->nama_inmut = substr($master->nama_inmut, 0, 100);
            
            $namaRuang = $dept ? $dept->nama : ($master->nama_ruang ?? '-');
            $rekap->nama_ruang = substr($namaRuang, 0, 50); 
            
            $rekap->save();
            return $rekap;
        }
    }
}
