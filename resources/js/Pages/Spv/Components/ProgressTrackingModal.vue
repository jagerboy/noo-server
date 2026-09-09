<script setup lang="js">
/**
 * Komponen Modal Progress Tracking NOO (SPV Area).
 * Menampilkan tabel pelacakan progres pengajuan toko NOO dari cabang-cabang binaan SPV.
 * Desain tabel dibuat dominan, lapang, dan langsung menampilkan progres alur kerja.
 * Menggunakan label bahasa Indonesia profesional (bukan nama kolom database).
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  myBranches: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'select-submission']);

// State Filter
const filterBranch = ref('ALL');
const filterMonth = ref('ALL');
const filterYear = ref(String(new Date().getFullYear()));
const filterStatus = ref('ALL');
const searchQuery = ref('');
const currentPage = ref(1);

// State Data & UI
const isLoading = ref(false);
const errorMessage = ref('');
const submissionsData = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0,
});
const metrics = ref({
  total: 0,
  pendingAdmin: 0,
  pendingSpv: 0,
  pendingEdp: 0,
  completed: 0,
  rejected: 0,
});
const availableYears = ref([new Date().getFullYear()]);

// State Detail Modal Submisi
const selectedDetail = ref(null);

// Daftar Pilihan Bulan (Bahasa Indonesia)
const monthOptions = [
  { value: 'ALL', label: 'Semua Bulan' },
  { value: '1', label: 'Januari' },
  { value: '2', label: 'Februari' },
  { value: '3', label: 'Maret' },
  { value: '4', label: 'April' },
  { value: '5', label: 'Mei' },
  { value: '6', label: 'Juni' },
  { value: '7', label: 'Juli' },
  { value: '8', label: 'Agustus' },
  { value: '9', label: 'September' },
  { value: '10', label: 'Oktober' },
  { value: '11', label: 'November' },
  { value: '12', label: 'Desember' },
];

// Pilihan Status Progres Alur Kerja
const statusOptions = [
  { value: 'ALL', label: 'Semua Status Progres' },
  { value: 'PENDING_ADMIN', label: 'Pending Admin Distributor' },
  { value: 'PENDING_SPV', label: 'Pending Review SPV Area' },
  { value: 'PENDING_EDP', label: 'Pending Persetujuan EDP' },
  { value: 'COMPLETED', label: 'Selesai / Approved' },
  { value: 'REJECTED', label: 'Ditolak / Rejected' },
];

// Helper Fetch Data dari Backend
let searchDebounceTimer = null;

async function fetchData(page = 1) {
  if (!props.show) return;
  isLoading.value = true;
  errorMessage.value = '';
  currentPage.value = page;

  try {
    const url = typeof route === 'function' && route().has('spv.progress_tracking_data')
      ? route('spv.progress_tracking_data')
      : '/spv/progress-tracking-data';

    const response = await axios.get(url, {
      params: {
        page: page,
        branch_id: filterBranch.value,
        month: filterMonth.value,
        year: filterYear.value,
        status: filterStatus.value,
        search: searchQuery.value.trim(),
      },
    });

    if (response.data) {
      submissionsData.value = response.data.submissions || { data: [], total: 0 };
      metrics.value = response.data.metrics || metrics.value;
      if (Array.isArray(response.data.availableYears) && response.data.availableYears.length > 0) {
        availableYears.value = response.data.availableYears;
      }
    }
  } catch (err) {
    console.error('Error fetching progress tracking data:', err);
    errorMessage.value = 'Gagal memuat data pelacakan progres. Silakan coba kembali.';
  } finally {
    isLoading.value = false;
  }
}

function handleSearchInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    fetchData(1);
  }, 350);
}

function handleFilterChange() {
  fetchData(1);
}

function resetFilter() {
  filterBranch.value = 'ALL';
  filterMonth.value = 'ALL';
  filterYear.value = 'ALL';
  filterStatus.value = 'ALL';
  searchQuery.value = '';
  fetchData(1);
}

function selectQuickStatus(statusKey) {
  filterStatus.value = statusKey;
  fetchData(1);
}

// Helper Format Tanggal Indonesia
function formatDateTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '-';
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDateOnly(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '-';
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

// Helper Format Hari & Minggu Rute
function formatRouteDays(item) {
  if (!item) return '';
  const days = [];
  if (item.h1 === 'Y' || item.h1 === 'YES') days.push('Senin');
  if (item.h2 === 'Y' || item.h2 === 'YES') days.push('Selasa');
  if (item.h3 === 'Y' || item.h3 === 'YES') days.push('Rabu');
  if (item.h4 === 'Y' || item.h4 === 'YES') days.push('Kamis');
  if (item.h5 === 'Y' || item.h5 === 'YES') days.push('Jumat');
  if (item.h6 === 'Y' || item.h6 === 'YES') days.push('Sabtu');
  if (item.h7 === 'Y' || item.h7 === 'YES') days.push('Minggu');
  return days.length > 0 ? days.join(', ') : '';
}

function formatRouteWeeks(item) {
  if (!item) return '';
  const weeks = [];
  if (item.m1 === 'Y' || item.m1 === 'YES') weeks.push('M1');
  if (item.m2 === 'Y' || item.m2 === 'YES') weeks.push('M2');
  if (item.m3 === 'Y' || item.m3 === 'YES') weeks.push('M3');
  if (item.m4 === 'Y' || item.m4 === 'YES') weeks.push('M4');
  return weeks.length > 0 ? weeks.join(', ') : '';
}

function formatRouteSummary(item) {
  if (!item) return '---';
  const days = formatRouteDays(item);
  const weeks = formatRouteWeeks(item);
  if (!days && !weeks) return '-';
  if (days && weeks) return `${days} (${weeks})`;
  return days || weeks || '-';
}

// Analisis Komprehensif Tahapan Progres Alur Kerja untuk Tampilan Langsung di Tabel
function getStageSummary(item) {
  const st = item?.status || '';

  if (st === 'SE_SUBMITTED' || st === 'SUBMITTED') {
    return {
      title: 'Menunggu Input Admin Distributor',
      badgeClass: 'bg-amber-100 text-amber-900 border-amber-300',
      description: 'Admin Distributor belum mengisikan kode customer distributor.',
      isSpvAction: false,
    };
  }

  if (['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(st)) {
    return {
      title: 'Perlu Tindakan SPV (Review & Rute)',
      badgeClass: 'bg-blue-100 text-blue-900 border-blue-400 font-bold ring-2 ring-blue-300',
      description: 'Admin Distributor telah submit kode. Menunggu penentuan jadwal rute kunjungan oleh SPV Area.',
      isSpvAction: true,
    };
  }

  if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(st)) {
    return {
      title: 'Menunggu Persetujuan EDP Principal',
      badgeClass: 'bg-purple-100 text-purple-900 border-purple-300',
      description: 'Jadwal rute telah ditentukan oleh SPV Area. Menunggu verifikasi & persetujuan EDP Principal.',
      isSpvAction: false,
    };
  }

  if (['APPROVED_EDP', 'EDP_APPROVED'].includes(st)) {
    return {
      title: 'Selesai (Kode NOO Aktif)',
      badgeClass: 'bg-emerald-100 text-emerald-900 border-emerald-300 font-bold',
      description: 'Toko telah diverifikasi penuh dan terdaftar di database Principal.',
      isSpvAction: false,
    };
  }

  if (['ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(st)) {
    return {
      title: 'Ditolak Admin Distributor',
      badgeClass: 'bg-rose-100 text-rose-900 border-rose-300',
      description: item.reject_reason ? `Pengajuan ditolak Admin Distributor (Alasan: "${item.reject_reason}")` : 'Pengajuan ditolak oleh Admin Distributor.',
      isSpvAction: false,
    };
  }

  if (['SPV_REJECTED', 'REJECTED_SPV'].includes(st)) {
    return {
      title: 'Ditolak SPV Area',
      badgeClass: 'bg-rose-100 text-rose-900 border-rose-300',
      description: item.reject_reason ? `Pengajuan ditolak SPV Area (Alasan: "${item.reject_reason}")` : 'Pengajuan ditolak oleh Supervisor Area.',
      isSpvAction: false,
    };
  }

  if (['EDP_REJECTED', 'REJECTED_EDP'].includes(st)) {
    return {
      title: 'Ditolak / Dikembalikan EDP',
      badgeClass: 'bg-rose-100 text-rose-900 border-rose-300',
      description: item.reject_reason ? `Pengajuan dikembalikan EDP Principal (Alasan: "${item.reject_reason}")` : 'Pengajuan dikembalikan oleh EDP Principal.',
      isSpvAction: false,
    };
  }

  return {
    title: st.replace(/_/g, ' '),
    badgeClass: 'bg-slate-100 text-slate-800 border-slate-300',
    description: 'Proses verifikasi toko.',
    isSpvAction: false,
  };
}

function handleManageRoute(item) {
  emit('select-submission', item);
}

// Watch Prop Show untuk Memuat Data Saat Modal Terbuka
watch(
  () => props.show,
  (isShown) => {
    if (isShown) {
      fetchData(1);
    } else {
      selectedDetail.value = null;
    }
  }
);

function handleKeydown(e) {
  if (e.key === 'Escape' && props.show) {
    if (selectedDetail.value) {
      selectedDetail.value = null;
    } else {
      emit('close');
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  if (props.show) {
    fetchData(1);
  }
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  clearTimeout(searchDebounceTimer);
});
</script>

<template>
  <Teleport to="body">
    <!-- BACKDROP MODAL UTAMA -->
    <div
      v-if="show"
      class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-3 overflow-hidden"
      @click.self="emit('close')"
    >
      <!-- CONTAINER MODAL: Lebar & Tinggi Maksimal Agar Tabel Langsung Dominan Terlihat -->
      <div
        class="bg-white rounded-2xl w-[96vw] max-w-[1480px] h-[93vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden text-slate-700 my-auto"
      >
        <!-- HEADER MODAL (Ringkas & Efisien) -->
        <div class="px-5 py-2.5 bg-slate-900 text-white flex items-center justify-between shrink-0">
          <div class="flex items-center space-x-3 flex-wrap">
            <h2 class="text-base sm:text-lg font-bold tracking-tight text-white flex items-center gap-2">
              <span>Progress Tracking Submisi NOO</span>
            </h2>
            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/20 text-indigo-200 border border-indigo-400/30">
              Wilayah SPV Area
            </span>
            <span class="text-xs text-slate-400 hidden md:inline">
              • Pantau progres pengajuan, status, dan tindakan yang dibutuhkan.
            </span>
          </div>
          <button
            type="button"
            @click="emit('close')"
            class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition text-base font-bold"
            title="Tutup Modal (Esc)"
          >
            ✕
          </button>
        </div>

        <!-- TOP TOOLBAR: STATUS QUICK-FILTERS & KONTROL FILTER (Kompak, Hanya ~75px) -->
        <div class="px-4 py-2 bg-white border-b border-slate-200 shrink-0 space-y-2">
          <!-- BARIS 1: Quick Metric Status Pills -->
          <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 select-none">
            <!-- Total -->
            <button
              type="button"
              @click="selectQuickStatus('ALL')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'ALL' ? 'bg-slate-800 text-white border-slate-800 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
            >
              <span>Total Pengajuan</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'ALL' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-800'">
                {{ metrics.total }}
              </span>
            </button>

            <!-- Pending Admin -->
            <button
              type="button"
              @click="selectQuickStatus('PENDING_ADMIN')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'PENDING_ADMIN' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-amber-50/80 text-amber-800 border-amber-200 hover:bg-amber-100'"
            >
              <span>1. Pending Admin</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'PENDING_ADMIN' ? 'bg-white/20 text-white' : 'bg-amber-200 text-amber-900'">
                {{ metrics.pendingAdmin }}
              </span>
            </button>

            <!-- Pending SPV -->
            <button
              type="button"
              @click="selectQuickStatus('PENDING_SPV')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'PENDING_SPV' ? 'bg-blue-600 text-white border-blue-600 shadow-2xs' : 'bg-blue-50/80 text-blue-800 border-blue-200 hover:bg-blue-100'"
            >
              <span>2. Pending SPV Area</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'PENDING_SPV' ? 'bg-white/20 text-white' : 'bg-blue-200 text-blue-900'">
                {{ metrics.pendingSpv }}
              </span>
            </button>

            <!-- Pending EDP -->
            <button
              type="button"
              @click="selectQuickStatus('PENDING_EDP')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'PENDING_EDP' ? 'bg-purple-600 text-white border-purple-600 shadow-2xs' : 'bg-purple-50/80 text-purple-800 border-purple-200 hover:bg-purple-100'"
            >
              <span>3. Pending EDP</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'PENDING_EDP' ? 'bg-white/20 text-white' : 'bg-purple-200 text-purple-900'">
                {{ metrics.pendingEdp }}
              </span>
            </button>

            <!-- Selesai -->
            <button
              type="button"
              @click="selectQuickStatus('COMPLETED')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'COMPLETED' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-emerald-50/80 text-emerald-800 border-emerald-200 hover:bg-emerald-100'"
            >
              <span>Selesai / Approved</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'COMPLETED' ? 'bg-white/20 text-white' : 'bg-emerald-200 text-emerald-900'">
                {{ metrics.completed }}
              </span>
            </button>

            <!-- Ditolak -->
            <button
              type="button"
              @click="selectQuickStatus('REJECTED')"
              class="px-2.5 py-1 rounded-lg border text-xs font-semibold transition shrink-0 cursor-pointer flex items-center gap-1.5"
              :class="filterStatus === 'REJECTED' ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-rose-50/80 text-rose-800 border-rose-200 hover:bg-rose-100'"
            >
              <span>Ditolak / Rejected</span>
              <span class="px-1.5 py-0.2 rounded font-bold" :class="filterStatus === 'REJECTED' ? 'bg-white/20 text-white' : 'bg-rose-200 text-rose-900'">
                {{ metrics.rejected }}
              </span>
            </button>
          </div>

          <!-- BARIS 2: Kontrol Filter Kompak (Dropdown Tidak Menimpa Icon, Truncate Aman & Rapi) -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Filter Distributor -->
            <div class="flex items-center gap-1.5">
              <label class="text-[11.5px] font-semibold text-slate-500 whitespace-nowrap">Distributor:</label>
              <select
                v-model="filterBranch"
                @change="handleFilterChange"
                class="text-xs font-medium rounded-lg border border-slate-300 bg-white pl-2.5 pr-8 py-1.5 text-slate-800 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[170px] max-w-[250px] truncate"
              >
                <option value="ALL">Semua Distributor</option>
                <option v-for="b in myBranches" :key="b.branch_id" :value="b.branch_id">
                  {{ b.branch_id }} - {{ b.branch_name }}
                </option>
              </select>
            </div>

            <!-- Filter Bulan -->
            <div class="flex items-center gap-1.5">
              <label class="text-[11.5px] font-semibold text-slate-500 whitespace-nowrap">Bulan:</label>
              <select
                v-model="filterMonth"
                @change="handleFilterChange"
                class="text-xs font-medium rounded-lg border border-slate-300 bg-white pl-2.5 pr-8 py-1.5 text-slate-800 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[130px] max-w-[160px] truncate"
              >
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                  {{ m.label }}
                </option>
              </select>
            </div>

            <!-- Filter Tahun -->
            <div class="flex items-center gap-1.5">
              <label class="text-[11.5px] font-semibold text-slate-500 whitespace-nowrap">Tahun:</label>
              <select
                v-model="filterYear"
                @change="handleFilterChange"
                class="text-xs font-medium rounded-lg border border-slate-300 bg-white pl-2.5 pr-7 py-1.5 text-slate-800 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[85px]"
              >
                <option value="ALL">Semua</option>
                <option v-for="y in availableYears" :key="y" :value="String(y)">
                  {{ y }}
                </option>
              </select>
            </div>

            <!-- Filter Status -->
            <div class="flex items-center gap-1.5">
              <label class="text-[11.5px] font-semibold text-slate-500 whitespace-nowrap">Status:</label>
              <select
                v-model="filterStatus"
                @change="handleFilterChange"
                class="text-xs font-medium rounded-lg border border-slate-300 bg-white pl-2.5 pr-8 py-1.5 text-slate-800 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[190px] max-w-[260px] truncate"
              >
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">
                  {{ s.label }}
                </option>
              </select>
            </div>

            <!-- Pencarian Toko / Salesman -->
            <div class="relative flex-1 min-w-[180px] max-w-xs">
              <input
                type="text"
                v-model="searchQuery"
                @input="handleSearchInput"
                placeholder="Cari toko, custcode, salesman..."
                class="w-full text-xs font-medium rounded-lg border border-slate-300 bg-white pl-2.5 pr-6 py-1.5 text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
              />
              <button
                v-if="searchQuery"
                @click="searchQuery = ''; handleFilterChange();"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <!-- Reset Filter -->
            <button
              type="button"
              @click="resetFilter"
              class="px-2.5 py-1.5 text-xs font-semibold rounded-lg text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 transition cursor-pointer"
            >
              Reset
            </button>

            <!-- Total Temuan -->
            <span class="text-xs text-slate-500 ml-auto whitespace-nowrap hidden sm:inline">
              Ditemukan: <strong class="text-slate-900">{{ submissionsData.total }}</strong> toko
            </span>
          </div>
        </div>

        <!-- ERROR NOTIFICATION -->
        <div v-if="errorMessage" class="px-4 py-2 text-xs font-semibold bg-rose-50 text-rose-800 border-b border-rose-200 shrink-0">
          {{ errorMessage }}
        </div>

        <!-- TABEL DATA PROGRESS: BAGIAN UTAMA (HERO) YANG MEMENUHI RUANG -->
        <div class="flex-1 min-h-0 flex flex-col bg-slate-50/50 p-2 sm:p-3 overflow-hidden">
          <div class="flex-1 min-h-0 bg-white rounded-xl border border-slate-200 shadow-2xs flex flex-col overflow-hidden">

            <!-- SCROLLABLE TABLE CONTAINER DENGAN STICKY THEAD -->
            <div class="flex-1 overflow-y-auto overflow-x-auto">
              <table class="w-full text-left text-xs text-slate-700 min-w-[1060px]">
                <thead class="sticky top-0 bg-slate-100 text-slate-800 font-semibold text-xs border-b border-slate-200 uppercase tracking-wider select-none z-10 shadow-2xs">
                  <tr>
                    <th class="w-12 px-3 py-2.5 text-center">No</th>
                    <th class="w-60 px-3 py-2.5">Toko & Alamat</th>
                    <th class="w-52 px-3 py-2.5">Branch & Salesman</th>
                    <!-- Kolom Progres dengan Panah Kanan Asli: Admin → SPV → EDP -->
                    <th class="px-3 py-2.5">Progres & Riwayat Tahapan (Admin → SPV → EDP)</th>
                    <!-- Kolom Info Detail menggantikan Kode Pelanggan -->
                    <th class="w-64 px-3 py-2.5">Info Detail</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-slate-200">
                  <!-- LOADING STATE -->
                  <tr v-if="isLoading">
                    <td colspan="5" class="py-16 text-center text-slate-500">
                      <div class="inline-flex items-center gap-2 font-medium">
                        <svg class="animate-spin h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Memuat data progres pendaftaran toko...</span>
                      </div>
                    </td>
                  </tr>

                  <!-- EMPTY STATE -->
                  <tr v-else-if="!submissionsData.data || submissionsData.data.length === 0">
                    <td colspan="5" class="py-16 text-center text-slate-400">
                      Tidak ada pengajuan toko yang sesuai dengan filter saat ini.
                    </td>
                  </tr>

                  <!-- DATA ROWS -->
                  <tr
                    v-else
                    v-for="(item, idx) in submissionsData.data"
                    :key="item.request_id || item.id"
                    class="hover:bg-slate-50/80 transition"
                    :class="{ 'bg-blue-50/20': getStageSummary(item).isSpvAction }"
                  >
                    <!-- 1. Nomor Urut -->
                    <td class="px-3 py-3 text-center font-mono text-slate-500 align-top">
                      {{ (submissionsData.from || 1) + idx }}
                    </td>

                    <!-- 2. Toko & Alamat -->
                    <td class="px-3 py-3 align-top">
                      <div class="font-bold text-slate-900 text-[13.5px] leading-snug">
                        {{ item.nama_noo }}
                      </div>
                      <div class="text-[11.5px] text-slate-500 mt-0.5 line-clamp-2" :title="item.alamat_noo">
                        {{ item.alamat_noo || '-' }}
                      </div>
                      <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                          {{ item.type_outlet_code || 'Outlet' }}
                        </span>
                        <span v-if="item.sub_group_region || item.principal" class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                          {{ item.sub_group_region || item.principal }}
                        </span>
                      </div>
                    </td>

                    <!-- 3. Branch & Salesman -->
                    <td class="px-3 py-3 align-top">
                      <div class="font-semibold text-slate-900 leading-tight">
                        {{ item.branch_name || item.branch_id }}
                      </div>
                      <div class="text-[11.5px] text-slate-700 mt-0.5">
                        {{ item.salesman_name || '-' }}
                        <span class="text-slate-400 font-mono text-[10.5px]">({{ item.salesman_code }})</span>
                      </div>
                      <div class="text-[11px] text-slate-400 mt-1">
                        Diajukan: {{ formatDateOnly(item.submitted_at || item.created_at) }}
                      </div>
                    </td>

                    <!-- 4. Progres & Riwayat Tahapan (Klik untuk Melihat Riwayat Alur) -->
                    <td class="px-3 py-3 align-top">
                      <div
                        @click="selectedDetail = item"
                        class="p-2.5 -m-1 rounded-xl hover:bg-indigo-50/60 border border-transparent hover:border-indigo-200 transition cursor-pointer group space-y-2"
                        title="Klik untuk melihat rincian riwayat progres toko ini"
                      >
                        <!-- Badge Status Utama & Indikator Klik -->
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                          <span
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                            :class="getStageSummary(item).badgeClass"
                          >
                            {{ getStageSummary(item).title }}
                          </span>
                          <span class="text-[11px] font-medium text-indigo-600 group-hover:text-indigo-800 flex items-center gap-1 opacity-75 group-hover:opacity-100 transition">
                            <span>Lihat Detail Alur</span>
                            <span>&rarr;</span>
                          </span>
                        </div>

                        <!-- Deskripsi Tunggal yang Jelas & Informatif -->
                        <div
                          class="text-[12px] leading-relaxed font-normal"
                          :class="item.status.includes('REJECTED') ? 'text-rose-800 font-medium' : getStageSummary(item).isSpvAction ? 'text-blue-950 font-medium' : 'text-slate-600'"
                        >
                          {{ getStageSummary(item).description }}
                        </div>

                        <!-- Tombol Cepat Jika Butuh Tindakan SPV -->
                        <div v-if="getStageSummary(item).isSpvAction" class="pt-0.5">
                          <button
                            type="button"
                            @click.stop="handleManageRoute(item)"
                            class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition cursor-pointer"
                            title="Buka form pengisian rute & approve pengajuan ini"
                          >
                            <span>Kelola Rute & Approve Sekarang</span>
                            <span>&rarr;</span>
                          </button>
                        </div>
                      </div>
                    </td>

                    <!-- 5. Kolom Info Detail (Cust. Distributor, Cust. Principal, Rute Kunjungan) -->
                    <td class="px-3 py-3 align-top">
                      <div class="space-y-1.5 text-xs bg-slate-50/80 p-2.5 rounded-lg border border-slate-200/90">
                        <!-- Kode Customer Distributor -->
                        <div class="flex items-baseline justify-between gap-2">
                          <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap">Cust. Distributor:</span>
                          <span class="font-mono font-bold text-slate-900 truncate" :title="item.custcode_distributor || '---'">
                            {{ item.custcode_distributor || '-' }}
                          </span>
                        </div>

                        <!-- Kode Customer Principal -->
                        <div class="flex items-baseline justify-between gap-2">
                          <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap">Cust. Principal:</span>
                          <span class="font-mono font-bold truncate" :class="item.code_noo_principal ? 'text-emerald-700' : 'text-slate-400'" :title="item.code_noo_principal || '---'">
                            {{ item.code_noo_principal || '-' }}
                          </span>
                        </div>

                        <!-- Rute Kunjungan -->
                        <div class="flex items-baseline justify-between gap-2">
                          <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap">Rute Kunjungan:</span>
                          <span class="font-medium text-slate-800 truncate" :title="formatRouteSummary(item)">
                            {{ formatRouteSummary(item) }}
                          </span>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- PAGINATION BAR (Terkunci Di Bawah Tabel) -->
            <div
              v-if="submissionsData.total > 0"
              class="px-4 py-2 bg-slate-50 border-t border-slate-200 shrink-0 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-600 select-none"
            >
              <div>
                Menampilkan data ke <strong class="text-slate-900">{{ submissionsData.from || 0 }}</strong> - <strong class="text-slate-900">{{ submissionsData.to || 0 }}</strong> dari total <strong class="text-slate-900">{{ submissionsData.total }}</strong> toko
              </div>

              <div class="flex items-center space-x-1.5">
                <button
                  type="button"
                  :disabled="submissionsData.current_page <= 1 || isLoading"
                  @click="fetchData(submissionsData.current_page - 1)"
                  class="px-2.5 py-0.5 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs transition"
                >
                  Sebelumnya
                </button>

                <span class="px-2 py-0.5 text-xs font-semibold text-slate-700">
                  {{ submissionsData.current_page }} / {{ submissionsData.last_page || 1 }}
                </span>

                <button
                  type="button"
                  :disabled="submissionsData.current_page >= submissionsData.last_page || isLoading"
                  @click="fetchData(submissionsData.current_page + 1)"
                  class="px-2.5 py-0.5 rounded border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs transition"
                >
                  Berikutnya
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- FOOTER MODAL (Kompak) -->
        <div class="px-5 py-2 bg-slate-100 border-t border-slate-200 flex items-center justify-between shrink-0">
          <span class="text-xs text-slate-500 hidden sm:inline">
            Klik pada kolom Progres untuk melihat rincian riwayat audit trail pendaftaran toko.
          </span>
          <button
            type="button"
            @click="emit('close')"
            class="px-4 py-1 text-xs font-semibold rounded-lg bg-white hover:bg-slate-200 text-slate-700 border border-slate-300 shadow-2xs transition cursor-pointer ml-auto"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- SUB-MODAL DETAIL TIMELINE ALUR KERJA TOKO -->
    <div
      v-if="selectedDetail"
      class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99995] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
      @click.self="selectedDetail = null"
    >
      <div class="bg-white rounded-2xl max-w-2xl w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <!-- Header Sub-modal -->
        <div class="flex items-start justify-between border-b border-slate-200 pb-3">
          <div>
            <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Rincian Riwayat Progres</span>
            <h3 class="text-lg font-bold text-slate-900 mt-0.5">{{ selectedDetail.nama_noo }}</h3>
            <p class="text-xs text-slate-500">
              {{ selectedDetail.branch_name }} | Salesman: {{ selectedDetail.salesman_name }} ({{ selectedDetail.salesman_code }})
            </p>
          </div>
          <button
            type="button"
            @click="selectedDetail = null"
            class="text-slate-400 hover:text-slate-700 text-lg font-bold p-1 rounded-lg"
          >
            ✕
          </button>
        </div>

        <!-- Informasi Alamat & Lokasi -->
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs space-y-1.5">
          <div>
            <span class="font-semibold text-slate-700">Alamat Lengkap:</span>
            <span class="text-slate-600 ml-1">{{ selectedDetail.alamat_noo || '-' }}</span>
          </div>
          <div class="flex items-center gap-4 text-slate-600 flex-wrap">
            <div>
              <span class="font-semibold text-slate-700">Pemilik Outlet:</span>
              <span class="ml-1">{{ selectedDetail.nama_pemilik_outlet || '-' }}</span>
            </div>
            <div>
              <span class="font-semibold text-slate-700">No. Kontak:</span>
              <span class="ml-1">{{ selectedDetail.no_hp_noo || selectedDetail.no_hp || '-' }}</span>
            </div>
            <div>
              <span class="font-semibold text-slate-700">Tipe Outlet:</span>
              <span class="ml-1">{{ selectedDetail.type_outlet_code }} ({{ selectedDetail.type_outlet_desc || 'Standar' }})</span>
            </div>
          </div>
        </div>

        <!-- VERTICAL TIMELINE AUDIT TRAIL ALUR KERJA -->
        <div class="space-y-3">
          <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">Tahapan Proses Pendaftaran</h4>

          <div class="space-y-3 relative before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 pl-7">
            <!-- 1. Pengajuan Sales Executive -->
            <div class="relative">
              <div class="absolute -left-7 top-0.5 w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold ring-4 ring-white">
                ✓
              </div>
              <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-1 shadow-2xs">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900">1. Pengajuan Awal oleh Salesman</span>
                  <span class="text-[10.5px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">SELESAI</span>
                </div>
                <div class="text-xs text-slate-600 space-y-0.5">
                  <div>Salesman: <strong>{{ selectedDetail.salesman_name }}</strong> ({{ selectedDetail.salesman_code }})</div>
                  <div>Waktu Pengajuan: {{ formatDateTime(selectedDetail.submitted_at || selectedDetail.created_at) }}</div>
                </div>
              </div>
            </div>

            <!-- 2. Verifikasi Admin Distributor -->
            <div class="relative">
              <div
                class="absolute -left-7 top-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white"
                :class="selectedDetail.pushed_to_spv_at ? 'bg-emerald-600 text-white' : selectedDetail.status.includes('ADMIN_REJECTED') ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white animate-pulse'"
              >
                {{ selectedDetail.pushed_to_spv_at ? '✓' : selectedDetail.status.includes('ADMIN_REJECTED') ? '✕' : '2' }}
              </div>
              <div
                class="p-3 rounded-xl border space-y-1 shadow-2xs"
                :class="selectedDetail.status.includes('ADMIN_REJECTED') ? 'bg-rose-50/70 border-rose-200' : 'bg-white border-slate-200'"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900">2. Verifikasi Admin Distributor</span>
                  <span
                    class="text-[10.5px] font-semibold px-2 py-0.5 rounded border"
                    :class="selectedDetail.pushed_to_spv_at ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : selectedDetail.status.includes('ADMIN_REJECTED') ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-amber-50 text-amber-800 border-amber-300'"
                  >
                    {{ selectedDetail.pushed_to_spv_at ? 'SELESAI' : selectedDetail.status.includes('ADMIN_REJECTED') ? 'DITOLAK ADMIN' : 'MENUNGGU PROSES' }}
                  </span>
                </div>
                <div class="text-xs text-slate-600 space-y-0.5">
                  <div v-if="selectedDetail.custcode_distributor">
                    Kode Pelanggan Distributor: <strong class="font-mono text-indigo-700">{{ selectedDetail.custcode_distributor }}</strong>
                  </div>
                  <div v-else class="text-amber-800 italic">
                    Admin Distributor belum mengisikan kode pelanggan distributor.
                  </div>
                  <div v-if="selectedDetail.approved_by_admin">
                    Petugas Admin: {{ selectedDetail.approved_by_admin }}
                  </div>
                  <div v-if="selectedDetail.pushed_to_spv_at">
                    Waktu Diteruskan ke SPV: {{ formatDateTime(selectedDetail.pushed_to_spv_at) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. Persetujuan SPV Area -->
            <div class="relative">
              <div
                class="absolute -left-7 top-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white"
                :class="['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'bg-emerald-600 text-white' : selectedDetail.status.includes('SPV_REJECTED') ? 'bg-rose-600 text-white' : ['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(selectedDetail.status) ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600'"
              >
                {{ ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? '✓' : selectedDetail.status.includes('SPV_REJECTED') ? '✕' : '3' }}
              </div>
              <div
                class="p-3 rounded-xl border space-y-1 shadow-2xs"
                :class="selectedDetail.status.includes('SPV_REJECTED') ? 'bg-rose-50/70 border-rose-200' : 'bg-white border-slate-200'"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900">3. Persetujuan & Penjadwalan Rute SPV Area</span>
                  <span
                    class="text-[10.5px] font-semibold px-2 py-0.5 rounded border"
                    :class="['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : selectedDetail.status.includes('SPV_REJECTED') ? 'bg-rose-100 text-rose-800 border-rose-300' : ['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(selectedDetail.status) ? 'bg-blue-50 text-blue-800 border-blue-300' : 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'DISETUJUI SPV' : selectedDetail.status.includes('SPV_REJECTED') ? 'DITOLAK SPV' : ['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(selectedDetail.status) ? 'PERLU REVIEW SPV' : 'MENUNGGU ADMIN' }}
                  </span>
                </div>
                <div class="text-xs text-slate-600 space-y-0.5">
                  <div v-if="selectedDetail.h1 || selectedDetail.m1">
                    Jadwal Rute Kunjungan:
                    <span class="font-semibold text-slate-800">{{ formatRouteSummary(selectedDetail) }}</span>
                  </div>
                  <div v-else class="text-slate-500 italic">
                    Jadwal rute kunjungan belum ditentukan oleh SPV Area.
                  </div>
                  <div v-if="selectedDetail.approved_by_spv">
                    Supervisor Penyetuju: {{ selectedDetail.approved_by_spv }}
                  </div>
                  <div v-if="selectedDetail.spv_submit_at || selectedDetail.pushed_to_edp_at">
                    Waktu Persetujuan SPV: {{ formatDateTime(selectedDetail.spv_submit_at || selectedDetail.pushed_to_edp_at) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. Verifikasi & Approval EDP Principal -->
            <div class="relative">
              <div
                class="absolute -left-7 top-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white"
                :class="['APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'bg-emerald-600 text-white' : selectedDetail.status.includes('EDP_REJECTED') ? 'bg-rose-600 text-white' : ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(selectedDetail.status) ? 'bg-purple-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600'"
              >
                {{ ['APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? '✓' : selectedDetail.status.includes('EDP_REJECTED') ? '✕' : '4' }}
              </div>
              <div
                class="p-3 rounded-xl border space-y-1 shadow-2xs"
                :class="selectedDetail.status.includes('EDP_REJECTED') ? 'bg-rose-50/70 border-rose-200' : 'bg-white border-slate-200'"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900">4. Penerbitan Kode Outlet EDP Principal</span>
                  <span
                    class="text-[10.5px] font-semibold px-2 py-0.5 rounded border"
                    :class="['APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : selectedDetail.status.includes('EDP_REJECTED') ? 'bg-rose-100 text-rose-800 border-rose-300' : ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(selectedDetail.status) ? 'bg-purple-50 text-purple-800 border-purple-300' : 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ ['APPROVED_EDP', 'EDP_APPROVED'].includes(selectedDetail.status) ? 'SELESAI (TERBIT)' : selectedDetail.status.includes('EDP_REJECTED') ? 'DITOLAK EDP' : ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(selectedDetail.status) ? 'MENUNGGU EDP' : 'MENUNGGU SPV' }}
                  </span>
                </div>
                <div class="text-xs text-slate-600 space-y-0.5">
                  <div v-if="selectedDetail.code_noo_principal">
                    Kode Pelanggan Principal: <strong class="font-mono text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">{{ selectedDetail.code_noo_principal }}</strong>
                  </div>
                  <div v-else class="text-slate-500 italic">
                    Kode pelanggan principal belum diterbitkan.
                  </div>
                  <div v-if="selectedDetail.approved_by_edp || selectedDetail.injected_by">
                    Petugas EDP: {{ selectedDetail.approved_by_edp || selectedDetail.injected_by }}
                  </div>
                  <div v-if="selectedDetail.edp_reviewed_at || selectedDetail.injected_at">
                    Waktu Persetujuan EDP: {{ formatDateTime(selectedDetail.edp_reviewed_at || selectedDetail.injected_at) }}
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Alasan Penolakan / Catatan Jika Ada -->
        <div v-if="selectedDetail.reject_reason || selectedDetail.admin_notes || selectedDetail.spv_notes || selectedDetail.edp_notes" class="space-y-2 pt-2 border-t border-slate-200">
          <div v-if="selectedDetail.reject_reason" class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-900 space-y-1">
            <div class="font-bold uppercase tracking-wider text-rose-800">Alasan Penolakan / Pengembalian:</div>
            <p class="whitespace-pre-line font-medium">{{ selectedDetail.reject_reason }}</p>
          </div>

          <div v-if="selectedDetail.spv_notes" class="p-3 bg-blue-50 rounded-xl border border-blue-200 text-xs text-blue-900 space-y-1">
            <div class="font-bold uppercase tracking-wider text-blue-800">Catatan Supervisor Area:</div>
            <p class="whitespace-pre-line font-medium">{{ selectedDetail.spv_notes }}</p>
          </div>

          <div v-if="selectedDetail.admin_notes" class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 space-y-1">
            <div class="font-bold uppercase tracking-wider text-slate-800">Catatan Admin Distributor:</div>
            <p class="whitespace-pre-line font-medium">{{ selectedDetail.admin_notes }}</p>
          </div>
        </div>

        <!-- Tombol Tutup & Tombol Kelola Rute Jika Ada Tindakan SPV -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
          <button
            v-if="['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(selectedDetail.status)"
            type="button"
            @click="handleManageRoute(selectedDetail)"
            class="px-4 py-1.5 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition cursor-pointer"
          >
            Kelola Rute Toko Ini &rarr;
          </button>
          <div v-else></div>

          <button
            type="button"
            @click="selectedDetail = null"
            class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
          >
            Tutup Rincian
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
