<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EdpMasterController extends Controller
{
    private function checkCanWrite(): bool
    {
        $user = Auth::user();
        $role = $user->role ?? 'EDP_REGION';
        return in_array($role, ['SUPERADMIN', 'ADMIN_PRINCIPAL']);
    }

    private function syncSequence(string $table): void
    {
        try {
            DB::statement("SELECT setval('{$table}_id_seq', COALESCE((SELECT MAX(id) FROM {$table}), 1))");
        } catch (Throwable $e) {
            // Ignore if sequence doesn't exist
        }
    }

    private function logAction(string $action, string $module, string $description): void
    {
        $user = Auth::user();
        DB::table('activity_logs')->insert([
            'username' => $user->name ?? $user->email,
            'user_role' => $user->role ?? 'UNKNOWN',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getFilterOptions(?\App\Models\User $user = null): array
    {
        $user = $user ?? Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';
        $regionCode = $user->region_code ?? null;

        $regionsQuery = DB::table('master_branches')
            ->select('region_code', 'region_name')
            ->distinct()
            ->whereNotNull('region_code');

        $entitiesQuery = DB::table('master_branches')
            ->select('entity_code_principal', 'entity_name_principal', 'region_code')
            ->distinct()
            ->whereNotNull('entity_code_principal');

        $branchesQuery = DB::table('master_branches')
            ->select('region_code', 'entity_code_principal', 'branch_id', 'branch_name', 'is_active')
            ->whereNotNull('branch_id');

        $cleanRegionCode = preg_replace('/^ADMIN\./i', '', $regionCode ?? '');

        if ($userRole === 'EDP_REGION' && !empty($regionCode)) {
            $regionsQuery->where('region_code', $cleanRegionCode);
            $entitiesQuery->where('region_code', $cleanRegionCode);
            $branchesQuery->where('region_code', $cleanRegionCode);
        } elseif ($userRole === 'ADMIN_PRINCIPAL' && !empty($regionCode)) {
            $regionsQuery->where('region_code', 'LIKE', "{$cleanRegionCode}%");
            $entitiesQuery->where('region_code', 'LIKE', "{$cleanRegionCode}%");
            $branchesQuery->where('region_code', 'LIKE', "{$cleanRegionCode}%");
        }

        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $entitiesQuery->where('entity_code_principal', 'LIKE', "{$user->entity_code_principal}%");
            $branchesQuery->where('entity_code_principal', 'LIKE', "{$user->entity_code_principal}%");
        }

        return [
            'regions' => $regionsQuery->orderBy('region_code')->get(),
            'entities' => $entitiesQuery->orderBy('entity_code_principal')->get(),
            'branches' => $branchesQuery->orderBy('branch_id', 'asc')->get(),
        ];
    }

    // 1. MASTER BRANCH
    public function masterBranch(Request $request): Response
    {
        $user = Auth::user();
        $query = DB::table('master_branches');

        $cleanRegionCode = preg_replace('/^ADMIN\./i', '', $user->region_code ?? '');
        $regionList = array_filter(array_map('trim', explode(',', $cleanRegionCode)));

        if (!empty($regionList)) {
            if ($user->role === 'EDP_REGION') {
                $query->whereIn('region_code', $regionList);
            } elseif ($user->role === 'ADMIN_PRINCIPAL') {
                $query->where(function ($q) use ($regionList) {
                    foreach ($regionList as $r) {
                        $q->orWhere('region_code', 'LIKE', "{$r}%");
                    }
                });
            }
        }

        if ($user->role === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $query->where('entity_code_principal', 'LIKE', "{$user->entity_code_principal}%");
        }

        $branches = $query->orderBy('branch_id', 'asc')->get();

        return Inertia::render('Edp/Master/MasterBranch', [
            'branches' => $branches,
            'canWrite' => $this->checkCanWrite(),
            'filters' => $request->only(['search', 'region_code', 'entity']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak. Peran Anda hanya Read-Only.']);
        }

        $request->validate([
            'region_code' => 'required|string',
            'principal_name' => 'required|string',
            'entity_code_principal' => 'required|string',
            'branch_id' => 'required|string|unique:master_branches,branch_id',
            'branch_name' => 'required|string',
            'pin_branch' => 'required|string',
        ]);

        $this->syncSequence('master_branches');

        DB::table('master_branches')->insert([
            'region_code' => strtoupper($request->region_code),
            'principal_name' => $request->principal_name,
            'entity_code_principal' => strtoupper($request->entity_code_principal),
            'branch_id' => strtoupper($request->branch_id),
            'branch_name' => $request->branch_name,
            'pin_branch' => $request->pin_branch,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Otomatis buatkan Record Counter Sequence untuk Cabang Baru ini
        $branchIdUpper = strtoupper($request->branch_id);
        $principalCodeUpper = strtoupper($request->principal_code ?: ($request->entity_code_principal ?: 'A'));
        $prefix = strlen($branchIdUpper) >= 5
            ? substr($branchIdUpper, 2, 3)
            : (strlen($branchIdUpper) >= 3 ? substr($branchIdUpper, 0, 3) : $branchIdUpper);

        DB::table('counter_sequences')->updateOrInsert(
            ['branch_id' => $branchIdUpper],
            [
                'principal_code' => $principalCodeUpper,
                'area_code' => strtoupper($request->region_code),
                'prefix' => strtoupper($prefix),
                'last_seq' => 0,
                'last_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->logAction('CREATE', 'MASTER_BRANCH', "Menambahkan Cabang Baru: {$request->branch_id} - {$request->branch_name} (Auto-created Counter Sequence)");

        return back()->with('success', "Data Master Cabang '{$request->branch_id} - {$request->branch_name}' berhasil ditambahkan.");
    }

    public function updateBranch(Request $request, $id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'region_code' => 'required|string',
            'principal_name' => 'required|string',
            'entity_code_principal' => 'required|string',
            'branch_name' => 'required|string',
            'pin_branch' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $updateData = [
            'region_code' => strtoupper($request->region_code),
            'principal_name' => $request->principal_name,
            'entity_code_principal' => strtoupper($request->entity_code_principal),
            'branch_name' => $request->branch_name,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ];

        if ($request->filled('pin_branch') && $request->pin_branch !== '******') {
            $updateData['pin_branch'] = $request->pin_branch;
        }

        DB::table('master_branches')->where('id', $id)->update($updateData);

        $this->logAction('UPDATE', 'MASTER_BRANCH', "Memperbarui Cabang ID {$id}: {$request->branch_name}");

        return back()->with('success', "Data Master Cabang '{$request->branch_name}' (ID: {$id}) berhasil diperbarui.");
    }

    public function destroyBranch($id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $branch = DB::table('master_branches')->where('id', $id)->first();
        $name = $branch ? "{$branch->branch_id} - {$branch->branch_name}" : "ID {$id}";

        DB::table('master_branches')->where('id', $id)->delete();
        $this->logAction('DELETE', 'MASTER_BRANCH', "Menghapus Master Cabang ID {$id}");

        return back()->with('success', "Data Master Cabang '{$name}' berhasil dihapus.");
    }

    // 2. MASTER SALESMAN
    public function masterSalesman(Request $request): Response
    {
        $user = Auth::user();
        $query = DB::table('master_salesmen')
            ->leftJoin('master_branches', 'master_salesmen.branch_id', '=', 'master_branches.branch_id')
            ->select(
                'master_salesmen.id',
                'master_salesmen.salesman_code',
                'master_salesmen.salesman_name',
                'master_salesmen.branch_id',
                'master_salesmen.is_active',
                'master_salesmen.created_at',
                'master_salesmen.updated_at',
                'master_branches.branch_name',
                DB::raw('COALESCE(master_branches.region_code, master_salesmen.region_code) as region_code'),
                'master_branches.entity_code_principal'
            );

        if ($user->role !== 'SUPERADMIN' && !empty($user->region_code)) {
            $regPrefix = substr($user->region_code, 0, 6);
            $query->where(function ($q) use ($user, $regPrefix) {
                $q->where('master_branches.region_code', 'LIKE', "{$user->region_code}%")
                  ->orWhere('master_salesmen.region_code', 'LIKE', "{$user->region_code}%")
                  ->orWhere('master_branches.region_code', 'LIKE', "{$regPrefix}%")
                  ->orWhere('master_salesmen.region_code', 'LIKE', "{$regPrefix}%");
            });
        }

        $salesmen = $query->orderBy('master_salesmen.salesman_code', 'asc')->get();

        return Inertia::render('Edp/Master/MasterSalesman', [
            'salesmen' => $salesmen,
            'canWrite' => $this->checkCanWrite(),
            'filters' => $request->only(['search', 'region_code', 'branch_id']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    public function storeSalesman(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'salesman_code' => 'required|string|unique:master_salesmen,salesman_code',
            'salesman_name' => 'required|string',
            'branch_id' => 'required|string',
            'region_code' => 'nullable|string',
        ]);

        $this->syncSequence('master_salesmen');

        DB::table('master_salesmen')->insert([
            'salesman_code' => strtoupper($request->salesman_code),
            'salesman_name' => $request->salesman_name,
            'branch_id' => strtoupper($request->branch_id),
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAction('CREATE', 'MASTER_SALESMAN', "Menambahkan Salesman: {$request->salesman_name} ({$request->salesman_code})");

        return back()->with('success', "Data Master Salesman '{$request->salesman_code} - {$request->salesman_name}' berhasil ditambahkan.");
    }

    public function updateSalesman(Request $request, $id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'salesman_name' => 'required|string',
            'branch_id' => 'required|string',
            'region_code' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        DB::table('master_salesmen')->where('id', $id)->update([
            'salesman_name' => $request->salesman_name,
            'branch_id' => strtoupper($request->branch_id),
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ]);

        $this->logAction('UPDATE', 'MASTER_SALESMAN', "Memperbarui Salesman ID {$id}: {$request->salesman_name}");

        return back()->with('success', "Data Master Salesman '{$request->salesman_name}' ({$request->salesman_code}) berhasil diperbarui.");
    }

    public function destroySalesman($id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $salesman = DB::table('master_salesmen')->where('id', $id)->first();
        $name = $salesman ? "{$salesman->salesman_code} - {$salesman->salesman_name}" : "ID {$id}";

        DB::table('master_salesmen')->where('id', $id)->delete();
        $this->logAction('DELETE', 'MASTER_SALESMAN', "Menghapus Salesman ID {$id}");

        return back()->with('success', "Data Master Salesman '{$name}' berhasil dihapus.");
    }

    // 3. MASTER SPV
    public function masterSpv(Request $request): Response
    {
        $user = Auth::user();
        $query = DB::table('master_spvs')
            ->leftJoin('master_branches', 'master_spvs.branch_id', '=', 'master_branches.branch_id')
            ->select(
                'master_spvs.id',
                'master_spvs.salescode',
                'master_spvs.nama',
                'master_spvs.area',
                'master_spvs.branch_id',
                'master_spvs.distributor_name',
                'master_spvs.is_active',
                'master_spvs.created_at',
                'master_spvs.updated_at',
                'master_branches.branch_name',
                'master_branches.region_code',
                'master_branches.entity_code_principal'
            );

        if ($user->role !== 'SUPERADMIN' && !empty($user->region_code)) {
            $regPrefix = substr($user->region_code, 0, 6);
            $query->where(function ($q) use ($user, $regPrefix) {
                $q->where('master_branches.region_code', 'LIKE', "{$user->region_code}%")
                  ->orWhere('master_branches.region_code', 'LIKE', "{$regPrefix}%");
            });
        }

        $spvsRaw = $query->orderBy('master_spvs.salescode', 'asc')->get();

        // Group data by salescode agar SPV unik hanya memiliki 1 baris di tabel UI
        $groupedSpvs = $spvsRaw->groupBy('salescode')->map(function ($items) {
            $first = $items->first();
            $branchIds = $items->pluck('branch_id')->filter()->unique()->values()->all();
            $branches = $items->map(function ($item) {
                return [
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch_name ?? $item->branch_id,
                    'region_code' => $item->region_code,
                    'entity_code_principal' => $item->entity_code_principal,
                ];
            })->unique('branch_id')->values()->all();

            return [
                'id' => $first->id,
                'salescode' => $first->salescode,
                'nama' => $first->nama,
                'area' => $first->area,
                'is_active' => (bool) $first->is_active,
                'created_at' => $first->created_at,
                'updated_at' => $first->updated_at,
                'branch_ids' => $branchIds,
                'branches' => $branches,
                'region_codes' => array_values(array_filter(array_unique(array_column($branches, 'region_code')))),
                'entity_codes' => array_values(array_filter(array_unique(array_column($branches, 'entity_code_principal')))),
            ];
        })->values();

        return Inertia::render('Edp/Master/MasterSpv', [
            'spvs' => $groupedSpvs,
            'canWrite' => $this->checkCanWrite(),
            'filters' => $request->only(['search', 'region_code', 'entity', 'branch_id']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    public function storeSpv(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'salescode' => 'required|string',
            'password' => 'required|string',
            'nama' => 'required|string',
            'branch_ids' => 'nullable|array',
            'branch_id' => 'nullable|string',
            'area' => 'nullable|string',
        ]);

        $salescode = strtoupper(trim($request->salescode));
        $branchIds = $request->input('branch_ids');
        if (!is_array($branchIds) || empty($branchIds)) {
            $branchIds = $request->filled('branch_id') ? [$request->branch_id] : [];
        }

        $branchIds = array_values(array_unique(array_filter(array_map('strtoupper', $branchIds))));

        if (empty($branchIds)) {
            return back()->withErrors(['branch_ids' => 'Pilih minimal satu Cabang Distributor.']);
        }

        // Sync sequence agar PostgreSQL nextval tidak duplikat dengan data import CSV
        $this->syncSequence('master_spvs');

        $hashedPassword = \Illuminate\Support\Facades\Hash::make(trim($request->password));

        foreach ($branchIds as $bId) {
            DB::table('master_spvs')->updateOrInsert(
                ['salescode' => $salescode, 'branch_id' => $bId],
                [
                    'password' => $hashedPassword,
                    'nama' => $request->nama,
                    'area' => $request->area,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->logAction('CREATE', 'MASTER_SPV', "Menambahkan SPV: {$request->nama} ({$salescode}) dengan " . count($branchIds) . " cabang");

        return back()->with('success', "Data Master SPV Area '{$salescode} - {$request->nama}' (" . count($branchIds) . " Cabang) berhasil ditambahkan.");
    }

    public function updateSpv(Request $request, $id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'nama' => 'required|string',
            'branch_ids' => 'nullable|array',
            'branch_id' => 'nullable|string',
            'area' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $targetSpv = DB::table('master_spvs')->where('id', $id)->first();
        $salescode = $targetSpv ? $targetSpv->salescode : null;

        if ($request->filled('salescode')) {
            $salescode = strtoupper(trim($request->salescode));
        }

        if (!$salescode) {
            return back()->withErrors(['error' => 'Data SPV tidak ditemukan.']);
        }

        $branchIds = $request->input('branch_ids');
        if (!is_array($branchIds) || empty($branchIds)) {
            $branchIds = $request->filled('branch_id') ? [$request->branch_id] : [];
        }

        $branchIds = array_values(array_unique(array_filter(array_map('strtoupper', $branchIds))));

        if (empty($branchIds)) {
            return back()->withErrors(['branch_ids' => 'Pilih minimal satu Cabang Distributor.']);
        }

        // Hapus asosiasi cabang yang sudah tidak dicentang lagi untuk SPV ini
        DB::table('master_spvs')
            ->where('salescode', $salescode)
            ->whereNotIn('branch_id', $branchIds)
            ->delete();

        $updateData = [
            'nama' => $request->nama,
            'area' => $request->area,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = \Illuminate\Support\Facades\Hash::make(trim($request->password));
        }

        $this->syncSequence('master_spvs');

        foreach ($branchIds as $bId) {
            $existing = DB::table('master_spvs')
                ->where('salescode', $salescode)
                ->where('branch_id', $bId)
                ->first();

            if ($existing) {
                DB::table('master_spvs')->where('id', $existing->id)->update($updateData);
            } else {
                DB::table('master_spvs')->insert(array_merge($updateData, [
                    'salescode' => $salescode,
                    'branch_id' => $bId,
                    'password' => $updateData['password'] ?? ($targetSpv ? $targetSpv->password : \Illuminate\Support\Facades\Hash::make('123456')),
                    'created_at' => now(),
                ]));
            }
        }

        $this->logAction('UPDATE', 'MASTER_SPV', "Memperbarui SPV {$salescode}: {$request->nama} (" . count($branchIds) . " Cabang)");

        return back()->with('success', "Data Master SPV Area '{$request->nama}' ({$salescode}) berhasil diperbarui untuk " . count($branchIds) . " Cabang.");
    }

    public function destroySpv($id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $spv = DB::table('master_spvs')->where('id', $id)->first();
        if (!$spv) {
            return back()->withErrors(['error' => 'Data SPV tidak ditemukan.']);
        }

        $salescode = $spv->salescode;
        $name = "{$salescode} - {$spv->nama}";

        DB::table('master_spvs')->where('salescode', $salescode)->delete();
        $this->logAction('DELETE', 'MASTER_SPV', "Menghapus SPV {$salescode}");

        return back()->with('success', "Data Master SPV Area '{$name}' (beserta seluruh cabang kovernya) berhasil dihapus.");
    }

    // 4. MASTER EDP (Konsolidasi ke tabel users)
    public function masterEdp(Request $request): Response
    {
        $user = Auth::user();
        $query = DB::table('users')
            ->select('id', 'username', 'name as nama', 'role', 'region_code', 'is_active');

        if ($user->role !== 'SUPERADMIN' && !empty($user->region_code)) {
            $query->where('region_code', 'LIKE', "{$user->region_code}%");
        }

        if ($request->filled('region_code')) {
            $query->where('region_code', $request->input('region_code'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('username', 'ILIKE', "%{$s}%")
                  ->orWhere('name', 'ILIKE', "%{$s}%")
                  ->orWhere('region_code', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        $edps = $query->orderBy('username', 'asc')->paginate($perPage)->withQueryString();

        return Inertia::render('Edp/Master/MasterEdp', [
            'edps' => $edps,
            'canWrite' => $this->checkCanWrite(),
            'filters' => $request->only(['search', 'region_code']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    public function storeEdp(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string',
            'nama' => 'required|string',
            'role' => 'required|string',
            'region_code' => 'nullable|string',
        ]);

        $username = strtolower(trim($request->username));
        $this->syncSequence('users');

        DB::table('users')->insert([
            'username' => $username,
            'name' => $request->nama,
            'email' => "{$username}@noo.portal",
            'password' => \Illuminate\Support\Facades\Hash::make(trim($request->password)),
            'role' => $request->role,
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAction('CREATE', 'MASTER_EDP', "Menambahkan EDP: {$username}");

        return back()->with('success', "Data Master EDP '{$username}' ({$request->nama}) berhasil ditambahkan.");
    }

    public function updateEdp(Request $request, $id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'nama' => 'required|string',
            'role' => 'required|string',
            'region_code' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $data = [
            'name' => $request->nama,
            'role' => $request->role,
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ];

        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make(trim($request->password));
        }

        DB::table('users')->where('id', $id)->update($data);
        $this->logAction('UPDATE', 'MASTER_EDP', "Memperbarui EDP ID {$id}");

        return back()->with('success', "Data Master EDP '{$request->nama}' berhasil diperbarui.");
    }

    public function destroyEdp($id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $edp = DB::table('users')->where('id', $id)->first();
        $name = $edp ? "{$edp->username} ({$edp->name})" : "ID {$id}";

        DB::table('users')->where('id', $id)->delete();
        $this->logAction('DELETE', 'MASTER_EDP', "Menghapus Master EDP ID {$id}");

        return back()->with('success', "Data Master EDP '{$name}' berhasil dihapus.");
    }

    // 5. MASTER OUTLET TYPES
    public function masterOutletTypes(Request $request): Response
    {
        $query = DB::table('master_outlet_types');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('code', 'ILIKE', "%{$s}%")
                  ->orWhere('description', 'ILIKE', "%{$s}%");
            });
        }

        $outletTypes = $query->orderBy('code', 'asc')->get();

        return Inertia::render('Edp/Master/MasterOutletTypes', [
            'outletTypes' => $outletTypes,
            'canWrite' => $this->checkCanWrite(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function storeOutletType(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'code' => 'required|string|unique:master_outlet_types,code',
            'description' => 'required|string',
        ]);

        $this->syncSequence('master_outlet_types');

        DB::table('master_outlet_types')->insert([
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAction('CREATE', 'MASTER_OUTLET_TYPES', "Menambahkan Tipe Outlet: {$request->code}");

        return back()->with('success', "Data Master Tipe Outlet '{$request->code} - {$request->description}' berhasil ditambahkan.");
    }

    public function updateOutletType(Request $request, $id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $request->validate([
            'description' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        DB::table('master_outlet_types')->where('id', $id)->update([
            'description' => $request->description,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ]);

        $this->logAction('UPDATE', 'MASTER_OUTLET_TYPES', "Memperbarui Tipe Outlet ID {$id}");

        return back()->with('success', "Data Master Tipe Outlet '{$request->description}' berhasil diperbarui.");
    }

    public function destroyOutletType($id): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak.']);
        }

        $type = DB::table('master_outlet_types')->where('id', $id)->first();
        $name = $type ? "{$type->code} - {$type->description}" : "ID {$id}";

        DB::table('master_outlet_types')->where('id', $id)->delete();
        $this->logAction('DELETE', 'MASTER_OUTLET_TYPES', "Menghapus Tipe Outlet ID {$id}");

        return back()->with('success', "Data Master Tipe Outlet '{$name}' berhasil dihapus.");
    }

    // 6. COUNTER SEQUENCE
    public function counterSequence(Request $request): Response
    {
        $user = Auth::user();

        // Auto-sync cabang master_branches yang belum memiliki record counter_sequences
        $existingBranchIds = DB::table('counter_sequences')->pluck('branch_id')->toArray();
        $missingBranches = DB::table('master_branches')
            ->whereNotIn('branch_id', $existingBranchIds)
            ->get();

        foreach ($missingBranches as $mb) {
            $bid = strtoupper($mb->branch_id);
            $prefix = strlen($bid) >= 5 ? substr($bid, 2, 3) : (strlen($bid) >= 3 ? substr($bid, 0, 3) : $bid);
            $pCode = strtoupper(!empty($mb->principal_code) ? $mb->principal_code : (!empty($mb->entity_code_principal) ? $mb->entity_code_principal : 'A'));

            DB::table('counter_sequences')->insert([
                'principal_code' => $pCode,
                'branch_id' => $bid,
                'area_code' => strtoupper($mb->region_code ?: ''),
                'prefix' => strtoupper($prefix),
                'last_seq' => 0,
                'last_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Auto-fix existing sequence prefixes if they were using 5-character prefix instead of chars 3,4,5
        $allSequences = DB::table('counter_sequences')->get();
        foreach ($allSequences as $seq) {
            $bid = strtoupper($seq->branch_id);
            if (strlen($bid) >= 5 && strlen($seq->prefix) === 5 && strtoupper($seq->prefix) === substr($bid, 0, 5)) {
                $newPrefix = substr($bid, 2, 3);
                DB::table('counter_sequences')
                    ->where('id', $seq->id)
                    ->update(['prefix' => strtoupper($newPrefix), 'updated_at' => now()]);
            }
        }

        $query = DB::table('counter_sequences')
            ->join('master_branches', 'counter_sequences.branch_id', '=', 'master_branches.branch_id')
            ->select('counter_sequences.*', 'master_branches.branch_name', 'master_branches.region_code', 'master_branches.entity_code_principal');

        $cleanRegionCode = preg_replace('/^ADMIN\./i', '', $user->region_code ?? '');
        $regionList = array_filter(array_map('trim', explode(',', $cleanRegionCode)));

        if (!empty($regionList)) {
            if ($user->role === 'EDP_REGION') {
                $query->whereIn('master_branches.region_code', $regionList);
            } elseif ($user->role === 'ADMIN_PRINCIPAL') {
                $query->where(function ($q) use ($regionList) {
                    foreach ($regionList as $r) {
                        $q->orWhere('master_branches.region_code', 'LIKE', "{$r}%");
                    }
                });
            }
        }

        if ($user->role === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $query->where('master_branches.entity_code_principal', 'LIKE', "{$user->entity_code_principal}%");
        }

        if ($request->filled('region_code')) {
            $query->where('master_branches.region_code', $request->input('region_code'));
        }
        if ($request->filled('entity')) {
            $query->where('master_branches.entity_code_principal', $request->input('entity'));
        }
        if ($request->filled('branch_id')) {
            $query->where('counter_sequences.branch_id', $request->input('branch_id'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('counter_sequences.branch_id', 'ILIKE', "%{$s}%")
                  ->orWhere('master_branches.branch_name', 'ILIKE', "%{$s}%")
                  ->orWhere('counter_sequences.prefix', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        $sequences = $query->orderBy('counter_sequences.branch_id', 'asc')->paginate($perPage)->withQueryString();

        return Inertia::render('Edp/Master/CounterSequence', [
            'sequences' => $sequences,
            'canEditSequence' => true,
            'canWriteFull' => $this->checkCanWrite(),
            'filters' => $request->only(['search', 'region_code', 'entity', 'branch_id']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    public function storeCounterSequence(Request $request): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->withErrors(['error' => 'Akses ditolak. Peran Anda hanya Read-Only.']);
        }

        $request->validate([
            'branch_id' => 'required|string|exists:master_branches,branch_id',
            'principal_code' => 'required|string',
            'prefix' => 'required|string',
            'last_seq' => 'required|integer|min:0',
        ]);

        $branch = DB::table('master_branches')->where('branch_id', strtoupper($request->branch_id))->first();

        DB::table('counter_sequences')->updateOrInsert(
            ['branch_id' => strtoupper($request->branch_id)],
            [
                'principal_code' => strtoupper($request->principal_code),
                'area_code' => $branch->region_code ?? '',
                'prefix' => strtoupper($request->prefix),
                'last_seq' => (int) $request->last_seq,
                'last_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->logAction('CREATE_SEQUENCE', 'COUNTER_SEQUENCE', "Menambahkan Counter Sequence cabang: {$request->branch_id}");

        return back()->with('success', "Counter Sequence cabang {$request->branch_id} berhasil dibuat/diperbarui.");
    }

    public function updateCounterSequence(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'last_seq' => 'required|integer|min:0',
            'prefix' => 'nullable|string',
            'principal_code' => 'nullable|string',
        ]);

        $seq = DB::table('counter_sequences')->where('id', $id)->first();
        if (!$seq) {
            return back()->withErrors(['error' => 'Data counter sequence tidak ditemukan.']);
        }

        $updateData = [
            'last_seq' => $request->last_seq,
            'last_updated_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->checkCanWrite()) {
            if ($request->filled('prefix')) {
                $updateData['prefix'] = strtoupper($request->prefix);
            }
            if ($request->filled('principal_code')) {
                $updateData['principal_code'] = strtoupper($request->principal_code);
            }
        }

        DB::table('counter_sequences')->where('id', $id)->update($updateData);

        $this->logAction('UPDATE_SEQUENCE', 'COUNTER_SEQUENCE', "Mengubah Sequence cabang {$seq->branch_id}: last_seq={$request->last_seq}");

        return back()->with('success', "Counter Sequence cabang {$seq->branch_id} berhasil diperbarui.");
    }

    public function downloadTemplate(string $type)
    {
        $columns = [];
        $sampleRow = [];
        $title = '';

        switch (strtolower($type)) {
            case 'branch':
                $title = 'MASTER BRANCH';
                $columns = ['region_code', 'region_name', 'principal_code', 'principal_name', 'entity_code_principal', 'entity_name_principal', 'area_code', 'branch_id', 'branch_name', 'pin_branch'];
                $sampleRow = ['ASWSUM1', 'SUMATERA 1', 'A', 'ASWFOODS', 'ASW', 'ASWFOODS MEDAN', 'SUM1', 'DAMDN003', 'CV. DWI TUNGGAL SENTOSA', '123456'];
                break;
            case 'salesman':
                $title = 'MASTER SALESMAN';
                $columns = ['salesman_code', 'salesman_name', 'branch_id', 'region_code', 'entity_code_principal'];
                $sampleRow = ['SEAMDN32', 'KURNIA SE', 'DAMDN003', 'ASWSUM1', 'ASW'];
                break;
            case 'spv':
                $title = 'MASTER SPV AREA';
                $columns = ['salescode', 'nama', 'password', 'branch_id', 'area', 'distributor_name'];
                $sampleRow = ['SPVMEDAN01', 'BUDI SPV MEDAN', '123456', 'DAMDN003', 'SUM1', 'CV. DWI TUNGGAL SENTOSA'];
                break;
            case 'counter_sequence':
                $title = 'COUNTER SEQUENCE';
                $columns = ['principal_code', 'area_code', 'branch_id', 'prefix', 'last_seq'];
                $sampleRow = ['A', 'SUM1', 'DAMDN003', 'MED', '15'];
                break;
            default:
                abort(404, 'Template tidak ditemukan.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Header placement
        $colIndex = 'A';
        foreach ($columns as $column) {
            $sheet->setCellValue($colIndex . '1', $column);
            $colIndex++;
        }

        // Header Styling
        $lastCol = chr(ord('A') + count($columns) - 1);
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'], // Royal Navy Blue
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Placement & Styling
        $colIndex = 'A';
        foreach ($sampleRow as $val) {
            $sheet->setCellValue($colIndex . '2', $val);
            $colIndex++;
        }

        $sampleRange = "A2:{$lastCol}2";
        $sheet->getStyle($sampleRange)->applyFromArray([
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Auto-fit column widths
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Stream Excel file
        $filename = "TEMPLATE_MASTER_" . strtoupper($type) . ".xlsx";
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function bulkUpload(Request $request, string $type): RedirectResponse
    {
        if (!$this->checkCanWrite()) {
            return back()->with('error', 'Fitur Bulk Upload Master Data hanya dapat dilakukan oleh Admin / Superadmin.');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $inserted = 0;
        $failedRows = [];

        try {
            $rows = [];
            if (in_array($ext, ['xlsx', 'xls'])) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rawRows = $sheet->toArray();
                if (count($rawRows) > 1) {
                    $rows = array_slice($rawRows, 1);
                }
            } else {
                $handle = fopen($file->getRealPath(), 'r');
                if (!$handle) {
                    return back()->with('error', 'Gagal membaca berkas CSV.');
                }
                fgetcsv($handle);
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                if (empty($row) || !is_array($row)) continue;

                $r = array_map(fn($v) => is_null($v) ? '' : trim((string)$v), $row);

                if (empty(implode('', $r))) continue;

                try {
                    switch (strtolower($type)) {
                        case 'branch':
                            $branchId = strtoupper($r[7] ?? '');
                            if (empty($branchId)) {
                                $failedRows[] = "Baris {$rowNumber}: 'branch_id' (kolom H) tidak boleh kosong.";
                                continue 2;
                            }

                            DB::table('master_branches')->updateOrInsert(
                                ['branch_id' => $branchId],
                                [
                                    'region_code' => strtoupper($r[0] ?? ''),
                                    'region_name' => $r[1] ?? '',
                                    'principal_code' => !empty($r[2]) ? strtoupper($r[2]) : 'A',
                                    'principal_name' => !empty($r[3]) ? $r[3] : 'ASWFOODS',
                                    'entity_code_principal' => !empty($r[4]) ? strtoupper($r[4]) : 'ASW',
                                    'entity_name_principal' => $r[5] ?? '',
                                    'area_code' => strtoupper($r[6] ?? ''),
                                    'branch_name' => $r[8] ?? "CABANG {$branchId}",
                                    'pin_branch' => !empty($r[9]) ? $r[9] : '123456',
                                    'is_active' => true,
                                    'updated_at' => now(),
                                ]
                            );
                            $inserted++;
                            break;

                        case 'salesman':
                            $salesmanCode = strtoupper($r[0] ?? '');
                            $branchId = strtoupper($r[2] ?? '');

                            if (empty($salesmanCode)) {
                                $failedRows[] = "Baris {$rowNumber}: 'salesman_code' (kolom A) wajib diisi.";
                                continue 2;
                            }
                            if (empty($branchId)) {
                                $failedRows[] = "Baris {$rowNumber} (Salesman '{$salesmanCode}'): 'branch_id' (kolom C) kosong / tidak diisi.";
                                continue 2;
                            }

                            $branchExists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
                            if (!$branchExists) {
                                DB::table('master_branches')->insert([
                                    'region_code' => !empty($r[3]) ? strtoupper($r[3]) : 'ASWSUM1',
                                    'region_name' => 'SUMATERA 1',
                                    'principal_code' => 'A',
                                    'principal_name' => 'ASWFOODS',
                                    'entity_code_principal' => !empty($r[4]) ? strtoupper($r[4]) : 'ASW',
                                    'branch_id' => $branchId,
                                    'branch_name' => "DISTRIBUTOR {$branchId}",
                                    'pin_branch' => '123456',
                                    'is_active' => true,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }

                            DB::table('master_salesmen')->updateOrInsert(
                                ['salesman_code' => $salesmanCode],
                                [
                                    'salesman_name' => $r[1] ?? '',
                                    'branch_id' => $branchId,
                                    'region_code' => !empty($r[3]) ? strtoupper($r[3]) : null,
                                    'entity_code_principal' => !empty($r[4]) ? strtoupper($r[4]) : null,
                                    'is_active' => true,
                                    'updated_at' => now(),
                                ]
                            );
                            $inserted++;
                            break;

                        case 'spv':
                            $salescode = strtoupper($r[0] ?? '');
                            $branchId = strtoupper($r[3] ?? '');

                            if (empty($salescode)) {
                                $failedRows[] = "Baris {$rowNumber}: 'salescode' (kolom A) wajib diisi.";
                                continue 2;
                            }
                            if (empty($branchId)) {
                                $failedRows[] = "Baris {$rowNumber} (SPV '{$salescode}'): 'branch_id' (kolom D) kosong / tidak diisi.";
                                continue 2;
                            }

                            $branchExists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
                            if (!$branchExists) {
                                DB::table('master_branches')->insert([
                                    'region_code' => 'ASWSUM1',
                                    'region_name' => 'SUMATERA 1',
                                    'principal_code' => 'A',
                                    'principal_name' => 'ASWFOODS',
                                    'entity_code_principal' => 'ASW',
                                    'branch_id' => $branchId,
                                    'branch_name' => "DISTRIBUTOR {$branchId}",
                                    'pin_branch' => '123456',
                                    'is_active' => true,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }

                            DB::table('master_spvs')->updateOrInsert(
                                [
                                    'salescode' => $salescode,
                                    'branch_id' => $branchId,
                                ],
                                [
                                    'nama' => $r[1] ?? '',
                                    'password' => \Illuminate\Support\Facades\Hash::make(!empty($r[2]) ? $r[2] : '123456'),
                                    'area' => !empty($r[4]) ? $r[4] : null,
                                    'distributor_name' => !empty($r[5]) ? $r[5] : null,
                                    'is_active' => true,
                                    'updated_at' => now(),
                                ]
                            );
                            $inserted++;
                            break;

                        case 'counter_sequence':
                            $branchId = strtoupper($r[2] ?? '');
                            if (empty($branchId)) {
                                $failedRows[] = "Baris {$rowNumber}: 'branch_id' (kolom C) wajib diisi.";
                                continue 2;
                            }

                            $principalCode = !empty($r[0]) ? strtoupper($r[0]) : 'A';
                            DB::table('counter_sequences')->updateOrInsert(
                                [
                                    'principal_code' => $principalCode,
                                    'branch_id' => $branchId,
                                ],
                                [
                                    'area_code' => $r[1] ?? '',
                                    'prefix' => strtoupper($r[3] ?? ''),
                                    'last_seq' => (int) ($r[4] ?? 0),
                                    'last_updated_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                            $inserted++;
                            break;
                    }
                } catch (\Throwable $rowEx) {
                    $failedRows[] = "Baris {$rowNumber}: Gagal diimpor ({$rowEx->getMessage()})";
                }
            }

            $this->logAction('BULK_UPLOAD', 'MASTER_DATA', "Bulk Upload Master {$type}: {$inserted} berhasil, " . count($failedRows) . " gagal.");

            if (count($failedRows) === 0) {
                return back()->with('success', "📦 Bulk Upload Master Data " . strtoupper($type) . " Berhasil! Total {$inserted} baris data berhasil diimpor & diperbarui.");
            }

            $errorDetailMsg = implode(' | ', array_slice($failedRows, 0, 5));
            if (count($failedRows) > 5) {
                $errorDetailMsg .= " (dan " . (count($failedRows) - 5) . " baris lainnya)...";
            }

            if ($inserted > 0) {
                return back()->with('warning', "⚠️ Bulk Upload Selesai Sebagian. Berhasil: {$inserted} baris. Gagal: " . count($failedRows) . " baris. Rincian: {$errorDetailMsg}");
            }

            return back()->with('error', "❌ Bulk Upload Gagal (" . count($failedRows) . " baris bermasalah). Rincian: {$errorDetailMsg}");

        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses Bulk Upload: ' . $e->getMessage());
        }
    }
}

