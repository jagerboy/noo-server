<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sub = DB::table('noo_submissions')
    ->select('id', 'store_name', 'photo_depan_path', 'photo_dalam_path', 'photo_ktp_path')
    ->whereNotNull('photo_depan_path')
    ->orWhereNotNull('photo_ktp_path')
    ->limit(5)
    ->get();

echo "Submissions count: " . count($sub) . "\n";
foreach ($sub as $s) {
    echo "ID: {$s->id} | Store: {$s->store_name}\n";
    echo "  Depan: {$s->photo_depan_path}\n";
    echo "  Dalam: {$s->photo_dalam_path}\n";
    echo "  KTP: {$s->photo_ktp_path}\n";
}

$storagePublic = public_path('storage');
echo "Public storage path: {$storagePublic}\n";
echo "Exists: " . (file_exists($storagePublic) ? 'YES' : 'NO') . "\n";
if (file_exists($storagePublic)) {
    echo "Is link: " . (is_link($storagePublic) ? 'YES' : 'NO') . "\n";
}
