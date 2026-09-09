<script setup lang="js">
/**
 * UI Login Portal Principal / EDP NOO+ (Vue 3 Composition API).
 * Disesuaikan dengan instruksi pengguna:
 * - Background halaman diberi warna berkarakter (soft corporate slate-tinted canvas).
 * - Split card rounded proporsional di tengah layar.
 * - Sisi Kiri: Form login bersih tanpa social buttons, tanpa sign up, tanpa forgot password.
 * - Sisi Kanan: Seni fluid aurora mesh gradient yang murni menggunakan warna utama perusahaan:
 *   ASWFOODS (Bold Red #D9232A & Navy Blue #1E2B7B) dan INAFOODS (Royal Purple #542B85 & Crown Gold #F59E0B).
 */
import { ref, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const showPassword = ref(false);
const rememberMe = ref(false);
const savedAccounts = ref([]);
const isShaking = ref(false);
const passwordInput = ref(null);

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

/**
 * Memicu animasi getar (shake animation) saat terjadi kegagalan autentikasi.
 */
function triggerShake() {
  isShaking.value = true;
  setTimeout(() => {
    isShaking.value = false;
  }, 650);
}

onMounted(() => {
  try {
    const raw = localStorage.getItem('noo_saved_edp_accounts');
    if (raw) {
      savedAccounts.value = JSON.parse(raw);
    }
    const lastUser = localStorage.getItem('noo_remembered_username');
    if (lastUser) {
      form.username = lastUser;
      rememberMe.value = true;
    }
  } catch (e) {
    console.error('Error membaca localStorage:', e);
  }
});

/**
 * Memilih akun tersimpan secara instan.
 * @param {string} username
 */
function selectSavedAccount(username) {
  form.username = username;
  form.clearErrors();
}

/**
 * Menghapus akun dari memori lokal peramban.
 * @param {string} username
 */
function removeSavedAccount(username) {
  savedAccounts.value = savedAccounts.value.filter((u) => u !== username);
  try {
    localStorage.setItem('noo_saved_edp_accounts', JSON.stringify(savedAccounts.value));
    if (form.username === username) {
      form.username = '';
    }
  } catch (e) {}
}

/**
 * Mengirimkan data autentikasi ke backend Laravel.
 */
function submitLogin() {
  form.remember = rememberMe.value;

  if (rememberMe.value && form.username) {
    try {
      localStorage.setItem('noo_remembered_username', form.username);
      if (!savedAccounts.value.includes(form.username)) {
        savedAccounts.value.push(form.username);
        localStorage.setItem('noo_saved_edp_accounts', JSON.stringify(savedAccounts.value));
      }
    } catch (e) {}
  } else {
    try {
      localStorage.removeItem('noo_remembered_username');
    } catch (e) {}
  }

  form.post(route('edp_login.store'), {
    onError: () => {
      triggerShake();
      form.reset('password');
      passwordInput.value?.focus();
    },
  });
}
</script>

<template>
  <Head title="Login - Portal Principal NOO+" />

  <!-- Kanvas Luar: Background Berwarna Kontras Lembut & Elegan -->
  <div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-[#E2E8F0] via-[#E8EDF5] to-[#DCE4EF] p-4 sm:p-6 lg:p-10 font-sans selection:bg-rose-100 select-none">
    
    <!-- KARTU UTAMA: Split Card Rounded dengan Border Halus -->
    <div class="w-full max-w-4xl lg:max-w-[940px] bg-white rounded-[28px] sm:rounded-[32px] border border-slate-300/80 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.12)] overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[510px] max-h-[620px] my-auto">
      
      <!-- SISI KIRI: RUANG UNTUK ELEVATED FLOATING LOGIN CARD (6 COLS) -->
      <div class="md:col-span-6 flex items-center justify-center p-6 sm:p-8 lg:p-10 bg-white relative">
        
        <!-- Elevated Floating Card (Dengan Animasi Shake Responsif saat Error) -->
        <div
          class="w-full max-w-[340px] sm:max-w-[360px] bg-white rounded-2xl sm:rounded-[22px] p-6 sm:p-7 shadow-[0_12px_35px_rgba(0,0,0,0.06)] border border-slate-100 flex flex-col justify-between transition-all duration-200"
          :class="{ 'animate-shake ring-2 ring-rose-400/30 border-rose-200': isShaking }"
        >
          
          <div>
            <!-- Header Brand: Logo NOO+ & Portal Principal (Tanpa Teks ASW • INA) -->
            <div class="flex items-center gap-2 mb-3">
              <img src="/logo-noo-plus.png" alt="Logo NOO+" class="h-6 w-auto object-contain rounded drop-shadow-2xs" />
              <span class="text-[12.5px] font-bold text-[#D9232A] tracking-tight">Portal Principal</span>
            </div>

            <!-- Judul "Login" Besar & Bersih -->
            <div class="mb-4">
              <h1 class="text-2xl sm:text-[28px] font-bold text-slate-900 tracking-tight leading-tight">Sign In</h1>
              <p class="text-[12px] text-slate-500 mt-0.5">Masuk ke sistem verifikasi level principal</p>
            </div>

            <!-- Daftar Akun Cepat Tersimpan -->
            <div v-if="savedAccounts.length > 0" class="mb-3">
              <label class="block text-[9.5px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pilih Akun:</label>
              <div class="flex items-center gap-1.5 flex-wrap">
                <div
                  v-for="u in savedAccounts"
                  :key="u"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[11px] font-semibold rounded-md border transition cursor-pointer"
                  :class="form.username === u ? 'bg-red-50 text-[#D9232A] border-red-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                  @click="selectSavedAccount(u)"
                >
                  <span class="truncate max-w-[130px]">{{ u }}</span>
                  <button
                    type="button"
                    @click.stop="removeSavedAccount(u)"
                    class="text-slate-400 hover:text-red-500 font-bold text-xs"
                    title="Hapus akun"
                  >
                    &times;
                  </button>
                </div>
              </div>
            </div>

            <!-- Pesan Error Validasi / Login dengan Animasi Masuk Halus & Ikon Alert -->
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
                class="flex items-center gap-2.5 p-3 mb-3 rounded-xl bg-rose-50/90 border border-rose-200 text-rose-800 shadow-xs"
                role="alert"
              >
                <div class="p-1 bg-rose-100 text-rose-600 rounded-lg shrink-0 animate-pulse">
                  <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[12px] font-semibold text-rose-800 leading-snug">
                    Username atau Password yang dimasukkan salah.
                  </p>
                </div>
              </div>
            </transition>

            <!-- Form Kredensial -->
            <form @submit.prevent="submitLogin" class="space-y-3.5">
              
              <!-- Input Username -->
              <div>
                <label class="block text-[11.5px] font-semibold text-slate-600 mb-1">Username</label>
                <input
                  type="text"
                  v-model="form.username"
                  @input="form.clearErrors('username')"
                  placeholder="Contoh: admin.aswsum"
                  required
                  class="w-full px-3.5 py-2 text-[13px] rounded-lg border transition leading-tight shadow-2xs"
                  :class="form.errors.username ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:bg-white focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 bg-slate-50/40 text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-[#D9232A] focus:ring-2 focus:ring-[#D9232A]/15'"
                />
              </div>

              <!-- Input Password -->
              <div>
                <label class="block text-[11.5px] font-semibold text-slate-600 mb-1">Password</label>
                <div class="relative">
                  <input
                    ref="passwordInput"
                    :type="showPassword ? 'text' : 'password'"
                    v-model="form.password"
                    @input="form.clearErrors('password', 'username')"
                    placeholder="••••••••"
                    required
                    class="w-full px-3.5 py-2 text-[13px] rounded-lg border transition leading-tight pr-9 shadow-2xs"
                    :class="(form.errors.password || form.errors.username) ? 'border-rose-400 bg-rose-50/20 text-rose-900 focus:bg-white focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 bg-slate-50/40 text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-[#D9232A] focus:ring-2 focus:ring-[#D9232A]/15'"
                  />
                  <!-- Toggle Visibility Password Button -->
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-1"
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

              <!-- Baris Opsi: Ingat Saya Saja (Forgot Password Dihilangkan) -->
              <div class="flex items-center justify-between pt-0.5">
                <label class="flex items-center gap-1.5 cursor-pointer select-none text-[11.5px] text-slate-500">
                  <input
                    type="checkbox"
                    v-model="rememberMe"
                    class="w-3.5 h-3.5 text-[#D9232A] border-slate-300 rounded focus:ring-[#D9232A] cursor-pointer"
                  />
                  <span>Ingat saya</span>
                </label>
              </div>

              <!-- Tombol Sign in Korporat -->
              <button
                type="submit"
                :disabled="form.processing || !form.username || !form.password"
                class="w-full py-2.5 mt-1.5 text-[13.5px] font-bold text-white transition-all bg-[#D9232A] hover:bg-[#B91C22] active:scale-[0.99] rounded-lg shadow-sm shadow-[#D9232A]/30 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
              >
                <span v-if="form.processing">Signing in...</span>
                <span v-else>Sign in</span>
              </button>
            </form>
          </div>

          <!-- Footer Bersih Kartu Login -->
          <div class="mt-6 pt-3 border-t border-slate-100 text-center text-[11px] text-slate-400">
            Copyright &copy; 2026 <strong class="text-slate-600">Portal Principal NOO+</strong>
          </div>

        </div>

      </div>

      <!-- SISI KANAN: FLUID LIQUID BLOB GRADIENT DENGAN LATAR DEEP INDIGO & TEKS KASAT MATA -->
      <div class="hidden md:flex md:col-span-6 relative overflow-hidden bg-[#0F0C22] select-none items-end justify-start p-7 sm:p-8 lg:p-10">
        
        <!-- FLUID AURORA MESH CANVAS (LATAR BELAKANG DEEP CORPORATE NAVY-PURPLE) -->
        <div class="aurora-container absolute inset-0 w-full h-full overflow-hidden bg-gradient-to-br from-[#0F0C22] via-[#1B0E3B] to-[#0A102A]">
          
          <!-- BLOB 1: ROYAL PURPLE (INAFOODS) - Cairan Kiri Atas -->
          <div class="aurora-blob blob-ina-purple animate-liquid-purple"></div>

          <!-- BLOB 2: NAVY BLUE (ASWFOODS) - Cairan Kanan Atas -->
          <div class="aurora-blob blob-asw-navy animate-liquid-navy"></div>

          <!-- BLOB 3: BOLD RED (ASWFOODS) - Cairan Kanan Bawah -->
          <div class="aurora-blob blob-asw-red animate-liquid-red"></div>

          <!-- BLOB 4: CROWN GOLD / WARM SUNSET (INAFOODS) - Cairan Kiri Tengah Bawah -->
          <div class="aurora-blob blob-ina-gold animate-liquid-gold"></div>

          <!-- BLOB 5: DEEP MAGENTA/PURPLE FLOW FUSION - Inti Cairan Tengah -->
          <div class="aurora-blob blob-fusion animate-liquid-fusion"></div>

          <!-- Ambient Luminous Soft Sheen -->
          <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-white/10 pointer-events-none"></div>
        </div>

        <!-- AMBIENT SHADOW GRADASI LEMBUT DI DASAR AGAR TEKS SELALU KASAT MATA 100% -->
        <div class="absolute inset-0 bg-gradient-to-t from-[#080514]/85 via-[#080514]/35 to-transparent pointer-events-none z-10"></div>

        <!-- KONTEN TEKS DESKRIPSI PRINCIPAL NOO+ (SEPENUHNYA KASAT MATA & HIGH-CONTRAST) -->
        <div class="relative z-20 w-full max-w-[420px] text-white">
          <!-- Badge Kategori Resmi -->
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md border border-white/20 text-[10px] font-bold text-white uppercase tracking-wider mb-3 shadow-xs">
            <span class="w-1.5 h-1.5 rounded-full bg-[#F59E0B]"></span>
            Portal Principal NOO+
          </div>
          
          <!-- Judul Utama (Putih Tebal, Tajam & Kasat Mata) -->
          <h2 class="text-xl sm:text-[22px] font-extrabold text-white tracking-tight leading-snug drop-shadow-md">
            Verifikasi &amp; Otorisasi Outlet Baru Secara Terpadu dan Akurat.
          </h2>
          
          <!-- Teks Deskripsi Sistem (Kontras Tinggi & Mudah Dibaca) -->
          <p class="text-[12.5px] sm:text-[13px] text-white/85 leading-relaxed mt-2.5 font-normal drop-shadow-sm">
            Pusat monitoring validitas data toko, kaji ulang identitas, sinkronisasi titik lokasi GPS, dan integrasi distribusi ASWFOODS &amp; INAFOODS.
          </p>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
/* Pengaturan Kanvas Seni Fluid Aurora Mesh Gradient */
.aurora-container {
  filter: saturate(155%) contrast(110%);
}

.aurora-blob {
  position: absolute;
  filter: blur(48px);
  will-change: transform, border-radius;
}

/* 1. Royal Purple (INAFOODS #542B85) - Ukuran Lebih Ringkas */
.blob-ina-purple {
  top: 2%;
  left: 6%;
  width: 250px;
  height: 250px;
  background: radial-gradient(circle, #7B3FA8 0%, #542B85 55%, transparent 78%);
  opacity: 0.95;
}

/* 2. Navy Blue (ASWFOODS #1E2B7B) - Ukuran Lebih Ringkas */
.blob-asw-navy {
  top: 2%;
  right: 2%;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, #3B82F6 0%, #1E2B7B 55%, transparent 80%);
  opacity: 0.94;
}

/* 3. Bold Red (ASWFOODS #D9232A) - Ukuran Lebih Ringkas */
.blob-asw-red {
  bottom: 0%;
  right: 4%;
  width: 290px;
  height: 290px;
  background: radial-gradient(circle, #EF4444 0%, #D9232A 60%, transparent 80%);
  opacity: 0.98;
}

/* 4. Crown Gold (INAFOODS #F59E0B) - Ukuran Lebih Ringkas */
.blob-ina-gold {
  bottom: 6%;
  left: 10%;
  width: 220px;
  height: 220px;
  background: radial-gradient(circle, #FBBF24 0%, #F59E0B 55%, transparent 75%);
  opacity: 0.90;
}

/* 5. Deep Fusion Core (Pusat Aliran Warna Korporat) - Ukuran Lebih Ringkas */
.blob-fusion {
  top: 32%;
  left: 26%;
  width: 230px;
  height: 230px;
  background: radial-gradient(circle, #9333EA 0%, #6B21A8 60%, transparent 80%);
  opacity: 0.92;
}

/* =========================================================================
 * GERAKAN ACAK (PSEUDO-RANDOM MULTI-WAYPOINTS) SANGAT PELAN & MEMBAL
 * Tiap blob memiliki durasi bilangan prima, delay berbeda, & vektor acak
 * ========================================================================= */

/* 1. Liquid Blob Purple (Gerakan Acak 1: Melayang diagonal, membal zig-zag) */
@keyframes liquidRandomPurple {
  0%, 100% {
    transform: translate3d(0, 0, 0) scale(1);
    border-radius: 46% 54% 63% 37% / 41% 44% 56% 59%;
  }
  18% {
    transform: translate3d(65px, 30px, 0) scale(1.12);
    border-radius: 64% 36% 45% 55% / 52% 65% 35% 48%;
  }
  37% {
    transform: translate3d(25px, 85px, 0) scale(0.92);
    border-radius: 38% 62% 58% 42% / 60% 40% 60% 40%;
  }
  56% {
    transform: translate3d(75px, 60px, 0) scale(1.08);
    border-radius: 52% 48% 36% 64% / 44% 58% 42% 56%;
  }
  74% {
    transform: translate3d(-10px, 70px, 0) scale(0.88);
    border-radius: 41% 59% 62% 38% / 58% 36% 64% 42%;
  }
  88% {
    transform: translate3d(30px, 15px, 0) scale(1.04);
    border-radius: 58% 42% 52% 48% / 46% 54% 46% 54%;
  }
}

/* 2. Liquid Blob Navy (Gerakan Acak 2: Mengalir melengkung dari atas kanan ke tengah) */
@keyframes liquidRandomNavy {
  0%, 100% {
    transform: translate3d(0, 0, 0) scale(1);
    border-radius: 53% 47% 42% 58% / 48% 55% 45% 52%;
  }
  15% {
    transform: translate3d(-40px, 70px, 0) scale(0.90);
    border-radius: 42% 58% 61% 39% / 62% 41% 59% 38%;
  }
  33% {
    transform: translate3d(-80px, 30px, 0) scale(1.15);
    border-radius: 65% 35% 48% 52% / 45% 58% 42% 55%;
  }
  52% {
    transform: translate3d(-35px, 100px, 0) scale(0.95);
    border-radius: 39% 61% 55% 45% / 54% 39% 61% 46%;
  }
  70% {
    transform: translate3d(15px, 55px, 0) scale(1.06);
    border-radius: 58% 42% 44% 56% / 48% 63% 37% 52%;
  }
  85% {
    transform: translate3d(-20px, 20px, 0) scale(0.98);
    border-radius: 47% 53% 58% 42% / 56% 48% 52% 44%;
  }
}

/* 3. Liquid Blob Red (Gerakan Acak 3: Mengorbit membal dari kanan bawah menuju pusat) */
@keyframes liquidRandomRed {
  0%, 100% {
    transform: translate3d(0, 0, 0) scale(1);
    border-radius: 56% 44% 58% 42% / 52% 46% 54% 48%;
  }
  16% {
    transform: translate3d(-70px, -45px, 0) scale(1.10);
    border-radius: 43% 57% 41% 59% / 59% 51% 49% 41%;
  }
  35% {
    transform: translate3d(-30px, -90px, 0) scale(0.89);
    border-radius: 62% 38% 65% 35% / 44% 63% 37% 56%;
  }
  54% {
    transform: translate3d(-95px, -30px, 0) scale(1.08);
    border-radius: 38% 62% 45% 55% / 56% 45% 55% 44%;
  }
  68% {
    transform: translate3d(-40px, -80px, 0) scale(0.93);
    border-radius: 54% 46% 57% 43% / 48% 58% 42% 52%;
  }
  86% {
    transform: translate3d(-15px, -35px, 0) scale(1.04);
    border-radius: 48% 52% 46% 54% / 55% 42% 58% 45%;
  }
}

/* 4. Liquid Blob Gold (Gerakan Acak 4: Meluncur acak di area bawah & tengah) */
@keyframes liquidRandomGold {
  0%, 100% {
    transform: translate3d(0, 0, 0) scale(1);
    border-radius: 47% 53% 45% 55% / 57% 43% 57% 43%;
  }
  20% {
    transform: translate3d(70px, -20px, 0) scale(1.14);
    border-radius: 59% 41% 64% 36% / 45% 59% 41% 55%;
  }
  38% {
    transform: translate3d(40px, -85px, 0) scale(0.90);
    border-radius: 39% 61% 43% 57% / 64% 47% 53% 36%;
  }
  55% {
    transform: translate3d(95px, -40px, 0) scale(1.09);
    border-radius: 55% 45% 58% 42% / 43% 56% 44% 57%;
  }
  72% {
    transform: translate3d(30px, 25px, 0) scale(0.92);
    border-radius: 44% 56% 38% 62% / 58% 39% 61% 42%;
  }
  89% {
    transform: translate3d(50px, -15px, 0) scale(1.03);
    border-radius: 54% 46% 52% 48% / 48% 62% 38% 52%;
  }
}

/* 5. Liquid Blob Fusion Core (Gerakan Acak 5: Pusaran lentur & rotasi asimetris di inti) */
@keyframes liquidRandomFusion {
  0%, 100% {
    transform: translate3d(0, 0, 0) scale(1) rotate(0deg);
    border-radius: 50% 50% 45% 55% / 55% 45% 55% 45%;
  }
  17% {
    transform: translate3d(35px, -30px, 0) scale(1.16) rotate(22deg);
    border-radius: 64% 36% 58% 42% / 42% 65% 35% 58%;
  }
  34% {
    transform: translate3d(-40px, 35px, 0) scale(0.88) rotate(-18deg);
    border-radius: 43% 57% 41% 59% / 59% 41% 59% 41%;
  }
  51% {
    transform: translate3d(20px, 40px, 0) scale(1.12) rotate(35deg);
    border-radius: 58% 42% 64% 36% / 38% 59% 41% 62%;
  }
  69% {
    transform: translate3d(-25px, -35px, 0) scale(0.92) rotate(-28deg);
    border-radius: 39% 61% 47% 53% / 62% 38% 62% 38%;
  }
  84% {
    transform: translate3d(10px, 15px, 0) scale(1.05) rotate(12deg);
    border-radius: 53% 47% 55% 45% / 46% 54% 46% 54%;
  }
}

/* Penerapan Waktu Prima (Incommensurate Durations) untuk Meniadakan Pola Berulang (Kecepatan +25%) */
.animate-liquid-purple {
  animation: liquidRandomPurple 26s cubic-bezier(0.45, 0.05, 0.55, 0.95) -5.5s infinite;
}

.animate-liquid-navy {
  animation: liquidRandomNavy 31s cubic-bezier(0.42, 0, 0.58, 1) -15s infinite;
}

.animate-liquid-red {
  animation: liquidRandomRed 23s cubic-bezier(0.45, 0.05, 0.55, 0.95) -10s infinite;
}

.animate-liquid-gold {
  animation: liquidRandomGold 20s cubic-bezier(0.42, 0, 0.58, 1) -4s infinite;
}

.animate-liquid-fusion {
  animation: liquidRandomFusion 30s cubic-bezier(0.45, 0.05, 0.55, 0.95) -18s infinite;
}

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

/* Aksesibilitas prefers-reduced-motion */
@media (prefers-reduced-motion: reduce) {
  .animate-liquid-purple,
  .animate-liquid-navy,
  .animate-liquid-red,
  .animate-liquid-gold,
  .animate-liquid-fusion,
  .animate-shake {
    animation: none !important;
  }
}
</style>




