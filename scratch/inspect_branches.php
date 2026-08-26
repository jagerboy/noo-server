<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- MASTER BRANCHES --- \n";
$branches = DB::table('master_branches')->get();
echo "Total branches: " . $branches->count() . "\n";
foreach ($branches->take(10) as $b) {
    echo "ID: {$b->branch_id} | Name: {$b->branch_name} | Region: " . var_export($b->region_code ?? null, true) . " | Entity: " . var_export($b->entity_code_principal ?? null, true) . " | Principal: " . var_export($b->principal_code ?? null, true) . "\n";
}

echo "\n--- NOO SUBMISSIONS DISTINCT REGIONS & ENTITIES --- \n";
$subs = DB::table('noo_submissions')
    ->select('region_code', 'principal', 'principal_code', 'branch_id')
    ->distinct()
    ->get();
foreach ($subs->take(10) as $s) {
    echo "Region: " . var_export($s->region_code, true) . " | Principal: " . var_export($s->principal, true) . " | PrincipalCode: " . var_export($s->principal_code, true) . " | Branch: " . var_export($s->branch_id, true) . "\n";
}
