<script setup lang="js">
/**
 * Halaman Master SPV Area - Web Portal NOO+
 * Fitur: Instant Client-Side Filter Bertingkat (Region -> Entity -> Branch -> Search) & Full CRUD.
 * Tanpa URL Domain Parameter Pollution.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  spvs: [Array, Object],
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

// 1. EXACT CASCADING FILTER: Entity hanya menampilkan entity yang ada di Region terpilih
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

// 1. EXACT CASCADING FILTER: Branch hanya menampilkan branch yang ada di Region/Entity terpilih
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

watch(selectedRegion, (newReg) => {
  if (newReg) {
    if (selectedEntity.value) {
      const validEntity = entityOptions.value.some((e) => e.value === selectedEntity.value);
      if (!validEntity) selectedEntity.value = '';
    }
    if (selectedBranch.value) {
      const validBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
      if (!validBranch) selectedBranch.value = '';
    }
  }
});

watch(selectedEntity, (newEnt) => {
  if (newEnt && selectedBranch.value) {
    const validBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!validBranch) selectedBranch.value = '';
  }
});

// INSTANT CLIENT-SIDE COMPUTED FILTERING
const rawSpvList = computed(() => {
  if (Array.isArray(props.spvs)) return props.spvs;
  if (props.spvs && Array.isArray(props.spvs.data)) return props.spvs.data;
  return [];
});

const filteredSpvs = computed(() => {
  let list = rawSpvList.value;

  if (selectedRegion.value) {
    list = list.filter((s) => s.region_code === selectedRegion.value);
  }
  if (selectedEntity.value) {
    list = list.filter((s) => s.entity_code_principal === selectedEntity.value);
  }
  if (selectedBranch.value) {
    list = list.filter((s) => s.branch_id === selectedBranch.value);
  }
  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (s) =>
        (s.salescode && String(s.salescode).toLowerCase().includes(q)) ||
        (s.nama && String(s.nama).toLowerCase().includes(q)) ||
        (s.branch_id && String(s.branch_id).toLowerCase().includes(q))
    );
  }

  return list;
});

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

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
  selectedBranch.value = '';
}

function submitAddSpv() {
  addForm.post(route('edp.master_spv.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

function openEditModal(s) {
  editingSpv.value = s;
  editForm.nama = s.nama;
  editForm.password = '';
  editForm.branch_id = s.branch_id;
  editForm.area = s.area || '';
  editForm.is_active = Boolean(s.is_active);
}

function submitEditSpv() {
  if (!editingSpv.value) return;
  editForm.put(route('edp.master_spv.update', editingSpv.value.id), {
    onSuccess: () => {
      editingSpv.value = null;
    },
  });
}

function deleteSpv(s) {
  if (confirm(`Yakin ingin menghapus SPV ${s.salescode} - ${s.nama}?`)) {
    router.delete(route('edp.master_spv.destroy', s.id));
  }
}

const isBulkModalOpen = ref(false);
</script>

<template>
  <EdpLayout>
    <Head title="Master SPV Area - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
            <span>🛡️ Master SPV Area</span>
          </h1>
          <p class="text-xs text-[#6B7280] mt-1">
            Manajemen Akun Login & Otoritas SPV Area per Cabang Distributor.
          </p>
        </div>

        <div v-if="canWrite" class="flex items-center gap-2">
          <button
            @click="isBulkModalOpen = true"
            class="px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 rounded-lg hover:bg-emerald-100 transition shadow-2xs flex items-center gap-1.5"
          >
            <span>📥 Bulk Import CSV</span>
          </button>
          <button
            @click="isAddModalOpen = true"
            class="px-4 py-2 text-xs font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah SPV</span>
          </button>
        </div>
      </div>

      <!-- Filter Bar (Instant Client-Side Filtering) -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-[#374151] flex items-center gap-2">
            🔍 Filter Data SPV (Instant Client-Side)
          </span>
          <button @click="resetFilters" class="text-xs font-semibold text-blue-600 hover:underline cursor-pointer">
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
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
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">ENTITY</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity..."
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Ketik ID / Nama Cabang..."
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#4B5563] mb-1">CARI SPV</label>
            <input
              type="text"
              v-model="search"
              placeholder="Salescode, Nama, Cabang..."
              class="w-full px-3 py-2 text-xs border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table SPV -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">Salescode</th>
                <th class="px-4 py-3">Nama SPV</th>
                <th class="px-4 py-3">Cabang Distributor</th>
                <th class="px-4 py-3">Area</th>
                <th class="px-4 py-3">Status</th>
                <th v-if="canWrite" class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="filteredSpvs.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-[#9CA3AF] italic">
                  Data SPV tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="s in filteredSpvs" :key="s.id || s.salescode" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 font-mono font-bold text-[#111827]">{{ s.salescode }}</td>
                <td class="px-4 py-3 font-semibold text-[#374151]">{{ s.nama }}</td>
                <td class="px-4 py-3 text-[#4B5563]">
                  <strong>{{ s.branch_id }}</strong> - {{ s.branch_name || 'Branch' }}
                </td>
                <td class="px-4 py-3 text-[#059669] font-bold">{{ s.area || s.region_code || '-' }}</td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'px-2.5 py-0.5 text-[10.5px] font-bold rounded-md uppercase tracking-wider',
                      (s.is_active === 1 || s.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (s.is_active === 1 || s.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-4 py-3 text-right space-x-2">
                  <button @click="openEditModal(s)" class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">Edit</button>
                  <button @click="deleteSpv(s)" class="text-xs font-semibold text-red-600 hover:text-red-800 hover:underline">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH SPV -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Tambah Master SPV</h3>
        <form @submit.prevent="submitAddSpv" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Salescode / Username</label>
            <input v-model="addForm.salescode" type="text" placeholder="misal: SPV001" class="w-full p-2 border rounded-lg uppercase" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Password</label>
            <input v-model="addForm.password" type="password" placeholder="Password Login" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama SPV</label>
            <input v-model="addForm.nama" type="text" placeholder="Nama Lengkap" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Cabang Distributor</label>
            <SearchableSelect
              v-model="addForm.branch_id"
              :options="props.filterOptions?.branches || []"
              placeholder="Pilih Cabang"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Area (Opsional)</label>
            <input v-model="addForm.area" type="text" placeholder="misal: SUMUT 1" class="w-full p-2 border rounded-lg" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT SPV -->
    <div v-if="editingSpv" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Edit Master SPV ({{ editingSpv.salescode }})</h3>
        <form @submit.prevent="submitEditSpv" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama SPV</label>
            <input v-model="editForm.nama" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Password (Kosongkan jika tidak ubah)</label>
            <input v-model="editForm.password" type="password" placeholder="******" class="w-full p-2 border rounded-lg" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Cabang Distributor</label>
            <SearchableSelect
              v-model="editForm.branch_id"
              :options="props.filterOptions?.branches || []"
              placeholder="Pilih Cabang"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Area</label>
            <input v-model="editForm.area" type="text" class="w-full p-2 border rounded-lg" />
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_spv" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded" />
            <label for="is_active_spv" class="font-semibold text-slate-700">SPV Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="editingSpv = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Update</button>
          </div>
        </form>
      </div>
    </div>

    <!-- BULK UPLOAD MODAL -->
    <BulkUploadModal
      :isOpen="isBulkModalOpen"
      title="Bulk Import Master SPV Area (CSV)"
      uploadRoute="edp.master_spv.bulk"
      sampleCsvUrl="/samples/master_spv_sample.csv"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
