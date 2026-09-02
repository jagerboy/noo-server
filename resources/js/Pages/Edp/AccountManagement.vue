<script setup lang="js">
/**
 * Halaman Manajemen Akun & User Role Manager Portal NOO+ (Superadmin Only)
 */
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  accounts: Object,
  regions: Array,
  principalAreas: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const activeTab = ref('all_users'); // 'all_users' | 'role_manager'
const isAddModalOpen = ref(false);
const editingAccount = ref(null);

const addForm = useForm({
  username: '',
  password: 'password123',
  nama: '',
  role: 'EDP_REGION',
  region_code: '',
  entity_code_principal: 'ASW',
});

const editForm = useForm({
  nama: '',
  password: '',
  role: 'EDP_REGION',
  region_code: '',
  is_active: true,
});

// State Multiple Select Single Region
const selectedSingleRegionsAdd = ref([]);
const selectedSingleRegions = ref([]);

// Mapping Sub-Region untuk auto select saat Principal Area di-klik
const principalAreaSubRegionsMap = {
  'ASWSUM': ['ASWSUM1', 'ASWSUM2', 'ASWSUM3'],
  'ASWJWA': ['ASWJWA1', 'ASWJWA2'],
  'ASWPUL': ['ASWPUL1'],
  'INAJWA': ['INAJWA1', 'INAJWA2'],
  'INAPUL': ['INAPUL1'],
  'INASUM': ['INASUM1', 'INASUM2'],
};

function onPrincipalAreaSelect(paCode, mode = 'edit') {
  const subRegions = principalAreaSubRegionsMap[paCode] || [];
  if (mode === 'add') {
    addForm.region_code = paCode;
    selectedSingleRegionsAdd.value = [...subRegions];
  } else {
    editForm.region_code = paCode;
    selectedSingleRegions.value = [...subRegions];
  }
}

function onSingleRegionToggle(mode = 'edit') {
  if (mode === 'add') {
    addForm.region_code = selectedSingleRegionsAdd.value.join(',');
  } else {
    editForm.region_code = selectedSingleRegions.value.join(',');
  }
}

function clearRegionSelection(mode = 'edit') {
  if (mode === 'add') {
    addForm.region_code = '';
    selectedSingleRegionsAdd.value = [];
  } else {
    editForm.region_code = '';
    selectedSingleRegions.value = [];
  }
}

// STATE MATRIKS HAK AKSES (Lengkap Sesuai Seluruh Menu Portal NOO+)
const defaultMatrixData = [
  {
    category: '🏠 HOME & DASHBOARD',
    categoryClass: 'bg-slate-100 font-bold text-slate-900',
    items: [
      { id: 'dashboard_view', name: 'Home (Executive Dashboard & Summary Statistik)', edp: true, admin: true, super: true },
    ]
  },
  {
    category: '📬 NOO VERIFICATION',
    categoryClass: 'bg-emerald-50/60 font-bold text-emerald-900',
    items: [
      { id: 'inbox_view', name: 'Inbox Submisi NOO (Tabel & Preview Detail Toko)', edp: true, admin: true, super: true },
      { id: 'inbox_approve', name: 'Approval Submisi NOO (Generate Kode Principal)', edp: true, admin: true, super: true },
      { id: 'inbox_reject', name: 'Tolak / Reject Submisi Toko (dengan Alasan)', edp: true, admin: true, super: true },
      { id: 'inbox_edit_info', name: 'Ubah Nama Outlet & Alamat Toko di Modal Detail', edp: true, admin: true, super: true },
      { id: 'inbox_revise_ktp', name: 'Revisi Foto KTP (1x dengan Watermark Standar)', edp: true, admin: true, super: true },
      { id: 'inbox_unlock_ktp', name: 'Buka Kunci (Unlock) Revisi KTP Toko', edp: false, admin: false, super: true },
      { id: 'inbox_reset', name: 'Reset / Pembatalan Approval EDP & Status Reject', edp: false, admin: true, super: true },
      { id: 'inbox_toggle_ro', name: 'Ubah Status Registered Outlet (RO) Toko', edp: true, admin: true, super: true },
      { id: 'inbox_export', name: 'Export Data Approved & Rejected (.xlsx Excel)', edp: true, admin: true, super: true },
    ]
  },
  {
    category: '📊 MONITORING RO (FINALISASI)',
    categoryClass: 'bg-amber-50/70 font-bold text-amber-950',
    items: [
      { id: 'monitoring_ro_view', name: 'Menu Monitoring RO (Target vs Realisasi RO Salesman)', edp: false, admin: false, super: true },
      { id: 'monitoring_ro_upload', name: 'Upload & Download Format Target RO Salesman (.xlsx)', edp: false, admin: false, super: true },
    ]
  },
  {
    category: '📈 PROGRESS TRACKING NOO',
    categoryClass: 'bg-blue-50/60 font-bold text-blue-900',
    items: [
      { id: 'progress_view', name: 'Workflow Status Progress Submisi Toko', edp: true, admin: true, super: true },
      { id: 'progress_reset_admin', name: 'Reset Inputan Admin Distributor (Kembali ke Draft SE)', edp: false, admin: true, super: true },
      { id: 'progress_reset_spv', name: 'Reset Keputusan SPV (Kembali ke Pushed to SPV)', edp: false, admin: true, super: true },
    ]
  },
  {
    category: '🏢 NOO MASTER DATA',
    categoryClass: 'bg-purple-50/60 font-bold text-purple-900',
    items: [
      { id: 'master_view', name: 'Lihat Master Data (Branch, Salesman, SPV, Outlet Type)', edp: true, admin: true, super: true },
      { id: 'master_crud', name: 'Kelola Master Data (Tambah / Edit / Hapus Data Master)', edp: false, admin: true, super: true },
      { id: 'master_seq', name: 'Setting & Update Counter Sequence Kode Customer Principal', edp: false, admin: true, super: true },
      { id: 'master_bulk', name: 'Bulk Upload / Import Massal Master Data (CSV)', edp: false, admin: false, super: true },
    ]
  },
  {
    category: '⚙️ MANAJEMEN AKUN & AUDIT LOGS',
    categoryClass: 'bg-gray-100 font-bold text-gray-900',
    items: [
      { id: 'sys_users', name: 'Kelola Akun Pengguna Portal (User Management & Password)', edp: false, admin: false, super: true },
      { id: 'sys_role_matrix', name: 'Atur Matriks Hak Akses Peran Pengguna (Role Manager)', edp: false, admin: false, super: true },
      { id: 'sys_logs', name: 'Audit Log & Riwayat Aktivitas Seluruh Pengguna Sistem', edp: false, admin: true, super: true },
    ]
  }
];

const savedMatrix = localStorage.getItem('noo_permission_matrix_v2');
const permissionMatrix = ref(savedMatrix ? JSON.parse(savedMatrix) : defaultMatrixData);
const isEditingMatrix = ref(false);
const matrixSuccessBanner = ref('');
let matrixBackup = null;

function startEditMatrix() {
  matrixBackup = JSON.parse(JSON.stringify(permissionMatrix.value));
  isEditingMatrix.value = true;
  matrixSuccessBanner.value = '';
}

function cancelEditMatrix() {
  if (matrixBackup) {
    permissionMatrix.value = JSON.parse(JSON.stringify(matrixBackup));
  }
  isEditingMatrix.value = false;
}

function saveMatrix() {
  localStorage.setItem('noo_permission_matrix_v2', JSON.stringify(permissionMatrix.value));
  isEditingMatrix.value = false;
  matrixSuccessBanner.value = 'Matriks Hak Akses Peran Pengguna berhasil diperbarui dan disimpan!';
}

function handleSearch() {
  router.get(route('edp.account_management'), { search: search.value }, { preserveState: true, replace: true });
}

function submitAddAccount() {
  addForm.post(route('edp.account_management.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(acc) {
  editingAccount.value = acc;
  editForm.nama = acc.name || acc.nama || '';
  editForm.password = '';
  editForm.role = acc.role || 'EDP_REGION';
  editForm.region_code = acc.region_code || '';
  editForm.is_active = Boolean(acc.is_active);

  // Auto populate selected single regions checkboxes
  if (acc.region_code) {
    const codes = acc.region_code.split(',').map(s => s.trim());
    const paSubRegions = principalAreaSubRegionsMap[acc.region_code];
    if (paSubRegions) {
      selectedSingleRegions.value = [...paSubRegions];
    } else {
      selectedSingleRegions.value = codes;
    }
  } else {
    selectedSingleRegions.value = [];
  }
}

function submitEditAccount() {
  if (!editingAccount.value) return;
  editForm.put(route('edp.account_management.update', editingAccount.value.id), {
    onSuccess: () => {
      editingAccount.value = null;
    },
  });
}

function deleteAccount(acc) {
  if (confirm(`Yakin ingin menghapus akun ${acc.username}?`)) {
    router.delete(route('edp.account_management.destroy', acc.id));
  }
}

function formatRole(role) {
  if (!role) return '-';
  const roleMap = {
    'SUPERADMIN': 'Superadmin',
    'ADMIN_PRINCIPAL': 'Admin Principal',
    'EDP_REGION': 'EDP Region',
    'SPV_AREA': 'SPV Area',
    'ADMIN_DISTRIBUTOR': 'Admin Distributor',
  };
  return roleMap[role] || role.replace(/_/g, ' ');
}
</script>

<template>
  <EdpLayout>
    <Head title="Manajemen Akun & Role Manager - Portal Principal NOO+" />

    <div class="space-y-6">
      <!-- Header Page -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>Manajemen Akun & User Role Manager</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Pengelolaan Akun Pengguna Portal Principal NOO+, Password, Peran Akses (Superadmin, Admin Principal, Operator Region) & Region Scope.
          </p>
        </div>

        <button
          @click="isAddModalOpen = true"
          class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
        >
          <span>+ Tambah Akun Baru</span>
        </button>
      </div>

      <!-- Navigation Tabs (Sesuai Referensi User Role Manager - ASW & INAFOODS Dual Brand) -->
      <div class="flex items-center gap-2 border-b border-gray-200 bg-white px-4 pt-2 rounded-t-xl">
        <button
          @click="activeTab = 'all_users'"
          :class="[
            'px-4 py-2.5 text-xs font-bold transition border-b-2 flex items-center gap-2 cursor-pointer',
            activeTab === 'all_users'
              ? 'border-[#542B85] text-[#542B85] bg-purple-50/70 rounded-t-lg font-extrabold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
        >
          <span>Daftar Pengguna</span>
          <span class="px-2 py-0.5 text-[10px] rounded-full bg-purple-100 text-[#542B85] font-extrabold border border-purple-200">
            {{ accounts.total || accounts.data?.length || 0 }}
          </span>
        </button>

        <button
          @click="activeTab = 'role_manager'"
          :class="[
            'px-4 py-2.5 text-xs font-bold transition border-b-2 flex items-center gap-2 cursor-pointer',
            activeTab === 'role_manager'
              ? 'border-[#D9232A] text-[#D9232A] bg-rose-50/70 rounded-t-lg font-extrabold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
        >
          <span>Matriks Hak Akses (User Role Manager)</span>
        </button>
      </div>

      <!-- TAB 1: ALL USERS TABLE -->
      <div v-if="activeTab === 'all_users'" class="space-y-4">
        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] flex items-center gap-3">
          <input
            type="text"
            v-model="search"
            @keyup.enter="handleSearch"
            placeholder="Cari Username, Nama Pengguna, Role, Region..."
            class="w-full max-w-md px-3.5 py-2 text-xs bg-white border border-[#D1D5DB] rounded-lg focus:ring-2 focus:ring-[#10B981]"
          />
          <button @click="handleSearch" class="px-4 py-2 text-xs font-semibold text-white bg-[#374151] rounded-lg hover:bg-gray-800 cursor-pointer">Cari</button>
        </div>

        <!-- Table Accounts -->
        <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
                <tr>
                  <th class="px-4 py-3">Username</th>
                  <th class="px-4 py-3">Nama Lengkap</th>
                  <th class="px-4 py-3">Peran Akses (Role)</th>
                  <th class="px-4 py-3">Region Scope / Area</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E5E7EB]">
                <tr v-for="acc in accounts.data" :key="acc.id" class="hover:bg-emerald-50/20 transition">
                  <td class="px-4 py-3 font-bold text-[#111827]">{{ acc.username || acc.region_code }}</td>
                  <td class="px-4 py-3 text-[#374151] font-semibold">{{ acc.name || acc.nama || '-' }}</td>
                  <td class="px-4 py-3">
                    <span 
                      :class="[
                        'px-2.5 py-1 text-[10px] font-bold rounded-md uppercase tracking-wider',
                        acc.role === 'SUPERADMIN' ? 'bg-purple-100 text-purple-800 border border-purple-300' :
                        acc.role === 'ADMIN_PRINCIPAL' ? 'bg-blue-100 text-blue-800 border border-blue-300' :
                        acc.role === 'SPV_AREA' ? 'bg-amber-100 text-amber-900 border border-amber-300' :
                        acc.role === 'ADMIN_DISTRIBUTOR' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' :
                        'bg-emerald-100 text-emerald-800 border border-emerald-300'
                      ]"
                    >
                      {{ formatRole(acc.role || 'EDP_REGION') }}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-bold text-[#059669]">{{ acc.region_code || 'ALL REGIONS (GLOBAL)' }}</td>
                  <td class="px-4 py-3">
                    <span v-if="acc.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md border border-emerald-300">AKTIF</span>
                    <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md border border-red-300">NON-AKTIF</span>
                  </td>
                  <td class="px-4 py-3 text-right space-x-2">
                    <button @click="openEditModal(acc)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded border border-blue-200 cursor-pointer">Edit</button>
                    <button @click="deleteAccount(acc)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded border border-red-200 cursor-pointer">Hapus</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination
            :links="accounts.links"
            :from="accounts.from"
            :to="accounts.to"
            :total="accounts.total"
          />
        </div>
      </div>

      <!-- TAB 2: USER ROLE MANAGER MATRIX TABLE (REFERENSI GAMBAR 2) -->
      <div v-if="activeTab === 'role_manager'" class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <!-- Header Card dengan Tombol Edit / Simpan Matriks -->
        <div class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
              <span>Matriks Hak Akses Peran Pengguna (Permissions Matrix)</span>
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">Penetapan dan otorisasi menu portal untuk masing-masing peran (Role Manager)</p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <div class="text-xs font-semibold px-3 py-1.5 bg-emerald-100 text-emerald-800 rounded-lg border border-emerald-300 flex items-center gap-1.5">
              <span>Superadmin Control</span>
            </div>

            <!-- Tombol Edit / Simpan / Batal -->
            <button
              v-if="!isEditingMatrix"
              @click="startEditMatrix"
              class="px-3.5 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer"
            >
              <span>✏️ Edit Matriks</span>
            </button>
            <template v-else>
              <button
                @click="cancelEditMatrix"
                class="px-3.5 py-1.5 text-xs font-bold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition cursor-pointer"
              >
                Batal
              </button>
              <button
                @click="saveMatrix"
                class="px-4 py-1.5 text-xs font-bold text-white bg-[#059669] hover:bg-[#047857] rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer"
              >
                <span>💾 Simpan Matriks</span>
              </button>
            </template>
          </div>
        </div>

        <!-- Banner Notifikasi Sukses Simpan Matriks -->
        <div v-if="matrixSuccessBanner" class="p-3 bg.emerald-100 border-b border-emerald-300 text-emerald-800 text-xs font-bold flex items-center justify-between px-5">
          <div class="flex items-center gap-2">
            <span>✔</span>
            <span>{{ matrixSuccessBanner }}</span>
          </div>
          <button @click="matrixSuccessBanner = ''" class="font-bold hover:text-emerald-950 text-sm cursor-pointer">✕</button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-bold uppercase">
              <tr>
                <th class="px-5 py-3 w-1/2">Menu & Fitur Portal</th>
                <th class="px-4 py-3 text-center">Operator Verifikator<br/><span class="text-[10px] text-emerald-700 font-normal">(EDP Region)</span></th>
                <th class="px-4 py-3 text-center">Admin Principal<br/><span class="text-[10px] text-blue-700 font-normal">(Master Data)</span></th>
                <th class="px-4 py-3 text-center">Superadmin<br/><span class="text-[10px] text-purple-700 font-normal">(Full Access)</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <template v-for="cat in permissionMatrix" :key="cat.category">
                <tr :class="[cat.categoryClass, 'font-bold']">
                  <td colspan="4" class="px-5 py-2 uppercase tracking-wider text-[11px]">{{ cat.category }}</td>
                </tr>
                <tr v-for="item in cat.items" :key="item.id" class="hover:bg-gray-50/80 transition">
                  <td class="px-5 py-2.5 pl-8 text-gray-800 font-medium">{{ item.name }}</td>
                  <td class="text-center py-2.5">
                    <input
                      type="checkbox"
                      v-model="item.edp"
                      :disabled="!isEditingMatrix"
                      class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
                    />
                  </td>
                  <td class="text-center py-2.5">
                    <input
                      type="checkbox"
                      v-model="item.admin"
                      :disabled="!isEditingMatrix"
                      class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
                    />
                  </td>
                  <td class="text-center py-2.5">
                    <input
                      type="checkbox"
                      v-model="item.super"
                      :disabled="!isEditingMatrix"
                      class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer"
                    />
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL BUAT AKUN BARU -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl relative my-auto overflow-hidden border border-gray-200">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white shrink-0">
              <h3 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span>➕ Buat Akun Pengguna Baru</span>
              </h3>
              <button @click="isAddModalOpen = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 font-bold transition cursor-pointer">✕</button>
            </div>

            <!-- Body Modal -->
            <form @submit.prevent="submitAddAccount" class="flex-1 overflow-y-auto p-6 space-y-4 bg-white">
              <!-- Field Textfields -->
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">USERNAME</label>
                <input type="text" v-model="addForm.username" placeholder="Contoh: edp.aswsum1" class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" required />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">NAMA LENGKAP PENGGUNA</label>
                <input type="text" v-model="addForm.nama" placeholder="Contoh: Budi Santoso" class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" required />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">PASSWORD</label>
                <input type="password" v-model="addForm.password" class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" required />
              </div>

              <!-- PERAN AKSES (ROLE) RADIO BUTTONS -->
              <div class="pt-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">PERAN AKSES (ROLE)</label>
                <div class="space-y-2 border border-gray-200 p-3 rounded-xl bg-gray-50/60">
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="addForm.role" value="EDP_REGION" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                    <span>Operator Verifikator (EDP Region)</span>
                  </label>
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="addForm.role" value="ADMIN_PRINCIPAL" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                    <span>Admin Principal</span>
                  </label>
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="addForm.role" value="SUPERADMIN" class="w-4 h-4 text-purple-600 focus:ring-purple-500" />
                    <span>Superadmin</span>
                  </label>
                </div>
              </div>

              <!-- REGION SCOPE / AREA COVERAGE -->
              <div class="pt-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">REGION SCOPE / WILAYAH OPERASIONAL</label>
                <div class="max-h-52 overflow-y-auto space-y-2 p-3 border border-gray-200 rounded-xl bg-gray-50/60">
                  <!-- Global All Regions -->
                  <label class="flex items-center gap-2.5 text-xs font-bold text-gray-900 cursor-pointer">
                    <input type="radio" v-model="addForm.region_code" value="" @change="selectedSingleRegionsAdd = []" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                    <span>🌐 Semua Region (Global / Superadmin)</span>
                  </label>

                  <!-- Principal Area -->
                  <div v-if="principalAreas && principalAreas.length > 0" class="pt-2 border-t border-gray-200">
                    <div class="text-[10px] font-extrabold text-blue-800 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                      <span>⭐ PRINCIPAL AREA (MENCAKUP BEBERAPA SUB-REGION)</span>
                      <button type="button" v-if="addForm.region_code" @click="clearRegionSelection('add')" class="text-[10px] text-rose-600 hover:underline normal-case">Reset Pilihan</button>
                    </div>
                    <div class="space-y-1.5">
                      <label v-for="pa in principalAreas" :key="pa.region_code" class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                        <input type="radio" v-model="addForm.region_code" :value="pa.region_code" @change="onPrincipalAreaSelect(pa.region_code, 'add')" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                        <span>⭐ {{ pa.region_name }}</span>
                      </label>
                    </div>
                  </div>

                  <!-- Single Regions (Multiple Select Checkboxes) -->
                  <div class="pt-2 border-t border-gray-200">
                    <div class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                      <span>📍 SPESIFIK SINGLE REGION (BISA PILIH LEBIH DARI SATU)</span>
                      <span v-if="selectedSingleRegionsAdd.length > 0" class="text-[10px] text-emerald-700 font-bold">({{ selectedSingleRegionsAdd.length }} Terpilih)</span>
                    </div>
                    <div class="space-y-1.5">
                      <label v-for="r in regions" :key="r.region_code || r" class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                        <input 
                          type="checkbox" 
                          :value="r.region_code || r" 
                          v-model="selectedSingleRegionsAdd"
                          @change="onSingleRegionToggle('add')"
                          class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 rounded-xs" 
                        />
                        <span>{{ r.region_code ? `${r.region_code} - ${r.region_name}` : r }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Footer Buttons -->
              <div class="flex justify-end gap-2 pt-3 border-t border-gray-200">
                <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition cursor-pointer">Batal</button>
                <button type="submit" :disabled="addForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-[#059669] hover:bg-[#047857] rounded-xl shadow-xs transition cursor-pointer">Simpan Akun</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- MODAL EDIT AKUN -->
      <Teleport to="body">
        <div v-if="editingAccount" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl relative my-auto overflow-hidden border border-gray-200">
            <!-- Header Modal -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white shrink-0">
              <h3 class="text-base font-bold text-[#111827] flex items-center gap-2">
                <span>✏️ Edit Akun <span class="font-mono text-emerald-700">{{ editingAccount.username }}</span></span>
              </h3>
              <button @click="editingAccount = null" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 font-bold transition cursor-pointer">✕</button>
            </div>

            <!-- Body Modal -->
            <form @submit.prevent="submitEditAccount" class="flex-1 overflow-y-auto p-6 space-y-4 bg-white">
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">NAMA LENGKAP PENGGUNA</label>
                <input type="text" v-model="editForm.nama" class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" required />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">PASSWORD BARU (Kosongkan jika tidak diubah)</label>
                <input type="password" v-model="editForm.password" placeholder="Password Baru..." class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" />
              </div>

              <!-- PERAN AKSES (ROLE) RADIO BUTTONS -->
              <div class="pt-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">PERAN AKSES (ROLE)</label>
                <div class="space-y-2 border border-gray-200 p-3 rounded-xl bg-gray-50/60">
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="editForm.role" value="EDP_REGION" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                    <span>Operator Verifikator (EDP Region)</span>
                  </label>
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="editForm.role" value="ADMIN_PRINCIPAL" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                    <span>Admin Principal</span>
                  </label>
                  <label class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                    <input type="radio" v-model="editForm.role" value="SUPERADMIN" class="w-4 h-4 text-purple-600 focus:ring-purple-500" />
                    <span>Superadmin</span>
                  </label>
                </div>
              </div>

              <!-- REGION SCOPE / AREA COVERAGE -->
              <div class="pt-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">REGION SCOPE / WILAYAH OPERASIONAL</label>
                <div class="max-h-52 overflow-y-auto space-y-2 p-3 border border-gray-200 rounded-xl bg-gray-50/60">
                  <!-- Global All Regions -->
                  <label class="flex items-center gap-2.5 text-xs font-bold text-gray-900 cursor-pointer">
                    <input type="radio" v-model="editForm.region_code" value="" @change="selectedSingleRegions = []" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                    <span>🌐 Semua Region (Global / Superadmin)</span>
                  </label>

                  <!-- Principal Area -->
                  <div v-if="principalAreas && principalAreas.length > 0" class="pt-2 border-t border-gray-200">
                    <div class="text-[10px] font-extrabold text-blue-800 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                      <span>⭐ PRINCIPAL AREA (MENCAKUP BEBERAPA SUB-REGION)</span>
                      <button type="button" v-if="editForm.region_code" @click="clearRegionSelection('edit')" class="text-[10px] text-rose-600 hover:underline normal-case">Reset Pilihan</button>
                    </div>
                    <div class="space-y-1.5">
                      <label v-for="pa in principalAreas" :key="pa.region_code" class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                        <input type="radio" v-model="editForm.region_code" :value="pa.region_code" @change="onPrincipalAreaSelect(pa.region_code, 'edit')" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                        <span>⭐ {{ pa.region_name }}</span>
                      </label>
                    </div>
                  </div>

                  <!-- Single Regions (Multiple Select Checkboxes) -->
                  <div class="pt-2 border-t border-gray-200">
                    <div class="text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                      <span>📍 SPESIFIK SINGLE REGION (BISA PILIH LEBIH DARI SATU)</span>
                      <span v-if="selectedSingleRegions.length > 0" class="text-[10px] text-emerald-700 font-bold">({{ selectedSingleRegions.length }} Terpilih)</span>
                    </div>
                    <div class="space-y-1.5">
                      <label v-for="r in regions" :key="r.region_code || r" class="flex items-center gap-2.5 text-xs text-gray-800 font-semibold cursor-pointer">
                        <input 
                          type="checkbox" 
                          :value="r.region_code || r" 
                          v-model="selectedSingleRegions"
                          @change="onSingleRegionToggle('edit')"
                          class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 rounded-xs" 
                        />
                        <span>{{ r.region_code ? `${r.region_code} - ${r.region_name}` : r }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- STATUS AKUN RADIO BUTTONS -->
              <div class="pt-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">STATUS AKUN</label>
                <div class="flex items-center gap-6 border border-gray-200 p-3 rounded-xl bg-gray-50/60">
                  <label class="flex items-center gap-2 text-xs font-bold text-emerald-800 cursor-pointer">
                    <input type="radio" v-model="editForm.is_active" :value="true" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" />
                    <span>🟢 AKTIF</span>
                  </label>
                  <label class="flex items-center gap-2 text-xs font-bold text-rose-800 cursor-pointer">
                    <input type="radio" v-model="editForm.is_active" :value="false" class="w-4 h-4 text-rose-600 focus:ring-rose-500" />
                    <span>🔴 NON-AKTIF</span>
                  </label>
                </div>
              </div>

              <!-- Footer Buttons -->
              <div class="flex justify-end gap-2 pt-3 border-t border-gray-200">
                <button type="button" @click="editingAccount = null" class="px-4 py-2 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition cursor-pointer">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-[#059669] hover:bg-[#047857] rounded-xl shadow-xs transition cursor-pointer">Update Akun</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>
