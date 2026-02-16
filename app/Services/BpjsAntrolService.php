<?php

namespace App\Services;

use App\Helpers\SignHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BpjsAntrolService
{
    protected $consId;
    protected $consSecret;
    protected $userKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->consId = config('services.bpjs.antrol.cons_id');
        $this->consSecret = config('services.bpjs.antrol.cons_secret');
        $this->userKey = config('services.bpjs.antrol.user_key');
        $this->baseUrl = config('services.bpjs.antrol.base_url');
    }

    /**
     * Perform a GET request to BPJS Antrol
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

        Log::info("Antrol Request: " . $this->baseUrl . $endpoint);

        try {
            $response = Http::withHeaders($headers)->timeout(10)->get($this->baseUrl . $endpoint);

            if ($response->successful()) {
                $data = $response->json();
                
                // If response is encrypted (usually in 'response' key as base64 string)
                if (isset($data['response']) && is_string($data['response']) && !empty($data['response'])) {
                    $decrypted = $this->decrypt($data['response'], $timestamp);
                    if ($decrypted) {
                        $data['response'] = json_decode($decrypted, true);
                    } else {
                        Log::error("Antrol Decryption Failed");
                    }
                }
                return $data;
            }

            Log::error("Antrol Request Failed: Status " . $response->status());
            return $response->json() ?: ['metadata' => ['code' => $response->status(), 'message' => 'Request Failed']];
            
        } catch (\Exception $e) {
            Log::error("Antrol Exception: " . $e->getMessage());
            return [
                'metadata' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Perform a POST request to BPJS Antrol
     * 
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    public function post($endpoint, $payload = [])
    {
        $signData = SignHelper::sign($this->consId, $this->consSecret);
        $timestamp = $signData['timestamp'];
        $signature = $signData['signature'];

        $headers = [
            'X-cons-id'   => $this->consId,
            'X-timestamp' => $timestamp,
            'X-signature' => $signature,
            'user_key'    => $this->userKey,
            'Content-Type' => 'application/json',
        ];

        Log::info("Antrol POST Request: " . $this->baseUrl . $endpoint . " Payload: " . json_encode($payload));

        try {
            $response = Http::withHeaders($headers)->timeout(10)->post($this->baseUrl . $endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['response']) && is_string($data['response']) && !empty($data['response'])) {
                    $decrypted = $this->decrypt($data['response'], $timestamp);
                    if ($decrypted) {
                        $data['response'] = json_decode($decrypted, true);
                    } else {
                        Log::error("Antrol POST Decryption Failed");
                    }
                }
                return $data;
            }

            Log::error("Antrol POST Request Failed: Status " . $response->status());
            return $response->json() ?: ['metadata' => ['code' => $response->status(), 'message' => 'Request Failed']];
            
        } catch (\Exception $e) {
            Log::error("Antrol POST Exception: " . $e->getMessage());
            return [
                'metadata' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Cancel queue
     * @param array $payload
     * @return array
     */
    public function cancelAntrean($payload)
    {
        return $this->post('/antrean/batal', $payload);
    }

    /**
     * Decrypt BPJS Antrol response
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
        
        Log::info("Antrol Decrypting - Timestamp: " . $timestamp . " | ID: " . $cId);
        
        try {
            // Key Raw: consId + consSecret + timestamp
            $keyRaw = $cId . $cSecret . $timestamp;
            
            $key = hex2bin(hash('sha256', $keyRaw));
            $iv = substr(hex2bin(hash('sha256', $keyRaw)), 0, 16);

            $decoded = base64_decode($string);
            $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                Log::error("openssl_decrypt error: " . openssl_error_string());
                return null;
            }

            // USE LZString as requested by user
            $decompressed = \LZCompressor\LZString::decompressFromEncodedURIComponent($decrypted);
            
            // FALLBACK 1: Standard LZString decompression
            if (!$decompressed) {
                Log::info("decompressFromEncodedURIComponent failed, trying standard LZString::decompress...");
                $decompressed = \LZCompressor\LZString::decompress($decrypted);
            }
            
            if (!$decompressed) {
                Log::warning("LZString decompression failed. Data might not be LZString or different Key was used.");
                // TRY Alt with Kode Faskes (just in case)
                $kodeFaskes = config('services.bpjs.kode_faskes');
                if ($kodeFaskes) {
                    $keyRawAlt = $cId . $cSecret . trim($kodeFaskes);
                    $keyAlt = hex2bin(hash('sha256', $keyRawAlt));
                    $ivAlt = substr(hex2bin(hash('sha256', $keyRawAlt)), 0, 16);
                    $decryptedAlt = openssl_decrypt($decoded, 'AES-256-CBC', $keyAlt, OPENSSL_RAW_DATA, $ivAlt);
                    if ($decryptedAlt !== false) {
                        $decompressedAlt = \LZCompressor\LZString::decompressFromEncodedURIComponent($decryptedAlt);
                        if ($decompressedAlt) {
                            Log::info("Success decrypting with LZString + kodeFaskes key!");
                            return $decompressedAlt;
                        }
                    }
                }
                
                Log::error("All LZString decompression attempts failed.");
                return null;
            }

            return $decompressed;
        } catch (\Exception $e) {
            Log::error("Antrol Decryption Exception: " . $e->getMessage());
            return null;
        }
    }
}
