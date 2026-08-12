<script setup lang="js">
/**
 * UI Login Portal NOO+ / Principal Portal (Vue 3 Composition API).
 * Fitur: Remember Me, Simpan Akun Terakhir (LocalStorage) & Toggle Show Password.
 */
import { ref, onMounted } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const showPassword = ref(false);
const rememberMe = ref(false);
const savedAccounts = ref([]);

const form = useForm({
  username: '',
  password: '',
  remember: false,
});

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
    console.error('Error reading localStorage:', e);
  }
});

function selectSavedAccount(username) {
  form.username = username;
}

function removeSavedAccount(username) {
  savedAccounts.value = savedAccounts.value.filter((u) => u !== username);
  try {
    localStorage.setItem('noo_saved_edp_accounts', JSON.stringify(savedAccounts.value));
    if (form.username === username) {
      form.username = '';
    }
  } catch (e) {}
}

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

  form.post(route('edp_login.store'));
}
</script>

<template>
  <Head title="Sign In - Portal Principal NOO+" />

  <!-- Background Halaman Ringkas 100vh tanpa scrollbar -->
  <div class="flex items-center justify-center min-h-screen bg-[#F0F2F5] text-[#374151] p-3 font-sans selection:bg-emerald-100 overflow-hidden">
    <!-- Main Card Container Kompak -->
    <div class="w-full max-w-4xl bg-white shadow-[0_10px_35px_rgba(0,0,0,0.08)] rounded-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12 border border-[#E2E8F0] my-auto">
      
      <!-- PANEL KIRI: LOGO NOO+, FORM PRINCIPAL LOGIN (7 COLS) -->
      <div class="md:col-span-7 p-5 sm:p-6 flex flex-col justify-between bg-white">
        <div>
          <!-- Header Brand dengan Shape Logo Pas & Lapang -->
          <div class="flex items-center gap-3.5 mb-4">
            <div class="flex items-center justify-center px-3.5 h-11 rounded-xl bg-gradient-to-r from-[#1E2B7B] via-[#542B85] to-[#D9232A] shadow-sm text-white font-black text-xl tracking-wider border border-[#F59E0B]/40 shrink-0 whitespace-nowrap">
              <span class="text-white">NOO</span><span class="text-[#F59E0B] ml-0.5">+</span>
            </div>
            <div>
              <h1 class="text-lg font-bold text-[#1E293B] tracking-tight leading-snug">Portal Principal NOO+</h1>
              <p class="text-[12px] text-[#64748B] font-medium flex items-center gap-1">
                <span>ASWFOODS</span> &bull; <span>INAFOODS</span>
              </p>
            </div>
          </div>

          <!-- Section Login Sub-Header -->
          <div class="mb-3.5">
            <h2 class="text-xl font-bold text-[#1E293B]">Sign In</h2>
            <p class="text-[12px] text-[#94A3B8]">Masuk menggunakan Username & Password Akun Anda</p>
          </div>

          <!-- Pilihan Akun Tersimpan (Saved Accounts Chip) -->
          <div v-if="savedAccounts.length > 0" class="mb-3">
            <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Akun Pernah Login (Saved Accounts):</label>
            <div class="flex items-center gap-1.5 flex-wrap">
              <div
                v-for="u in savedAccounts"
                :key="u"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg border transition cursor-pointer"
                :class="form.username === u ? 'bg-purple-50 text-[#542B85] border-purple-300 font-bold' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                @click="selectSavedAccount(u)"
              >
                <span>👤 {{ u }}</span>
                <button
                  type="button"
                  @click.stop="removeSavedAccount(u)"
                  class="text-slate-400 hover:text-red-600 font-bold ml-1"
                  title="Hapus akun ini"
                >
                  &times;
                </button>
              </div>
            </div>
          </div>

          <form @submit.prevent="submitLogin" class="space-y-3">
            <!-- GROUPED FORM CONTAINERS (Border Rounded Box Ringkas) -->
            <div class="border border-[#CBD5E1] rounded-xl overflow-hidden divide-y divide-[#E2E8F0] shadow-2xs">
              
              <!-- 1. USERNAME -->
              <div class="px-3 py-1.5 bg-[#F8FAFC]">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Username</label>
                <input
                  type="text"
                  v-model="form.username"
                  placeholder="Masukkan Username Anda"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none placeholder-[#94A3B8] transition leading-tight"
                />
              </div>

              <!-- 2. PASSWORD -->
              <div class="px-3 py-1.5 bg-white">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Password</label>
                <input
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.password"
                  placeholder="Masukkan Password Anda"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none placeholder-[#94A3B8] transition leading-tight"
                />
              </div>
            </div>

            <!-- Radio Button Show Password -->
            <div class="flex items-center justify-between pt-0.5 px-0.5">
              <label class="flex items-center gap-2 cursor-pointer select-none text-[12px] text-[#64748B]" @click.prevent="showPassword = !showPassword">
                <input
                  type="radio"
                  :checked="showPassword"
                  class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer"
                />
                <span class="font-medium text-[#475569]">Show Password</span>
              </label>

              <label class="flex items-center gap-1.5 cursor-pointer select-none text-[12px] text-[#64748B]">
                <input
                  type="checkbox"
                  v-model="rememberMe"
                  class="w-3.5 h-3.5 text-[#542B85] border-gray-300 rounded focus:ring-[#542B85] cursor-pointer"
                />
                <span>Ingat Saya</span>
              </label>
            </div>

            <span v-if="form.errors.username || form.errors.password" class="text-xs text-[#DC2626] font-semibold block px-0.5">
              ⚠️ {{ form.errors.username || form.errors.password }}
            </span>

            <!-- DUAL BRAND LOGIN BUTTON -->
            <button
              type="submit"
              :disabled="form.processing || !form.username || !form.password"
              class="w-full py-2.5 mt-2 text-[14px] font-bold text-white transition-all bg-gradient-to-r from-[#1E2B7B] via-[#542B85] to-[#D9232A] hover:opacity-95 rounded-xl shadow-sm border border-[#F59E0B]/30 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
            >
              <span v-if="form.processing">Processing...</span>
              <span v-else>Login</span>
            </button>
          </form>
        </div>

        <!-- FOOTER COPYRIGHT IDENTITAS NOO+ -->
        <div class="mt-4 pt-2.5 border-t border-[#F1F5F9] text-center text-[11px] text-[#64748B]">
          Copyright &copy; 2026 <strong class="text-[#334155]">Portal Verifikasi Principal NOO+</strong>.
        </div>
      </div>

      <!-- PANEL KANAN: CORPORATE PRINCIPAL HERO BACKGROUND (5 COLS) -->
      <div class="hidden md:flex md:col-span-5 relative bg-[#0F172A] flex-col justify-between p-6 overflow-hidden text-white">
        
        <!-- Gradient Layer & Architectural Grid Lines Dual Brand -->
        <div class="absolute inset-0 bg-gradient-to-tr from-[#1E2B7B] via-[#542B85] to-[#D9232A] opacity-95"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff15_1px,transparent_1px),linear-gradient(to_bottom,#ffffff15_1px,transparent_1px)] bg-[size:32px_32px]"></div>

        <!-- Subtle Vector Glowing Blobs -->
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-[#F59E0B]/30 rounded-full blur-2xl pointer-events-none animate-pulse-glow"></div>
        <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-purple-500/30 rounded-full blur-2xl pointer-events-none animate-pulse-glow" style="animation-delay: 4s;"></div>

        <!-- Header Tagline Panel Kanan -->
        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-white/15 backdrop-blur-md text-white border border-[#F59E0B]/50 shadow-sm">
            <span>👑</span> PORTAL PRINCIPAL
          </div>
          <h3 class="text-xl font-bold text-white tracking-tight">Pusat Otoritas & Master Data</h3>
          <p class="text-xs text-emerald-100/90 leading-relaxed font-medium">
            Verifikasi data outlet baru, pembentukan Kode Toko Principal, dan pengelolaan master data secara terpadu.
          </p>
        </div>

        <!-- Visual Feature Highlights -->
        <div class="relative z-10 space-y-2.5 my-auto py-2">
          <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/10 backdrop-blur-md border border-white/15">
            <span class="w-8 h-8 rounded-lg bg-emerald-400/20 flex items-center justify-center text-emerald-200 font-bold text-sm shrink-0">✅</span>
            <div>
              <h4 class="text-xs font-bold text-white">Approval Toko & Kode NOO</h4>
              <p class="text-[10px] text-emerald-100/80">Otomatisasi pemetaan Kode Toko Principal</p>
            </div>
          </div>

          <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/10 backdrop-blur-md border border-white/15">
            <span class="w-8 h-8 rounded-lg bg-teal-400/20 flex items-center justify-center text-teal-200 font-bold text-sm shrink-0">📊</span>
            <div>
              <h4 class="text-xs font-bold text-white">Master Data & Log Aktivitas</h4>
              <p class="text-[10px] text-teal-100/80">Manajemen Salesman, SPV, Cabang & Rekap Log</p>
            </div>
          </div>
        </div>

        <!-- Animated Running Ticker -->
        <div class="relative z-10 overflow-hidden bg-white/10 backdrop-blur-md rounded-xl border border-white/15 py-1.5 px-3 mb-2">
          <div class="animate-marquee whitespace-nowrap text-[11px] font-medium text-emerald-100 flex gap-6">
            <span>🛡️ Approval Toko & Generator Kode NOO</span>
            <span>📊 Master Data Salesman, SPV & Cabang</span>
            <span>📋 Log Aktivitas & Rekap Verifikasi Terpadu</span>
            <span>🛡️ Approval Toko & Generator Kode NOO</span>
            <span>📊 Master Data Salesman, SPV & Cabang</span>
            <span>📋 Log Aktivitas & Rekap Verifikasi Terpadu</span>
          </div>
        </div>

        <!-- Bottom Footer Info -->
        <div class="relative z-10 text-[11px] text-emerald-200/80 font-medium border-t border-white/10 pt-2 flex items-center justify-between">
          <span>Status Akses:</span>
          <strong class="text-white font-bold tracking-wide">Terverifikasi & Terproteksi</strong>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
@keyframes marquee {
  0% { transform: translateX(0%); }
  100% { transform: translateX(-50%); }
}
@keyframes pulseGlow {
  0%, 100% { opacity: 0.25; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.1); }
}
.animate-marquee {
  display: flex;
  width: 200%;
  animation: marquee 20s linear infinite;
}
.animate-pulse-glow {
  animation: pulseGlow 7s ease-in-out infinite;
}
</style>

