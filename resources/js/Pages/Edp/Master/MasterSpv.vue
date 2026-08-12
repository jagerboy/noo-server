<script setup lang="js">
/**
 * Halaman Master SPV Area - Web Portal NOO+
 * Fitur: Filter dinamis (Branch, Search), SearchableSelect dropdown, & Full CRUD.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  spvs: Object,
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedEntity = ref(props.filters?.entity || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const isAddModalOpen = ref(false);
const editingSpv = ref(null);

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
    value: e.entity_code_principal || e,
    label: e.entity_name_principal ? `${e.entity_code_principal} - ${e.entity_name_principal}` : String(e.entity_code_principal || e),
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
  if (selectedRegion.value) {
    const validEntity = entityOptions.value.some((e) => e.value === selectedEntity.value);
    if (!validEntity) {
      selectedEntity.value = '';
    }
    const validBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!validBranch) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

function onEntityChange() {
  if (selectedEntity.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

const addForm = useForm({
  salescode: '',
  password: '123',
  nama: '',
  branch_id: '',
  area: '',
});

const editForm = useForm({
  nama: '',
  password: '',
  branch_id: '',
  area: '',
  is_active: true,
});

function applyFilters() {
  router.get(
    route('edp.master_spv'),
    {
      search: search.value,
      region_code: selectedRegion.value,
      entity: selectedEntity.value,
      branch_id: selectedBranch.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
  selectedBranch.value = '';
  applyFilters();
}

function submitAddSpv() {
  addForm.post(route('edp.master_spv.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(spv) {
  editingSpv.value = spv;
  editForm.nama = spv.nama;
  editForm.password = '';
  editForm.branch_id = spv.branch_id;
  editForm.area = spv.area || '';
  editForm.is_active = Boolean(spv.is_active);
}

function submitEditSpv() {
  if (!editingSpv.value) return;
  editForm.put(route('edp.master_spv.update', editingSpv.value.id), {
    onSuccess: () => {
      editingSpv.value = null;
    },
  });
}

function deleteSpv(spv) {
  if (confirm(`Yakin ingin menghapus SPV ${spv.salescode} - ${spv.nama}?`)) {
    router.delete(route('edp.master_spv.destroy', spv.id));
  }
}
const isBulkModalOpen = ref(false);
</script>

<template>
  <EdpLayout>
    <Head title="Master SPV - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>📋 Master Supervisor (SPV Area)</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Supervisor Area yang melakukan verifikasi & approval rute kunjungan.
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
            <span>+ Tambah SPV Baru</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS DENGAN SEARCHABLE SELECT -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-xs font-bold text-[#374151] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data SPV</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
              @change="onEntityChange"
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

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI SPV</label>
            <input type="text" v-model="search" @keyup.enter="applyFilters" placeholder="Salescode, Nama, Area..." class="w-full px-3 py-2 text-xs border rounded-lg" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="resetFilters" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 rounded-lg hover:bg-gray-200">Reset</button>
          <button @click="applyFilters" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#374151] rounded-lg hover:bg-black">Terapkan</button>
        </div>
      </div>

      <!-- Table SPV -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">Salescode SPV</th>
                <th class="px-4 py-3">Nama SPV</th>
                <th class="px-4 py-3">ID Cabang</th>
                <th class="px-4 py-3">Area / Distributor</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-for="spv in spvs.data" :key="spv.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-bold text-[#111827]">{{ spv.salescode }}</td>
                <td class="px-4 py-3 text-[#374151]">{{ spv.nama }}</td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ spv.branch_id }}</td>
                <td class="px-4 py-3 text-[#6B7280]">{{ spv.area || '-' }} {{ spv.distributor_name ? `(${spv.distributor_name})` : '' }}</td>
                <td class="px-4 py-3">
                  <span v-if="spv.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md">AKTIF</span>
                  <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md">NON-AKTIF</span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(spv)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded">Edit</button>
                  <button @click="deleteSpv(spv)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded">Hapus</button>
                </td>
              </tr>
              <tr v-if="spvs.data.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data SPV.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="spvs.links"
          :from="spvs.from"
          :to="spvs.to"
          :total="spvs.total"
        />
      </div>

      <!-- Add Modal dengan SearchableSelect -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl overflow-visible my-auto">
            <h3 class="text-base font-bold text-[#111827]">Tambah SPV Baru</h3>
            
            <form @submit.prevent="submitAddSpv" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">SALESCODE SPV</label>
                <input type="text" v-model="addForm.salescode" placeholder="Contoh: SPVSUMBAR1" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">PASSWORD LOGIN</label>
                <input type="password" v-model="addForm.password" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">NAMA SPV</label>
                <input type="text" v-model="addForm.nama" placeholder="Contoh: ARIF PRIYADI" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">ID CABANG (BERURUT ALFABETIS + SEARCH BOX)</label>
                <SearchableSelect
                  v-model="addForm.branch_id"
                  :options="branchOptions"
                  placeholder="-- Pilih Cabang --"
                  searchPlaceholder="Ketik ID atau Nama Cabang..."
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">AREA</label>
                <input type="text" v-model="addForm.area" placeholder="Contoh: SUMBAR" class="w-full px-3 py-2 text-xs border rounded-lg" />
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="isAddModalOpen = false" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="addForm.processing || !addForm.branch_id" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Simpan</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- Edit Modal -->
      <Teleport to="body">
        <div v-if="editingSpv" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl overflow-visible my-auto">
            <h3 class="text-base font-bold text-[#111827]">Edit SPV {{ editingSpv.salescode }}</h3>
            
            <form @submit.prevent="submitEditSpv" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">NAMA SPV</label>
                <input type="text" v-model="editForm.nama" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">PASSWORD (Kosongkan jika tidak diubah)</label>
                <input type="password" v-model="editForm.password" class="w-full px-3 py-2 text-xs border rounded-lg" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">ID CABANG</label>
                <SearchableSelect
                  v-model="editForm.branch_id"
                  :options="branchOptions"
                  placeholder="-- Pilih Cabang --"
                  searchPlaceholder="Ketik ID atau Nama Cabang..."
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">AREA</label>
                <input type="text" v-model="editForm.area" class="w-full px-3 py-2 text-xs border rounded-lg" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">STATUS SPV</label>
                <select v-model="editForm.is_active" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option :value="true">AKTIF</option>
                  <option :value="false">NON-AKTIF</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingSpv = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Update SPV</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>

    <!-- Modal Bulk Upload -->
    <BulkUploadModal
      :is-open="isBulkModalOpen"
      type="spv"
      title="Master SPV Area"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
