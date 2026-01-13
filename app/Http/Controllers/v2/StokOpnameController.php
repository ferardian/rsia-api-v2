<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Opname;
use App\Models\GudangBarang;
use App\Models\Bangsal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Traits\LogsToTracker;

class StokOpnameController extends Controller
{
    use LogsToTracker;
    public function index(Request $request)
    {
        $query = Opname::select('opname.*')
            ->join('databarang', 'opname.kode_brng', '=', 'databarang.kode_brng')
            ->with(['barang.satuan', 'bangsal'])
            ->orderBy('opname.tanggal', 'desc')
            ->orderBy('databarang.nama_brng');

        if ($request->has('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } else {
            // Default to today
            $query->whereDate('tanggal', Carbon::now());
        }

        if ($request->has('kd_bangsal')) {
            $query->where('kd_bangsal', $request->kd_bangsal);
        }

        $data = $query->paginate($request->limit ?? 15);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        // Validation
        $request->validate([
            'kode_brng' => 'required',
            'kd_bangsal' => 'required',
            'no_batch' => 'required',
            'no_faktur' => 'required',
            'real' => 'required|numeric',
            'h_beli' => 'required|numeric',
        ]);

        $tanggal = $request->tanggal ?? Carbon::now()->format('Y-m-d');
        
        // Find current stock in GudangBarang
        $gudang = GudangBarang::where([
            'kode_brng' => $request->kode_brng,
            'kd_bangsal' => $request->kd_bangsal,
            'no_batch' => $request->no_batch,
            'no_faktur' => $request->no_faktur,
        ])->first();

        if (!$gudang) {
            // If item doesn't exist in that location/batch, assume stock 0? 
            // Or create new entry? Usually opname is on existing stock.
            // But let's handle case where we found a batch not recorded.
            $stok_awal = 0;
            // Proceed to create gudang entry later if needed, OR fail. 
            // For safety, let's create a temporary object or fail.
            // Ideally validation should check this, but for "Finding items", user might input new batch?
            // User request says "abaikan kondisi aktifkan batch", so we assume standard flow.
            
            // Let's create a new GudangBarang instance if not found (Adding new stock via opname?)
            // Usually opname is adjustment. If it doesn't exist, initial stock is 0.
             $gudang = new GudangBarang([
                'kode_brng' => $request->kode_brng,
                'kd_bangsal' => $request->kd_bangsal,
                'no_batch' => $request->no_batch,
                'no_faktur' => $request->no_faktur,
                'stok' => 0
            ]);
        }

        $stok_awal = $gudang->stok;
        $real = $request->real;
        $diff = $real - $stok_awal;

        // Calculate missing/loss or excess
        $selisih = $diff; // Can be negative (loss) or positive (gain)
        
        $nomihilang = 0;
        $lebih = 0;
        $nomilebih = 0;

        if ($selisih < 0) {
            // Hilang / Loss
            // abs(selisih) * h_beli
            $nomihilang = abs($selisih) * $request->h_beli;
        } elseif ($selisih > 0) {
            // Lebih / Gain
            $lebih = $selisih;
            $nomilebih = $selisih * $request->h_beli;
        }

        DB::beginTransaction();
        try {
            // 1. Create Opname Record
            Opname::create([
                'kode_brng' => $request->kode_brng,
                'h_beli' => $request->h_beli,
                'tanggal' => $tanggal,
                'stok' => $stok_awal,
                'real' => $real,
                'selisih' => $selisih, // The diff quantity
                'nomihilang' => $nomihilang, // Value loss
                'lebih' => $lebih, // Excess qty (if positive)
                'nomilebih' => $nomilebih, // Value gain
                'keterangan' => $request->keterangan ?? '-',
                'kd_bangsal' => $request->kd_bangsal,
                'no_batch' => $request->no_batch,
                'no_faktur' => $request->no_faktur,
            ]);

            // 2. Update Stock in GudangBarang
            // If it was a new instance (stok 0), we save it.
            $gudang->stok = $real;
            $gudang->save();

            // Log to trackersql
            $keterangan = $request->keterangan ?? '-';
            $sqlLog = "INSERT INTO opname VALUES ('{$request->kode_brng}', '{$request->h_beli}', '$tanggal', '$stok_awal', '$real', '$selisih', '$nomihilang', '$lebih', '$nomilebih', '$keterangan', '{$request->kd_bangsal}', '{$request->no_batch}', '{$request->no_faktur}')";
            $this->logTracker($sqlLog, $request);

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Stok Opname berhasil disimpan',
                'data' => [
                    'selisih' => $selisih,
                    'stok_akhir' => $real
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan Stok Opname: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getItems(Request $request)
    {
        $kd_bangsal = $request->kd_bangsal;
        if (!$kd_bangsal) {
            return response()->json(['success' => false, 'message' => 'Kode Bangsal diperlukan'], 400);
        }

        // Use Query Builder for performance and flat structure
        // Select ALL active items from databarang, LEFT JOIN with gudangbarang filtered by location
        $query = DB::table('databarang')
            ->leftJoin('gudangbarang', function($join) use ($kd_bangsal) {
                $join->on('databarang.kode_brng', '=', 'gudangbarang.kode_brng')
                     ->where('gudangbarang.kd_bangsal', '=', $kd_bangsal);
            })
            ->leftJoin('jenis', 'databarang.kdjns', '=', 'jenis.kdjns')
            ->leftJoin('kodesatuan', 'databarang.kode_sat', '=', 'kodesatuan.kode_sat')
            ->where('databarang.status', '1') // Active items only
            ->groupBy(
                'databarang.kode_brng',
                'databarang.nama_brng',
                'databarang.h_beli',
                'jenis.nama',
                'kodesatuan.satuan',
                'gudangbarang.kd_bangsal'
            )
            ->select(
                'databarang.kode_brng',
                'databarang.nama_brng',
                'databarang.h_beli',
                'jenis.nama as jenis_nama',
                'kodesatuan.satuan as satuan_nama',
                DB::raw('SUM(gudangbarang.stok) as stok'),
                DB::raw('MAX(gudangbarang.no_batch) as no_batch'),
                DB::raw('MAX(gudangbarang.no_faktur) as no_faktur'),
                'gudangbarang.kd_bangsal'
            );

        if ($request->has('q') && !empty($request->q)) {
            $q = $request->q;
            $query->where(function($qBuilder) use ($q) {
                $qBuilder->where('databarang.nama_brng', 'like', "%{$q}%")
                         ->orWhere('databarang.kode_brng', 'like', "%{$q}%");
            });
        }

        if ($request->has('stok_filter') && $request->stok_filter !== '') {
            if ($request->stok_filter === '0') {
                $query->where(function($q) {
                    $q->where('gudangbarang.stok', '=', 0)
                      ->orWhereNull('gudangbarang.stok');
                });
            } elseif ($request->stok_filter === '>0') {
                $query->where('gudangbarang.stok', '>', 0);
            }
        }

        $limit = $request->limit ?? 50;
        $data = $query->orderBy('databarang.nama_brng')->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function storeBulk(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.kode_brng' => 'required',
            'items.*.kd_bangsal' => 'required',
            'items.*.no_batch' => 'nullable',
            'items.*.no_faktur' => 'nullable',
            'items.*.real' => 'required|numeric',
            'items.*.h_beli' => 'required|numeric',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string',
        ]);

        $tanggal = $request->tanggal;
        $keterangan = $request->keterangan;
        $items = $request->items;
        
        $savedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $itemData) {
                // Check for existing Opname transaction
                // Since UI groups by item, we check if ANY opname exists for this item/loc/date
                // Use default empty string if null
                $no_batch = $itemData['no_batch'] ?? '';
                $no_faktur = $itemData['no_faktur'] ?? '';

                $existingOpname = Opname::where('kode_brng', $itemData['kode_brng'])
                    ->where('kd_bangsal', $itemData['kd_bangsal'])
                    ->whereDate('tanggal', $tanggal)
                    ->first();

                if ($existingOpname) {
                    $barang = \App\Models\DataBarang::find($itemData['kode_brng']);
                    $bangsal = Bangsal::where('kd_bangsal', $itemData['kd_bangsal'])->first();
                    
                    $nama_obat = $barang ? $barang->nama_brng : $itemData['kode_brng'];
                    $nama_bangsal = $bangsal ? $bangsal->nm_bangsal : $itemData['kd_bangsal'];
                    $tgl = Carbon::parse($tanggal)->translatedFormat('d F Y');

                    throw new \Exception("Sudah ada transaksi stok opname '{$nama_obat}' di '{$nama_bangsal}' pada tanggal '{$tgl}'");
                }
                
                // Find existing stock records for this item & location (ignoring batch/faktur)
                $gudangRecords = GudangBarang::where([
                    'kode_brng' => $itemData['kode_brng'],
                    'kd_bangsal' => $itemData['kd_bangsal'],
                ])->get();

                $stok_awal = 0; // Initialize stok_awal for opname record
                $real = $itemData['real'];

                if ($gudangRecords->isEmpty()) {
                     // Create new if none exist
                     $gudang = GudangBarang::create([
                         'kode_brng' => $itemData['kode_brng'],
                         'kd_bangsal' => $itemData['kd_bangsal'],
                         'no_batch' => $no_batch,
                         'no_faktur' => $no_faktur,
                         'stok' => $real
                     ]);
                     $stok_awal = 0; // If no record existed, initial stock for opname is 0
                } else {
                    // Use the first record found
                    $gudang = $gudangRecords->first();
                    
                    // If multiple records exist (duplicates), delete the others to enforce "1 data" rule
                    if ($gudangRecords->count() > 1) {
                        $pims = $gudangRecords->slice(1);
                        foreach ($pims as $pim) {
                            $pim->delete();
                        }
                    }

                    // Sum stock from all duplicates? Or just take the first?
                    // "stok_awal" logically should be the sum if we are merging them back to one.
                    // But usually duplicate rows are errors. Let's sum them to be safe so we don't 'lose' stock.
                    $stok_awal = $gudangRecords->sum('stok');
                }

                $real = $itemData['real'];
                
                $selisih = $real - $stok_awal;
                $nomihilang = 0;
                $lebih = 0;
                $nomilebih = 0;

                if ($selisih < 0) {
                    $nomihilang = abs($selisih) * $itemData['h_beli'];
                } elseif ($selisih > 0) {
                    $lebih = $selisih;
                    $nomilebih = $selisih * $itemData['h_beli'];
                }

                // Create Opname
                Opname::create([
                    'kode_brng' => $itemData['kode_brng'],
                    'h_beli' => $itemData['h_beli'],
                    'tanggal' => $tanggal,
                    'stok' => $stok_awal,
                    'real' => $real,
                    'selisih' => $selisih,
                    'nomihilang' => $nomihilang,
                    'lebih' => $lebih,
                    'nomilebih' => $nomilebih,
                    'keterangan' => $keterangan,
                    'kd_bangsal' => $itemData['kd_bangsal'],
                    'no_batch' => $itemData['no_batch'] ?? '',
                    'no_faktur' => $itemData['no_faktur'] ?? '',
                ]);

                // Log to trackersql
                $sqlLog = "INSERT INTO opname VALUES ('{$itemData['kode_brng']}', '{$itemData['h_beli']}', '$tanggal', '$stok_awal', '$real', '$selisih', '$nomihilang', '$lebih', '$nomilebih', '$keterangan', '{$itemData['kd_bangsal']}', '$no_batch', '$no_faktur')";
                $this->logTracker($sqlLog, $request);

                // Update Gudang (Single Record)
                // We update with new batch/faktur info too
                $gudang->no_batch = $no_batch;
                $gudang->no_faktur = $no_faktur;
                $gudang->stok = $real;
                
                // Important: Since primary keys include batch/faktur, changing them might be tricky in Eloquent
                // if it relies on them for the UPDATE query.
                // However, GudangBarang uses Compoships.
                // Safest way if changing PKs: Delete old and create new? 
                // Or try saving. Eloquent might fail if PKs change.
                // Let's try explicit update query to be safe given the composite PK challenge.
                
                DB::table('gudangbarang')
                    ->where('kode_brng', $gudang->kode_brng)
                    ->where('kd_bangsal', $gudang->kd_bangsal)
                    ->update([
                        'no_batch' => $no_batch,
                        'no_faktur' => $no_faktur,
                        'stok' => $real
                    ]);
                
                $savedCount++;
            }
            
            DB::commit();
            return response()->json(['success' => true, 'message' => "$savedCount data opname berhasil disimpan"]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan bulk opname: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'kode_brng' => 'required',
            'no_batch' => 'nullable',
            'no_faktur' => 'nullable',
            'tanggal' => 'required|date',
            'kd_bangsal' => 'required'
        ]);

        $kode_brng = $request->kode_brng;
        $tanggal = $request->tanggal;
        $kd_bangsal = $request->kd_bangsal;
        // Handle null/empty batch/faktur. In DB strict comparison needed.
        // It seems the model uses empty strings for missing batch/faktur now based on previous steps.
        $no_batch = $request->no_batch ?? ''; 
        $no_faktur = $request->no_faktur ?? '';

        // Find the Opname record
        // Note: Opname uses composite keys.
        $opname = Opname::where('kode_brng', $kode_brng)
            ->where('kd_bangsal', $kd_bangsal)
            ->whereDate('tanggal', $tanggal)
            ->where('no_batch', $no_batch)
            ->where('no_faktur', $no_faktur)
            ->first();

        // If not found with exact match, try broader match if batch/faktur might be inconsistent?
        // User requested strict "1 data per item/loc".
        // But for Opname history keys: kode_brng, tanggal, kd_bangsal, no_batch, no_faktur
        if (!$opname) {
             // Try ignoring batch/faktur if they are empty strings vs nulls
             // Actually, let's rely on the keys passed from frontend which come from the item object
             return response()->json(['success' => false, 'message' => 'Data opname tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            // Restore previous stock? 
            // "Hapus riwayat" usually means reverting the opname.
            // Logic:
            // Opname `real` was the NEW stock. `stok` was OLD stock. `selisih` = real - stok.
            // If we delete this opname, should we revert `gudangbarang.stok` to `opname.stok` (stok awal)?
            // OR checks if `gudangbarang.stok` is still equal to `opname.real`?
            // If subsequent transactions happened, reverting might be dangerous.
            // SAFE APPROACH: Just delete the history record as requested ("hapus riwayatnya").
            // User did not explicitly say "revert stock". Usually stock opname is a checkpoint. 
            // Deleting the RECORD usually just removes the evidence/log, doesn't necessarily undo stock if not requested.
            // HOWEVER, if it was a mistake, user expects undo.
            // Let's assume JUST DELETE RECORD for now as per "hapus riwayatnya masukkan ke table ini". 
            // If revert is needed, user typically asks "batalkan opname".

            $sql = "DELETE FROM opname WHERE kode_brng='{$kode_brng}' AND kd_bangsal='{$kd_bangsal}' AND tanggal='{$tanggal}' AND no_batch='{$no_batch}' AND no_faktur='{$no_faktur}'";
            
            // Use DB facade to delete because Opname has composite keys and no single PK for Eloquent
            DB::table('opname')
                ->where('kode_brng', $kode_brng)
                ->where('kd_bangsal', $kd_bangsal)
                ->whereDate('tanggal', $tanggal)
                ->where('no_batch', $no_batch)
                ->where('no_faktur', $no_faktur)
                ->delete();

            $this->logTracker($sql, $request);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data opname berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }
    
    public function bangsal()
    {
        $data = Bangsal::active()->get();
        return response()->json(['success' => true, 'data' => $data]);
    }



}
