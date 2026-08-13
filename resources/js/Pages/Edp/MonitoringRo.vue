<script setup lang="js">
/**
 * Halaman Khusus Monitoring Target RO vs Realisasi Approved Salesman.
 * - Exact Cascading Filter (Region -> Entity -> Branch).
 * - Instant Client-Side Multiselect Month Checkbox Filter (Kuartal, Semester, Multi-Bulan).
 * - Multi-month Approval Date filtering for EDP Approved ROs.
 * - Redesigned Executive Data Table & Stacked Bar Analytics.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
  branchesData: Array,
  summary: Object,
  filters: Object,
  filterOptions: Object,
  userRole: String,
});

const selectedMonths = ref([]); // Array of string month numbers (e.g. ['1', '2', '3'])
const isMonthDropdownOpen = ref(false);
const selectedYear = ref(props.filters?.year || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedPrincipal = ref(props.filters?.principal || '');
const selectedBranch = ref(props.filters?.branch_id || '');

// Expanded state for branch accordions (all expanded by default)
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

const yearOptions = computed(() => {
  return (props.filterOptions?.years || [new Date().getFullYear()]).map((y) => ({
    value: String(y),
    label: `Tahun ${y}`,
  }));
});

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : r.region_code,
  }));
});

// Exact Cascading Filter: Entity based on selected Region
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

// Exact Cascading Filter: Branch based on selected Region & Entity
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

// Watcher to reset dependent options when parent region/entity changes
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

// Multi-select Month Helper Functions
function isMonthSelected(val) {
  return selectedMonths.value.includes(val);
}

function toggleMonth(val) {
  if (isMonthSelected(val)) {
    selectedMonths.value = selectedMonths.value.filter((m) => m !== val);
  } else {
    selectedMonths.value = [...selectedMonths.value, val];
  }
}

function toggleSelectAllMonths() {
  if (selectedMonths.value.length === 12) {
    selectedMonths.value = [];
  } else {
    selectedMonths.value = monthOptions.map((m) => m.value);
  }
}

function selectQuarter(q) {
  if (q === 1) selectedMonths.value = ['1', '2', '3'];
  else if (q === 2) selectedMonths.value = ['4', '5', '6'];
  else if (q === 3) selectedMonths.value = ['7', '8', '9'];
  else if (q === 4) selectedMonths.value = ['10', '11', '12'];
}

function selectSemester(s) {
  if (s === 1) selectedMonths.value = ['1', '2', '3', '4', '5', '6'];
  else if (s === 2) selectedMonths.value = ['7', '8', '9', '10', '11', '12'];
}

const selectedMonthsLabel = computed(() => {
  const count = selectedMonths.value.length;
  if (count === 0 || count === 12) return 'Semua Bulan';
  if (count === 1) {
    const found = monthOptions.find((m) => m.value === selectedMonths.value[0]);
    return found ? found.label : '1 Bulan';
  }
  return `${count} Bulan Terpilih`;
});

// Click Outside listener for Month Dropdown
const monthDropdownRef = ref(null);
function handleClickOutside(event) {
  if (monthDropdownRef.value && !monthDropdownRef.value.contains(event.target)) {
    isMonthDropdownOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  // Expand all branches by default
  if (props.branchesData) {
    props.branchesData.forEach((b) => {
      expandedBranches.value[b.branch_id] = true;
    });
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

function toggleBranchExpand(branchId) {
  expandedBranches.value[branchId] = !expandedBranches.value[branchId];
}

// Dynamic Computation for Branches & Salesmen with Multi-Month Filter Instant Recalculation
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

  const selMonths = selectedMonths.value;
  const hasMonthFilter = selMonths.length > 0 && selMonths.length < 12;
  const selY = selectedYear.value ? parseInt(selectedYear.value, 10) : null;

  return list.map((b) => {
    let branchApprovedRo = 0;
    let branchTargetRo = 0;
    let branchAchievedCount = 0;

    const salesmen = (b.salesmen || []).map((s) => {
      let approvedRo = s.approved_ro;

      if (hasMonthFilter || selY !== null) {
        const mStats = s.monthly_stats || [];
        approvedRo = mStats
          .filter((item) => {
            const matchM = !hasMonthFilter || selMonths.includes(String(item.month));
            const matchY = selY === null || item.year === selY;
            return matchM && matchY;
          })
          .reduce((sum, item) => sum + item.approved_count, 0);
      }

      const visitType = s.visit_type === 'F4' || s.visit_type === 'P4' ? 'F4' : 'F2';
      const targetRo = s.target_ro || (visitType === 'F4' ? 300 : 150);
      const percentage = targetRo > 0 ? roundOneDecimal((approvedRo / targetRo) * 100) : 0;
      const isAchieved = approvedRo >= targetRo;

      if (isAchieved) branchAchievedCount++;
      branchApprovedRo += approvedRo;
      branchTargetRo += targetRo;

      return {
        ...s,
        visit_type: visitType,
        approved_ro: approvedRo,
        target_ro: targetRo,
        percentage: percentage,
        is_achieved: isAchieved,
      };
    });

    return {
      ...b,
      total_approved_ro: branchApprovedRo,
      total_target_ro: branchTargetRo,
      achieved_salesmen_count: branchAchievedCount,
      total_salesmen_count: salesmen.length,
      salesmen: salesmen,
    };
  });
});

function roundOneDecimal(num) {
  return Math.round(num * 10) / 10;
}

// Dynamic Global Summary Metrics
const filteredSummary = computed(() => {
  const branches = filteredBranchesData.value;
  let totalSalesmen = 0;
  let totalAchieved = 0;
  let totalApprovedRo = 0;

  branches.forEach((b) => {
    totalSalesmen += b.total_salesmen_count || 0;
    totalAchieved += b.achieved_salesmen_count || 0;
    totalApprovedRo += b.total_approved_ro || 0;
  });

  return {
    total_branches: branches.length,
    total_salesmen: totalSalesmen,
    total_achieved: totalAchieved,
    total_approved_ro: totalApprovedRo,
  };
});

function resetFilters() {
  selectedMonths.value = [];
  selectedYear.value = '';
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
}
</script>

<template>
  <EdpLayout>
    <Head title="Monitoring Target RO Salesman - Portal Principal" />

    <div class="space-y-6">
      <!-- HEADER DASHBOARD TITLE & ANALYTICS TOP BAR -->
      <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-950 p-6 rounded-2xl border border-slate-700/60 shadow-lg text-white flex flex-col lg:flex-row lg:items-center justify-between gap-5 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
          <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
            <span>📈 Monitoring Target RO vs Realisasi Salesman</span>
          </h1>
          <p class="text-xs text-slate-300 font-medium max-w-2xl leading-relaxed">
            Visual Monitoring Registered Outlet (RO) Approved Principal vs Target Selamanya per Salesman (F2: 150 RO | F4: 300 RO) dengan Filter Multiselect Checkbox per Bulan / Kuartal / Semester.
          </p>
        </div>

        <!-- METRIC SUMMARY CARDS WITH SMOOTH TRANSITIONS -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 relative z-10 shrink-0">
          <div class="px-3.5 py-2.5 rounded-xl bg-white/10 border border-white/15 backdrop-blur-md text-center transition-all duration-300 hover:bg-white/20">
            <div class="text-[10.5px] uppercase font-bold tracking-wider text-slate-300">🏢 Active Branch</div>
            <div class="text-xl font-black text-white mt-0.5 transition-all duration-500">{{ filteredSummary.total_branches }}</div>
          </div>
          <div class="px-3.5 py-2.5 rounded-xl bg-white/10 border border-white/15 backdrop-blur-md text-center transition-all duration-300 hover:bg-white/20">
            <div class="text-[10.5px] uppercase font-bold tracking-wider text-purple-200">👔 Salesmen</div>
            <div class="text-xl font-black text-purple-300 mt-0.5 transition-all duration-500">{{ filteredSummary.total_salesmen }}</div>
          </div>
          <div class="px-3.5 py-2.5 rounded-xl bg-emerald-500/20 border border-emerald-400/30 backdrop-blur-md text-center transition-all duration-300 hover:bg-emerald-500/30">
            <div class="text-[10.5px] uppercase font-bold tracking-wider text-emerald-200">🎯 Achieved</div>
            <div class="text-xl font-black text-emerald-300 mt-0.5 transition-all duration-500">{{ filteredSummary.total_achieved }}</div>
          </div>
          <div class="px-3.5 py-2.5 rounded-xl bg-amber-500/20 border border-amber-400/30 backdrop-blur-md text-center transition-all duration-300 hover:bg-amber-500/30">
            <div class="text-[10.5px] uppercase font-bold tracking-wider text-amber-200">🏪 Approved RO</div>
            <div class="text-xl font-black text-amber-300 mt-0.5 transition-all duration-500">{{ filteredSummary.total_approved_ro }}</div>
          </div>
        </div>
      </div>

      <!-- FILTER BAR BERTINGKAT & MULTISELECT CHECKBOX BULAN -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
            🔍 Filter Bertingkat & Multiselect Monitoring RO
          </span>
          <button
            @click="resetFilters"
            class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer flex items-center gap-1"
          >
            <span>🔄 Reset Filter</span>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
          <!-- FILTER BULAN APPROVAL (MULTISELECT CHECKBOX DROPDOWN) -->
          <div class="relative" ref="monthDropdownRef">
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Bulan Approved EDP:</label>
            
            <button
              type="button"
              @click="isMonthDropdownOpen = !isMonthDropdownOpen"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-white font-semibold text-slate-800 flex items-center justify-between shadow-2xs hover:border-blue-500 focus:outline-none transition cursor-pointer"
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

              <!-- CHECKBOX LIST (GRID 2 COLUMNS) -->
              <div class="max-h-48 overflow-y-auto custom-scrollbar pr-1 grid grid-cols-2 gap-1">
                <label
                  v-for="m in monthOptions"
                  :key="m.value"
                  class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-slate-700 font-medium transition"
                >
                  <input
                    type="checkbox"
                    :value="m.value"
                    :checked="isMonthSelected(m.value)"
                    @change="toggleMonth(m.value)"
                    class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer"
                  />
                  <span>{{ m.label }}</span>
                </label>
              </div>

              <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ selectedMonths.length === 0 || selectedMonths.length === 12 ? 'Menampilkan semua bulan' : `${selectedMonths.length} bulan terpilih` }}</span>
                <button
                  type="button"
                  @click="isMonthDropdownOpen = false"
                  class="px-2.5 py-1 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 transition cursor-pointer"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>

          <!-- Filter Tahun -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Tahun Approved EDP:</label>
            <select
              v-model="selectedYear"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white font-medium shadow-2xs"
            >
              <option value="">Semua Tahun</option>
              <option v-for="y in yearOptions" :key="y.value" :value="y.value">
                {{ y.label }}
              </option>
            </select>
          </div>

          <!-- Filter Region (Parent 1) -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">1. Wilayah / Region:</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="Semua Region"
            />
          </div>

          <!-- Filter Entity (Child 1 - Bertingkat dari Region) -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">2. Entity Principal:</label>
            <SearchableSelect
              v-model="selectedPrincipal"
              :options="entityOptions"
              placeholder="Semua Entity"
            />
          </div>

          <!-- Filter Branch (Child 2 - Bertingkat dari Region & Entity) -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">3. Cabang / Branch:</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="Semua Cabang"
            />
          </div>
        </div>
      </div>

      <!-- VISUAL GRADIENT PILL BAR-IN-BAR STACKED CHART SECTION -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5 transition-all duration-500">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 gap-3">
          <div>
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
              <span>📊 Stacked Bar Chart &bull; Vertical Analytics (Model Bar in Bar)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Model Bar Overlapping: Target RO Selamanya (Background Pill Track) & Actual Realisasi Salesman (Gradient Active Pill Bar).
            </p>
          </div>

          <!-- SLEEK MODERN CHART LEGENDS -->
          <div class="flex items-center gap-3 text-xs font-semibold flex-wrap">
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 border border-slate-200">
              <span class="w-3.5 h-3.5 rounded-full bg-slate-200 border border-slate-300"></span>
              <span>Target RO (F2: 150 / F4: 300)</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 border border-blue-200">
              <span class="w-3.5 h-3.5 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600"></span>
              <span>Actuals Realisasi</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold shadow-2xs">
              <span class="w-3.5 h-3.5 rounded-full bg-gradient-to-r from-emerald-500 to-teal-400"></span>
              <span>🎉 Achieved (&ge; 100%)</span>
            </div>
          </div>
        </div>

        <!-- SCROLLABLE CHART HOLDER WITH X-AXIS SCALE & Y-AXIS BRANCH LABELS -->
        <div v-if="filteredBranchesData.length === 0" class="py-16 text-center text-xs text-slate-400 italic">
          Tidak ada data cabang / salesman untuk grafik pada filter terpilih.
        </div>

        <div v-else class="space-y-3">
          <!-- TOP X-AXIS NUMERICAL RO SCALE TICKS -->
          <div class="flex items-center justify-between pl-48 sm:pl-56 pr-4 text-[11px] font-bold text-slate-400 border-b border-slate-100 pb-2 font-mono">
            <span>0</span>
            <span>50</span>
            <span>100</span>
            <span>150</span>
            <span>200</span>
            <span>250</span>
            <span>300 RO</span>
          </div>

          <!-- SCROLLABLE BAR CONTAINER (FIXED HEIGHT MAX WITH OVERFLOW-Y AUTO & COMPACT BRANCH SPACING) -->
          <div class="max-h-[480px] overflow-y-auto pr-2 space-y-5 custom-scrollbar">
            <TransitionGroup name="swap-anim" tag="div" class="space-y-5">
              <!-- BRANCH GROUP -->
              <div
                v-for="b in filteredBranchesData"
                :key="b.branch_id"
                class="space-y-2.5 transition-all duration-500"
              >
                <!-- Y-AXIS COMPACT BRANCH HEADER SEPARATOR -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-1.5 pt-1.5 bg-slate-50/90 px-3.5 rounded-xl border-l-4 border-l-blue-600">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-tight">🏢 {{ b.branch_name }}</span>
                    <span class="text-[10px] font-mono font-bold bg-blue-100 text-blue-800 px-1.5 py-0.2 rounded border border-blue-300">
                      {{ b.branch_id }}
                    </span>
                  </div>
                  <div class="text-[10.5px] font-semibold text-slate-600">
                    Approved: <strong class="text-blue-700 font-bold">{{ b.total_approved_ro }}</strong> RO &bull; Salesman: <strong>{{ b.total_salesmen_count }}</strong>
                  </div>
                </div>

                <!-- SALESMEN BAR-IN-BAR OVERLAPPING BARS LIST -->
                <div v-if="!b.salesmen || b.salesmen.length === 0" class="py-1 pl-4 text-[11px] text-slate-400 italic">
                  Belum ada salesman terdaftar di cabang ini.
                </div>

                <div v-else class="space-y-2.5 pl-1">
                  <div
                    v-for="s in b.salesmen"
                    :key="s.salesman_code"
                    class="flex items-center gap-3 text-xs group transition-all duration-300 hover:bg-blue-50/40 p-1.5 rounded-xl"
                  >
                    <!-- Y-AXIS SALESMAN LABEL (LEFT HOLDER) -->
                    <div class="w-44 sm:w-52 shrink-0 font-medium text-slate-800 truncate pr-2 text-right">
                      <div class="font-bold text-slate-900 truncate leading-tight group-hover:text-blue-700 transition-colors">{{ s.salesman_name }}</div>
                      <div class="text-[10px] font-mono text-slate-500 font-semibold leading-tight">({{ s.salesman_code }}) &bull; {{ s.visit_type }}</div>
                    </div>

                    <!-- GRAPH HOLDER: BAR IN BAR -->
                    <div class="flex-1 relative h-7 bg-slate-50 rounded-full border border-slate-200/80 flex items-center shadow-2xs group/bar">
                      <!-- BACKGROUND VERTICAL GRID LINES (0 - 300) -->
                      <div class="absolute inset-0 flex justify-between pointer-events-none opacity-20 px-0.5">
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                        <div class="w-px bg-slate-400 h-full"></div>
                      </div>

                      <!-- OUTER TARGET RO PILL TRACK -->
                      <div
                        class="absolute left-0 top-0.5 bottom-0.5 bg-slate-200/80 border border-slate-300/80 rounded-full transition-all duration-700 ease-out origin-left shadow-2xs"
                        :style="{ width: `${Math.min(100, (s.target_ro / 300) * 100)}%` }"
                      ></div>

                      <!-- INNER GRADIENT ACTIVE PILL BAR -->
                      <div
                        class="absolute left-0 top-1 bottom-1 rounded-full transition-all duration-700 ease-out flex items-center justify-end px-2.5 text-[10.5px] font-extrabold text-white shadow-sm origin-left group-hover/bar:brightness-110"
                        :class="s.is_achieved ? 'bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 border border-emerald-400 shadow-emerald-500/30' : 'bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 border border-blue-500 shadow-blue-500/20'"
                        :style="{ width: `${Math.min(100, (s.approved_ro / 300) * 100)}%` }"
                      >
                        <span v-if="s.approved_ro > 0" class="truncate font-mono drop-shadow-xs">{{ s.approved_ro }} RO</span>
                      </div>

                      <!-- VALUE METRIC TEXT RIGHT SIDE -->
                      <div class="absolute right-3 text-[11px] font-bold z-10 pointer-events-none">
                        <span :class="s.is_achieved ? 'text-emerald-700 font-black' : 'text-slate-700'">
                          {{ s.approved_ro }} / {{ s.target_ro }} RO ({{ s.percentage }}%)
                        </span>
                      </div>

                      <!-- TOOLTIP CARD ON HOVER -->
                      <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/bar:flex flex-col bg-slate-900 text-white text-[11px] px-3 py-1.5 rounded-xl shadow-xl border border-slate-700 z-50 whitespace-nowrap pointer-events-none transition-all">
                        <div class="font-bold text-blue-300 flex items-center gap-1.5">
                          <span>👔 {{ s.salesman_name }}</span>
                          <span class="font-mono text-[9.5px] text-slate-400">({{ s.salesman_code }})</span>
                        </div>
                        <div class="text-[10px] text-slate-300 mt-0.5">
                          Target: <strong>{{ s.target_ro }} RO</strong> ({{ s.visit_type }}) &bull; Realisasi: <strong>{{ s.approved_ro }} RO</strong> ({{ s.percentage }}%)
                        </div>
                        <div class="text-[9.5px] font-bold mt-0.5" :class="s.is_achieved ? 'text-emerald-400' : 'text-amber-400'">
                          {{ s.is_achieved ? '🎉 Achieved / Target Terpenuhi' : `Sisa Target: ${s.target_ro - s.approved_ro} RO` }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TransitionGroup>
          </div>

          <!-- BOTTOM X-AXIS NUMERICAL RO SCALE TICKS -->
          <div class="flex items-center justify-between pl-48 sm:pl-56 pr-4 text-[11px] font-bold text-slate-400 border-t border-slate-100 pt-2.5 font-mono">
            <span>0</span>
            <span>50</span>
            <span>100</span>
            <span>150</span>
            <span>200</span>
            <span>250</span>
            <span>300 RO</span>
          </div>
        </div>
      </div>

      <!-- REDESIGNED EXECUTIVE DATA TABLE SECTION -->
      <div class="space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2 px-1">
          <div>
            <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
              <span>📋 Tabel Rekapitulasi Realisasi & Target RO Salesman</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Rincian data per Cabang & Salesman beserta status keaktifan RO & progres persentase.
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
                    <h3 class="text-sm font-black text-white tracking-tight">🏢 {{ b.branch_name }}</h3>
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
                    Approved RO: <strong class="text-amber-300 font-bold">{{ b.total_approved_ro }}</strong> / {{ b.total_target_ro }}
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
                      <th class="py-3 px-4 w-60">👔 Salesman</th>
                      <th class="py-3 px-3 text-center w-36">🔄 Tipe Kunjungan</th>
                      <th class="py-3 px-3 text-center w-28">🎯 Target RO</th>
                      <th class="py-3 px-3 text-center w-32">🏪 Approved RO</th>
                      <th class="py-3 px-4 w-64">📊 Progres & Pencapaian %</th>
                      <th class="py-3 px-4 text-center w-36">🏷️ Status Target</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-xs">
                    <tr
                      v-for="s in b.salesmen"
                      :key="s.salesman_code"
                      class="hover:bg-blue-50/40 transition duration-150"
                      :class="s.is_achieved ? 'bg-emerald-50/20' : ''"
                    >
                      <!-- SALESMAN NAME & CODE -->
                      <td class="py-3.5 px-4 font-semibold text-slate-900">
                        <div class="font-bold text-slate-900 text-[13px] flex items-center gap-2">
                          <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0">
                            👔
                          </span>
                          <span class="truncate">{{ s.salesman_name }}</span>
                        </div>
                        <div class="text-[10.5px] font-mono text-slate-500 pl-8 mt-0.5">
                          Kode: {{ s.salesman_code }}
                        </div>
                      </td>

                      <!-- VISIT TYPE (F2 / F4) -->
                      <td class="py-3.5 px-3 text-center">
                        <span
                          class="px-2.5 py-1 text-[11px] font-black rounded-lg border inline-flex items-center gap-1 shadow-2xs"
                          :class="s.visit_type === 'F4' ? 'bg-indigo-50 text-indigo-800 border-indigo-200' : 'bg-purple-50 text-purple-800 border-purple-200'"
                        >
                          <span>{{ s.visit_type === 'F4' ? '🗓️ F4' : '🗓️ F2' }}</span>
                          <span class="text-[10px] font-normal opacity-80">({{ s.visit_type === 'F4' ? 'Mingguan' : '2-Mingguan' }})</span>
                        </span>
                      </td>

                      <!-- TARGET RO -->
                      <td class="py-3.5 px-3 text-center font-mono font-extrabold text-slate-800 text-[13px]">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                          {{ s.target_ro }} RO
                        </span>
                      </td>

                      <!-- APPROVED RO -->
                      <td class="py-3.5 px-3 text-center">
                        <span
                          class="px-3 py-1 font-mono font-black text-[13.5px] rounded-lg border shadow-2xs inline-flex items-center gap-1.5"
                          :class="s.is_achieved ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-blue-50 text-blue-900 border-blue-200'"
                        >
                          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                          {{ s.approved_ro }} RO
                        </span>
                      </td>

                      <!-- PROGRESS BAR & PERCENTAGE -->
                      <td class="py-3.5 px-4">
                        <div class="space-y-1">
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
                      </td>

                      <!-- STATUS BADGE -->
                      <td class="py-3.5 px-4 text-center">
                        <span
                          v-if="s.is_achieved"
                          class="px-3 py-1 text-[11px] font-black rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs inline-flex items-center gap-1"
                        >
                          🎉 Achieved
                        </span>
                        <span
                          v-else
                          class="px-3 py-1 text-[11px] font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200 inline-flex items-center gap-1"
                        >
                          ⏳ Sisa {{ s.target_ro - s.approved_ro }} RO
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

/* Swapped Smooth Animations for Filter Changes */
.swap-anim-enter-active {
  transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.swap-anim-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 1, 1);
}
.swap-anim-enter-from {
  opacity: 0;
  transform: scaleX(0.95) translateY(-6px);
}
.swap-anim-leave-to {
  opacity: 0;
  transform: scaleX(0.98) translateY(6px);
}
</style>
