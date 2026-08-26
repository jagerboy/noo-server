<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = DB::table('users')->where('role', 'ADMIN_PRINCIPAL')->first();

if ($user) {
    echo "User role: {$user->role}, region: {$user->region_code}, entity: {$user->entity_code_principal}\n";
    $userRole = $user->role;
    $userRegion = $user->region_code;
    $userEntity = $user->entity_code_principal;

    $regionsQuery = DB::table('master_branches')->select('region_code', 'region_name')->distinct()->whereNotNull('region_code');
    $entitiesQuery = DB::table('master_branches')->select('entity_code_principal', 'entity_name_principal', 'region_code')->distinct()->whereNotNull('entity_code_principal');
    $branchesQuery = DB::table('master_branches')->select('branch_id', 'branch_name', 'region_code', 'entity_code_principal');

    if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
        $regionsQuery->where('region_code', 'LIKE', "{$userRegion}%");
        $entitiesQuery->where('region_code', 'LIKE', "{$userRegion}%");
        $branchesQuery->where('region_code', 'LIKE', "{$userRegion}%");
    }

    if ($userRole === 'ADMIN_PRINCIPAL' && !empty($userEntity)) {
        $entitiesQuery->where('entity_code_principal', $userEntity);
        $branchesQuery->where('entity_code_principal', $userEntity);
    }

    $regions = $regionsQuery->orderBy('region_code')->get();
    $entities = $entitiesQuery->orderBy('entity_code_principal')->get();
    $branches = $branchesQuery->orderBy('branch_id', 'asc')->get();

    echo "Regions count: " . $regions->count() . "\n";
    print_r($regions->toArray());
    echo "Entities count: " . $entities->count() . "\n";
    print_r($entities->toArray());
    echo "Branches count: " . $branches->count() . "\n";
    print_r($branches->take(5)->toArray());
} else {
    echo "No ADMIN_PRINCIPAL user found!\n";
}
