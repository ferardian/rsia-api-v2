<?php

namespace App\Http\Controllers\v2;

use App\Helpers\SafeAccess;
use Illuminate\Support\Str;
use App\Helpers\ApiResponse;
use App\Helpers\NaikKelasHelper;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\RsiaReqResIdrg;
use Halim\EKlaim\Builders\BodyBuilder;
use Halim\EKlaim\Services\EklaimService;
use Halim\EKlaim\Controllers\GroupKlaimController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KlaimController extends Controller
{
    /**
     * new klaim method
     *
     * @param \Halim\EKlaim\Http\Requests\NewKlaimRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     * */
    public function new(\Halim\EKlaim\Http\Requests\NewKlaimRequest $request)
    {
        BodyBuilder::setMetadata('new_claim');
        BodyBuilder::setData([
            "nomor_sep"   => $request->nomor_sep,
            "nomor_kartu" => $request->nomor_kartu,
            "nomor_rm"    => $request->nomor_rm,
            "nama_pasien" => $request->nama_pasien,
            "tgl_lahir"   => $request->tgl_lahir,
            "gender"      => $request->gender,
        ]);

        $response = EklaimService::send(BodyBuilder::prepared());

        if ($response->getStatusCode() == 200) {
            $response_data = $response->getData();
            $this->storeInacbgKlaimBaru2(
                $request->no_rawat,
                $request->nomor_sep,
                $response_data->response->patient_id,
                $response_data->response->admission_id,
                $response_data->response->hospital_admission_id
            );
        } else {
            Log::channel(config('eklaim.log_channel'))->error("NEW KLAIM ERROR", json_decode(json_encode($response->getData()), true));

            BodyBuilder::setMetadata('get_claim_data');
            BodyBuilder::setData([
                "nomor_sep" => $request->nomor_sep,
            ]);

            $klaim_data = EklaimService::send(BodyBuilder::prepared());
            $klaim_data = $klaim_data->getData();

            $this->storeInacbgKlaimBaru2(
                $request->no_rawat,
                $request->nomor_sep,
                $klaim_data->response->data->patient_id,
                $klaim_data->response->data->admission_id,
                $klaim_data->response->data->hospital_admission_id
            );
        }

        return $response_data ?? $response;
    }

    /**
     * Send klaim method
     * Kirim online individual klaim
     *
     * @param string $sep
     *
     * @return \Illuminate\Http\JsonResponse
     * */
    public function send($sep)
    {
        try {
            // get user from middleware detail-user
            $user = \Illuminate\Support\Facades\Auth::user();
            $nik = \App\Models\RsiaCoderNik::where('nik', $user->id_user)->first();

            BodyBuilder::setMetadata('send_claim_individual');
            BodyBuilder::setData([
                "nomor_sep" => $sep
            ]);

            return EklaimService::send(BodyBuilder::prepared())->then(function ($response) use ($sep, $nik) {
                Log::channel(config('eklaim.log_channel'))->info("KIRIM ONLINE INDIVIDUAL", [
                    "sep"      => $sep,
                    "nik"      => $nik ? $nik->no_ik : "3326105603750002",
                    "response" => $response,
                ]);

                \App\Models\InacbgDataTerkirim::updateOrCreate(
                    ['no_sep' => $sep],
                    ['nik' => $nik ? $nik->no_ik : "3326105603750002"]
                );
            });
        } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("SEND CLAIM ERROR", [
                "sep"   => $sep,
                "error" => $th->getMessage(),
            ]);

            return ApiResponse::error($th->getMessage(), 500);
        }
    }

    /**
     * set klaim data method
     *
     * @param string $sep
     * @param \Halim\EKlaim\Http\Requests\SetKlaimDataRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     * */
    public function set($sep, \Halim\EKlaim\Http\Requests\SetKlaimDataRequest $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $nik = \App\Models\RsiaCoderNik::where('nik', $user->id_user)->first();

        try {
            if ($sep != $request->nomor_sep) {
                throw new \Exception("Nomor SEP tidak sama dengan nomor SEP pada request");
            }

            // ==================================================== NEW KLAIM PROCESS

            // if (!\App\Models\InacbgKlaimBaru2::where('no_sep', $sep)->exists()) {
            $bridging_sep = \App\Models\BridgingSep::with(['dokter', 'pasien'])->where('no_sep', $sep)->first();
            $this->new(new \Halim\EKlaim\Http\Requests\NewKlaimRequest([
                'nomor_sep'   => $sep,
                'nomor_kartu' => $bridging_sep->no_kartu,
                'nomor_rm'    => $bridging_sep->pasien->no_rkm_medis,
                'no_rawat'    => $bridging_sep->no_rawat,
                'nama_pasien' => $bridging_sep->pasien->nm_pasien,
                'tgl_lahir'   => $bridging_sep->pasien->tgl_lahir,
                'gender'      => $bridging_sep->pasien->jk,
            ]));
            // }

            // ==================================================== PARSE DATA

            $required = [
                "nomor_sep" => $sep,
                "coder_nik" => $nik ? $nik->no_ik : "3326105603750002",
                "payor_id"  => $request->payor_id,
                "payor_cd"  => $request->payor_cd,
            ];

            $data = array_merge($required, \Halim\EKlaim\Helpers\ClaimDataParser::parseClaim($request));

            // if in data not has nama_dokter key add it
            if (!array_key_exists('nama_dokter', $data)) {
                $data['nama_dokter'] = strtoupper($bridging_sep->dokter->nm_dokter);
            }

            // [0]. Re-Edit Klaim
            $this->reEdit($sep);

            // [1]. Set claim data
            usleep(rand(500, 1000) * 1000);

            BodyBuilder::setMetadata('set_claim_data', ["nomor_sep" => $sep]);
            BodyBuilder::setData($data);
            // simpan request payload dalam bentuk array (bukan stdClass)
            $requestPayload = json_decode(json_encode(BodyBuilder::prepared()), true);

            EklaimService::send($requestPayload)->then(function ($response) use ($sep, $data, $requestPayload) {
                Log::channel(config('eklaim.log_channel'))->info("SET KLAIM DATA", [
                    "sep"      => $sep,
                    "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
                    "response" => $response,
                ]);
                 $decodedResponse = json_decode(json_encode($response), true);

                // ==================================================== SAVE DATAS

                // $this->saveDiagnosaAndProcedures($data);
                $this->saveChunksData($data);

                // simpan request & response set_claim
               $this->saveReqResIdrg($sep, $requestPayload, $decodedResponse, 'set_claim');


                // ==================================================== END OF SAVE DATAS
            });

            // [2]. Grouping stage 1 & 2
            // usleep(rand(500, 1000) * 1000);

            // $hasilGrouping = $this->groupStages($sep);
            // $responseCode  = SafeAccess::object($hasilGrouping, 'response->cbg->code');

            // if (SafeAccess::object($hasilGrouping, "metadata->error_no")) {
            //     throw new \Exception(SafeAccess::object($hasilGrouping, "metadata->error_no") . " : " . SafeAccess::object($hasilGrouping, "metadata->message"), SafeAccess::object($hasilGrouping, "metadata->code"));
            // }

            // cekNaikKelas
            // $this->cekNaikKelas($sep, $hasilGrouping, $request);

            // [3]. Final Klaim
            // usleep(rand(500, 1000) * 1000);
            // $this->finalClaim($sep);

            // if (SafeAccess::object($hasilGrouping, 'metadata->code') == 200) {
            //     Log::channel(config('eklaim.log_channel'))->info("HASIL", ["grouping" => $hasilGrouping, "response" => $responseCode]);
            // } else {
            //     Log::channel(config('eklaim.log_channel'))->error("HASIL", ["grouping" => $hasilGrouping, "response" => $responseCode]);
            // }

            // if ($responseCode && (Str::startsWith($responseCode, 'X') || Str::startsWith($responseCode, 'x'))) {
            //     throw new \Exception($hasilGrouping->response->cbg->code . " : " . $hasilGrouping->response->cbg->description);
            // }

            // return ApiResponse::successWithData($hasilGrouping->response, "Grouping Berhasil dilakukan");
        } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("SET KLAIM DATA", [
                "error" => $th->getMessage(),
            ]);

            return ApiResponse::error($th->getMessage(), 500);
        }
    }

   public function setGroupingIdrg(Request $request, string $sep)
{
    try {
        // 1. Kirim Diagnosa
        $diagnosaData = \Halim\EKlaim\Helpers\ClaimDataParser::parseDiagnosis($request);

        BodyBuilder::setMetadata('idrg_diagnosa_set', ["nomor_sep" => $sep]);
        BodyBuilder::setData($diagnosaData);

        $diagnosaResponse = EklaimService::send(BodyBuilder::prepared());
        $diagnosaDecoded  = $this->decodeResponse($diagnosaResponse);

        Log::channel(config('eklaim.log_channel'))->info("IDRG DIAGNOSA SET", [
            "sep"      => $sep,
            "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $diagnosaDecoded,
        ]);
        

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($diagnosaDecoded), true);

                Log::info("DEBUG RAW DIAGNOSA RESPONSE", [
            "sep"      => $sep,
            "raw"      => $diagnosaResponse,
            "decoded"  => $diagnosaDecoded,
        ]);


        $this->saveReqResIdrg($sep, $requestData, $responseData, 'idrg_diagnosa');
        // Jika gagal, hentikan
       $diagnosaCode = $diagnosaDecoded['metadata']['code'] ?? 400;


        // 2. Kirim Procedure
        $procedureData = \Halim\EKlaim\Helpers\ClaimDataParser::parseProcedure($request);

        // === Tambahan logic untuk handle multiplicity ===
        if (!empty($procedureData['procedure'])) {
            $procedures = explode('#', $procedureData['procedure']);
            $counter = [];

            foreach ($procedures as $proc) {
                $counter[$proc] = ($counter[$proc] ?? 0) + 1;
            }

            // Format ulang: kalau count > 1 → kode+count
            $procedureData['procedure'] = implode('#', array_map(
                fn($kode, $count) => $count > 1 ? "{$kode}+{$count}" : $kode,
                array_keys($counter),
                $counter
            ));
        }

        BodyBuilder::setMetadata('idrg_procedure_set', ["nomor_sep" => $sep]);
        BodyBuilder::setData($procedureData);

        $procedureResponse = EklaimService::send(BodyBuilder::prepared());
        $procedureDecoded  = $this->decodeResponse($procedureResponse);

        Log::channel(config('eklaim.log_channel'))->info("IDRG PROCEDURE SET", [
            "sep"      => $sep,
            "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $procedureDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($procedureDecoded), true);

        $this->saveReqResIdrg($sep, $requestData, $responseData, 'idrg_procedure');

     

        $procedureCode = $procedureDecoded['metadata']['code'] ?? 400;


        // 3. Kirim Grouper Stage 1
        BodyBuilder::setMetadata('grouper', [
            "stage"   => "1",
            "grouper" => "idrg"
        ]);
        BodyBuilder::setData(["nomor_sep" => $sep]);

        $grouperResponse = EklaimService::send(BodyBuilder::prepared());
        $grouperDecoded  = $this->decodeResponse($grouperResponse);

        Log::channel(config('eklaim.log_channel'))->info("IDRG GROUPER STAGE 1", [
            "sep"      => $sep,
            "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $grouperDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($grouperDecoded), true);

        $this->saveReqResIdrg($sep, $requestData, $responseData, 'grouper');

        return ApiResponse::success($grouperDecoded);

    } catch (\Throwable $th) {
        Log::channel(config('eklaim.log_channel'))->error("IDRG GROUPING ERROR", [
            "sep"   => $sep,
            "error" => $th->getMessage(),
        ]);

        return ApiResponse::error($th->getMessage(), 500);
    }
}

public function setGroupingInacbg(Request $request, string $sep)
{
    try {
        // ===================================================================
        // LANGKAH 1: KIRIM DIAGNOSA
        // ===================================================================
        $diagnosaData = \Halim\EKlaim\Helpers\ClaimDataParser::parseDiagnosis($request);

        BodyBuilder::setMetadata('inacbg_diagnosa_set', ["nomor_sep" => $sep]);
        BodyBuilder::setData($diagnosaData);

        $diagnosaResponse = EklaimService::send(BodyBuilder::prepared());
        $diagnosaDecoded  = $this->decodeResponse($diagnosaResponse);

        // Logging (opsional, tapi sangat direkomendasikan)
        Log::info("INA-CBG DIAGNOSA SET", [
            "sep"      => $sep,
            "request"  => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $diagnosaDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($diagnosaDecoded), true);

        $this->saveReqResIdrg($sep, $requestData, $responseData, 'inacbg_diagnosa');

        // Hentikan proses jika request pertama gagal
        if (($diagnosaDecoded['metadata']['code'] ?? 400) != 200) {
            return ApiResponse::error('Gagal saat set diagnosa INA-CBG', 400, $diagnosaDecoded);
        }

        // ===================================================================
        // LANGKAH 2: KIRIM PROSEDUR
        // ===================================================================
        $procedureData = \Halim\EKlaim\Helpers\ClaimDataParser::parseProcedure($request);

        BodyBuilder::setMetadata('inacbg_procedure_set', ["nomor_sep" => $sep]);
        BodyBuilder::setData($procedureData);

        $procedureResponse = EklaimService::send(BodyBuilder::prepared());
        $procedureDecoded  = $this->decodeResponse($procedureResponse);

        // Logging
        Log::info("INA-CBG PROCEDURE SET", [
            "sep"      => $sep,
            "request"  => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $procedureDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($diagnosaDecoded), true);

        $this->saveReqResIdrg($sep, $requestData, $responseData, 'inacbg_procedure');

        // Hentikan proses jika request kedua gagal
        if (($procedureDecoded['metadata']['code'] ?? 400) != 200) {
            return ApiResponse::error('Gagal saat set prosedur INA-CBG', 400, $procedureDecoded);
        }

        // ===================================================================
        // LANGKAH 3: JALANKAN GROUPER
        // ===================================================================
        BodyBuilder::setMetadata('grouper', [
            "stage"   => "1",
            "grouper" => "inacbg" // Menggunakan grouper "inacbg"
        ]);
        BodyBuilder::setData(["nomor_sep" => $sep]);

        $grouperResponse = EklaimService::send(BodyBuilder::prepared());
        $grouperDecoded  = $this->decodeResponse($grouperResponse);

        // Logging
        Log::info("INA-CBG GROUPER", [
            "sep"      => $sep,
            "request"  => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $grouperDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($diagnosaDecoded), true);

        $this->saveReqResIdrg($sep, $requestData, $responseData, 'grouper_inacbg_stage1');

        // Kembalikan hasil akhir dari grouper
        return ApiResponse::success($grouperDecoded);

    } catch (\Throwable $th) {
        // Tangani jika terjadi error tak terduga
        Log::error("INA-CBG GROUPING FATAL ERROR", [
            "sep"   => $sep,
            "error" => $th->getMessage(),
            "trace" => $th->getTraceAsString()
        ]);

        return ApiResponse::error('Terjadi kesalahan pada server: ' . $th->getMessage(), 500);
    }
}


public function final(Request $request, $sep)
{
    try {
        // Bangun payload
        BodyBuilder::setMetadata("idrg_grouper_final");
        BodyBuilder::setData([
            "nomor_sep" => $sep
        ]);

        // Kirim ke Eklaim
        $finalGrouperResponse = EklaimService::send(BodyBuilder::prepared());
        $finalGrouperDecoded  = $this->decodeResponse($finalGrouperResponse);

        // Logging
        Log::channel(config('eklaim.log_channel'))->info("IDRG FINAL GROUPER", [
            "sep"      => $sep,
            "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $finalGrouperDecoded,
        ]);

        $requestData = json_decode(json_encode(BodyBuilder::prepared()), true);
        $responseData = json_decode(json_encode($finalGrouperDecoded), true);

        // Simpan ke tabel req/res
        $this->saveReqResIdrg($sep, $requestData, $responseData, 'final');

        // 🔹 hapus kolom reedit_res di record SEP ini
        RsiaReqResIdrg::where('no_sep', $sep)->update([
            'reedit_res' => null
        ]);

        // Beri respons balik ke FE
        return response()->json($finalGrouperDecoded, 200);
    } catch (\Throwable $e) {
        Log::channel(config('eklaim.log_channel'))->error("IDRG FINAL GROUPER ERROR", [
            "sep"      => $sep,
            "message"  => $e->getMessage(),
            "trace"    => $e->getTraceAsString(),
        ]);

        return response()->json([
            "metadata" => [
                "code"    => 500,
                "message" => "Terjadi kesalahan saat memproses final grouper: " . $e->getMessage(),
            ],
        ], 500);
    }
}


public function reEditIdrg(Request $request, $no_sep)
{
    try {
        BodyBuilder::setMetadata('idrg_grouper_reedit', [
            "method" => "idrg_grouper_reedit"
        ]);
        BodyBuilder::setData([
            "nomor_sep" => $no_sep
        ]);

        $reeditResponse = EklaimService::send(BodyBuilder::prepared());
        $reeditDecoded  = $this->decodeResponse($reeditResponse);

        Log::channel(config('eklaim.log_channel'))->info("IDRG REEDIT GROUPER", [
            "sep"      => $no_sep,
            "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
            "response" => $reeditDecoded,
        ]);

        $this->saveReqResIdrg(
            $no_sep,
            json_decode(json_encode(BodyBuilder::prepared()), true),
            json_decode(json_encode($reeditDecoded), true),
            'reedit'
        );

        return response()->json($reeditDecoded, 200);
    } catch (\Throwable $e) {
        Log::channel(config('eklaim.log_channel'))->error("IDRG REEDIT ERROR", [
            "sep"    => $no_sep,
            "error"  => $e->getMessage(),
        ]);
        return response()->json([
            "metadata" => [
                "code"    => 500,
                "message" => "Gagal reedit IDRG: " . $e->getMessage(),
            ]
        ], 500);
    }
}


public function importIdrgToInacbg(Request $request, $no_sep)
    {
        try {
            // Bangun payload untuk service eksternal
            BodyBuilder::setMetadata("idrg_to_inacbg_import");
            BodyBuilder::setData([
                "nomor_sep" => $no_sep
            ]);

            // Kirim request ke Eklaim Service
            $importResponse = EklaimService::send(BodyBuilder::prepared());
            $importDecoded  = $this->decodeResponse($importResponse);

            // Logging
            Log::channel(config('eklaim.log_channel'))->info("IDRG_TO_INACBG_IMPORT", [
                "sep"      => $no_sep,
                "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
                "response" => $importDecoded,
            ]);

            // (Opsional) Simpan request & response ke database
            $this->saveReqResIdrg(
                $no_sep,
                json_decode(json_encode(BodyBuilder::prepared()), true),
                json_decode(json_encode($importDecoded), true),
                'import_idrg_to_inacbg' // tipe log baru
            );

            // Kembalikan response ke Frontend
            // Response ini diasumsikan berisi data diagnosa & prosedur yang akan ditampilkan
            return response()->json($importDecoded, 200);

        } catch (\Throwable $e) {
            Log::channel(config('eklaim.log_channel'))->error("IDRG_TO_INACBG_IMPORT ERROR", [
                "sep"    => $no_sep,
                "error"  => $e->getMessage(),
            ]);
            return response()->json([
                "metadata" => [
                    "code"    => 500,
                    "message" => "Gagal mengimpor data dari iDRG ke INACBG: " . $e->getMessage(),
                ]
            ], 500);
        }
    }


   public function setDiagnosaIdrg(Request $request, string $sep)
{
    try {
    $data = \Halim\EKlaim\Helpers\ClaimDataParser::parseDiagnosisAndProcedure($request);

    BodyBuilder::setMetadata('idrg_diagnosa_set', ["nomor_sep" => $sep]);
    BodyBuilder::setData($data);
  

    EklaimService::send(BodyBuilder::prepared())->then(function ($response) use ($sep, $data) {
                Log::channel(config('eklaim.log_channel'))->info("IDRG DIAGNOSA SET", [
                    "sep"      => $sep,
                    "data"     => json_decode(json_encode(BodyBuilder::prepared()), true),
                    "response" => $response,
                ]);

                // ==================================================== SAVE DATAS

                // $this->saveDiagnosaAndProcedures($data);
                // $this->saveChunksData($data);

                // simpan request & response set_claim
                $this->saveReqResIdrg($sep, $data, $response);

                // ==================================================== END OF SAVE DATAS
            });
            } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("IDRG DIAGNOSA SET", [
                "error" => $th->getMessage(),
            ]);

            return ApiResponse::error($th->getMessage(), 500);
        }
}


    public function reEdit(String $sep)
    {
        BodyBuilder::setMetadata('reedit_claim');
        BodyBuilder::setData(["nomor_sep" => $sep]);

        EklaimService::send(BodyBuilder::prepared())->then(function ($response) use ($sep) {
            Log::channel(config('eklaim.log_channel'))->info("RE-EDIT", [
                "sep"      => $sep,
                "response" => $response,
            ]);
        });
    }

    public function finalClaim(String $sep)
    {
        try {
            BodyBuilder::setMetadata('claim_final');
            BodyBuilder::setData([
                "nomor_sep" => $sep,
                "coder_nik" => '3326105603750002',
            ]);

            EklaimService::send(BodyBuilder::prepared())->then(function ($response) use ($sep) {
                if (SafeAccess::object($response, 'metadata->code') == 200) {
                    Log::channel(config('eklaim.log_channel'))->info("FINAL", ["sep" => $sep, "response" => $response]);
                } else {
                    Log::channel(config('eklaim.log_channel'))->error("FINAL", ["sep" => $sep, "response" => $response]);
                    throw new \Exception("Error in final claim: " . SafeAccess::object($response, 'metadata->message'));
                }
            });
        } catch (\Exception $e) {
            Log::error("Error in finalClaim", [
                'sep'   => $sep,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * get klaim data method
     *
     * method to get klaim data from eklaim service and save it to our database
     *
     * @param string $sep
     *
     * @return \Illuminate\Http\JsonResponse
     * */
    public function sync($sep)
    {
        BodyBuilder::setMetadata('get_claim_data');
        BodyBuilder::setData(["nomor_sep" => $sep]);

        $response = EklaimService::send(BodyBuilder::prepared());

        // Pastikan response berhasil (status code 200)
        if ($response->getStatusCode() !== 200) {
            return $this->logAndReturnError("SYNC - Error while getting klaim data", $response);
        }

        $responseData = SafeAccess::object($response->getData(), "response->data");
        if (!$responseData) {
            return $this->logAndReturnError("SYNC - Error while getting klaim data", $response);
        }

        $cbg = SafeAccess::object($responseData, "grouper->response->cbg");
        if (!$cbg) {
            return $this->logAndReturnError("SYNC - Error while getting klaim data", $response);
        }

        $groupingData = [
            'no_sep'    => $sep,
            'code_cbg'  => $cbg->code ?? null,
            'deskripsi' => $cbg->description ?? null,
            'tarif'     => $cbg->tariff ?? null,
        ];

        // Melakukan transaksi penyimpanan data ke database
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($sep, $groupingData) {
                \App\Models\InacbgGropingStage12::where('no_sep', $sep)->delete();
                \App\Models\InacbgGropingStage12::create($groupingData);
            }, 5);

            Log::channel(config('eklaim.log_channel'))->info("SYNC - inacbg_grouping_stage12", $groupingData);
            return ApiResponse::successWithData($responseData, "Grouping Berhasil dilakukan");
        } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("SYNC - inacbg_grouping_stage12", [
                "error" => $th->getMessage(),
            ]);
            return ApiResponse::error("Error while inserting data to inacbg_grouping_stage12", 500);
        }
    }

    /**
     * Helper method untuk log dan kembalikan error.
     */
    protected function logAndReturnError($message, $response)
    {
        Log::channel(config('eklaim.log_channel'))->error($message, json_decode(json_encode($response->getData()), true));
        return ApiResponse::error($message, 500);
    }

    private function storeInacbgKlaimBaru2($no_rawat, $nomor_sep, $patient_id, $admission_id, $hospital_admission_id)
    {
        $dataToSave = [
            'no_rawat'              => $no_rawat,
            'no_sep'                => $nomor_sep,
            'patient_id'            => $patient_id,
            'admission_id'          => $admission_id,
            'hospital_admission_id' => $hospital_admission_id,
        ];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($dataToSave) {
                \App\Models\InacbgKlaimBaru2::updateOrCreate([
                    'no_sep' => $dataToSave['no_sep'],
                ], $dataToSave);
            });

            Log::channel(config('eklaim.log_channel'))->info("UPDATE OR CREATE - inacbg_klaim_baru2", [
                "data" => $dataToSave,
            ]);
        } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("UPDATE OR CREATE - inacbg_klaim_baru2", [
                "error" => $th->getMessage(),
                "data"  => $dataToSave,
            ]);
        }
    }

    private function saveChunksData($klaim_data)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($klaim_data) {
                \App\Models\RsiaGroupingChunks::updateOrCreate(['no_sep' => $klaim_data['nomor_sep']], [
                    'cara_masuk'      => $klaim_data['cara_masuk'] ?? null,
                    'sistole'         => $klaim_data['sistole'] ?? null,
                    'diastole'        => $klaim_data['diastole'] ?? null,
                    'usia_kehamilan'  => $klaim_data['persalinan']['usia_kehamilan'] ?? null,
                    'onset_kontraksi' => $klaim_data['persalinan']['onset_kontraksi'] ?? null,
                    'noreg_sitb'      => $klaim_data['jkn_sitb_noreg'] ?? null,
                ]);
            }, 5);
        } catch (\Exception $e) {
            Log::error("Error in saveChunksData", [
                'klaim_data' => $klaim_data,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

private function saveReqResIdrg(string $sep, array $requestData, ?array $responseData, string $type = 'set_claim')
{
    $updateData = [
        'updated_at' => now(),
    ];

    switch ($type) {
        case 'set_claim':
            $updateData['set_claim_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['set_claim_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

        case 'idrg_diagnosa':
            $updateData['idrg_diagnosa_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['idrg_diagnosa_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

        case 'idrg_procedure':
            $updateData['idrg_procedure_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['idrg_procedure_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

        case 'grouper':
            $updateData['grouper_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['grouper_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

        case 'final':
            $updateData['final_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['final_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

        case 'reedit':
            $updateData['reedit_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['reedit_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;
        
        case 'import_idrg_to_inacbg':
            $updateData['import_idrg_to_inacbg_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['import_idrg_to_inacbg_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

         case 'inacbg_diagnosa':
            $updateData['inacbg_diagnosa_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['inacbg_diagnosa_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

         case 'inacbg_procedure':
            $updateData['inacbg_procedure_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['inacbg_procedure_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;

         case 'grouper_inacbg_stage1':
            $updateData['grouper_inacbg_stage1_req'] = json_encode($requestData ?? [], JSON_UNESCAPED_UNICODE);
            $updateData['grouper_inacbg_stage1_res'] = json_encode($responseData ?? [], JSON_UNESCAPED_UNICODE);
            break;
    }

    \App\Models\RsiaReqResIdrg::updateOrCreate(
        ['no_sep' => $sep],
        $updateData
    );
}






    private function saveDiagnosaAndProcedures($klaim_data)
    {
        try {
            $explodedDiagnosa   = explode("#", $klaim_data['diagnosa']);
            $explodedProcedures = explode("#", $klaim_data['procedure']);

            $no_rawat = \App\Models\BridgingSep::where('no_sep', $klaim_data['nomor_sep'])->first()->no_rawat;

            if (!$no_rawat) {
                Log::channel(config('eklaim.log_channel'))->error("SAVE DIAGNOSA", [
                    "no_sep"     => $klaim_data['nomor_sep'],
                    "diag"       => $explodedDiagnosa,
                    "procedures" => $explodedProcedures,
                ]);

                return;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($explodedDiagnosa, $no_rawat, $klaim_data) {
                \App\Models\DiagnosaPasien::where('no_rawat', $no_rawat)->delete();

                foreach ($explodedDiagnosa as $key => $diagnosa) {
                    if (empty($diagnosa)) {
                        continue;
                    }

                    \App\Models\DiagnosaPasien::create([
                        "no_rawat"    => $no_rawat,
                        "kd_penyakit" => $diagnosa,
                        "status"      => $klaim_data['jenis_rawat'] == 1 ? "Ranap" : "Ralan",
                        "prioritas"   => $key + 1,
                    ]);
                }
            }, 5);

            \Illuminate\Support\Facades\DB::transaction(function () use ($explodedProcedures, $no_rawat, $klaim_data) {
                \App\Models\ProsedurPasien::where('no_rawat', $no_rawat)->delete();

                foreach ($explodedProcedures as $key => $procedure) {
                    if (empty($procedure)) {
                        continue;
                    }

                    \App\Models\ProsedurPasien::create([
                        "no_rawat"  => $no_rawat,
                        "kode"      => $procedure,
                        "status"    => $klaim_data['jenis_rawat'] == 1 ? "Ranap" : "Ralan",
                        "prioritas" => $key + 1,
                    ]);
                }
            }, 5);
        } catch (\Exception $e) {
            Log::error("Error in saveDiagnosaAndProcedures", [
                'klaim_data' => $klaim_data,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }


    private function groupStages($sep)
    {
        // ==================================================== GROUPING STAGE 1 & 2
        $group = new GroupKlaimController();

        // Grouping stage 1
        $gr2 = null;
        $gr1 = $group->stage1(new \Halim\EKlaim\Http\Requests\GroupingStage1Request(["nomor_sep" => $sep]))->then(function ($response) use ($sep) {
            if (SafeAccess::object($response, 'metadata->code') == 200) {
                Log::channel(config('eklaim.log_channel'))->info("GROUPPING - stage 1", ["sep" => $sep, 'response' => $response]);
            } else {
                Log::channel(config('eklaim.log_channel'))->error("GROUPPING - stage 1", ["sep" => $sep, 'response' => $response]);
            }
        });

        $hasilGrouping = $gr1->getData();
        if (isset($hasilGrouping->special_cmg_option)) {
            $special_cmg_option_code = array_map(function ($item) {
                return $item->code;
            }, $hasilGrouping->special_cmg_option);

            $special_cmg_option_code = implode("#", $special_cmg_option_code);

            // Grouping stage 2
            $gr2 = $group->stage2(new \Halim\EKlaim\Http\Requests\GroupingStage2Request(["nomor_sep" => $sep, "special_cmg" => $special_cmg_option_code ?? '']))->then(function ($response) use ($sep, $special_cmg_option_code) {
                if (SafeAccess::object($response, 'metadata->code') == 200) {
                    Log::channel(config('eklaim.log_channel'))->info("GROUPPING - stage 2", ["sep" => $sep, 'response' => $response, "special_cmg" => $special_cmg_option_code ?? '']);
                } else {
                    Log::channel(config('eklaim.log_channel'))->error("GROUPPING - stage 2", ["sep" => $sep, 'response' => $response, "special_cmg" => $special_cmg_option_code ?? '']);
                }
            });

            $hasilGrouping = $gr2->getData();
        }

        // ==================================================== END OF GROUPING STAGE 1 & 2

        try {
            $groupingData = [
                "no_sep"    => $sep,
                "code_cbg"  => SafeAccess::object($hasilGrouping, 'response->cbg->code'),
                "deskripsi" => SafeAccess::object($hasilGrouping, 'response->cbg->description'),
                "tarif"     => SafeAccess::object($hasilGrouping, 'response->cbg->tariff'),
            ];

            \Illuminate\Support\Facades\DB::transaction(function () use ($sep, $groupingData) {
                \App\Models\InacbgGropingStage12::where('no_sep', $sep)->delete();
                \App\Models\InacbgGropingStage12::create($groupingData);
            }, 5);

            Log::channel(config('eklaim.log_channel'))->info("INSERT - inacbg_grouping_stage12", $groupingData);
        } catch (\Throwable $th) {
            Log::channel(config('eklaim.log_channel'))->error("INSERT - inacbg_grouping_stage12", [
                "error" => $th->getMessage(),
            ]);

            throw $th;
        }

        return $hasilGrouping;
    }

    private function cekNaikKelas($sep, $groupResponse, \Halim\EKlaim\Http\Requests\SetKlaimDataRequest $request)
    {
        $sep = \App\Models\BridgingSep::where('no_sep', $sep)->first();

        if (!$sep || $sep->jnspelayanan == 2) {
            Log::channel(config('eklaim.log_channel'))->info("SKIP CEK NAIK KELAS", ["jenis_pelayanan" => $sep->jnspelayanan]);
            return;
        }

        // in request not has upgrade_class_ind or upgrade_class_ind == 0
        if (!$request->has('upgrade_class_ind') || $request->upgrade_class_ind == 0) {
            Log::channel(config('eklaim.log_channel'))->info("SKIP CEK NAIK KELAS", ["upgrade_class_ind" => $request->upgrade_class_ind]);
            return;
        }

        // Jika realcost < cbg->tariff, return
        $cdp          = \Halim\EKlaim\Helpers\ClaimDataParser::parse($request);
        $tarif_rs     = $cdp['tarif_rs'];
        $tarif_rs_sum = array_sum($tarif_rs);

        $klsrawatNaik = in_array($sep->klsrawat, [1, 2]) && Str::contains(\App\Helpers\NaikKelasHelper::translate($sep->klsnaik), ['VIP', 'Diatas']);

        if ($klsrawatNaik && $tarif_rs_sum < SafeAccess::object($groupResponse, 'response->cbg->tariff', 0)) {
            Log::channel(config('eklaim.log_channel'))->info("SKIP CEK NAIK KELAS", [
                "tarif_rs_sum" => $tarif_rs_sum,
                "cbg_tariff" => SafeAccess::object($groupResponse, 'response->cbg->tariff', 0),
            ]);

            return;
        }

        try {
            // Periksa spesialis dokter, lanjutkan hanya jika spesialisnya kandungan
            $regPeriksa = \App\Models\RegPeriksa::with('dokter.spesialis')->where('no_rawat', $sep->no_rawat)->first();

            // Ambil tarif dan tarif alternatif kelas 1
            $cbgTarif      = SafeAccess::object($groupResponse, 'response->cbg->tariff', 0);
            $altTariKelas1 = SafeAccess::object(collect($groupResponse->tarif_alt)->where('kelas', 'kelas_1')->first(), 'tarif_inacbg', 0);
            $altTariKelas2 = SafeAccess::object(collect($groupResponse->tarif_alt)->where('kelas', 'kelas_2')->first(), 'tarif_inacbg', 0);

            $kelasHak      = $sep->klsrawat == 2 ? $altTariKelas2 : ($sep->klsrawat == 1 ? $altTariKelas1 : 0);
            $kelasNaik     = $sep->klsnaik  == 3 ? $altTariKelas1 : ($sep->klsnaik == 8 ? $altTariKelas1 : 0);

            $xPersen = $tambahanBiaya = $presentase = null;
            $tarif_1 = $kelasNaik;

            if ($tarif_rs_sum > $cbgTarif) {
                $xPersen = $this->getKoeffisien($tarif_rs_sum, $cbgTarif, $kelasNaik, $kelasHak);
                if ($xPersen < 0) {
                    $xPersen = 0;
                    $tambahanBiaya = $kelasNaik - $cbgTarif;
		    $presentase = null;
                } else {
                    // Jika spesialis dokter adalah kandungan
                    if (Str::contains(Str::lower($regPeriksa->dokter->spesialis->nm_sps), 'kandungan')) {
                        [$presentase, $tambahanBiaya] = $this->tambahanBiayaKandungan($sep, $altTariKelas1, $kelasNaik, $kelasHak);
                    } else {
                        [$presentase, $tambahanBiaya] = $this->tambahanBiayaAnak($sep, $altTariKelas1, $kelasNaik, $kelasHak, $tarif_rs_sum, $cbgTarif);
                    }
                }

                $tarif_2 = $cbgTarif;
            } else {
                // Jika spesialis dokter adalah kandungan
                if (Str::contains(Str::lower($regPeriksa->dokter->spesialis->nm_sps), 'kandungan')) {
                    [$presentase, $tambahanBiaya] = $this->tambahanBiayaKandungan($sep, $altTariKelas1, $kelasNaik, $kelasHak);
                } else {
                    [$presentase, $tambahanBiaya] = $this->tambahanBiayaAnak($sep, $altTariKelas1, $kelasNaik, $kelasHak, $tarif_rs_sum, $cbgTarif);
                }

                $tarif_2 = $kelasHak;
            }

            if (!is_null($presentase) && strpos($presentase, '.') != false) {
                $presentase = number_format($presentase, 5);
            }

            // Simpan data naik kelas
            \App\Models\RsiaNaikKelas::updateOrCreate(
                ['no_sep' => $sep->no_sep], // Kondisi untuk update
                [
                    'jenis_naik'  => "Naik " . \App\Helpers\NaikKelasHelper::getJumlahNaik($sep->klsrawat, $sep->klsnaik) . " Kelas",
                    'tarif_1'     => $tarif_1,
                    'tarif_2'     => $tarif_2,
                    'presentase'  => $presentase ?? null,
                    'tarif_akhir' => $tambahanBiaya,
                    'diagnosa'    => $sep->nmdiagnosaawal,
                ]
            );
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::channel(config('eklaim.log_channel'))->error("CEK NAIK KELAS", [
                "sep"   => $sep->no_sep,
                "error" => $th->getMessage(),
        	"file"  => $th->getFile(),
        	"line"  => $th->getLine(),
            ]);

            return ApiResponse::error($th->getMessage(), 500);
        }
    }

    private function tambahanBiayaAnak($sep, $altTariKelas1, $kelasNaik, $kelasHak, $tarif_rs_sum, $cbgTarif)
    {
        // Jika spesialis dokter bukan kandungan (anak)
        if (!$altTariKelas1) {
            throw new \Exception("Pasien Naik Kelas namun, alt tarif kelas tidak ditemukan");
        }

        $isVip = $sep->klsnaik == 8;
        $xPersen = 0;

        if ($isVip) {
            $xPersen = $this->getKoeffisien($tarif_rs_sum, $cbgTarif, $kelasNaik, $kelasHak);
            if ($xPersen >= 75) {
                $xPersen = 75;
            }

            // Menghitung tarif tambahan berdasarkan persentase
            $tambahanBiaya = $kelasNaik - $kelasHak + ($kelasNaik * $xPersen / 100);
        } else {
            $tambahanBiaya = $kelasNaik - $kelasHak;
        }

        $presentase = $xPersen ?? 0;

        return [
            $presentase,
            $tambahanBiaya,
        ];
    }

    private function tambahanBiayaKandungan($sep, $altTariKelas1, $kelasNaik, $kelasHak)
    {
        $kamarInap = \App\Models\KamarInap::where('no_rawat', $sep->no_rawat)
            ->where('stts_pulang', '<>', 'Pindah Kamar')
            ->latest('tgl_masuk')->latest('jam_masuk')->first();

        if (!$altTariKelas1) {
            throw new \Exception("Pasien Naik Kelas namun, alt tarif kelas tidak ditemukan");
        }

        if (Str::contains(Str::lower($kamarInap->kd_kamar), 'kandungan va')) {
            $presentase    = 73;
            $tambahanBiaya = $kelasNaik - $kelasHak + ($kelasNaik * $presentase / 100);
        } elseif (Str::contains(Str::lower($kamarInap->kd_kamar), 'kandungan vb')) {
            $presentase    = 43;
            $tambahanBiaya = $kelasNaik - $kelasHak + ($kelasNaik * $presentase / 100);
        } else {
	    $presentase = null;
            $tambahanBiaya = $kelasNaik - $kelasHak;
        }

        return [
            $presentase,
            $tambahanBiaya,
        ];
    }

    private function getKoeffisien($realCost, $grouppingCost, $kelasNaik, $kelasHak)
    {
        $selisihDenganHasilGroup = $realCost - $grouppingCost;
        // Tarif Kelas Naik - Tarif Kelas Hak + (Tarif Kelas Naik * x%) = (Real Cost Rumah Sakit - cbgTariff) atau $selisihDenganHasilGroup

        // 1. Sederhanakan bagian satu
        $satu = $kelasNaik - $kelasHak;
        // Jadi, persamaan menjadi : $satu + (Tarif Kelas Naik * x%) = $selisihDenganHasilGroup

        // 2. pindahkan $satu ke sebelah kanan : (Tarif Kelas Naik * x%) = $selisihDenganHasilGroup - $satu
        $dua = $selisihDenganHasilGroup - $satu;
        // Jadi, persamaan menjadi : Tarif Kelas Naik * x% = $dua

        // 3. cari x%
        $x = $dua / $kelasNaik;
        $xPersen = $x * 100;

        return $xPersen;
    }

   private function decodeResponse($response): array
{
    // convert ke string
    $raw = (string) $response;

    // cari posisi JSON pertama (tanda { )
    $pos = strpos($raw, '{');
    if ($pos !== false) {
        $json = substr($raw, $pos);
        return json_decode($json, true) ?? [];
    }

    return [];
}


}
