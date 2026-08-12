<script setup lang="js">
/**
 * Halaman Master Outlet Types - Web Portal NOO+
 * Fitur: Filter dinamis (Search) & Full CRUD (Create, Edit, Delete).
 */
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';

const props = defineProps({
  outletTypes: Array,
  canWrite: Boolean,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const isAddModalOpen = ref(false);
const editingType = ref(null);

const addForm = useForm({
  code: '',
  description: '',
});

const editForm = useForm({
  description: '',
  is_active: true,
});

function applyFilters() {
  router.get(route('edp.master_outlet_types'), { search: search.value }, { preserveState: true, replace: true });
}

function resetFilters() {
  search.value = '';
  applyFilters();
}

function submitAddType() {
  addForm.post(route('edp.master_outlet_types.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(t) {
  editingType.value = t;
  editForm.description = t.description;
  editForm.is_active = Boolean(t.is_active);
}

function submitEditType() {
  if (!editingType.value) return;
  editForm.put(route('edp.master_outlet_types.update', editingType.value.id), {
    onSuccess: () => {
      editingType.value = null;
    },
  });
}

function deleteType(t) {
  if (confirm(`Yakin ingin menghapus Tipe Outlet ${t.code}?`)) {
    router.delete(route('edp.master_outlet_types.destroy', t.id));
  }
}
</script>

<template>
  <EdpLayout>
    <Head title="Master Outlet Types - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>🏷️ Master Outlet Types / Channel Tipe Toko</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Daftar Kode & Deskripsi Tipe Outlet (General Trade GT01-GT07 & Modern Trade MT01-MT05).
          </p>
        </div>

        <div v-if="canWrite">
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg shadow-sm transition flex items-center gap-2"
          >
            <span>+ Tambah Tipe Outlet Baru</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] flex items-center gap-3">
        <input
          type="text"
          v-model="search"
          @keyup.enter="applyFilters"
          placeholder="Cari Kode Tipe Outlet atau Deskripsi..."
          class="w-full max-w-md px-3.5 py-2 text-xs bg-white border border-[#D1D5DB] rounded-lg focus:ring-2 focus:ring-[#10B981]"
        />
        <button @click="applyFilters" class="px-4 py-2 text-xs font-semibold text-white bg-[#374151] rounded-lg">Cari</button>
        <button @click="resetFilters" class="px-3 py-2 text-xs font-semibold bg-gray-100 rounded-lg">Reset</button>
      </div>

      <!-- Table Outlet Types -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
            <tr>
              <th class="px-4 py-3">Kode Tipe Outlet</th>
              <th class="px-4 py-3">Deskripsi / Tipe Channel</th>
              <th class="px-4 py-3">Status</th>
              <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E5E7EB]">
            <tr v-for="t in outletTypes" :key="t.id" class="hover:bg-emerald-50/20 transition">
              <td class="px-4 py-3 font-bold text-[#111827]">{{ t.code }}</td>
              <td class="px-4 py-3 text-[#374151] font-semibold">{{ t.description }}</td>
              <td class="px-4 py-3">
                <span v-if="t.is_active" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-md">AKTIF</span>
                <span v-else class="px-2 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded-md">NON-AKTIF</span>
              </td>
              <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                <button @click="openEditModal(t)" class="px-2.5 py-1 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 rounded">Edit</button>
                <button @click="deleteType(t)" class="px-2.5 py-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 rounded">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Add Modal -->
      <Teleport to="body">
        <div v-if="isAddModalOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Tambah Tipe Outlet Baru</h3>
            
            <form @submit.prevent="submitAddType" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">KODE TIPE OUTLET</label>
                <input type="text" v-model="addForm.code" placeholder="Contoh: GT08" class="w-full px-3 py-2 text-xs border rounded-lg uppercase" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">DESKRIPSI</label>
                <input type="text" v-model="addForm.description" placeholder="Contoh: Toko Manisan & Oleh-oleh" class="w-full px-3 py-2 text-xs border rounded-lg" required />
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
        <div v-if="editingType" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl my-auto">
            <h3 class="text-base font-bold text-[#111827]">Edit Tipe Outlet {{ editingType.code }}</h3>
            
            <form @submit.prevent="submitEditType" class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">DESKRIPSI</label>
                <input type="text" v-model="editForm.description" class="w-full px-3 py-2 text-xs border rounded-lg" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#4B5563]">STATUS</label>
                <select v-model="editForm.is_active" class="w-full px-3 py-2 text-xs border rounded-lg">
                  <option :value="true">AKTIF</option>
                  <option :value="false">NON-AKTIF</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="editingType = null" class="px-3 py-1.5 text-xs font-semibold bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" :disabled="editForm.processing" class="px-4 py-1.5 text-xs font-semibold text-white bg-[#059669] rounded-lg">Update</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>
