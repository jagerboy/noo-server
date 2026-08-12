<script setup lang="js">
/**
 * Component Toast Notification Mengambang di Pojok Kiri Atas.
 * Menampilkan notifikasi realtime untuk event Approved, Rejected, Updated, & Reset
 * dengan informasi detail nama toko.
 */
import { ref, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let nextId = 1;

function addToast(type, message) {
  if (!message) return;
  const id = nextId++;
  toasts.value.push({
    id,
    type, // 'success' | 'error' | 'info'
    message,
    timer: setTimeout(() => {
      removeToast(id);
    }, 5000),
  });
}

function removeToast(id) {
  const idx = toasts.value.findIndex((t) => t.id === id);
  if (idx !== -1) {
    clearTimeout(toasts.value[idx].timer);
    toasts.value.splice(idx, 1);
  }
}

// Watch untuk flash messages dari Inertia response
watch(
  () => page.props.flash,
  (newFlash) => {
    if (newFlash?.success) {
      addToast('success', newFlash.success);
    }
    if (newFlash?.error) {
      addToast('error', newFlash.error);
    }
    if (newFlash?.info) {
      addToast('info', newFlash.info);
    }
  },
  { deep: true, immediate: true }
);

// Watch untuk Inertia errors prop
watch(
  () => page.props.errors,
  (newErrors) => {
    if (newErrors && Object.keys(newErrors).length > 0) {
      const firstErrKey = Object.keys(newErrors)[0];
      const firstErrVal = newErrors[firstErrKey];
      if (firstErrVal) {
        addToast('error', firstErrVal);
      }
    }
  },
  { deep: true, immediate: true }
);

onMounted(() => {
  if (page.props.flash?.success) {
    addToast('success', page.props.flash.success);
  }
  if (page.props.flash?.error) {
    addToast('error', page.props.flash.error);
  }
  if (page.props.errors && Object.keys(page.props.errors).length > 0) {
    const firstErrKey = Object.keys(page.props.errors)[0];
    if (page.props.errors[firstErrKey]) {
      addToast('error', page.props.errors[firstErrKey]);
    }
  }
});
</script>

<template>
  <div class="fixed top-5 left-5 z-[9999] flex flex-col gap-2.5 max-w-md w-full pointer-events-none">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform -translate-x-full opacity-0 scale-95"
      enter-to-class="transform translate-x-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-x-0 opacity-100 scale-100"
      leave-to-class="transform -translate-x-full opacity-0 scale-95"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto flex items-start justify-between gap-3 p-4 rounded-xl shadow-xl border backdrop-blur-md transition-all',
          t.type === 'success'
            ? 'bg-emerald-900/95 border-emerald-500/50 text-white'
            : t.type === 'error'
            ? 'bg-rose-900/95 border-rose-500/50 text-white'
            : 'bg-indigo-900/95 border-indigo-500/50 text-white'
        ]"
      >
        <div class="flex items-start gap-3">
          <!-- Icon -->
          <div class="mt-0.5 shrink-0">
            <span v-if="t.type === 'success'" class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 text-sm font-bold border border-emerald-400/30">✓</span>
            <span v-else-if="t.type === 'error'" class="flex items-center justify-center w-6 h-6 rounded-full bg-rose-500/20 text-rose-400 text-sm font-bold border border-rose-400/30">✕</span>
            <span v-else class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-400 text-sm font-bold border border-indigo-400/30">ℹ</span>
          </div>

          <div class="flex flex-col">
            <span class="text-xs font-bold uppercase tracking-wider text-white/70">
              {{ t.type === 'success' ? 'Berhasil' : t.type === 'error' ? 'Peringatan / Penolakan' : 'Informasi System' }}
            </span>
            <span class="text-sm font-medium mt-0.5 leading-snug break-words">
              {{ t.message }}
            </span>
          </div>
        </div>

        <button
          @click="removeToast(t.id)"
          class="shrink-0 p-1 text-white/60 hover:text-white rounded-lg hover:bg-white/10 transition cursor-pointer"
          title="Tutup Notifikasi"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
