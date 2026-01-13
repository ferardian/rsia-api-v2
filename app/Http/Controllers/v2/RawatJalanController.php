<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Dokter;
use App\Models\Poliklinik;
use App\Models\RegPeriksa;
use Illuminate\Http\Request;

class RawatJalanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $date = $request->tgl_periksa ?? date('Y-m-d');
            
            $query = RegPeriksa::query()
                ->where('tgl_registrasi', $date)
                ->where('tgl_registrasi', $date)
                ->when($request->has('status_lanjut') && $request->status_lanjut != 'Semua', function ($q) use ($request) {
                    return $q->where('status_lanjut', $request->status_lanjut ?? 'Ralan');
                }, function ($q) use ($request) {
                    // Default behavior if not specified: Only Ralan, unless explicit 'Semua' or other logic ?? 
                    // Wait, if I want default Ralan, the logic above:
                    // If request has status_lanjut AND it is NOT 'Semua', use it.
                    // If request DOES NOT have status_lanjut, I should default to Ralan?
                    // Let's make it simpler.
                    if (!$request->has('status_lanjut')) {
                         return $q->where('status_lanjut', 'Ralan');
                    }
                })
                ->with([
                    'pasien' => function($q) {
                        $q->select('*');
                    },
                    'dokter' => function($q) {
                        $q->select('kd_dokter', 'nm_dokter');
                    },
                    'poliklinik' => function($q) {
                        $q->select('kd_poli', 'nm_poli');
                    },
                    'caraBayar' => function($q) { // Asuransi/Cara Bayar
                        $q->select('kd_pj', 'png_jawab');
                    }
                ]);

            // Filters
            if ($request->has('kd_poli') && $request->kd_poli != '') {
                $query->where('kd_poli', $request->kd_poli);
            }

            if ($request->has('kd_dokter') && $request->kd_dokter != '') {
                $query->where('kd_dokter', $request->kd_dokter);
            }

            // Search (No RM / Nama / No Rawat / No Reg)
            if ($request->has('keyword') && $request->keyword != '') {
                $keyword = $request->keyword;
                $query->where(function($q) use ($keyword) {
                    $q->where('no_rkm_medis', 'like', "%$keyword%")
                      ->orWhere('no_rawat', 'like', "%$keyword%")
                      ->orWhere('no_reg', 'like', "%$keyword%")
                      ->orWhereHas('pasien', function($sq) use ($keyword) {
                          $sq->where('nm_pasien', 'like', "%$keyword%");
                      });
                });
            }

            // Sorting
            $query->orderBy('no_reg', 'asc');

            $data = $query->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'message' => 'Data Rawat Jalan berhasil diambil',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function poli(Request $request)
    {
        try {
            $poli = Poliklinik::where('status', '1')->orderBy('nm_poli')->get();
            return response()->json(['success' => true, 'data' => $poli]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function dokter(Request $request)
    {
        try {
            $dokter = Dokter::where('status', '1')->orderBy('nm_dokter')->get();
            return response()->json(['success' => true, 'data' => $dokter]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    public function penunjang(Request $request)
    {
        try {
            $no_rawat = $request->no_rawat;
            if (!$no_rawat) {
                return response()->json(['success' => false, 'message' => 'Parameter no_rawat required'], 400);
            }

            $no_rawat = base64_decode($no_rawat);

            // Fetch Lab Results
            $lab = \App\Models\PeriksaLab::where('no_rawat', $no_rawat)
                ->with([
                    'detailPeriksaLab' => function ($q) {
                        $q->select('no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam', 'id_template', 'nilai', 'nilai_rujukan', 'keterangan');
                    },
                    'detailPeriksaLab.template' => function ($q) {
                        $q->select('id_template', 'Pemeriksaan', 'satuan');
                    },
                    'jenisPerawatan',
                    'dokter', 
                    'petugas'
                ])
                ->orderBy('tgl_periksa', 'desc')
                ->orderBy('jam', 'desc')
                ->get();

            // Fetch Radiology Results
            $radiologi = \App\Models\PeriksaRadiologi::where('no_rawat', $no_rawat)
                ->with([
                    'hasilRadiologi',
                    'jenisPerawatan',
                    'dokter',
                    'petugas'
                ])
                ->orderBy('tgl_periksa', 'desc')
                ->orderBy('jam', 'desc')
                ->get();

            return response()->json([
                'success' => true, 
                'data' => [
                    'lab' => $lab,
                    'radiologi' => $radiologi
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function billing(Request $request)
    {
        try {
            $no_rawat = $request->no_rawat;
            if (!$no_rawat) {
                return response()->json(['success' => false, 'message' => 'Parameter no_rawat required'], 400);
            }

            $no_rawat = base64_decode($no_rawat);
            
            // Get Status Lanjut (Ralan/Ranap)
            $regPeriksa = RegPeriksa::select('status_lanjut')->where('no_rawat', $no_rawat)->first();
            
            if (!$regPeriksa) {
                 return response()->json(['success' => false, 'message' => 'Data Registrasi tidak ditemukan'], 404);
            }

            $billingData = $this->getTarif($no_rawat, $regPeriksa->status_lanjut);

            return response()->json([
                'success' => true,
                'data' => $billingData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    private function getTarif(string $no_rawat, string $statusLanjut)
    {
        // Define common query parameters
        $baseQuery = [
            'select' => ['no_rawat', 'kd_jenis_prw', 'biaya_rawat'],
            'with' => [
                'jenisPerawatan' => function ($q) {
                    $q->select(
                        \Illuminate\Support\Facades\DB::raw('TRIM(kd_jenis_prw) as kd_jenis_prw'),
                        \Illuminate\Support\Facades\DB::raw('TRIM(nm_perawatan) as nm_perawatan'),
                        \Illuminate\Support\Facades\DB::raw('TRIM(kd_kategori) as kd_kategori')
                    )->with(['kategori' => function ($qq) {
                        $qq->select(
                            \Illuminate\Support\Facades\DB::raw('TRIM(kd_kategori) as kd_kategori'),
                            \Illuminate\Support\Facades\DB::raw('TRIM(nm_kategori) as nm_kategori')
                        );
                    }]);
                }
            ],
            'where' => ['no_rawat' => $no_rawat]
        ];

        // Define models to query
        $models = [
            \App\Models\RawatInapPr::class,
            \App\Models\RawatInapDr::class,
            \App\Models\RawatInapDrPr::class,
            \App\Models\RawatJalanPr::class,
            \App\Models\RawatJalanDr::class,
            \App\Models\RawatJalanDrPr::class
        ];

        // Process all models in a single loop
        $rawatData = collect($models)->map(function ($model) use ($baseQuery) {
            $mappedSelect = array_map(function ($item) {
                return \Illuminate\Support\Facades\DB::raw("TRIM($item) as $item");
            }, $baseQuery['select']);

            return $model::select($mappedSelect)
                ->with($baseQuery['with'])
                ->where($baseQuery['where'])
                ->get()
                ->groupBy(function ($item) {
                    return $item->jenisPerawatan->kategori->nm_kategori ?? 'Lainnya';
                })
                ->map(function ($group) {
                    return $group->groupBy(function ($item) {
                        return $item->jenisPerawatan->nm_perawatan ?? 'Perawatan';
                    });
                });
        })->filter();

        // Initialize base data
        $mergedData = collect([
            "Pemeriksaan Lab" => $this->getTarifPeriksaLab($no_rawat),
            "Pemeriksaan Radiologi" => $this->getTarifPeriksaRadiologi($no_rawat),
            "Obat dan BHP" => $this->getTarifObatDanBhp($no_rawat),
        ]);

        // Merge rawat data
        foreach ($rawatData as $data) {
            foreach ($data as $kategori => $items) {
                if ($mergedData->has($kategori)) {
                    $mergedData[$kategori] = $mergedData[$kategori]->mergeRecursive($items);
                } else {
                    $mergedData[$kategori] = $items;
                }
            }
        }

        return $mergedData->filter(function ($item) {
            return $item && !$item->isEmpty();
        })->sortKeys();
    }

    private function getTarifPeriksaLab(string $no_rawat)
    {
        $periksaLab = \App\Models\PeriksaLab::with('jenisPerawatan')
            ->where('no_rawat', $no_rawat)
            ->get();

        // Group by procedure name instead of code
        $grouped = $periksaLab->groupBy(function($item) {
            return $item->jenisPerawatan->nm_perawatan ?? $item->kd_jenis_prw;
        });

        // Map biaya to biaya_rawat for frontend compatibility
        return $grouped->map(function($items) {
            return $items->map(function($item) {
                $item->biaya_rawat = $item->biaya;
                return $item;
            });
        });
    }

    private function getTarifPeriksaRadiologi(string $no_rawat)
    {
        $periksaRad = \App\Models\PeriksaRadiologi::with('jenisPerawatan')
            ->where('no_rawat', $no_rawat)
            ->get();

        // Group by procedure name instead of code
        $grouped = $periksaRad->groupBy(function($item) {
            return $item->jenisPerawatan->nm_perawatan ?? $item->kd_jenis_prw;
        });

        // Map biaya to biaya_rawat for frontend compatibility
        return $grouped->map(function($items) {
            return $items->map(function($item) {
                $item->biaya_rawat = $item->biaya;
                return $item;
            });
        });
    }

    private function getTarifObatDanBhp(string $no_rawat)
    {
        return \App\Models\DetailPemberianObat::with('obat')
            ->where('jml', '<>', 0)
            ->where('no_rawat', $no_rawat)
            ->get()->groupBy(function ($q) {
                return $q->obat->nama_brng;
            })->sortKeys();
    }
}
