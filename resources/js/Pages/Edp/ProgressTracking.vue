<script setup lang="js">
/**
 * Halaman Progress Tracking Submisi NOO & Reset Inputan Admin / SPV / EDP.
 * Menampilkan Vertical Stepper Timeline modern dan kontrol reset bertingkat.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  submissions: Object,
  metrics: Object,
  userRole: String,
  canReset: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedBranch = ref(props.filters?.branch_id || '');
const selectedStage = ref(props.filters?.stage || 'all');

const activeSubmissionModal = ref(null);
const resetModalState = ref({
  isOpen: false,
  type: null, // 'ADMIN' | 'SPV' | 'EDP'
  submission: null,
  reason: '',
});

const resetForm = useForm({
  request_id: '',
  reason: '',
});

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : r.region_code,
  }));
});

const branchOptions = computed(() => {
  let list = props.filterOptions?.branches || [];
  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }
  return list.map((b) => ({
    value: b.branch_id,
    label: `${b.branch_id} - ${b.branch_name}`,
  }));
});

function applyFilters() {
  router.get(
    route('edp.progress_tracking'),
    {
      search: search.value,
      region_code: selectedRegion.value,
      branch_id: selectedBranch.value,
      stage: selectedStage.value,
    },
    { preserveState: true, replace: true }
  );
}

function setStageFilter(stage) {
  selectedStage.value = stage;
  applyFilters();
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedBranch.value = '';
  selectedStage.value = 'all';
  applyFilters();
}

function openResetModal(type, sub) {
  resetModalState.value = {
    isOpen: true,
    type,
    submission: sub,
    reason: '',
  };
  resetForm.request_id = sub.request_id;
  resetForm.reason = '';
}

function closeResetModal() {
  resetModalState.value.isOpen = false;
  resetModalState.value.submission = null;
}

function submitReset() {
  if (!resetModalState.value.submission) return;
  let targetRoute = '';
  if (resetModalState.value.type === 'ADMIN') {
    targetRoute = route('edp.reset_admin_input');
  } else if (resetModalState.value.type === 'SPV') {
    targetRoute = route('edp.reset_spv_input');
  } else if (resetModalState.value.type === 'EDP') {
    targetRoute = route('edp.reset_edp_approval');
  }

  resetForm.post(targetRoute, {
    onSuccess: () => {
      closeResetModal();
      if (activeSubmissionModal.value) {
        // Refresh active submission in modal
        const reqId = activeSubmissionModal.value.request_id;
        const updated = (props.submissions?.data || []).find((s) => s.request_id === reqId);
        activeSubmissionModal.value = updated || null;
      }
    },
  });
}

function formatDatetime(dt) {
  if (!dt) return '-';
  const d = new Date(dt);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Helper format Hari JKS (H1 - H7)
 * Menampilkan HANYA hari yang bernilai 'Y'
 */
function formatJksDays(item) {
  if (!item) return '-';
  const days = [];
  if (item.h1 === 'Y') days.push('Senin');
  if (item.h2 === 'Y') days.push('Selasa');
  if (item.h3 === 'Y') days.push('Rabu');
  if (item.h4 === 'Y') days.push('Kamis');
  if (item.h5 === 'Y') days.push('Jumat');
  if (item.h6 === 'Y') days.push('Sabtu');
  if (item.h7 === 'Y') days.push('Minggu');
  return days.length > 0 ? days.join(', ') : '-';
}

/**
 * Helper format Minggu JKS (M1 - M4)
 * - YYYY = F4 / All Week
 * - YTYT = F2 / Minggu Ganjil
 * - TYTY = F2 / Minggu Genap
 */
function formatJksWeeks(item) {
  if (!item) return '-';
  const m1 = item.m1 === 'Y' ? 'Y' : 'T';
  const m2 = item.m2 === 'Y' ? 'Y' : 'T';
  const m3 = item.m3 === 'Y' ? 'Y' : 'T';
  const m4 = item.m4 === 'Y' ? 'Y' : 'T';
  const pattern = `${m1}${m2}${m3}${m4}`;

  if (pattern === 'YYYY') {
    return 'F4 / All Week';
  } else if (pattern === 'YTYT') {
    return 'F2 / Minggu Ganjil';
  } else if (pattern === 'TYTY') {
    return 'F2 / Minggu Genap';
  } else {
    const weeks = [];
    if (m1 === 'Y') weeks.push('M1');
    if (m2 === 'Y') weeks.push('M2');
    if (m3 === 'Y') weeks.push('M3');
    if (m4 === 'Y') weeks.push('M4');
    return weeks.length > 0 ? `P${weeks.length} (${weeks.join(', ')})` : '-';
  }
}

function isRejectedAdmin(item) {
  if (!item) return false;
  return item.status === 'ADMIN_REJECTED' || item.status === 'REJECTED_ADMIN';
}

function isRejectedSpv(item) {
  if (!item) return false;
  return item.status === 'SPV_REJECTED' || item.status === 'REJECTED_SPV';
}

function isRejectedEdp(item) {
  if (!item) return false;
  return item.status === 'EDP_REJECTED' || item.status === 'REJECTED_EDP' || item.status === 'REJECTED';
}

function isItemRejected(item) {
  if (!item) return false;
  return isRejectedAdmin(item) || isRejectedSpv(item) || isRejectedEdp(item);
}

const sortKey = ref('created_at');
const sortDir = ref('desc');

function handleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
}

const sortedSubmissions = computed(() => {
  const rawList = props.submissions?.data || props.submissions || [];
  const list = [...rawList];
  if (!sortKey.value) return list;

  return list.sort((a, b) => {
    let valA = a[sortKey.value] ?? '';
    let valB = b[sortKey.value] ?? '';

    if (['submitted_at', 'created_at', 'pushed_to_spv_at', 'pushed_to_edp_at'].includes(sortKey.value)) {
      valA = valA ? new Date(valA).getTime() : 0;
      valB = valB ? new Date(valB).getTime() : 0;
    } else if (typeof valA === 'string') {
      valA = valA.toLowerCase();
      valB = valB.toLowerCase();
    }

    if (valA < valB) return sortDir.value === 'asc' ? -1 : 1;
    if (valA > valB) return sortDir.value === 'asc' ? 1 : -1;
    return 0;
  });
});
</script>

<template>
  <EdpLayout>
    <Head title="Progress Tracking NOO - Portal NOO+" />

    <div class="space-y-6">
      <!-- HEADER METRICS DASHBOARD -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>Monitoring Progress Workflow NOO</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Lacak posisi submisi toko & lakukan reset inputan Admin/SPV/EDP sesuai tahapan workflow.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <span class="px-3 py-1.5 text-xs font-bold rounded-lg border bg-blue-50 text-blue-700 border-blue-200">
            Role Access: {{ userRole }}
          </span>
        </div>
      </div>

      <!-- METRIC CARDS INTERAKTIF -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- CARD TOTAL -->
        <button
          @click="setStageFilter('all')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'all' ? 'bg-blue-600 text-white border-blue-600 ring-2 ring-blue-400' : 'bg-white text-gray-800 border-gray-200 hover:border-blue-300'
          ]"
        >
          <div class="text-2xl font-black">{{ metrics?.total || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'all' ? 'text-blue-100' : 'text-gray-500'">Total NOO</div>
        </button>

        <!-- ADMIN BELUM MEMPROSES -->
        <button
          @click="setStageFilter('stuck_admin')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'stuck_admin' ? 'bg-amber-600 text-white border-amber-600 ring-2 ring-amber-400' : 'bg-white text-gray-800 border-gray-200 hover:border-amber-300'
          ]"
        >
          <div class="text-2xl font-black text-amber-600" :class="{ 'text-white': selectedStage === 'stuck_admin' }">{{ metrics?.stuckAdmin || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'stuck_admin' ? 'text-amber-100' : 'text-amber-700'">Admin Belum Memproses</div>
        </button>

        <!-- SPV BELUM MEMPROSES JKS -->
        <button
          @click="setStageFilter('stuck_spv')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'stuck_spv' ? 'bg-purple-600 text-white border-purple-600 ring-2 ring-purple-400' : 'bg-white text-gray-800 border-gray-200 hover:border-purple-300'
          ]"
        >
          <div class="text-2xl font-black text-purple-600" :class="{ 'text-white': selectedStage === 'stuck_spv' }">{{ metrics?.stuckSpv || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'stuck_spv' ? 'text-purple-100' : 'text-purple-700'">SPV Belum Memproses JKS</div>
        </button>

        <!-- PENDING EDP -->
        <button
          @click="setStageFilter('pending_edp')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'pending_edp' ? 'bg-sky-600 text-white border-sky-600 ring-2 ring-sky-400' : 'bg-white text-gray-800 border-gray-200 hover:border-sky-300'
          ]"
        >
          <div class="text-2xl font-black text-sky-600" :class="{ 'text-white': selectedStage === 'pending_edp' }">{{ metrics?.pendingEdp || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'pending_edp' ? 'text-sky-100' : 'text-sky-700'">Pending EDP</div>
        </button>

        <!-- COMPLETED -->
        <button
          @click="setStageFilter('completed')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 ring-2 ring-emerald-400' : 'bg-white text-gray-800 border-gray-200 hover:border-emerald-300'
          ]"
        >
          <div class="text-2xl font-black text-emerald-600" :class="{ 'text-white': selectedStage === 'completed' }">{{ metrics?.completed || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'completed' ? 'text-emerald-100' : 'text-emerald-700'">Selesai</div>
        </button>

        <!-- REJECTED -->
        <button
          @click="setStageFilter('rejected')"
          :class="[
            'p-4 rounded-xl border text-left transition shadow-xs cursor-pointer',
            selectedStage === 'rejected' ? 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-400' : 'bg-white text-gray-800 border-gray-200 hover:border-rose-300'
          ]"
        >
          <div class="text-2xl font-black text-rose-600" :class="{ 'text-white': selectedStage === 'rejected' }">{{ metrics?.rejected || 0 }}</div>
          <div class="text-[11px] font-bold uppercase tracking-wider mt-1" :class="selectedStage === 'rejected' ? 'text-rose-100' : 'text-rose-700'">Ditolak</div>
        </button>
      </div>

      <!-- FILTER BAR -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Cari Region..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Cari Cabang..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">TAHAPAN WORKFLOW</label>
            <select v-model="selectedStage" @change="applyFilters" class="w-full px-3 py-2 text-xs border rounded-lg bg-white">
              <option value="all">-- Semua Tahapan --</option>
              <option value="stuck_admin">Admin Belum Memproses Kode Cust</option>
              <option value="stuck_spv">SPV Belum Memproses Rute JKS</option>
              <option value="pending_edp">Pending Verifikasi EDP</option>
              <option value="completed">Selesai / Approved EDP</option>
              <option value="rejected">Ditolak / Rejected</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI TOKO / SALESMAN</label>
            <input
              type="text"
              v-model="search"
              @keyup.enter="applyFilters"
              placeholder="Nama Toko, Custcode, Salesman..."
              class="w-full px-3 py-2 text-xs border rounded-lg"
            />
          </div>
        </div>

        <div class="flex justify-end">
          <button @click="resetFilters" class="px-3 py-1.5 text-xs text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition cursor-pointer">
            Reset Filter
          </button>
        </div>
      </div>

      <!-- TABEL DATA PROGRESS WORKFLOW (INTERACTIVE HEADER SORTING) -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F8FAFC] border-b text-[#4B5563] uppercase tracking-wider font-semibold select-none">
              <tr>
                <th @click="handleSort('nama_noo')" class="p-3 cursor-pointer hover:bg-slate-100 transition">
                  <div class="flex items-center gap-1.5">
                    <span>DATA TOKO / OUTLET</span>
                    <span class="text-[10px] text-blue-600 font-bold" v-if="sortKey === 'nama_noo'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span class="text-[10px] text-gray-400" v-else>↕</span>
                  </div>
                </th>
                <th @click="handleSort('branch_id')" class="p-3 cursor-pointer hover:bg-slate-100 transition">
                  <div class="flex items-center gap-1.5">
                    <span>HIRARKI CABANG & SALESMAN</span>
                    <span class="text-[10px] text-blue-600 font-bold" v-if="sortKey === 'branch_id'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span class="text-[10px] text-gray-400" v-else>↕</span>
                  </div>
                </th>
                <th @click="handleSort('stage_code')" class="p-3 cursor-pointer hover:bg-slate-100 transition">
                  <div class="flex items-center gap-1.5">
                    <span>TAHAPAN WORKFLOW PROGRESS</span>
                    <span class="text-[10px] text-blue-600 font-bold" v-if="sortKey === 'stage_code'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span class="text-[10px] text-gray-400" v-else>↕</span>
                  </div>
                </th>
                <th @click="handleSort('submitted_at')" class="p-3 cursor-pointer hover:bg-slate-100 transition">
                  <div class="flex items-center gap-1.5">
                    <span>STATUS DETAILS</span>
                    <span class="text-[10px] text-blue-600 font-bold" v-if="sortKey === 'submitted_at'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span class="text-[10px] text-gray-400" v-else>↕</span>
                  </div>
                </th>
                <th class="p-3 text-right">AKSI</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="item in sortedSubmissions" :key="item.request_id" class="hover:bg-gray-50/80 transition">
                <!-- DATA TOKO -->
                <td class="p-3">
                  <div class="font-bold text-sm text-gray-900">{{ item.nama_noo }}</div>
                  <div class="text-xs text-gray-500 mt-0.5 max-w-xs truncate">{{ item.alamat_noo }}</div>
                  <div class="text-[11px] text-gray-400 mt-0.5">
                    {{ item.kel_noo ? `${item.kel_noo}, ` : '' }}{{ item.kec_noo ? `${item.kec_noo}, ` : '' }}{{ item.kab_kota_noo }}
                  </div>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-700 rounded border">
                      {{ item.type_outlet_code }}
                    </span>
                    <span v-if="item.code_noo_principal" class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 rounded border border-emerald-300">
                      {{ item.code_noo_principal }}
                    </span>
                  </div>
                </td>

                <!-- HIRARKI CABANG -->
                <td class="p-3">
                  <div class="font-semibold text-gray-800">{{ item.branch_id }} - {{ item.branch_name || 'Cabang' }}</div>
                  <div class="text-xs text-gray-500 mt-0.5">{{ item.salesman_name }} ({{ item.salesman_code }})</div>
                  <div class="text-[11px] text-gray-400 mt-0.5">Region: {{ item.region_code }}</div>
                </td>

                <!-- VISUAL STEP WORKFLOW -->
                <td class="p-3 min-w-[280px]">
                  <div class="space-y-1.5">
                    <!-- Stage Status Badge -->
                    <span
                      :class="[
                        'inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold border',
                        item.stage_code === 'STUCK_ADMIN' ? 'bg-amber-50 text-amber-800 border-amber-300' :
                        item.stage_code === 'STUCK_SPV' ? 'bg-purple-50 text-purple-800 border-purple-300' :
                        item.stage_code === 'PENDING_EDP' ? 'bg-sky-50 text-sky-800 border-sky-300' :
                        item.stage_code === 'COMPLETED' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' :
                        'bg-rose-50 text-rose-800 border-rose-300'
                      ]"
                    >
                      {{ item.stage_label }}
                    </span>

                    <!-- Step Dots Visualizer -->
                    <div class="flex items-center gap-1 mt-1">
                      <!-- Step 1: SE -->
                      <div
                        class="flex-1 h-2 rounded-full bg-emerald-500"
                        title="Step 1: SE Submitted"
                      ></div>
                      <!-- Step 2: Admin Dist -->
                      <div
                        :class="[
                          'flex-1 h-2 rounded-full',
                          isRejectedAdmin(item)
                            ? 'bg-rose-500'
                            : (item.pushed_to_spv_at || item.status === 'PUSHED_TO_SPV' || item.status === 'APPROVED_SPV' || item.status === 'APPROVED_EDP' || isRejectedSpv(item) || isRejectedEdp(item)
                                ? 'bg-emerald-500'
                                : (item.status === 'SE_SUBMITTED' ? 'bg-amber-400 animate-pulse' : 'bg-gray-200'))
                        ]"
                        :title="isRejectedAdmin(item) ? 'Step 2: Ditolak Admin Distributor' : 'Step 2: Admin Distributor Submit'"
                      ></div>
                      <!-- Step 3: SPV JKS -->
                      <div
                        :class="[
                          'flex-1 h-2 rounded-full',
                          isRejectedAdmin(item)
                            ? 'bg-gray-200'
                            : (isRejectedSpv(item)
                                ? 'bg-rose-500'
                                : (item.pushed_to_edp_at || item.status === 'APPROVED_SPV' || item.status === 'APPROVED_EDP' || isRejectedEdp(item)
                                    ? 'bg-emerald-500'
                                    : (item.status === 'PUSHED_TO_SPV' ? 'bg-purple-400 animate-pulse' : 'bg-gray-200')))
                        ]"
                        :title="isRejectedSpv(item) ? 'Step 3: Ditolak SPV Area' : 'Step 3: SPV Area Submit JKS'"
                      ></div>
                      <!-- Step 4: EDP -->
                      <div
                        :class="[
                          'flex-1 h-2 rounded-full',
                          (isRejectedAdmin(item) || isRejectedSpv(item))
                            ? 'bg-gray-200'
                            : (isRejectedEdp(item)
                                ? 'bg-rose-500'
                                : (item.status === 'APPROVED_EDP'
                                    ? 'bg-emerald-500'
                                    : (item.pushed_to_edp_at || item.status === 'APPROVED_SPV' ? 'bg-sky-400 animate-pulse' : 'bg-gray-200')))
                        ]"
                        :title="isRejectedEdp(item) ? 'Step 4: Ditolak EDP Principal' : 'Step 4: EDP Principal Approval'"
                      ></div>
                    </div>
                  </div>
                </td>

                <!-- STATUS DETAILS -->
                <td class="p-3 text-xs">
                  <div class="space-y-1 text-gray-600">
                    <div><span class="font-medium">SE Submit:</span> {{ formatDatetime(item.submitted_at) }}</div>
                    <div v-if="item.custcode_distributor">
                      <span class="font-medium">Custcode:</span> <span class="font-bold text-gray-800">{{ item.custcode_distributor }}</span>
                    </div>
                    <div v-if="item.norute">
                      <span class="font-medium">Rute JKS:</span> Hari: {{ formatJksDays(item) }} | Minggu: {{ formatJksWeeks(item) }}
                    </div>
                  </div>
                </td>

                <!-- AKSI TUNGGAL: DETAIL TIMELINE -->
                <td class="p-3 text-right">
                  <button
                    @click="activeSubmissionModal = item"
                    class="px-3.5 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition flex items-center gap-1.5 ml-auto shadow-xs cursor-pointer"
                  >
                    <span>Detail Timeline</span>
                  </button>
                </td>
              </tr>

              <tr v-if="!submissions.data || submissions.data.length === 0">
                <td colspan="5" class="p-8 text-center text-gray-500">
                  Tidak ada data submisi toko yang sesuai dengan filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-4 border-t bg-[#F8FAFC]">
          <Pagination :links="submissions.links" />
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL TIMELINE STEPPER (LEVEL 1 Z-INDEX 99990) -->
    <Teleport to="body">
      <div v-if="activeSubmissionModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl relative my-auto overflow-hidden border border-gray-200">
          <!-- Sticky Header Modal -->
          <div class="sticky top-0 z-20 bg-white px-6 py-4 border-b border-gray-200 flex items-center justify-between shrink-0 shadow-2xs">
            <div>
              <h3 class="text-lg font-bold text-[#111827] flex items-center gap-2">
                <span>Detail Timeline & Progress Workflow</span>
              </h3>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ activeSubmissionModal.nama_noo }} &bull; ID: <span class="font-mono text-gray-700">{{ activeSubmissionModal.request_id }}</span>
              </p>
            </div>
            <button
              @click="activeSubmissionModal = null"
              class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 transition font-bold cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Scrollable Modal Body -->
          <div class="p-6 sm:p-8 overflow-y-auto flex-1 bg-white">
            
            <!-- TIMELINE STEPPER CONTAINER (PERFECT SYMMETRY) -->
            <div class="relative pl-8 sm:pl-10 space-y-6 before:absolute before:left-3.5 sm:before:left-4.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
              
              <!-- STEP 1: SALES EXECUTIVE -->
              <div class="relative flex items-start">
                <div class="absolute -left-8 sm:-left-10 top-0.5 w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs z-10">
                  ✓
                </div>
                <div class="w-full bg-emerald-50/70 border border-emerald-200 rounded-xl p-4 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-emerald-900 uppercase tracking-wide">1. Submisi Sales Executive (Mobile App)</span>
                  <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded border border-emerald-300">COMPLETED</span>
                </div>
                <div class="text-xs text-emerald-800 space-y-1">
                  <div><strong>Salesman:</strong> {{ activeSubmissionModal.salesman_name }} ({{ activeSubmissionModal.salesman_code }})</div>
                  <div><strong>Cabang:</strong> {{ activeSubmissionModal.branch_id }} - {{ activeSubmissionModal.branch_name }}</div>
                  <div><strong>Waktu Submit:</strong> {{ formatDatetime(activeSubmissionModal.submitted_at) }}</div>
                </div>
              </div>
            </div>

            <!-- STEP 2: ADMIN DISTRIBUTOR -->
            <div class="relative flex items-start">
                <div
                  :class="[
                    'absolute -left-8 sm:-left-10 top-0.5 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs z-10',
                    isRejectedAdmin(activeSubmissionModal) ? 'bg-rose-600 text-white' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white animate-pulse')
                  ]"
                >
                  {{ isRejectedAdmin(activeSubmissionModal) ? '✕' : (activeSubmissionModal.pushed_to_spv_at ? '✓' : '2') }}
                </div>
                <div
                  :class="[
                    'w-full rounded-xl p-4 space-y-3 border transition',
                    isRejectedAdmin(activeSubmissionModal) ? 'bg-rose-50/90 border-rose-300' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-emerald-50/70 border-emerald-200' : 'bg-amber-50/80 border-amber-300')
                  ]"
                >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wide" :class="isRejectedAdmin(activeSubmissionModal) ? 'text-rose-900' : (activeSubmissionModal.pushed_to_spv_at ? 'text-emerald-900' : 'text-amber-900')">
                    2. Submisi Admin Distributor
                  </span>
                  <span
                    :class="[
                      'text-[11px] font-bold px-2 py-0.5 rounded border',
                      isRejectedAdmin(activeSubmissionModal) ? 'bg-rose-100 text-rose-800 border-rose-300' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-900 border-amber-400')
                    ]"
                  >
                    {{ isRejectedAdmin(activeSubmissionModal) ? 'DITOLAK ADMIN' : (activeSubmissionModal.pushed_to_spv_at ? 'COMPLETED' : 'PENDING PROCESS') }}
                  </span>
                </div>

                <!-- Info Body -->
                <div class="text-xs space-y-1" :class="isRejectedAdmin(activeSubmissionModal) ? 'text-rose-800' : (activeSubmissionModal.pushed_to_spv_at ? 'text-emerald-800' : 'text-amber-900')">
                  <div v-if="isRejectedAdmin(activeSubmissionModal)" class="font-bold text-rose-800">
                    Submisi ditolak oleh Admin Distributor
                  </div>
                  <div v-else-if="activeSubmissionModal.pushed_to_spv_at">
                    <strong>Custcode Distributor:</strong> <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded border border-emerald-300">{{ activeSubmissionModal.custcode_distributor }}</span>
                  </div>
                  <div v-else class="font-semibold text-amber-800">
                    Admin belum memproses NOO dengan mengisikan kode customer versi distributor
                  </div>
                  <div><strong>Approver Admin:</strong> {{ activeSubmissionModal.approved_by_admin || '-' }}</div>
                  <div><strong>Waktu Submit:</strong> {{ formatDatetime(activeSubmissionModal.pushed_to_spv_at) }}</div>
                </div>

                <!-- ACTION BUTTON EMBEDDED RESET ADMIN (TOMBOL WARNA MERAH / ROSE) -->
                <div v-if="canReset && activeSubmissionModal.pushed_to_spv_at" class="pt-2 border-t border-emerald-200/80 flex items-center justify-between">
                  <span class="text-[11px] text-gray-500 font-medium">Batal / Reset Input Admin</span>
                  
                  <!-- Enable if NOT Approved EDP -->
                  <button
                    v-if="activeSubmissionModal.status !== 'APPROVED_EDP'"
                    @click="openResetModal('ADMIN', activeSubmissionModal)"
                    class="px-3 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 border border-rose-700 shadow-xs rounded-lg transition flex items-center gap-1 cursor-pointer"
                  >
                    <span>Reset Input Admin</span>
                  </button>

                  <!-- Disabled Lock Notice if Approved EDP -->
                  <div v-else class="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold text-gray-500 bg-gray-100 border border-gray-300 rounded-lg" title="Reset terkunci karena toko sudah Approved EDP. Lakukan Reset Approval EDP terlebih dahulu.">
                    <span>Terkunci (Approved EDP)</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- STEP 3: SPV AREA JKS -->
            <div class="relative flex items-start">
                <div
                  :class="[
                    'absolute -left-8 sm:-left-10 top-0.5 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs z-10',
                    isRejectedSpv(activeSubmissionModal) ? 'bg-rose-600 text-white' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-emerald-600 text-white' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-purple-600 text-white animate-pulse' : 'bg-gray-300 text-gray-600'))
                  ]"
                >
                  {{ isRejectedSpv(activeSubmissionModal) ? '✕' : (activeSubmissionModal.pushed_to_edp_at ? '✓' : '3') }}
                </div>
                <div
                  :class="[
                    'w-full rounded-xl p-4 space-y-3 border transition',
                    isRejectedSpv(activeSubmissionModal) ? 'bg-rose-50/90 border-rose-300' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-emerald-50/70 border-emerald-200' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-purple-50/80 border-purple-300' : 'bg-gray-50 border-gray-200 opacity-60'))
                  ]"
                >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wide" :class="isRejectedSpv(activeSubmissionModal) ? 'text-rose-900' : (activeSubmissionModal.pushed_to_edp_at ? 'text-emerald-900' : (activeSubmissionModal.pushed_to_spv_at ? 'text-purple-900' : 'text-gray-500'))">
                    3. Submisi SPV Area (Rute JKS H1-H7 & M1-M4)
                  </span>
                  <span
                    :class="[
                      'text-[11px] font-bold px-2 py-0.5 rounded border',
                      isRejectedSpv(activeSubmissionModal) ? 'bg-rose-100 text-rose-800 border-rose-300' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : (activeSubmissionModal.pushed_to_spv_at ? 'bg-purple-100 text-purple-900 border-purple-300' : 'bg-gray-100 text-gray-500 border-gray-300'))
                    ]"
                  >
                    {{ isRejectedSpv(activeSubmissionModal) ? 'DITOLAK SPV' : (activeSubmissionModal.pushed_to_edp_at ? 'COMPLETED' : (activeSubmissionModal.pushed_to_spv_at ? 'PENDING JKS' : 'WAITING ADMIN')) }}
                  </span>
                </div>

                <!-- Info Body -->
                <div class="text-xs space-y-1" :class="isRejectedSpv(activeSubmissionModal) ? 'text-rose-800' : (activeSubmissionModal.pushed_to_edp_at ? 'text-emerald-800' : (activeSubmissionModal.pushed_to_spv_at ? 'text-purple-900' : 'text-gray-500'))">
                  <div v-if="isRejectedSpv(activeSubmissionModal)" class="font-bold text-rose-800">
                    Submisi ditolak oleh SPV Area
                  </div>
                  <div v-else-if="activeSubmissionModal.pushed_to_edp_at">
                    <strong>Jadwal Rute JKS:</strong>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                      <span class="px-2.5 py-1 font-bold bg-white text-gray-800 rounded border border-gray-300 shadow-2xs">
                        No Rute: {{ activeSubmissionModal.norute || '01' }}
                      </span>
                      <span class="px-2.5 py-1 font-bold bg-emerald-100 text-emerald-900 rounded border border-emerald-300 shadow-2xs">
                        Hari: {{ formatJksDays(activeSubmissionModal) }}
                      </span>
                      <span class="px-2.5 py-1 font-bold bg-emerald-100 text-emerald-900 rounded border border-emerald-300 shadow-2xs">
                        Minggu: {{ formatJksWeeks(activeSubmissionModal) }}
                      </span>
                    </div>
                  </div>
                  <div v-else-if="activeSubmissionModal.pushed_to_spv_at" class="font-semibold text-purple-800">
                    SPV belum memproses NOO dengan mengisikan JKS
                  </div>
                  <div v-else>
                    Belum dapat diproses oleh SPV (Menunggu Admin Distributor).
                  </div>
                  <div><strong>Approver SPV:</strong> {{ activeSubmissionModal.approved_by_spv || '-' }}</div>
                  <div><strong>Waktu Submit:</strong> {{ formatDatetime(activeSubmissionModal.pushed_to_edp_at) }}</div>
                </div>

                <!-- ACTION BUTTON EMBEDDED RESET SPV (TOMBOL WARNA MERAH / ROSE) -->
                <div v-if="canReset && activeSubmissionModal.pushed_to_edp_at" class="pt-2 border-t border-purple-200/80 flex items-center justify-between">
                  <span class="text-[11px] text-gray-500 font-medium">Batal / Reset Input SPV</span>
                  
                  <!-- Enable if NOT Approved EDP -->
                  <button
                    v-if="activeSubmissionModal.status !== 'APPROVED_EDP'"
                    @click="openResetModal('SPV', activeSubmissionModal)"
                    class="px-3 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 border border-rose-700 shadow-xs rounded-lg transition flex items-center gap-1 cursor-pointer"
                  >
                    <span>Reset Input SPV</span>
                  </button>

                  <!-- Disabled Lock Notice if Approved EDP -->
                  <div v-else class="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold text-gray-500 bg-gray-100 border border-gray-300 rounded-lg" title="Reset terkunci karena toko sudah Approved EDP. Lakukan Reset Approval EDP terlebih dahulu.">
                    <span>Terkunci (Approved EDP)</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- STEP 4: EDP PRINCIPAL -->
            <div class="relative flex items-start">
                <div
                  :class="[
                    'absolute -left-8 sm:-left-10 top-0.5 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-xs z-10',
                    isRejectedEdp(activeSubmissionModal) ? 'bg-rose-600 text-white' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'bg-emerald-600 text-white' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-sky-600 text-white animate-pulse' : 'bg-gray-300 text-gray-600'))
                  ]"
                >
                  {{ isRejectedEdp(activeSubmissionModal) ? '✕' : (activeSubmissionModal.status === 'APPROVED_EDP' ? '✓' : '4') }}
                </div>
                <div
                  :class="[
                    'w-full rounded-xl p-4 space-y-3 border transition',
                    isRejectedEdp(activeSubmissionModal) ? 'bg-rose-50/90 border-rose-300' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'bg-emerald-50/70 border-emerald-200' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-sky-50/80 border-sky-300' : 'bg-gray-50 border-gray-200 opacity-60'))
                  ]"
                >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wide" :class="isRejectedEdp(activeSubmissionModal) ? 'text-rose-900' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'text-emerald-900' : (activeSubmissionModal.pushed_to_edp_at ? 'text-sky-900' : 'text-gray-500'))">
                    4. Verifikasi & Approval EDP Principal
                  </span>
                  <span
                    :class="[
                      'text-[11px] font-bold px-2 py-0.5 rounded border',
                      isRejectedEdp(activeSubmissionModal) ? 'bg-rose-100 text-rose-800 border-rose-300' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : (activeSubmissionModal.pushed_to_edp_at ? 'bg-sky-100 text-sky-800 border-sky-300' : 'bg-gray-100 text-gray-500 border-gray-300'))
                    ]"
                  >
                    {{ isRejectedEdp(activeSubmissionModal) ? 'DITOLAK EDP' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'APPROVED' : (activeSubmissionModal.pushed_to_edp_at ? 'PENDING EDP' : 'WAITING SPV')) }}
                  </span>
                </div>

                <!-- Info Body -->
                <div class="text-xs space-y-1" :class="isRejectedEdp(activeSubmissionModal) ? 'text-rose-800' : (activeSubmissionModal.status === 'APPROVED_EDP' ? 'text-emerald-800' : (activeSubmissionModal.pushed_to_edp_at ? 'text-sky-900' : 'text-gray-500'))">
                  <div v-if="isRejectedEdp(activeSubmissionModal)" class="font-bold text-rose-800">
                    Submisi ditolak oleh EDP Principal
                  </div>
                  <div v-else>
                    <strong>Kode Customer Principal:</strong>
                    <span v-if="activeSubmissionModal.code_noo_principal" class="font-bold text-emerald-900 bg-white px-2 py-0.5 rounded border border-emerald-300 ml-1">
                      {{ activeSubmissionModal.code_noo_principal }}
                    </span>
                    <span v-else class="text-gray-400 italic ml-1">Belum Terbit</span>
                  </div>
                  <div><strong>Waktu Review EDP:</strong> {{ formatDatetime(activeSubmissionModal.edp_reviewed_at) }}</div>
                </div>

                <!-- ACTION BUTTON EMBEDDED RESET APPROVAL EDP (TOMBOL WARNA MERAH / ROSE) -->
                <div v-if="canReset && activeSubmissionModal.status === 'APPROVED_EDP'" class="pt-2 border-t border-emerald-200/80 flex items-center justify-between">
                  <span class="text-[11px] text-emerald-800 font-semibold">Toko sudah Approved EDP</span>
                  <button
                    @click="openResetModal('EDP', activeSubmissionModal)"
                    class="px-3 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 border border-rose-700 shadow-xs rounded-lg transition flex items-center gap-1 cursor-pointer"
                  >
                    <span>Reset Approval EDP</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Dedicated Rejection & Reset Reason Section -->
            <div v-if="activeSubmissionModal.reject_reason || activeSubmissionModal.reset_reason" class="pt-3 border-t border-slate-200 space-y-2">
              <div v-if="activeSubmissionModal.reject_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-xs shadow-2xs">
                <span class="font-bold block text-[11px] text-rose-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                  Alasan Penolakan (Rejected Reason):
                </span>
                <p class="whitespace-pre-line leading-relaxed font-medium text-rose-950">{{ activeSubmissionModal.reject_reason }}</p>
              </div>
              <div v-if="activeSubmissionModal.reset_reason" class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs shadow-2xs">
                <span class="font-bold block text-[11px] text-amber-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                  Alasan Pembatalan / Reset:
                </span>
                <p class="whitespace-pre-line leading-relaxed font-medium text-amber-950">{{ activeSubmissionModal.reset_reason }}</p>
              </div>
            </div>

            </div>
          </div>

          <!-- Sticky Footer Modal -->
          <div class="sticky bottom-0 z-20 bg-gray-50 px-6 py-3.5 border-t border-gray-200 flex items-center justify-end shrink-0 shadow-xs">
            <button
              @click="activeSubmissionModal = null"
              class="px-5 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 rounded-xl shadow-xs transition cursor-pointer"
            >
              Tutup Modal
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MODAL CONFIRMATION RESET INPUT (LEVEL 2 Z-INDEX 999999) -->
    <Teleport to="body">
      <div v-if="resetModalState.isOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl relative my-auto">
          <button @click="closeResetModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 font-bold">✕</button>

          <div class="flex items-center gap-3 border-b pb-3 text-rose-700">
            <span class="text-2xl">⚠️</span>
            <div>
              <h3 class="text-base font-bold">
                Reset / Cancel {{ resetModalState.type === 'ADMIN' ? 'Input Admin Distributor' : (resetModalState.type === 'SPV' ? 'Input SPV Area' : 'Approval EDP Principal') }}
              </h3>
              <p class="text-xs text-gray-500 mt-0.5">
                Konfirmasi pembatalan data toko.
              </p>
            </div>
          </div>

          <div class="text-xs space-y-3 text-gray-700">
            <p>
              Anda akan mereset data toko:
            </p>
            <div class="p-3 bg-gray-50 rounded-lg border font-bold text-gray-900">
              {{ resetModalState.submission?.nama_noo }}
            </div>

            <!-- EFEK RESET ADMIN (CASCADING RESET) -->
            <div v-if="resetModalState.type === 'ADMIN'" class="p-3 bg-rose-50 text-rose-800 rounded-lg border border-rose-200 text-[11px] leading-relaxed space-y-1">
              <strong>⚠️ Cascading Reset Admin & SPV:</strong>
              <ul class="list-disc ml-4 space-y-0.5">
                <li>Mengosongkan Kode Customer Distributor (`custcode_distributor`).</li>
                <li><strong>Mereset otomatis inputan Rute JKS SPV Area</strong> agar alur urutan tetap konsisten.</li>
                <li>Mengembalikan status toko ke awal: <strong>SE_SUBMITTED</strong>.</li>
              </ul>
            </div>

            <!-- EFEK RESET SPV -->
            <div v-if="resetModalState.type === 'SPV'" class="p-3 bg-rose-50 text-rose-800 rounded-lg border border-rose-200 text-[11px] leading-relaxed space-y-1">
              <strong>Efek Reset SPV:</strong>
              <ul class="list-disc ml-4 space-y-0.5">
                <li>Mengosongkan Rute JKS Hari (H1-H7) & Minggu (M1-M4).</li>
                <li>Mereset timestamp & approval status SPV.</li>
                <li>Mengembalikan status toko ke: <strong>PUSHED_TO_SPV</strong>.</li>
              </ul>
            </div>

            <!-- EFEK RESET EDP APPROVAL -->
            <div v-if="resetModalState.type === 'EDP'" class="p-3 bg-rose-50 text-rose-800 rounded-lg border border-rose-200 text-[11px] leading-relaxed space-y-1">
              <strong>Efek Reset Approval EDP:</strong>
              <ul class="list-disc ml-4 space-y-0.5">
                <li>Membatalkan Kode Customer Principal yang terbit.</li>
                <li>Mengembalikan status toko ke: <strong>APPROVED_SPV</strong>.</li>
                <li><strong>Otomatis mengaktifkan kembali (Enable) tombol Reset Admin & Reset SPV</strong> pada modal ini.</li>
              </ul>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan / Alasan Reset (Opsional):</label>
              <textarea
                v-model="resetForm.reason"
                rows="2"
                placeholder="Masukkan catatan alasan pembatalan..."
                class="w-full text-xs p-2.5 border rounded-lg focus:ring-1 focus:ring-rose-500"
              ></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t">
            <button
              @click="closeResetModal"
              :disabled="resetForm.processing"
              class="px-3.5 py-2 text-xs text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer disabled:opacity-50"
            >
              Batal
            </button>
            <button
              @click="submitReset"
              :disabled="resetForm.processing"
              :class="[
                'px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer',
                resetForm.processing ? 'opacity-50 cursor-not-allowed' : ''
              ]"
            >
              <svg v-if="resetForm.processing" class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ resetForm.processing ? 'Mereset Inputan...' : '↩️ Ya, Lakukan Reset' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </EdpLayout>
</template>
