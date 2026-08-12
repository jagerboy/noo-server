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
});

const search = ref(props.filters?.search || '');

function handleSearch() {
  router.get(route('edp.logs'), { search: search.value }, { preserveState: true, replace: true });
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

      <!-- Search Bar -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] flex items-center gap-3">
        <input
          type="text"
          v-model="search"
          @keyup.enter="handleSearch"
          placeholder="Cari Username, Modul, atau Aktivitas..."
          class="w-full max-w-md px-3.5 py-2 text-xs bg-white border border-[#D1D5DB] rounded-lg focus:ring-2 focus:ring-[#10B981]"
        />
        <button @click="handleSearch" class="px-4 py-2 text-xs font-semibold text-white bg-[#374151] rounded-lg">Cari</button>
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
                <td class="px-4 py-3 font-semibold text-[#059669]">{{ log.user_role }}</td>
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
