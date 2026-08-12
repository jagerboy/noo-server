<script setup lang="js">
/**
 * UI Login Bertingkat Admin Distributor (Vue 3 Composition API).
 * Instan load via Props, Default 'Pilih Principal dulu', Toggle Eye Show/Hide PIN, Tanpa Arrow Button.
 */
import { ref, computed, onMounted } from 'vue';
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
      console.error('Failed fetching bootstrap data client-side', e);
    }
  }
});

// Default Principal dikosongkan agar pengguna memilih "Pilih Principal dulu"
const selectedPrincipalGroup = ref('');
const showPin = ref(false);

const form = useForm({
  principal_code: '',
  region_code: '',
  entity_code_principal: '',
  branch_id: '',
  pin_branch: '',
});

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
}

function onRegionChange() {
  form.entity_code_principal = '';
  form.branch_id = '';
}

function onEntityChange() {
  form.branch_id = '';
}

function submitLogin() {
  form.post(route('distributor_login.store'));
}
</script>

<template>
  <Head title="Login Distributor Admin Portal - NOO+" />

  <!-- Background Halaman Ringkas 100vh tanpa scrollbar -->
  <div class="flex items-center justify-center min-h-screen bg-[#F0F2F5] text-[#374151] p-3 font-sans selection:bg-emerald-100 overflow-hidden">
    <!-- Main Card Container Kompak -->
    <div class="w-full max-w-4xl bg-white shadow-[0_10px_35px_rgba(0,0,0,0.08)] rounded-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12 border border-[#E2E8F0] my-auto">
      
      <!-- PANEL KIRI: LOGO NOO+, FORM CASCADING, GREEN BUTTON (7 COLS) -->
      <div class="md:col-span-7 p-5 sm:p-6 flex flex-col justify-between bg-white">
        <div>
          <!-- Header Brand dengan Shape Logo Pas & Lapang -->
          <div class="flex items-center gap-3.5 mb-4">
            <div class="flex items-center justify-center px-3.5 h-11 rounded-xl bg-gradient-to-r from-[#D9232A] via-[#1E2B7B] to-[#542B85] shadow-sm text-white font-black text-xl tracking-wider border border-[#F59E0B]/40 shrink-0 whitespace-nowrap">
              <span class="text-white">NOO</span><span class="text-[#F59E0B] ml-0.5">+</span>
            </div>
            <div>
              <h1 class="text-lg font-bold text-[#1E293B] tracking-tight leading-snug flex items-center gap-1.5">
                Distributor Admin Portal
              </h1>
              <p class="text-[12px] text-[#64748B] font-medium flex items-center gap-1">
                <span>ASWFOODS</span> &bull; <span>INAFOODS</span>
              </p>
            </div>
          </div>

          <!-- Section Login Sub-Header -->
          <div class="mb-3.5">
            <h2 class="text-xl font-bold text-[#1E293B]">Sign In</h2>
            <p class="text-[12px] text-[#94A3B8]">Masuk menggunakan otentikasi PIN Cabang</p>
          </div>

          <form @submit.prevent="submitLogin" class="space-y-3">
            <!-- GROUPED FORM CONTAINERS (Border Rounded Box Ringkas) -->
            <div class="border border-[#CBD5E1] rounded-xl overflow-hidden divide-y divide-[#E2E8F0] shadow-2xs">
              
              <!-- 1. PRINCIPAL GROUP -->
              <div class="px-3 py-1.5 bg-[#F8FAFC]">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Principal</label>
                <select
                  v-model="selectedPrincipalGroup"
                  @change="onPrincipalGroupChange"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none transition cursor-pointer leading-tight"
                >
                  <option value="" disabled selected>Pilih Principal dulu</option>
                  <option v-for="p in principalList" :key="p.code" :value="p.code">
                    {{ p.label }}
                  </option>
                </select>
              </div>

              <!-- 2. REGION -->
              <div class="px-3 py-1.5 bg-white">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Region</label>
                <select
                  v-model="form.region_code"
                  @change="onRegionChange"
                  :disabled="!selectedPrincipalGroup || availableRegions.length === 0"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-[#94A3B8] disabled:cursor-not-allowed transition cursor-pointer leading-tight"
                >
                  <option value="" disabled selected>
                    {{ selectedPrincipalGroup ? (availableRegions.length > 0 ? 'Pilih Region...' : 'Tidak ada region') : 'Pilih Principal dulu' }}
                  </option>
                  <option v-for="r in availableRegions" :key="r.code" :value="r.code">
                    {{ r.label }}
                  </option>
                </select>
              </div>

              <!-- 3. ENTITY PRINCIPAL -->
              <div class="px-3 py-1.5 bg-[#F8FAFC]">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Entity Principal</label>
                <select
                  v-model="form.entity_code_principal"
                  @change="onEntityChange"
                  :disabled="!form.region_code"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-[#94A3B8] disabled:cursor-not-allowed transition cursor-pointer leading-tight"
                >
                  <option value="" disabled selected>{{ form.region_code ? 'Pilih Entity...' : 'Pilih region dulu' }}</option>
                  <option v-for="e in availableEntities" :key="e.code" :value="e.code">
                    {{ e.label }}
                  </option>
                </select>
              </div>

              <!-- 4. BRANCH / DISTRIBUTOR -->
              <div class="px-3 py-1.5 bg-white">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">Cabang Distributor</label>
                <select
                  v-model="form.branch_id"
                  :disabled="!form.entity_code_principal || availableBranches.length === 0"
                  class="w-full py-1 text-[13px] font-semibold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none disabled:text-[#94A3B8] disabled:cursor-not-allowed transition cursor-pointer leading-tight"
                >
                  <option value="" disabled selected>
                    {{ form.entity_code_principal ? (availableBranches.length > 0 ? 'Pilih Cabang Distributor...' : 'Tidak ada cabang') : 'Pilih entity dulu' }}
                  </option>
                  <option v-for="b in availableBranches" :key="b.branch_id || b.code" :value="b.branch_id || b.code">
                    {{ b.label || (b.branch_name ? `${b.branch_id || b.code} - ${b.branch_name}` : (b.branch_id || b.code)) }}
                  </option>
                </select>
              </div>

              <!-- 5. INPUT PIN CABANG -->
              <div class="px-3 py-1.5 bg-[#F8FAFC]">
                <label class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider">PIN Cabang</label>
                <input
                  :type="showPin ? 'text' : 'password'"
                  v-model="form.pin_branch"
                  placeholder="Masukkan PIN Cabang"
                  class="w-full py-1 text-[13px] font-bold text-[#1E293B] bg-transparent border-0 focus:ring-0 focus:outline-none placeholder:font-normal placeholder:text-[#94A3B8]"
                />
              </div>
            </div>

            <!-- TOGGLE SHOW / HIDE PASSWORD WITH SINGLE RADIO INPUT -->
            <div class="flex items-center justify-between px-0.5 py-0.5 text-xs text-[#64748B]">
              <label class="flex items-center gap-2 cursor-pointer select-none" @click.prevent="showPin = !showPin">
                <input
                  type="radio"
                  :checked="showPin"
                  class="w-4 h-4 text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer"
                />
                <span class="font-medium text-[#475569]">Show Password</span>
              </label>
            </div>

            <span v-if="form.errors.pin_branch" class="text-xs text-[#DC2626] font-semibold block px-0.5">
              ⚠️ {{ form.errors.pin_branch }}
            </span>

            <!-- LOGIN BUTTON -->
            <button
              type="submit"
              :disabled="form.processing || !form.branch_id || !form.pin_branch"
              class="w-full py-2.5 mt-2 text-[14px] font-bold text-white transition-all bg-gradient-to-r from-[#D9232A] via-[#1E2B7B] to-[#542B85] hover:opacity-95 rounded-xl shadow-sm border border-[#F59E0B]/30 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
            >
              <span v-if="form.processing">Processing...</span>
              <span v-else>Login</span>
            </button>
          </form>
        </div>

        <!-- FOOTER COPYRIGHT IDENTITAS NOO+ -->
        <div class="mt-4 pt-2.5 border-t border-[#F1F5F9] text-center text-[11px] text-[#64748B]">
          Copyright &copy; 2026 <strong class="text-[#334155]">Layanan Registrasi Outlet NOO+</strong>.
        </div>
      </div>

      <!-- PANEL KANAN: CORPORATE DISTRIBUTOR HERO BACKGROUND (5 COLS) -->
      <div class="hidden md:flex md:col-span-5 relative bg-[#0F172A] flex-col justify-between p-6 overflow-hidden text-white">
        
        <!-- Gradient Layer ASWFOODS Red & Navy Blue -->
        <div class="absolute inset-0 bg-gradient-to-tr from-[#D9232A] via-[#B91C1C] to-[#1E2B7B] opacity-95"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff15_1px,transparent_1px),linear-gradient(to_bottom,#ffffff15_1px,transparent_1px)] bg-[size:24px_24px]"></div>

        <!-- Subtle Vector Glowing Blobs -->
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-rose-400/30 rounded-full blur-2xl pointer-events-none animate-pulse-glow"></div>
        <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-blue-500/30 rounded-full blur-2xl pointer-events-none animate-pulse-glow" style="animation-delay: 3s;"></div>

        <!-- Floating Badge Identitas NOO+ Distributor -->
        <div class="relative z-10">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-white/15 backdrop-blur-md text-white border border-white/30 shadow-sm">
            <span>🏢</span> PORTAL ADMIN DISTRIBUTOR
          </div>
        </div>

        <!-- Text Banner Identitas NOO+ -->
        <div class="relative z-10 space-y-2 my-auto py-3">
          <h3 class="text-xl font-extrabold text-white leading-tight tracking-wide">
            NOO+ Web Portal
          </h3>
          <p class="text-[12px] text-blue-100/90 leading-relaxed font-normal">
            Pusat verifikasi dan pengajuan Outlet Baru secara terpadu, cepat, dan transparan antar cabang distributor.
          </p>
        </div>

        <!-- Animated Running Ticker -->
        <div class="relative z-10 overflow-hidden bg-white/10 backdrop-blur-md rounded-xl border border-white/15 py-2 px-3 my-2">
          <div class="animate-marquee whitespace-nowrap text-[11px] font-medium text-blue-100 flex gap-6">
            <span>⚡ Verifikasi Outlet Baru Instant</span>
            <span>📍 Presisi GPS & Foto Lokasi</span>
            <span>🔒 Otentikasi Terproteksi PIN Branch</span>
            <span>📊 Rekap Data Terpadu Cabang</span>
            <span>⚡ Verifikasi Outlet Baru Instant</span>
            <span>📍 Presisi GPS & Foto Lokasi</span>
            <span>🔒 Otentikasi Terproteksi PIN Branch</span>
            <span>📊 Rekap Data Terpadu Cabang</span>
          </div>
        </div>

        <div class="relative z-10 text-[10px] text-blue-200/70 font-medium tracking-wider flex items-center justify-between border-t border-white/10 pt-2">
          <span>Layanan Registrasi Outlet</span>
          <span class="font-bold text-white">NOO+ System</span>
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





