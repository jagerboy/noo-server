<script setup lang="js">
/**
 * Halaman Audit Logs System NOO+ - Web Portal
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  logs: Object,
  filters: Object,
  availableRoles: {
    type: Array,
    default: () => [],
  },
});

const search = ref(props.filters?.search || '');
const selectedRole = ref(props.filters?.role || 'ALL');

function handleSearch() {
  router.get(
    route('edp.logs'),
    {
      search: search.value,
      role: selectedRole.value,
    },
    { preserveState: true, replace: true }
  );
}

function formatRole(role) {
  if (!role) return '-';
  const roleMap = {
    'SUPERADMIN': 'Superadmin',
    'ADMIN_PRINCIPAL': 'Admin Principal',
    'EDP_REGION': 'EDP Region',
    'SPV_AREA': 'SPV Area',
    'ADMIN_DISTRIBUTOR': 'Admin Distributor',
  };
  return roleMap[role] || role.replace(/_/g, ' ');
}

function getRoleBadgeStyle(role) {
  switch (role) {
    case 'SUPERADMIN':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    case 'ADMIN_PRINCIPAL':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'EDP_REGION':
      return 'bg-indigo-100 text-indigo-800 border-indigo-200';
    case 'SPV_AREA':
      return 'bg-amber-100 text-amber-900 border-amber-300';
    case 'ADMIN_DISTRIBUTOR':
      return 'bg-emerald-100 text-emerald-900 border-emerald-300';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
}
const isExporting = ref(false);

function exportToExcel() {
  isExporting.value = true;
  const params = new URLSearchParams();
  if (search.value) params.append('search', search.value);
  if (selectedRole.value && selectedRole.value !== 'ALL') params.append('role', selectedRole.value);

  const url = `${route('edp.logs.export_excel')}?${params.toString()}`;
  window.location.href = url;
  setTimeout(() => {
    isExporting.value = false;
  }, 2000);
}
</script>

<template>
  <EdpLayout>
    <Head title="Logs & Audit Activity - Portal NOO+" />

    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 md:p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div>
          <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight flex items-center gap-2">
            <span>Audit Activity & System Logs</span>
          </h1>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] text-[#6B7280] mt-0.5">
            Rekam jejak seluruh aktivitas pengguna (Login, Approval, Rejection, Perubahan Sequence & Master Data).
          </p>
        </div>

        <button
          @click="exportToExcel"
          :disabled="isExporting"
          class="px-3 py-1.5 text-[11.5px] font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 rounded-lg shadow-xs transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shrink-0"
        >
          <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <span>{{ isExporting ? 'Mengunduh...' : 'Export Excel (.xlsx)' }}</span>
        </button>
      </div>

      <!-- Search Bar & Role Filter -->
      <div class="bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 flex-1 min-w-[280px]">
          <input
            type="text"
            v-model="search"
            @keyup.enter="handleSearch"
            placeholder="Cari Username, Modul, atau Aktivitas..."
            class="w-full max-w-md px-2.5 py-1.5 text-[12px] bg-white border border-[#D1D5DB] rounded-lg focus:ring-2 focus:ring-[#10B981] outline-none"
          />
          <button @click="handleSearch" class="px-3 py-1.5 text-[11.5px] font-semibold text-white bg-[#374151] hover:bg-slate-800 rounded-lg cursor-pointer transition">
            Cari
          </button>
        </div>

        <!-- Role Filter Dropdown -->
        <div class="flex items-center gap-2 shrink-0">
          <label class="text-[11.5px] font-medium text-slate-500">Filter Role:</label>
          <select
            v-model="selectedRole"
            @change="handleSearch"
            class="px-2.5 py-1.5 text-[12px] font-medium bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#10B981] text-slate-800 cursor-pointer shadow-2xs"
          >
            <option value="ALL">Semua Role</option>
            <option v-for="r in availableRoles" :key="r" :value="r">
              {{ formatRole(r) }}
            </option>
          </select>
        </div>
      </div>

      <!-- Table Audit Logs -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-semibold text-[#475569] text-[11.5px] uppercase">
              <tr>
                <th class="px-3 py-3">Waktu</th>
                <th class="px-3 py-3">Pengguna</th>
                <th class="px-3 py-3">Role</th>
                <th class="px-3 py-3">Aksi / Action</th>
                <th class="px-3 py-3">Modul</th>
                <th class="px-3 py-3">Deskripsi Audit</th>
                <th class="px-3 py-3">IP Address</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB] text-[12.5px] leading-[17px]">
              <tr v-for="log in logs.data" :key="log.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-3 py-2.5 text-gray-500 font-mono text-[11px] whitespace-nowrap">
                  {{ new Date(log.created_at).toLocaleString('id-ID') }}
                </td>
                <td class="px-3 py-2.5 font-bold text-[#111827] text-[13px]">{{ log.username }}</td>
                <td class="px-3 py-2.5">
                  <span class="px-2 py-0.5 text-[10.5px] font-semibold rounded-full border whitespace-nowrap" :class="getRoleBadgeStyle(log.user_role)">
                    {{ formatRole(log.user_role) }}
                  </span>
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                  <span class="px-2 py-0.5 text-[10.5px] font-semibold bg-gray-100 text-gray-800 rounded">
                    {{ log.action }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-gray-600 font-medium text-[12px] whitespace-nowrap">{{ log.module }}</td>
                <td class="px-3 py-2.5 text-[#374151]">{{ log.description }}</td>
                <td class="px-3 py-2.5 font-mono text-gray-400 text-[11px]">{{ log.ip_address || '-' }}</td>
              </tr>
              <tr v-if="logs.data.length === 0">
                <td colspan="7" class="px-3 py-8 text-center text-gray-400">Belum ada catatan log aktivitas.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="logs.links"
          :from="logs.from"
          :to="logs.to"
          :total="logs.total"
        />
      </div>
    </div>
  </EdpLayout>
</template>
