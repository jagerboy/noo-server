<script setup lang="js">
/**
 * Halaman Master Branch - Web Portal NOO+
 * Fitur: Instant Client-Side Filter Bertingkat (Region -> Entity -> Search) & Full CRUD (Create, Edit, Delete).
 * Form Tambah & Edit Master Cabang menggunakan Master List Region dan Entity standar (Cascading Dropdown).
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import BulkUploadModal from '@/Components/BulkUploadModal.vue';

const props = defineProps({
  branches: [Array, Object],
  canWrite: Boolean,
  filters: Object,
  filterOptions: Object,
});

// Master Predefined List Region
const MASTER_REGIONS = [
  { region_code: 'ASWJWA1', region_name: 'ASW JAWA 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWJWA2', region_name: 'ASW JAWA 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWPUL1', region_name: 'ASW PULAU 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM2', region_name: 'ASW SUMATERA 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM3', region_name: 'ASW SUMATERA 3', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'INAJWA1', region_name: 'INA JAWA 1', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAJWA2', region_name: 'INA JAWA 2', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAPUL1', region_name: 'INA PULAU 1', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM2', region_name: 'INA SUMATERA 2', principal_name: 'INAFOODS', principal_code: 'INA' },
];

// Master Predefined List Entity (Cascading by Region)
const MASTER_ENTITIES = [
  // ASWJWA1
  { region_code: 'ASWJWA1', region_name: 'ASW JAWA 1', entity_code_principal: 'ASW01', entity_name_principal: 'ASW JABODETABEK', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWJWA1', region_name: 'ASW JAWA 1', entity_code_principal: 'ASW02', entity_name_principal: 'ASW JAWA TIMUR 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWJWA1', region_name: 'ASW JAWA 1', entity_code_principal: 'ASW03', entity_name_principal: 'ASW JAWA TIMUR 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // ASWJWA2
  { region_code: 'ASWJWA2', region_name: 'ASW JAWA 2', entity_code_principal: 'ASW04', entity_name_principal: 'ASW JAWA BARAT', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWJWA2', region_name: 'ASW JAWA 2', entity_code_principal: 'ASW05', entity_name_principal: 'ASW JAWA TENGAH 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWJWA2', region_name: 'ASW JAWA 2', entity_code_principal: 'ASW06', entity_name_principal: 'ASW JAWA TENGAH 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // ASWPUL1
  { region_code: 'ASWPUL1', region_name: 'ASW PULAU 1', entity_code_principal: 'ASW07', entity_name_principal: 'ASW KALIMANTAN', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWPUL1', region_name: 'ASW PULAU 1', entity_code_principal: 'ASW08', entity_name_principal: 'ASW SULAWESI 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWPUL1', region_name: 'ASW PULAU 1', entity_code_principal: 'ASW09', entity_name_principal: 'ASW SULAWESI 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWPUL1', region_name: 'ASW PULAU 1', entity_code_principal: 'ASW10', entity_name_principal: 'ASW INDONESIA TIMUR', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // ASWSUM1
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', entity_code_principal: 'ASW11', entity_name_principal: 'ASW NAD 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', entity_code_principal: 'ASW12', entity_name_principal: 'ASW NAD 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', entity_code_principal: 'ASW13', entity_name_principal: 'ASW SUMUT 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', entity_code_principal: 'ASW14', entity_name_principal: 'ASW SUMUT 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM1', region_name: 'ASW SUMATERA 1', entity_code_principal: 'ASW15', entity_name_principal: 'ASW SUMUT 3', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // ASWSUM2
  { region_code: 'ASWSUM2', region_name: 'ASW SUMATERA 2', entity_code_principal: 'ASW16', entity_name_principal: 'ASW JAMBI', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM2', region_name: 'ASW SUMATERA 2', entity_code_principal: 'ASW17', entity_name_principal: 'ASW RIAU 1', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM2', region_name: 'ASW SUMATERA 2', entity_code_principal: 'ASW18', entity_name_principal: 'ASW RIAU 2', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM2', region_name: 'ASW SUMATERA 2', entity_code_principal: 'ASW19', entity_name_principal: 'ASW SUMBAR', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // ASWSUM3
  { region_code: 'ASWSUM3', region_name: 'ASW SUMATERA 3', entity_code_principal: 'ASW20', entity_name_principal: 'ASW BENGKULU', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM3', region_name: 'ASW SUMATERA 3', entity_code_principal: 'ASW21', entity_name_principal: 'ASW KEPULAUAN RIAU', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM3', region_name: 'ASW SUMATERA 3', entity_code_principal: 'ASW22', entity_name_principal: 'ASW LAMBABEL', principal_name: 'ASWFOODS', principal_code: 'ASW' },
  { region_code: 'ASWSUM3', region_name: 'ASW SUMATERA 3', entity_code_principal: 'ASW23', entity_name_principal: 'ASW SUMSEL', principal_name: 'ASWFOODS', principal_code: 'ASW' },

  // INAJWA1
  { region_code: 'INAJWA1', region_name: 'INA JAWA 1', entity_code_principal: 'INA01', entity_name_principal: 'INA JABODETABEK', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAJWA1', region_name: 'INA JAWA 1', entity_code_principal: 'INA02', entity_name_principal: 'INA JAWA TIMUR 1', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAJWA1', region_name: 'INA JAWA 1', entity_code_principal: 'INA03', entity_name_principal: 'INA JAWA TIMUR 2', principal_name: 'INAFOODS', principal_code: 'INA' },

  // INAJWA2
  { region_code: 'INAJWA2', region_name: 'INA JAWA 2', entity_code_principal: 'INA04', entity_name_principal: 'INA JAWA BARAT', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAJWA2', region_name: 'INA JAWA 2', entity_code_principal: 'INA05', entity_name_principal: 'INA JAWA TENGAH 1', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAJWA2', region_name: 'INA JAWA 2', entity_code_principal: 'INA06', entity_name_principal: 'INA JAWA TENGAH 2', principal_name: 'INAFOODS', principal_code: 'INA' },

  // INAPUL1
  { region_code: 'INAPUL1', region_name: 'INA PULAU 1', entity_code_principal: 'INA07', entity_name_principal: 'INA KALIMANTAN', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INAPUL1', region_name: 'INA PULAU 1', entity_code_principal: 'INA08', entity_name_principal: 'INA SULAWESI', principal_name: 'INAFOODS', principal_code: 'INA' },

  // INASUM1
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', entity_code_principal: 'INA09', entity_name_principal: 'INA NAD', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', entity_code_principal: 'INA10', entity_name_principal: 'INA RIAU', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', entity_code_principal: 'INA11', entity_name_principal: 'INA SUMUT', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', entity_code_principal: 'INA011', entity_name_principal: 'INA SUMBAR', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM1', region_name: 'INA SUMATERA 1', entity_code_principal: 'INA16', entity_name_principal: 'INA KEPRI', principal_name: 'INAFOODS', principal_code: 'INA' },

  // INASUM2
  { region_code: 'INASUM2', region_name: 'INA SUMATERA 2', entity_code_principal: 'INA12', entity_name_principal: 'INA BENGKULU', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM2', region_name: 'INA SUMATERA 2', entity_code_principal: 'INA13', entity_name_principal: 'INA JAMBI', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM2', region_name: 'INA SUMATERA 2', entity_code_principal: 'INA14', entity_name_principal: 'INA LAMPUNG', principal_name: 'INAFOODS', principal_code: 'INA' },
  { region_code: 'INASUM2', region_name: 'INA SUMATERA 2', entity_code_principal: 'INA015', entity_name_principal: 'INA SUMSEL', principal_name: 'INAFOODS', principal_code: 'INA' },
];

// Dynamic computed from Database props (fallback to MASTER_REGIONS / MASTER_ENTITIES)
const allRegions = computed(() => {
  const dbRegions = props.filterOptions?.regions;
  if (Array.isArray(dbRegions) && dbRegions.length > 0) {
    return dbRegions.map((r) => ({
      region_code: typeof r === 'object' ? r.region_code : r,
      region_name: typeof r === 'object' ? (r.region_name || r.region_code) : String(r),
      principal_name: typeof r === 'object' ? (r.principal_name || (String(r.region_code).startsWith('ASW') ? 'ASWFOODS' : 'INAFOODS')) : 'ASWFOODS',
      principal_code: typeof r === 'object' ? (r.principal_code || (String(r.region_code).startsWith('ASW') ? 'ASW' : 'INA')) : 'ASW',
    }));
  }
  return MASTER_REGIONS;
});

const allEntities = computed(() => {
  const dbEntities = props.filterOptions?.entities;
  if (Array.isArray(dbEntities) && dbEntities.length > 0) {
    return dbEntities.map((e) => ({
      region_code: typeof e === 'object' ? e.region_code : '',
      region_name: typeof e === 'object' ? e.region_name : '',
      entity_code_principal: typeof e === 'object' ? e.entity_code_principal : e,
      entity_name_principal: typeof e === 'object' ? (e.entity_name_principal || e.entity_code_principal) : String(e),
      principal_name: typeof e === 'object' ? (e.principal_name || (String(e.region_code || e.entity_code_principal).startsWith('ASW') ? 'ASWFOODS' : 'INAFOODS')) : 'ASWFOODS',
      principal_code: typeof e === 'object' ? (e.principal_code || (String(e.region_code || e.entity_code_principal).startsWith('ASW') ? 'ASW' : 'INA')) : 'ASW',
    }));
  }
  return MASTER_ENTITIES;
});

const search = ref(props.filters?.search || '');
const selectedRegion = ref(props.filters?.region_code || '');
const selectedEntity = ref(props.filters?.entity || '');

const isAddModalOpen = ref(false);
const editingBranch = ref(null);

// Options untuk Filter Bar
const regionOptions = computed(() => {
  return allRegions.value.map((r) => ({
    value: r.region_code,
    label: `${r.region_code} - ${r.region_name}`,
  }));
});

const entityOptions = computed(() => {
  let list = allEntities.value;
  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  return list.map((e) => ({
    value: e.entity_code_principal,
    label: `${e.entity_code_principal} - ${e.entity_name_principal}`,
  }));
});

// Watcher Reset Entity jika Region berubah di filter bar
watch(selectedRegion, (newReg) => {
  if (newReg && selectedEntity.value) {
    const valid = entityOptions.value.some((e) => e.value === selectedEntity.value);
    if (!valid) {
      selectedEntity.value = '';
    }
  }
});

// INSTANT CLIENT-SIDE COMPUTED FILTERING
const rawBranchesList = computed(() => {
  if (Array.isArray(props.branches)) return props.branches;
  if (props.branches && Array.isArray(props.branches.data)) return props.branches.data;
  return [];
});

const filteredBranches = computed(() => {
  let list = rawBranchesList.value;

  if (selectedRegion.value) {
    list = list.filter((b) => b.region_code === selectedRegion.value);
  }

  if (selectedEntity.value) {
    list = list.filter((b) => b.entity_code_principal === selectedEntity.value);
  }

  if (search.value) {
    const q = search.value.toLowerCase();
    list = list.filter(
      (b) =>
        (b.branch_id && String(b.branch_id).toLowerCase().includes(q)) ||
        (b.branch_name && String(b.branch_name).toLowerCase().includes(q)) ||
        (b.region_code && String(b.region_code).toLowerCase().includes(q))
    );
  }

  return list;
});

// FORM TAMBAH CABANG
const addForm = useForm({
  region_code: '',
  region_name: '',
  principal_name: 'ASWFOODS',
  principal_code: 'ASW',
  entity_code_principal: '',
  entity_name_principal: '',
  branch_id: '',
  branch_name: '',
  pin_branch: '123456',
  is_active: true,
});

const addRegionOptions = computed(() => {
  return allRegions.value.map((r) => ({
    value: r.region_code,
    label: `${r.region_code} - ${r.region_name}`,
  }));
});

const addEntityOptions = computed(() => {
  let list = allEntities.value;
  if (addForm.region_code) {
    list = list.filter((e) => e.region_code === addForm.region_code);
  }
  return list.map((e) => ({
    value: e.entity_code_principal,
    label: `${e.entity_code_principal} - ${e.entity_name_principal}`,
  }));
});

function onAddRegionChange(regCode) {
  const foundReg = allRegions.value.find((r) => r.region_code === regCode);
  if (foundReg) {
    addForm.region_name = foundReg.region_name;
    addForm.principal_name = foundReg.principal_name;
    addForm.principal_code = foundReg.principal_code;
  }
  // Reset Entity jika entity yang dipilih tidak berada di region baru
  if (addForm.entity_code_principal) {
    const valid = allEntities.value.some(
      (e) => e.region_code === regCode && e.entity_code_principal === addForm.entity_code_principal
    );
    if (!valid) {
      addForm.entity_code_principal = '';
      addForm.entity_name_principal = '';
    }
  }
}

function onAddEntityChange(entCode) {
  const foundEnt = allEntities.value.find((e) => e.entity_code_principal === entCode);
  if (foundEnt) {
    addForm.entity_name_principal = foundEnt.entity_name_principal;
    if (!addForm.region_code) {
      addForm.region_code = foundEnt.region_code;
      addForm.region_name = foundEnt.region_name;
      addForm.principal_name = foundEnt.principal_name;
      addForm.principal_code = foundEnt.principal_code;
    }
  }
}

function submitAddBranch() {
  addForm.post(route('edp.master_branch.store'), {
    onSuccess: () => {
      isAddModalOpen.value = false;
      addForm.reset();
    },
  });
}

// FORM EDIT CABANG
const editForm = useForm({
  region_code: '',
  region_name: '',
  principal_name: '',
  principal_code: '',
  entity_code_principal: '',
  entity_name_principal: '',
  branch_name: '',
  pin_branch: '',
  is_active: true,
});

const editEntityOptions = computed(() => {
  let list = allEntities.value;
  if (editForm.region_code) {
    list = list.filter((e) => e.region_code === editForm.region_code);
  }
  return list.map((e) => ({
    value: e.entity_code_principal,
    label: `${e.entity_code_principal} - ${e.entity_name_principal}`,
  }));
});

function onEditRegionChange(regCode) {
  const foundReg = allRegions.value.find((r) => r.region_code === regCode);
  if (foundReg) {
    editForm.region_name = foundReg.region_name;
    editForm.principal_name = foundReg.principal_name;
    editForm.principal_code = foundReg.principal_code;
  }
  if (editForm.entity_code_principal) {
    const valid = allEntities.value.some(
      (e) => e.region_code === regCode && e.entity_code_principal === editForm.entity_code_principal
    );
    if (!valid) {
      editForm.entity_code_principal = '';
      editForm.entity_name_principal = '';
    }
  }
}

function onEditEntityChange(entCode) {
  const foundEnt = allEntities.value.find((e) => e.entity_code_principal === entCode);
  if (foundEnt) {
    editForm.entity_name_principal = foundEnt.entity_name_principal;
    if (!editForm.region_code) {
      editForm.region_code = foundEnt.region_code;
      editForm.region_name = foundEnt.region_name;
      editForm.principal_name = foundEnt.principal_name;
      editForm.principal_code = foundEnt.principal_code;
    }
  }
}

function openEditModal(b) {
  editingBranch.value = b;
  editForm.region_code = b.region_code || '';
  const reg = allRegions.value.find((r) => r.region_code === b.region_code);
  editForm.region_name = b.region_name || (reg ? reg.region_name : '');
  editForm.principal_name = b.principal_name || (reg ? reg.principal_name : (b.region_code?.startsWith('ASW') ? 'ASWFOODS' : 'INAFOODS'));
  editForm.principal_code = b.principal_code || (reg ? reg.principal_code : (b.region_code?.startsWith('ASW') ? 'ASW' : 'INA'));
  editForm.entity_code_principal = b.entity_code_principal || '';
  const ent = allEntities.value.find((e) => e.entity_code_principal === b.entity_code_principal);
  editForm.entity_name_principal = b.entity_name_principal || (ent ? ent.entity_name_principal : '');
  editForm.branch_name = b.branch_name || '';
  editForm.pin_branch = b.pin_branch || '';
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

function resetFilters() {
  search.value = '';
  selectedRegion.value = '';
  selectedEntity.value = '';
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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight flex items-center gap-2">
            <span>Master Branch / Cabang Distributor</span>
          </h1>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Manajemen Master Cabang Distributor, PIN Branch, Region Code, dan Entitas Principal.
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
            <span>+ Tambah Cabang</span>
          </button>
        </div>
      </div>

      <!-- Filter Bar (Instant Client-Side Filtering) -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-[11.5px] font-semibold uppercase tracking-wider text-[#374151] flex items-center gap-2">
            Filter Data Cabang
          </span>
          <div class="flex items-center gap-3">
            <button
              @click="toggleShowPins"
              class="text-[10.5px] font-medium text-purple-700 hover:text-purple-900 cursor-pointer flex items-center gap-1 bg-purple-50 px-2 py-0.5 rounded border border-purple-200"
            >
              <span>{{ showAllPins ? 'Sembunyikan PIN Cabang' : 'Tampilkan PIN Cabang' }}</span>
            </button>
            <button @click="resetFilters" class="text-[10.5px] font-medium text-rose-500 hover:text-rose-700 hover:underline cursor-pointer">
              Reset Filter
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">ENTITY PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedEntity"
              :options="entityOptions"
              placeholder="-- Semua Entity --"
              searchPlaceholder="Ketik Entity Code / Nama..."
            />
          </div>

          <div>
            <label class="block text-[11.5px] font-medium text-slate-500 mb-1">CARI CABANG</label>
            <input
              type="text"
              v-model="search"
              placeholder="ID Cabang, Nama, Region..."
              class="w-full px-2.5 py-1.5 text-[12px] border border-[#D1D5DB] rounded-lg focus:ring-1 focus:ring-[#059669]"
            />
          </div>
        </div>
      </div>

      <!-- Table Branch -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase">
              <tr>
                <th class="px-3 py-3">ID Cabang</th>
                <th class="px-3 py-3">Nama Cabang</th>
                <th class="px-3 py-3">Region</th>
                <th class="px-3 py-3">Principal / Entity</th>
                <th class="px-3 py-3 select-none">
                  <div class="flex items-center gap-2 cursor-pointer group" @click="toggleShowPins" title="Klik untuk tampilkan / sembunyikan semua PIN branch">
                    <span>PIN Branch</span>
                    <span class="p-1 rounded-md bg-slate-100 group-hover:bg-slate-200 text-slate-600 transition flex items-center justify-center">
                      <svg v-if="showAllPins" class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      <svg v-else class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.025 10.025 0 012.122-.063c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21M3 3l18 18"/>
                      </svg>
                    </span>
                  </div>
                </th>
                <th class="px-3 py-3">Status</th>
                <th v-if="canWrite" class="px-3 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-if="filteredBranches.length === 0">
                <td colspan="7" class="px-3 py-8 text-center text-[#9CA3AF] italic">
                  Data Cabang tidak ditemukan untuk filter ini.
                </td>
              </tr>

              <tr v-for="b in filteredBranches" :key="b.id || b.branch_id" class="hover:bg-emerald-50/20 transition">
                <td class="px-3 py-2.5 font-bold text-[#111827] text-[13px]">{{ b.branch_id }}</td>
                <td class="px-3 py-2.5 text-[#374151]">{{ b.branch_name }}</td>
                <td class="px-3 py-2.5 text-[#059669] font-bold">
                  <div>{{ b.region_code }}</div>
                  <div v-if="b.region_name" class="text-[10px] text-slate-500 font-normal">{{ b.region_name }}</div>
                </td>
                <td class="px-3 py-2.5 text-[#6B7280] text-[11.5px]">
                  <span class="font-medium text-slate-700">{{ b.principal_name || (b.region_code?.startsWith('ASW') ? 'ASWFOODS' : 'INAFOODS') }}</span>
                  <span class="text-slate-500"> ({{ b.entity_code_principal }})</span>
                  <div v-if="b.entity_name_principal" class="text-[10.5px] text-slate-400 italic">{{ b.entity_name_principal }}</div>
                </td>
                <td class="px-3 py-2.5 font-mono font-bold text-gray-700">
                  <span v-if="showAllPins" class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 text-[10.5px]">
                    {{ b.pin_branch }}
                  </span>
                  <span v-else class="text-slate-400 text-[10.5px]">
                    ******
                  </span>
                </td>
                <td class="px-3 py-2.5">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[10.5px] font-semibold rounded-full uppercase tracking-wider',
                      (b.is_active === 1 || b.is_active === true)
                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                    ]"
                  >
                    {{ (b.is_active === 1 || b.is_active === true) ? 'AKTIF' : 'NON-AKTIF' }}
                  </span>
                </td>
                <td v-if="canWrite" class="px-3 py-2.5 text-right space-x-2">
                  <button @click="openEditModal(b)" class="text-[11.5px] font-semibold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer">Edit</button>
                  <button @click="deleteBranch(b)" class="text-[11.5px] font-semibold text-red-600 hover:text-red-800 hover:underline cursor-pointer">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH CABANG -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Tambah Master Cabang</h3>
          <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitAddBranch" class="space-y-3 text-xs">
          <!-- 1. Region Selector -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="addForm.region_code"
              :options="addRegionOptions"
              placeholder="-- Pilih Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="onAddRegionChange"
            />
          </div>

          <!-- 2. Entity Principal Selector -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Entity Principal <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="addForm.entity_code_principal"
              :options="addEntityOptions"
              :placeholder="addForm.region_code ? '-- Pilih Entity Principal --' : '-- Pilih Region Dahulu --'"
              searchPlaceholder="Ketik Entity Code / Nama..."
              @change="onAddEntityChange"
            />
          </div>

          <!-- 3. Branch ID -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Branch ID <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.branch_id"
              type="text"
              placeholder="misal: DAMDN003"
              class="w-full p-2 border rounded-lg uppercase"
              required
            />
          </div>

          <!-- 4. Nama Cabang -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Cabang <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.branch_name"
              type="text"
              placeholder="Nama Lengkap Cabang (misal: CV. DWI TUNGGAL SENTOSA)"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>

          <!-- 5. PIN Branch -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">PIN Branch <span class="text-rose-500">*</span></label>
            <input
              v-model="addForm.pin_branch"
              type="text"
              placeholder="PIN Branch (misal: 1111 / 123456)"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="addForm.processing || !addForm.region_code || !addForm.entity_code_principal || !addForm.branch_id || !addForm.branch_name || !addForm.pin_branch"
              class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857] disabled:opacity-50"
            >
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL EDIT CABANG -->
    <div v-if="editingBranch" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border">
        <div class="flex items-center justify-between border-b pb-2">
          <h3 class="text-base font-bold text-[#111827]">Edit Master Cabang ({{ editingBranch.branch_id }})</h3>
          <button @click="editingBranch = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        <form @submit.prevent="submitEditBranch" class="space-y-3 text-xs">
          <!-- 1. Region Selector -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Region <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="editForm.region_code"
              :options="addRegionOptions"
              placeholder="-- Pilih Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="onEditRegionChange"
            />
          </div>

          <!-- 2. Entity Principal Selector -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Entity Principal <span class="text-rose-500">*</span></label>
            <SearchableSelect
              v-model="editForm.entity_code_principal"
              :options="editEntityOptions"
              :placeholder="editForm.region_code ? '-- Pilih Entity Principal --' : '-- Pilih Region Dahulu --'"
              searchPlaceholder="Ketik Entity Code / Nama..."
              @change="onEditEntityChange"
            />
          </div>

          <!-- 3. Nama Cabang -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Nama Cabang <span class="text-rose-500">*</span></label>
            <input
              v-model="editForm.branch_name"
              type="text"
              class="w-full p-2 border rounded-lg"
              required
            />
          </div>

          <!-- 4. PIN Branch -->
          <div>
            <label class="block font-semibold text-slate-700 mb-1">PIN Branch (Isi jika mau ubah)</label>
            <input
              v-model="editForm.pin_branch"
              type="text"
              placeholder="******"
              class="w-full p-2 border rounded-lg"
            />
          </div>

          <!-- 5. Status Aktif -->
          <div class="flex items-center gap-2 pt-1">
            <input id="is_active_check" type="checkbox" v-model="editForm.is_active" class="w-4 h-4 text-emerald-600 rounded" />
            <label for="is_active_check" class="font-semibold text-slate-700 cursor-pointer">Cabang Aktif</label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button type="button" @click="editingBranch = null" class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">Batal</button>
            <button
              type="submit"
              :disabled="editForm.processing || !editForm.region_code || !editForm.entity_code_principal || !editForm.branch_name"
              class="px-4 py-2 bg-[#059669] text-white rounded-lg hover:bg-[#047857] disabled:opacity-50"
            >
              Update
            </button>
          </div>
        </form>
      </div>
    </div>

    <BulkUploadModal
      :isOpen="isBulkModalOpen"
      type="branch"
      title="Master Branch"
      @close="isBulkModalOpen = false"
    />
  </EdpLayout>
</template>

