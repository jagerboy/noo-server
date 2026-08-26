<script setup lang="js">
/**
 * Halaman Master EDP - Web Portal NOO+
 * Fitur: Instant Client-Side Filter (Region, Search) & Full CRUD.
 * Tanpa URL Domain Parameter Pollution.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
  edps: [Array, Object],
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

// INSTANT CLIENT-SIDE COMPUTED FILTERING
const rawEdpList = computed(() => {
  if (Array.isArray(props.edps)) return props.edps;
  if (props.edps && Array.isArray(props.edps.data)) return props.edps.data;
  return [];
});

const filteredEdps = computed(() => {
  let list = rawEdpList.value;

  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (e) =>
        (e.username && String(e.username).toLowerCase().includes(q)) ||
        (e.nama && String(e.nama).toLowerCase().includes(q)) ||
        (e.region_code && String(e.region_code).toLowerCase().includes(q))
    );
  }

  return list;
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

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
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
            <span>Master User EDP / Administrator</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Akun User EDP Region & Otoritas Sistem NOO+.
          </p>
        </div>

        <div v-if="canWrite">
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah User EDP</span>
          </button>
        </div>
      </div>

      <!-- Filter Bar (Instant Client-Side Filtering) -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-[#374151] flex items-center gap-2">
            Filter Data User EDP
          </span>
          <button @click="resetFilters" class="text-xs font-semibold text-blue-600 hover:underline cursor-pointer">
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region..."
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI USER</label>
            <input
              type="text"
              v-model="search"
              placeholder="Username, Nama, Region..."
              class="w-full px-3 py-2 text-xs border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table EDP Users -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">Username</th>
                <th class="px-4 py-3">Nama User</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Region Code</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="filteredEdps.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-[#9CA3AF] italic">
                  Data User EDP tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="edp in filteredEdps" :key="edp.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-mono font-bold text-[#111827]">{{ edp.username }}</td>
                <td class="px-4 py-3 font-semibold text-[#374151]">{{ edp.nama }}</td>
                <td class="px-4 py-3 text-[#6B7280]">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">
                    {{ edp.role }}
                  </span>
                </td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ edp.region_code || 'GLOBAL / ALL' }}</td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2.5 py-0.5 text-[10.5px] font-bold rounded-md uppercase tracking-wider',
                      (edp.is_active === 1 || edp.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (edp.is_active === 1 || edp.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(edp)" class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">Edit</button>
                  <button @click="deleteEdp(edp)" class="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH EDP -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Tambah Master User EDP</h3>
        <form @submit.prevent="submitAddEdp" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Username</label>
            <input v-model="addForm.username" type="text" placeholder="misal: edp_sumut" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Password</label>
            <input v-model="addForm.password" type="password" placeholder="Password Login" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap</label>
            <input v-model="addForm.nama" type="text" placeholder="Nama Operator EDP" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Role / Peran</label>
            <select v-model="addForm.role" class="w-full p-2 border rounded-lg">
              <option value="EDP_REGION">EDP_REGION</option>
              <option value="ADMIN_PRINCIPAL">ADMIN_PRINCIPAL</option>
              <option value="SUPERADMIN">SUPERADMIN</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Code (Kosongkan jika Global)</label>
            <input v-model="addForm.region_code" type="text" placeholder="misal: ASWSUM1" class="w-full p-2 border rounded-lg uppercase" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT EDP -->
    <div v-if="editingEdp" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Edit User EDP ({{ editingEdp.username }})</h3>
        <form @submit.prevent="submitEditEdp" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap</label>
            <input v-model="editForm.nama" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Password (Kosongkan jika tidak ubah)</label>
            <input v-model="editForm.password" type="password" placeholder="******" class="w-full p-2 border rounded-lg" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Role / Peran</label>
            <select v-model="editForm.role" class="w-full p-2 border rounded-lg">
              <option value="EDP_REGION">EDP_REGION</option>
              <option value="ADMIN_PRINCIPAL">ADMIN_PRINCIPAL</option>
              <option value="SUPERADMIN">SUPERADMIN</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Code</label>
            <input v-model="editForm.region_code" type="text" class="w-full p-2 border rounded-lg uppercase" />
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_edp" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded" />
            <label for="is_active_edp" class="font-semibold text-slate-700">User Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="editingEdp = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Update</button>
          </div>
        </form>
      </div>
    </div>
  </EdpLayout>
</template>
