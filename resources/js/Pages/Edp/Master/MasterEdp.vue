<script setup lang="js">
/**
 * Halaman Master EDP - Web Portal NOO+
 * Fitur: Filter dinamis dengan SearchableSelect, Pagination & Full CRUD.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  edps: Object,
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => {
    if (typeof r === 'object' && r !== null) {
      return {
        value: r.region_code || r.value,
        label: r.region_name ? `${r.region_code} - ${r.region_name}` : String(r.region_code || r.value),
      };
    }
    return { value: r, label: String(r) };
  });
});

const isAddModalOpen = ref(false);
const editingEdp = ref(null);

const addForm = useForm({
  username: '',
  password: '123',
  nama: '',
  role: 'EDP_REGION',
  region_code: '',
});

const editForm = useForm({
  nama: '',
  password: '',
  role: 'EDP_REGION',
  region_code: '',
  is_active: true,
});

function applyFilters() {
  router.get(
    route('edp.master_edp'),
    {
      search: search.value,
      region_code: selectedRegion.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  applyFilters();
}

function submitAddEdp() {
  addForm.post(route('edp.master_edp.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(edp) {
  editingEdp.value = edp;
  editForm.nama = edp.nama || '';
  editForm.password = '';
  editForm.role = edp.role || 'EDP_REGION';
  editForm.region_code = edp.region_code || '';
  editForm.is_active = Boolean(edp.is_active);
}

function submitEditEdp() {
  if (!editingEdp.value) return;
  editForm.put(route('edp.master_edp.update', editingEdp.value.id), {
    onSuccess: () => {
      editingEdp.value = null;
    },
  });
}

function deleteEdp(edp) {
  if (confirm(`Yakin ingin menghapus EDP ${edp.username}?`)) {
    router.delete(route('edp.master_edp.destroy', edp.id));
  }
}
</script>

<template>
  <EdpLayout>
    <Head title="Master EDP - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>💻 Master EDP Region & Principal</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Akun EDP Region, Administrator Principal, dan Hak Akses Wilayah.
          </p>
        </div>

        <div v-if="canWrite">
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg shadow-sm transition flex items-center gap-2"
          >
            <span>+ Tambah Akun EDP Baru</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS -->
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-xs font-bold text-[#374151] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data EDP</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="applyFilters"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI EDP</label>
            <input type="text" v-model="search" @keyup.enter="applyFilters" placeholder="Username, Nama..." class="w-full px-3 py-2 text-xs border rounded-lg" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="resetFilters" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 rounded-lg hover:bg-gray-200">Reset</button>
          <button @click="applyFilters" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#374151] rounded-lg hover:bg-black">Terapkan</button>
        </div>
      </div>

      <!-- Table EDP -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">Username</th>
                <th class="px-4 py-3">Nama Lengkap</th>
                <th class="px-4 py-3">Peran / Role</th>
                <th class="px-4 py-3">Region Code</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-for="e in edps.data" :key="e.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-bold text-[#111827]">{{ e.username || e.region_code }}</td>
                <td class="px-4 py-3 text-[#374151]">{{ e.nama || '-' }}</td>
                <td class="px-4 py-3 font-semibold text-emerald-700">{{ e.role || 'EDP_REGION' }}</td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ e.region_code || 'ALL REGIONS' }}</td>
                <td class="px-4 py-3">
                  <span v-if="e.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md">AKTIF</span>
                  <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md">NON-AKTIF</span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(e)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded">Edit</button>
                  <button @click="deleteEdp(e)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded">Hapus</button>
                </td>
              </tr>
              <tr v-if="edps.data.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada data EDP.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="edps.links"
          :from="edps.from"
          :to="edps.to"
          :total="edps.total"
        />
      </div>

      <!-- Add Modal -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Tambah Master EDP Baru</h3>
            
            <form @submit.prevent="submitAddEdp" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">USERNAME</label>
                <input type="text" v-model="addForm.username" placeholder="Contoh: edp.aswsum1" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">PASSWORD</label>
                <input type="password" v-model="addForm.password" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">NAMA LENGKAP</label>
                <input type="text" v-model="addForm.nama" placeholder="Contoh: EDP Sumatera 1" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">ROLE</label>
                <select v-model="addForm.role" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option value="EDP_REGION">EDP Region</option>
                  <option value="ADMIN_PRINCIPAL">Admin Principal</option>
                  <option value="SUPERADMIN">Superadmin</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">REGION CODE</label>
                <select v-model="addForm.region_code" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option value="">-- Semua Region --</option>
                  <option v-for="r in filterOptions?.regions" :key="r" :value="r">{{ r }}</option>
                </select>
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
        <div v-if="editingEdp" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Edit EDP {{ editingEdp.username }}</h3>
            
            <form @submit.prevent="submitEditEdp" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">NAMA LENGKAP</label>
                <input type="text" v-model="editForm.nama" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">PASSWORD BARU (Kosongkan jika tidak diubah)</label>
                <input type="password" v-model="editForm.password" class="w-full px-3 py-2 text-xs border rounded-lg" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">ROLE</label>
                <select v-model="editForm.role" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option value="EDP_REGION">EDP Region</option>
                  <option value="ADMIN_PRINCIPAL">Admin Principal</option>
                  <option value="SUPERADMIN">Superadmin</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">REGION CODE</label>
                <select v-model="editForm.region_code" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option value="">-- Semua Region --</option>
                  <option v-for="r in filterOptions?.regions" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">STATUS AKUN</label>
                <select v-model="editForm.is_active" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option :value="true">AKTIF</option>
                  <option :value="false">NON-AKTIF</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingEdp = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Update EDP</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>
