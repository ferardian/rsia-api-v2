<?php

namespace App\Services;

use App\Helpers\SignHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BpjsVclaimService
{
    protected $consId;
    protected $consSecret;
    protected $userKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->consId = config('services.bpjs.vclaim.cons_id');
        $this->consSecret = config('services.bpjs.vclaim.cons_secret');
        $this->userKey = config('services.bpjs.vclaim.user_key');
        $this->baseUrl = config('services.bpjs.vclaim.base_url');
    }

    /**
     * Perform a GET request to BPJS VClaim
     * 
     * @param string $endpoint
     * @return array
     */
    public function get($endpoint)
    {
        $signData = SignHelper::sign($this->consId, $this->consSecret);
        $timestamp = $signData['timestamp'];
        $signature = $signData['signature'];

        $headers = [
            'X-cons-id'   => $this->consId,
            'X-timestamp' => $timestamp,
            'X-signature' => $signature,
            'user_key'    => $this->userKey,
        ];

        Log::info("VClaim Request: " . $this->baseUrl . $endpoint);

        try {
            $response = Http::withHeaders($headers)->timeout(10)->get($this->baseUrl . $endpoint);

            if ($response->successful()) {
                $data = $response->json();
                
                // If response is encrypted
                if (isset($data['response']) && is_string($data['response']) && !empty($data['response'])) {
                    $decrypted = $this->decrypt($data['response'], $timestamp);
                    if ($decrypted) {
                        $data['response'] = json_decode($decrypted, true);
                    } else {
                        Log::error("VClaim Decryption Failed");
                    }
                }
                return $data;
            }

            Log::error("VClaim Request Failed: Status " . $response->status());
            return $response->json() ?: ['metaData' => ['code' => $response->status(), 'message' => 'Request Failed']];
            
        } catch (\Exception $e) {
            Log::error("VClaim Exception: " . $e->getMessage());
            return [
                'metaData' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Decrypt BPJS VClaim response
     * Key: consId + consSecret + timestamp
     * 
     * @param string $string Base64 encoded encrypted string
     * @param string $timestamp The timestamp used for the request
     * @return string|null
     */
    protected function decrypt($string, $timestamp)
    {
        $cId = trim($this->consId);
        $cSecret = trim($this->consSecret);
        
        try {
            $keyRaw = $cId . $cSecret . $timestamp;
            $key = hex2bin(hash('sha256', $keyRaw));
            $iv = substr(hex2bin(hash('sha256', $keyRaw)), 0, 16);

            $decoded = base64_decode($string);
            $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                Log::error("openssl_decrypt error: " . openssl_error_string());
                return null;
            }

            $decompressed = \LZCompressor\LZString::decompressFromEncodedURIComponent($decrypted);
            
            if (!$decompressed) {
                $decompressed = \LZCompressor\LZString::decompress($decrypted);
            }
            
            return $decompressed;
        } catch (\Exception $e) {
            Log::error("VClaim Decryption Exception: " . $e->getMessage());
            return null;
        }
    }
}
