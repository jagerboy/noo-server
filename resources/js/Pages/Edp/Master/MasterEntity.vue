<script setup lang="js">
/**
 * Halaman Master Entity - Web Portal NOO+
 * Khusus Role Superadmin.
 * Fitur: Cascading Region Filter, Search, Tambah Entity, Edit Entity, Status Aktif, Hapus Entity.
 */
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
  entities: [Array, Object],
  regions: [Array, Object],
  filters: Object,
  canWrite: {
    type: Boolean,
    default: true,
  },
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const isAddModalOpen = ref(false);
const editingEntity = ref(null);

const rawEntitiesList = computed(() => {
  if (Array.isArray(props.entities)) return props.entities;
  if (props.entities && Array.isArray(props.entities.data)) return props.entities.data;
  return [];
});

const regionOptions = computed(() => {
  return (props.regions || []).map((r) => ({
    value: r.region_code,
    label: `${r.region_code} - ${r.region_name}`,
  }));
});

const filteredEntities = computed(() => {
  let list = rawEntitiesList.value;

  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }

  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (e) =>
        (e.entity_code_principal && String(e.entity_code_principal).toLowerCase().includes(q)) ||
        (e.entity_name_principal && String(e.entity_name_principal).toLowerCase().includes(q)) ||
        (e.region_code && String(e.region_code).toLowerCase().includes(q)) ||
        (e.region_name && String(e.region_name).toLowerCase().includes(q))
    );
  }

  return list;
});

const addForm = useForm({
  region_code: '',
  entity_code_principal: '',
  entity_name_principal: '',
  principal_code: 'ASW',
  principal_name: 'ASWFOODS',
  is_active: true,
});

function onAddRegionChange(regCode) {
  const r = (props.regions || []).find((item) => item.region_code === regCode);
  if (r) {
    addForm.principal_code = r.principal_code || (r.region_code.startsWith('ASW') ? 'ASW' : 'INA');
    addForm.principal_name = r.principal_name || (r.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS');
  }
}

function submitAddEntity() {
  addForm.post(route('edp.master_entity.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

const editForm = useForm({
  region_code: '',
  entity_name_principal: '',
  principal_code: 'ASW',
  principal_name: 'ASWFOODS',
  is_active: true,
});

function onEditRegionChange(regCode) {
  const r = (props.regions || []).find((item) => item.region_code === regCode);
  if (r) {
    editForm.principal_code = r.principal_code || (r.region_code.startsWith('ASW') ? 'ASW' : 'INA');
    editForm.principal_name = r.principal_name || (r.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS');
  }
}

function openEditModal(e) {
  editingEntity.value = e;
  editForm.region_code = e.region_code;
  editForm.entity_name_principal = e.entity_name_principal;
  editForm.principal_code = e.principal_code || (e.region_code?.startsWith('ASW') ? 'ASW' : 'INA');
  editForm.principal_name = e.principal_name || (editForm.principal_code === 'ASW' ? 'ASWFOODS' : 'INAFOODS');
  editForm.is_active = Boolean(e.is_active);
}

function submitEditEntity() {
  if (!editingEntity.value) return;
  editForm.put(route('edp.master_entity.update', editingEntity.value.id), {
    onSuccess: () => {
      editingEntity.value = null;
    },
  });
}

function deleteEntity(e) {
  if (confirm(`Yakin ingin menghapus Master Entity ${e.entity_code_principal} - ${e.entity_name_principal}?`)) {
    router.delete(route('edp.master_entity.destroy', e.id));
  }
}

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
}
</script>

<template>
  <EdpLayout>
    <Head title="Master Entity - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight">
              Master Entity Principal
            </h1>
          </div>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Manajemen Master Kode dan Nama Entitas Principal terhubung ke Region Wilayah.
          </p>
        </div>

        <div v-if="canWrite">
          <button
            @click="isAddModalOpen = true"
            class="px-3.5 py-1.5 text-[11.5px] font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah Entity</span>
          </button>
        </div>
      </div>

      <!-- Filter & Search Bar -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-[11.5px] font-semibold uppercase tracking-wider text-[#374151]">
            Filter & Pencarian Entity
          </span>
          <button @click="resetFilters" class="text-[10.5px] font-medium text-rose-500 hover:text-rose-700 hover:underline cursor-pointer">
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Filter Region</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region..."
            />
          </div>
          <div>
            <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Cari Entity</label>
            <input
              type="text"
              v-model="search"
              placeholder="Ketik Entity Code / Nama..."
              class="w-full px-3 py-1.5 text-xs border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase">
              <tr>
                <th class="px-4 py-3">Entity Code</th>
                <th class="px-4 py-3">Nama Entity</th>
                <th class="px-4 py-3">Region Terkait</th>
                <th class="px-4 py-3">Principal</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-if="filteredEntities.length === 0">
                <td :colspan="canWrite ? 6 : 5" class="px-4 py-8 text-center text-[#9CA3AF] italic">
                  Data Entity tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="e in filteredEntities" :key="e.id || e.entity_code_principal" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-mono font-bold text-[#059669] text-[13px]">{{ e.entity_code_principal }}</td>
                <td class="px-4 py-3 font-semibold text-[#111827]">{{ e.entity_name_principal }}</td>
                <td class="px-4 py-3 text-slate-700 font-medium">
                  <span class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 text-xs font-mono font-bold mr-1.5">
                    {{ e.region_code }}
                  </span>
                  <span class="text-slate-500 text-[11.5px]">{{ e.region_name }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600">
                  <span class="font-medium text-slate-800">{{ e.principal_name }}</span>
                  <span class="text-slate-400 text-xs"> ({{ e.principal_code }})</span>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[10.5px] font-semibold rounded-full uppercase tracking-wider',
                      (e.is_active === 1 || e.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (e.is_active === 1 || e.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(e)" class="text-[11.5px] font-semibold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer">Edit</button>
                  <button @click="deleteEntity(e)" class="text-[11.5px] font-semibold text-red-600 hover:text-red-800 hover:underline cursor-pointer">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH ENTITY -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Tambah Master Entity</h3>
          <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitAddEntity" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Terkait <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="addForm.region_code"
              :options="regionOptions"
              placeholder="-- Pilih Region --"
              searchPlaceholder="Ketik Region..."
              @change="onAddRegionChange"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Entity Principal Code <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.entity_code_principal"
              type="text"
              placeholder="misal: ASW01 / INA01"
              class="w-full p-2 border rounded-lg uppercase"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Entity Principal <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.entity_name_principal"
              type="text"
              placeholder="misal: ASW JABODETABEK"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Principal <span class="text-rose-500">*</span></label>
            <select
              v-model="addForm.principal_code"
              class="w-full p-2 border rounded-lg bg-white cursor-pointer"
            >
              <option value="ASW">ASW (ASWFOODS)</option>
              <option value="INA">INA (INAFOODS)</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_entity_add" type="checkbox" v-model="addForm.is_active" class="w-4 h-4 text-emerald-600 rounded cursor-pointer" />
            <label for="is_active_entity_add" class="font-semibold text-slate-700 cursor-pointer">Entity Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="addForm.processing || !addForm.region_code || !addForm.entity_code_principal || !addForm.entity_name_principal"
              class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857] disabled:opacity-50"
            >
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT ENTITY -->
    <div v-if="editingEntity" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Edit Master Entity ({{ editingEntity.entity_code_principal }})</h3>
          <button @click="editingEntity = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitEditEntity" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region Terkait <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="editForm.region_code"
              :options="regionOptions"
              placeholder="-- Pilih Region --"
              searchPlaceholder="Ketik Region..."
              @change="onEditRegionChange"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Entity Principal <span class="text-rose-500">*</span></label>
            <input
              v-model="editForm.entity_name_principal"
              type="text"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Principal <span class="text-rose-500">*</span></label>
            <select
              v-model="editForm.principal_code"
              class="w-full p-2 border rounded-lg bg-white cursor-pointer"
            >
              <option value="ASW">ASW (ASWFOODS)</option>
              <option value="INA">INA (INAFOODS)</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_entity_edit" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded cursor-pointer" />
            <label for="is_active_entity_edit" class="font-semibold text-slate-700 cursor-pointer">Entity Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="editingEntity = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="editForm.processing || !editForm.region_code || !editForm.entity_name_principal"
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
