<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\Notification\FirebaseCloudMessaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\AndroidConfig;

// Setup Target
$rm = "046064"; // GANTI DENGAN RM ANDA (CONTOH: 046064)
$sanitizedRm = str_replace('/', '', $rm);
$topic = "pasien_" . $sanitizedRm;

$title = "TEST: Waktunya Minum Obat 💊";
$body = "Halo, ini adalah notifikasi uji coba sistem baru (Fail-Safe). Mohon konfirmasi jika muncul di HP.";

echo "Mengirim notifikasi ke topic: $topic...\n";

try {
    $message = CloudMessage::withTarget('topic', $topic)
        ->withData([
            'type' => 'MEDICINE_REMINDER_DIRECT',
            'topic' => $topic,
            'meds' => 'Obat Test (Paracetamol)',
            'route' => '/home',
            'title' => $title,
            'body' => $body,
        ])
        ->withAndroidConfig(AndroidConfig::fromArray([
            'priority' => 'high',
        ]));

    FirebaseCloudMessaging::send($message);
    echo "✅ Notifikasi berhasil dikirim!\n";
} catch (\Exception $e) {
    echo "❌ Gagal mengirim: " . $e->getMessage() . "\n";
}
