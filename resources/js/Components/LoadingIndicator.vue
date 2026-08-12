<script setup lang="js">
/**
 * Komponen Global Loading Indicator saat navigasi Inertia.js berjalan
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const isLoading = ref(false);

let removeStartEventListener;
let removeFinishEventListener;

onMounted(() => {
  removeStartEventListener = router.on('start', () => {
    isLoading.value = true;
  });
  removeFinishEventListener = router.on('finish', () => {
    isLoading.value = false;
  });
});

onUnmounted(() => {
  if (removeStartEventListener) removeStartEventListener();
  if (removeFinishEventListener) removeFinishEventListener();
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isLoading"
      class="fixed inset-0 bg-black/40 backdrop-blur-xs z-[9999] flex items-center justify-center p-4 transition-all duration-200"
    >
      <div class="bg-white/95 backdrop-blur-md px-4 py-3.5 rounded-xl shadow-xl border border-white/50 flex flex-col items-center gap-2 max-w-[180px]">
        <!-- Spinner animation -->
        <div class="relative w-6 h-6">
          <div class="w-6 h-6 rounded-full border-2 border-emerald-100 border-t-emerald-600 animate-spin"></div>
        </div>
        <div class="text-center">
          <p class="text-[11px] font-bold text-slate-800 tracking-wide leading-none">Memuat Data...</p>
          <p class="text-[9px] font-medium text-slate-500 mt-1 leading-none">Mohon tunggu sebentar</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
