<?php

$baseUrl = "http://localhost:8010/api/v2/pasien/auth/register";
$nik = "332605" . random_int(1000000000, 9999999999);
$phone = "081234567890";

echo "🚀 [E2E TEST] Starting Registration Flow Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// 1. Check NIK
echo "Step 1: Checking NIK ($nik)... ";
$ch = curl_init("$baseUrl/cek-nik");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['nik' => $nik]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = json_decode(curl_exec($ch), true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    echo "✅ OK\n";
} else {
    echo "❌ FAILED ($code): " . ($res['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// 2. Send OTP
echo "Step 2: Sending OTP to $phone... ";
$ch = curl_init("$baseUrl/send-otp");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['no_telp' => $phone]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = json_decode(curl_exec($ch), true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    echo "✅ OK (OTP: " . ($res['data']['otp'] ?? 'HIDDEN') . ")\n";
} else {
    echo "❌ FAILED ($code): " . ($res['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// 3. Register
echo "Step 3: Submitting Registration... ";
$postData = [
    'nik' => $nik,
    'nm_pasien' => 'TEST PASIEN ' . time(),
    'jk' => 'L',
    'tgl_lahir' => '1990-01-01',
    'no_telp' => $phone,
    'alamat' => 'ALAMAT TEST NO. 123',
    'nm_ibu' => 'IBU TEST',
    'ktp_image' => new CURLFile('ktp_dummy.jpg', 'image/jpeg', 'ktp.jpg')
];

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = json_decode(curl_exec($ch), true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    echo "✅ SUCCESS! Reg ID: " . $res['data']['no_reg_online'] . "\n";
} else {
    echo "❌ FAILED ($code): " . ($res['message'] ?? 'Unknown error') . "\n";
    if (isset($res['errors'])) print_r($res['errors']);
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎉 [E2E TEST] Registration Flow Completed Successfully!\n";
