<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sub = DB::table('noo_submissions')
    ->where('request_id', 'like', '%b14978fa%')
    ->orWhere('store_name', 'like', '%BUJANG%')
    ->first();

if ($sub) {
    echo "ID: {$sub->id}\n";
    echo "Request ID: {$sub->request_id}\n";
    echo "Store: {$sub->store_name}\n";
    echo "photo_depan_path: " . var_export($sub->photo_depan_path, true) . "\n";
    echo "photo_dalam_path: " . var_export($sub->photo_dalam_path, true) . "\n";
    echo "photo_ktp_path: " . var_export($sub->photo_ktp_path, true) . "\n";

    $fullDepan = storage_path('app/public/' . $sub->photo_depan_path);
    echo "Full path Depan: {$fullDepan}\n";
    echo "File exists in app/public: " . (file_exists($fullDepan) ? 'YES' : 'NO') . "\n";

    $publicDepan = public_path('storage/' . $sub->photo_depan_path);
    echo "Public storage path: {$publicDepan}\n";
    echo "File exists in public/storage: " . (file_exists($publicDepan) ? 'YES' : 'NO') . "\n";
} else {
    echo "Submission not found!\n";
}
