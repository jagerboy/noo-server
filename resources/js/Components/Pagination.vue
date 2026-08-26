<script setup lang="js">
/**
 * Komponen Pagination Laravel Inertia.js untuk Web Portal NOO+
 * Fitur: Navigasi Halaman, Display Rincian Data & Dynamic Per-Page Select (10, 25, 50, 100, All Data).
 */
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  links: {
    type: Array,
    default: () => [],
  },
  from: Number,
  to: Number,
  total: Number,
  currentPerPage: {
    type: [Number, String],
    default: 10,
  },
});

const perPageOptions = [
  { value: 10, label: '10' },
  { value: 25, label: '25' },
  { value: 50, label: '50' },
  { value: 100, label: '100' },
  { value: -1, label: 'Tampilkan Semua (Show All)' },
];

function getInitialPerPage() {
  const params = new URLSearchParams(window.location.search);
  const p = params.get('per_page');
  return p ? Number(p) : props.currentPerPage || 10;
}

const selectedPerPage = ref(getInitialPerPage());

function changePerPage() {
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', String(selectedPerPage.value));
  currentUrl.searchParams.set('page', '1');

  router.get(
    currentUrl.pathname + currentUrl.search,
    {},
    { preserveState: true, preserveScroll: true, replace: true }
  );
}
</script>

<template>
  <div v-if="total > 0 || (links && links.length > 3)" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3 bg-white border-t border-[#E5E7EB] text-xs">
    
    <!-- Left: Data Count Info & Per Page Selector -->
    <div class="flex items-center gap-4 flex-wrap text-[#6B7280]">
      <div>
        Menampilkan <span class="font-bold text-[#111827]">{{ from || 0 }}</span> sampai <span class="font-bold text-[#111827]">{{ to || 0 }}</span> dari <span class="font-bold text-[#111827]">{{ total || 0 }}</span> data
      </div>

      <!-- Select Jumlah Data per Halaman -->
      <div class="flex items-center gap-1.5 border-l border-[#E5E7EB] pl-4">
        <label class="text-[11px] font-semibold text-[#4B5563]">Tampilkan:</label>
        <select
          v-model="selectedPerPage"
          @change="changePerPage"
          class="px-2 py-1 text-[11px] font-semibold bg-[#F8FAFC] border border-[#CBD5E1] rounded-[6px] focus:ring-1 focus:ring-[#10B981] cursor-pointer text-[#1E293B]"
        >
          <option v-for="opt in perPageOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Right: Page Links -->
    <div v-if="links && links.length > 3" class="flex items-center gap-1 flex-wrap">
      <template v-for="(link, key) in links" :key="key">
        <div
          v-if="link.url === null"
          class="px-2.5 py-1 text-[11px] font-semibold text-[#9CA3AF] bg-[#F3F4F6] rounded-[6px] cursor-not-allowed border border-[#E5E7EB]"
          v-html="link.label"
        />
        <Link
          v-else
          :href="link.url"
          class="px-2.5 py-1 text-[11px] font-semibold rounded-[6px] border transition cursor-pointer"
          :class="
            link.active
              ? 'bg-[#10B981] text-white border-[#10B981] shadow-xs font-bold'
              : 'bg-white text-[#374151] border-[#D1D5DB] hover:bg-[#F9FAFB] hover:border-[#9CA3AF]'
          "
          v-html="link.label"
          preserve-scroll
        />
      </template>
    </div>
  </div>
</template>
