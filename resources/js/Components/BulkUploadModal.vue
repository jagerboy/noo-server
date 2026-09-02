<script setup lang="js">
/**
 * Komponen Reusable Modal Bulk Upload & Download Template Excel/CSV untuk Superadmin
 */
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  isOpen: Boolean,
  type: String, // 'branch', 'salesman', 'spv', 'counter_sequence'
  title: String,
});

const emit = defineEmits(['close']);

const selectedFile = ref(null);
const uploadForm = useForm({
  file: null,
});

// Definisi kolom & rincian template untuk tiap jenis master data
const templateDetails = computed(() => {
  switch (props.type) {
    case 'branch':
      return {
        columns: ['region_code', 'region_name', 'principal_code', 'principal_name', 'entity_code_principal', 'entity_name_principal', 'area_code', 'branch_id', 'branch_name', 'pin_branch'],
        sample: 'ASWSUM1, SUMATERA 1, A, ASWFOODS, ASW, ASWFOODS MEDAN, SUM1, DAMDN003, CV. DWI TUNGGAL SENTOSA, 123456'
      };
    case 'salesman':
      return {
        columns: ['salesman_code', 'salesman_name', 'branch_id', 'region_code', 'entity_code_principal'],
        sample: 'SEAMDN32, KURNIA SE, DAMDN003, ASWSUM1, ASW'
      };
    case 'spv':
      return {
        columns: ['salescode', 'nama', 'password', 'branch_id', 'area', 'distributor_name'],
        sample: 'SPVMEDAN01, BUDI SPV MEDAN, 123456, DAMDN003, SUM1, CV. DWI TUNGGAL SENTOSA'
      };
    case 'counter_sequence':
      return {
        columns: ['principal_code', 'area_code', 'branch_id', 'prefix', 'last_seq'],
        sample: 'A, SUM1, DAMDN003, MED, 15'
      };
    default:
      return { columns: [], sample: '' };
  }
});

function handleFileChange(e) {
  const file = e.target.files[0];
  if (file) {
    selectedFile.value = file;
    uploadForm.file = file;
  }
}

function submitUpload() {
  if (!uploadForm.file) return;

  uploadForm.post(route('edp.master.bulk_upload', props.type), {
    preserveScroll: true,
    onSuccess: () => {
      uploadForm.reset();
      selectedFile.value = null;
      emit('close');
    },
  });
}

function downloadTemplate() {
  window.open(route('edp.master.download_template', props.type), '_blank');
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-slate-900/65 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto transition-opacity">
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl border border-slate-100 transform transition-all">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg border border-emerald-100 shadow-xs">
              📊
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-800">
                Bulk Import {{ title }}
              </h3>
              <p class="text-[11px] text-slate-500">Unggah berkas data secara kolektif (Format .CSV / .XLSX)</p>
            </div>
          </div>
          <button 
            @click="emit('close')" 
            class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors font-bold text-lg"
          >
            &times;
          </button>
        </div>

        <div class="space-y-4">
          <!-- 1. Download Template Box -->
          <div class="p-4 bg-emerald-50/60 border border-emerald-200/80 rounded-xl space-y-2.5">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                  <span>📄 Format Standard Template (.CSV)</span>
                </p>
                <p class="text-[11px] text-emerald-700/90 mt-0.5">
                  Unduh template resmi agar penamaan kolom sesuai.
                </p>
              </div>
              <button
                type="button"
                @click="downloadTemplate"
                class="px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-xl transition shadow-xs whitespace-nowrap shrink-0 flex items-center gap-1.5 cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>Unduh Template</span>
              </button>
            </div>

            <!-- Previews of structure columns -->
            <div v-if="templateDetails.columns.length > 0" class="pt-2 border-t border-emerald-200/50">
              <p class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider mb-1">Struktur Kolom Wajib:</p>
              <div class="flex flex-wrap gap-1">
                <span 
                  v-for="(col, idx) in templateDetails.columns" 
                  :key="idx"
                  class="px-1.5 py-0.5 bg-white text-emerald-800 border border-emerald-200 text-[10px] font-mono rounded-md shadow-2xs"
                >
                  {{ col }}
                </span>
              </div>
            </div>
          </div>

          <!-- 2. Form Upload -->
          <form @submit.prevent="submitUpload" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Berkas CSV / Excel</label>
              <div class="relative">
                <input
                  type="file"
                  accept=".csv, .txt, .xlsx, .xls"
                  @change="handleFileChange"
                  class="w-full text-xs text-slate-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 border border-slate-300 rounded-xl cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                  required
                />
              </div>
              <p class="text-[10px] text-slate-400 mt-1">Mendukung format: .csv, .xlsx, .xls (Maks. 10MB)</p>
            </div>

            <span v-if="uploadForm.errors.file" class="text-xs text-red-600 font-semibold flex items-center gap-1">
              ⚠️ {{ uploadForm.errors.file }}
            </span>

            <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
              <button
                type="button"
                @click="emit('close')"
                class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 active:bg-slate-300 rounded-xl transition cursor-pointer"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="uploadForm.processing || !selectedFile"
                class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-xl transition shadow-xs disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="uploadForm.processing" class="flex items-center gap-1.5">
                  <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Mengunggah...
                </span>
                <span v-else class="flex items-center gap-1.5">
                  <span>📤 Upload & Import Data</span>
                </span>
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </Teleport>
</template>

