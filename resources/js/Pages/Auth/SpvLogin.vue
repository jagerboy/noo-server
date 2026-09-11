<script setup lang="js">
/**
 * UI Login Khusus Supervisor Area - Dual Panel Layout.
 * Ditingkatkan dengan:
 * - Sisi Kiri: Form kredensial modern, inline toggle password (eye/eye-off icon),
 *   dukungan 'Ingat Salescode', animasi shake saat gagal, dan alert modern.
 * - Sisi Kanan: Foto Pabrik ASW Foods Revisi dengan text blur fade overlay elegan.
 */
import { ref, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const props = defineProps({
  metrics: {
    type: Object,
    default: () => ({
      total: 0,
      pendingAdmin: 0,
      pushedToSpv: 0,
      approvedSpv: 0,
      approvedEdp: 0,
      rejected: 0,
    }),
  },
});

const showPassword = ref(false);
const rememberMe = ref(false);
const isShaking = ref(false);
const passwordInput = ref(null);

const form = useForm({
  username: '',
  password: '',
});

/**
 * Memicu animasi getar (shake animation) saat login gagal.
 */
function triggerShake() {
  isShaking.value = true;
  setTimeout(() => {
    isShaking.value = false;
  }, 650);
}

onMounted(() => {
  try {
    const remembered = localStorage.getItem('noo_spv_remembered_salescode');
    if (remembered) {
      form.username = remembered;
      rememberMe.value = true;
    }
  } catch (e) {
    console.error('Gagal membaca localStorage:', e);
  }
});

function submitLogin() {
  form.username = (form.username || '').toUpperCase().trim();

  if (rememberMe.value && form.username) {
    try {
      localStorage.setItem('noo_spv_remembered_salescode', form.username);
    } catch (e) {}
  } else {
    try {
      localStorage.removeItem('noo_spv_remembered_salescode');
    } catch (e) {}
  }

  form.post(route('spv_login.store'), {
    onError: () => {
      triggerShake();
      form.reset('password');
      passwordInput.value?.focus();
    },
  });
}
</script>

<template>
  <Head title="Sign In - Portal SPV Area NOO+" />

  <!-- Background Kanvas Halaman yang Lembut & Kontras Elegan (Ramah Desktop & Tablet) -->
  <div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-[#E2E8F0] via-[#E8EDF5] to-[#DCE4EF] p-3 sm:p-4 md:p-6 lg:p-8 font-sans selection:bg-purple-100 select-none overflow-y-auto">
    
    <!-- Main Card Container Split Elegan: Responsif Tablet (md: 768px+) & Desktop (lg: 1024px+) -->
    <div
      class="w-full max-w-md md:max-w-3xl lg:max-w-[940px] bg-white shadow-[0_20px_60px_-15px_rgba(15,23,42,0.12)] rounded-2xl md:rounded-[28px] lg:rounded-[32px] overflow-hidden grid grid-cols-1 md:grid-cols-12 border border-slate-300/80 my-auto transition-all duration-200"
      :class="{ 'animate-shake ring-2 ring-rose-400/30 border-rose-300': isShaking }"
    >
      
      <!-- PANEL KIRI: LOGO NOO+, BRAND HEADER, FORM KREDENSIAL SPV (6 COLS DI TABLET & DESKTOP) -->
      <div class="md:col-span-6 lg:col-span-6 p-5 sm:p-6 md:p-6 lg:p-8 flex flex-col justify-between bg-white">
        <div>
          <!-- Header Brand dengan Logo NOO+ & Identitas Korporat -->
          <div class="flex items-center gap-2.5 md:gap-3 mb-4 md:mb-5">
            <img
              src="/logo-noo-plus-v2.png"
              alt="Logo NOO+"
              class="h-8 sm:h-9 md:h-10 w-auto object-contain rounded-lg shrink-0 drop-shadow-xs"
            />
            <div>
              <div class="flex items-center gap-1.5">
                <h1 class="text-sm sm:text-base md:text-[17px] font-bold text-slate-900 tracking-tight leading-none">
                  Portal SPV Area
                </h1>
                <span class="px-1.5 py-0.5 text-[9px] md:text-[9.5px] font-extrabold uppercase rounded bg-purple-100 text-[#542B85]">
                  SPV
                </span>
              </div>
              <p class="text-[11px] md:text-[11.5px] text-slate-500 font-medium mt-1 flex items-center gap-1">
                <span>ASWFOODS</span> &bull; <span>INAFOODS</span>
              </p>
            </div>
          </div>

          <!-- Section Sub-Header Judul Login -->
          <div class="mb-4 md:mb-5">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Sign In</h2>
            <p class="text-[12px] md:text-[12.5px] text-slate-500 mt-0.5 md:mt-1">
              Masuk menggunakan Salescode dan Password sesuai akun Eskamobile Anda
            </p>
          </div>

          <!-- Alert Error Validasi / Login Gagal -->
          <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform -translate-y-2 opacity-0 scale-95"
            enter-to-class="transform translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100 scale-100"
            leave-to-class="transform -translate-y-2 opacity-0 scale-95"
          >
            <div
              v-if="form.errors.username || form.errors.password"
              class="flex items-center gap-2 p-2.5 md:p-3 mb-3.5 md:mb-4 rounded-xl bg-rose-50/95 border border-rose-200 text-rose-800 shadow-xs"
              role="alert"
            >
              <div class="p-1 bg-rose-100 text-rose-600 rounded-lg shrink-0">
                <svg class="w-3.5 h-3.5 md:w-4 md:h-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <p class="text-[11.5px] md:text-[12px] font-semibold text-rose-800 leading-snug">
                {{ form.errors.username || form.errors.password || 'Salescode atau Password yang Anda masukkan salah.' }}
              </p>
            </div>
          </transition>

          <!-- Form Input Kredensial -->
          <form @submit.prevent="submitLogin" class="space-y-3.5 md:space-y-4">
            <!-- 1. SALESCODE -->
            <div>
              <label class="block text-[10.5px] md:text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                Salescode
              </label>
              <div class="relative">
                <input
                  type="text"
                  v-model="form.username"
                  @input="form.clearErrors('username')"
                  placeholder="Masukkan Salescode"
                  required
                  class="w-full pl-3.5 pr-10 py-2 md:py-2.5 text-[13px] md:text-[13.5px] font-semibold uppercase rounded-xl border transition leading-tight shadow-2xs placeholder:normal-case placeholder:font-normal placeholder:text-slate-400"
                  :class="form.errors.username ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:bg-white focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:border-[#542B85] focus:ring-2 focus:ring-[#542B85]/15'"
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- 2. PASSWORD DENGAN INLINE EYE TOGGLE -->
            <div>
              <label class="block text-[10.5px] md:text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                Password
              </label>
              <div class="relative">
                <input
                  ref="passwordInput"
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.password"
                  @input="form.clearErrors('password', 'username')"
                  placeholder="Masukkan Password"
                  required
                  class="w-full pl-3.5 pr-10 py-2 md:py-2.5 text-[13px] md:text-[13.5px] font-semibold rounded-xl border transition leading-tight shadow-2xs placeholder:font-normal placeholder:text-slate-400"
                  :class="(form.errors.password || form.errors.username) ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:bg-white focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:border-[#542B85] focus:ring-2 focus:ring-[#542B85]/15'"
                />
                <!-- Tombol Toggle Show / Hide Password Berupa Eye Icon Elegan -->
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-1 cursor-pointer"
                  title="Lihat Password"
                >
                  <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- OPSI INGAT SALESCODE -->
            <div class="flex items-center justify-between pt-0.5">
              <label class="flex items-center gap-2 cursor-pointer select-none text-[11.5px] md:text-[12px] text-slate-600 hover:text-slate-800 transition">
                <input
                  type="checkbox"
                  v-model="rememberMe"
                  class="w-4 h-4 text-[#542B85] border-slate-300 rounded focus:ring-[#542B85] cursor-pointer"
                />
                <span class="font-medium">Ingat Salescode di perangkat ini</span>
              </label>
            </div>

            <!-- LOGIN BUTTON KORPORAT DUAL-BRAND GRADIENT -->
            <button
              type="submit"
              :disabled="form.processing || !form.username || !form.password"
              class="w-full py-2.5 mt-1.5 md:mt-2 text-[13.5px] md:text-[14px] font-bold text-white transition-all bg-gradient-to-r from-[#542B85] via-[#3B1F60] to-[#1E2B7B] hover:brightness-110 active:scale-[0.99] rounded-xl shadow-md shadow-[#542B85]/20 border border-purple-400/20 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
            >
              <svg
                v-if="form.processing"
                class="animate-spin h-4 w-4 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span v-if="form.processing">Memproses Masuk...</span>
              <span v-else>Masuk ke Portal SPV</span>
            </button>
          </form>
        </div>

        <!-- FOOTER COPYRIGHT IDENTITAS NOO+ -->
        <div class="mt-5 md:mt-6 pt-2.5 md:pt-3 border-t border-slate-100 text-center text-[10.5px] md:text-[11px] text-slate-500">
          Copyright &copy; 2026 <strong class="text-slate-700">Portal SPV Area NOO+</strong>
        </div>
      </div>

      <!-- PANEL KANAN: FOTO PABRIK ASW FOODS REVISI DITINGGIKAN & TEXT BLUR FADE RINGKAS (6 COLS DI TABLET & DESKTOP) -->
      <div class="hidden md:flex md:col-span-6 lg:col-span-6 relative overflow-hidden bg-slate-900 select-none min-h-[480px] md:min-h-[510px] lg:min-h-[540px]">
        
        <!-- Background Foto Pabrik ASW Foods Revisi (Dinaikkan agar bangunan pabrik & atap lengkung kuning tampak jelas dan tidak tertutup teks) -->
        <img
          src="/Photo-Pabrik-ASW-Foods-Revisi.jpg"
          alt="Pabrik ASW Foods"
          class="absolute inset-0 w-full h-full object-cover object-[center_70%] sm:object-[center_72%] transform -translate-y-8 md:-translate-y-10 scale-[1.18] md:scale-[1.20] hover:scale-[1.25] transition-transform duration-700 ease-out"
        />

        <!-- Subtle Top Vignette -->
        <div class="absolute inset-x-0 top-0 h-24 md:h-28 bg-gradient-to-b from-black/50 via-black/20 to-transparent pointer-events-none z-10"></div>

        <!-- Badge Floating Atas -->
        <div class="absolute top-4 sm:top-5 md:top-6 left-4 sm:left-5 md:left-6 z-20">
          <div class="inline-flex items-center gap-1.5 md:gap-2 px-2.5 md:px-3 py-1 md:py-1.5 rounded-full bg-black/45 backdrop-blur-md border border-white/25 text-[9.5px] md:text-[10.5px] font-bold tracking-wider text-white uppercase shadow-lg">
            <span class="w-1.5 h-1.5 md:w-2 md:h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>PORTAL SUPERVISOR AREA</span>
          </div>
        </div>

        <!-- Text Overlay Bawah dengan Efek Blur Fade Ringkas (Hanya menutup bayangan jalan di dasar, bangunan pabrik tetap 100% bebas terlihat) -->
        <div class="absolute inset-x-0 bottom-0 z-20 pt-10 md:pt-12 pb-4 md:pb-5 px-5 md:px-6 bg-gradient-to-t from-black/95 via-black/75 to-transparent backdrop-blur-[2px] flex flex-col justify-end text-white">
          <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-500/25 border border-amber-400/40 text-[9.5px] md:text-[10px] font-bold text-amber-300 uppercase tracking-wide mb-1.5 w-fit">
            <span>ASWFOODS &bull; INAFOODS</span>
          </div>

          <h2 class="text-base sm:text-lg md:text-xl font-extrabold text-white tracking-tight leading-snug drop-shadow-md">
            NOO+ (New Open Outlet) - SPV Area
          </h2>

          <p class="text-[11.5px] md:text-[12px] text-white/90 leading-snug mt-1 font-normal drop-shadow-sm line-clamp-2 sm:line-clamp-none">
            Pusat verifikasi dan otorisasi Outlet Baru (NOO+), validasi koordinat GPS toko, dan penataan rute kunjungan salesman area terpadu.
          </p>

          <div class="mt-2.5 pt-2 border-t border-white/20 flex items-center justify-between text-[10px] md:text-[10.5px] text-white/70 font-medium">
            <span>Sistem Monitoring Lapangan</span>
            <span class="text-amber-300 font-semibold">NOO+ Realtime Engine</span>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
/* Animasi Getar (Shake Effect) Saat Terjadi Kegagalan Autentikasi */
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
  .animate-shake {
    animation: none !important;
  }
}
</style>
