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
</script>

<template>
  <EdpLayout>
    <Head title="Logs & Audit Activity - Portal NOO+" />

    <div class="space-y-6">
      <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs">
        <h1 class="text-xl font-bold text-[#111827] flex items-center gap-2">
          <span>📜 Audit Activity & System Logs</span>
        </h1>
        <p class="text-xs text-[#6B7280] mt-1">
          Rekam jejak seluruh aktivitas pengguna (Login, Approval, Rejection, Perubahan Sequence & Master Data).
        </p>
      </div>

      <!-- Search Bar & Role Filter -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 flex-1 min-w-[300px]">
          <input
            type="text"
            v-model="search"
            @keyup.enter="handleSearch"
            placeholder="Cari Username, Modul, atau Aktivitas..."
            class="w-full max-w-md px-3.5 py-2 text-xs bg-white border border-[#D1D5DB] rounded-lg focus:ring-2 focus:ring-[#10B981] outline-none"
          />
          <button @click="handleSearch" class="px-4 py-2 text-xs font-semibold text-white bg-[#374151] hover:bg-slate-800 rounded-lg cursor-pointer transition">
            Cari
          </button>
        </div>

        <!-- Role Filter Dropdown -->
        <div class="flex items-center gap-2 shrink-0">
          <label class="text-xs font-bold text-slate-700">Filter Role:</label>
          <select
            v-model="selectedRole"
            @change="handleSearch"
            class="px-3.5 py-2 text-xs font-semibold bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#10B981] text-slate-800 cursor-pointer shadow-2xs"
          >
            <option value="ALL">Semua Role</option>
            <option v-for="r in availableRoles" :key="r" :value="r">
              {{ r }}
            </option>
          </select>
        </div>
      </div>

      <!-- Table Audit Logs -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F9FAFB] border-b border-[#E5E7EB] font-bold text-[#374151] uppercase">
              <tr>
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Pengguna</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Aksi / Action</th>
                <th class="px-4 py-3">Modul</th>
                <th class="px-4 py-3">Deskripsi Audit</th>
                <th class="px-4 py-3">IP Address</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-for="log in logs.data" :key="log.id" class="hover:bg-emerald-50/20 transition">
                <td class="px-4 py-3 text-gray-500 font-mono">
                  {{ new Date(log.created_at).toLocaleString('id-ID') }}
                </td>
                <td class="px-4 py-3 font-bold text-[#111827]">{{ log.username }}</td>
                <td class="px-4 py-3 font-semibold">
                  <span class="px-2 py-0.5 text-[10px] font-bold rounded border" :class="getRoleBadgeStyle(log.user_role)">
                    {{ log.user_role }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-800 rounded">
                    {{ log.action }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600 font-semibold">{{ log.module }}</td>
                <td class="px-4 py-3 text-[#374151]">{{ log.description }}</td>
                <td class="px-4 py-3 font-mono text-gray-400">{{ log.ip_address || '-' }}</td>
              </tr>
              <tr v-if="logs.data.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada catatan log aktivitas.</td>
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
