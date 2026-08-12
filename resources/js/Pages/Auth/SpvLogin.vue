<script setup lang="js">
/**
 * UI Login Khusus Supervisor Area - Dual Panel Layout.
 * Menyeleraskan Style UI dengan DistributorLogin.vue (Grouped Box + Green Button + NOO+ Identitas).
 */
import { ref } from 'vue';
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

const form = useForm({
  username: '',
  password: '',
});

function submitLogin() {
  form.post(route('spv_login.store'));
}
</script>

<template>
  <Head title="Sign In - Portal SPV Area NOO+" />

  <!-- Background Halaman Ringkas 100vh tanpa scrollbar -->
  <div class="flex items-center justify-center min-h-screen bg-[#F0F2F5] text-[#374151] p-3 font-sans selection:bg-emerald-100 overflow-hidden">
    <!-- Main Card Container Kompak -->
    <div class="w-full max-w-4xl bg-white shadow-[0_10px_35px_rgba(0,0,0,0.08)] rounded-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12 border border-[#E2E8F0] my-auto">
      
      <!-- PANEL KIRI: LOGO NOO+, FORM SPV LOGIN, GREEN BUTTON (7 COLS) -->
      <div class="md:col-span-7 p-5 sm:p-6 flex flex-col justify-between bg-white">
        <div>
          <!-- Header Brand dengan Shape Logo Pas & Lapang -->
          <div class="flex items-center gap-3.5 mb-4">
            <div class="flex items-center justify-center px-3.5 h-11 rounded-xl bg-gradient-to-r from-[#542B85] via-[#1E2B7B] to-[#D9232A] shadow-sm text-white font-black text-xl tracking-wider border border-[#F59E0B]/40 shrink-0 whitespace-nowrap">
              <span class="text-white">NOO</span><span class="text-[#F59E0B] ml-0.5">+</span>
            </div>
            <div>
              <h1 class="text-lg font-bold text-[#1E293B] tracking-tight leading-snug">Portal SPV Area</h1>
              <p class="text-[12px] text-[#64748B] font-medium flex items-center gap-1">
                <span>ASWFOODS</span> &bull; <span>INAFOODS</span>
              </p>
            </div>
          </div>

          <!-- Section Login Sub-Header -->
          <div class="mb-3.5">
            <h2 class="text-xl font-bold text-[#1E293B]">Sign In</h2>
            <p class="text-[12px] text-[#94A3B8]">Masuk menggunakan akun yang sama dengan akun Eskamobile anda</p>
          </div>

          <form @submit.prevent="submitLogin" class="space-y-3">
            <!-- GROUPED FORM CONTAINERS (Border Rounded Box Ringkas) -->
            <div class="border border-[#CBD5E1] rounded-xl overflow-hidden divide-y divide-[#E2E8F0] shadow-2xs">
              
              <!-- 1. SALESCODE -->
              <div class="px-3 py-1.5 bg-[#F8FAFC]">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Salescode</label>
                <input
                  type="text"
                  v-model="form.username"
                  placeholder="Masukkan Salescode"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none uppercase placeholder-[#94A3B8] transition leading-tight"
                />
              </div>

              <!-- 2. PASSWORD -->
              <div class="px-3 py-1.5 bg-white">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Password</label>
                <input
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.password"
                  placeholder="Masukkan Password"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none placeholder-[#94A3B8] transition leading-tight"
                />
              </div>
            </div>

            <!-- Radio Button Show Password -->
            <div class="flex items-center justify-start text-[12px] text-[#64748B] pt-0.5 px-0.5">
              <label class="flex items-center gap-2 cursor-pointer select-none" @click.prevent="showPassword = !showPassword">
                <input
                  type="radio"
                  :checked="showPassword"
                  class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer"
                />
                <span class="font-medium text-[#475569]">Show Password</span>
              </label>
            </div>

            <span v-if="form.errors.username || form.errors.password" class="text-xs text-[#DC2626] font-semibold block px-0.5">
              ⚠️ {{ form.errors.username || form.errors.password }}
            </span>

            <!-- LOGIN BUTTON DUAL BRAND -->
            <button
              type="submit"
              :disabled="form.processing || !form.username || !form.password"
              class="w-full py-2.5 mt-2 text-[14px] font-bold text-white transition-all bg-gradient-to-r from-[#542B85] via-[#1E2B7B] to-[#D9232A] hover:opacity-95 rounded-xl shadow-sm border border-[#F59E0B]/30 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
            >
              <span v-if="form.processing">Processing...</span>
              <span v-else>Login</span>
            </button>
          </form>
        </div>

        <!-- FOOTER COPYRIGHT IDENTITAS NOO+ -->
        <div class="mt-4 pt-2.5 border-t border-[#F1F5F9] text-center text-[11px] text-[#64748B]">
          Copyright &copy; 2026 <strong class="text-[#334155]">Portal SPV Area NOO+</strong>.
        </div>
      </div>

      <!-- PANEL KANAN: LIVE OVERVIEW METRICS NOO (5 COLS) -->
      <div class="hidden md:flex md:col-span-5 relative bg-[#0F172A] flex-col justify-between p-6 overflow-hidden text-white">
        
        <!-- Gradient Layer INAFOODS Royal Purple & Crown Gold -->
        <div class="absolute inset-0 bg-gradient-to-tr from-[#4C1D95] via-[#542B85] to-[#3B0764] opacity-95"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff15_1px,transparent_1px),linear-gradient(to_bottom,#ffffff15_1px,transparent_1px)] bg-[size:32px_32px]"></div>

        <!-- Subtle Vector Glowing Blobs -->
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-[#F59E0B]/35 rounded-full blur-2xl pointer-events-none animate-pulse-glow"></div>
        <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-amber-400/20 rounded-full blur-2xl pointer-events-none animate-pulse-glow" style="animation-delay: 3.5s;"></div>

        <!-- Floating Badge -->
        <div class="relative z-10">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-white/15 backdrop-blur-md text-white border border-[#F59E0B]/60 shadow-sm">
            <span>👑</span> OVERVIEW SUPERVISOR AREA
          </div>
        </div>

        <!-- Cards Summary Metrics Grid -->
        <div class="relative z-10 space-y-2 my-auto py-1">
          <div class="bg-white/10 backdrop-blur-md p-2.5 rounded-xl border border-white/15 flex items-center justify-between">
            <div>
              <p class="text-[11px] text-blue-200">Pending Review SPV</p>
              <p class="text-base font-bold text-white mt-0.5">{{ metrics.pushedToSpv }} Toko</p>
            </div>
            <span class="w-7 h-7 rounded-lg bg-blue-400/20 flex items-center justify-center text-blue-200 font-bold text-xs">⏳</span>
          </div>

          <div class="bg-white/10 backdrop-blur-md p-2.5 rounded-xl border border-white/15 flex items-center justify-between">
            <div>
              <p class="text-[11px] text-blue-200">Disetujui SPV Area</p>
              <p class="text-base font-bold text-white mt-0.5">{{ metrics.approvedSpv }} Toko</p>
            </div>
            <span class="w-7 h-7 rounded-lg bg-purple-400/20 flex items-center justify-center text-purple-200 font-bold text-xs">✓</span>
          </div>

          <div class="bg-white/10 backdrop-blur-md p-2.5 rounded-xl border border-white/15 flex items-center justify-between">
            <div>
              <p class="text-[11px] text-blue-200">Disetujui EDP Principal</p>
              <p class="text-base font-bold text-white mt-0.5">{{ metrics.approvedEdp }} Toko</p>
            </div>
            <span class="w-7 h-7 rounded-lg bg-emerald-400/20 flex items-center justify-center text-emerald-200 font-bold text-xs">🎉</span>
          </div>

          <div class="bg-white/10 backdrop-blur-md p-2.5 rounded-xl border border-white/15 flex items-center justify-between">
            <div>
              <p class="text-[11px] text-blue-200">Total Akumulasi Ditolak</p>
              <p class="text-base font-bold text-rose-200 mt-0.5">{{ metrics.rejected }} Toko</p>
            </div>
            <span class="w-7 h-7 rounded-lg bg-rose-400/20 flex items-center justify-center text-rose-200 font-bold text-xs">✕</span>
          </div>
        </div>

        <!-- Animated Running Ticker -->
        <div class="relative z-10 overflow-hidden bg-white/10 backdrop-blur-md rounded-xl border border-white/15 py-1.5 px-3">
          <div class="animate-marquee whitespace-nowrap text-[11px] font-medium text-blue-100 flex gap-6">
            <span>🗺️ Pengaturan Rute Kunjungan JKS</span>
            <span>📍 Verifikasi Koordinat Lokasi GPS</span>
            <span>✓ Persetujuan Pengajuan Toko Area</span>
            <span>🗺️ Pengaturan Rute Kunjungan JKS</span>
            <span>📍 Verifikasi Koordinat Lokasi GPS</span>
            <span>✓ Persetujuan Pengajuan Toko Area</span>
          </div>
        </div>

        <div class="relative z-10 text-[11px] text-blue-200/80 font-medium border-t border-white/10 pt-2 flex items-center justify-between">
          <span>Total Terdaftar Area:</span>
          <strong class="text-white font-bold">{{ metrics.total }} Toko</strong>
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
  animation: marquee 18s linear infinite;
}
.animate-pulse-glow {
  animation: pulseGlow 7s ease-in-out infinite;
}
</style>

