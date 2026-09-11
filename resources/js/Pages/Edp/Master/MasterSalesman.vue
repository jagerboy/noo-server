<script setup lang="js">
/**
 * Halaman Master Salesman - Web Portal NOO+
 * Fitur: Instant Client-Side Filter Bertingkat (Region -> Entity -> Branch -> Search) & Full CRUD.
 * Berlaku untuk semua role (SUPERADMIN, ADMIN_PRINCIPAL, EDP_REGION).
 * Tanpa URL Domain Parameter Pollution.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  salesmen: [Array, Object],
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedEntity = ref(props.filters?.entity || '');
const selectedBranch = ref(props.filters?.branch_id || '');

const isAddModalOpen = ref(false);
const editingSalesman = ref(null);

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => ({
    value: r.region_code || r,
    label: r.region_name ? `${r.region_code} - ${r.region_name}` : String(r.region_code || r),
  }));
});

// Cascading Filter Entity: Menampilkan entity yang tersedia berdasarkan Region yang dipilih (jika ada)
const entityOptions = computed(() => {
  let list = props.filterOptions?.entities || [];
  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  const seen = new Set();
  const res = [];
  for (const e of list) {
    const code = e.entity_code_principal || e;
    if (code && !seen.has(code)) {
      seen.add(code);
      res.push({
        value: code,
        label: e.entity_name_principal ? `${code} - ${e.entity_name_principal}` : String(code),
      });
    }
  }
  return res;
});

// Cascading Filter Branch: Menampilkan cabang sesuai pilihan Region dan/atau Entity
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

// Daftar seluruh cabang untuk pilihan dropdown modal Tambah/Edit Salesman
const allBranchOptions = computed(() => {
  return (props.filterOptions?.branches || []).map((b) => ({
    value: b.branch_id,
    label: `${b.branch_id} - ${b.branch_name}`,
  }));
});

// Watcher untuk mereset pilihan entity/branch jika tidak valid setelah region/entity berganti
watch(selectedRegion, (newReg) => {
  if (newReg) {
    if (selectedEntity.value) {
      const validEntity = entityOptions.value.some((e) => e.value === selectedEntity.value);
      if (!validEntity) {
        selectedEntity.value = '';
      }
    }
    if (selectedBranch.value) {
      const validBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
      if (!validBranch) {
        selectedBranch.value = '';
      }
    }
  }
});

watch(selectedEntity, (newEnt) => {
  if (newEnt && selectedBranch.value) {
    const validBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!validBranch) {
      selectedBranch.value = '';
    }
  }
});

// INSTANT CLIENT-SIDE COMPUTED FILTERING
const rawSalesmenList = computed(() => {
  if (Array.isArray(props.salesmen)) return props.salesmen;
  if (props.salesmen && Array.isArray(props.salesmen.data)) return props.salesmen.data;
  return [];
});

const filteredSalesmen = computed(() => {
  let list = rawSalesmenList.value;

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
        (s.salesman_code && String(s.salesman_code).toLowerCase().includes(q)) ||
        (s.salesman_name && String(s.salesman_name).toLowerCase().includes(q)) ||
        (s.branch_id && String(s.branch_id).toLowerCase().includes(q)) ||
        (s.branch_name && String(s.branch_name).toLowerCase().includes(q))
    );
  }

  return list;
});

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

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
  selectedBranch.value = '';
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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight flex items-center gap-2">
            <span>Master Salesman</span>
          </h1>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Manajemen Kode & Nama Salesman Terdaftar per Cabang Distributor.
          </p>
        </div>

        <div v-if="canWrite" class="flex items-center gap-2">
          <button
            @click="isBulkModalOpen = true"
            class="px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 rounded-lg hover:bg-emerald-100 transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>Bulk Import CSV</span>
          </button>
          <button
            @click="isAddModalOpen = true"
            class="px-3 py-1.5 text-[11.5px] font-semibold text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
          >
            <span>+ Tambah Salesman</span>
          </button>
        </div>
      </div>

      <!-- Filter Bar (Instant Client-Side Filtering) -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-[11.5px] font-semibold uppercase tracking-wider text-[#374151] flex items-center gap-2">
            Filter Data Salesman
          </span>
          <button @click="resetFilters" class="text-[10.5px] font-medium text-rose-500 hover:text-rose-700 hover:underline cursor-pointer">
            Reset Filter
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">ENTITY</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity..."
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Ketik ID / Nama Cabang..."
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">CARI SALESMAN</label>
            <input
              type="text"
              v-model="search"
              placeholder="Kode, Nama Salesman, ID Cabang..."
              class="w-full px-2.5 py-1.5 text-[12px] border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table Salesman -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase">
              <tr>
                <th class="px-3 py-3">Kode Salesman</th>
                <th class="px-3 py-3">Nama Salesman</th>
                <th class="px-3 py-3">Cabang Distributor</th>
                <th class="px-3 py-3">Entity</th>
                <th class="px-3 py-3">Region</th>
                <th class="px-3 py-3">Status</th>
                <th v-if="canWrite" class="px-3 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-if="filteredSalesmen.length === 0">
                <td colspan="7" class="px-3 py-8 text-center text-[#9CA3AF] italic">
                  Data Salesman tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="s in filteredSalesmen" :key="s.id || s.salesman_code" class="hover:bg-emerald-50/20 transition">
                <td class="px-3 py-2.5 font-mono font-bold text-[#111827] text-[12px]">{{ s.salesman_code }}</td>
                <td class="px-3 py-2.5 font-bold text-[#111827] text-[13px]">{{ s.salesman_name }}</td>
                <td class="px-3 py-2.5 text-[#4B5563]">
                  <strong>{{ s.branch_id }}</strong> - {{ s.branch_name || 'Branch' }}
                </td>
                <td class="px-3 py-2.5">
                  <span
                    v-if="s.entity_code_principal"
                    class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-slate-100 text-slate-700 border border-slate-300"
                  >
                    {{ s.entity_code_principal }}
                  </span>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="px-3 py-2.5 text-[#059669] font-bold">{{ s.region_code || '-' }}</td>
                <td class="px-3 py-2.5">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[10.5px] font-semibold rounded-full uppercase tracking-wider',
                      (s.is_active === 1 || s.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (s.is_active === 1 || s.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-3 py-2.5 text-right space-x-2">
                  <button @click="openEditModal(s)" class="text-[11.5px] font-semibold text-blue-600 hover:text-blue-800 hover:underline">Edit</button>
                  <button @click="deleteSalesman(s)" class="text-[11.5px] font-semibold text-red-600 hover:text-red-800 hover:underline">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH SALESMAN -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Tambah Master Salesman</h3>
        <form @submit.prevent="submitAddSalesman" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Kode Salesman</label>
            <input v-model="addForm.salesman_code" type="text" placeholder="misal: SEAMDN32" class="w-full p-2 border rounded-lg uppercase" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Salesman</label>
            <input v-model="addForm.salesman_name" type="text" placeholder="Nama Lengkap Salesman" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">ID Cabang Distributor</label>
            <SearchableSelect
              v-model="addForm.branch_id"
              :options="allBranchOptions"
              placeholder="Pilih Cabang"
            />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT SALESMAN -->
    <div v-if="editingSalesman" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <h3 class="text-base font-bold text-[#111827]">Edit Master Salesman ({{ editingSalesman.salesman_code }})</h3>
        <form @submit.prevent="submitEditSalesman" class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Salesman</label>
            <input v-model="editForm.salesman_name" type="text" class="w-full p-2 border rounded-lg" required />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">ID Cabang Distributor</label>
            <SearchableSelect
              v-model="editForm.branch_id"
              :options="allBranchOptions"
              placeholder="Pilih Cabang"
            />
          </div>
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_salesman" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded" />
            <label for="is_active_salesman" class="font-semibold text-slate-700">Salesman Aktif</label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="editingSalesman = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857]">Update</button>
          </div>
        </form>
      </div>
    </div>

    <BulkUploadModal
      :isOpen="isBulkModalOpen"
      type="salesman"
      title="Master Salesman"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>
