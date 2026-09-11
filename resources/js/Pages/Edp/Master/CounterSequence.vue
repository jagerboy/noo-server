<script setup lang="js">
/**
 * Halaman Counter Sequence Kode Customer Principal - Web Portal NOO+
 * Fitur: Filter dinamis dengan SearchableSelect, Preview Kode Customer Principal Selanjutnya,
 * & Edit Sequence Terakhir (EDP Region / Admin).
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  sequences: Object,
  canEditSequence: Boolean,
  canWriteFull: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedEntity = ref(props.filters?.entity || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const editingItem = ref(null);

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code || r,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : String(r.region_code || r),
  }));
});

const entityOptions = computed(() => {
  let list = props.filterOptions?.entities || [];
  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  return list.map((e) => ({
    value: typeof e === 'object' ? e.entity_code_principal : e,
    label: typeof e === 'object' ? (e.entity_name_principal ? `${e.entity_code_principal} - ${e.entity_name_principal}` : e.entity_code_principal) : String(e),
  }));
});

const branchOptions = computed(() => {
  let list = props.filterOptions?.branches || [];
  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }
  if (selectedEntity.value) {
    list = list.filter((b) => b.entity_code_principal === selectedEntity.value);
  }
  return list.map((b) => ({
    value: b.branch_id,
    label: `${b.branch_id} - ${b.branch_name}`,
  }));
});

function onRegionChange() {
  if (selectedRegion.value && selectedEntity.value) {
    const valid = entityOptions.value.some((e) => e.value === selectedEntity.value);
    if (!valid) {
      selectedEntity.value = '';
    }
  }
  if (selectedRegion.value && selectedBranch.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

function onEntityChange() {
  if (selectedEntity.value && selectedBranch.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

const sortKey = ref(props.filters?.sort_by || 'branch_id');
const sortDir = ref(props.filters?.sort_dir || 'asc');

function handleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
  applyFilters();
}

const sortedSequences = computed(() => {
  return props.sequences?.data || props.sequences || [];
});

const editForm = useForm({
  principal_code: 'A',
  prefix: '',
  last_seq: 0,
});

function applyFilters() {
  router.get(
    route('edp.counter_sequence'),
    {
      search: search.value,
      region_code: selectedRegion.value,
      entity: selectedEntity.value,
      branch_id: selectedBranch.value,
      sort_by: sortKey.value,
      sort_dir: sortDir.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
  selectedBranch.value = '';
  sortKey.value = 'branch_id';
  sortDir.value = 'asc';
  applyFilters();
}

function openEditModal(item) {
  editingItem.value = item;
  editForm.principal_code = item.principal_code || 'A';
  editForm.prefix = item.prefix || '';
  editForm.last_seq = item.last_seq || 0;
}

function submitUpdateSequence() {
  if (!editingItem.value) return;

  editForm.post(route('edp.counter_sequence.update', editingItem.value.id), {
    onSuccess: () => {
      editingItem.value = null;
      editForm.reset();
    },
  });
}

function generateNextCodePreview(item) {
  const pCode = item.principal_code || 'A';
  const prefix = item.prefix || '';
  const nextSeqNum = Number(item.last_seq || 0) + 1;
  const seqStr = String(nextSeqNum).padStart(5, '0');
  return `C${pCode}${prefix}${seqStr}`;
}
const isAddModalOpen = ref(false);
const addForm = useForm({
  branch_id: '',
  principal_code: 'A',
  prefix: '',
  last_seq: 0,
});

function openAddModal() {
  addForm.reset();
  addForm.branch_id = '';
  addForm.principal_code = 'A';
  addForm.prefix = '';
  addForm.last_seq = 0;
  isAddModalOpen.value = true;
}

function onAddBranchSelect(branchId) {
  if (!branchId) return;
  addForm.branch_id = branchId;
  const str = String(branchId).toUpperCase();
  const branchObj = (props.filterOptions?.branches || []).find((b) => b.branch_id === branchId);
  if (branchObj && (branchObj.principal_code || branchObj.entity_code_principal)) {
    addForm.principal_code = branchObj.principal_code || branchObj.entity_code_principal;
  }
  if (str.length >= 5) {
    addForm.prefix = str.substring(2, 5);
  } else if (str.length >= 3) {
    addForm.prefix = str.substring(0, 3);
  } else {
    addForm.prefix = str;
  }
}

function submitAddSequence() {
  addForm.post(route('edp.counter_sequence.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

const isBulkModalOpen = ref(false);
</script>

<template>
  <EdpLayout>
    <Head title="Counter Sequence - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight flex items-center gap-2">
            <span>Counter Sequence Kode Customer Principal</span>
          </h1>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Penataan & Otomatisasi Sequence Nomor Urut Kode Customer Principal per Cabang Distributor.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="canWriteFull"
            @click="openAddModal"
            class="px-3 py-1.5 text-[11.5px] font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Setting Sequence Cabang Baru</span>
          </button>
          <button
            v-if="$page.props.auth?.user?.role === 'SUPERADMIN'"
            @click="isBulkModalOpen = true"
            class="px-3 py-1.5 text-[11.5px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>Bulk Upload</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS DENGAN SEARCHABLE SELECT -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <h2 class="text-[11.5px] font-semibold text-[#475569] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data Counter Sequence</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="onRegionChange"
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">ENTITY / PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity Code / Nama..."
              @change="onEntityChange"
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Ketik ID atau Nama Cabang..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">CARI KATA KUNCI</label>
            <input type="text" v-model="search" @keyup.enter="applyFilters" placeholder="Cari Branch ID, Prefix, Cabang..." class="w-full px-2.5 py-1.5 text-[12px] border border-slate-300 rounded-lg" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1 border-t border-slate-100">
          <button @click="resetFilters" class="px-2.5 py-1 text-[10.5px] font-medium text-rose-500 hover:text-rose-700 hover:underline cursor-pointer">Reset</button>
          <button @click="applyFilters" class="px-3 py-1 text-[11px] font-semibold text-white bg-[#374151] rounded-lg hover:bg-black cursor-pointer">Terapkan</button>
        </div>
      </div>

      <!-- Table Counter Sequence -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase select-none">
              <tr>
                <th @click="handleSort('branch_id')" class="px-3 py-3 cursor-pointer hover:bg-[#E5E7EB]">
                  <div class="flex items-center gap-1">
                    <span>ID Cabang</span>
                    <span v-if="sortKey === 'branch_id'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('branch_name')" class="px-3 py-3 cursor-pointer hover:bg-[#E5E7EB]">
                  <div class="flex items-center gap-1">
                    <span>Nama Cabang & Region</span>
                    <span v-if="sortKey === 'branch_name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('principal_code')" class="px-3 py-3 cursor-pointer hover:bg-[#E5E7EB]">
                  <div class="flex items-center gap-1">
                    <span>Principal & Prefix Kode</span>
                    <span v-if="sortKey === 'principal_code'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('last_seq')" class="px-3 py-3 cursor-pointer hover:bg-[#E5E7EB]">
                  <div class="flex items-center gap-1">
                    <span>Sequence Terakhir (Last Seq)</span>
                    <span v-if="sortKey === 'last_seq'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th class="px-3 py-3">Preview Kode NOO Selanjutnya</th>
                <th @click="handleSort('last_updated_at')" class="px-3 py-3 cursor-pointer hover:bg-[#E5E7EB]">
                  <div class="flex items-center gap-1">
                    <span>Terakhir Diperbarui</span>
                    <span v-if="sortKey === 'last_updated_at'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th class="px-3 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-for="s in sortedSequences" :key="s.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-3 py-2.5 font-bold text-[#111827] text-[13px]">{{ s.branch_id }}</td>
                <td class="px-3 py-2.5">
                  <span class="font-bold text-[#374151]">{{ s.branch_name }}</span> <br>
                  <span class="text-[10.5px] text-[#059669] font-bold">Region: {{ s.region_code }}</span>
                </td>
                <td class="px-3 py-2.5">
                  <span class="font-mono font-bold text-[#1D4ED8] text-[11.5px]">Code: {{ s.principal_code }}</span> |
                  <span class="font-mono font-bold text-[#059669] text-[11.5px]">Prefix: {{ s.prefix }}</span>
                </td>
                <td class="px-3 py-2.5">
                  <span class="px-2 py-0.5 rounded bg-emerald-100 text-[#065F46] font-mono font-bold text-[11.5px]">
                    {{ String(s.last_seq).padStart(5, '0') }} ({{ s.last_seq }})
                  </span>
                </td>
                <!-- PREVIEW KODE PRINCIPAL SELANJUTNYA -->
                <td class="px-3 py-2.5">
                  <span class="px-2 py-0.5 rounded bg-blue-50 border border-blue-200 text-blue-800 font-mono font-bold text-[11.5px]">
                    👉 {{ generateNextCodePreview(s) }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-[#6B7280] text-[11px] font-mono">
                  {{ s.last_updated_at ? new Date(s.last_updated_at).toLocaleString('id-ID') : '-' }}
                </td>
                <td class="px-3 py-2.5 text-right">
                  <button
                    @click="openEditModal(s)"
                    class="px-2.5 py-1 text-[11px] font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg transition shadow-2xs cursor-pointer"
                  >
                    Update Sequence
                  </button>
                </td>
              </tr>
              <tr v-if="sequences.data.length === 0">
                <td colspan="7" class="px-3 py-8 text-center text-gray-400">Tidak ada data sequence.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="sequences.links"
          :from="sequences.from"
          :to="sequences.to"
          :total="sequences.total"
        />
      </div>

      <!-- Modal Edit Sequence -->
      <Teleport to="body">
        <div v-if="editingItem" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Update Counter Sequence</h3>
            
            <div class="p-3.5 bg-emerald-50 rounded-lg text-xs space-y-1.5 border border-emerald-200">
              <p><strong class="text-emerald-900">Cabang:</strong> {{ editingItem.branch_id }} - {{ editingItem.branch_name }}</p>
              <p><strong class="text-emerald-900">Preview Kode Selanjutnya:</strong> <span class="font-mono font-bold text-blue-700">{{ generateNextCodePreview(editingItem) }}</span></p>
            </div>

            <form @submit.prevent="submitUpdateSequence" class="space-y-3">
              <div v-if="canWriteFull" class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-[#4B5563] mb-1">PRINCIPAL CODE</label>
                  <input
                    type="text"
                    v-model="editForm.principal_code"
                    class="w-full px-3 py-2 text-xs font-mono font-bold border rounded-lg uppercase"
                    required
                  />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-[#4B5563] mb-1">PREFIX KODE</label>
                  <input
                    type="text"
                    v-model="editForm.prefix"
                    class="w-full px-3 py-2 text-xs font-mono font-bold border rounded-lg uppercase"
                    required
                  />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">LAST SEQUENCE TERAKHIR (ANGKA)</label>
                <input
                  type="number"
                  v-model="editForm.last_seq"
                  min="0"
                  class="w-full px-3 py-2 text-sm font-bold font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10B981]"
                  required
                />
                <p class="text-[11px] text-gray-500 mt-1">
                  Masukkan sequence terakhir dari Master Database Customer Distributor.
                </p>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingItem = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Simpan Update</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- Modal Tambah Counter Sequence Cabang -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Setting Counter Sequence Cabang Baru</h3>
            
            <form @submit.prevent="submitAddSequence" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">ID CABANG (SEARCHABLE)</label>
                <SearchableSelect
                  v-model="addForm.branch_id"
                  :options="branchOptions"
                  placeholder="-- Pilih Cabang --"
                  searchPlaceholder="Ketik ID atau Nama Cabang..."
                  @change="onAddBranchSelect"
                />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-[#4B5563] mb-1">PRINCIPAL CODE</label>
                  <input
                    type="text"
                    v-model="addForm.principal_code"
                    placeholder="Contoh: A / ASW"
                    class="w-full px-3 py-2 text-xs font-mono font-bold border rounded-lg uppercase"
                    required
                  />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-[#4B5563] mb-1">PREFIX KODE</label>
                  <input
                    type="text"
                    v-model="addForm.prefix"
                    placeholder="Contoh: DATBH"
                    class="w-full px-3 py-2 text-xs font-mono font-bold border rounded-lg uppercase"
                    required
                  />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">SEQUENCE TERAKHIR (LAST SEQ)</label>
                <input
                  type="number"
                  v-model="addForm.last_seq"
                  min="0"
                  placeholder="0"
                  class="w-full px-3 py-2 text-sm font-bold font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#10B981]"
                  required
                />
                <p class="text-[11px] text-gray-500 mt-1">
                  Masukkan nomor urut terakhir jika cabang sudah memiliki customer di DB distributor, atau 0 untuk cabang baru.
                </p>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="isAddModalOpen = false" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="addForm.processing || !addForm.branch_id" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Simpan Sequence</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>

    <!-- Modal Bulk Upload -->
    <BulkUploadModal
      :is-open="isBulkModalOpen"
      type="counter_sequence"
      title="Counter Sequence"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
