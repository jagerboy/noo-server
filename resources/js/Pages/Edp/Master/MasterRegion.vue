<script setup lang="js">
/**
 * Halaman Master Region - Web Portal NOO+
 * Khusus Role Superadmin.
 * Fitur: Search, Tambah Region, Edit Region, Status Aktif, Hapus Region.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';

const props = defineProps({
  regions: [Array, Object],
  filters: Object,
  canWrite: {
    type: Boolean,
    default: true,
  },
});

const search = ref(props.filters?.search || '');
const isAddModalOpen = ref(false);
const editingRegion = ref(null);

const rawRegionsList = computed(() => {
  if (Array.isArray(props.regions)) return props.regions;
  if (props.regions && Array.isArray(props.regions.data)) return props.regions.data;
  return [];
});

const filteredRegions = computed(() => {
  let list = rawRegionsList.value;
  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (r) =>
        (r.region_code && String(r.region_code).toLowerCase().includes(q)) ||
        (r.region_name && String(r.region_name).toLowerCase().includes(q)) ||
        (r.principal_name && String(r.principal_name).toLowerCase().includes(q))
    );
  }
  return list;
});

const addForm = useForm({
  region_code: '',
  region_name: '',
  principal_code: 'ASW',
  principal_name: 'ASWFOODS',
  is_active: true,
});

function onPrincipalCodeChange(mode = 'add') {
  if (mode === 'add') {
    addForm.principal_name = addForm.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS';
  } else {
    editForm.principal_name = editForm.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS';
  }
}

function submitAddRegion() {
  addForm.post(route('edp.master_region.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

const editForm = useForm({
  region_name: '',
  principal_code: 'ASW',
  principal_name: 'ASWFOODS',
  is_active: true,
});

function openEditModal(r) {
  editingRegion.value = r;
  editForm.region_name = r.region_name;
  editForm.principal_code = r.principal_code || 'ASW';
  editForm.principal_name = r.principal_name || (r.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS');
  editForm.is_active = Boolean(r.is_active);
}

function submitEditRegion() {
  if (!editingRegion.value) return;
  editForm.put(route('edp.master_region.update', editingRegion.value.id), {
    onSuccess: () => {
      editingRegion.value = null;
    },
  });
}

function deleteRegion(r) {
  if (confirm(`Yakin ingin menghapus Master Region ${r.region_code} - ${r.region_name}?`)) {
    router.delete(route('edp.master_region.destroy', r.id));
  }
}
</script>

<template>
  <EdpLayout>
    <Head title="Master Region - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight">
              Master Region Wilayah
            </h1>
          </div>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Manajemen Master Kode dan Nama Region Wilayah Nasional (ASWFOODS & INAFOODS).
          </p>
        </div>

        <div v-if="canWrite">
          <button
            @click="isAddModalOpen = true"
            class="px-3.5 py-1.5 text-[11.5px] font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah Region</span>
          </button>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-xs flex items-center justify-between gap-3">
        <div class="w-full max-w-sm">
          <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Cari Region</label>
          <input
            type="text"
            v-model="search"
            placeholder="Ketik Region Code, Nama, atau Principal..."
            class="w-full px-3 py-1.5 text-xs border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
          />
        </div>
        <div class="text-xs text-slate-500 font-medium">
          Total: <span class="font-bold text-slate-800">{{ filteredRegions.length }}</span> Region
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase">
              <tr>
                <th class="px-4 py-3">Region Code</th>
                <th class="px-4 py-3">Nama Region</th>
                <th class="px-4 py-3">Principal</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-if="filteredRegions.length === 0">
                <td :colspan="canWrite ? 5 : 4" class="px-4 py-8 text-center text-[#9CA3AF] italic">
                  Data Region tidak ditemukan.
                </td>
              </tr>

              <tr v-for="r in filteredRegions" :key="r.id || r.region_code" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-mono font-bold text-[#059669] text-[13px]">{{ r.region_code }}</td>
                <td class="px-4 py-3 font-semibold text-[#111827]">{{ r.region_name }}</td>
                <td class="px-4 py-3 text-slate-600">
                  <span class="font-medium text-slate-800">{{ r.principal_name }}</span>
                  <span class="text-slate-400 text-xs"> ({{ r.principal_code }})</span>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[10.5px] font-semibold rounded-full uppercase tracking-wider',
                      (r.is_active === 1 || r.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (r.is_active === 1 || r.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(r)" class="text-[11.5px] font-semibold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer">Edit</button>
                  <button @click="deleteRegion(r)" class="text-[11.5px] font-semibold text-red-600 hover:text-red-800 hover:underline cursor-pointer">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH REGION -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Tambah Master Region</h3>
          <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitAddRegion" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Code <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.region_code"
              type="text"
              placeholder="misal: ASWSUM1 / INAJWA1"
              class="w-full p-2 border rounded-lg uppercase"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Region <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.region_name"
              type="text"
              placeholder="misal: ASW SUMATERA 1"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Principal <span class="text-rose-500">*</span></label>
            <select
              v-model="addForm.principal_code"
              @change="onPrincipalCodeChange('add')"
              class="w-full p-2 border rounded-lg bg-white cursor-pointer"
            >
              <option value="ASW">ASW (ASWFOODS)</option>
              <option value="INA">INA (INAFOODS)</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_region_add" type="checkbox" v-model="addForm.is_active" class="w-4 h-4 text-emerald-600 rounded cursor-pointer" />
            <label for="is_active_region_add" class="font-semibold text-slate-700 cursor-pointer">Region Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="addForm.processing || !addForm.region_code || !addForm.region_name"
              class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857] disabled:opacity-50"
            >
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT REGION -->
    <div v-if="editingRegion" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Edit Master Region ({{ editingRegion.region_code }})</h3>
          <button @click="editingRegion = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitEditRegion" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Region <span class="text-rose-500">*</span></label>
            <input
              v-model="editForm.region_name"
              type="text"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Principal <span class="text-rose-500">*</span></label>
            <select
              v-model="editForm.principal_code"
              @change="onPrincipalCodeChange('edit')"
              class="w-full p-2 border rounded-lg bg-white cursor-pointer"
            >
              <option value="ASW">ASW (ASWFOODS)</option>
              <option value="INA">INA (INAFOODS)</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_region_edit" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded cursor-pointer" />
            <label for="is_active_region_edit" class="font-semibold text-slate-700 cursor-pointer">Region Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="editingRegion = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="editForm.processing || !editForm.region_name"
              class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857] disabled:opacity-50"
            >
              Update
            </button>
          </div>
        </form>
      </div>
    </div>

  </EdpLayout>
</template>
