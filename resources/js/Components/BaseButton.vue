<script setup lang="js">
/**
 * Komponen Reusable BaseButton.
 * Digunakan sebagai standar tombol aksi di seluruh Portal Admin (Distributor, SPV, EDP Principal).
 * 
 * Props:
 * - variant: 'primary' | 'secondary' | 'outline' | 'danger'
 * - size: 'sm' | 'md' | 'lg'
 * - disabled: boolean
 * - loading: boolean
 * - type: 'button' | 'submit' | 'reset'
 */
import { computed, useAttrs } from 'vue';

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (val) => ['primary', 'secondary', 'outline', 'danger'].includes(val),
  },
  size: {
    type: String,
    default: 'md',
    validator: (val) => ['sm', 'md', 'lg'].includes(val),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: 'button',
  },
});

const attrs = useAttrs();

// Menggabungkan kelas varian warna sesuai Design System NOO
const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-noo-primary hover:bg-[#212a76] active:bg-[#1a2160] text-white border border-noo-primary shadow-2xs';
    case 'secondary':
      return 'bg-noo-secondary hover:bg-[#2e216a] active:bg-[#241a54] text-white border border-noo-secondary shadow-2xs';
    case 'outline':
      return 'bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-700 hover:text-slate-900 border border-slate-300 shadow-2xs';
    case 'danger':
      return 'bg-noo-danger hover:bg-[#c2122e] active:bg-[#9d0e25] text-white border border-noo-danger shadow-2xs';
    default:
      return 'bg-noo-primary text-white border border-noo-primary';
  }
});

// Mengatur padding & responsivitas teks secara proporsional
const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'px-3 py-1.5 text-xs font-semibold rounded-lg gap-1.5';
    case 'lg':
      return 'px-5 py-2.5 text-sm sm:text-base font-semibold rounded-xl gap-2.5';
    case 'md':
    default:
      return 'px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg gap-2';
  }
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    v-bind="attrs"
    :class="[
      'inline-flex items-center justify-center font-sans transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-noo-primary/30 disabled:opacity-50 disabled:cursor-not-allowed select-none',
      variantClasses,
      sizeClasses,
      $attrs.class
    ]"
  >
    <!-- Loading Spinner Native SVG -->
    <svg
      v-if="loading"
      class="animate-spin h-4 w-4 shrink-0 text-current"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <slot />
  </button>
</template>
