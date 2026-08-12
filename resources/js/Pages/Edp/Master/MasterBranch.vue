<script setup lang="js">
/**
 * Halaman Master Branch - Web Portal NOO+
 * Fitur: Filter dinamis (Region, Entity, Search) & Full CRUD (Create, Edit, Delete).
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  branches: Object,
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

function onRegionChange() {
  if (selectedRegion.value) {
    const valid = entityOptions.value.some((e) => e.value === selectedEntity.value);
    if (!valid) {
      selectedEntity.value = '';
    }
  }
  applyFilters();
}

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

function applyFilters() {
  router.get(
    route('edp.master_branch'),
    {
      search: search.value,
      region_code: selectedRegion.value,
      entity: selectedEntity.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
  applyFilters();
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
            <span>🏢 Master Branch / Cabang Distributor</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Master Cabang Distributor, PIN Branch, Region Code, dan Entitas Principal.
          </p>
        </div>

        <div v-if="canWrite" class="flex items-center gap-2">
          <button
            v-if="$page.props.auth?.user?.role === 'SUPERADMIN'"
            @click="isBulkModalOpen = true"
            class="px-3.5 py-2 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>📤 Bulk Upload</span>
          </button>
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg shadow-sm transition flex items-center gap-2"
          >
            <span>+ Tambah Cabang Baru</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-xs font-bold text-[#374151] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data Cabang</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="onRegionChange"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">ENTITY PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity Code / Nama..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI CABANG</label>
            <input type="text" v-model="search" @keyup.enter="applyFilters" placeholder="ID Cabang, Nama, Region..." class="w-full px-3 py-2 text-xs border rounded-lg" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="resetFilters" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 rounded-lg hover:bg-gray-200">Reset</button>
          <button @click="applyFilters" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#374151] rounded-lg hover:bg-black">Terapkan</button>
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
                      <!-- Eye Open Icon (showing PINs) -->
                      <svg v-if="showAllPins" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      <!-- Eye Slash / Closed Icon (hidden PINs) -->
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
              <tr v-for="b in branches.data" :key="b.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-bold text-[#111827]">{{ b.branch_id }}</td>
                <td class="px-4 py-3 text-[#374151]">{{ b.branch_name }}</td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ b.region_code }}</td>
                <td class="px-4 py-3 text-[#6B7280]">{{ b.principal_name }} ({{ b.entity_code_principal }})</td>
                <td class="px-4 py-3 font-mono font-bold text-gray-700">
                  <span v-if="showAllPins" class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                    {{ b.pin_branch }}
                  </span>
                  <span v-else class="text-slate-400">
                    ******
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span v-if="b.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md">AKTIF</span>
                  <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md">NON-AKTIF</span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(b)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded">Edit</button>
                  <button @click="deleteBranch(b)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded">Hapus</button>
                </td>
              </tr>
              <tr v-if="branches.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Tidak ada data cabang.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="branches.links"
          :from="branches.from"
          :to="branches.to"
          :total="branches.total"
        />
      </div>

      <!-- Add Modal -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Tambah Master Cabang Baru</h3>
            
            <form @submit.prevent="submitAddBranch" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">REGION CODE</label>
                <input type="text" v-model="addForm.region_code" placeholder="Contoh: ASWSUM1" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">ENTITY CODE PRINCIPAL</label>
                <input type="text" v-model="addForm.entity_code_principal" placeholder="Contoh: ASW / INA" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">ID CABANG (BRANCH ID)</label>
                <input type="text" v-model="addForm.branch_id" placeholder="Contoh: MKS / JKT01" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">NAMA CABANG</label>
                <input type="text" v-model="addForm.branch_name" placeholder="Contoh: MAKASSAR CABANG" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">PIN BRANCH (6 DIGIT)</label>
                <input type="text" v-model="addForm.pin_branch" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="isAddModalOpen = false" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="addForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Simpan</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- Edit Modal -->
      <Teleport to="body">
        <div v-if="editingBranch" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Edit Cabang {{ editingBranch.branch_id }}</h3>
            
            <form @submit.prevent="submitEditBranch" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">REGION CODE</label>
                <input type="text" v-model="editForm.region_code" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">ENTITY CODE PRINCIPAL</label>
                <input type="text" v-model="editForm.entity_code_principal" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">NAMA CABANG</label>
                <input type="text" v-model="editForm.branch_name" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">PIN BRANCH</label>
                <input type="text" v-model="editForm.pin_branch" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">STATUS CABANG</label>
                <select v-model="editForm.is_active" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option :value="true">AKTIF</option>
                  <option :value="false">NON-AKTIF</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingBranch = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Update Cabang</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>

    <!-- Modal Bulk Upload -->
    <BulkUploadModal
      :is-open="isBulkModalOpen"
      type="branch"
      title="Master Branch / Cabang Distributor"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
