<script setup lang="js">
/**
 * Halaman Khusus Monitoring Target RO vs Realisasi Approved Salesman.
 * - Target RO Upload per bulan & tahun via Excel/CSV.
 * - Realisasi NOO per status APPROVED di edp_decision & terpisah per bulan (submitted_at).
 * - Alert Peringatan bila Target RO Distributor belum di-upload untuk bulan terpilih.
 * - Redesigned Vertical Stacked Bar Chart per Distributor dengan Tooltip Interactive Salesman Breakdown.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const page = usePage();
const flash = computed(() => page.props.flash || {});

const props = defineProps({
  branchesData: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    default: () => ({}),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  filterOptions: {
    type: Object,
    default: () => ({}),
  },
  userRole: String,
  missingTargetBranches: {
    type: Array,
    default: () => [],
  },
  hasTargetUploaded: {
    type: Boolean,
    default: false,
  },
});

const currentMonthNum = new Date().getMonth() + 1;
const currentYearNum = new Date().getFullYear();

const selectedMonth = ref(props.filters?.month ? String(props.filters.month) : String(currentMonthNum));
const selectedYear = ref(props.filters?.year ? String(props.filters.year) : String(currentYearNum));
const selectedRegion = ref(props.filters?.region_code || '');
const selectedPrincipal = ref(props.filters?.principal || '');
const selectedBranch = ref(props.filters?.branch_id || '');

// Accordion expanded state for detail tables
const expandedBranches = ref({});

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

const selectedMonthName = computed(() => {
  const found = monthOptions.find((m) => m.value === selectedMonth.value);
  return found ? found.label : 'Bulan Terpilih';
});

const yearOptions = computed(() => {
  const yrs = props.filterOptions?.years || [currentYearNum];
  return yrs.map((y) => ({
    value: String(y),
    label: String(y),
  }));
});

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : r.region_code,
  }));
});

const entityOptions = computed(() => {
  let list = props.filterOptions?.entities || [];
  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  return list.map((e) => ({
    value: e.entity_code_principal,
    label: e.entity_name_principal ? `${e.entity_code_principal} - ${e.entity_name_principal}` : e.entity_code_principal,
  }));
});

const branchOptions = computed(() => {
  let list = props.filterOptions?.branches || [];
  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }
  if (selectedPrincipal.value) {
    list = list.filter((b) => b.entity_code_principal === selectedPrincipal.value);
  }
  return list.map((b) => ({
    value: b.branch_id,
    label: `${b.branch_id} - ${b.branch_name}`,
  }));
});

// Cascading resets
watch(selectedRegion, (newReg) => {
  if (newReg) {
    if (selectedPrincipal.value) {
      const valid = entityOptions.value.some((e) => e.value === selectedPrincipal.value);
      if (!valid) selectedPrincipal.value = '';
    }
    if (selectedBranch.value) {
      const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
      if (!valid) selectedBranch.value = '';
    }
  }
});

watch(selectedPrincipal, (newEnt) => {
  if (newEnt && selectedBranch.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) selectedBranch.value = '';
  }
});

function applyFilters() {
  const queryParams = {};
  if (selectedMonth.value) queryParams.month = selectedMonth.value;
  if (selectedYear.value) queryParams.year = selectedYear.value;
  if (selectedRegion.value) queryParams.region_code = selectedRegion.value;
  if (selectedPrincipal.value) queryParams.principal = selectedPrincipal.value;
  if (selectedBranch.value) queryParams.branch_id = selectedBranch.value;

  router.get(route('edp.monitoring_ro'), queryParams, {
    preserveScroll: true,
    replace: true,
    onSuccess: () => {
      if (window.location.search) {
        window.history.replaceState({}, '', window.location.pathname);
      }
    },
  });
}

function resetFilters() {
  selectedMonth.value = String(currentMonthNum);
  selectedYear.value = String(currentYearNum);
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
  applyFilters();
}

onMounted(() => {
  if (props.branchesData) {
    props.branchesData.forEach((b) => {
      expandedBranches.value[b.branch_id] = true;
    });
  }
});

function toggleBranchExpand(branchId) {
  expandedBranches.value[branchId] = !expandedBranches.value[branchId];
}

// UPLOAD MODAL STATE
const showUploadModal = ref(false);
const uploadMonth = ref(String(currentMonthNum));
const uploadYear = ref(String(currentYearNum));
const selectedUploadFile = ref(null);
const dragActive = ref(false);

const uploadForm = useForm({
  month: uploadMonth.value,
  year: uploadYear.value,
  file: null,
});

function openUploadModal(m = null, y = null) {
  uploadMonth.value = m ? String(m) : selectedMonth.value;
  uploadYear.value = y ? String(y) : selectedYear.value;
  selectedUploadFile.value = null;
  uploadForm.clearErrors();
  showUploadModal.value = true;
}

function closeUploadModal() {
  showUploadModal.value = false;
  selectedUploadFile.value = null;
}

function handleFileSelect(event) {
  const file = event.target.files[0];
  if (file) {
    selectedUploadFile.value = file;
    uploadForm.file = file;
  }
}

function handleDrop(event) {
  dragActive.value = false;
  const file = event.dataTransfer.files[0];
  if (file) {
    selectedUploadFile.value = file;
    uploadForm.file = file;
  }
}

function submitUploadTarget() {
  if (!selectedUploadFile.value) {
    alert('Harap pilih berkas Excel atau CSV target RO.');
    return;
  }
  uploadForm.month = uploadMonth.value;
  uploadForm.year = uploadYear.value;

  uploadForm.post(route('edp.monitoring_ro.upload_target'), {
    preserveScroll: true,
    onSuccess: () => {
      closeUploadModal();
    },
  });
}

function downloadTemplate() {
  window.open(route('edp.monitoring_ro.download_template'), '_blank');
}

// FILTERED BRANCHES & COMPUTED CHART METRICS
const filteredBranchesData = computed(() => {
  let list = props.branchesData || [];

  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }
  if (selectedPrincipal.value) {
    list = list.filter(
      (b) =>
        b.entity_code_principal === selectedPrincipal.value ||
        (b.entity_name_principal && b.entity_name_principal.toLowerCase().includes(selectedPrincipal.value.toLowerCase()))
    );
  }
  if (selectedBranch.value) {
    list = list.filter((b) => b.branch_id === selectedBranch.value);
  }

  return list;
});

// CHART Y-AXIS SCALING (Sumbu Y Besaran Total RO)
const maxChartRoVal = computed(() => {
  let max = 300;
  filteredBranchesData.value.forEach((b) => {
    if ((b.total_target_ro || 0) > max) max = b.total_target_ro;
    if ((b.total_approved_ro || 0) > max) max = b.total_approved_ro;
  });
  return Math.ceil(max / 100) * 100;
});

const yAxisTicks = computed(() => {
  const max = maxChartRoVal.value;
  return [
    max,
    Math.round(max * 0.75),
    Math.round(max * 0.5),
    Math.round(max * 0.25),
    0,
  ];
});

// COMPACT HOVER TOOLTIP STATE FOR DISTRIBUTOR CHART
const hoveredChartBranch = ref(null);
const tooltipPosition = ref({ x: 0, y: 0 });

// DETAIL MODAL STATE ON BAR CLICK
const detailModalBranch = ref(null);

function handleChartBarHover(event, branch) {
  hoveredChartBranch.value = branch;
  const rect = event.currentTarget.getBoundingClientRect();
  tooltipPosition.value = {
    x: rect.left + rect.width / 2,
    y: rect.top,
  };
}

function handleChartBarLeave() {
  hoveredChartBranch.value = null;
}

function handleChartBarClick(branch) {
  detailModalBranch.value = branch;
}

function closeDetailModal() {
  detailModalBranch.value = null;
}

// DYNAMIC GLOBAL SUMMARY
const filteredSummary = computed(() => {
  const branches = filteredBranchesData.value;
  let totalSalesmen = 0;
  let totalAchieved = 0;
  let totalApprovedRo = 0;
  let totalTargetRo = 0;

  branches.forEach((b) => {
    totalSalesmen += b.total_salesmen_count || 0;
    totalAchieved += b.achieved_salesmen_count || 0;
    totalApprovedRo += b.total_approved_ro || 0;
    totalTargetRo += b.total_target_ro || 0;
  });

  return {
    total_branches: branches.length,
    total_salesmen: totalSalesmen,
    total_achieved: totalAchieved,
    total_approved_ro: totalApprovedRo,
    total_target_ro: totalTargetRo,
  };
});
</script>

<template>
  <EdpLayout>
    <Head title="Monitoring Target & Realisasi RO Salesman - Portal Principal" />

    <div class="space-y-6">

      <!-- FLASH NOTIFICATIONS (SUCCESS / WARNING / ERROR) -->
      <div v-if="flash.success" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs font-bold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <span>✅</span>
          <span>{{ flash.success }}</span>
        </div>
      </div>
      <div v-if="flash.warning" class="p-4 rounded-2xl bg-amber-50 border-2 border-amber-300 text-amber-950 text-xs font-medium flex items-start gap-2 shadow-xs">
        <span class="text-base shrink-0">⚠️</span>
        <div class="leading-relaxed whitespace-pre-line font-medium">{{ flash.warning }}</div>
      </div>
      <div v-if="flash.error" class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-xs font-bold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <span>❌</span>
          <span>{{ flash.error }}</span>
        </div>
      </div>

      <!-- HEADER DASHBOARD TITLE & METRICS -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs text-slate-900 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div class="space-y-1.5">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">
              Monitoring Target & Realisasi RO Salesman
            </h1>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
              Periode {{ selectedMonthName }} {{ selectedYear }}
            </span>
          </div>
          <p class="text-xs text-slate-500 font-medium max-w-2xl leading-relaxed">
            Rekapitulasi capaian Registered Outlet (RO) toko approved vs target kuota per salesman per distributor per bulan (berdasarkan tanggal <span class="font-semibold text-slate-700">SE Submitted</span>).
          </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap shrink-0">
          <button
            type="button"
            @click="openUploadModal()"
            class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs shadow-md transition flex items-center gap-2 cursor-pointer shrink-0"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span>Upload Target RO</span>
          </button>

          <!-- METRIC SUMMARY CARDS -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <div class="px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-center">
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Cabang</div>
              <div class="text-base font-bold text-slate-900 mt-0.5">{{ filteredSummary.total_branches }}</div>
            </div>
            <div class="px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-center">
              <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Salesman</div>
              <div class="text-base font-bold text-slate-900 mt-0.5">{{ filteredSummary.total_salesmen }}</div>
            </div>
            <div class="px-3.5 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-center">
              <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Achieved</div>
              <div class="text-base font-bold text-emerald-800 mt-0.5">{{ filteredSummary.total_achieved }}</div>
            </div>
            <div class="px-3.5 py-2.5 rounded-xl bg-blue-50 border border-blue-200 text-center">
              <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Approved RO</div>
              <div class="text-base font-bold text-blue-900 mt-0.5">{{ filteredSummary.total_approved_ro }} / {{ filteredSummary.total_target_ro }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- WARNING ALERT BANNER BILA TARGET BULAN INI BELUM DI-UPLOAD UNTUK DISTRIBUTOR TERPAUT -->
      <div
        v-if="missingTargetBranches && missingTargetBranches.length > 0"
        class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 sm:p-5 shadow-xs flex items-center gap-3"
      >
        <div class="w-10 h-10 rounded-xl bg-amber-100 border border-amber-300 text-amber-700 flex items-center justify-center shrink-0 font-bold text-lg">
          ⚠️
        </div>
        <div class="space-y-1">
          <h3 class="text-sm font-black text-amber-900">
            Peringatan: Target RO Bulan {{ selectedMonthName }} {{ selectedYear }} Belum Lengkap!
          </h3>
          <p class="text-xs text-amber-800 font-medium leading-relaxed">
            Target RO dari <strong>{{ missingTargetBranches.length }} distributor</strong> di bawah belum diupload oleh EDP untuk bulan {{ selectedMonthName }} {{ selectedYear }}:
            <span class="font-bold text-amber-950">
              {{ missingTargetBranches.map(b => b.branch_name).slice(0, 5).join(', ') }}{{ missingTargetBranches.length > 5 ? ` dan ${missingTargetBranches.length - 5} distributor lainnya` : '' }}
            </span>.
          </p>
        </div>
      </div>

      <!-- FILTER BAR (MONTH, YEAR, REGION, PRINCIPAL, BRANCH) -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
            Filter Monitoring RO Bulanan
          </span>
          <button
            @click="resetFilters"
            class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
          >
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
          <!-- Filter Bulan Target -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Bulan Target RO:</label>
            <select
              v-model="selectedMonth"
              @change="applyFilters"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white font-semibold text-slate-800 shadow-2xs"
            >
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                {{ m.label }}
              </option>
            </select>
          </div>

          <!-- Filter Tahun Target -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Tahun Target RO:</label>
            <select
              v-model="selectedYear"
              @change="applyFilters"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white font-medium shadow-2xs"
            >
              <option v-for="y in yearOptions" :key="y.value" :value="y.value">
                {{ y.label }}
              </option>
            </select>
          </div>

          <!-- Filter Region -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">1. Wilayah / Region:</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="Semua Region"
              @change="applyFilters"
            />
          </div>

          <!-- Filter Entity Principal -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">2. Entity Principal:</label>
            <SearchableSelect
              v-model="selectedPrincipal"
              :options="entityOptions"
              placeholder="Semua Entity"
              @change="applyFilters"
            />
          </div>

          <!-- Filter Branch -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">3. Cabang / Branch:</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="Semua Cabang"
              @change="applyFilters"
            />
          </div>
        </div>
      </div>

      <!-- GRAFIK SUMMARY MONITORING RO PER DISTRIBUTOR (STACKED VERTICAL BAR CHART WITH HORIZONTAL SCROLL) -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-3">
          <div>
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
              <span>Grafik Summary Monitoring RO per Distributor</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Sumbu Y: Total Besaran RO (Realisasi vs Target) &bull; Sumbu X: Distributor (Sorot kursor / hover pada bar distributor untuk detail salesman).
            </p>
          </div>

          <!-- CHART LEGENDS -->
          <div class="flex items-center gap-2.5 text-xs font-semibold flex-wrap">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 text-[11px]">
              <span class="w-3 h-3 rounded bg-slate-300 border border-slate-400"></span>
              <span>Target RO Distributor</span>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 text-[11px]">
              <span class="w-3 h-3 rounded bg-gradient-to-t from-blue-600 to-indigo-600"></span>
              <span>Realisasi Approved RO</span>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-300 text-[11px] font-bold">
              <span class="w-3 h-3 rounded bg-gradient-to-t from-emerald-500 to-teal-400"></span>
              <span>Achieved Target (&ge;100%)</span>
            </div>
          </div>
        </div>

        <div v-if="filteredBranchesData.length === 0" class="py-16 text-center text-xs text-slate-400 italic">
          Tidak ada data distributor untuk grafik pada filter terpilih.
        </div>

        <!-- VERTICAL BAR CHART WITH FIXED Y-AXIS & HORIZONTAL SCROLLABLE X-AXIS CONTAINER -->
        <div v-else class="relative pt-2 pb-2">

          <div class="flex items-stretch">
            <!-- FIXED LEFT Y-AXIS TICKS -->
            <div class="w-16 shrink-0 flex flex-col justify-between text-right pr-2 text-[10.5px] font-mono font-bold text-slate-400 h-80 pt-10 pb-8 border-r border-slate-200 select-none">
              <span v-for="tick in yAxisTicks" :key="tick">{{ tick }}</span>
            </div>

            <!-- HORIZONTAL SCROLLABLE BARS HOLDER -->
            <div class="flex-1 overflow-x-auto custom-scrollbar pl-4 pt-10 pb-4">
              <div class="flex items-end gap-6 min-w-max h-72 border-b-2 border-slate-300 pb-2 relative overflow-visible">
                
                <!-- BACKGROUND HORIZONTAL GRID LINES -->
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-20 pb-8 pt-4">
                  <div class="w-full h-px bg-slate-400"></div>
                  <div class="w-full h-px bg-slate-400"></div>
                  <div class="w-full h-px bg-slate-400"></div>
                  <div class="w-full h-px bg-slate-400"></div>
                  <div class="w-full h-px bg-slate-400"></div>
                </div>

                <!-- EACH DISTRIBUTOR COLUMN BAR -->
                <div
                  v-for="b in filteredBranchesData"
                  :key="b.branch_id"
                  @mouseenter="handleChartBarHover($event, b)"
                  @mouseleave="handleChartBarLeave"
                  @click="handleChartBarClick(b)"
                  class="flex flex-col items-center gap-2 group cursor-pointer relative"
                  style="width: 85px;"
                  title="Klik untuk mengunci detail salesman distributor ini"
                >
                  <!-- VALUE BADGE ABOVE BAR (HIGH CONTRAST, ALWAYS VISIBLE & UNCLIPPED) -->
                  <div class="text-[10.5px] font-mono font-extrabold px-2 py-0.5 rounded-md bg-slate-900 text-white border border-slate-700 shadow-md transition-transform group-hover:scale-110 group-hover:bg-blue-900 group-hover:border-blue-500 whitespace-nowrap z-20 shrink-0">
                    {{ b.total_target_ro > 0 ? `${b.total_approved_ro}/${b.total_target_ro}` : `${b.total_approved_ro} RO` }}
                  </div>

                  <!-- VERTICAL PILLAR TRACK (TARGET vs APPROVED) -->
                  <div class="w-10 h-48 bg-slate-100 rounded-t-xl border border-slate-300/80 relative flex items-end justify-center overflow-hidden group-hover:border-blue-500 transition-colors shadow-inner">
                    <!-- TARGET RO PILL LEVEL -->
                    <div
                      v-if="b.total_target_ro > 0"
                      class="w-full bg-slate-200/90 border-t border-slate-300 absolute bottom-0 left-0 transition-all duration-500"
                      :style="{ height: `${Math.min(100, (b.total_target_ro / maxChartRoVal) * 100)}%` }"
                    ></div>

                    <!-- APPROVED REALISASI RO GRADIENT BAR -->
                    <div
                      class="w-full rounded-t-lg transition-all duration-700 ease-out z-10 flex items-start justify-center pt-1 group-hover:brightness-110"
                      :class="b.is_branch_achieved ? 'bg-gradient-to-t from-emerald-600 via-teal-500 to-emerald-400 border-t-2 border-emerald-300' : 'bg-gradient-to-t from-blue-700 via-indigo-600 to-blue-500 border-t-2 border-blue-300'"
                      :style="{ height: `${Math.min(100, (b.total_approved_ro / maxChartRoVal) * 100)}%` }"
                    >
                      <span v-if="b.branch_percentage >= 20" class="text-[9px] font-black text-white font-mono leading-none">
                        {{ b.branch_percentage }}%
                      </span>
                    </div>
                  </div>

                  <!-- X-AXIS DISTRIBUTOR LABEL -->
                  <div class="text-center w-full">
                    <div class="text-[11px] font-bold text-slate-800 truncate group-hover:text-blue-700 transition" :title="b.branch_name">
                      {{ b.branch_name }}
                    </div>
                    <div class="text-[9.5px] font-mono text-slate-400">
                      {{ b.branch_id }}
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- COMPACT HOVER TOOLTIP (SLIM & ELEGANT - MATCHING REFERENCE IMAGE) -->
          <div
            v-if="hoveredChartBranch"
            class="fixed z-50 w-64 bg-slate-950/90 backdrop-blur-md text-white rounded-xl p-3 border border-slate-800/90 shadow-2xl pointer-events-none -translate-x-1/2 -translate-y-full -mt-3 space-y-2 transition-all duration-100"
            :style="{ left: `${tooltipPosition.x}px`, top: `${tooltipPosition.y}px` }"
          >
            <!-- TOOLTIP HEADER -->
            <div class="border-b border-slate-800/80 pb-1.5 flex items-center justify-between gap-1.5">
              <div class="truncate">
                <div class="text-[11px] font-bold text-slate-100 truncate" :title="hoveredChartBranch.branch_name">
                  {{ hoveredChartBranch.branch_name }}
                </div>
                <div class="text-[9.5px] text-slate-400 font-mono">
                  {{ hoveredChartBranch.branch_id }} &bull; {{ hoveredChartBranch.region_code }}
                </div>
              </div>
              <span
                class="px-2 py-0.5 text-[9px] font-bold rounded-full shrink-0"
                :class="hoveredChartBranch.total_target_ro > 0 ? (hoveredChartBranch.is_branch_achieved ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30') : 'bg-slate-800 text-slate-400'"
              >
                {{ hoveredChartBranch.total_target_ro > 0 ? (hoveredChartBranch.is_branch_achieved ? 'Achieved' : `${hoveredChartBranch.branch_percentage}%`) : 'Belum Set' }}
              </span>
            </div>

            <!-- COMPACT SALESMAN ROW LIST (LIKE REFERENCE IMAGE) -->
            <div class="space-y-1.5 text-[11px]">
              <div
                v-for="s in hoveredChartBranch.salesmen.slice(0, 5)"
                :key="s.salesman_code"
                class="flex items-center justify-between gap-2"
              >
                <div class="flex items-center gap-1.5 truncate">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="s.is_custom_target && s.is_achieved ? 'bg-emerald-400 shadow-xs' : (s.approved_ro > 0 ? 'bg-blue-400' : 'bg-purple-400')"
                  ></span>
                  <span class="text-slate-200 truncate font-medium text-[11px]" :title="s.salesman_name">
                    {{ s.salesman_name }}
                  </span>
                </div>

                <div class="font-mono text-[10.5px] shrink-0 text-slate-300">
                  <span v-if="s.is_custom_target && s.target_ro > 0">
                    <strong :class="s.is_achieved ? 'text-emerald-400' : 'text-blue-300'">{{ s.approved_ro }}</strong> / {{ s.target_ro }}
                  </span>
                  <span v-else class="text-slate-400">
                    {{ s.approved_ro }} RO
                  </span>
                </div>
              </div>

              <!-- MORE SALESMEN FOOTER HINT -->
              <div v-if="hoveredChartBranch.salesmen.length > 5" class="text-[9.5px] text-blue-300/80 italic text-center pt-1 border-t border-slate-800/60">
                + {{ hoveredChartBranch.salesmen.length - 5 }} salesman lainnya (Klik bar untuk detail)
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- DETAIL SALESMAN MODAL DIALOG ON BAR CLICK -->
      <Teleport to="body">
        <div
          v-if="detailModalBranch"
          class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
          @click.self="closeDetailModal"
        >
          <div class="bg-white rounded-2xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 my-auto">
            <!-- MODAL HEADER -->
            <div class="bg-slate-900 text-white p-4 sm:p-5 flex items-center justify-between gap-3">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="text-base font-black text-white">{{ detailModalBranch.branch_name }}</h3>
                  <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-blue-500/30 border border-blue-400/40 text-blue-200">
                    {{ detailModalBranch.branch_id }}
                  </span>
                  <span class="text-xs text-slate-300 font-medium">({{ detailModalBranch.region_code }})</span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                  Rincian capaian {{ detailModalBranch.total_salesmen_count }} salesman pada periode {{ selectedMonthName }} {{ selectedYear }}.
                </p>
              </div>

              <button
                type="button"
                @click="closeDetailModal"
                class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center font-bold text-sm transition cursor-pointer shrink-0"
              >
                ✕
              </button>
            </div>

            <!-- MODAL BODY -->
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
              <!-- SUMMARY TILES -->
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-center">
                  <div class="text-[10px] font-bold text-slate-500 uppercase">Salesman</div>
                  <div class="text-sm font-black text-slate-900 mt-0.5">{{ detailModalBranch.total_salesmen_count }} Orang</div>
                </div>
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-center">
                  <div class="text-[10px] font-bold text-emerald-700 uppercase">Achieved</div>
                  <div class="text-sm font-black text-emerald-800 mt-0.5">{{ detailModalBranch.achieved_salesmen_count }} Salesman</div>
                </div>
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-center col-span-2 sm:col-span-1">
                  <div class="text-[10px] font-bold text-blue-700 uppercase">Total Approved RO</div>
                  <div class="text-sm font-black text-blue-900 mt-0.5">{{ detailModalBranch.total_approved_ro }} / {{ detailModalBranch.total_target_ro > 0 ? detailModalBranch.total_target_ro : '-' }}</div>
                </div>
              </div>

              <!-- SALESMEN LIST -->
              <div class="space-y-2.5">
                <div
                  v-for="s in detailModalBranch.salesmen"
                  :key="s.salesman_code"
                  class="p-3 rounded-xl border transition"
                  :class="s.is_custom_target && s.is_achieved ? 'bg-emerald-50/40 border-emerald-200' : 'bg-slate-50/70 border-slate-200'"
                >
                  <div class="flex items-center justify-between gap-2">
                    <div>
                      <div class="font-bold text-slate-900 text-xs">{{ s.salesman_name }}</div>
                      <div class="text-[10.5px] font-mono text-slate-500 mt-0.5">Kode: {{ s.salesman_code }}</div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span
                        v-if="s.is_custom_target && s.visit_type"
                        class="text-[10px] font-extrabold px-2 py-0.5 rounded border uppercase"
                        :class="s.visit_type === 'F4' ? 'bg-indigo-100 text-indigo-800 border-indigo-200' : 'bg-purple-100 text-purple-800 border-purple-200'"
                      >
                        {{ s.visit_type }}
                      </span>
                      <span
                        v-if="s.is_custom_target && s.is_achieved"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300"
                      >
                        Achieved
                      </span>
                      <span
                        v-else-if="s.is_custom_target"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700"
                      >
                        Sisa {{ Math.max(0, s.target_ro - s.approved_ro) }} RO
                      </span>
                      <span v-else class="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200">
                        Target Belum Set
                      </span>
                    </div>
                  </div>

                  <!-- PROGRESS BAR IN MODAL -->
                  <div v-if="s.is_custom_target && s.target_ro > 0" class="mt-2 space-y-1">
                    <div class="flex items-center justify-between text-[10.5px] font-mono text-slate-600">
                      <span>Realisasi: <strong>{{ s.approved_ro }}</strong> / {{ s.target_ro }} RO</span>
                      <span class="font-bold" :class="s.is_achieved ? 'text-emerald-700' : 'text-slate-700'">{{ s.percentage }}%</span>
                    </div>
                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="s.is_achieved ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-blue-500 to-indigo-500'"
                        :style="{ width: `${Math.min(100, s.percentage)}%` }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- MODAL FOOTER -->
            <div class="bg-slate-50 p-4 border-t border-slate-200 flex justify-end">
              <button
                type="button"
                @click="closeDetailModal"
                class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- REDESIGNED EXECUTIVE DATA TABLE SECTION -->
      <div class="space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2 px-1">
          <div>
            <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
              <span>Tabel Detail RO per Distributor ({{ selectedMonthName }} {{ selectedYear }})</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Rincian data per Cabang & Salesman beserta tipe kunjungan F2/F4, target bulanan, dan status keaktifan RO.
            </p>
          </div>
          <span class="text-xs font-semibold px-3 py-1 rounded-xl bg-slate-100 text-slate-700 border border-slate-200">
            Total Cabang: <strong>{{ filteredBranchesData.length }}</strong>
          </span>
        </div>

        <div v-if="!filteredBranchesData || filteredBranchesData.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center text-xs text-slate-400 italic shadow-xs">
          Belum ada data cabang aktif yang terdaftar pada filter terpilih.
        </div>

        <!-- BRANCH ACCORDION CARDS WITH REVAMPED DATA TABLES -->
        <div v-else class="space-y-4">
          <div
            v-for="b in filteredBranchesData"
            :key="b.branch_id"
            class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden transition-all duration-300"
          >
            <!-- BRANCH GROUP HEADER BAR -->
            <div
              @click="toggleBranchExpand(b.branch_id)"
              class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 p-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 cursor-pointer select-none hover:bg-slate-800 transition"
            >
              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center text-xs font-bold text-white transition shrink-0"
                >
                  {{ expandedBranches[b.branch_id] ? '▼' : '▶' }}
                </button>
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm font-black text-white tracking-tight">{{ b.branch_name }}</h3>
                    <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-blue-500/30 border border-blue-400/40 text-blue-200">
                      {{ b.branch_id }}
                    </span>
                    <span
                      v-if="b.entity_code_principal"
                      class="text-[10px] font-bold px-2 py-0.5 rounded-full border uppercase"
                      :class="b.entity_code_principal.includes('INA') ? 'bg-purple-900/60 border-purple-400/50 text-purple-200' : 'bg-red-900/60 border-red-400/50 text-red-200'"
                    >
                      {{ b.entity_code_principal }}
                    </span>
                    <span v-if="b.region_code" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-700/60 border border-slate-600 text-slate-300">
                      {{ b.region_code }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- BRANCH SUMMARY STATS & MINI PROGRESS -->
              <div class="flex items-center gap-3 shrink-0 flex-wrap">
                <div class="text-right hidden sm:block">
                  <div class="text-[11px] text-slate-300 font-medium">
                    Salesman Achieved: <strong class="text-emerald-400 font-bold">{{ b.achieved_salesmen_count }}</strong> / {{ b.total_salesmen_count }}
                  </div>
                  <div class="text-[11px] text-slate-300 font-medium">
                    Approved RO: <strong class="text-amber-300 font-bold">{{ b.total_approved_ro }}</strong> / {{ b.total_target_ro > 0 ? `${b.total_target_ro} RO` : '-' }}
                  </div>
                </div>

                <div class="w-24 sm:w-28 bg-slate-700/60 rounded-full h-3 border border-white/20 overflow-hidden relative">
                  <div
                    class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500"
                    :style="{ width: b.total_target_ro > 0 ? `${Math.min(100, (b.total_approved_ro / b.total_target_ro) * 100)}%` : '0%' }"
                  ></div>
                </div>
              </div>
            </div>

            <!-- COLLAPSIBLE TABLE CONTAINER -->
            <div v-show="expandedBranches[b.branch_id]" class="p-0 border-t border-slate-200">
              <div v-if="!b.salesmen || b.salesmen.length === 0" class="p-6 text-center text-xs text-slate-400 italic">
                Belum ada salesman terdaftar di cabang <strong>{{ b.branch_name }}</strong>.
              </div>

              <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[720px]">
                  <thead>
                    <tr class="bg-slate-100/80 text-slate-700 text-[11px] font-black uppercase tracking-wider border-b border-slate-200">
                      <th class="py-3 px-4 w-60">Salesman</th>
                      <th class="py-3 px-3 text-center w-36">Tipe Kunjungan</th>
                      <th class="py-3 px-3 text-center w-36">Target RO {{ selectedMonthName }}</th>
                      <th class="py-3 px-3 text-center w-32">Approved RO</th>
                      <th class="py-3 px-4 w-64">Progres Pencapaian %</th>
                      <th class="py-3 px-4 text-center w-36">Status Target</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-xs">
                    <tr
                      v-for="s in b.salesmen"
                      :key="s.salesman_code"
                      class="hover:bg-blue-50/40 transition duration-150"
                      :class="s.is_custom_target && s.is_achieved ? 'bg-emerald-50/20' : ''"
                    >
                      <!-- SALESMAN NAME & CODE -->
                      <td class="py-3.5 px-4 font-semibold text-slate-900">
                        <div class="font-bold text-slate-900 text-[13px] flex items-center gap-2">
                          <span class="truncate">{{ s.salesman_name }}</span>
                        </div>
                        <div class="text-[10.5px] font-mono text-slate-500 mt-0.5 flex items-center gap-1.5">
                          <span>Kode: {{ s.salesman_code }}</span>
                          <span v-if="s.is_custom_target" class="px-1.5 py-0.2 rounded bg-blue-100 text-blue-800 text-[9.5px] font-bold">Target Uploaded</span>
                        </div>
                      </td>

                      <!-- VISIT TYPE (F2 / F4) -->
                      <td class="py-3.5 px-3 text-center">
                        <span
                          v-if="s.is_custom_target && s.visit_type"
                          class="px-2.5 py-1 text-[11px] font-black rounded-lg border inline-flex items-center gap-1 shadow-2xs"
                          :class="s.visit_type === 'F4' ? 'bg-indigo-50 text-indigo-800 border-indigo-200' : 'bg-purple-50 text-purple-800 border-purple-200'"
                        >
                          <span>{{ s.visit_type === 'F4' ? 'F4' : 'F2' }}</span>
                          <span class="text-[10px] font-normal opacity-80">({{ s.visit_type === 'F4' ? 'Mingguan' : '2-Mingguan' }})</span>
                        </span>
                        <span v-else class="text-slate-400 font-medium text-xs">-</span>
                      </td>

                      <!-- TARGET RO -->
                      <td class="py-3.5 px-3 text-center">
                        <span v-if="s.is_custom_target && s.target_ro > 0" class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-mono font-extrabold text-slate-800 text-[13px]">
                          {{ s.target_ro }} RO
                        </span>
                        <span v-else class="text-slate-400 font-medium text-xs">-</span>
                      </td>

                      <!-- APPROVED RO -->
                      <td class="py-3.5 px-3 text-center">
                        <span
                          class="px-3 py-1 font-mono font-black text-[13.5px] rounded-lg border shadow-2xs inline-flex items-center gap-1.5"
                          :class="s.is_custom_target && s.is_achieved ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-blue-50 text-blue-900 border-blue-200'"
                        >
                          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                          {{ s.approved_ro }} RO
                        </span>
                      </td>

                      <!-- PROGRESS BAR & PERCENTAGE -->
                      <td class="py-3.5 px-4">
                        <div v-if="s.is_custom_target && s.target_ro > 0" class="space-y-1">
                          <div class="flex items-center justify-between text-[11px] font-bold">
                            <span :class="s.is_achieved ? 'text-emerald-700' : 'text-slate-700'">
                              {{ s.percentage }}%
                            </span>
                            <span class="text-[10px] text-slate-500">
                              {{ s.approved_ro }} / {{ s.target_ro }}
                            </span>
                          </div>
                          <div class="w-full bg-slate-200/80 h-3 rounded-full overflow-hidden p-0.5 border border-slate-300/60 shadow-inner">
                            <div
                              class="h-full rounded-full transition-all duration-700 ease-out"
                              :class="s.is_achieved ? 'bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-600' : (s.percentage >= 50 ? 'bg-gradient-to-r from-blue-600 to-indigo-600' : 'bg-gradient-to-r from-amber-500 to-rose-500')"
                              :style="{ width: `${Math.min(100, s.percentage)}%` }"
                            ></div>
                          </div>
                        </div>
                        <div v-else class="text-center text-slate-400 font-medium text-xs">
                          -
                        </div>
                      </td>

                      <!-- STATUS BADGE -->
                      <td class="py-3.5 px-4 text-center">
                        <span
                          v-if="s.is_custom_target && s.is_achieved"
                          class="px-3 py-1 text-[11px] font-black rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs inline-flex items-center gap-1"
                        >
                          Achieved
                        </span>
                        <span
                          v-else-if="s.is_custom_target && s.target_ro > 0"
                          class="px-3 py-1 text-[11px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200 inline-flex items-center gap-1"
                        >
                          Sisa {{ Math.max(0, s.target_ro - s.approved_ro) }} RO
                        </span>
                        <span
                          v-else
                          class="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-slate-100 text-slate-400 border border-slate-200 inline-block"
                        >
                          Target Belum Set
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL UPLOAD TARGET RO (DRAG & DROP / CLICK UPLOAD, DOWNLOAD TEMPLATE, INPUT BULAN & TAHUN) -->
      <Teleport to="body">
        <div
          v-if="showUploadModal"
          class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
          @click.self="closeUploadModal"
        >
          <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-200 my-auto">
            <!-- MODAL HEADER -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="space-y-0.5">
                <h3 class="text-lg font-black text-slate-900">Upload Target RO Salesman</h3>
                <p class="text-xs text-slate-500 font-medium">Unggah berkas target RO bulanan (.xls, .xlsx, .csv)</p>
              </div>
              <button
                @click="closeUploadModal"
                class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm cursor-pointer transition"
              >
                ✕
              </button>
            </div>

            <!-- INPUT FORM -->
            <div class="space-y-4">
              <!-- INPUT BULAN & TAHUN -->
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Target Bulan:</label>
                  <select
                    v-model="uploadMonth"
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 font-semibold bg-white cursor-pointer"
                  >
                    <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                      {{ m.label }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Target Tahun:</label>
                  <select
                    v-model="uploadYear"
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 font-semibold bg-white cursor-pointer"
                  >
                    <option v-for="y in yearOptions" :key="y.value" :value="y.value">
                      {{ y.label }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- DRAG & DROP FILE ZONE -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Berkas Excel / CSV Target RO:</label>
                
                <div
                  @dragover.prevent="dragActive = true"
                  @dragleave.prevent="dragActive = false"
                  @drop.prevent="handleDrop"
                  class="border-2 border-dashed rounded-2xl p-6 text-center transition cursor-pointer flex flex-col items-center justify-center gap-2"
                  :class="dragActive ? 'border-blue-500 bg-blue-50/60' : (selectedUploadFile ? 'border-emerald-400 bg-emerald-50/40' : 'border-slate-300 bg-slate-50/50 hover:bg-slate-100/60')"
                  @click="$refs.fileInput.click()"
                >
                  <input
                    ref="fileInput"
                    type="file"
                    accept=".xls,.xlsx,.csv"
                    class="hidden"
                    @change="handleFileSelect"
                  />

                  <div v-if="selectedUploadFile" class="space-y-1">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 border border-emerald-300 flex items-center justify-center mx-auto text-xl font-bold">
                      📊
                    </div>
                    <div class="text-xs font-bold text-emerald-900 truncate max-w-xs">
                      {{ selectedUploadFile.name }}
                    </div>
                    <div class="text-[10px] text-emerald-700 font-mono">
                      {{ (selectedUploadFile.size / 1024).toFixed(1) }} KB
                    </div>
                    <span class="text-[11px] text-blue-600 font-bold hover:underline block pt-1">Klik untuk mengganti berkas</span>
                  </div>

                  <div v-else class="space-y-1">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 border border-blue-200 flex items-center justify-center mx-auto text-xl font-bold">
                      📁
                    </div>
                    <div class="text-xs font-bold text-slate-800">
                      Drag & drop berkas Excel di sini, atau <span class="text-blue-600 underline">klik untuk cari</span>
                    </div>
                    <div class="text-[10.5px] text-slate-500 font-medium">
                      Format didukung: .xlsx, .xls, .csv (Maksimal 10 MB)
                    </div>
                  </div>
                </div>
              </div>

              <!-- BUTTON DOWNLOAD TEMPLATE EXCEL -->
              <div class="pt-1 flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-600 font-medium">
                  Belum memiliki format file? Download template resmi Excel di sini:
                </div>
                <button
                  type="button"
                  @click="downloadTemplate"
                  class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold shadow-2xs transition flex items-center gap-1.5 cursor-pointer shrink-0"
                >
                  <span>Download Template</span>
                </button>
              </div>

              <!-- ERROR ALERT -->
              <div v-if="uploadForm.errors.file" class="text-xs font-bold text-rose-600 bg-rose-50 p-2.5 rounded-xl border border-rose-200">
                {{ uploadForm.errors.file }}
              </div>
            </div>

            <!-- MODAL ACTIONS -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
              <button
                type="button"
                @click="closeUploadModal"
                class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 border border-slate-200 transition cursor-pointer"
              >
                Batal
              </button>

              <button
                type="button"
                @click="submitUploadTarget"
                :disabled="uploadForm.processing || !selectedUploadFile"
                class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition disabled:opacity-50 flex items-center gap-2 cursor-pointer"
              >
                <svg v-if="uploadForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>Simpan & Aplikasikan</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
