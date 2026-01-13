<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiwayatBarangMedis;

class RiwayatObatController extends Controller
{
    public function index(Request $request) 
    {
        $query = RiwayatBarangMedis::with(['barang', 'bangsal'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam', 'desc');

        // Filter by Date
        if ($request->has('tgl_awal') && $request->has('tgl_akhir')) {
            $query->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]);
        } else {
             // Default to today if no date provided? Or allow all? 
             // Usually best to limit to this month or today to avoid huge load.
             // Let's default to today if strict, or let frontend handle defaults.
             // For now, if no date, maybe limit to current month? 
             // Let's check request, if empty, maybe last 7 days.
             // $query->where('tanggal', '>=', now()->subDays(7));
             // User usually sends dates.
        }

        // Filter by Keyword (Kode Barang or Nama Barang)
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where(function($q) use ($keyword) {
                $q->where('kode_brng', 'like', "%{$keyword}%")
                  ->orWhereHas('barang', function($sq) use ($keyword) {
                      $sq->where('nama_brng', 'like', "%{$keyword}%");
                  })
                  ->orWhere('no_batch', 'like', "%{$keyword}%")
                  ->orWhere('no_faktur', 'like', "%{$keyword}%");
            });
        }

        // Filter by Bangsal
        if ($request->has('kd_bangsal') && !empty($request->kd_bangsal)) {
            $query->where('kd_bangsal', $request->kd_bangsal);
        }

        $perPage = $request->get('limit', 20);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data Riwayat Obat berhasil diambil',
            'data' => $data
        ]);
    }
    public function summary(Request $request)
    {
        // 1. Determine which columns (posisi) to pivot
        // If user filters specific 'status' (which correlates to 'posisi' enum), use those.
        // Otherwise, use all available or a default set? 
        // User said: "nnti data baru munucul saat tombol tampilkan di klik", implying filters are set.
        // We use $request->posisi (mapped to 'posisi' column in DB).
        
        $requestedPosisi = $request->input('posisi', []);
        
        // If no status selected, we might want to return empty or all?
        // Let's assume if empty, we pick up all distinct 'posisi' from DB or just return generic count?
        // User instruction: "misal centang Penjualan dan Resep Pulang, nnti yang muncul jumlah ada kolom tersebut"
        // So we strictly follow requested statuses.
        
        if (empty($requestedPosisi)) {
             return response()->json([
                'success' => true,
                'data' => [
                   'current_page' => 1,
                   'data' => [],
                   'total' => 0
                ],
                'message' => 'Silakan pilih jenis transaksi (posisi) terlebih dahulu'
             ]);
        }

        // Build Select Clause dynamically
        $selects = [
            'riwayat_barang_medis.kode_brng',
            // We need nama_brng. We can join or get it via relation.
            // Since we group by kode_brng, we can't easily select nama_brng without grouping or aggregation.
            // But usually mysql allows it if strict mode is off, or we use ANY_VALUE.
            // Safer: Load 'barang' relation.
        ];
        
        $selectRawParts = ['riwayat_barang_medis.kode_brng'];
        
        foreach ($requestedPosisi as $posisi) {
            // sanitize literal string for SQL just in case, though usually bounded by enum
            // We use SUM(CASE WHEN posisi = ? THEN (masuk + keluar) ELSE 0 END) as 'alias'
            // This covers both IN (Penerimaan) and OUT (Pemberian) transactions for specific columns.
            $alias = \Str::slug($posisi, '_');
            $selectRawParts[] = "COALESCE(SUM(CASE WHEN posisi = '{$posisi}' THEN (masuk + keluar) ELSE 0 END), 0) as `{$alias}`";
        }

        // STRATEGY: Hybrid Approach (Transaction-First vs Master-First)
        // 1. If searching by Item Name (Keyword): Use Master-First (DataBarang). 
        //    We filter the small list of matching items, then check history.
        // 2. If browsing (No Keyword): Use Transaction-First (RiwayatBarangMedis).
        //    We find distinct items that ACTUALLY moved in the date range. This avoids checking 
        //    thousands of inactive items in DataBarang one by one (which causes the timeout).

        $perPage = $request->get('limit', 20);
        // STRATEGY: Master-First Only (DataBarang Driver)
        // We ALWAYS query DataBarang first.
        // Reason: The user wants "Order By Name". 
        // 1. Browsing 'Riwayat' (Transaction-First) with 'Order By Name' requires: 
        //    SELECT DISTINCT kode_brng FROM riwayat JOIN databarang ORDER BY databarang.nama_brng
        //    This causes a massive temporary table and FILESORT on millions of rows -> TIMEOUT.
        // 2. Querying 'DataBarang' (Master-First):
        //    SELECT * FROM databarang WHERE EXISTS(riwayat...) ORDER BY nama_brng
        //    This scans the small Master table (e.g. 5k rows), checks EXISTS (fast index lookup), and sorts natively.
        // This is significantly faster for this specific requirement.

        $perPage = $request->get('limit', 20);
        
        $query = \App\Models\DataBarang::query();
        $query->select('kode_brng', 'nama_brng', 'kode_sat', 'kdjns');
        
        // 1. Filter Keyword (if any)
        if ($request->has('keyword') && !empty($request->keyword)) {
            $query->where(function($q) use ($request) {
                $q->where('nama_brng', 'like', "%{$request->keyword}%")
                  ->orWhere('kode_brng', 'like', "%{$request->keyword}%");
            });
        }

        // 2. Filter Active History (EXISTS)
        $query->whereHas('riwayat', function($q) use ($requestedPosisi, $request) {
            if ($request->has('tgl_awal') && $request->has('tgl_akhir')) {
               $q->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]);
            }
            if (!empty($requestedPosisi)) {
                $q->whereIn('posisi', $requestedPosisi);
            }
            if ($request->has('kd_bangsal') && !empty($request->kd_bangsal)) {
               $q->where('kd_bangsal', $request->kd_bangsal);
            }
        });
        
        $query->orderBy('nama_brng');
        $query->with(['satuan']);
        $data = $query->simplePaginate($perPage);


        // === COMMON: AGGREGATE CALCULATIONS ===
        // Now valid for both strategies.
        $itemCodes = $data->pluck('kode_brng')->toArray();

        if (!empty($itemCodes)) {
            $aggregates = \App\Models\RiwayatBarangMedis::selectRaw('kode_brng, posisi, SUM(masuk + keluar) as total_qty')
                ->whereIn('kode_brng', $itemCodes)
                ->whereIn('posisi', $requestedPosisi)
                ->when($request->has('tgl_awal'), fn($q) => $q->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]))
                ->when($request->has('kd_bangsal') && !empty($request->kd_bangsal), fn($q) => $q->where('kd_bangsal', $request->kd_bangsal))
                ->groupBy('kode_brng', 'posisi')
                ->get()
                ->groupBy('kode_brng');

            $data->getCollection()->transform(function ($item) use ($aggregates, $requestedPosisi) {
                // Ensure item is not null (from filter above)
                if (!$item) return null;

                $itemStats = $aggregates->get($item->kode_brng, collect());
                
                $totalSelected = 0;
                foreach ($requestedPosisi as $posisi) {
                    $alias = \Str::slug($posisi, '_');
                    $stat = $itemStats->firstWhere('posisi', $posisi);
                    $qty = $stat ? $stat->total_qty : 0;
                    
                    $item->setAttribute($alias, $qty);
                    $totalSelected += $qty;
                }
                $item->setAttribute('total_selected', $totalSelected);

                return $item;
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Ringkasan berhasil diambil',
            'data' => $data,
            'columns' => $requestedPosisi // Send back columns so frontend knows what to render
        ]);
    }   

    public function statuses()
    {
        // Get distinct statuses from DB to populate filter
        // Use cache to performance if needed, but for now direct query
        // Limit to recent data if table is huge, or just all distinct
        $statuses = \App\Models\RiwayatBarangMedis::select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');
            
        return response()->json([
            'success' => true,
            'data' => $statuses
        ]);
    }

    public function export(\Illuminate\Http\Request $request) 
    {
        // Increase time limit for export
        set_time_limit(300); // 5 minutes

        $requestedPosisi = $request->input('posisi', []);
        
        // Headers for CSV
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=riwayat_obat_summary.csv',
            'Expires'             => '0',
            'Pragma'              => 'public'
        ];

        $callback = function() use ($request, $requestedPosisi) {
            $file = fopen('php://output', 'w');
            
            // Write Header
            $csvHeaders = ['Kode Barang', 'Nama Barang', 'Satuan'];
            foreach ($requestedPosisi as $posisi) {
                $csvHeaders[] = $posisi; // Use actual name, not slug
            }
            $csvHeaders[] = 'Total';
            fputcsv($file, $csvHeaders);

            // === REUSE LOGIC: Hybrid Approach ===
            // We use chucking loop instead of pagination.

            $hasKeyword = $request->has('keyword') && !empty($request->keyword);
            
            // Helper to process a chunk of items
            $processChunk = function($itemCodes) use ($file, $requestedPosisi, $request) {
                 if (empty($itemCodes)) return;

                 // 1. Fetch Item Details
                 $items = \App\Models\DataBarang::with(['satuan'])
                    ->whereIn('kode_brng', $itemCodes)
                    ->get()
                    ->keyBy('kode_brng');

                 // 2. Fetch Aggregates
                 $aggregates = \App\Models\RiwayatBarangMedis::selectRaw('kode_brng, posisi, SUM(masuk + keluar) as total_qty')
                    ->whereIn('kode_brng', $itemCodes)
                    ->whereIn('posisi', $requestedPosisi)
                    ->when($request->has('tgl_awal'), fn($q) => $q->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]))
                    ->when($request->has('kd_bangsal') && !empty($request->kd_bangsal), fn($q) => $q->where('kd_bangsal', $request->kd_bangsal))
                    ->groupBy('kode_brng', 'posisi')
                    ->get()
                    ->groupBy('kode_brng');

                 // 3. Merge and Write
                 foreach ($itemCodes as $code) {
                     $item = $items->get($code);
                     if (!$item) continue;

                     $itemStats = $aggregates->get($code, collect());
                     
                     $row = [
                         $item->kode_brng,
                         $item->nama_brng,
                         $item->satuan ? $item->satuan->satuan : '-',
                     ];

                     $totalSelected = 0;
                     foreach ($requestedPosisi as $posisi) {
                         $stat = $itemStats->firstWhere('posisi', $posisi);
                         $qty = $stat ? $stat->total_qty : 0;
                         $row[] = $qty; // Number
                         $totalSelected += $qty;
                     }
                     $row[] = $totalSelected;

                     fputcsv($file, $row);
                 }
            };

            // === EXECUTION ===
            $chunkSize = 250; 

            // Always use Master-First for exporting too, to match the UI order and performance
            \App\Models\DataBarang::query()
                ->select('kode_brng')
                ->whereHas('riwayat', function($q) use ($requestedPosisi, $request) {
                    if ($request->has('tgl_awal') && $request->has('tgl_akhir')) {
                       $q->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]);
                    }
                    if (!empty($requestedPosisi)) {
                        $q->whereIn('posisi', $requestedPosisi);
                    }
                    if ($request->has('kd_bangsal') && !empty($request->kd_bangsal)) {
                       $q->where('kd_bangsal', $request->kd_bangsal);
                    }
                })
                ->when($hasKeyword, function($query) use ($request) {
                    $query->where(function($q) use ($request) {
                        $q->where('nama_brng', 'like', "%{$request->keyword}%")
                          ->orWhere('kode_brng', 'like', "%{$request->keyword}%");
                    });
                })
                ->orderBy('nama_brng')
                ->chunk($chunkSize, function($items) use ($processChunk) {
                    $processChunk($items->pluck('kode_brng')->toArray());
                });
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }


    public function lastStock(Request $request)
    {
        $limit = $request->get('limit', 20);
        $date = $request->get('tanggal', date('Y-m-d'));

        // Master-First Strategy for Stock Balance
        // 1. Get Base Items from DataBarang
        $query = \App\Models\DataBarang::with('satuan')
            ->where('status', '1');

        // 2. Filter by Keyword (if any)
        if ($request->has('keyword') && !empty($request->keyword)) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_brng', 'like', "%{$request->keyword}%")
                  ->orWhere('kode_brng', 'like', "%{$request->keyword}%");
            });
        }

        // 3. Add Subquery for Last Stock Calculation
        // We find the LATEST transaction for each item ON OR BEFORE the selected date
        // and take its stok_akhir.
        $query->addSelect([
            'last_stock' => \App\Models\RiwayatBarangMedis::select('stok_akhir')
                ->whereColumn('kode_brng', 'databarang.kode_brng')
                ->whereDate('tanggal', '<=', $date) // Transactions up to this date
                ->orderBy('tanggal', 'desc')        // Latest date first
                ->orderBy('jam', 'desc')            // Latest time first
                ->limit(1)
        ]);

        // 5. Paginate with the calculated column
        $data = $query->orderBy('nama_brng')->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function exportLastStock(Request $request) 
    {
        $date = $request->get('tanggal', date('Y-m-d'));
        $fileName = 'stok_akhir_' . $date . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($request, $date) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, ['Kode Barang', 'Nama Barang', 'Satuan', 'Stok Akhir']);

            // Master-First Query similar to lastStock but chunked
            $query = \App\Models\DataBarang::with('satuan')
                ->where('status', '1');

            if ($request->has('keyword') && !empty($request->keyword)) {
                $query->where(function ($q) use ($request) {
                    $q->where('nama_brng', 'like', "%{$request->keyword}%")
                      ->orWhere('kode_brng', 'like', "%{$request->keyword}%");
                });
            }

            // Subquery logic for export
            $query->addSelect([
                'last_stock' => \App\Models\RiwayatBarangMedis::select('stok_akhir')
                    ->whereColumn('kode_brng', 'databarang.kode_brng')
                    ->whereDate('tanggal', '<=', $date)
                    ->orderBy('tanggal', 'desc')
                    ->orderBy('jam', 'desc')
                    ->limit(1)
            ]);

            $chunkSize = 500;
            $query->orderBy('nama_brng')->chunk($chunkSize, function($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->kode_brng,
                        $item->nama_brng,
                        $item->satuan->satuan ?? $item->kode_sat,
                        $item->last_stock ?? 0
                    ]);
                }
            });
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
