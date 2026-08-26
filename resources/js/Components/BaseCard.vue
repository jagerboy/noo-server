<script setup lang="js">
/**
 * Komponen Reusable BaseCard.
 * Menghasilkan wadah konten berstandar enterprise dengan slot header, body, dan footer.
 * 
 * Slots:
 * - #header: Judul kartu (font-heading font-semibold text-slate-900)
 * - default: Isi konten kartu (font-sans font-normal text-slate-600)
 * - #footer: Bagian bawah kartu (bg-slate-50 border-t)
 */
import { useSlots } from 'vue';

const slots = useSlots();
</script>

<template>
  <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden transition-all duration-200">
    <!-- Named Slot Header -->
    <div
      v-if="slots.header"
      class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4"
    >
      <div class="text-lg md:text-xl font-heading font-semibold text-slate-900 leading-tight">
        <slot name="header" />
      </div>
      <div v-if="slots.headerAction" class="shrink-0">
        <slot name="headerAction" />
      </div>
    </div>

    <!-- Default Slot Body -->
    <div class="p-5 sm:p-6 text-sm md:text-base font-sans font-normal text-slate-600 space-y-4">
      <slot />
    </div>

    <!-- Named Slot Footer -->
    <div
      v-if="slots.footer"
      class="px-5 py-4 bg-slate-50 border-t border-slate-200/80 rounded-b-xl flex items-center justify-between flex-wrap gap-3"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
