<script setup lang="js">
/**
 * UI Login Bertingkat Admin Distributor - Dribbble Prototype Inspired.
 * Fitur:
 * - Panel Kiri: Form kredensial bertingkat ringkas tanpa scroll (Principal Area, Region, Entity, Branch full-width tanpa terpotong),
 *   single dropdown icon (tanpa duplikat), tombol reset saat opsi terpilih, dan PIN Branch format password dengan inline eye toggle.
 * - Panel Kanan: Slide animated interaktif bergaya Dribbble prototype dengan 4 tahapan alur NOO+:
 *   1. Salesman Input di Lapangan: Mockup visual inputan SE aplikasi Android NOO+ v2.0 (Data Toko, GPS Locked & Akurasi, serta 2 Foto Fisik Depan & Dalam).
 *   2. Admin Input Customer Code Distributor: Penginputan Customer Code distributor untuk mapping ke Eskalink.
 *   3. SPV Mengisi & Menentukan JKS: Penataan jadwal rute H1-H7 & M1-M4.
 *   4. Principal (EDP) Approval NOO: Validasi Customer Master dan inject data outlet baru ke Eskalink.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const props = defineProps({
  bootstrapData: {
    type: Object,
    default: () => ({}),
  },
});

const activeBootstrapData = ref({
  principals: props.bootstrapData?.principals || [],
  regionsByPrincipalGroup: props.bootstrapData?.regionsByPrincipalGroup || {},
  entitiesByRegion: props.bootstrapData?.entitiesByRegion || {},
  branchesByRegionEntity: props.bootstrapData?.branchesByRegionEntity || {},
});

const selectedPrincipalGroup = ref('');
const showPin = ref(false);
const rememberBranch = ref(false);
const isShaking = ref(false);
const pinInput = ref(null);

const form = useForm({
  principal_code: '',
  region_code: '',
  entity_code_principal: '',
  branch_id: '',
  pin_branch: '',
});

/**
 * Memicu efek getar (shake) saat login gagal atau kredensial keliru.
 */
function triggerShake() {
  isShaking.value = true;
  setTimeout(() => {
    isShaking.value = false;
  }, 650);
}

// -------------------------------------------------------------
// Bootstrapping Data & Remember Me Logic
// -------------------------------------------------------------
onMounted(async () => {
  if (!activeBootstrapData.value.principals || activeBootstrapData.value.principals.length === 0) {
    try {
      const res = await fetch(route('distributor_login.bootstrap'));
      const data = await res.json();
      if (data && data.ok) {
        activeBootstrapData.value = {
          principals: data.principals || [],
          regionsByPrincipalGroup: data.regionsByPrincipalGroup || {},
          entitiesByRegion: data.entitiesByRegion || {},
          branchesByRegionEntity: data.branchesByRegionEntity || {},
        };
      }
    } catch (e) {
      console.error('Gagal mengambil bootstrap data distributor client-side:', e);
    }
  }

  // Restore remembered branch info
  try {
    const saved = localStorage.getItem('noo_distributor_remembered_branch');
    if (saved) {
      const parsed = JSON.parse(saved);
      if (parsed.principal) selectedPrincipalGroup.value = parsed.principal;
      if (parsed.region) form.region_code = parsed.region;
      if (parsed.entity) form.entity_code_principal = parsed.entity;
      if (parsed.branch) form.branch_id = parsed.branch;
      form.principal_code = selectedPrincipalGroup.value;
      rememberBranch.value = true;
    }
  } catch (e) {}

  startAutoSlide();
});

onUnmounted(() => {
  stopAutoSlide();
});

// -------------------------------------------------------------
// Cascading Dropdown Selectors
// -------------------------------------------------------------
const principalList = computed(() => activeBootstrapData.value.principals || []);

const availableRegions = computed(() => {
  if (!selectedPrincipalGroup.value || !activeBootstrapData.value.regionsByPrincipalGroup?.[selectedPrincipalGroup.value]) {
    return [];
  }
  return activeBootstrapData.value.regionsByPrincipalGroup[selectedPrincipalGroup.value];
});

const availableEntities = computed(() => {
  if (!form.region_code || !activeBootstrapData.value.entitiesByRegion?.[form.region_code]) {
    return [];
  }
  return activeBootstrapData.value.entitiesByRegion[form.region_code];
});

const availableBranches = computed(() => {
  const reKey = `${form.region_code}||${form.entity_code_principal}`;
  if (!form.region_code || !form.entity_code_principal || !activeBootstrapData.value.branchesByRegionEntity?.[reKey]) {
    return [];
  }
  return activeBootstrapData.value.branchesByRegionEntity[reKey];
});

function onPrincipalGroupChange() {
  form.principal_code = selectedPrincipalGroup.value;
  form.region_code = '';
  form.entity_code_principal = '';
  form.branch_id = '';
  form.clearErrors();
}

function onRegionChange() {
  form.entity_code_principal = '';
  form.branch_id = '';
  form.clearErrors();
}

function onEntityChange() {
  form.branch_id = '';
  form.clearErrors();
}

// -------------------------------------------------------------
// Satu Tombol Reset untuk Semua Dropdown
// -------------------------------------------------------------
const hasSelectedDropdowns = computed(() => {
  return !!(
    selectedPrincipalGroup.value ||
    form.region_code ||
    form.entity_code_principal ||
    form.branch_id
  );
});

function resetAllDropdowns() {
  selectedPrincipalGroup.value = '';
  form.principal_code = '';
  form.region_code = '';
  form.entity_code_principal = '';
  form.branch_id = '';
  form.clearErrors();
}

// -------------------------------------------------------------
// Submit Handler
// -------------------------------------------------------------
function submitLogin() {
  form.principal_code = selectedPrincipalGroup.value;

  if (rememberBranch.value && form.branch_id) {
    try {
      localStorage.setItem('noo_distributor_remembered_branch', JSON.stringify({
        principal: selectedPrincipalGroup.value,
        region: form.region_code,
        entity: form.entity_code_principal,
        branch: form.branch_id,
      }));
    } catch (e) {}
  } else {
    try {
      localStorage.removeItem('noo_distributor_remembered_branch');
    } catch (e) {}
  }

  form.post(route('distributor_login.store'), {
    onError: () => {
      triggerShake();
      form.reset('pin_branch');
      pinInput.value?.focus();
    },
  });
}

// -------------------------------------------------------------
// Animated Slides (4 Tahapan Alur NOO+ - Dribbble Style)
// -------------------------------------------------------------
const currentSlide = ref(0);
const isHovered = ref(false);
let slideTimer = null;

const slides = [
  {
    stepNumber: '1',
    stepTitle: 'Salesman Input di Lapangan',
    title: 'Input Outlet Baru via Aplikasi NOO+',
    desc: 'Salesman mendata calon outlet baru langsung di lokasi toko, mengunci titik koordinat GPS presisi, serta melengkapi foto tampak depan dan dalam toko serta identitas pemilik.',
    role: 'Salesman Lapangan',
    roleTag: 'Aplikasi Mobile NOO+',
    accentColor: 'from-amber-400 to-orange-500',
    type: 'salesman',
  },
  {
    stepNumber: '2',
    stepTitle: 'Verifikasi & ERP Cabang',
    title: 'Admin Input Customer Code Distributor',
    desc: 'Admin cabang distributor memeriksa berkas legalitas toko, memverifikasi data toko, dan melampirkan Customer Code versi Distributor untuk di-mapping-kan ke Eskalink.',
    role: 'Admin Cabang',
    roleTag: 'Portal Distributor',
    accentColor: 'from-emerald-400 to-teal-500',
    type: 'admin',
  },
  {
    stepNumber: '3',
    stepTitle: 'Penataan Rute & Jadwal JKS',
    title: 'SPV Mengisi & Menentukan JKS',
    desc: 'Supervisor Area memvalidasi rute kunjungan toko, menentukan Jadwal Kunjungan Salesman (JKS: H1-H7 & M1-M4), serta memberikan persetujuan pengajuan.',
    role: 'SPV Area',
    roleTag: 'Portal SPV Area',
    accentColor: 'from-purple-400 to-indigo-500',
    type: 'spv',
  },
  {
    stepNumber: '4',
    stepTitle: 'Otorisasi Final Principal',
    title: 'Principal (EDP) Melakukan Approval NOO',
    desc: 'Tim Principal / EDP Pusat melakukan otorisasi akhir, validasi data Customer Master, dan melakukan inject data outlet baru ke Eskalink.',
    role: 'Principal / EDP',
    roleTag: 'Portal Principal NOO+',
    accentColor: 'from-rose-400 to-red-500',
    type: 'principal',
  },
];

function nextSlide() {
  currentSlide.value = (currentSlide.value + 1) % slides.length;
}

function prevSlide() {
  currentSlide.value = (currentSlide.value - 1 + slides.length) % slides.length;
}

function goToSlide(idx) {
  currentSlide.value = idx;
}

function startAutoSlide() {
  stopAutoSlide();
  slideTimer = setInterval(() => {
    if (!isHovered.value) {
      nextSlide();
    }
  }, 5500);
}

function stopAutoSlide() {
  if (slideTimer) {
    clearInterval(slideTimer);
    slideTimer = null;
  }
}
</script>

<template>
  <Head title="Sign In - Portal Admin Distributor NOO+" />

  <!-- Kanvas Luar: Bebas Scroll di Desktop & Laptop (max-h-screen) -->
  <div class="flex items-center justify-center min-h-screen md:max-h-screen bg-gradient-to-br from-[#E2E8F0] via-[#E9EFF7] to-[#DBE4F0] p-2 sm:p-3 md:p-4 lg:p-5 font-sans selection:bg-blue-100 select-none overflow-y-auto md:overflow-hidden">
    
    <!-- MAIN CARD CONTAINER DUAL-PANEL: Compact Fit to Viewport -->
    <div
      class="w-full max-w-md md:max-w-4xl lg:max-w-[980px] bg-white shadow-[0_20px_50px_-12px_rgba(15,23,42,0.15)] rounded-[22px] sm:rounded-[26px] lg:rounded-[28px] overflow-hidden grid grid-cols-1 md:grid-cols-12 border border-slate-300/80 my-auto transition-all duration-200"
      :class="{ 'animate-shake ring-2 ring-rose-400/30 border-rose-300': isShaking }"
    >
      
      <!-- ========================================================================= -->
      <!-- PANEL KIRI: FORM LOGIN KREDENSIAL DISTRIBUTOR (KOMPAK & TANPA SCROLL)       -->
      <!-- ========================================================================= -->
      <div class="md:col-span-6 lg:col-span-6 p-4 sm:p-4 md:p-5 lg:p-5 flex flex-col justify-between bg-white">
        <div>
          <!-- Brand Header Logo NOO+ & Label Portal (Compact) -->
          <div class="flex items-center justify-between gap-2 mb-1.5 sm:mb-2">
            <div class="flex items-center gap-2">
              <img
                src="/logo-noo-plus-v2.png"
                alt="Logo NOO+"
                class="h-8 sm:h-8.5 w-auto object-contain rounded shrink-0 drop-shadow-xs"
              />
              <div>
                <div class="flex items-center gap-1.5 leading-tight">
                  <h1 class="text-[13.5px] sm:text-[14.5px] font-bold text-slate-900 tracking-tight leading-none">
                    Portal Distributor
                  </h1>
                  <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold uppercase rounded bg-blue-100 text-[#1E2B7B] leading-none">
                    ADMIN
                  </span>
                </div>
                <p class="text-[10px] text-slate-500 font-medium mt-0.5 leading-none">
                  ASWFOODS &bull; INAFOODS
                </p>
              </div>
            </div>

            <!-- Status Pill -->
            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200/80 text-[9px] font-semibold text-slate-600">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Login Page</span>
            </div>
          </div>

          <!-- Sub-Header Judul Login (Ringkas) -->
          <div class="mb-1.5 sm:mb-2">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight leading-none">Selamat Datang!</h2>
            <p class="text-[10.5px] sm:text-[11px] text-slate-500 mt-0.5 leading-tight">
              Pilih Branch/Distributor dan masukkan PIN Branch Anda
            </p>
          </div>

          <!-- Alert Error Validasi / Login Gagal (Ringkas) -->
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform -translate-y-1 opacity-0 scale-95"
            enter-to-class="transform translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100 scale-100"
            leave-to-class="transform -translate-y-1 opacity-0 scale-95"
          >
            <div
              v-if="form.errors.pin_branch || form.errors.region_code || form.errors.branch_id"
              class="flex items-center gap-1.5 p-1.5 mb-1.5 rounded-lg bg-rose-50/95 border border-rose-200 text-rose-800 shadow-xs"
              role="alert"
            >
              <div class="p-0.5 bg-rose-100 text-rose-600 rounded shrink-0">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <p class="text-[10.5px] font-semibold text-rose-800 leading-tight">
                {{ form.errors.pin_branch || form.errors.branch_id || 'PIN Branch atau kombinasi cabang tidak valid.' }}
              </p>
            </div>
          </transition>

          <!-- FORM KREDENSIAL RINGKAS (Single Column Full-Width, Single Dropdown Icon, Reset Buttons) -->
          <form @submit.prevent="submitLogin" class="space-y-1.5">
            
            <!-- 1. PRINCIPAL AREA -->
            <div>
              <div class="flex items-center justify-between mb-0.5">
                <label class="block text-[9px] sm:text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                  Principal Area
                </label>
                <!-- SATU TOMBOL RESET UNTUK SEMUA DROPDOWN -->
                <button
                  v-if="hasSelectedDropdowns"
                  type="button"
                  @click="resetAllDropdowns"
                  class="text-[8.5px] sm:text-[9px] font-bold text-rose-500 hover:text-rose-700 hover:underline cursor-pointer flex items-center gap-0.5 transition"
                  title="Reset semua pilihan dropdown"
                >
                  <span>✕ Reset Pilihan</span>
                </button>
              </div>
              <div class="relative rounded-lg border border-slate-200 bg-slate-50/70 transition focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600/20 focus-within:bg-white shadow-2xs">
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <!-- bg-none & inline style untuk menjamin HANYA 1 icon chevron dropdown -->
                <select
                  v-model="selectedPrincipalGroup"
                  @change="onPrincipalGroupChange"
                  style="background-image: none !important;"
                  class="w-full pl-8 pr-7 py-1 text-[11.5px] sm:text-[12px] font-semibold text-slate-800 bg-transparent border-0 focus:ring-0 focus:outline-none transition cursor-pointer leading-tight truncate appearance-none bg-none"
                >
                  <option value="" disabled selected>Pilih Principal Area...</option>
                  <option v-for="p in principalList" :key="p.code" :value="p.code">
                    {{ p.label }}
                  </option>
                </select>
                <div class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- 2. REGION (Full Width: Text Tidak Terpotong) -->
            <div>
              <div class="flex items-center justify-between mb-0.5">
                <label class="block text-[9px] sm:text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                  Region
                </label>
              </div>
              <div
                class="relative rounded-lg border transition focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600/20 focus-within:bg-white shadow-2xs"
                :class="!selectedPrincipalGroup ? 'bg-slate-100/70 border-slate-200 opacity-60 cursor-not-allowed' : 'bg-slate-50/70 border-slate-200'"
              >
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <select
                  v-model="form.region_code"
                  @change="onRegionChange"
                  :disabled="!selectedPrincipalGroup || availableRegions.length === 0"
                  style="background-image: none !important;"
                  class="w-full pl-8 pr-7 py-1 text-[11.5px] sm:text-[12px] font-semibold text-slate-800 bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-slate-400 disabled:cursor-not-allowed transition cursor-pointer leading-tight truncate appearance-none bg-none"
                >
                  <option value="" disabled selected>
                    {{ selectedPrincipalGroup ? (availableRegions.length > 0 ? 'Pilih Region...' : 'Tidak ada region') : 'Pilih Principal Area dulu' }}
                  </option>
                  <option v-for="r in availableRegions" :key="r.code" :value="r.code">
                    {{ r.label }}
                  </option>
                </select>
                <div class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- 3. ENTITY PRINCIPAL (Full Width: Text Tidak Terpotong) -->
            <div>
              <div class="flex items-center justify-between mb-0.5">
                <label class="block text-[9px] sm:text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                  Entity Principal
                </label>
              </div>
              <div
                class="relative rounded-lg border transition focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600/20 focus-within:bg-white shadow-2xs"
                :class="!form.region_code ? 'bg-slate-100/70 border-slate-200 opacity-60 cursor-not-allowed' : 'bg-slate-50/70 border-slate-200'"
              >
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <select
                  v-model="form.entity_code_principal"
                  @change="onEntityChange"
                  :disabled="!form.region_code"
                  style="background-image: none !important;"
                  class="w-full pl-8 pr-7 py-1 text-[11.5px] sm:text-[12px] font-semibold text-slate-800 bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-slate-400 disabled:cursor-not-allowed transition cursor-pointer leading-tight truncate appearance-none bg-none"
                >
                  <option value="" disabled selected>
                    {{ form.region_code ? 'Pilih Entity Principal...' : 'Pilih Region dulu' }}
                  </option>
                  <option v-for="e in availableEntities" :key="e.code" :value="e.code">
                    {{ e.label }}
                  </option>
                </select>
                <div class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- 4. BRANCH / DISTRIBUTOR -->
            <div>
              <div class="flex items-center justify-between mb-0.5">
                <label class="block text-[9px] sm:text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                  Branch / Distributor
                </label>
              </div>
              <div
                class="relative rounded-lg border transition focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600/20 focus-within:bg-white shadow-2xs"
                :class="(!form.entity_code_principal || availableBranches.length === 0) ? 'bg-slate-100/70 border-slate-200 opacity-60 cursor-not-allowed' : 'bg-slate-50/70 border-slate-200'"
              >
                <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                  </svg>
                </div>
                <select
                  v-model="form.branch_id"
                  :disabled="!form.entity_code_principal || availableBranches.length === 0"
                  style="background-image: none !important;"
                  class="w-full pl-8 pr-7 py-1 text-[11.5px] sm:text-[12px] font-semibold text-slate-800 bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-slate-400 disabled:cursor-not-allowed transition cursor-pointer leading-tight truncate appearance-none bg-none"
                >
                  <option value="" disabled selected>
                    {{ form.entity_code_principal ? (availableBranches.length > 0 ? 'Pilih Cabang Distributor...' : 'Tidak ada cabang') : 'Pilih Entity dulu' }}
                  </option>
                  <option v-for="b in availableBranches" :key="b.branch_id || b.code" :value="b.branch_id || b.code">
                    {{ b.label || (b.branch_name ? `${b.branch_id || b.code} - ${b.branch_name}` : (b.branch_id || b.code)) }}
                  </option>
                </select>
                <div class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- 5. PIN BRANCH (FORMAT PASSWORD DENGAN INLINE EYE TOGGLE) -->
            <div>
              <div class="flex items-center justify-between mb-0.5">
                <label class="block text-[9px] sm:text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">
                  PIN Branch
                </label>
                <span class="text-[8.5px] text-slate-400 font-medium">Terproteksi</span>
              </div>
              <div class="relative">
                <input
                  ref="pinInput"
                  :type="showPin ? 'text' : 'password'"
                  v-model="form.pin_branch"
                  @input="form.clearErrors('pin_branch')"
                  placeholder="Masukkan PIN Branch"
                  required
                  class="w-full pl-3 pr-7 py-1 text-[12px] font-semibold rounded-lg border transition leading-tight shadow-2xs placeholder:font-normal placeholder:text-slate-400"
                  :class="form.errors.pin_branch ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:bg-white focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500/20' : 'border-slate-200 bg-slate-50/70 text-slate-800 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600/20'"
                />
                <!-- Tombol Inline Eye Toggle -->
                <button
                  type="button"
                  @click="showPin = !showPin"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-0.5 cursor-pointer"
                  :title="showPin ? 'Sembunyikan PIN' : 'Lihat PIN'"
                >
                  <svg v-if="!showPin" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- OPSI INGAT CABANG -->
            <div class="flex items-center justify-between pt-0.5">
              <label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] sm:text-[10.5px] text-slate-600 hover:text-slate-800 transition">
                <input
                  type="checkbox"
                  v-model="rememberBranch"
                  class="w-3 h-3 text-[#1E2B7B] border-slate-300 rounded focus:ring-[#1E2B7B] cursor-pointer"
                />
                <span>Ingat pilihan cabang di perangkat ini</span>
              </label>
            </div>

            <!-- TOMBOL LOGIN KORPORAT -->
            <button
              type="submit"
              :disabled="form.processing || !form.branch_id || !form.pin_branch"
              class="w-full py-1.5 sm:py-2 mt-0.5 text-[12.5px] sm:text-[13px] font-bold text-white transition-all bg-gradient-to-r from-[#1E2B7B] via-[#2563EB] to-[#1D4ED8] hover:brightness-110 active:scale-[0.99] rounded-xl shadow-md shadow-blue-900/20 border border-blue-400/20 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-1.5"
            >
              <svg
                v-if="form.processing"
                class="animate-spin h-3.5 w-3.5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span v-if="form.processing">Memvalidasi PIN...</span>
              <span v-else>Masuk ke Portal Distributor</span>
            </button>
          </form>
        </div>

        <!-- FOOTER IDENTITAS NOO+ (Kompak) -->
        <div class="mt-2 pt-1.5 border-t border-slate-100 text-center text-[9.5px] text-slate-400">
          Copyright &copy; 2026 <strong class="text-slate-600">Portal Layanan NOO+</strong> &bull; ASW &amp; INA Foods
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- PANEL KANAN: SLIDE ANIMATED ALUR NOO+ (DRIBBLE PROTOTYPE INSPIRED) (6 COLS) -->
      <!-- ========================================================================= -->
      <div
        class="hidden md:flex md:col-span-6 lg:col-span-6 relative overflow-hidden bg-gradient-to-br from-[#1E2B7B] via-[#1D4ED8] to-[#2563EB] select-none flex-col justify-between p-4 sm:p-5 lg:p-5 text-white group"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
      >
        <!-- Background Vector Grid & Floating Glowing Blobs -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff12_1px,transparent_1px),linear-gradient(to_bottom,#ffffff12_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-amber-400/25 rounded-full blur-2xl pointer-events-none animate-pulse-glow"></div>
        <div class="absolute -bottom-12 -left-12 w-52 h-52 bg-cyan-400/25 rounded-full blur-2xl pointer-events-none animate-pulse-glow" style="animation-delay: 2.5s;"></div>

        <!-- FLOATING CONFETTI SHAPES (Sesuai Referensi Dribbble Shot) -->
        <div class="absolute top-7 left-5 w-3 h-5 bg-amber-400/80 rounded-full rotate-45 animate-float-slow pointer-events-none"></div>
        <div class="absolute top-14 right-8 w-3.5 h-3.5 bg-emerald-300/80 rounded-sm rotate-12 animate-float-medium pointer-events-none"></div>
        <div class="absolute bottom-20 left-6 w-5 h-2.5 bg-rose-400/80 rounded-full -rotate-12 animate-float-slow pointer-events-none"></div>
        <div class="absolute bottom-12 right-10 w-3 h-3 bg-yellow-300/80 rounded-full animate-float-fast pointer-events-none"></div>

        <!-- TOP BAR: HEADER ALUR NOO+ & STEP BADGE -->
        <div class="relative z-10 flex items-center justify-between gap-2">
          <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-[9px] font-bold text-white uppercase tracking-wider shadow-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
            <span>ALUR SISTEM REGISTRASI NOO+</span>
          </div>

          <!-- Step Indicator Pill -->
          <div class="text-[9.5px] font-bold tracking-wider px-2 py-0.5 rounded-md bg-black/30 backdrop-blur-xs border border-white/15 text-blue-100">
            TAHAP {{ slides[currentSlide].stepNumber }} / 4
          </div>
        </div>

        <!-- CENTRAL MOCKUP CARDS CAROUSEL (Dribbble 3D-Card Look) -->
        <div class="relative z-10 my-auto py-1 flex flex-col items-center justify-center min-h-[255px]">
          
          <transition name="slide-card" mode="out-in">
            <!-- SLIDE 1: MOCKUP VISUAL SE INPUTAN APLIKASI NOO+ v2.0 (DATA TOKO, GPS TERKUNCI & 2 FOTO FISIK DEPAN/DALAM) -->
            <div v-if="currentSlide === 0" key="slide-0" class="relative w-full max-w-[340px] perspective-[1000px]">
              
              <!-- Android Phone Frame Mockup NOO+ v2.0 -->
              <div class="bg-slate-950 text-slate-800 rounded-2xl p-1.5 shadow-[0_16px_40px_rgba(0,0,0,0.35)] border border-slate-700/80 transform -rotate-1 hover:rotate-0 transition-transform duration-300">
                
                <!-- Inner Mobile Screen -->
                <div class="bg-white rounded-xl overflow-hidden border border-slate-200">
                  
                  <!-- Android App Bar SE NOO+ v2.0 -->
                  <div class="bg-[#1E2B7B] text-white px-2.5 py-1.5 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                      <span class="text-xs font-bold leading-none">‹</span>
                      <div>
                        <div class="text-[9.5px] font-extrabold tracking-tight leading-none flex items-center gap-1">
                          <span>NOO+ v2.0</span>
                        </div>
                        <div class="text-[7.5px] text-blue-200 font-medium leading-none mt-0.5">
                          Form Registrasi Outlet Baru
                        </div>
                      </div>
                    </div>
                    <span class="text-[7.5px] bg-white/20 text-white font-mono px-1.5 py-0.5 rounded font-bold">
                      SEASW-042
                    </span>
                  </div>

                  <!-- Form Inputan SE Lengkap (Sesuai Aplikasi Mobile NOO+ v2.0) -->
                  <div class="p-2 space-y-1.5 bg-slate-50/70 text-slate-800">
                    
                    <!-- Form Field 1: Nama Toko & Tipe Outlet (Inputan SE) -->
                    <div class="bg-white p-1.5 rounded-lg border border-slate-200 shadow-3xs">
                      <div class="flex items-center justify-between">
                        <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-wider">1. Data Toko Baru</span>
                        <span class="text-[7px] font-bold px-1 rounded bg-blue-50 text-blue-700 border border-blue-200">GT01 - Star Outlet</span>
                      </div>
                      <div class="text-[10.5px] font-extrabold text-slate-900 leading-tight mt-0.5">
                        TOKO SUMBER REZEKI
                      </div>
                      <div class="text-[8px] text-slate-500 leading-none mt-0.5 flex items-center justify-between">
                        <span>Pemilik: <strong>Bpk. Hendra Wijaya</strong></span>
                        <span class="font-mono text-slate-600">0812-3456-7890</span>
                      </div>
                      <div class="text-[7.5px] text-slate-400 truncate mt-0.5">
                        Alamat: Jl. Krakatau No. 12, Kel. Glugur, Medan
                      </div>
                    </div>

                    <!-- Form Field 2: Koordinat GPS Terkunci (Inputan SE) -->
                    <div class="bg-emerald-50/90 border border-emerald-300/80 rounded-lg p-1.5 flex items-center justify-between">
                      <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-md bg-emerald-600 text-white flex items-center justify-center text-[10px] shrink-0 font-bold">
                          📍
                        </div>
                        <div>
                          <div class="text-[7.5px] font-black text-emerald-800 uppercase tracking-wider flex items-center gap-1">
                            <span>GPS LOCKED (TERKUNCI)</span>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                          </div>
                          <div class="text-[9px] font-mono font-bold text-emerald-950 leading-none mt-0.5">
                            3.5382521, 98.717929
                          </div>
                        </div>
                      </div>
                      <div class="text-right">
                        <span class="text-[7.5px] font-extrabold text-emerald-800 bg-emerald-200/80 px-1 py-0.5 rounded">
                          ✓ Akurasi 15 m
                        </span>
                      </div>
                    </div>

                    <!-- Form Field 3: Dua Foto Fisik Toko (Tampak Depan & Tampak Dalam Inputan SE) -->
                    <div>
                      <div class="flex items-center justify-between mb-0.5">
                        <span class="text-[7.5px] font-bold text-slate-500 uppercase tracking-wider">2. Foto Fisik Toko (Wajib)</span>
                        <span class="text-[7px] font-bold text-emerald-600">✓ 2 Foto Terlampir</span>
                      </div>
                      <div class="grid grid-cols-2 gap-1.5">
                        
                        <!-- Thumbnail 1: Foto Tampak Depan -->
                        <div class="relative rounded-lg overflow-hidden border border-slate-300 bg-slate-100 aspect-4/3 flex flex-col justify-end p-1">
                          <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-800/25 to-amber-100/50 flex items-center justify-center">
                            <span class="text-xl opacity-90 drop-shadow-xs">🏪</span>
                          </div>
                          <div class="relative z-10 flex items-center justify-between">
                            <span class="text-[7px] font-extrabold text-white bg-black/70 px-1 rounded truncate">
                              Foto Depan
                            </span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 text-slate-950 flex items-center justify-center text-[7px] font-black">✓</span>
                          </div>
                        </div>

                        <!-- Thumbnail 2: Foto Tampak Dalam -->
                        <div class="relative rounded-lg overflow-hidden border border-slate-300 bg-slate-100 aspect-4/3 flex flex-col justify-end p-1">
                          <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-800/25 to-blue-100/50 flex items-center justify-center">
                            <span class="text-xl opacity-90 drop-shadow-xs">🛒</span>
                          </div>
                          <div class="relative z-10 flex items-center justify-between">
                            <span class="text-[7px] font-extrabold text-white bg-black/70 px-1 rounded truncate">
                              Foto Dalam
                            </span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 text-slate-950 flex items-center justify-center text-[7px] font-black">✓</span>
                          </div>
                        </div>

                      </div>
                    </div>

                    <!-- Tombol Aksi Submit Inputan SE -->
                    <div class="pt-0.5">
                      <div class="w-full py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-center rounded text-[8.5px] font-extrabold tracking-wider flex items-center justify-center gap-1 shadow-xs">
                        <span>💾</span> SIMPAN &amp; SUBMIT DATA TOKO
                      </div>
                    </div>

                  </div>

                </div>

              </div>

              <!-- Floating Accent Badge (Dribbble Overlap) -->
              <div class="absolute -bottom-2 -right-2 bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 px-2.5 py-1 rounded-lg shadow-md border border-white/60 transform rotate-3 flex items-center gap-1.5">
                <span class="text-xs">⚡</span>
                <span class="text-[9px] font-black uppercase tracking-tight">Inputan Salesman &bull; Sync Realtime</span>
              </div>

            </div>

            <!-- SLIDE 2: ADMIN MENGISI CUSTOMER CODE DISTRIBUTOR -->
            <div v-else-if="currentSlide === 1" key="slide-1" class="relative w-full max-w-[340px] perspective-[1000px]">
              <div class="bg-white text-slate-800 rounded-2xl p-3 shadow-[0_16px_40px_rgba(0,0,0,0.28)] border border-white/80 transform rotate-1 hover:rotate-0 transition-transform duration-300">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 mb-1.5">
                  <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded bg-emerald-500/15 text-emerald-600 flex items-center justify-center text-[10px] font-bold">🏢</span>
                    <span class="text-[10.5px] font-bold text-slate-800">Admin Distributor</span>
                  </div>
                  <span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[8px] font-extrabold">
                    Input Customer Code
                  </span>
                </div>

                <div class="text-[11.5px] font-bold text-slate-900 leading-snug">
                  Penginputan Customer Code
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">
                  Distributor: <strong>BR01 - MEDAN (PT. Sumber Rezeki)</strong>
                </div>

                <!-- Input Box Mockup -->
                <div class="mt-1.5 p-1.5 bg-blue-50/70 border border-blue-200/80 rounded-lg">
                  <div class="text-[8px] uppercase font-bold text-blue-800 tracking-wider">
                    Customer Code Versi Distributor
                  </div>
                  <div class="text-[11.5px] font-mono font-black text-[#1E2B7B] tracking-wider mt-0.5">
                    CUST-78921-MDN
                  </div>
                </div>

                <div class="mt-1.5 flex items-center justify-between text-[9px] text-slate-500">
                  <span class="flex items-center gap-1 text-emerald-600 font-semibold">
                    <span>✓</span> Toko Valid
                  </span>
                  <span>Siap ke SPV</span>
                </div>
              </div>

              <!-- Floating Mini Card Overlap -->
              <div class="absolute -top-2 -left-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-2 py-0.5 rounded-lg shadow-md border border-white/40 transform -rotate-3 flex items-center gap-1">
                <span class="text-[9px]">🏷️</span>
                <span class="text-[8.5px] font-extrabold uppercase tracking-wide">Data Cabang Terverifikasi</span>
              </div>
            </div>

            <!-- SLIDE 3: SPV MENGISI & MENENTUKAN JKS -->
            <div v-else-if="currentSlide === 2" key="slide-2" class="relative w-full max-w-[340px] perspective-[1000px]">
              <div class="bg-white text-slate-800 rounded-2xl p-3 shadow-[0_16px_40px_rgba(0,0,0,0.28)] border border-white/80 transform -rotate-1 hover:rotate-0 transition-transform duration-300">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 mb-1.5">
                  <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded bg-purple-500/15 text-[#542B85] flex items-center justify-center text-[10px] font-bold">🧭</span>
                    <span class="text-[10.5px] font-bold text-slate-800">Supervisor Area Portal</span>
                  </div>
                  <span class="px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-[8px] font-extrabold">
                    JKS Active
                  </span>
                </div>

                <div class="text-[11.5px] font-bold text-slate-900 leading-snug">
                  Penetapan Jadwal Kunjungan (JKS)
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">
                  Validasi Rute &amp; Pola Distribusi Toko
                </div>

                <!-- JKS Schedule Tags -->
                <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                  <div class="p-1 rounded-lg bg-purple-50 border border-purple-100 text-center">
                    <span class="text-[7.5px] text-purple-600 block font-bold uppercase">Hari Kunjungan</span>
                    <span class="text-[10px] font-extrabold text-[#542B85]">Senin (H1)</span>
                  </div>
                  <div class="p-1 rounded-lg bg-blue-50 border border-blue-100 text-center">
                    <span class="text-[7.5px] text-blue-600 block font-bold uppercase">Minggu Rute</span>
                    <span class="text-[10px] font-extrabold text-[#1E2B7B]">M1 &amp; M3 (Ganjil)</span>
                  </div>
                </div>

                <div class="mt-1 text-center text-[8.5px] font-semibold text-emerald-600 bg-emerald-50 py-0.5 rounded border border-emerald-100">
                  ✓ Radius Outlet &amp; GPS Layak Dikunjungi
                </div>
              </div>

              <!-- Floating Mini Card Overlap -->
              <div class="absolute -bottom-2 -left-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-2 py-0.5 rounded-lg shadow-md border border-white/40 transform -rotate-2 flex items-center gap-1">
                <span class="text-[9px]">🗓️</span>
                <span class="text-[8.5px] font-extrabold uppercase tracking-wide">Rute JKS Terjadwal</span>
              </div>
            </div>

            <!-- SLIDE 4: PRINCIPAL (EDP) MELAKUKAN APPROVAL NOO -->
            <div v-else-if="currentSlide === 3" key="slide-3" class="relative w-full max-w-[340px] perspective-[1000px]">
              <div class="bg-white text-slate-800 rounded-2xl p-3 shadow-[0_16px_40px_rgba(0,0,0,0.28)] border border-white/80 transform rotate-1 hover:rotate-0 transition-transform duration-300">
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 mb-1.5">
                  <div class="flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded bg-rose-500/15 text-[#D9232A] flex items-center justify-center text-[10px] font-bold">👑</span>
                    <span class="text-[10.5px] font-bold text-slate-800">Portal Principal (EDP)</span>
                  </div>
                  <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[8px] font-black tracking-wide uppercase">
                    APPROVED
                  </span>
                </div>

                <div class="text-[11.5px] font-bold text-slate-900 leading-snug">
                  Customer Master &amp; Inject Eskalink
                </div>
                <div class="text-[9px] text-slate-500 mt-0.5">
                  ASWFOODS &bull; INAFOODS Master Data
                </div>

                <!-- Master Verified Box -->
                <div class="mt-1.5 p-1.5 bg-gradient-to-br from-red-50 via-slate-50 to-amber-50 rounded-lg border border-red-100">
                  <div class="flex items-center justify-between text-[8.5px] text-slate-600">
                    <span>ID Outlet Nasional:</span>
                    <span class="font-mono font-bold text-slate-900">NOO-2026-0894</span>
                  </div>
                  <div class="flex items-center justify-between text-[8.5px] text-slate-600 mt-0.5">
                    <span>Inject Status:</span>
                    <span class="font-bold text-emerald-600">APPROVED (Active)</span>
                  </div>
                </div>

                <div class="mt-1 text-center text-[8.5px] font-medium text-slate-400">
                  Data siap di-inject dan toko siap dikunjungi.
                </div>
              </div>

              <!-- Floating Mini Card Overlap -->
              <div class="absolute -bottom-2 -right-1.5 bg-gradient-to-r from-red-600 to-rose-600 text-white px-2 py-0.5 rounded-lg shadow-md border border-white/40 transform rotate-2 flex items-center gap-1">
                <span class="text-[9px]">🎉</span>
                <span class="text-[8.5px] font-extrabold uppercase tracking-wide">Outlet didaftarkan</span>
              </div>
            </div>
          </transition>

        </div>

        <!-- BOTTOM AREA: SLIDE TEXT & PAGINATION INDICATORS (Compact Dribbble Layout) -->
        <div class="relative z-10 pt-1">
          <transition name="fade-text" mode="out-in">
            <div :key="currentSlide" class="text-center">
              <h3 class="text-[14px] sm:text-[15px] font-extrabold text-white tracking-tight leading-tight drop-shadow-md">
                {{ slides[currentSlide].title }}
              </h3>
              <p class="text-[10.5px] sm:text-[11px] text-blue-100/90 leading-snug font-normal mt-0.5 max-w-[360px] mx-auto drop-shadow-xs">
                {{ slides[currentSlide].desc }}
              </p>
            </div>
          </transition>

          <!-- Interactive Pagination Dots & Navigation Chevrons -->
          <div class="flex items-center justify-center gap-2 mt-2">
            <!-- Prev Button -->
            <button
              type="button"
              @click="prevSlide"
              class="w-5.5 h-5.5 rounded-full bg-white/10 hover:bg-white/25 active:scale-95 transition flex items-center justify-center text-white/80 hover:text-white cursor-pointer"
              title="Slide Sebelumnya"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <!-- Morphing Dots -->
            <div class="flex items-center gap-1.5">
              <button
                v-for="(s, idx) in slides"
                :key="idx"
                @click="goToSlide(idx)"
                type="button"
                class="h-1.5 rounded-full transition-all duration-300 cursor-pointer"
                :class="currentSlide === idx ? 'w-5 bg-white shadow-xs' : 'w-1.5 bg-white/40 hover:bg-white/70'"
                :title="`Buka Tahap ${idx + 1}`"
              ></button>
            </div>

            <!-- Next Button -->
            <button
              type="button"
              @click="nextSlide"
              class="w-5.5 h-5.5 rounded-full bg-white/10 hover:bg-white/25 active:scale-95 transition flex items-center justify-center text-white/80 hover:text-white cursor-pointer"
              title="Slide Selanjutnya"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
/* Transisi Halus Kartu 3D Mockup */
.slide-card-enter-active,
.slide-card-leave-active {
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-card-enter-from {
  opacity: 0;
  transform: translateY(16px) scale(0.94);
}

.slide-card-leave-to {
  opacity: 0;
  transform: translateY(-16px) scale(0.94);
}

/* Transisi Teks Deskripsi Slide */
.fade-text-enter-active,
.fade-text-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-text-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.fade-text-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Floating Confetti Animations */
@keyframes floatSlow {
  0%, 100% {
    transform: translateY(0) rotate(0deg);
  }
  50% {
    transform: translateY(-8px) rotate(8deg);
  }
}

@keyframes floatMedium {
  0%, 100% {
    transform: translateY(0) rotate(12deg);
  }
  50% {
    transform: translateY(-10px) rotate(24deg);
  }
}

@keyframes floatFast {
  0%, 100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-6px) scale(1.1);
  }
}

@keyframes pulseGlow {
  0%, 100% {
    opacity: 0.2;
    transform: scale(1);
  }
  50% {
    opacity: 0.4;
    transform: scale(1.08);
  }
}

.animate-float-slow {
  animation: floatSlow 5s ease-in-out infinite;
}

.animate-float-medium {
  animation: floatMedium 4s ease-in-out infinite;
}

.animate-float-fast {
  animation: floatFast 3s ease-in-out infinite;
}

.animate-pulse-glow {
  animation: pulseGlow 6s ease-in-out infinite;
}

/* Animasi Getar (Shake Horizontal) saat Otentikasi Gagal */
@keyframes shakeHorizontal {
  0%, 100% {
    transform: translateX(0);
  }
  15% {
    transform: translateX(-8px) rotate(-0.5deg);
  }
  30% {
    transform: translateX(7px) rotate(0.5deg);
  }
  45% {
    transform: translateX(-5px);
  }
  60% {
    transform: translateX(4px);
  }
  75% {
    transform: translateX(-2px);
  }
  90% {
    transform: translateX(1px);
  }
}

.animate-shake {
  animation: shakeHorizontal 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

@media (prefers-reduced-motion: reduce) {
  .animate-shake,
  .animate-float-slow,
  .animate-float-medium,
  .animate-float-fast,
  .animate-pulse-glow {
    animation: none !important;
  }
}
</style>
