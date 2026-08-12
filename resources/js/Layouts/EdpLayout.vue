<script setup lang="js">
/**
 * Layout Utama Portal NOO+ dengan Sidebar & Topbar Responsif.
 * Memenuhi Design System NOO+ Architecture Tokens:
 * Header: #1E3A8A ke #2563EB
 * Sidebar: Background #FFFFFF, Active #DBEAFE, Text #1D4ED8
 * Accordion NOO Master Data auto-expand saat berada di halaman master.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import ToastNotification from '@/Components/ToastNotification.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const userRole = computed(() => user.value.role || 'EDP_REGION');

const isFlashSuccessDismissed = ref(false);
const isFlashErrorDismissed = ref(false);

watch(
  () => page.props.flash,
  () => {
    isFlashSuccessDismissed.value = false;
    isFlashErrorDismissed.value = false;
  },
  { deep: true }
);

const isSidebarPinned = ref(sessionStorage.getItem('noo_sidebar_pinned') === 'true');

function toggleSidebarPin() {
  isSidebarPinned.value = !isSidebarPinned.value;
  sessionStorage.setItem('noo_sidebar_pinned', String(isSidebarPinned.value));
}

const isMasterMenuOpen = ref(
  Boolean(
    route().current('edp.master_branch') ||
    route().current('edp.master_salesman') ||
    route().current('edp.master_spv') ||
    route().current('edp.master_edp') ||
    route().current('edp.master_outlet_types') ||
    route().current('edp.counter_sequence')
  )
);

function toggleMasterMenu() {
  isMasterMenuOpen.value = !isMasterMenuOpen.value;
}
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] font-sans antialiased text-[#374151] flex flex-col">
    <!-- Top Executive Header Navbar (Portal Principal Executive Dark Multi-Gradient: Dark Slate #0F172A -> ASW Navy #1E2B7B -> INA Purple #542B85 -> ASW Red #D9232A) -->
    <header class="bg-gradient-to-r from-[#0F172A] via-[#1E2B7B] via-[#542B85] to-[#D9232A] border-b-2 border-[#F59E0B] shadow-md sticky top-0 z-30">
      <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- Left Logo & Sidebar Toggle Pin -->
          <div class="flex items-center gap-4">
            <button 
              @click="toggleSidebarPin"
              class="p-2 text-white/80 hover:text-white rounded-[8px] hover:bg-white/10 transition duration-200 cursor-pointer flex items-center justify-center group overflow-hidden"
              :title="isSidebarPinned ? 'Sembunyikan Sidebar' : 'Tampilkan Semua Menu'"
            >
              <div class="relative w-6 h-6 flex items-center justify-center transition-transform duration-300" :class="isSidebarPinned ? 'rotate-90' : 'rotate-0'">
                <!-- Icon 3 Garis (Hamburger) saat mini mode -->
                <svg 
                  :class="['w-6 h-6 absolute inset-0 transition-all duration-300', isSidebarPinned ? 'opacity-0 scale-75 rotate-45' : 'opacity-100 scale-100 rotate-0']" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>

                <!-- Icon Silang (Hide/Close) saat expanded mode -->
                <svg 
                  :class="['w-6 h-6 absolute inset-0 transition-all duration-300', isSidebarPinned ? 'opacity-100 scale-100 rotate-0' : 'opacity-0 scale-75 -rotate-45']" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </div>
            </button>

            <Link :href="route('edp.dashboard')" class="flex items-center gap-3 group">
              <div class="flex items-center justify-center px-3.5 py-1.5 min-w-[95px] rounded-[10px] bg-gradient-to-r from-[#D9232A] via-[#1E2B7B] to-[#542B85] border border-[#F59E0B]/60 text-white font-black text-base tracking-wider shadow-inner group-hover:scale-105 transition shrink-0">
                <span class="text-white">NOO</span><span class="text-[#F59E0B] ml-0.5">+</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[16px] font-extrabold text-white tracking-wide leading-tight flex items-center gap-2">
                  PORTAL PRINCIPAL
                  <span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-[#F59E0B] text-slate-900 border border-amber-300 uppercase tracking-tight shadow-xs hidden sm:inline-block">
                    👑 ASWFOODS & INAFOODS
                  </span>
                </span>
                <span class="text-[12px] text-amber-100/90 font-normal leading-none mt-0.5">
                  Sistem Informasi & Otoritas Master Data Principal NOO+
                </span>
              </div>
            </Link>
          </div>

          <!-- Right User Profile Badge & Logout -->
          <div class="flex items-center gap-3">
            <div class="hidden md:flex flex-col items-end text-right">
              <span class="text-[14px] font-semibold text-white">{{ user.name || user.username || user.email }}</span>
              <span class="text-[12px] text-amber-200 font-semibold px-2 py-0.5 rounded-[6px] bg-white/15 border border-white/20">
                {{ userRole === 'EDP_REGION' ? 'Operator Principal (Region)' : userRole }} {{ user.region_code ? `(${user.region_code})` : '' }}
              </span>
            </div>

            <!-- LOGOUT BUTTON -->
            <Link 
              :href="route('edp_logout')" 
              method="post" 
              as="button" 
              class="px-4 py-2 text-xs font-bold text-white bg-[#DC2626] hover:bg-[#B91C1C] border border-red-400/40 rounded-lg shadow-sm transition-all duration-150 flex items-center gap-2 cursor-pointer active:scale-95 shrink-0"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>Logout</span>
            </Link>
          </div>

        </div>
      </div>
    </header>

    <!-- Main Container with Sidebar -->
    <div class="flex-1 flex relative">
      <!-- Sidebar Navigation (Mini Icon Mode by Default, Floating Tooltip on Hover) -->
      <aside 
        :class="[
          'bg-white border-r border-[#E5E7EB] flex-shrink-0 shadow-sm flex flex-col justify-between transition-all duration-300 ease-in-out z-40 sticky top-16 h-[calc(100vh-4rem)] overflow-visible',
          isSidebarPinned ? 'w-64' : 'w-[68px]'
        ]"
      >
        <div class="p-3 space-y-2">
          
          <!-- MENU 1: HOME DASHBOARD -->
          <div class="relative group">
            <Link
              :href="route('edp.dashboard')"
              :class="[
                'flex items-center gap-3 py-2.5 rounded-[8px] text-[14px] font-medium transition-all duration-150',
                isSidebarPinned ? 'px-3.5' : 'justify-center px-0',
                route().current('edp.dashboard') 
                  ? 'bg-[#DBEAFE] text-[#1D4ED8] font-semibold shadow-xs' 
                  : 'text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827]'
              ]"
            >
              <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
              <span v-show="isSidebarPinned" class="whitespace-nowrap">Home (Dashboard)</span>
            </Link>

            <!-- Floating Tooltip on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-[#0F172A] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50 flex items-center gap-1 border border-slate-700/60"
            >
              <div class="absolute right-full top-1/2 -mt-1 border-4 border-transparent border-r-[#0F172A]"></div>
              <span>Home (Dashboard)</span>
            </div>
          </div>

          <!-- MENU 2: NOO VERIFICATION -->
          <div class="relative group">
            <Link
              :href="route('edp.inbox')"
              :class="[
                'flex items-center gap-3 py-2.5 rounded-[8px] text-[14px] font-medium transition-all duration-150',
                isSidebarPinned ? 'px-3.5' : 'justify-center px-0',
                route().current('edp.inbox') 
                  ? 'bg-[#DBEAFE] text-[#1D4ED8] font-semibold shadow-xs' 
                  : 'text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827]'
              ]"
            >
              <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span v-show="isSidebarPinned" class="whitespace-nowrap">NOO Verification</span>
            </Link>

            <!-- Floating Tooltip on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-[#0F172A] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50 flex items-center gap-1 border border-slate-700/60"
            >
              <div class="absolute right-full top-1/2 -mt-1 border-4 border-transparent border-r-[#0F172A]"></div>
              <span>NOO Verification</span>
            </div>
          </div>

          <!-- MENU 3: PROGRESS TRACKING NOO -->
          <div class="relative group">
            <Link
              :href="route('edp.progress_tracking')"
              :class="[
                'flex items-center gap-3 py-2.5 rounded-[8px] text-[14px] font-medium transition-all duration-150',
                isSidebarPinned ? 'px-3.5' : 'justify-center px-0',
                route().current('edp.progress_tracking') 
                  ? 'bg-[#DBEAFE] text-[#1D4ED8] font-semibold shadow-xs' 
                  : 'text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827]'
              ]"
            >
              <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              <span v-show="isSidebarPinned" class="whitespace-nowrap">Progress Tracking NOO</span>
            </Link>

            <!-- Floating Tooltip on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-[#0F172A] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50 flex items-center gap-1 border border-slate-700/60"
            >
              <div class="absolute right-full top-1/2 -mt-1 border-4 border-transparent border-r-[#0F172A]"></div>
              <span>Progress Tracking NOO</span>
            </div>
          </div>

          <!-- MENU 3: NOO MASTER DATA ACCORDION / POPOVER -->
          <div class="relative group">
            <button
              type="button"
              @click="toggleMasterMenu"
              :class="[
                'w-full flex items-center py-2.5 rounded-[8px] text-[14px] font-medium text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827] transition-all duration-150 cursor-pointer',
                isSidebarPinned ? 'justify-between px-3.5' : 'justify-center px-0'
              ]"
            >
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M4 14h16"/></svg>
                <span v-show="isSidebarPinned" class="whitespace-nowrap">NOO Master Data</span>
              </div>
              <svg v-show="isSidebarPinned" :class="['w-4 h-4 transition-transform duration-300 text-[#6B7280]', isMasterMenuOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <!-- Floating Popover Sub-Menu on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-0 pl-3 hidden group-hover:block z-50 transition-all duration-150"
            >
              <div class="bg-[#0F172A] text-white p-2 rounded-xl shadow-2xl border border-slate-700/80 min-w-[200px] space-y-1">
                <div class="px-2.5 py-1 text-[11px] font-bold text-blue-400 border-b border-slate-700/80 uppercase tracking-wider mb-1">
                  NOO Master Data
                </div>
                <div class="space-y-1">
                  <Link :href="route('edp.master_branch')" class="block px-2.5 py-1.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800 rounded-lg transition">🏢 Master Branch</Link>
                  <Link :href="route('edp.master_salesman')" class="block px-2.5 py-1.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800 rounded-lg transition">👔 Master Salesman</Link>
                  <Link :href="route('edp.master_spv')" class="block px-2.5 py-1.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800 rounded-lg transition">📋 Master SPV</Link>
                  <Link :href="route('edp.master_outlet_types')" class="block px-2.5 py-1.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800 rounded-lg transition">🏷️ Master Outlet Types</Link>
                  <Link :href="route('edp.counter_sequence')" class="block px-2.5 py-1.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800 rounded-lg transition">🔢 Counter Sequence</Link>
                </div>
              </div>
            </div>

            <!-- SUB-MENUS (Mode Pinned Expanded) -->
            <Transition
              enter-active-class="transition-all duration-200 ease-out"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-150 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="isMasterMenuOpen && isSidebarPinned" class="mt-1 ml-4 pl-4 border-l-2 border-[#2563EB] space-y-1">
                <Link
                  :href="route('edp.master_branch')"
                  :class="[
                    'block px-3 py-2 rounded-[6px] text-[13px] font-medium transition duration-150',
                    route().current('edp.master_branch') ? 'bg-[#DBEAFE] text-[#1D4ED8] font-bold' : 'text-[#6B7280] hover:text-[#111827] hover:bg-[#EFF6FF]'
                  ]"
                >
                  🏢 Master Branch
                </Link>
                <Link
                  :href="route('edp.master_salesman')"
                  :class="[
                    'block px-3 py-2 rounded-[6px] text-[13px] font-medium transition duration-150',
                    route().current('edp.master_salesman') ? 'bg-[#DBEAFE] text-[#1D4ED8] font-bold' : 'text-[#6B7280] hover:text-[#111827] hover:bg-[#EFF6FF]'
                  ]"
                >
                  👔 Master Salesman
                </Link>
                <Link
                  :href="route('edp.master_spv')"
                  :class="[
                    'block px-3 py-2 rounded-[6px] text-[13px] font-medium transition duration-150',
                    route().current('edp.master_spv') ? 'bg-[#DBEAFE] text-[#1D4ED8] font-bold' : 'text-[#6B7280] hover:text-[#111827] hover:bg-[#EFF6FF]'
                  ]"
                >
                  📋 Master SPV
                </Link>
                <Link
                  :href="route('edp.master_outlet_types')"
                  :class="[
                    'block px-3 py-2 rounded-[6px] text-[13px] font-medium transition duration-150',
                    route().current('edp.master_outlet_types') ? 'bg-[#DBEAFE] text-[#1D4ED8] font-bold' : 'text-[#6B7280] hover:text-[#111827] hover:bg-[#EFF6FF]'
                  ]"
                >
                  🏷️ Master Outlet Types
                </Link>
                <Link
                  :href="route('edp.counter_sequence')"
                  :class="[
                    'block px-3 py-2 rounded-[6px] text-[13px] font-medium transition duration-150',
                    route().current('edp.counter_sequence') ? 'bg-[#DBEAFE] text-[#1D4ED8] font-bold' : 'text-[#6B7280] hover:text-[#111827] hover:bg-[#EFF6FF]'
                  ]"
                >
                  🔢 Counter Sequence
                </Link>
              </div>
            </Transition>
          </div>

          <!-- MENU 4: MANAJEMEN AKUN (SUPERADMIN & ADMIN PRINCIPAL) -->
          <div v-if="userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL'" class="relative group">
            <Link
              :href="route('edp.account_management')"
              :class="[
                'flex items-center gap-3 py-2.5 rounded-[8px] text-[14px] font-medium transition-all duration-150',
                isSidebarPinned ? 'px-3.5' : 'justify-center px-0',
                route().current('edp.account_management') 
                  ? 'bg-[#DBEAFE] text-[#1D4ED8] font-semibold shadow-xs' 
                  : 'text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827]'
              ]"
            >
              <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <span v-show="isSidebarPinned" class="whitespace-nowrap">Manajemen Akun</span>
            </Link>

            <!-- Floating Tooltip on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-[#0F172A] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50 flex items-center gap-1 border border-slate-700/60"
            >
              <div class="absolute right-full top-1/2 -mt-1 border-4 border-transparent border-r-[#0F172A]"></div>
              <span>Manajemen Akun</span>
            </div>
          </div>

          <!-- MENU 5: AUDIT LOGS -->
          <div v-if="userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL'" class="relative group">
            <Link
              :href="route('edp.logs')"
              :class="[
                'flex items-center gap-3 py-2.5 rounded-[8px] text-[14px] font-medium transition-all duration-150',
                isSidebarPinned ? 'px-3.5' : 'justify-center px-0',
                route().current('edp.logs') 
                  ? 'bg-[#DBEAFE] text-[#1D4ED8] font-semibold shadow-xs' 
                  : 'text-[#4B5563] hover:bg-[#EFF6FF] hover:text-[#111827]'
              ]"
            >
              <svg class="w-5 h-5 text-[#2563EB] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              <span v-show="isSidebarPinned" class="whitespace-nowrap">Logs & Audit</span>
            </Link>

            <!-- Floating Tooltip on Icon Hover (Mode Mini) -->
            <div 
              v-if="!isSidebarPinned"
              class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-[#0F172A] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50 flex items-center gap-1 border border-slate-700/60"
            >
              <div class="absolute right-full top-1/2 -mt-1 border-4 border-transparent border-r-[#0F172A]"></div>
              <span>Logs & Audit</span>
            </div>
          </div>

        </div>

        <!-- Sidebar Footer Info -->
        <div class="p-3 border-t border-[#E5E7EB] text-center text-[12px] text-[#9CA3AF]">
          <span v-if="isSidebarPinned">NOO+ &copy; 2026</span>
          <span v-else>&copy;</span>
        </div>
      </aside>

      <!-- Content Area -->
      <main class="flex-1 min-w-0 p-6 transition-all duration-300">
        <!-- FLASH NOTIFICATION BANNERS WITH CLOSE BUTTON -->
        <div 
          v-if="$page.props.flash?.success && !isFlashSuccessDismissed" 
          class="mb-4 p-4 text-[14px] font-semibold text-[#15803D] bg-[#DCFCE7] border border-[#86EFAC] rounded-[8px] flex items-center justify-between shadow-xs transition"
        >
          <div class="flex items-center gap-2">
            <span class="text-base font-bold">✔</span>
            <span>{{ $page.props.flash.success }}</span>
          </div>
          <button 
            @click="isFlashSuccessDismissed = true" 
            class="text-[#15803D] hover:text-emerald-950 font-bold text-sm px-2 py-0.5 rounded-full hover:bg-emerald-200/60 transition cursor-pointer flex items-center justify-center"
            title="Tutup Notifikasi"
          >
            ✕
          </button>
        </div>

        <div 
          v-if="$page.props.flash?.error && !isFlashErrorDismissed" 
          class="mb-4 p-4 text-[14px] font-semibold text-[#B91C1C] bg-[#FEE2E2] border border-[#FCA5A5] rounded-[8px] flex items-center justify-between shadow-xs transition"
        >
          <div class="flex items-center gap-2">
            <span class="text-base font-bold">✖</span>
            <span>{{ $page.props.flash.error }}</span>
          </div>
          <button 
            @click="isFlashErrorDismissed = true" 
            class="text-[#B91C1C] hover:text-red-950 font-bold text-sm px-2 py-0.5 rounded-full hover:bg-red-200/60 transition cursor-pointer flex items-center justify-center"
            title="Tutup Notifikasi"
          >
            ✕
          </button>
        </div>

        <slot />
      </main>
    </div>

    <!-- Global Toast Notification & Loading Indicator -->
    <ToastNotification />
    <LoadingIndicator />
  </div>
</template>
