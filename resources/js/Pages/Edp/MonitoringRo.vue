<script setup lang="js">
/**
 * Halaman Khusus Monitoring Target RO vs Realisasi Approved Salesman.
 * - Exact Cascading Filter (Region -> Entity -> Branch).
 * - Instant Client-Side Reactive Month & Year Filtering.
 * - Horizontal Bar-in-Bar Overlapping Chart Design (Custom Non-Green Target, Non-Black Actuals, Swap Animations).
 */
import { ref, computed, watch } from 'vue';
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

const selectedMonth = ref(props.filters?.month || '');
const selectedYear = ref(props.filters?.year || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedPrincipal = ref(props.filters?.principal || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const monthOptions = [
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

// 1. EXACT CASCADING FILTER: Entity hanya memuat entity yang ada di Region terpilih
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

// 1. EXACT CASCADING FILTER: Branch hanya memuat branch yang ada di Region/Entity terpilih
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

// Watcher untuk mereset opsi turunan ketika parent region/entity berubah
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

// Dynamic Computation for Branches & Salesmen with Filter Instant & Monthly Recalculation
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

  // Recalculate Salesmen Stats based on selected Month & Year
  const selM = selectedMonth.value ? parseInt(selectedMonth.value, 10) : null;
  const selY = selectedYear.value ? parseInt(selectedYear.value, 10) : null;

  return list.map((b) => {
    let branchApprovedRo = 0;
    let branchTargetRo = 0;
    let branchAchievedCount = 0;

    const salesmen = (b.salesmen || []).map((s) => {
      let approvedRo = s.approved_ro;

      if (selM !== null || selY !== null) {
        const mStats = s.monthly_stats || [];
        approvedRo = mStats
          .filter((item) => (selM === null || item.month === selM) && (selY === null || item.year === selY))
          .reduce((sum, item) => sum + item.approved_count, 0);
      }

      const targetRo = s.target_ro || (s.visit_type === 'P4' ? 300 : 150);
      const percentage = targetRo > 0 ? roundOneDecimal((approvedRo / targetRo) * 100) : 0;
      const isAchieved = approvedRo >= targetRo;

      if (isAchieved) branchAchievedCount++;
      branchApprovedRo += approvedRo;
      branchTargetRo += targetRo;

      return {
        ...s,
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
  selectedMonth.value = '';
  selectedYear.value = '';
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
}
</script>

<template>
  <EdpLayout>
    <Head title="Monitoring RO - Portal NOO+" />

    <div class="space-y-6">
      <!-- HEADER DASHBOARD TITLE & ANALYTICS TOP BAR -->
      <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-950 p-6 rounded-2xl border border-slate-700/60 shadow-lg text-white flex flex-col lg:flex-row lg:items-center justify-between gap-5 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
          <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
            <span>📈 Monitoring Target RO vs Realisasi Salesman</span>
          </h1>
          <p class="text-xs text-slate-300 font-medium max-w-2xl leading-relaxed">
            Visual Monitoring Registered Outlet (RO) Approved Principal vs Target Bulanan per Salesman (P2: 150 RO | P4: 300 RO) dengan Filter Bertingkat Instant & Chart Bar-in-Bar Model.
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

      <!-- FILTER BAR BERTINGKAT (CASCADE REGION -> ENTITY -> BRANCH) -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
            🔍 Filter Bertingkat Monitoring RO (Instant Client-Side)
          </span>
          <button
            @click="resetFilters"
            class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer flex items-center gap-1"
          >
            <span>🔄 Reset Filter</span>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
          <!-- Filter Bulan -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Bulan Pengajuan:</label>
            <select
              v-model="selectedMonth"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white font-medium shadow-2xs"
            >
              <option value="">Semua Bulan</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                {{ m.label }}
              </option>
            </select>
          </div>

          <!-- Filter Tahun -->
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Tahun Pengajuan:</label>
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

      <!-- VISUAL BAR-IN-BAR STACKED CHART SECTION -->
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4 transition-all duration-500">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-3">
          <div>
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
              <span>📊 Stacked Bar Chart &bull; Vertical Analytics (Model Bar in Bar)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Model Bar Overlapping: Sumbu X berisi Scale RO Target & Actuals, Sumbu Y berisi Cabang & Kode Salesman.
            </p>
          </div>

          <!-- CLEAN CHART LEGEND BADGES (WITHOUT COLOR DESCRIPTION TEXT) -->
          <div class="flex items-center gap-3 text-xs font-semibold flex-wrap">
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-100 text-slate-800 border border-slate-300">
              <span class="w-3.5 h-3.5 rounded-xs bg-slate-200 border border-slate-300"></span>
              <span>Target RO</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg bg-blue-900 text-white border border-blue-800">
              <span class="w-3.5 h-3.5 rounded-xs bg-blue-600 border border-blue-500"></span>
              <span>Actuals Realisasi</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold shadow-xs">
              <span class="w-3.5 h-3.5 rounded-xs bg-emerald-500 border border-white"></span>
              <span>🎉 Achieved (&ge; 100%)</span>
            </div>
          </div>
        </div>

        <!-- SCROLLABLE CHART HOLDER WITH X-AXIS SCALE & Y-AXIS BRANCH LABELS -->
        <div v-if="filteredBranchesData.length === 0" class="py-16 text-center text-xs text-slate-400 italic">
          Tidak ada data cabang / salesman untuk grafik pada filter terpilih.
        </div>

        <div v-else class="space-y-2">
          <!-- TOP X-AXIS NUMERICAL RO SCALE TICKS -->
          <div class="flex items-center justify-between pl-52 pr-4 text-[11px] font-bold text-slate-400 border-b border-slate-200 pb-1.5 font-mono">
            <span>0</span>
            <span>50</span>
            <span>100</span>
            <span>150</span>
            <span>200</span>
            <span>250</span>
            <span>300 RO</span>
          </div>

          <!-- SCROLLABLE BAR CONTAINER (FIXED HEIGHT MAX WITH OVERFLOW-Y AUTO & COMPACT BRANCH SPACING) -->
          <div class="max-h-[520px] overflow-y-auto pr-3 space-y-4 custom-scrollbar">
            <TransitionGroup name="swap-anim" tag="div" class="space-y-4">
              <!-- BRANCH GROUP (COMPACT SEAMLESS CONTINUOUS LAYOUT) -->
              <div
                v-for="b in filteredBranchesData"
                :key="b.branch_id"
                class="space-y-2 transition-all duration-500"
              >
                <!-- Y-AXIS COMPACT BRANCH HEADER SEPARATOR -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-1 pt-1 bg-slate-50/90 px-3 rounded-lg border-l-4 border-l-blue-600">
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

                <div v-else class="space-y-2 pl-2">
                  <div
                    v-for="s in b.salesmen"
                    :key="s.salesman_code"
                    class="flex items-center gap-3 text-xs group transition-all duration-500 hover:bg-slate-50/70 p-1 rounded-md"
                  >
                    <!-- Y-AXIS SALESMAN LABEL (LEFT HOLDER) -->
                    <div class="w-48 shrink-0 font-medium text-slate-800 truncate pr-2 text-right">
                      <div class="font-bold text-slate-900 truncate leading-tight">{{ s.salesman_name }}</div>
                      <div class="text-[10px] font-mono text-slate-500 font-semibold leading-tight">({{ s.salesman_code }}) &bull; {{ s.visit_type }}</div>
                    </div>

                    <!-- GRAPH HOLDER: BAR IN BAR (Silver/Slate Target Outer + Royal Blue/Emerald Inner) -->
                    <div class="flex-1 relative h-6 bg-slate-100/90 rounded-md overflow-hidden border border-slate-200 flex items-center shadow-2xs">
                      <!-- BACKGROUND VERTICAL GRID LINES (50, 100, 150, 200, 250, 300) -->
                      <div class="absolute inset-0 flex justify-between pointer-events-none opacity-25">
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                        <div class="w-px bg-slate-300 h-full"></div>
                      </div>

                      <!-- OUTER SILVER SLATE TARGET BAR (NON-GREEN) -->
                      <div
                        class="absolute left-0 top-0 bottom-0 bg-slate-200 border border-slate-300 rounded-md transition-all duration-700 ease-out origin-left"
                        :style="{ width: `${Math.min(100, (s.target_ro / 300) * 100)}%` }"
                      ></div>

                      <!-- INNER ROYAL BLUE / EMERALD ACTUALS BAR (NON-BLACK) -->
                      <div
                        class="absolute left-0 top-0.5 bottom-0.5 rounded-xs transition-all duration-700 ease-out flex items-center justify-end px-2 text-[10px] font-bold text-white shadow-xs origin-left"
                        :class="s.is_achieved ? 'bg-emerald-600 border border-emerald-500 shadow-emerald-500/30' : 'bg-blue-600 border border-blue-500'"
                        :style="{ width: `${Math.min(100, (s.approved_ro / 300) * 100)}%` }"
                      >
                        <span v-if="s.approved_ro > 0" class="truncate">{{ s.approved_ro }} RO</span>
                      </div>

                      <!-- FLOATING VALUE DISPLAY ON RIGHT IF ACTUALS IS ZERO OR SMALL -->
                      <div class="absolute right-2 text-[10.5px] font-bold z-10">
                        <span :class="s.is_achieved ? 'text-emerald-800 font-black' : 'text-slate-700'">
                          {{ s.approved_ro }} / {{ s.target_ro }} RO ({{ s.percentage }}%)
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TransitionGroup>
          </div>

          <!-- BOTTOM X-AXIS NUMERICAL RO SCALE TICKS -->
          <div class="flex items-center justify-between pl-52 pr-4 text-[11px] font-bold text-slate-400 border-t border-slate-200 pt-2 font-mono">
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

      <!-- MAIN BRANCHES DATA & SALESMAN EXPLORER LIST (EXCLUDES INACTIVE BRANCHES) -->
      <div v-if="!filteredBranchesData || filteredBranchesData.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center text-xs text-slate-400 italic shadow-xs">
        Belum ada data cabang aktif yang terdaftar pada filter terpilih.
      </div>

      <div v-else class="space-y-6">
        <div
          v-for="b in filteredBranchesData"
          :key="b.branch_id"
          class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition-all duration-500 hover:shadow-md space-y-4"
        >
          <!-- BRANCH HEADER CARD -->
          <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-950 p-4 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center font-bold text-base shrink-0 shadow-inner">
                🏢
              </div>
              <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2 flex-wrap">
                  <span>{{ b.branch_name }}</span>
                  <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-blue-500/30 border border-blue-400/40 text-blue-200">
                    {{ b.branch_id }}
                  </span>
                </h3>
                <div class="text-[11px] text-slate-300 mt-0.5 flex items-center gap-3 flex-wrap">
                  <span v-if="b.region_name || b.region_code">📍 Region: <strong>{{ b.region_name || b.region_code }}</strong></span>
                  <span v-if="b.entity_name_principal || b.entity_code_principal">&bull; Entity: <strong>{{ b.entity_name_principal || b.entity_code_principal }}</strong></span>
                </div>
              </div>
            </div>

            <!-- Branch Summary Badges -->
            <div class="flex items-center gap-2.5 flex-wrap shrink-0">
              <span class="text-xs font-semibold px-3 py-1 rounded-lg bg-white/15 border border-white/20 text-white">
                🏪 Approved: <strong>{{ b.total_approved_ro }}</strong> RO
              </span>
              <span class="text-xs font-semibold px-3 py-1 rounded-lg bg-emerald-500/20 border border-emerald-400/40 text-emerald-200">
                👔 Salesman: <strong>{{ b.total_salesmen_count }}</strong> Orang (Achieved: <strong>{{ b.achieved_salesmen_count }}</strong>)
              </span>
            </div>
          </div>

          <!-- BRANCH COVERED SALESMEN SUB-LIST -->
          <div class="p-4 pt-0">
            <div v-if="!b.salesmen || b.salesmen.length === 0" class="p-6 bg-slate-50/70 rounded-xl border border-dashed border-slate-300 text-center text-xs text-slate-400 italic">
              Belum ada salesman terdaftar di cabang <strong>{{ b.branch_name }}</strong>.
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
              <div
                v-for="s in b.salesmen"
                :key="s.salesman_code"
                class="p-3.5 rounded-xl border transition-all duration-500 relative group cursor-pointer"
                :class="s.is_achieved ? 'bg-emerald-50/30 border-emerald-200 hover:bg-emerald-50/60 hover:border-emerald-300 shadow-2xs' : 'bg-slate-50/60 border-slate-200 hover:bg-rose-50/30 hover:border-rose-300 shadow-2xs'"
              >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs"
                      :class="s.is_achieved ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-slate-200 text-slate-700'"
                    >
                      👔
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5 flex-wrap">
                        <span>{{ s.salesman_name }}</span>
                        <span class="font-mono text-[10.5px] font-semibold text-slate-500">({{ s.salesman_code }})</span>
                      </div>
                      <div class="text-[10.5px] text-slate-500 mt-0.5 flex items-center gap-2">
                        <span
                          class="px-1.5 py-0.5 text-[9.5px] font-bold rounded border uppercase tracking-wider"
                          :class="s.visit_type === 'P4' ? 'bg-indigo-100 text-indigo-800 border-indigo-300' : 'bg-purple-100 text-purple-800 border-purple-300'"
                        >
                          Periode {{ s.visit_type }} (Target: {{ s.target_ro }} RO)
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs font-black" :class="s.is_achieved ? 'text-emerald-700' : 'text-slate-700'">
                      {{ s.percentage }}%
                    </span>
                    <span v-if="s.is_achieved" class="text-[10px] px-2 py-0.5 rounded font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                      🎉 Achieved
                    </span>
                  </div>
                </div>

                <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden mt-2 relative">
                  <div
                    class="h-full rounded-full transition-all duration-700 ease-in-out"
                    :class="s.is_achieved ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : (s.percentage > 50 ? 'bg-gradient-to-r from-amber-400 to-amber-500' : 'bg-gradient-to-r from-rose-500 to-rose-400')"
                    :style="{ width: Math.min(s.percentage, 100) + '%' }"
                  ></div>
                </div>

                <div class="flex items-center justify-between text-[10.5px] text-slate-600 mt-2">
                  <span>Approved RO: <strong>{{ s.approved_ro }}</strong> / {{ s.target_ro }} Toko</span>
                  <span v-if="!s.is_achieved" class="text-rose-600 font-semibold">Sisa: {{ s.target_ro - s.approved_ro }} RO</span>
                  <span v-else class="text-emerald-700 font-semibold">Tercapai! 👍</span>
                </div>
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
