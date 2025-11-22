<?php

namespace App\Helpers;

class SignHelper
{
    public static function rsia($name, $id_or_nik)
    {
        $hash = \App\Models\SidikJari::where('id', $id_or_nik)->select('id', \Illuminate\Support\Facades\DB::raw('SHA1(sidikjari) as sidikjari'))->first();

        if ($hash) {
            $hash = $hash->sidikjari;
        } else {
            $hash = \Illuminate\Support\Facades\Hash::make($id_or_nik);
        }

        $text     = 'Dikeluarkan di RSIA Aisyiyah Pekajangan, Ditandatangani secara elektronik oleh ' . $name . '. ID : ' . $hash;
        $logoPath = public_path('assets/images/logo.png');

        $qrCode = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->writerOptions([])
            ->data($text)
            ->logoPath($logoPath)
            ->logoResizeToWidth(100)
            ->encoding(new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'))
            ->errorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
            ->build();

        return $qrCode;
    }

    public static function blankRsia()
    {
        $text     = 'Dikeluarkan di RSIA Aisyiyah Pekajangan, Ditandatangani secara elektronik pada ' . date('Y-m-d H:i:s') . ' ID : ' . \Illuminate\Support\Facades\Hash::make(date('Y-m-d H:i:s'));
        $logoPath = public_path('assets/images/logo.png');

        $qrCode = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->writerOptions([])
            ->data($text)
            ->logoPath($logoPath)
            ->logoResizeToWidth(100)
            ->encoding(new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'))
            ->errorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
            ->build();

        return $qrCode;
    } 

    public static function toQr($data)
    {
        $qrCode = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->writerOptions([])
            ->data($data)
            ->encoding(new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'))
            ->errorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
            ->build();

        return $qrCode;
    }

    /**
     * Generate BPJS API signature
     * Based on BPJS API documentation for HMAC-SHA256 signature generation
     */
    public static function sign($consId, $consSecret)
    {
        // Set UTC timezone as per BPJS reference
        date_default_timezone_set('UTC');

        // Compute timestamp as per BPJS reference
        $timestamp = strval(time() - strtotime('1970-01-01 00:00:00'));

        // Generate the string to sign
        $stringToSign = $consId . '&' . $timestamp;

        // Generate HMAC-SHA256 signature
        $signature = hash_hmac('sha256', $stringToSign, $consSecret, true);

        // Base64 encode signature as per BPJS reference
        $signature = base64_encode($signature);

        return [
            'timestamp' => $timestamp,
            'signature' => $signature
        ];
    }
}
