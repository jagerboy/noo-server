<script setup lang="js">
/**
 * Halaman Master Branch - Web Portal NOO+
 * Fitur: Instant Client-Side Filter Bertingkat (Region -> Entity -> Search) & Full CRUD (Create, Edit, Delete).
 * Tanpa URL Domain Parameter Pollution.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  branches: [Array, Object],
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedEntity = ref(props.filters?.entity || '');

const isAddModalOpen = ref(false);
const editingBranch = ref(null);

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: typeof r === 'object' ? r.region_code : r,
    label: typeof r === 'object' ? (r.region_name ? `${r.region_code} - ${r.region_name}` : r.region_code) : String(r),
  }));
});

// 1. EXACT CASCADING FILTER: Entity hanya menampilkan entity yang ada di Region terpilih
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

// Watcher Reset Entity jika Region berubah
watch(selectedRegion, (newReg) => {
  if (newReg && selectedEntity.value) {
    const valid = entityOptions.value.some((e) => e.value === selectedEntity.value);
    if (!valid) {
      selectedEntity.value = '';
    }
  }
});

// INSTANT CLIENT-SIDE COMPUTED FILTERING (Tanpa URL Domain Parameter Pollution)
const rawBranchesList = computed(() => {
  if (Array.isArray(props.branches)) return props.branches;
  if (props.branches && Array.isArray(props.branches.data)) return props.branches.data;
  return [];
});

const filteredBranches = computed(() => {
  let list = rawBranchesList.value;

  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }

  if (selectedEntity.value) {
    list = list.filter((b) => b.entity_code_principal === selectedEntity.value);
  }

  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (b) =>
        (b.branch_id && String(b.branch_id).toLowerCase().includes(q)) ||
        (b.branch_name && String(b.branch_name).toLowerCase().includes(q)) ||
        (b.region_code && String(b.region_code).toLowerCase().includes(q))
    );
  }

  return list;
});

const addForm = useForm({
  region_code: '',
  principal_name: 'ASWFOODS',
  entity_code_principal: 'ASW',
  branch_id: '',
  branch_name: '',
  pin_branch: '123456',
});

const editForm = useForm({
  region_code: '',
  principal_name: '',
  entity_code_principal: '',
  branch_name: '',
  pin_branch: '',
  is_active: true,
});

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
}

function submitAddBranch() {
  addForm.post(route('edp.master_branch.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(b) {
  editingBranch.value = b;
  editForm.region_code = b.region_code;
  editForm.principal_name = b.principal_name;
  editForm.entity_code_principal = b.entity_code_principal;
  editForm.branch_name = b.branch_name;
  editForm.pin_branch = b.pin_branch;
  editForm.is_active = Boolean(b.is_active);
}

function submitEditBranch() {
  if (!editingBranch.value) return;
  editForm.put(route('edp.master_branch.update', editingBranch.value.id), {
    onSuccess: () => {
      editingBranch.value = null;
    },
  });
}

function deleteBranch(b) {
  if (confirm(`Yakin ingin menghapus Cabang ${b.branch_id} - ${b.branch_name}?`)) {
    router.delete(route('edp.master_branch.destroy', b.id));
  }
}
const isBulkModalOpen = ref(false);

const showAllPins = ref(false);
function toggleShowPins() {
  showAllPins.value = !showAllPins.value;
}
</script>

<template>
  <EdpLayout>
    <Head title="Master Branch - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>Master Branch / Cabang Distributor</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Master Cabang Distributor, PIN Branch, Region Code, dan Entitas Principal.
          </p>
        </div>

        <div v-if="canWrite" class="flex items-center gap-2">
          <button
            @click="isBulkModalOpen = true"
            class="px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 rounded-lg hover:bg-emerald-100 transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>Bulk Import CSV</span>
          </button>
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah Cabang</span>
          </button>
        </div>
      </div>

      <!-- Filter Bar (Instant Client-Side Filtering) -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-[#374151] flex items-center gap-2">
            Filter Data Cabang
          </span>
          <div class="flex items-center gap-3">
            <button
              @click="toggleShowPins"
              class="text-xs font-semibold text-purple-700 hover:text-purple-900 cursor-pointer flex items-center gap-1 bg-purple-50 px-2.5 py-1 rounded border border-purple-200"
            >
              <span>{{ showAllPins ? 'Sembunyikan PIN Cabang' : 'Tampilkan PIN Cabang' }}</span>
            </button>
            <button @click="resetFilters" class="text-xs font-semibold text-blue-600 hover:underline cursor-pointer">
              Reset Filter
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">ENTITY PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity Code / Nama..."
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI CABANG</label>
            <input
              type="text"
              v-model="search"
              placeholder="ID Cabang, Nama, Region..."
              class="w-full px-3 py-2 text-xs border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table Branch -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">ID Cabang</th>
                <th class="px-4 py-3">Nama Cabang</th>
                <th class="px-4 py-3">Region</th>
                <th class="px-4 py-3">Principal / Entity</th>
                <th class="px-4 py-3 select-none">
                  <div class="flex items-center gap-2 cursor-pointer group" @click="toggleShowPins" title="Klik untuk tampilkan / sembunyikan semua PIN branch">
                    <span>PIN Branch</span>
                    <span class="p-1 rounded-md bg-slate-100 group-hover:bg-slate-200 text-slate-600 transition flex items-center justify-center">
                      <svg v-if="showAllPins" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      <svg v-else class="w-4 h-4 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.025 10.025 0 012.122-.063c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/>
                      </svg>
                    </span>
                  </div>
                </th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="filteredBranches.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-[#9CA3AF] italic">
                  Data Cabang tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="b in filteredBranches" :key="b.id || b.branch_id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-bold text-[#111827]">{{ b.branch_id }}</td>
                <td class="px-4 py-3 text-[#374151]">{{ b.branch_name }}</td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ b.region_code }}</td>
                <td class="px-4 py-3 text-[#6B7280]">{{ b.principal_name || 'ASWFOODS' }} ({{ b.entity_code_principal }})</td>
                <td class="px-4 py-3 font-mono font-bold text-gray-700">
                  <span v-if="showAllPins" class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                    {{ b.pin_branch }}
                  </span>
                  <span v-else class="text-slate-400">
                    ******
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2.5 py-0.5 text-[10.5px] font-bold rounded-md uppercase tracking-wider',
                      (b.is_active === 1 || b.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (b.is_active === 1 || b.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(b)" class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">Edit</button>
                  <button @click="deleteBranch(b)" class="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH CABANG -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Tambah Master Cabang</h3>
        <form @submit.prevent="submitAddBranch" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Code</label>
            <input v-model="addForm.region_code" type="text" placeholder="misal: ASWSUM1" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Entity Principal Code</label>
            <input v-model="addForm.entity_code_principal" type="text" placeholder="misal: ASW14" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Branch ID</label>
            <input v-model="addForm.branch_id" type="text" placeholder="misal: DAAKK001" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Cabang</label>
            <input v-model="addForm.branch_name" type="text" placeholder="Nama Lengkap Cabang" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">PIN Branch</label>
            <input v-model="addForm.pin_branch" type="text" placeholder="PIN 6 Digit" class="w-full p-2 border rounded-lg" required />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT CABANG -->
    <div v-if="editingBranch" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Edit Master Cabang ({{ editingBranch.branch_id }})</h3>
        <form @submit.prevent="submitEditBranch" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Code</label>
            <input v-model="editForm.region_code" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Entity Principal Code</label>
            <input v-model="editForm.entity_code_principal" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Cabang</label>
            <input v-model="editForm.branch_name" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">PIN Branch (Isi jika mau ubah)</label>
            <input v-model="editForm.pin_branch" type="text" placeholder="******" class="w-full p-2 border rounded-lg" />
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_check" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded" />
            <label for="is_active_check" class="font-semibold text-slate-700">Cabang Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="editingBranch = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Update</button>
          </div>
        </form>
      </div>
    </div>

    <BulkUploadModal
      :isOpen="isBulkModalOpen"
      type="branch"
      title="Master Branch"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
