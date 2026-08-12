<script setup lang="js">
/**
 * Halaman Master Salesman - Web Portal NOO+
 * Fitur: Filter dinamis (Region, Branch, Search), SearchableSelect dropdown, & Full CRUD.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  salesmen: Object,
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const isAddModalOpen = ref(false);
const editingSalesman = ref(null);

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code || r,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : String(r.region_code || r),
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

function onRegionChange() {
  if (selectedRegion.value) {
    const valid = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!valid) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

const addForm = useForm({
  salesman_code: '',
  salesman_name: '',
  branch_id: '',
  region_code: '',
});

const editForm = useForm({
  salesman_name: '',
  branch_id: '',
  region_code: '',
  is_active: true,
});

function applyFilters() {
  router.get(
    route('edp.master_salesman'),
    {
      search: search.value,
      region_code: selectedRegion.value,
      branch_id: selectedBranch.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedBranch.value = '';
  applyFilters();
}

function submitAddSalesman() {
  addForm.post(route('edp.master_salesman.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(s) {
  editingSalesman.value = s;
  editForm.salesman_name = s.salesman_name;
  editForm.branch_id = s.branch_id;
  editForm.region_code = s.region_code || '';
  editForm.is_active = Boolean(s.is_active);
}

function submitEditSalesman() {
  if (!editingSalesman.value) return;
  editForm.put(route('edp.master_salesman.update', editingSalesman.value.id), {
    onSuccess: () => {
      editingSalesman.value = null;
    },
  });
}

function deleteSalesman(s) {
  if (confirm(`Yakin ingin menghapus Salesman ${s.salesman_code} - ${s.salesman_name}?`)) {
    router.delete(route('edp.master_salesman.destroy', s.id));
  }
}
const isBulkModalOpen = ref(false);
</script>

<template>
  <EdpLayout>
    <Head title="Master Salesman - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>👔 Master Salesman (SE)</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Salesman / Sales Executive yang teregistrasi untuk pengajuan NOO Mobile App.
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
            <span>+ Tambah Salesman Baru</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS DENGAN SEARCHABLE SELECT -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-xs font-bold text-[#374151] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data Salesman</span>
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
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI SALESMAN</label>
            <input type="text" v-model="search" @keyup.enter="applyFilters" placeholder="Kode Salesman, Nama..." class="w-full px-3 py-2 text-xs border rounded-lg" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="resetFilters" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 rounded-lg hover:bg-gray-200">Reset</button>
          <button @click="applyFilters" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#374151] rounded-lg hover:bg-black">Terapkan</button>
        </div>
      </div>

      <!-- Table Salesman -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">ID & Nama Cabang</th>
                <th class="px-4 py-3">Kode Salesman</th>
                <th class="px-4 py-3">Nama Salesman</th>
                <th class="px-4 py-3">Region</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-for="s in salesmen.data" :key="s.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-bold text-[#059669]">
                  {{ s.branch_id }}
                  <span v-if="s.branch_name" class="font-semibold text-gray-700"> - {{ s.branch_name }}</span>
                </td>
                <td class="px-4 py-3 font-bold text-[#111827]">{{ s.salesman_code }}</td>
                <td class="px-4 py-3 text-[#374151] font-medium">{{ s.salesman_name }}</td>
                <td class="px-4 py-3 text-[#6B7280]">{{ s.region_code || '-' }}</td>
                <td class="px-4 py-3">
                  <span v-if="s.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md">AKTIF</span>
                  <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md">NON-AKTIF</span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(s)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded">Edit</button>
                  <button @click="deleteSalesman(s)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded">Hapus</button>
                </td>
              </tr>
              <tr v-if="salesmen.data.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data salesman.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="salesmen.links"
          :from="salesmen.from"
          :to="salesmen.to"
          :total="salesmen.total"
        />
      </div>

      <!-- Add Modal -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl overflow-visible my-auto">
            <h3 class="text-base font-bold text-[#111827]">Tambah Salesman Baru</h3>
            
            <form @submit.prevent="submitAddSalesman" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">KODE SALESMAN</label>
                <input type="text" v-model="addForm.salesman_code" placeholder="Contoh: SE001" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">NAMA SALESMAN</label>
                <input type="text" v-model="addForm.salesman_name" placeholder="Contoh: Budi Gunawan" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">ID CABANG (SEARCHABLE)</label>
                <SearchableSelect
                  v-model="addForm.branch_id"
                  :options="branchOptions"
                  placeholder="-- Pilih Cabang --"
                  searchPlaceholder="Ketik ID atau Nama Cabang..."
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION CODE</label>
                <SearchableSelect
                  v-model="addForm.region_code"
                  :options="regionOptions"
                  placeholder="-- Pilih Region --"
                  searchPlaceholder="Ketik Region Code..."
                />
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
        <div v-if="editingSalesman" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl overflow-visible my-auto">
            <h3 class="text-base font-bold text-[#111827]">Edit Salesman {{ editingSalesman.salesman_code }}</h3>
            
            <form @submit.prevent="submitEditSalesman" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">NAMA SALESMAN</label>
                <input type="text" v-model="editForm.salesman_name" class="w-full px-3 py-2 text-xs border rounded-lg" required />
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
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION CODE</label>
                <SearchableSelect
                  v-model="editForm.region_code"
                  :options="regionOptions"
                  placeholder="-- Pilih Region --"
                  searchPlaceholder="Ketik Region Code..."
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563] mb-1">STATUS SALESMAN</label>
                <select v-model="editForm.is_active" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option :value="true">AKTIF</option>
                  <option :value="false">NON-AKTIF</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingSalesman = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Update Salesman</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>

    <!-- Modal Bulk Upload -->
    <BulkUploadModal
      :is-open="isBulkModalOpen"
      type="salesman"
      title="Master Salesman"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
