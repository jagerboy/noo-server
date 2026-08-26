<script setup lang="js">
/**
 * Layout Portal Web SPV Area - Light Mode Executive Theme.
 * Sesuai dengan spesifikasi Design System:
 * - Header Utama: Primary 900 (#1E3A8A)
 * - Background Body: Body (#F8FAFC)
 * - Font Family: Inter
 * - Card & Borders: White (#FFFFFF) & Gray 200 (#E5E7EB)
 */
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ToastNotification from '@/Components/ToastNotification.vue';

const page = usePage();
const showingNavigationDropdown = ref(false);
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] text-[#374151] font-sans antialiased">
    <!-- Navbar Top Bar (Portal SPV Area: INAFOODS Royal Purple #542B85 & Crown Gold #F59E0B) -->
    <nav class="bg-gradient-to-r from-[#4C1D95] via-[#542B85] to-[#3B0764] text-white shadow-md sticky top-0 z-30 border-b-2 border-[#F59E0B]">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between items-center">
          
          <!-- Left Brand & Title -->
          <div class="flex items-center space-x-3">
            <img src="/logo-noo-plus.png" alt="Logo NOO+" class="h-10 w-auto object-contain rounded-lg shrink-0 drop-shadow-xs" />
            <div>
              <div class="flex items-center space-x-2">
                <h1 class="text-base font-bold tracking-wide text-white flex items-center gap-1.5">
                  PORTAL SPV AREA
                  <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-[#F59E0B] text-slate-900 border border-amber-300 uppercase tracking-tight shadow-xs flex items-center gap-1">
                    <span>👑</span> SPV SUPERVISOR
                  </span>
                </h1>
              </div>
              <p class="text-xs text-amber-200/90">Verifikasi Rute Kunjungan & Persetujuan Outlet</p>
            </div>
          </div>

          <!-- Right User Profile & Logout -->
          <div class="hidden sm:flex sm:items-center sm:ms-6">
            <div class="relative ms-3">
              <Dropdown align="right" width="64">
                <template #trigger>
                  <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-[#542B85] px-3.5 py-2 text-xs font-semibold text-white hover:bg-[#3B0764] focus:outline-none transition shadow-sm border border-purple-400/30"
                  >
                    <div class="w-6 h-6 rounded-md bg-[#1E2B7B] text-[#F59E0B] flex items-center justify-center font-black me-2 shrink-0 border border-[#F59E0B]/40">
                      {{ page.props.auth.user ? page.props.auth.user.name.charAt(0) : 'S' }}
                    </div>
                    <span class="font-bold text-xs whitespace-nowrap">{{ page.props.auth.user ? page.props.auth.user.name : 'Supervisor Area' }}</span>

                    <svg class="-me-0.5 ms-2 h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                  </button>
                </template>

                <template #content>
                  <div class="px-4 py-2.5 border-b border-[#E5E7EB] bg-[#F9FAFB]">
                    <p class="text-xs text-[#6B7280]">Role Login:</p>
                    <p class="text-xs font-bold text-[#111827]">SPV Area</p>
                  </div>
                  <DropdownLink :href="route('spv_logout')" method="post" as="button" class="text-[#DC2626]">
                    🚪 Sign Out
                  </DropdownLink>
                </template>
              </Dropdown>
            </div>
          </div>

          <!-- Mobile Hamburger -->
          <div class="-me-2 flex items-center sm:hidden">
            <button
              @click="showingNavigationDropdown = !showingNavigationDropdown"
              class="inline-flex items-center justify-center rounded-lg p-2 text-blue-100 hover:bg-[#1D4ED8] focus:outline-none"
            >
              <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Navigation Dropdown -->
      <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden border-t border-[#1D4ED8] bg-[#1E3A8A]">
        <div class="px-4 py-3 border-t border-[#1D4ED8]">
          <div class="font-medium text-base text-white">{{ page.props.auth.user ? page.props.auth.user.name : 'Supervisor Area' }}</div>
          <div class="font-medium text-xs text-blue-200">{{ page.props.auth.user ? page.props.auth.user.email : '' }}</div>
          <div class="mt-3">
            <Link
              :href="route('spv_logout')"
              method="post"
              as="button"
              class="w-full text-left text-sm font-semibold text-rose-300 hover:text-rose-100 py-1.5"
            >
              🚪 Sign Out
            </Link>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content Area -->
    <main class="py-8">
      <slot />
    </main>

    <!-- Global Toast Notification & Loading Indicator -->
    <ToastNotification />
    <LoadingIndicator />
  </div>
</template>
