<script setup lang="js">
/**
 * Reusable Searchable Select Dropdown Component (Vue 3 Composition API).
 * Dilengkapi Input Search Box di dalam dropdown untuk pencarian cepat data berurut alfabetis.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: [String, Number],
  options: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: '-- Pilih --',
  },
  searchPlaceholder: {
    type: String,
    default: 'Ketik untuk mencari...',
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);

const formattedOptions = computed(() => {
  return props.options.map((item) => {
    if (typeof item === 'object' && item !== null) {
      return {
        value: item.value ?? item.branch_id ?? item.region_code,
        label: item.label ?? (item.branch_id ? `${item.branch_id} - ${item.branch_name}` : (item.region_code ? `${item.region_code} - ${item.region_name || ''}` : String(item.value))),
      };
    }
    return { value: item, label: String(item) };
  });
});

const filteredOptions = computed(() => {
  if (!searchQuery.value) return formattedOptions.value;
  const q = searchQuery.value.toLowerCase();
  return formattedOptions.value.filter((opt) => opt.label.toLowerCase().includes(q) || String(opt.value).toLowerCase().includes(q));
});

const selectedLabel = computed(() => {
  const found = formattedOptions.value.find((opt) => opt.value === props.modelValue);
  return found ? found.label : props.placeholder;
});

function toggleDropdown() {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    searchQuery.value = '';
  }
}

function selectOption(opt) {
  emit('update:modelValue', opt.value);
  emit('change', opt.value);
  isOpen.value = false;
  searchQuery.value = '';
}

function handleClickOutside(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div ref="containerRef" class="relative w-full text-xs">
    <!-- Trigger Button -->
    <button
      type="button"
      @click="toggleDropdown"
      class="w-full px-3 py-2 text-left bg-white border border-[#D1D5DB] rounded-lg shadow-xs flex items-center justify-between focus:ring-2 focus:ring-[#10B981] transition"
    >
      <span :class="['truncate', modelValue ? 'font-bold text-[#111827]' : 'text-gray-400']">
        {{ selectedLabel }}
      </span>
      <svg :class="['w-4 h-4 text-gray-400 transition-transform ml-2 flex-shrink-0', isOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <!-- Dropdown Menu Box -->
    <div
      v-if="isOpen"
      class="absolute z-50 left-0 right-0 mt-1 bg-white border border-[#E5E7EB] rounded-xl shadow-xl overflow-hidden max-h-60 flex flex-col"
    >
      <!-- Search Input Box inside Dropdown -->
      <div class="p-2 border-b border-gray-100 bg-gray-50 sticky top-0 z-10">
        <input
          type="text"
          v-model="searchQuery"
          :placeholder="searchPlaceholder"
          class="w-full px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-md focus:ring-2 focus:ring-[#10B981] outline-none"
          ref="searchInput"
          @click.stop
        />
      </div>

      <!-- Options List -->
      <div class="overflow-y-auto flex-1 divide-y divide-gray-50">
        <div
          @click="selectOption({ value: '', label: props.placeholder })"
          class="px-3 py-2 hover:bg-gray-100 cursor-pointer text-gray-500 italic"
        >
          {{ props.placeholder }}
        </div>

        <div
          v-for="opt in filteredOptions"
          :key="opt.value"
          @click="selectOption(opt)"
          :class="[
            'px-3 py-2 cursor-pointer transition flex items-center justify-between',
            opt.value === props.modelValue ? 'bg-emerald-50 text-[#059669] font-bold' : 'hover:bg-emerald-50/50 text-[#374151]'
          ]"
        >
          <span class="truncate">{{ opt.label }}</span>
          <span v-if="opt.value === props.modelValue" class="text-xs text-[#059669]">✓</span>
        </div>

        <div v-if="filteredOptions.length === 0" class="p-3 text-center text-gray-400 italic">
          Data tidak ditemukan.
        </div>
      </div>
    </div>
  </div>
</template>
