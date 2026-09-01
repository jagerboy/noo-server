<script setup lang="js">
/**
 * Executive Dashboard NOO+ v2.0 dengan Realtime Metrics, Interactive Filter, Visualisasi Grafik & Summary Logs (Vue 3).
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const page = usePage();

const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({}),
  },
  charts: {
    type: Object,
    default: () => ({}),
  },
  recentLogs: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  filterOptions: {
    type: Object,
    default: () => ({}),
  },
  userRole: {
    type: String,
    default: 'EDP_REGION',
  },
});

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

const selectedRegion = ref(props.filters?.region_code || '');
const selectedPrincipal = ref(props.filters?.principal || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const initMonths = () => {
  if (props.filters?.months) {
    return String(props.filters.months).split(',').filter(Boolean);
  }
  if (props.filters?.month) {
    return [String(props.filters.month)];
  }
  return [];
};
const selectedMonths = ref(initMonths());
const isMonthDropdownOpen = ref(false);
const monthDropdownRef = ref(null);

const selectedYear = ref(props.filters?.year || '');

const isFiltering = ref(false);

const chart1Year = ref(props.filters?.chart1_year || props.filters?.year || '');
const chart2Year = ref(props.filters?.chart2_year || props.filters?.year || '');
const chart3Year = ref(props.filters?.chart3_year || props.filters?.year || '');
const chart4Year = ref(props.filters?.chart4_year || props.filters?.year || '');

function handleClickOutsideMonth(event) {
  if (monthDropdownRef.value && !monthDropdownRef.value.contains(event.target)) {
    isMonthDropdownOpen.value = false;
  }
}

const displayUserRole = computed(() => {
  const role = props.userRole || 'EDP_REGION';
  const userObj = page.props.auth?.user || {};
  const reg = (userObj.region_code || props.filters?.region_code || '').toUpperCase();
  const username = (userObj.username || '').toLowerCase();

  if (role === 'EDP_REGION') return 'EDP Regional';
  if (role === 'SUPERADMIN') return 'Superadmin';
  if (role === 'ADMIN_PRINCIPAL') {
    if (reg.includes('ASWSUM') || username.includes('aswsum')) return 'Admin Principal ASW Sumatera';
    if (reg.includes('ASWJWA') || username.includes('aswjwa')) return 'Admin Principal ASW Jawa';
    if (reg.includes('ASWPUL') || username.includes('aswpul')) return 'Admin Principal ASW Pulau';
    if (reg.includes('INAJWA') || username.includes('inajwa')) return 'Admin Principal INA Jawa';
    if (reg.includes('INAPUL') || username.includes('inapul')) return 'Admin Principal INA Pulau';
    if (reg.includes('INASUM') || username.includes('inasum')) return 'Admin Principal INA Sumatera';
    return 'Admin Principal';
  }
  return role;
});

// Intersection Observer state for charts scroll trigger
const chartSectionRef = ref(null);
const isChartVisible = ref(false);

// Animation state for numbers
const isAnimated = ref(false);
const displayMetrics = ref({
  total_submitted_se: 0,
  pending_admin: 0,
  approved_admin: 0,
  rejected_admin: 0,
  pending_spv: 0,
  approved_spv: 0,
  rejected_spv: 0,
  pending_principal: 0,
  approved_principal: 0,
  rejected_principal: 0,
});

function animateNumbers() {
  isAnimated.value = false;
  setTimeout(() => {
    isAnimated.value = true;
  }, 50);

  const duration = 800; // ms
  const steps = 30;
  const stepTime = duration / steps;
  let currentStep = 0;

  const targetMetrics = {
    total_submitted_se: Number(props.metrics?.total_submitted_se || 0),
    pending_admin: Number(props.metrics?.pending_admin || 0),
    approved_admin: Number(props.metrics?.approved_admin || 0),
    rejected_admin: Number(props.metrics?.rejected_admin || 0),
    pending_spv: Number(props.metrics?.pending_spv || 0),
    approved_spv: Number(props.metrics?.approved_spv || 0),
    rejected_spv: Number(props.metrics?.rejected_spv || 0),
    pending_principal: Number(props.metrics?.pending_principal || 0),
    approved_principal: Number(props.metrics?.approved_principal || 0),
    rejected_principal: Number(props.metrics?.rejected_principal || 0),
  };

  const timer = setInterval(() => {
    currentStep++;
    const progress = Math.min(currentStep / steps, 1);
    
    Object.keys(targetMetrics).forEach((key) => {
      displayMetrics.value[key] = Math.round(targetMetrics[key] * progress);
    });

    if (currentStep >= steps) {
      clearInterval(timer);
    }
  }, stepTime);
}

onMounted(() => {
  animateNumbers();
  document.addEventListener('click', handleClickOutsideMonth);

  // Scroll Observer for re-triggering animations whenever scrolled into/out of view
  if ('IntersectionObserver' in window && chartSectionRef.value) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          isChartVisible.value = entry.isIntersecting;
        });
      },
      { threshold: 0.15 }
    );
    observer.observe(chartSectionRef.value);
  } else {
    isChartVisible.value = true;
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutsideMonth);
});

watch(
  () => props.metrics,
  () => {
    animateNumbers();
  },
  { deep: true }
);

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => {
    if (typeof r === 'object' && r !== null) {
      const code = r.region_code || r.value;
      const name = r.region_name;
      return {
        value: code,
        label: name ? `${code} - ${name}` : String(code),
      };
    }
    return { value: r, label: String(r) };
  });
});

const entityOptions = computed(() => {
  const allEntities = props.filterOptions?.entities || [];
  let list = allEntities;
  if (selectedRegion.value) {
    const filtered = list.filter((e) => e.region_code === selectedRegion.value || (e.region_code && selectedRegion.value.startsWith(e.region_code)));
    if (filtered.length > 0) {
      list = filtered;
    }
  }
  return list.map((e) => {
    if (typeof e === 'object' && e !== null) {
      const code = e.entity_code_principal || e.value || '';
      const name = e.entity_name_principal || e.label || '';
      return {
        value: code,
        label: name ? `${code} - ${name}` : String(code),
        region_code: e.region_code,
      };
    }
    return { value: e, label: String(e) };
  });
});

const branchOptions = computed(() => {
  const allBranches = props.filterOptions?.branches || [];
  let list = allBranches;
  if (selectedRegion.value) {
    const filtered = list.filter((b) => b.region_code === selectedRegion.value || (b.region_code && selectedRegion.value.startsWith(b.region_code)));
    if (filtered.length > 0) {
      list = filtered;
    }
  }
  if (selectedPrincipal.value) {
    const filteredByPrinc = list.filter((b) => b.entity_code_principal === selectedPrincipal.value || b.principal_code === selectedPrincipal.value);
    if (filteredByPrinc.length > 0) {
      list = filteredByPrinc;
    }
  }
  return list.map((b) => {
    if (typeof b === 'object' && b !== null) {
      return {
        value: b.branch_id,
        label: `${b.branch_id} - ${b.branch_name}`,
        region_code: b.region_code,
        entity_code_principal: b.entity_code_principal,
      };
    }
    return { value: b, label: String(b) };
  });
});

watch(selectedRegion, () => {
  if (selectedPrincipal.value) {
    const valid = entityOptions.value.some((e) => e.value === selectedPrincipal.value);
    if (!valid) selectedPrincipal.value = '';
  }
  if (selectedBranch.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) selectedBranch.value = '';
  }
});

watch(selectedPrincipal, () => {
  if (selectedBranch.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) selectedBranch.value = '';
  }
});

const monthOptions = [
  { value: '1', label: 'Januari', short: 'Jan' },
  { value: '2', label: 'Februari', short: 'Feb' },
  { value: '3', label: 'Maret', short: 'Mar' },
  { value: '4', label: 'April', short: 'Apr' },
  { value: '5', label: 'Mei', short: 'Mei' },
  { value: '6', label: 'Juni', short: 'Jun' },
  { value: '7', label: 'Juli', short: 'Jul' },
  { value: '8', label: 'Agustus', short: 'Agu' },
  { value: '9', label: 'September', short: 'Sep' },
  { value: '10', label: 'Oktober', short: 'Okt' },
  { value: '11', label: 'November', short: 'Nov' },
  { value: '12', label: 'Desember', short: 'Des' },
];

const selectedMonthsLabel = computed(() => {
  const len = selectedMonths.value.length;
  if (len === 0) return '-- Semua Bulan --';
  if (len === 12) return 'Semua Bulan (12 Bulan)';
  if (len === 1) {
    const found = monthOptions.find((m) => m.value === selectedMonths.value[0]);
    return found ? found.label : '1 Bulan';
  }
  const shorts = monthOptions
    .filter((m) => selectedMonths.value.includes(m.value))
    .map((m) => m.short)
    .join(', ');
  return `${len} Bulan (${shorts})`;
});

const yearOptions = computed(() => {
  const years = props.filterOptions?.years || [new Date().getFullYear()];
  return years.map((y) => ({
    value: String(y),
    label: String(y),
  }));
});

function applyFilters() {
  isFiltering.value = true;
  const params = {};
  if (selectedRegion.value) params.region_code = selectedRegion.value;
  if (selectedPrincipal.value) params.principal = selectedPrincipal.value;
  if (selectedBranch.value) params.branch_id = selectedBranch.value;
  if (selectedMonths.value && selectedMonths.value.length > 0) {
    params.months = selectedMonths.value.join(',');
  }
  if (selectedYear.value) params.year = selectedYear.value;
  if (chart1Year.value) params.chart1_year = chart1Year.value;
  if (chart2Year.value) params.chart2_year = chart2Year.value;
  if (chart3Year.value) params.chart3_year = chart3Year.value;
  if (chart4Year.value) params.chart4_year = chart4Year.value;

  router.get(
    route('edp.dashboard'),
    params,
    {
      preserveState: true,
      replace: true,
      preserveScroll: true,
      onFinish: () => {
        isFiltering.value = false;
        animateNumbers();
      },
    }
  );
}

function resetFilters() {
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
  selectedMonths.value = [];
  selectedYear.value = '';
  chart1Year.value = '';
  chart2Year.value = '';
  chart3Year.value = '';
  chart4Year.value = '';
  applyFilters();
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  try {
    const d = new Date(dateStr);
    return d.toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return dateStr;
  }
}

/**
 * Format string Action Aktivitas tanpa underscore (Title Case)
 */
function formatActionLabel(action) {
  if (!action) return 'Update Data';
  switch (action) {
    case 'SE_SUBMITTED':
    case 'SUBMITTED':
      return 'Submisi SE';
    case 'PUSHED_TO_SPV':
    case 'ADMIN_APPROVED':
      return 'Diteruskan ke SPV';
    case 'APPROVED_SPV':
    case 'APPROVED_BY_SPV':
    case 'PUSHED_TO_EDP':
      return 'Approved SPV';
    case 'APPROVED_EDP':
    case 'EDP_APPROVED':
      return 'Approved EDP';
    case 'ADMIN_REJECTED':
    case 'REJECTED_ADMIN':
      return 'Ditolak Admin';
    case 'SPV_REJECTED':
    case 'REJECTED_SPV':
      return 'Ditolak SPV';
    case 'EDP_REJECTED':
    case 'REJECTED_EDP':
      return 'Ditolak EDP';
    case 'RESET_ADMIN_INPUT':
      return 'Reset Input Admin';
    case 'RESET_SPV_INPUT':
      return 'Reset Input SPV';
    case 'RESET_EDP_APPROVAL':
      return 'Reset Approval EDP';
    default:
      return String(action).replace(/_/g, ' ');
  }
}
</script>

<template>
  <EdpLayout>
    <Head title="Dashboard NOO+ - Portal Principal" />

    <div class="space-y-6">
      
      <!-- Page Header Card -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black text-[#111827] tracking-tight flex items-center gap-3">
            <span>Executive Dashboard Overview NOO+</span>
          </h1>
          <p class="text-xs sm:text-sm text-[#6B7280] mt-1.5 leading-relaxed">
            Ringkasan Metrik Pengajuan Outlet Baru (NOO), Status Persetujuan Bertingkat, Analisis Grafik & Log Aktivitas.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-xs font-bold px-3.5 py-1.5 rounded-full bg-purple-100 text-[#542B85] border border-purple-200 shadow-2xs">
            Peran Login: {{ displayUserRole }}
          </span>
        </div>
      </div>

      <!-- FILTER BAR DASHBOARD -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-xs font-bold text-[#4B5563] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-[#2563EB]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>FILTER DATA WILAYAH, PRINCIPAL, CABANG & TAHUN</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Kode atau Nama Region..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">ENTITY / PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedPrincipal"
              :options="entityOptions"
              placeholder="-- Semua Principal --"
              searchPlaceholder="Ketik Kode atau Nama Principal..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Ketik ID atau Nama Cabang..."
              @change="applyFilters"
            />
          </div>

          <!-- BULAN PENGAJUAN (MULTISELECT CHECKBOX DROPDOWN) -->
          <div class="relative" ref="monthDropdownRef">
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">BULAN PENGAJUAN</label>

            <button
              type="button"
              @click="isMonthDropdownOpen = !isMonthDropdownOpen"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white font-semibold text-slate-800 flex items-center justify-between shadow-2xs hover:border-blue-500 focus:outline-none transition cursor-pointer"
            >
              <span class="truncate pr-2">{{ selectedMonthsLabel }}</span>
              <div class="flex items-center gap-1.5 shrink-0">
                <span
                  v-if="selectedMonths.length > 0 && selectedMonths.length < 12"
                  class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center"
                >
                  {{ selectedMonths.length }}
                </span>
                <span class="text-[10px] text-slate-500">▼</span>
              </div>
            </button>

            <!-- DROPDOWN OVERLAY WITH CHECKBOXES -->
            <div
              v-if="isMonthDropdownOpen"
              class="absolute left-0 top-full mt-1.5 w-72 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 p-3 space-y-2 text-xs"
            >
              <div class="grid grid-cols-2 gap-1.5 max-h-48 overflow-y-auto pt-1">
                <label
                  v-for="m in monthOptions"
                  :key="m.value"
                  class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer select-none text-slate-700"
                >
                  <input
                    type="checkbox"
                    :value="m.value"
                    v-model="selectedMonths"
                    class="w-3.5 h-3.5 text-blue-600 rounded border-slate-300 focus:ring-blue-500"
                  />
                  <span class="font-medium text-[11.5px]">{{ m.label }}</span>
                </label>
              </div>

              <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-[11px]">
                <span class="text-slate-500 font-semibold">{{ selectedMonths.length }} bulan terpilih</span>
                <button
                  type="button"
                  @click="isMonthDropdownOpen = false; applyFilters();"
                  class="text-blue-600 font-bold hover:underline cursor-pointer"
                >
                  Selesai
                </button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">TAHUN PENGAJUAN</label>
            <select
              v-model="selectedYear"
              @change="applyFilters"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition cursor-pointer"
            >
              <option value="">-- Semua Tahun --</option>
              <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <button
            @click="resetFilters"
            class="px-4 py-2 text-xs font-semibold text-[#4B5563] bg-[#F3F4F6] hover:bg-[#E5E7EB] rounded-lg transition cursor-pointer"
          >
            Reset Filter
          </button>
          <button
            @click="applyFilters"
            class="px-5 py-2 text-xs font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] rounded-lg transition shadow-xs cursor-pointer"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- METRICS GRID (SUB-SECTIONS LEGA DENGAN INNER PADDING & ROUNDED CARD CORNERS) -->
      <div class="space-y-4">
        
        <!-- SECTION 1: TOTAL SUBMISI SE -->
        <div class="bg-white p-5 rounded-xl border border-[#CBD5E1] shadow-xs hover:border-blue-300 transition">
          <div class="flex items-center justify-between">
            <div>
              <span class="text-xs font-bold text-[#64748B] uppercase tracking-wider">1. Total NOO Disubmit SE</span>
              <h2 class="text-3xl sm:text-4xl font-black text-[#1E293B] mt-1 transition-all duration-300">
                {{ displayMetrics.total_submitted_se }}
              </h2>
              <p class="text-xs text-[#64748B] mt-0.5">Total pengajuan outlet baru dari aplikasi mobile sales</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-blue-50 text-blue-600 border border-blue-100 shadow-2xs">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
          </div>
        </div>

        <!-- SECTION 2: PER TAHAP VERIFIKASI -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          
          <!-- TAHAP 1: ADMIN DISTRIBUTOR -->
          <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-blue-300 transition flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <span class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <span>Admin Distributor</span>
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Tahap 1</span>
            </div>
            
            <div class="grid grid-cols-3 gap-2.5 mt-3 pt-1">
              <div class="p-2.5 rounded-lg bg-amber-50/80 border border-amber-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-amber-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wide leading-tight">Belum Diproses</span>
                </div>
                <span class="text-xl font-black text-amber-950 block mt-1">{{ displayMetrics.pending_admin }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-emerald-50/80 border border-emerald-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-emerald-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wide leading-tight">Approved</span>
                </div>
                <span class="text-xl font-black text-emerald-950 block mt-1">{{ displayMetrics.approved_admin }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-rose-50/80 border border-rose-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-rose-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wide leading-tight">Rejected</span>
                </div>
                <span class="text-xl font-black text-rose-950 block mt-1">{{ displayMetrics.rejected_admin }}</span>
              </div>
            </div>
          </div>

          <!-- TAHAP 2: SPV AREA -->
          <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <span class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <span>SPV Area</span>
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Tahap 2</span>
            </div>
            
            <div class="grid grid-cols-3 gap-2.5 mt-3 pt-1">
              <div class="p-2.5 rounded-lg bg-amber-50/80 border border-amber-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-amber-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wide leading-tight">Belum Diproses</span>
                </div>
                <span class="text-xl font-black text-amber-950 block mt-1">{{ displayMetrics.pending_spv }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-teal-50/80 border border-teal-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-teal-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-teal-800 uppercase tracking-wide leading-tight">Approved</span>
                </div>
                <span class="text-xl font-black text-teal-950 block mt-1">{{ displayMetrics.approved_spv }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-rose-50/80 border border-rose-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-rose-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wide leading-tight">Rejected</span>
                </div>
                <span class="text-xl font-black text-rose-950 block mt-1">{{ displayMetrics.rejected_spv }}</span>
              </div>
            </div>
          </div>

          <!-- TAHAP 3: EDP PRINCIPAL -->
          <div class="bg-white p-5 rounded-xl border-2 border-emerald-500 shadow-xs bg-emerald-50/10 hover:border-emerald-600 transition flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-emerald-100 pb-2.5">
              <span class="text-xs font-bold text-[#065F46] uppercase tracking-wider flex items-center gap-1.5">
                <span>Principal (EDP)</span>
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Final Verification</span>
            </div>
            
            <div class="grid grid-cols-3 gap-2.5 mt-3 pt-1">
              <div class="p-2.5 rounded-lg bg-amber-50/80 border border-amber-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-amber-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wide leading-tight">Belum Diproses</span>
                </div>
                <span class="text-xl font-black text-amber-950 block mt-1">{{ displayMetrics.pending_principal }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-emerald-600 text-white text-center flex flex-col justify-between h-full min-h-[84px] shadow-xs transition hover:bg-emerald-700">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold uppercase tracking-wide opacity-90 leading-tight">Approved</span>
                </div>
                <span class="text-xl font-black block mt-1">{{ displayMetrics.approved_principal }}</span>
              </div>
              <div class="p-2.5 rounded-lg bg-rose-50/80 border border-rose-200/60 text-center flex flex-col justify-between h-full min-h-[84px] transition hover:bg-rose-100/80">
                <div class="min-h-[30px] flex items-center justify-center">
                  <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wide leading-tight">Rejected</span>
                </div>
                <span class="text-xl font-black text-rose-950 block mt-1">{{ displayMetrics.rejected_principal }}</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- GRAFIK VISUALISASI CHART (FILTER TAHUN DI MASING-MASING CHART) -->
      <div ref="chartSectionRef" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- CHART 1: 🍩 SVG DONUT CHART DENGAN FILTER TAHUN MANDIRI -->
        <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4 hover:shadow-md transition">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
              <h3 class="text-sm font-bold text-[#111827]">Perbandingan Status Submisi NOO</h3>
              <p class="text-[11px] text-slate-500">Distribusi Total Submit SE vs Approved vs Rejected Principal</p>
            </div>
            <!-- FILTER TAHUN CHART 1 -->
            <div class="flex items-center gap-1.5 shrink-0">
              <label class="text-[10.5px] font-bold text-slate-500 uppercase">Tahun:</label>
              <select
                v-model="chart1Year"
                @change="applyFilters"
                class="pl-2.5 pr-8 py-1 text-xs border border-slate-200 rounded-lg bg-slate-50 font-bold text-slate-700 hover:bg-white transition cursor-pointer min-w-[125px]"
              >
                <option value="">Semua Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
            <!-- Donut SVG Visualizer dengan Hover Scale -->
            <div class="relative w-40 h-40 flex items-center justify-center group cursor-pointer">
              <svg class="w-full h-full transform -rotate-90 group-hover:scale-105 transition-all duration-300" viewBox="0 0 36 36">
                <path
                  class="text-slate-100"
                  stroke-width="3.8"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <!-- Slice 1: Approved (Emerald) -->
                <path
                  class="text-emerald-500 hover:text-emerald-600 transition-all duration-1000 ease-out"
                  :stroke-dasharray="isChartVisible ? `${Math.min(100, ((charts?.comparison?.approved_principal || 0) / (charts?.comparison?.total_submitted_se || 1)) * 100)}, 100` : '0, 100'"
                  stroke-width="4.5"
                  stroke-linecap="round"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <!-- Slice 2: Rejected (Rose) -->
                <path
                  class="text-rose-500 hover:text-rose-600 transition-all duration-1000 ease-out"
                  :stroke-dasharray="isChartVisible ? `${Math.min(100, ((charts?.comparison?.rejected_principal || 0) / (charts?.comparison?.total_submitted_se || 1)) * 100)}, 100` : '0, 100'"
                  :stroke-dashoffset="-(Math.min(100, ((charts?.comparison?.approved_principal || 0) / (charts?.comparison?.total_submitted_se || 1)) * 100))"
                  stroke-width="4.5"
                  stroke-linecap="round"
                  stroke="currentColor"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>

              <!-- Central Text -->
              <div class="absolute text-center group-hover:scale-110 transition-transform">
                <span class="text-2xl font-black text-slate-800 block">{{ charts?.comparison?.total_submitted_se || 0 }}</span>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total NOO</span>
              </div>
            </div>

            <!-- Legend List -->
            <div class="space-y-2.5 text-xs w-full max-w-[210px]">
              <div class="flex items-center justify-between p-2.5 rounded-lg bg-blue-50/80 border border-blue-200 hover:bg-blue-100/80 transition cursor-pointer">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full bg-blue-500 shadow-2xs"></span>
                  <span class="font-semibold text-slate-700">Submit SE</span>
                </div>
                <div class="text-right">
                  <span class="font-bold text-blue-700 block">{{ charts?.comparison?.total_submitted_se || 0 }}</span>
                  <span class="text-[9.5px] text-blue-600 font-medium">100% Volume</span>
                </div>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-lg bg-emerald-50/80 border border-emerald-200 hover:bg-emerald-100/80 transition cursor-pointer">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-2xs"></span>
                  <span class="font-semibold text-slate-700">Approved</span>
                </div>
                <div class="text-right">
                  <span class="font-bold text-emerald-700 block">{{ charts?.comparison?.approved_principal || 0 }}</span>
                  <span class="text-[9.5px] text-emerald-600 font-medium">
                    {{ Math.round(((charts?.comparison?.approved_principal || 0) / (charts?.comparison?.total_submitted_se || 1)) * 100) }}% Rate
                  </span>
                </div>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-lg bg-rose-50/80 border border-rose-200 hover:bg-rose-100/80 transition cursor-pointer">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full bg-rose-500 shadow-2xs"></span>
                  <span class="font-semibold text-slate-700">Rejected</span>
                </div>
                <div class="text-right">
                  <span class="font-bold text-rose-700 block">{{ charts?.comparison?.rejected_principal || 0 }}</span>
                  <span class="text-[9.5px] text-rose-600 font-medium">
                    {{ Math.round(((charts?.comparison?.rejected_principal || 0) / (charts?.comparison?.total_submitted_se || 1)) * 100) }}% Rate
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CHART 2: DUAL-COLUMN GROUPED BAR CHART DENGAN FILTER TAHUN MANDIRI -->
        <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4 hover:shadow-md transition">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
              <h3 class="text-sm font-bold text-[#111827]">Analisis Submisi vs Approval per Region Area</h3>
              <p class="text-[11px] text-slate-500">Perbandingan Jumlah Submitted SE (Biru) & Approved Principal (Hijau)</p>
            </div>
            <!-- FILTER TAHUN CHART 2 -->
            <div class="flex items-center gap-1.5 shrink-0">
              <label class="text-[10.5px] font-bold text-slate-500 uppercase">Tahun:</label>
              <select
                v-model="chart2Year"
                @change="applyFilters"
                class="pl-2.5 pr-8 py-1 text-xs border border-slate-200 rounded-lg bg-slate-50 font-bold text-slate-700 hover:bg-white transition cursor-pointer min-w-[125px]"
              >
                <option value="">Semua Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
            </div>
          </div>

          <div v-if="!charts?.top_principal_areas || charts.top_principal_areas.length === 0" class="py-8 text-center text-xs text-[#9CA3AF]">
            Belum ada data pengajuan NOO pada area terpilih / tahun terpilih.
          </div>

          <div v-else class="space-y-3.5 pt-1">
            <div
              v-for="area in charts.top_principal_areas"
              :key="area.area_code"
              class="p-2.5 rounded-xl bg-slate-50/50 hover:bg-slate-100/60 border border-slate-100 transition group cursor-pointer"
            >
              <div class="flex justify-between items-center text-xs mb-1.5">
                <span class="font-mono font-bold text-slate-800 group-hover:text-blue-700 transition">Area {{ area.area_code }}</span>
                <div class="flex items-center gap-3 text-[11px] font-bold">
                  <span class="text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">Submisi: {{ area.total_submitted }}</span>
                  <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Approved: {{ area.approved_principal }}</span>
                </div>
              </div>

              <!-- Dual Progress Bar per Area -->
              <div class="space-y-1.5">
                <div class="w-full bg-slate-200/70 rounded-full h-2.5 overflow-hidden">
                  <div
                    class="bg-blue-600 h-2.5 rounded-full transition-all duration-1000 ease-out group-hover:bg-blue-700"
                    :style="{ width: isChartVisible ? `${Math.min(100, (area.total_submitted / (charts.top_principal_areas[0]?.total_submitted || 1)) * 100)}%` : '0%' }"
                  ></div>
                </div>
                <div class="w-full bg-slate-200/70 rounded-full h-2.5 overflow-hidden">
                  <div
                    class="bg-emerald-500 h-2.5 rounded-full transition-all duration-1000 ease-out group-hover:bg-emerald-600"
                    :style="{ width: isChartVisible ? `${Math.min(100, (area.approved_principal / (charts.top_principal_areas[0]?.total_submitted || 1)) * 100)}%` : '0%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CHART 3: 🏪 SEBARAN SUBMISI PER TIPE OUTLET DENGAN FILTER TAHUN MANDIRI -->
        <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4 hover:shadow-md transition">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
              <h3 class="text-sm font-bold text-[#111827]">Sebaran Submisi per Tipe Outlet / Channel</h3>
              <p class="text-[11px] text-slate-500">Distribusi pengajuan berdasarkan jenis outlet toko</p>
            </div>
            <!-- FILTER TAHUN CHART 3 -->
            <div class="flex items-center gap-1.5 shrink-0">
              <label class="text-[10.5px] font-bold text-slate-500 uppercase">Tahun:</label>
              <select
                v-model="chart3Year"
                @change="applyFilters"
                class="pl-2.5 pr-8 py-1 text-xs border border-slate-200 rounded-lg bg-slate-50 font-bold text-slate-700 hover:bg-white transition cursor-pointer min-w-[125px]"
              >
                <option value="">Semua Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
            </div>
          </div>

          <div v-if="!charts?.outlet_types || charts.outlet_types.length === 0" class="py-8 text-center text-xs text-[#9CA3AF]">
            Belum ada data tipe outlet pada tahun terpilih.
          </div>

          <div v-else class="space-y-3 pt-1">
            <div
              v-for="t in charts.outlet_types"
              :key="t.outlet_type"
              class="p-2 rounded-xl hover:bg-indigo-50/40 transition group cursor-pointer space-y-1.5"
            >
              <div class="flex justify-between items-center text-xs font-bold">
                <span class="text-slate-800 font-mono group-hover:text-indigo-700 transition">{{ t.outlet_type }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                    {{ t.total }} Submisi
                  </span>
                  <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    {{ t.approved }} Approved
                  </span>
                </div>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                <div
                  class="bg-gradient-to-r from-indigo-500 to-purple-600 h-3 rounded-full transition-all duration-1000 ease-out group-hover:brightness-110"
                  :style="{ width: isChartVisible ? `${Math.min(100, (t.total / (charts?.comparison?.total_submitted_se || 1)) * 100)}%` : '0%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- CHART 4: 🏬 TOP CABANG SUBMISI DENGAN FILTER TAHUN MANDIRI -->
        <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4 hover:shadow-md transition">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
              <h3 class="text-sm font-bold text-[#111827]">Top Cabang Submisi NOO Terbanyak</h3>
              <p class="text-[11px] text-slate-500">Peringkat cabang dengan volume pengajuan toko baru tertinggi</p>
            </div>
            <!-- FILTER TAHUN CHART 4 -->
            <div class="flex items-center gap-1.5 shrink-0">
              <label class="text-[10.5px] font-bold text-slate-500 uppercase">Tahun:</label>
              <select
                v-model="chart4Year"
                @change="applyFilters"
                class="pl-2.5 pr-8 py-1 text-xs border border-slate-200 rounded-lg bg-slate-50 font-bold text-slate-700 hover:bg-white transition cursor-pointer min-w-[125px]"
              >
                <option value="">Semua Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
            </div>
          </div>

          <div v-if="!charts?.top_branches || charts.top_branches.length === 0" class="py-8 text-center text-xs text-[#9CA3AF]">
            Belum ada data pengajuan cabang pada tahun terpilih.
          </div>

          <div v-else class="space-y-3 pt-1">
            <div
              v-for="(b, idx) in charts.top_branches.slice(0, 5)"
              :key="b.branch_name"
              class="p-3.5 rounded-xl border border-slate-200/70 bg-slate-50/60 hover:bg-white hover:shadow-md hover:border-emerald-300 transition-all duration-200 flex items-center justify-between gap-4 group cursor-pointer"
            >
              <div class="flex items-center gap-3.5 min-w-0">
                <span
                  :class="[
                    'w-8 h-8 rounded-xl flex items-center justify-center font-black text-xs shadow-2xs shrink-0 transition-transform group-hover:scale-110',
                    idx === 0 ? 'bg-amber-400 text-amber-950' : (idx === 1 ? 'bg-slate-300 text-slate-900' : (idx === 2 ? 'bg-amber-600 text-white' : 'bg-slate-200 text-slate-700'))
                  ]"
                >
                  #{{ idx + 1 }}
                </span>
                <div class="min-w-0">
                  <div class="font-bold text-xs text-slate-900 whitespace-normal break-words leading-snug group-hover:text-emerald-700 transition">
                    {{ b.branch_name }}
                  </div>
                  <div class="text-[11px] text-slate-500 font-medium mt-0.5 flex items-center gap-1.5 flex-wrap">
                    <span class="px-1.5 py-0.5 rounded bg-slate-200/80 text-slate-700 text-[10px] font-bold">Region: {{ b.region_code || '-' }}</span>
                    <span>&bull;</span>
                    <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-800 text-[10px] font-bold border border-blue-200">Entity: {{ b.entity_name || '-' }}</span>
                  </div>
                </div>
              </div>

              <div class="text-right shrink-0">
                <span class="text-base font-black text-emerald-700 block group-hover:scale-105 transition-transform">{{ b.total_submitted }}</span>
                <span class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider">Submisi NOO</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- SECTION 5: SUMMARY RECENT ACTIVITY AUDIT LOGS (TAMPILKAN 5 DATA TERBARU & TEKS AKTIVITAS RAPI) -->
      <div class="bg-white p-6 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold text-[#111827] flex items-center gap-2">
              <span>Summary Recent Audit & Activity Logs</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Daftar 5 aktivitas pergerakan data pengajuan NOO terbaru</p>
          </div>
          <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
            Internal Audit Stream
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-bold text-[10.5px]">
              <tr>
                <th class="p-3">Waktu & Tanggal</th>
                <th class="p-3">Pengguna / User</th>
                <th class="p-3">Aktivitas (Action)</th>
                <th class="p-3">Entitas / Key</th>
                <th class="p-3">Detail Catatan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!recentLogs || recentLogs.length === 0">
                <td colspan="5" class="p-4 text-center text-slate-400 italic">Belum ada log aktivitas tercatat.</td>
              </tr>
              <tr v-else v-for="log in recentLogs.slice(0, 5)" :key="log.id" class="hover:bg-slate-50/80 transition">
                <td class="p-3 font-mono text-[11px] whitespace-nowrap text-slate-600">
                  {{ formatDate(log.timestamp || log.created_at) }}
                </td>
                <td class="p-3">
                  <div class="font-semibold text-slate-800">{{ log.username || 'System' }}</div>
                  <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                    {{ formatRole(log.role || 'USER') }}
                  </span>
                </td>
                <td class="p-3 whitespace-nowrap">
                  <span class="inline-block px-2.5 py-1 text-[11px] font-bold rounded-md bg-blue-50 text-blue-800 border border-blue-200 shadow-2xs whitespace-nowrap">
                    {{ formatActionLabel(log.action) }}
                  </span>
                </td>
                <td class="p-3 font-mono text-[11px] text-slate-600">
                  {{ log.row_key || log.table_name || '-' }}
                </td>
                <td class="p-3 text-[11.5px] text-slate-700 whitespace-normal break-words">
                  {{ log.notes || log.field_name || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </EdpLayout>
</template>
