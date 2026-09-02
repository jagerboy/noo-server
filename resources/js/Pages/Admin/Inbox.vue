<script setup lang="js">
/**
 * Halaman Inbox Portal Web Admin Distributor (NOO+ v2.0).
 * Vue 3 Composition API + Light Mode Theme Design System Specification.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';

const props = defineProps({
  submissions: {
    type: [Array, Object],
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => null,
  },
  userBranch: {
    type: String,
    default: '',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

// State Filter & Search
const searchQuery = ref('');
const statusFilter = ref('ALL');

// State Modal Detail & Reject
const selectedSubmission = ref(null);
const showDetailModal = ref(false);
const showRejectModal = ref(false);
const activePhotoZoom = ref(null);
const custCodeInputRef = ref(null);
const showCustCodeWarning = ref(false);

// Form Submit SPV & Update Detail (Langsung dari Modal Detail Preview)
const detailForm = useForm({
  request_id: '',
  custcode_distributor: '',
  admin_notes: '',
});

// Form Reject Toko
const rejectForm = useForm({
  request_id: '',
  reject_reason: '',
});

// State & Form Edit Nama Outlet
const isEditingNamaOutlet = ref(false);
const editNamaForm = useForm({
  request_id: '',
  nama_noo: '',
});

// Validasi status: Nama toko HANYA bisa diubah sebelum disubmit ke SPV (status masih SE_SUBMITTED)
const canEditNamaOutlet = computed(() => {
  return selectedSubmission.value && selectedSubmission.value.status === 'SE_SUBMITTED';
});

function startEditNamaOutlet() {
  if (!selectedSubmission.value || !canEditNamaOutlet.value) return;
  editNamaForm.request_id = selectedSubmission.value.request_id;
  editNamaForm.nama_noo = selectedSubmission.value.nama_noo || '';
  isEditingNamaOutlet.value = true;
}

function cancelEditNamaOutlet() {
  isEditingNamaOutlet.value = false;
  editNamaForm.reset();
}

function submitUpdateNamaOutlet() {
  if (!editNamaForm.nama_noo.trim()) return;
  const newNama = editNamaForm.nama_noo.trim();
  const requestId = editNamaForm.request_id;
  
  // Langsung sembunyikan form edit input/button Simpan & Batal
  isEditingNamaOutlet.value = false;

  editNamaForm.post(route('admin.update_nama_outlet'), {
    preserveScroll: true,
    onSuccess: () => {
      if (selectedSubmission.value) {
        selectedSubmission.value.nama_noo = newNama;
      }
      const rawList = Array.isArray(props.submissions) ? props.submissions : (props.submissions?.data || []);
      const targetSub = rawList.find(s => s.request_id === requestId);
      if (targetSub) {
        targetSub.nama_noo = newNama;
      }
    },
    onError: () => {
      // Tampilkan kembali jika gagal
      isEditingNamaOutlet.value = true;
    }
  });
}

const rawSubmissions = computed(() => {
  if (Array.isArray(props.submissions)) return props.submissions;
  if (props.submissions && Array.isArray(props.submissions.data)) return props.submissions.data;
  return [];
});

// Filter Submisi Data Toko
const filteredSubmissions = computed(() => {
  return rawSubmissions.value.filter((item) => {
    const matchesSearch =
      (item.nama_noo || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.salesman_name || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.salesman_code || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.alamat_noo || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.custcode_distributor || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.code_noo_principal || '').toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchesStatus =
      statusFilter.value === 'ALL'
        ? true
        : statusFilter.value === 'REJECTED'
        ? ['ADMIN_REJECTED', 'SPV_REJECTED', 'EDP_REJECTED', 'REJECTED_ADMIN', 'REJECTED_SPV', 'REJECTED_EDP'].includes(item.status)
        : item.status === statusFilter.value;

    return matchesSearch && matchesStatus;
  });
});

// State & Handler Sort Tabel (Default: Pending Admin & submitted_at terbaru)
const sortKey = ref('default_order');
const sortDir = ref('asc');

const sortSelect = computed({
  get() {
    if (sortKey.value === 'default_order') return 'submitted_at_desc';
    return `${sortKey.value}_${sortDir.value}`;
  },
  set(val) {
    if (!val) return;
    const parts = val.split('_');
    const dir = parts.pop();
    const key = parts.join('_');
    sortKey.value = key;
    sortDir.value = dir;
  },
});

const sortedSubmissions = computed(() => {
  const list = [...filteredSubmissions.value];
  if (!sortKey.value || sortKey.value === 'default_order') {
    return list.sort((a, b) => {
      const aPending = a.status === 'SE_SUBMITTED' ? 0 : 1;
      const bPending = b.status === 'SE_SUBMITTED' ? 0 : 1;
      if (aPending !== bPending) return aPending - bPending;
      const aTime = a.submitted_at ? new Date(a.submitted_at).getTime() : (a.created_at ? new Date(a.created_at).getTime() : 0);
      const bTime = b.submitted_at ? new Date(b.submitted_at).getTime() : (b.created_at ? new Date(b.created_at).getTime() : 0);
      return bTime - aTime;
    });
  }

  return list.sort((a, b) => {
    let valA = a[sortKey.value] ?? '';
    let valB = b[sortKey.value] ?? '';

    if (['submitted_at', 'created_at', 'pushed_to_spv_at', 'spv_submit_at', 'pushed_to_edp_at', 'edp_reviewed_at'].includes(sortKey.value)) {
      valA = valA ? new Date(valA).getTime() : 0;
      valB = valB ? new Date(valB).getTime() : 0;
    } else if (typeof valA === 'string') {
      valA = valA.toLowerCase();
      valB = valB.toLowerCase();
    }

    if (valA < valB) return sortDir.value === 'asc' ? -1 : 1;
    if (valA > valB) return sortDir.value === 'asc' ? 1 : -1;
    return 0;
  });
});

// Stats Ringkasan Data Toko
const stats = computed(() => {
  if (props.stats) return props.stats;
  const list = rawSubmissions.value;
  const total = list.length;
  const pendingSe = list.filter((i) => i.status === 'SE_SUBMITTED').length;
  const pushedSpv = list.filter((i) => i.status === 'PUSHED_TO_SPV').length;
  const rejected = list.filter((i) => ['ADMIN_REJECTED', 'SPV_REJECTED', 'EDP_REJECTED', 'REJECTED_ADMIN', 'REJECTED_SPV', 'REJECTED_EDP'].includes(i.status)).length;
  const approved = list.filter((i) => ['EDP_APPROVED', 'APPROVED_EDP', 'APPROVED_BY_SPV', 'APPROVED_SPV'].includes(i.status)).length;

  return { total, pendingSe, pushedSpv, rejected, approved };
});

// Helper Resolusi URL Foto (/media-photo/ Streaming Route)
function getPhotoUrl(pathOrUrl) {
  if (!pathOrUrl) return null;
  if (pathOrUrl.includes('/media-photo/')) return pathOrUrl;
  const cleanPath = pathOrUrl.replace(/^https?:\/\/[^\/]+\/storage\//, '').replace(/^\/?storage\//, '');
  return `/media-photo/${cleanPath}`;
}

// Helper Format Tanggal
function formatDate(dtString) {
  if (!dtString) return '-';
  const d = new Date(dtString);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function handleEscKeydown(e) {
  if (e.key === 'Escape') {
    if (activePhotoZoom.value) {
      activePhotoZoom.value = null;
      return;
    }
    if (showRejectModal.value) {
      showRejectModal.value = false;
      return;
    }
    if (showDetailModal.value) {
      showDetailModal.value = false;
      selectedSubmission.value = null;
      return;
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleEscKeydown);
});
onUnmounted(() => {
  window.removeEventListener('keydown', handleEscKeydown);
});

// Buka Modal Detail Toko
function openDetailModal(item) {
  selectedSubmission.value = item;
  detailForm.request_id = item.request_id;
  detailForm.custcode_distributor = item.custcode_distributor || '';
  detailForm.admin_notes = item.admin_notes || '';
  showCustCodeWarning.value = false;
  isEditingNamaOutlet.value = false;
  showDetailModal.value = true;
}

// Buka Modal Reject
function openRejectModal(item) {
  selectedSubmission.value = item;
  rejectForm.request_id = item.request_id;
  rejectForm.reject_reason = '';
  showRejectModal.value = true;
}

// Submit Toko ke SPV Area langsung dari Modal Detail Preview
function submitToSpvFromDetail() {
  if (!detailForm.custcode_distributor) {
    showCustCodeWarning.value = true;
    if (custCodeInputRef.value) {
      custCodeInputRef.value.focus();
      custCodeInputRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return;
  }

  showCustCodeWarning.value = false;
  detailForm.post(route('admin.submit_spv'), {
    onSuccess: () => {
      showDetailModal.value = false;
      detailForm.reset();
    },
  });
}

// Proses Reject Toko
function submitReject() {
  rejectForm.post(route('admin.reject'), {
    onSuccess: () => {
      showRejectModal.value = false;
      showDetailModal.value = false;
      rejectForm.reset();
    },
  });
}

// Helper Format Label Status (Bebas dari underscore, tampilan elegan & rapi)
function formatStatusLabel(status) {
  if (!status) return '-';
  switch (status) {
    case 'SE_SUBMITTED':
    case 'SUBMITTED':
      return 'Pending Admin';
    case 'PUSHED_TO_SPV':
    case 'ADMIN_APPROVED':
      return 'Pushed to SPV';
    case 'APPROVED_SPV':
    case 'APPROVED_BY_SPV':
    case 'PUSHED_TO_EDP':
      return 'Approved SPV';
    case 'APPROVED_EDP':
    case 'EDP_APPROVED':
      return 'Approved EDP';
    case 'ADMIN_REJECTED':
    case 'REJECTED_ADMIN':
      return 'Rejected Admin';
    case 'SPV_REJECTED':
    case 'REJECTED_SPV':
      return 'Rejected SPV Area';
    case 'EDP_REJECTED':
    case 'REJECTED_EDP':
      return 'Rejected EDP';
    case 'REVISION_KTP':
      return 'Revisi KTP';
    default:
      return String(status).replace(/_/g, ' ');
  }
}

// Warna Berbeda Berdasarkan Setiap Status Approval (Modern Soft Palette & Vibrant Text)
function getStatusBadgeStyle(status) {
  switch (status) {
    case 'SE_SUBMITTED':
    case 'SUBMITTED':
      return 'bg-amber-50 text-amber-700 border-amber-200 shadow-2xs font-semibold'; // Pending SE / Admin
    case 'PUSHED_TO_SPV':
    case 'ADMIN_APPROVED':
      return 'bg-blue-50 text-blue-700 border-blue-200 shadow-2xs font-semibold'; // Pushed to SPV
    case 'APPROVED_SPV':
    case 'APPROVED_BY_SPV':
    case 'PUSHED_TO_EDP':
      return 'bg-purple-50 text-purple-700 border-purple-200 shadow-2xs font-semibold'; // Approved SPV
    case 'APPROVED_EDP':
    case 'EDP_APPROVED':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200 shadow-2xs font-semibold'; // Fully Approved EDP
    case 'ADMIN_REJECTED':
    case 'REJECTED_ADMIN':
      return 'bg-orange-50 text-orange-700 border-orange-200 shadow-2xs font-semibold'; // Ditolak Admin Dist
    case 'SPV_REJECTED':
    case 'REJECTED_SPV':
      return 'bg-rose-50 text-rose-700 border-rose-200 shadow-2xs font-semibold'; // Ditolak SPV Area
    case 'EDP_REJECTED':
    case 'REJECTED_EDP':
      return 'bg-red-50 text-red-700 border-red-200 shadow-2xs font-semibold'; // Ditolak EDP Principal
    case 'REVISION_KTP':
      return 'bg-cyan-50 text-cyan-700 border-cyan-200 shadow-2xs font-semibold'; // Revisi KTP
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200 font-semibold';
  }
}

// Helper Rute Kunjungan
function getRouteDaysSummary(item) {
  if (!item) return 'Belum di-set';
  const days = [];
  if (item.h1 === 'Y' || item.h1 === 'YES') days.push('Sen');
  if (item.h2 === 'Y' || item.h2 === 'YES') days.push('Sel');
  if (item.h3 === 'Y' || item.h3 === 'YES') days.push('Rab');
  if (item.h4 === 'Y' || item.h4 === 'YES') days.push('Kam');
  if (item.h5 === 'Y' || item.h5 === 'YES') days.push('Jum');
  if (item.h6 === 'Y' || item.h6 === 'YES') days.push('Sab');
  if (item.h7 === 'Y' || item.h7 === 'YES') days.push('Ming');
  return days.length > 0 ? days.join(', ') : 'Belum di-set';
}

function getRouteWeeksSummary(item) {
  if (!item) return 'Belum di-set';
  const weeks = [];
  if (item.m1 === 'Y' || item.m1 === 'YES') weeks.push('M1');
  if (item.m2 === 'Y' || item.m2 === 'YES') weeks.push('M2');
  if (item.m3 === 'Y' || item.m3 === 'YES') weeks.push('M3');
  if (item.m4 === 'Y' || item.m4 === 'YES') weeks.push('M4');
  return weeks.length > 0 ? weeks.join(', ') : 'Belum di-set';
}

// UI Progress Tracker Helpers (Step 1: Admin, Step 2: SPV, Step 3: EDP)
function getStepStatus(step, item) {
  if (!item) return 'NOT_STARTED';
  const s = item.status || '';

  if (step === 1) {
    if (['ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(s)) return 'REJECTED';
    if (s === 'SE_SUBMITTED') return 'PENDING';
    return 'COMPLETED';
  }

  if (step === 2) {
    if (['SE_SUBMITTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(s)) return 'NOT_STARTED';
    if (s === 'PUSHED_TO_SPV') return 'PENDING';
    if (['SPV_REJECTED', 'REJECTED_SPV'].includes(s)) return 'REJECTED';
    return 'COMPLETED';
  }

  if (step === 3) {
    if (['SE_SUBMITTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN', 'PUSHED_TO_SPV', 'SPV_REJECTED', 'REJECTED_SPV'].includes(s)) return 'NOT_STARTED';
    if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(s)) return 'PENDING';
    if (['EDP_REJECTED', 'REJECTED_EDP'].includes(s)) return 'REJECTED';
    if (['APPROVED_EDP', 'EDP_APPROVED'].includes(s)) return 'COMPLETED';
  }

  return 'NOT_STARTED';
}

function getStepNodeStyle(step, item) {
  const st = getStepStatus(step, item);
  if (st === 'COMPLETED') return 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-md';
  if (st === 'REJECTED') return 'bg-red-600 text-white ring-4 ring-red-100 shadow-md';
  if (st === 'PENDING') return 'bg-amber-500 text-white ring-4 ring-amber-100 animate-pulse shadow-md';
  return 'bg-slate-200 text-slate-500 border-2 border-slate-300';
}

function getStepBadgeStyle(step, item) {
  const st = getStepStatus(step, item);
  if (st === 'COMPLETED') return 'bg-emerald-100 text-emerald-800 border-emerald-300';
  if (st === 'REJECTED') return 'bg-red-100 text-red-800 border-red-300';
  if (st === 'PENDING') return 'bg-amber-100 text-amber-800 border-amber-300';
  return 'bg-slate-100 text-slate-500 border-slate-300';
}

function getStepLabel(step, item) {
  const st = getStepStatus(step, item);
  if (step === 1) {
    if (st === 'COMPLETED') return '✓ DIINPUT (PUSHED)';
    if (st === 'REJECTED') return '✕ DITOLAK ADMIN';
    return '⏳ PENDING INPUT';
  }
  if (step === 2) {
    if (st === 'COMPLETED') return '✓ DISETUJUI SPV';
    if (st === 'REJECTED') return '✕ DITOLAK SPV';
    if (st === 'PENDING') return '⏳ REVIEW SPV';
    return '🔒 BELUM DIMULAI';
  }
  if (step === 3) {
    if (st === 'COMPLETED') return '✓ DISETUJUI EDP';
    if (st === 'REJECTED') return '✕ DITOLAK EDP';
    if (st === 'PENDING') return '⏳ REVIEW EDP';
    return '🔒 BELUM DIMULAI';
  }
  return '';
}

function getApproverName(step, item) {
  if (!item) return null;

  if (step === 1) {
    if (item.approved_by_admin || item.pushed_by_admin) return item.approved_by_admin || item.pushed_by_admin;
    if (item.admin_notes) {
      const match = item.admin_notes.match(/oleh\s+([^\]\n\r]+)/i);
      if (match) return match[1].trim();
    }
    if (['PUSHED_TO_SPV', 'APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(item.status)) {
      return item.branch_name ? `Admin ${item.branch_name}` : `Admin Cabang (${item.branch_id})`;
    }
    return null;
  }

  if (step === 2) {
    if (item.approved_by_spv) return item.approved_by_spv;
    if (item.spv_notes) {
      const match = item.spv_notes.match(/oleh\s+([^\]\n\r]+)/i);
      if (match) return match[1].trim();
    }
    return null;
  }

  if (step === 3) {
    if (item.approved_by_edp) return item.approved_by_edp;
    if (item.edp_notes) {
      const match = item.edp_notes.match(/oleh\s+([^\]\n\r]+)/i);
      if (match) return match[1].trim();
    }
    return null;
  }

  return null;
}

function getStepTimestamp(step, item) {
  if (!item) return '-';

  if (step === 1) {
    const ts = item.pushed_to_spv_at || item.submitted_at || item.created_at;
    if (ts) {
      const isPushed = ['PUSHED_TO_SPV', 'APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP'].includes(item.status);
      return (isPushed ? 'Pushed: ' : 'Disubmit: ') + formatDate(ts);
    }
    return '-';
  }

  if (step === 2) {
    const ts = item.spv_submit_at || item.pushed_to_edp_at;
    if (ts) {
      const isRejected = ['SPV_REJECTED', 'REJECTED_SPV'].includes(item.status);
      return (isRejected ? 'Ditolak: ' : 'Approved: ') + formatDate(ts);
    }
    if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP'].includes(item.status)) {
      return 'Approved: ' + formatDate(item.updated_at);
    }
    if (['SPV_REJECTED', 'REJECTED_SPV'].includes(item.status)) {
      return 'Ditolak: ' + formatDate(item.updated_at);
    }
    return 'Menunggu review SPV';
  }

  if (step === 3) {
    const ts = item.edp_reviewed_at || item.injected_at;
    if (ts) {
      const isRejected = ['EDP_REJECTED', 'REJECTED_EDP'].includes(item.status);
      return (isRejected ? 'Ditolak: ' : 'Approved: ') + formatDate(ts);
    }
    if (['APPROVED_EDP', 'EDP_APPROVED'].includes(item.status)) {
      return 'Approved EDP: ' + formatDate(item.updated_at);
    }
    if (['EDP_REJECTED', 'REJECTED_EDP'].includes(item.status)) {
      return 'Ditolak EDP: ' + formatDate(item.updated_at);
    }
    return 'Menunggu keputusan EDP';
  }

  return '-';
}

function getLineStyle(stepBefore, item) {
  const st = getStepStatus(stepBefore, item);
  if (st === 'COMPLETED') return 'bg-emerald-500';
  if (st === 'REJECTED') return 'bg-red-500';
  return 'bg-slate-200';
}
function getRowStyle(item) {
  const st = item.status;
  if (['EDP_APPROVED', 'APPROVED_EDP', 'APPROVED_BY_SPV', 'APPROVED_SPV', 'INJECTED'].includes(st)) {
    return 'bg-emerald-50/50 hover:bg-emerald-100/60 text-slate-900';
  }
  if (['ADMIN_REJECTED', 'SPV_REJECTED', 'EDP_REJECTED', 'REJECTED_ADMIN', 'REJECTED_SPV', 'REJECTED_EDP'].includes(st)) {
    return 'bg-rose-50/50 hover:bg-rose-100/60 text-slate-900';
  }
  // Pending
  return 'bg-white hover:bg-slate-50 text-slate-900';
}
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Page Header with Brand Stat Cards -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <div class="flex items-center space-x-3">
            <h1 class="text-xl md:text-[24px] font-semibold text-[#111827] tracking-tight leading-[1.4]">Inbox Submisi Outlet</h1>
            <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-[#DBEAFE] text-[#1D4ED8] border border-[#93C5FD]">
              Cabang: {{ userBranch || 'Unassigned' }}
            </span>
          </div>
          <p class="text-[14px] leading-[1.5] font-normal text-[#6B7280] mt-1">
            Verifikasi data pendaftaran toko baru, pengisian kode customer distributor, & penyerahan ke SPV Area.
          </p>
        </div>

        <!-- Metric Stat Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center flex flex-col justify-between h-full">
            <div class="min-h-[32px] flex items-center justify-center">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-[#B45309]">Pending Admin</span>
            </div>
            <div class="text-2xl font-bold text-[#D97706] mt-1">{{ stats.pendingSe }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center flex flex-col justify-between h-full">
            <div class="min-h-[32px] flex items-center justify-center">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-[#1D4ED8]">Pushed to SPV</span>
            </div>
            <div class="text-2xl font-bold text-[#2563EB] mt-1">{{ stats.pushedSpv }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center flex flex-col justify-between h-full">
            <div class="min-h-[32px] flex items-center justify-center">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-[#15803D]">EDP Approved</span>
            </div>
            <div class="text-2xl font-bold text-[#16A34A] mt-1">{{ stats.approved }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center flex flex-col justify-between h-full">
            <div class="min-h-[32px] flex items-center justify-center">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-[#B91C1C]">Ditolak</span>
            </div>
            <div class="text-2xl font-bold text-[#DC2626] mt-1">{{ stats.rejected }}</div>
          </div>
        </div>
      </div>

      <!-- Filter & Search Toolbar (Red/Blue Accent for Admin Distributor) -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] flex flex-col lg:flex-row items-center justify-between gap-4">
        
        <!-- Search Input (Form Input 16px / 400) -->
        <div class="relative w-full lg:w-80">
          <svg class="w-4 h-4 absolute left-3.5 top-3.5 text-[#9CA3AF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari toko, salesman, custcode, alamat..."
            class="w-full pl-10 pr-4 py-2 text-[15px] font-normal rounded-lg bg-white border border-[#D1D5DB] text-[#374151] placeholder-[#9CA3AF] focus:ring-2 focus:ring-[#DC2626] focus:border-[#B91C1C] transition"
          />
        </div>

        <!-- Filter Status & Sort Dropdowns Side-by-Side -->
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
          <!-- Filter Status Dropdown -->
          <div class="flex items-center space-x-2 w-full sm:w-auto">
            <label class="text-[14px] font-medium text-[#4B5563] whitespace-nowrap">Filter Status:</label>
            <select
              v-model="statusFilter"
              class="w-full sm:w-60 text-[14px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] py-2 px-3 focus:ring-2 focus:ring-[#DC2626] focus:border-[#B91C1C] shadow-xs cursor-pointer"
            >
              <option value="ALL">Semua Submisi</option>
              <option value="SE_SUBMITTED">1. Pending Admin</option>
              <option value="ADMIN_REJECTED">2. Rejected Admin</option>
              <option value="PUSHED_TO_SPV">3. Pushed to SPV (Pending SPV)</option>
              <option value="APPROVED_BY_SPV">4. Approved SPV (Pending EDP)</option>
              <option value="SPV_REJECTED">5. Rejected SPV Area</option>
              <option value="EDP_APPROVED">6. Approved EDP (Completed)</option>
              <option value="EDP_REJECTED">7. Rejected EDP Principal</option>
            </select>
          </div>

          <!-- Sort Dropdown (Memindahkan Sort dari Header Tabel) -->
          <div class="flex items-center space-x-2 w-full sm:w-auto">
            <label class="text-[14px] font-medium text-[#4B5563] whitespace-nowrap">Urutkan:</label>
            <select
              v-model="sortSelect"
              class="w-full sm:w-56 text-[14px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] py-2 px-3 focus:ring-2 focus:ring-[#DC2626] focus:border-[#B91C1C] shadow-xs cursor-pointer"
            >
              <option value="submitted_at_desc">Terbaru (Tanggal Submisi)</option>
              <option value="submitted_at_asc">Terlama (Tanggal Submisi)</option>
              <option value="nama_noo_asc">Nama Toko (A - Z)</option>
              <option value="nama_noo_desc">Nama Toko (Z - A)</option>
              <option value="status_asc">Status Approval (Asc)</option>
              <option value="custcode_distributor_asc">CustCode Dist (A - Z)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- TABEL PRESISI DENGAN TABLE-FIXED -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] overflow-hidden">
        <div class="w-full overflow-x-auto">
          <table class="w-full text-left text-[14px] leading-[20px] text-[#374151] table-fixed min-w-[900px]">
            <thead class="bg-[#F3F4F6] text-[14px] font-semibold text-[#1F2937] border-b border-[#E5E7EB] select-none">
              <tr>
                <th class="w-[20%] px-4 py-3.5">Toko & Tipe Outlet</th>
                <th class="w-[22%] px-4 py-3.5">Alamat & Wilayah</th>
                <th class="w-[18%] px-4 py-3.5">Salesman & Tanggal</th>
                <th class="w-[16%] px-4 py-3.5">Status</th>
                <th class="w-[11%] px-4 py-3.5">Cust Code Dist.</th>
                <th class="w-[11%] px-4 py-3.5">Cust Code Principal</th>
                <th class="w-[12%] px-4 py-3.5 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="sortedSubmissions.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-[#6B7280] font-normal">
                  Tidak ada data outlet yang cocok dengan pencarian / filter.
                </td>
              </tr>
              <tr
                v-for="item in sortedSubmissions"
                :key="item.id || item.request_id"
                class="transition border-b group"
                :class="getRowStyle(item)"
              >
                <!-- Toko & Tipe Outlet -->
                <td class="px-4 py-3.5">
                  <div class="font-bold text-[14px] text-[#111827] group-hover:text-[#1D4ED8] transition truncate" :title="item.nama_noo">
                    {{ item.nama_noo }}
                  </div>
                  <div class="flex items-center gap-1.5 mt-1 truncate">
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-[#DBEAFE] text-[#1D4ED8] border border-[#93C5FD] shrink-0">
                      {{ item.type_outlet_code }}
                    </span>
                    <span class="text-[12px] text-[#6B7280] truncate">{{ item.type_outlet_desc || 'Retail Outlet' }}</span>
                  </div>
                </td>

                <!-- Alamat & Wilayah -->
                <td class="px-4 py-3.5">
                  <div class="truncate text-[#374151] text-[14px] font-normal" :title="item.alamat_noo">
                    {{ item.alamat_noo }}
                  </div>
                  <div class="text-[12px] text-[#6B7280] mt-0.5 truncate">
                    📍 {{ item.kec_noo }}, {{ item.kab_kota_noo }}
                  </div>
                </td>

                <!-- Salesman & Tanggal -->
                <td class="px-4 py-3.5 truncate">
                  <div class="font-medium text-[#1F2937] text-[14px] truncate">{{ item.salesman_name }}</div>
                  <div class="text-[12px] text-[#6B7280] mt-0.5">
                    {{ formatDate(item.submitted_at || item.created_at) }}
                  </div>
                </td>

                <!-- Status -->
                <td class="px-4 py-3.5">
                  <span class="inline-block px-2.5 py-0.5 rounded-full text-[12px] font-semibold border" :class="getStatusBadgeStyle(item.status)">
                    {{ formatStatusLabel(item.status) }}
                  </span>
                </td>

                <!-- Cust Code Distributor -->
                <td class="px-4 py-3.5 truncate">
                  <span v-if="item.custcode_distributor" class="font-mono text-[12px] font-semibold text-[#1D4ED8] bg-[#DBEAFE] px-2 py-0.5 rounded border border-[#93C5FD] truncate block">
                    {{ item.custcode_distributor }}
                  </span>
                  <span v-else class="text-[12px] italic text-[#9CA3AF]">Belum diisi</span>
                </td>

                <!-- Cust Code Principal -->
                <td class="px-4 py-3.5">
                  <span v-if="item.code_noo_principal" class="font-mono text-[12px] font-semibold text-[#15803D] bg-[#DCFCE7] px-2 py-0.5 rounded border border-[#86EFAC] block whitespace-normal break-words">
                    {{ item.code_noo_principal }}
                  </span>
                  <span v-else class="text-[12px] italic text-[#9CA3AF] block whitespace-normal break-words">Belum tergenerate</span>
                </td>

                <!-- Action Button -->
                <td class="px-4 py-3.5 text-center">
                  <BaseButton
                    variant="primary"
                    size="sm"
                    class="w-full font-sans"
                    @click="openDetailModal(item)"
                  >
                    Kelola Toko
                  </BaseButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Links -->
        <Pagination
          v-if="submissions?.links"
          :links="submissions.links"
          :from="submissions.from"
          :to="submissions.to"
          :total="submissions.total"
          :current-per-page="submissions.per_page"
        />
      </div>

    </div>

    <!-- MODAL SLIDE-OVER PREVIEW DETAIL & FORM INPUT LANGSUNG (LEVEL 1 Z-INDEX 99990) -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedSubmission" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6" @click.self="showDetailModal = false">
      <div class="bg-white rounded-xl max-w-4xl w-full max-h-[85vh] flex flex-col shadow-[0_15px_40px_rgba(0,0,0,0.18)] border border-[#E5E7EB] overflow-hidden text-[#374151]">
        
        <!-- Header Modal (Section Title 22px / 600) -->
        <div class="px-6 py-4 bg-[#1E3A8A] text-white flex items-center justify-between shrink-0">
          <div>
            <div class="flex items-center space-x-2.5 flex-wrap gap-y-1">
              <!-- Edit Mode Nama Outlet -->
              <div v-if="isEditingNamaOutlet" class="flex items-center gap-2">
                <input
                  v-model="editNamaForm.nama_noo"
                  type="text"
                  class="px-3 py-1 text-sm font-bold text-slate-900 bg-white border-2 border-amber-400 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none w-64 sm:w-80 shadow-md"
                  placeholder="Masukkan Nama Outlet..."
                  @keydown.enter="submitUpdateNamaOutlet"
                  @keydown.esc.stop="cancelEditNamaOutlet"
                />
                <button
                  type="button"
                  @click="submitUpdateNamaOutlet"
                  :disabled="editNamaForm.processing || !editNamaForm.nama_noo.trim()"
                  class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs shadow-sm transition disabled:opacity-50 flex items-center gap-1 cursor-pointer"
                  title="Simpan Nama Outlet"
                >
                  <svg v-if="editNamaForm.processing" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <span>Simpan</span>
                </button>
                <button
                  type="button"
                  @click="cancelEditNamaOutlet"
                  class="px-2 py-1 rounded-lg bg-slate-600 hover:bg-slate-500 text-white font-bold text-xs transition cursor-pointer"
                  title="Batal"
                >
                  Batal
                </button>
              </div>

              <!-- Normal Display Nama Outlet + Pencil Edit Button -->
              <div v-else class="flex items-center space-x-2.5">
                <h3 class="text-[22px] font-semibold leading-[28px] text-white">{{ selectedSubmission.nama_noo }}</h3>
                <button
                  v-if="canEditNamaOutlet"
                  type="button"
                  @click="startEditNamaOutlet"
                  title="Ubah / Rename Nama Toko"
                  class="px-2 py-1 rounded-lg bg-amber-400 hover:bg-amber-300 active:bg-amber-500 text-slate-950 font-bold text-xs shadow-md transition-all flex items-center gap-1.5 cursor-pointer border border-amber-300 hover:scale-105"
                >
                  <svg class="w-3.5 h-3.5 text-slate-950 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                  <span>Ubah Nama</span>
                </button>
              </div>

              <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-white/20 text-white border border-white/30 shrink-0">
                {{ selectedSubmission.type_outlet_code }} - {{ selectedSubmission.type_outlet_desc }}
              </span>
            </div>
            <p class="text-xs text-blue-200 mt-0.5">Request ID: {{ selectedSubmission.request_id }} | Branch: {{ selectedSubmission.branch_id }}</p>
          </div>
          <button @click="showDetailModal = false" class="text-blue-200 hover:text-white text-xl font-bold p-1 hover:bg-blue-800 rounded-lg cursor-pointer">
            ✕
          </button>
        </div>

        <!-- Body Modal Detail -->
        <div class="p-6 space-y-6 overflow-y-auto flex-1 bg-[#F8FAFC]">

          <!-- Info Grid Lapang Komprehensif -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-sm">
            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Pemilik Outlet & No. HP</p>
              <p class="text-[14px] font-semibold text-[#111827] mt-0.5">
                {{ selectedSubmission.nama_pemilik_outlet || '-' }}
                <span class="text-[#6B7280] font-normal">({{ selectedSubmission.no_hp_noo || selectedSubmission.no_hp || '-' }})</span>
              </p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Salesman & Waktu Submisi</p>
              <p class="text-[14px] font-semibold text-[#111827] mt-0.5">
                {{ selectedSubmission.salesman_name }} ({{ selectedSubmission.salesman_code }})
              </p>
              <p class="text-[12px] text-[#6B7280] mt-0.5">
                🗓️ {{ formatDate(selectedSubmission.submitted_at || selectedSubmission.created_at) }}
              </p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Principal & Entity Region</p>
              <p class="text-[14px] font-semibold text-[#111827] mt-0.5">
                {{ selectedSubmission.principal }} (Code: {{ selectedSubmission.principal_code }})
              </p>
              <p class="text-[12px] text-[#6B7280] mt-0.5">Region Code: {{ selectedSubmission.region_code || '-' }}</p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Status & Kode Customer</p>
              <div class="flex flex-wrap items-center gap-2 mt-0.5">
                <span class="px-2.5 py-0.5 rounded-full text-[12px] font-semibold border" :class="getStatusBadgeStyle(selectedSubmission.status)">
                  {{ formatStatusLabel(selectedSubmission.status) }}
                </span>
                <span v-if="selectedSubmission.custcode_distributor" class="font-mono text-[12px] font-semibold text-[#1D4ED8] bg-[#DBEAFE] px-2 py-0.5 rounded border border-[#93C5FD]">
                  Dist: {{ selectedSubmission.custcode_distributor }}
                </span>
              </div>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Customer Code Principal</p>
              <div class="mt-1">
                <span
                  v-if="selectedSubmission.code_noo_principal"
                  class="px-2.5 py-1 text-[14px] font-mono font-bold text-[#065F46] bg-[#D1FAE5] border border-[#6EE7B7] rounded-md inline-block"
                >
                  {{ selectedSubmission.code_noo_principal }}
                </span>
                <span v-else class="text-[13px] text-[#94A3B8] italic">
                  Belum tergenerate (Menunggu Approval EDP)
                </span>
              </div>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Alamat Lengkap & Wilayah</p>
              <p class="text-[14px] text-[#374151] mt-0.5">{{ selectedSubmission.alamat_noo }}</p>
              <p class="text-[12px] text-[#6B7280] mt-0.5">Kel. {{ selectedSubmission.kel_noo }}, Kec. {{ selectedSubmission.kec_noo }}, {{ selectedSubmission.kab_kota_noo }}, {{ selectedSubmission.provinsi_noo }}</p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">GPS Koordinat</p>
              <p class="text-[14px] font-mono text-[#374151] mt-0.5">{{ selectedSubmission.la }}, {{ selectedSubmission.lg }}</p>
              <p class="text-[12px] text-[#15803D] font-semibold mt-0.5">Akurasi GPS: {{ selectedSubmission.accuracy_m }} meter</p>
            </div>
          </div>

          <!-- SECTION PREVIEW MAPS (PETA LOKASI TOKO INDEPENDEN SEPERTI INBOX SPV & PRINCIPAL) -->
          <div class="space-y-3 bg-[#F8FAFC] p-4 rounded-[10px] border border-[#E5E7EB]">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider flex items-center gap-2">
                <span>🌐 Preview Peta Lokasi Toko</span>
              </h3>
              <a
                :href="`https://www.google.com/maps?q=${selectedSubmission.la},${selectedSubmission.lg}`"
                target="_blank"
                class="text-[13px] font-semibold text-[#2563EB] hover:text-[#1D4ED8] flex items-center gap-1"
              >
                <span>Buka Google Maps ↗</span>
              </a>
            </div>

            <!-- Interactive Google Maps Embed -->
            <div class="w-full h-72 bg-[#E5E7EB] rounded-[8px] overflow-hidden border border-[#D1D5DB] relative shadow-inner">
              <iframe
                v-if="selectedSubmission.la && selectedSubmission.lg"
                class="w-full h-full border-0"
                :src="`https://maps.google.com/maps?q=${selectedSubmission.la},${selectedSubmission.lg}&z=17&output=embed`"
                allowfullscreen=""
                loading="lazy"
              ></iframe>
              <div v-else class="w-full h-full flex items-center justify-center text-slate-400 text-xs italic">
                Koordinat GPS tidak tersedia
              </div>
            </div>
          </div>

          <!-- BERKAS FOTO TOKO -->
          <div>
            <h4 class="text-[14px] font-semibold uppercase text-[#4B5563] tracking-wider mb-3">
              📸 BERKAS FOTO TOKO
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Foto Depan -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">1. FOTO DEPAN</span>
                <div v-if="getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)">
                  <!-- Image Protection -->
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)"
                    alt="Foto Depan"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Overlay (Tanpa embel-embel ASWFOODS/INAFOODS) -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-white font-semibold text-xs opacity-0 group-hover:opacity-100 transition rounded-lg z-20">🔎 Zoom Foto</span>
                </div>
                <div v-else class="h-44 flex items-center justify-center text-[#9CA3AF] text-xs italic bg-[#F3F4F6] rounded-lg">Belum Terupload</div>
              </div>

              <!-- Foto Dalam -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">2. FOTO DALAM</span>
                <div v-if="getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)">
                  <!-- Image Protection -->
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)"
                    alt="Foto Dalam"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Overlay -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-white font-semibold text-xs opacity-0 group-hover:opacity-100 transition rounded-lg z-20">🔎 Zoom Foto</span>
                </div>
                <div v-else class="h-44 flex items-center justify-center text-[#9CA3AF] text-xs italic bg-[#F3F4F6] rounded-lg">Belum Terupload</div>
              </div>

              <!-- Foto KTP -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">3. FOTO KTP</span>
                <div v-if="getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)">
                  <!-- Image Protection -->
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)"
                    alt="Foto KTP"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Overlay -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-white font-semibold text-xs opacity-0 group-hover:opacity-100 transition rounded-lg z-20">🔎 Zoom Foto</span>
                </div>
                <div v-else class="h-44 flex items-center justify-center text-[#9CA3AF] text-xs italic bg-[#F3F4F6] rounded-lg">Belum Terupload</div>
              </div>
            </div>
          </div>

          <!-- SECTION 4: TRACK RECORD PERSETUJUAN (TIMELINE PROGRESS TRACKER) -->
          <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-[#F3F4F6] pb-3 flex-wrap gap-2">
              <h4 class="text-[14px] font-semibold text-[#111827] uppercase tracking-wider flex items-center gap-2">
                <span>📈 TRACK RECORD PERSETUJUAN (PROGRESS TRACKER)</span>
              </h4>
              <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200">
                3-Step Audit Trail
              </span>
            </div>

            <!-- Vertical Timeline List -->
            <div class="relative pl-7 sm:pl-9 space-y-4 pt-1 pb-1 before:absolute before:left-3.5 sm:before:left-4.5 before:top-4 before:bottom-4 before:w-0.5 before:bg-slate-200">
              
              <!-- STEP 1: Admin Distributor -->
              <div class="relative flex items-start">
                <!-- Step Circle Node -->
                <div :class="getStepNodeStyle(1, selectedSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                  <span v-if="getStepStatus(1, selectedSubmission) === 'COMPLETED'">✓</span>
                  <span v-else-if="getStepStatus(1, selectedSubmission) === 'REJECTED'">✕</span>
                  <span v-else-if="getStepStatus(1, selectedSubmission) === 'PENDING'">⏳</span>
                  <span v-else>1</span>
                </div>

                <!-- Step Content Card -->
                <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-blue-300 transition-all space-y-2.5">
                  <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-bold text-slate-800 text-[13.5px]">1. Admin Distributor</span>
                      <span :class="getStepBadgeStyle(1, selectedSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                        {{ getStepLabel(1, selectedSubmission) }}
                      </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                      🗓️ {{ getStepTimestamp(1, selectedSubmission) }}
                    </span>
                  </div>

                  <!-- Details Row -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                      <span class="font-medium text-blue-900 bg-blue-50/90 px-2.5 py-0.5 rounded border border-blue-200 text-[11.5px]">
                        👤 {{ getApproverName(1, selectedSubmission) || 'Admin Cabang' }}
                      </span>
                    </div>
                    <div v-if="selectedSubmission.custcode_distributor" class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Kode Dist:</span>
                      <span class="font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 text-[11.5px]">
                        {{ selectedSubmission.custcode_distributor }}
                      </span>
                    </div>
                  </div>

                  <!-- Admin Notes -->
                  <div v-if="selectedSubmission.admin_notes" class="p-2.5 rounded-lg bg-amber-50/90 border border-amber-200 text-amber-900 text-xs">
                    <span class="font-bold block text-[11px] text-amber-800 mb-0.5">💬 Catatan Admin Distributor:</span>
                    <p class="whitespace-pre-line leading-relaxed">{{ selectedSubmission.admin_notes }}</p>
                  </div>
                  <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan admin distributor.</div>
                </div>
              </div>

              <!-- STEP 2: SPV Area -->
              <div class="relative flex items-start">
                <!-- Step Circle Node -->
                <div :class="getStepNodeStyle(2, selectedSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                  <span v-if="getStepStatus(2, selectedSubmission) === 'COMPLETED'">✓</span>
                  <span v-else-if="getStepStatus(2, selectedSubmission) === 'REJECTED'">✕</span>
                  <span v-else-if="getStepStatus(2, selectedSubmission) === 'PENDING'">⏳</span>
                  <span v-else>2</span>
                </div>

                <!-- Step Content Card -->
                <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-purple-300 transition-all space-y-2.5">
                  <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-bold text-slate-800 text-[13.5px]">2. SPV Area</span>
                      <span :class="getStepBadgeStyle(2, selectedSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                        {{ getStepLabel(2, selectedSubmission) }}
                      </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                      🗓️ {{ getStepTimestamp(2, selectedSubmission) }}
                    </span>
                  </div>

                  <!-- Details Row -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                      <span v-if="getApproverName(2, selectedSubmission)" class="font-medium text-purple-900 bg-purple-50/90 px-2.5 py-0.5 rounded border border-purple-200 text-[11.5px]">
                        👤 {{ getApproverName(2, selectedSubmission) }}
                      </span>
                      <span v-else class="text-slate-400 italic text-[11px]">-</span>
                    </div>
                    <div v-if="getRouteDaysSummary(selectedSubmission) !== 'Belum di-set'" class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Rute Sales:</span>
                      <span class="font-semibold text-purple-800 bg-purple-50/90 px-2 py-0.5 rounded border border-purple-200 text-[11.5px]">
                        📅 Rute: {{ getRouteDaysSummary(selectedSubmission) }} | Periode: {{ getRouteWeeksSummary(selectedSubmission) }}
                      </span>
                    </div>
                  </div>

                  <!-- SPV Notes -->
                  <div v-if="selectedSubmission.spv_notes" class="p-2.5 rounded-lg bg-purple-50/90 border border-purple-200 text-purple-900 text-xs">
                    <span class="font-bold block text-[11px] text-purple-800 mb-0.5">💬 Catatan SPV Area:</span>
                    <p class="whitespace-pre-line leading-relaxed">{{ selectedSubmission.spv_notes }}</p>
                  </div>
                  <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan SPV area.</div>
                </div>
              </div>

              <!-- STEP 3: EDP Principal -->
              <div class="relative flex items-start">
                <!-- Step Circle Node -->
                <div :class="getStepNodeStyle(3, selectedSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                  <span v-if="getStepStatus(3, selectedSubmission) === 'COMPLETED'">✓</span>
                  <span v-else-if="getStepStatus(3, selectedSubmission) === 'REJECTED'">✕</span>
                  <span v-else-if="getStepStatus(3, selectedSubmission) === 'PENDING'">⏳</span>
                  <span v-else>3</span>
                </div>

                <!-- Step Content Card -->
                <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-emerald-300 transition-all space-y-2.5">
                  <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-bold text-slate-800 text-[13.5px]">3. EDP Principal</span>
                      <span :class="getStepBadgeStyle(3, selectedSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                        {{ getStepLabel(3, selectedSubmission) }}
                      </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                      🗓️ {{ getStepTimestamp(3, selectedSubmission) }}
                    </span>
                  </div>

                  <!-- Details Row -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                      <span v-if="getApproverName(3, selectedSubmission)" class="font-medium text-emerald-900 bg-emerald-50/90 px-2.5 py-0.5 rounded border border-emerald-200 text-[11.5px]">
                        👤 {{ getApproverName(3, selectedSubmission) }}
                      </span>
                      <span v-else class="text-slate-400 italic text-[11px]">-</span>
                    </div>
                    <div v-if="selectedSubmission.code_noo_principal" class="flex items-center gap-2">
                      <span class="font-semibold text-slate-500 min-w-[70px]">Kode NOO:</span>
                      <span class="font-mono font-bold text-emerald-800 bg-emerald-50/90 px-2 py-0.5 rounded border border-emerald-200 text-[11.5px]">
                        {{ selectedSubmission.code_noo_principal }}
                      </span>
                    </div>
                  </div>

                  <!-- EDP Notes -->
                  <div v-if="selectedSubmission.edp_notes" class="p-2.5 rounded-lg bg-emerald-50/90 border border-emerald-200 text-emerald-900 text-xs">
                    <span class="font-bold block text-[11px] text-emerald-800 mb-0.5">💬 Catatan EDP Principal:</span>
                    <p class="whitespace-pre-line leading-relaxed">{{ selectedSubmission.edp_notes }}</p>
                  </div>
                  <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan EDP principal.</div>
                </div>
              </div>

              <!-- Dedicated Rejection & Reset Reason Section -->
              <div v-if="selectedSubmission.reject_reason || selectedSubmission.reset_reason" class="pt-3 border-t border-slate-200 space-y-2">
                <div v-if="selectedSubmission.reject_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-xs shadow-2xs">
                  <span class="font-bold block text-[11px] text-rose-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                    🚫 Alasan Penolakan (Rejected Reason):
                  </span>
                  <p class="whitespace-pre-line leading-relaxed font-medium text-rose-950">{{ selectedSubmission.reject_reason }}</p>
                </div>
                <div v-if="selectedSubmission.reset_reason" class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs shadow-2xs">
                  <span class="font-bold block text-[11px] text-amber-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                    ↩️ Alasan Pembatalan / Reset:
                  </span>
                  <p class="whitespace-pre-line leading-relaxed font-medium text-amber-950">{{ selectedSubmission.reset_reason }}</p>
                </div>
              </div>

            </div>
          </div>

          <!-- 3. PENGINPUTAN DATA DISTRIBUTOR (Diletakkan Di Paling Bawah Modal) -->
          <div v-if="selectedSubmission.status === 'SE_SUBMITTED'" class="bg-blue-50/50 p-5 rounded-xl border-2 border-[#3B82F6] shadow-md space-y-4">
            <h4 class="text-[14px] font-bold text-[#1E3A8A] uppercase tracking-wider flex items-center gap-2">
              <span>📝 PENGINPUTAN DATA DISTRIBUTOR</span>
              <span class="text-xs font-normal text-[#2563EB] bg-blue-100 px-2 py-0.5 rounded-full border border-blue-200">Langkah Akhir Approval Admin</span>
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-[14px] font-semibold text-[#1E293B] mb-1">
                  Kode Customer Distributor <span class="text-[#DC2626]">*</span>
                </label>
                <input
                  ref="custCodeInputRef"
                  v-model="detailForm.custcode_distributor"
                  type="text"
                  placeholder="Contoh: CUST-MDN-001"
                  class="w-full px-4 py-2.5 text-[16px] font-semibold rounded-lg bg-white border-2 border-[#93C5FD] text-[#0F172A] focus:ring-2 focus:ring-[#2563EB] focus:border-[#2563EB] transition shadow-xs"
                  :class="{ 'border-rose-500 ring-2 ring-rose-200': showCustCodeWarning }"
                />
                <span v-if="showCustCodeWarning" class="text-xs font-bold text-rose-600 mt-1 flex items-center gap-1">
                  ⚠️ Silakan isi Kode Customer Distributor terlebih dahulu.
                </span>
              </div>

              <div>
                <label class="block text-[14px] font-medium text-[#4B5563] mb-1">
                  Catatan Admin Distributor (Opsional)
                </label>
                <input
                  v-model="detailForm.admin_notes"
                  type="text"
                  placeholder="Catatan tambahan untuk SPV..."
                  class="w-full px-4 py-2.5 text-[16px] font-normal rounded-lg bg-white border border-[#D1D5DB] text-[#374151] focus:ring-2 focus:ring-[#3B82F6] focus:border-[#2563EB]"
                />
              </div>
            </div>
          </div>

        </div>

        <!-- Footer Actions Modal (Button Text 15px / 600) -->
        <div class="px-6 py-4 bg-white border-t border-[#E5E7EB] flex justify-between items-center">
          <button @click="showDetailModal = false" class="px-4 py-2 bg-[#F3F4F6] hover:bg-[#E5E7EB] text-[#374151] rounded-lg text-[15px] font-semibold transition">
            Tutup
          </button>

          <div v-if="selectedSubmission.status === 'SE_SUBMITTED'" class="space-x-3 flex items-center">
            <button @click="openRejectModal(selectedSubmission)" class="px-4 py-2 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-lg text-[15px] font-semibold transition">
              Rejected
            </button>
            <!-- Container Group Tooltip Hover untuk Tombol Approved Disabled -->
            <div class="relative group inline-block">
              <button
                @click="submitToSpvFromDetail"
                :disabled="detailForm.processing || !detailForm.custcode_distributor"
                :title="!detailForm.custcode_distributor ? 'Lengkapi Kode Customer Distributor' : ''"
                class="px-5 py-2 bg-[#10B981] hover:bg-[#059669] active:bg-[#047857] disabled:opacity-50 text-white rounded-lg text-[15px] font-semibold transition shadow-sm cursor-pointer disabled:cursor-not-allowed flex items-center gap-1.5"
              >
                <svg v-if="detailForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ detailForm.processing ? 'Memproses...' : 'Approved' }}</span>
              </button>

              <!-- Floating Tooltip Box saat Hover pada Tombol Disabled -->
              <div
                v-if="!detailForm.custcode_distributor"
                class="absolute bottom-full right-0 mb-2 hidden group-hover:flex items-center gap-1.5 px-3 py-1.5 bg-[#0F172A] text-white text-[12px] font-medium rounded-lg shadow-xl whitespace-nowrap z-50 pointer-events-none animate-fade-in border border-slate-700/60"
              >
                <span>⚠️ Lengkapi Kode Customer Distributor</span>
                <!-- Triangle Arrow Pointer -->
                <div class="absolute top-full right-5 -mt-1 border-4 border-transparent border-t-[#0F172A]"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

    <!-- MODAL REJECT TOKO (LEVEL 2 Z-INDEX 999999) -->
    <Teleport to="body">
      <div v-if="showRejectModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" @click.self="showRejectModal = false">
        <div class="bg-white rounded-xl max-w-md w-full shadow-[0_15px_40px_rgba(0,0,0,0.18)] p-6 border border-[#E5E7EB] space-y-4 text-[#374151] my-auto">
          <h3 class="text-[22px] font-semibold text-[#DC2626]">Tolak Submisi Toko</h3>
          <p class="text-[14px] text-[#6B7280]">
            Outlet: <strong class="text-[#111827]">{{ selectedSubmission?.nama_noo }}</strong>
          </p>

          <div>
            <label class="block text-[14px] font-medium text-[#4B5563] mb-1">
              Alasan Penolakan Admin <span class="text-[#DC2626]">*</span>
            </label>
            <textarea
              v-model="rejectForm.reject_reason"
              rows="3"
              placeholder="Contoh: Alamat toko tidak valid / data ganda..."
              class="w-full px-4 py-2.5 text-[16px] font-normal rounded-lg bg-white border border-[#D1D5DB] text-[#374151] focus:ring-2 focus:ring-[#EF4444]"
            ></textarea>
          </div>

          <div class="flex justify-end space-x-3 pt-2">
            <button
              :disabled="rejectForm.processing"
              @click="showRejectModal = false"
              class="px-4 py-2 bg-[#F3F4F6] text-[#374151] rounded-lg text-[15px] font-semibold disabled:opacity-50"
            >
              Batal
            </button>
            <button
              @click="submitReject"
              :disabled="rejectForm.processing || !rejectForm.reject_reason"
              class="px-5 py-2 bg-[#DC2626] hover:bg-[#B91C1C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-[15px] font-semibold transition shadow-sm flex items-center gap-1.5"
            >
              <svg v-if="rejectForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ rejectForm.processing ? 'Memproses...' : 'Konfirmasi Tolak Toko' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MODAL FULLSCREEN ZOOM FOTO (LEVEL 3 Z-INDEX 9999999) -->
    <Teleport to="body">
      <div v-if="activePhotoZoom" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[9999999] bg-black/95 flex items-center justify-center p-4 select-none" @click="activePhotoZoom = null">
        <div class="relative max-w-full max-h-full flex items-center justify-center overflow-hidden rounded-xl shadow-2xl">
          <img
            :src="activePhotoZoom"
            class="max-w-full max-h-[90vh] object-contain rounded-xl pointer-events-none select-none"
            draggable="false"
            oncontextmenu="return false;"
          />
          <!-- Watermark Fullscreen (Tanpa embel-embel ASWFOODS/INAFOODS) -->
          <div class="absolute inset-0 pointer-events-none flex items-center justify-center opacity-30 select-none">
            <p class="text-xs md:text-base font-black text-white uppercase tracking-widest -rotate-30 text-center px-4 drop-shadow-lg">
              CONFIDENTIAL • SYSTEM SECURITY WATERMARK • DO NOT REPRODUCE OR DISTRIBUTE
            </p>
          </div>
        </div>
        <button class="absolute top-6 right-6 text-white text-xs font-semibold bg-[#1F2937] hover:bg-[#111827] px-4 py-2 rounded-lg border border-[#4B5563] z-50">✕ Tutup Zoom</button>
      </div>
    </Teleport>

  </AdminLayout>
</template>
