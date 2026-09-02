<script setup lang="js">
/**
 * Komponen Reusable Modal Bulk Upload & Download Template Excel/CSV untuk Superadmin
 */
import { ref } from 'vue';
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
    <div v-if="isOpen" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99999] bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200">
      
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
          <span>📤 Bulk Upload {{ title }}</span>
        </h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
      </div>

      <div class="space-y-4">
        <!-- 1. Download Template Box -->
        <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-center justify-between">
          <div>
            <p class="text-xs font-bold text-emerald-900">Format Template (.CSV / Excel)</p>
            <p class="text-[11px] text-emerald-700 mt-0.5">Unduh template sesuai struktur tabel {{ title }}.</p>
          </div>
          <button
            type="button"
            @click="downloadTemplate"
            class="px-3 py-1.5 text-xs font-semibold text-white bg-[#059669] hover:bg-[#047857] rounded-lg transition shadow-xs whitespace-nowrap shrink-0 flex items-center gap-1.5 cursor-pointer"
          >
            <span>📥 Unduh Template</span>
          </button>
        </div>

        <!-- 2. Form Upload -->
        <form @submit.prevent="submitUpload" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Berkas CSV / Excel</label>
            <input
              type="file"
              accept=".csv, .txt, .xlsx, .xls"
              @change="handleFileChange"
              class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 border border-slate-300 rounded-xl cursor-pointer"
              required
            />
          </div>

          <span v-if="uploadForm.errors.file" class="text-xs text-red-600 font-semibold block">
            ⚠️ {{ uploadForm.errors.file }}
          </span>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              @click="emit('close')"
              class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="uploadForm.processing || !selectedFile"
              class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-xl transition shadow-xs disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
            >
              <span v-if="uploadForm.processing">Mengunggah...</span>
              <span v-else>📤 Upload & Import Data</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
  </Teleport>
</template>


