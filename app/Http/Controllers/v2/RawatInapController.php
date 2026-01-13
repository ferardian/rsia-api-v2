<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\KamarInap;
use Illuminate\Http\Request;

class RawatInapController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = KamarInap::query()
                ->select('kamar_inap.*', 'penjab.png_jawab')
                ->leftJoin('reg_periksa', 'kamar_inap.no_rawat', '=', 'reg_periksa.no_rawat')
                ->leftJoin('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->leftJoin('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                ->leftJoin('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->leftJoin('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                ->leftJoin('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal')
                ->with([
                    'sepSimple' => function ($q) {
                        $q->select('no_sep', 'no_rawat');
                    },
                    'regPeriksa' => function ($q) {
                        $q->select('no_rawat', 'no_rkm_medis', 'kd_dokter', 'kd_poli', 'tgl_registrasi', 'jam_reg', 'umurdaftar', 'sttsumur', 'p_jawab', 'hubunganpj', 'almt_pj');
                    },
                    'regPeriksa.pasien' => function ($q) {
                        $q->select('no_rkm_medis', 'nm_pasien', 'jk', 'tgl_lahir', 'alamat');
                    },
                    'regPeriksa.dokter' => function ($q) {
                        $q->select('kd_dokter', 'nm_dokter');
                    },
                    'kamar' => function ($q) {
                        $q->select('kd_kamar', 'kd_bangsal', 'trf_kamar');
                    },
                    'kamar.bangsal' => function ($q) {
                        $q->select('kd_bangsal', 'nm_bangsal');
                    },
                    'ranapGabung' => function ($q) {
                        $q->select('no_rawat', 'no_rawat2');
                    },
                    'ranapGabung.regPeriksa2' => function ($q) {
                        $q->select('no_rawat', 'no_rkm_medis');
                    },
                    'ranapGabung.regPeriksa2.pasien' => function ($q) {
                        $q->select('no_rkm_medis', 'nm_pasien', 'jk', 'tgl_lahir');
                    },
                    'regPeriksa.skriningGizi' => function ($q) {
                        $q->select('no_rawat', 'skor', 'keterangan', 'jenis_diet');
                    }
                ]);

            // Status Filter logic
            $status = $request->status ?? 'belum_pulang';
            $tgl_awal = $request->tgl_awal ?? date('Y-m-d');
            $tgl_akhir = $request->tgl_akhir ?? date('Y-m-d');

            if ($status === 'pulang') {
                $query->where('kamar_inap.stts_pulang', '!=', '-')
                      ->whereBetween('kamar_inap.tgl_keluar', [$tgl_awal, $tgl_akhir]);
            } elseif ($status === 'masuk') {
                $query->whereBetween('kamar_inap.tgl_masuk', [$tgl_awal, $tgl_akhir]);
            } else {
                // Default: Belum Pulang
                $query->where('kamar_inap.stts_pulang', '-');
            }

            // Filter: Spesialis
            if ($request->has('kd_sps') && $request->kd_sps != '') {
                $query->where('dokter.kd_sps', $request->kd_sps);
            }

            // Filter: Dokter (Doctor)
            if ($request->has('kd_dokter') && $request->kd_dokter != '') {
                $query->where('reg_periksa.kd_dokter', $request->kd_dokter);
            }

            // Search
            if ($request->has('keyword') && $request->keyword != '') {
                $keyword = $request->keyword;
                $query->where(function ($q) use ($keyword) {
                    $q->where('kamar_inap.no_rawat', 'like', "%$keyword%")
                        ->orWhere('reg_periksa.no_rkm_medis', 'like', "%$keyword%")
                        ->orWhere('kamar.kd_kamar', 'like', "%$keyword%")
                        ->orWhere('pasien.nm_pasien', 'like', "%$keyword%");
                });
            }

            // Default Order
            $query->orderBy('kamar_inap.kd_kamar', 'asc');

            // Pagination
            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data Rawat Inap fetched successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('RawatInap Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function spesialis()
    {
        try {
            $data = \App\Models\Spesialis::orderBy('nm_sps')->get();
            return response()->json(['success' => true, 'data' => $data]);
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
            
            // Get Status Lanjut (Ralan/Ranap) - Optional check
            $regPeriksa = \App\Models\RegPeriksa::select('status_lanjut')->where('no_rawat', $no_rawat)->first();
            
            if (!$regPeriksa) {
                 return response()->json(['success' => false, 'message' => 'Data Registrasi tidak ditemukan'], 404);
            }

            // 1. Get Mother's Billing
            $mainBilling = $this->getTarif($no_rawat);

            // 2. Check for Babies (Gabung)
            $babies = \App\Models\RanapGabung::with(['regPeriksa2.pasien' => function($q) {
                $q->select('no_rkm_medis', 'nm_pasien');
            }])
            ->where('no_rawat', $no_rawat)
            ->get();

            // 3. Process Baby Billing separately
            $gabungBilling = [];
            foreach ($babies as $baby) {
                if (!$baby->regPeriksa2) continue;

                $babyNoRawat = $baby->no_rawat2;
                $babyName = $baby->regPeriksa2->pasien->nm_pasien ?? 'Bayi';
                
                $gabungBilling[] = [
                    'no_rawat' => $babyNoRawat,
                    'nama' => $babyName,
                    'billing' => $this->getTarif($babyNoRawat)->sortKeys()
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $mainBilling->sortKeys(),
                'gabung' => $gabungBilling
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

    private function getTarif(string $no_rawat)
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
                ->where($baseQuery['where'])
                ->get()
                ->toBase()
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
            "Kamar Inap" => $this->getTarifKamar($no_rawat),
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

    private function getTarifKamar(string $no_rawat)
    {
        $kamarInap = \App\Models\KamarInap::with(['kamar.bangsal'])
            ->where('no_rawat', $no_rawat)
            ->get();

        return $kamarInap->map(function ($item) {
             $biaya = $item->ttl_biaya;
             $lama = $item->lama;

             if ($item->stts_pulang == '-') {
                 $start = \Illuminate\Support\Carbon::parse($item->tgl_masuk)->startOfDay();
                 $end = \Illuminate\Support\Carbon::now()->startOfDay();
                 $lama = $start->diffInDays($end);
                 $biaya = $lama * $item->trf_kamar;
             }
             
             $namaKamar = $item->kamar->bangsal->nm_bangsal ?? $item->kd_kamar;
             
             // Additional info for UI
             $item->biaya_rawat = $biaya;
             $item->nm_perawatan = $namaKamar;
             $item->lama_inap_real = $lama; 
             
             return $item;
        })->groupBy(function($item) {
            return $item->nm_perawatan . ' (' . $item->lama_inap_real . ' hari)';
        });
    }

    private function getTarifPeriksaLab(string $no_rawat)
    {
        $periksaLab = \App\Models\PeriksaLab::with(['jenisPerawatan', 'detailPeriksaLab'])
            ->where('no_rawat', $no_rawat)
            ->get();

        // Group by procedure name instead of code
        $grouped = $periksaLab->groupBy(function($item) {
            return $item->jenisPerawatan->nm_perawatan ?? $item->kd_jenis_prw;
        });

        // Map biaya to biaya_rawat for frontend compatibility
        return $grouped->map(function($items) {
            return $items->map(function($item) {
                // Calculate total item cost from details
                $biayaItem = $item->detailPeriksaLab->sum('biaya_item');
                
                // Add detail item cost to main cost
                $item->biaya_rawat = $item->biaya + $biayaItem;
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
            ->get()->toBase()->groupBy(function ($q) {
                return $q->obat->nama_brng;
            })->sortKeys();
    }
}
