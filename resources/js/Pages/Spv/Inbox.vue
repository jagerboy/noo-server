<script setup lang="js">
/**
 * UI Portal Web SPV Area (Vue 3 Composition API).
 * Light Mode Theme dengan Inter Font Family & Tokoh Desain System.
 * Menampilkan daftar submisi toko dari Admin Distributor, pengisian rute H1-H7 & M1-M4,
 * persetujuan SPV (Approve & Pushed ke EDP), dan penolakan SPV.
 */
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import SpvLayout from '@/Layouts/SpvLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';

const props = defineProps({
  submissions: {
    type: Array,
    default: () => [],
  },
  myBranches: {
    type: Array,
    default: () => [],
  },
});

// State Pencarian & Filter
const searchQuery = ref('');
const statusFilter = ref('ALL');
const branchFilter = ref('ALL');

// State Modal Detail & Action
const showDetailModal = ref(false);
const showRejectModal = ref(false);
const selectedSubmission = ref(null);
const activePhotoZoom = ref(null);

// Form Approve SPV (Rute Kunjungan H1-H7 & M1-M4)
const approveForm = useForm({
  request_id: '',
  norute: '1',
  h1: '', h2: '', h3: '', h4: '', h5: '', h6: '', h7: '',
  m1: '', m2: '', m3: '', m4: '',
  spv_notes: '',
});

// Form Reject SPV
const rejectForm = useForm({
  request_id: '',
  reject_reason: '',
});

// Filter Submisi Data Toko
const filteredSubmissions = computed(() => {
  return props.submissions.filter((item) => {
    const matchesSearch =
      (item.nama_noo || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.salesman_name || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.salesman_code || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.branch_name || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.alamat_noo || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.custcode_distributor || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.code_noo_principal || '').toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchesStatus =
      statusFilter.value === 'ALL'
        ? true
        : statusFilter.value === 'REJECTED'
        ? ['REJECTED_SPV', 'SPV_REJECTED', 'REJECTED_EDP', 'EDP_REJECTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(item.status)
        : item.status === statusFilter.value;

    const matchesBranch =
      branchFilter.value === 'ALL'
        ? true
        : item.branch_id === branchFilter.value;

    return matchesSearch && matchesStatus && matchesBranch;
  });
});

// State & Handler Sort Tabel
const sortKey = ref('submitted_at');
const sortDir = ref('desc');

const sortSelect = computed({
  get() {
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
  if (!sortKey.value) return list;

  return list.sort((a, b) => {
    let valA = a[sortKey.value] ?? '';
    let valB = b[sortKey.value] ?? '';

    if (['submitted_at', 'created_at', 'pushed_to_spv_at', 'spv_approved_at', 'edp_approved_at'].includes(sortKey.value)) {
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

// Stats Metric Counter
const stats = computed(() => {
  const total = props.submissions.length;
  const pendingSpv = props.submissions.filter((i) => i.status === 'PUSHED_TO_SPV').length;
  const approvedSpv = props.submissions.filter((i) => ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(i.status)).length;
  const approvedEdp = props.submissions.filter((i) => ['APPROVED_EDP', 'EDP_APPROVED'].includes(i.status)).length;
  const rejected = props.submissions.filter((i) => ['REJECTED_SPV', 'SPV_REJECTED', 'REJECTED_EDP', 'EDP_REJECTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(i.status)).length;

  return { total, pendingSpv, approvedSpv, approvedEdp, rejected };
});

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

// Helper pilih 1 Hari Kunjungan (H1-H7) Button (Single Choice, Auto Disable Others)
function selectDay(dayKey) {
  if (isReadOnly.value) return;
  const days = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'];
  const currentVal = approveForm[dayKey];
  days.forEach((key) => {
    if (key === dayKey) {
      approveForm[key] = currentVal === 'Y' ? '' : 'Y';
    } else {
      approveForm[key] = '';
    }
  });
}

// Helper toggle Pola Minggu (M1-M4) Button & Pattern F2 / F4
const selectedVisitPattern = ref(''); // 'F2' | 'F4' | ''
const f2SelectedType = ref(''); // '' | 'GANJIL' | 'GENAP'

function selectVisitPattern(pattern) {
  if (isReadOnly.value) return;
  selectedVisitPattern.value = pattern;

  if (pattern === 'F4') {
    approveForm.m1 = 'Y';
    approveForm.m2 = 'Y';
    approveForm.m3 = 'Y';
    approveForm.m4 = 'Y';
    f2SelectedType.value = '';
  } else if (pattern === 'F2') {
    f2SelectedType.value = ''; // Biarkan user memilih M1/M3 atau M2/M4 secara manual
    approveForm.m1 = 'T';
    approveForm.m2 = 'T';
    approveForm.m3 = 'T';
    approveForm.m4 = 'T';
  }
}

function selectF2SubOption(option) {
  if (isReadOnly.value) return;
  if (option === 'M1_M3') {
    approveForm.m1 = 'Y';
    approveForm.m2 = 'T';
    approveForm.m3 = 'Y';
    approveForm.m4 = 'T';
  } else if (option === 'M2_M4') {
    approveForm.m1 = 'T';
    approveForm.m2 = 'Y';
    approveForm.m3 = 'T';
    approveForm.m4 = 'Y';
  }
}

function handleWeekClick(weekKey) {
  if (isReadOnly.value || !selectedVisitPattern.value) return;
  if (selectedVisitPattern.value === 'F2') {
    if (weekKey === 'm1' || weekKey === 'm3') {
      selectF2SubOption('M1_M3');
      f2SelectedType.value = 'GANJIL';
    } else if (weekKey === 'm2' || weekKey === 'm4') {
      selectF2SubOption('M2_M4');
      f2SelectedType.value = 'GENAP';
    }
  }
}

// Cek apakah form dalam mode Read-Only / Terkunci (jika status bukan PUSHED_TO_SPV)
const isReadOnly = computed(() => {
  return selectedSubmission.value ? selectedSubmission.value.status !== 'PUSHED_TO_SPV' : false;
});

// Cek apakah ada hari yang sudah dipilih untuk auto-disable button lainnya
const selectedDayKey = computed(() => {
  const days = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'];
  return days.find((k) => approveForm[k] === 'Y') || null;
});

// Cek apakah Rute (Hari H1-H7 dan Pola Minggu M1-M4) valid untuk mengaktifkan button Approved
const isRouteValid = computed(() => {
  const hasDay = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'].some((k) => approveForm[k] === 'Y');
  const hasWeek = ['m1', 'm2', 'm3', 'm4'].some((k) => approveForm[k] === 'Y');
  return hasDay && hasWeek && selectedVisitPattern.value !== '';
});

// Buka Modal Detail & Pengisian Rute Toko
function openDetailModal(item) {
  selectedSubmission.value = item;
  approveForm.request_id = item.request_id;
  approveForm.norute = item.norute || '1';
  approveForm.h1 = item.h1 || '';
  approveForm.h2 = item.h2 || '';
  approveForm.h3 = item.h3 || '';
  approveForm.h4 = item.h4 || '';
  approveForm.h5 = item.h5 || '';
  approveForm.h6 = item.h6 || '';
  approveForm.h7 = item.h7 || '';

  const m1IsY = item.m1 === 'Y' || item.m1 === 'YES';
  const m2IsY = item.m2 === 'Y' || item.m2 === 'YES';
  const m3IsY = item.m3 === 'Y' || item.m3 === 'YES';
  const m4IsY = item.m4 === 'Y' || item.m4 === 'YES';

  if (m1IsY && m2IsY && m3IsY && m4IsY) {
    selectedVisitPattern.value = 'F4';
    f2SelectedType.value = '';
    approveForm.m1 = 'Y';
    approveForm.m2 = 'Y';
    approveForm.m3 = 'Y';
    approveForm.m4 = 'Y';
  } else if (m1IsY || m2IsY || m3IsY || m4IsY) {
    selectedVisitPattern.value = 'F2';
    if (m2IsY || m4IsY) {
      f2SelectedType.value = 'GENAP';
      approveForm.m1 = 'T'; approveForm.m2 = 'Y'; approveForm.m3 = 'T'; approveForm.m4 = 'Y';
    } else {
      f2SelectedType.value = 'GANJIL';
      approveForm.m1 = 'Y'; approveForm.m2 = 'T'; approveForm.m3 = 'Y'; approveForm.m4 = 'T';
    }
  } else {
    selectedVisitPattern.value = '';
    f2SelectedType.value = '';
    approveForm.m1 = ''; approveForm.m2 = ''; approveForm.m3 = ''; approveForm.m4 = '';
  }

  approveForm.spv_notes = item.spv_notes || '';
  showDetailModal.value = true;
}

// Buka Modal Reject SPV
function openRejectModal(item) {
  selectedSubmission.value = item;
  rejectForm.request_id = item.request_id;
  rejectForm.reject_reason = '';
  showRejectModal.value = true;
}

// Submit Approve SPV & Dorong ke EDP Principal
function submitApproveSpv() {
  approveForm.post(route('spv.approve'), {
    onSuccess: () => {
      showDetailModal.value = false;
      approveForm.reset();
    },
  });
}

// Submit Reject SPV
function submitRejectSpv() {
  rejectForm.post(route('spv.reject'), {
    onSuccess: () => {
      showRejectModal.value = false;
      showDetailModal.value = false;
      rejectForm.reset();
    },
  });
}

// Format Hari Rute Singkat
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

// Format Minggu Rute Singkat
function getRouteWeeksSummary(item) {
  if (!item) return 'Belum di-set';
  const weeks = [];
  if (item.m1 === 'Y' || item.m1 === 'YES') weeks.push('M1');
  if (item.m2 === 'Y' || item.m2 === 'YES') weeks.push('M2');
  if (item.m3 === 'Y' || item.m3 === 'YES') weeks.push('M3');
  if (item.m4 === 'Y' || item.m4 === 'YES') weeks.push('M4');
  return weeks.length > 0 ? weeks.join(', ') : 'Belum di-set';
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
      return 'Ditolak Admin';
    case 'SPV_REJECTED':
    case 'REJECTED_SPV':
      return 'Ditolak SPV Area';
    case 'EDP_REJECTED':
    case 'REJECTED_EDP':
      return 'Ditolak EDP';
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
      return 'bg-blue-50 text-blue-700 border-blue-200 shadow-2xs font-semibold'; // Pending SPV
    case 'APPROVED_SPV':
    case 'APPROVED_BY_SPV':
    case 'PUSHED_TO_EDP':
      return 'bg-purple-50 text-purple-700 border-purple-200 shadow-2xs font-semibold'; // Approved SPV / Menunggu EDP
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

// Helper Nama Approver Per Step
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
    if (st === 'COMPLETED') return 'DIINPUT (PUSHED)';
    if (st === 'REJECTED') return 'DITOLAK ADMIN';
    return 'PENDING INPUT';
  }
  if (step === 2) {
    if (st === 'COMPLETED') return 'DISETUJUI SPV';
    if (st === 'REJECTED') return 'DITOLAK SPV';
    if (st === 'PENDING') return 'REVIEW SPV';
    return 'BELUM DIMULAI';
  }
  if (step === 3) {
    if (st === 'COMPLETED') return 'DISETUJUI EDP';
    if (st === 'REJECTED') return 'DITOLAK EDP';
    if (st === 'PENDING') return 'REVIEW EDP';
    return 'BELUM DIMULAI';
  }
  return '';
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
  if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'].includes(st)) {
    return 'bg-emerald-50/50 hover:bg-emerald-100/60 text-slate-900';
  }
  if (['SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(st)) {
    return 'bg-rose-50/50 hover:bg-rose-100/60 text-slate-900';
  }
  // Pending
  return 'bg-white hover:bg-slate-50 text-slate-900';
}
</script>

<template>
  <Head title="Portal SPV Area - Inbox NOO+" />

  <SpvLayout>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Header & Stats Counter Cards -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <div class="flex items-center space-x-3">
            <h1 class="text-xl md:text-[24px] font-semibold text-[#111827] tracking-tight leading-[1.4]">Inbox Submisi SPV Area</h1>
            <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-[#F3E8FF] text-[#7E22CE] border border-[#C084FC]">
              Supervisor Area
            </span>
          </div>
          <p class="text-[14px] leading-[1.5] font-normal text-[#6B7280] mt-1">
            Verifikasi data pendaftaran toko, pengisian rute kunjungan H1-H7 & M1-M4, dan persetujuan ke EDP Principal.
          </p>
        </div>

        <!-- Metric Stat Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#1D4ED8]">Pending Review</span>
            <div class="text-2xl font-bold text-[#2563EB] mt-0.5">{{ stats.pendingSpv }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#7E22CE]">Disetujui SPV</span>
            <div class="text-2xl font-bold text-[#9333EA] mt-0.5">{{ stats.approvedSpv }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#15803D]">EDP Approved</span>
            <div class="text-2xl font-bold text-[#16A34A] mt-0.5">{{ stats.approvedEdp }}</div>
          </div>
          <div class="bg-white p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] text-center">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#B91C1C]">Ditolak</span>
            <div class="text-2xl font-bold text-[#DC2626] mt-0.5">{{ stats.rejected }}</div>
          </div>
        </div>
      </div>

      <!-- Filter Bar SPV Area (Hijau Emerald Accent) -->
      <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] flex flex-col lg:flex-row items-center justify-between gap-4">
        
        <!-- Search Input (Form Input 16px / 400) -->
        <div class="relative w-full lg:w-80">
          <svg class="w-4 h-4 absolute left-3.5 top-3.5 text-[#9CA3AF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari toko, pemilik, salesman, sub-grup..."
            class="w-full pl-10 pr-4 py-2 text-[15px] font-normal rounded-lg bg-white border border-[#D1D5DB] text-[#374151] placeholder-[#9CA3AF] focus:ring-2 focus:ring-[#059669] focus:border-[#047857] transition"
          />
        </div>

        <!-- Filter Status & Sort Dropdowns Side-by-Side -->
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
          <!-- Filter Status Dropdown -->
          <div class="flex items-center space-x-2 w-full sm:w-auto">
            <label class="text-[14px] font-medium text-[#4B5563] whitespace-nowrap">Filter Status:</label>
            <select
              v-model="statusFilter"
              class="w-full sm:w-60 text-[14px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] py-2 px-3 focus:ring-2 focus:ring-[#059669] focus:border-[#047857] shadow-xs cursor-pointer"
            >
              <option value="ALL">Semua Submisi SPV</option>
              <option value="PUSHED_TO_SPV">1. Pending Review SPV</option>
              <option value="APPROVED_BY_SPV">2. Approved SPV (Pending EDP)</option>
              <option value="SPV_REJECTED">3. Ditolak SPV Area</option>
              <option value="EDP_APPROVED">4. Approved EDP Principal</option>
              <option value="EDP_REJECTED">5. Ditolak EDP Principal</option>
            </select>
          </div>

          <!-- Sort Dropdown (Memindahkan Sort dari Header Tabel) -->
          <div class="flex items-center space-x-2 w-full sm:w-auto">
            <label class="text-[14px] font-medium text-[#4B5563] whitespace-nowrap">Urutkan:</label>
            <select
              v-model="sortSelect"
              class="w-full sm:w-52 text-[14px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] py-2 px-3 focus:ring-2 focus:ring-[#059669] focus:border-[#047857] shadow-xs cursor-pointer"
            >
              <option value="submitted_at_desc">Terbaru (Submisi)</option>
              <option value="submitted_at_asc">Terlama (Submisi)</option>
              <option value="nama_noo_asc">Nama Toko (A - Z)</option>
              <option value="nama_noo_desc">Nama Toko (Z - A)</option>
              <option value="nama_pemilik_outlet_asc">Pemilik (A - Z)</option>
              <option value="salesman_name_asc">Salesman (A - Z)</option>
              <option value="status_asc">Status Approval (Asc)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- TABEL SUBMISI SPV PRESISI -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.08)] overflow-hidden">
        <div class="w-full overflow-x-auto">
          <table class="w-full text-left text-[14px] leading-[20px] text-[#374151] table-fixed min-w-[960px]">
            <thead class="bg-[#F3F4F6] text-[14px] font-semibold text-[#1F2937] border-b border-[#E5E7EB] select-none">
              <tr>
                <th class="w-[22%] px-4 py-3.5">Toko & Sub-Grup</th>
                <th class="w-[18%] px-4 py-3.5">Pemilik & No. HP</th>
                <th class="w-[18%] px-4 py-3.5">Salesman & Cabang</th>
                <th class="w-[15%] px-4 py-3.5">Status & Cust Dist.</th>
                <th class="w-[15%] px-4 py-3.5">Rute Kunjungan</th>
                <th class="w-[12%] px-4 py-3.5 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="sortedSubmissions.length === 0">
                <td colspan="6" class="text-center py-12 text-[#9CA3AF]">
                  Tidak ada data submisi toko yang sesuai dengan kriteria filter.
                </td>
              </tr>

              <tr
                v-for="item in sortedSubmissions"
                :key="item.request_id || item.id"
                class="transition border-b"
                :class="getRowStyle(item)"
              >
                <!-- Toko & Sub-Grup -->
                <td class="px-4 py-3.5">
                  <div class="font-semibold text-[#111827] text-[15px] truncate" :title="item.nama_noo">
                    {{ item.nama_noo }}
                  </div>
                  <div class="flex items-center space-x-1 mt-1 flex-wrap gap-1">
                    <span v-if="item.sub_group_region || item.principal" class="px-2 py-0.5 text-[10px] font-bold rounded bg-purple-100 text-purple-800 border border-purple-300">
                      {{ item.sub_group_region || item.principal }}
                    </span>
                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded bg-[#DBEAFE] text-[#1D4ED8] border border-[#93C5FD]">
                      {{ item.type_outlet_code }}
                    </span>
                    <span v-if="item.is_exif_valid !== null || item.exif_depan_distance_m !== null"
                          class="px-1.5 py-0.5 text-[10px] font-bold rounded"
                          :class="item.is_exif_valid !== false && (item.exif_depan_distance_m === null || item.exif_depan_distance_m <= 15) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300'">
                      {{ item.is_exif_valid !== false && (item.exif_depan_distance_m === null || item.exif_depan_distance_m <= 15) ? '✓ EXIF <15m' : '⚠️ EXIF >15m' }}
                    </span>
                  </div>
                </td>

                <!-- Pemilik & No HP -->
                <td class="px-4 py-3.5">
                  <div class="font-medium text-[#1F2937] text-[14px] truncate">
                    {{ item.nama_pemilik_outlet || '-' }}
                  </div>
                  <div class="text-[12px] text-[#6B7280] mt-0.5 font-mono">
                    📞 {{ item.no_hp_noo || item.no_hp || '-' }}
                  </div>
                </td>

                <!-- Salesman & Cabang -->
                <td class="px-4 py-3.5 truncate">
                  <div class="font-medium text-[#1F2937] text-[14px] truncate">{{ item.salesman_name }}</div>
                  <div class="text-[12px] text-[#6B7280] mt-0.5 truncate">
                    {{ item.branch_name }}
                  </div>
                </td>

                <!-- Status & Cust Dist -->
                <td class="px-4 py-3.5">
                  <div class="flex flex-col space-y-1 items-start">
                    <span class="px-2.5 py-0.5 rounded-full text-[12px] font-semibold border" :class="getStatusBadgeStyle(item.status)">
                      {{ formatStatusLabel(item.status) }}
                    </span>
                    <span v-if="item.custcode_distributor" class="font-mono text-[11px] font-semibold text-[#1D4ED8] bg-[#DBEAFE] px-2 py-0.5 rounded border border-[#93C5FD]">
                      {{ item.custcode_distributor }}
                    </span>
                  </div>
                </td>

                <!-- Rute Kunjungan Summary -->
                <td class="px-4 py-3.5">
                  <div class="text-[12px] text-[#374151]">
                    <span class="font-semibold text-[#1F2937]">Hari:</span> {{ getRouteDaysSummary(item) }}
                  </div>
                  <div class="text-[12px] text-[#6B7280] mt-0.5">
                    <span class="font-semibold text-[#1F2937]">Minggu:</span> {{ getRouteWeeksSummary(item) }}
                  </div>
                </td>

                <!-- Action Button -->
                <td class="px-4 py-3.5 text-center">
                  <BaseButton
                    variant="primary"
                    size="sm"
                    class="w-full font-sans"
                    @click="openDetailModal(item)"
                  >
                    Kelola & Rute
                  </BaseButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- MODAL SLIDE-OVER PREVIEW DETAIL & PENGATURAN RUTE SPV (LEVEL 1 Z-INDEX 99990) -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedSubmission" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 md:p-6">
      <div class="bg-white rounded-xl max-w-4xl w-full max-h-[92vh] sm:max-h-[85vh] flex flex-col shadow-[0_15px_40px_rgba(0,0,0,0.18)] border border-[#E5E7EB] overflow-hidden text-[#374151]">
        
        <!-- Header Modal -->
        <div class="px-6 py-4 bg-[#1E3A8A] text-white flex items-center justify-between shrink-0">
          <div>
            <div class="flex items-center space-x-3">
              <h3 class="text-[22px] font-semibold leading-[28px] text-white">{{ selectedSubmission.nama_noo }}</h3>
              <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-white/20 text-white border border-white/30">
                {{ selectedSubmission.type_outlet_code }} - {{ selectedSubmission.type_outlet_desc }}
              </span>
            </div>
            <p class="text-xs text-blue-200 mt-0.5">Request ID: {{ selectedSubmission.request_id }} | Branch: {{ selectedSubmission.branch_name }}</p>
          </div>
          <button @click="showDetailModal = false" class="text-blue-200 hover:text-white text-xl font-bold p-1 hover:bg-blue-800 rounded-lg">
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
                {{ formatDate(selectedSubmission.submitted_at || selectedSubmission.created_at) }}
              </p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Kode Cust Distributor</p>
              <p class="text-[14px] font-mono font-semibold text-[#1D4ED8] mt-0.5">
                {{ selectedSubmission.custcode_distributor || 'Belum diisi Admin' }}
              </p>
            </div>

            <div>
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">Status Workflow</p>
              <div class="flex items-center space-x-2 mt-0.5">
                <span class="px-2.5 py-0.5 rounded-full text-[12px] font-semibold border" :class="getStatusBadgeStyle(selectedSubmission.status)">
                  {{ formatStatusLabel(selectedSubmission.status) }}
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
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">GPS Koordinat & Akurasi</p>
              <p class="text-[14px] font-mono text-[#374151] mt-0.5">Lat: {{ selectedSubmission.la }}, Lg: {{ selectedSubmission.lg }}</p>
              <p class="text-[12px] text-[#15803D] font-semibold mt-0.5">Akurasi GPS: {{ selectedSubmission.accuracy_m }} meter</p>
              <a
                :href="`https://www.google.com/maps?q=${selectedSubmission.la},${selectedSubmission.lg}`"
                target="_blank"
                class="inline-flex items-center text-[13px] font-semibold text-[#2563EB] hover:underline mt-1"
              >
                Lihat Lokasi Google Maps →
              </a>
            </div>
          </div>

          <!-- SECTION 4: TRACK RECORD PERSETUJUAN (TIMELINE PROGRESS TRACKER) -->
          <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-[#F3F4F6] pb-3 flex-wrap gap-2">
              <h4 class="text-[14px] font-semibold text-[#111827] uppercase tracking-wider flex items-center gap-2">
                <span>TRACK RECORD PERSETUJUAN (PROGRESS TRACKER)</span>
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

          <!-- SECTION PREVIEW MAPS (PETA LOKASI TOKO INDEPENDEN SEPERTI INBOX PRINCIPAL) -->
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

          <!-- BERKAS FOTO TOKO (3 KOLEKSI HD) - SEKARANG BERADA DI ATAS SECTION RUTE -->
          <div class="bg-white p-4 rounded-xl border border-[#E5E7EB] shadow-sm space-y-4">
            <h4 class="text-[13px] font-semibold text-[#111827] uppercase tracking-wider border-b border-[#F3F4F6] pb-2">
              📸 BERKAS FOTO TOKO & KTP (HD 1:1)
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Foto Depan -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">1. FOTO DEPAN</span>
                <div v-if="selectedSubmission.photo_depan_url" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = selectedSubmission.photo_depan_url">
                  <img
                    :src="selectedSubmission.photo_depan_url"
                    alt="Foto Depan"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Security Overlay -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-semibold text-xs z-20">
                    🔍 Perbesar Foto
                  </div>
                </div>
                <span v-else class="text-xs text-[#9CA3AF] italic py-12 block">Foto Depan Belum Ada</span>
              </div>

              <!-- Foto Dalam -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">2. FOTO DALAM</span>
                <div v-if="selectedSubmission.photo_dalam_url" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = selectedSubmission.photo_dalam_url">
                  <img
                    :src="selectedSubmission.photo_dalam_url"
                    alt="Foto Dalam"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Security Overlay -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-semibold text-xs z-20">
                    🔍 Perbesar Foto
                  </div>
                </div>
                <span v-else class="text-xs text-[#9CA3AF] italic py-12 block">Foto Dalam Belum Ada</span>
              </div>

              <!-- Foto KTP -->
              <div class="bg-white p-2.5 rounded-xl border border-[#E5E7EB] text-center relative select-none shadow-sm">
                <span class="text-[12px] font-semibold text-[#4B5563] block mb-2">3. FOTO KTP</span>
                <div v-if="selectedSubmission.photo_ktp_url" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = selectedSubmission.photo_ktp_url">
                  <img
                    :src="selectedSubmission.photo_ktp_url"
                    alt="Foto KTP"
                    class="w-full h-44 object-cover rounded-lg shadow-md pointer-events-none select-none"
                    draggable="false"
                    oncontextmenu="return false;"
                  />
                  <!-- Watermark Security Overlay -->
                  <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
                    <p class="text-[9px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                      CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
                    </p>
                  </div>
                  <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-semibold text-xs z-20">
                    🔍 Perbesar Foto
                  </div>
                </div>
                <span v-else class="text-xs text-[#9CA3AF] italic py-12 block">Foto KTP Belum Ada</span>
              </div>
            </div>
          </div>

          <!-- FORM REGISTRASI RUTE KUNJUNGAN SPV (H1-H7 & M1-M4) - DILETAKKAN DIBAWAH FOTO -->
          <div class="bg-white p-5 rounded-xl border border-[#93C5FD] shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
              <h4 class="text-[14px] font-semibold text-[#1D4ED8] uppercase tracking-wider flex items-center gap-2">
                <span>📅 PENGATURAN RUTE KUNJUNGAN SALESMAN (SPV AREA)</span>
              </h4>
              <span v-if="isReadOnly" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#E2E8F0] text-[#475569] border border-[#CBD5E1]">
                🔒 TERKUNCI (READ-ONLY)
              </span>
            </div>

            <!-- Button-Only Hari Kunjungan (H1-H7) - Single Select with Auto Disable -->
            <div>
              <label class="block text-[14px] font-medium text-[#4B5563] mb-2">
                Jadwal Hari Kunjungan (Pilih 1 Hari):
              </label>
              <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                <button
                  type="button"
                  @click="selectDay('h1')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h1')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h1 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h1')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H1</span>
                  <span class="text-[10px] opacity-80">(Senin)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h2')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h2')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h2 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h2')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H2</span>
                  <span class="text-[10px] opacity-80">(Selasa)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h3')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h3')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h3 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h3')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H3</span>
                  <span class="text-[10px] opacity-80">(Rabu)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h4')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h4')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h4 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h4')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H4</span>
                  <span class="text-[10px] opacity-80">(Kamis)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h5')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h5')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h5 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h5')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H5</span>
                  <span class="text-[10px] opacity-80">(Jumat)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h6')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h6')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h6 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h6')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H6</span>
                  <span class="text-[10px] opacity-80">(Sabtu)</span>
                </button>

                <button
                  type="button"
                  @click="selectDay('h7')"
                  :disabled="isReadOnly || (selectedDayKey && selectedDayKey !== 'h7')"
                  :class="[
                    'py-2 px-3 rounded-lg text-xs font-semibold border transition-all flex flex-col items-center justify-center space-y-0.5',
                    approveForm.h7 === 'Y'
                      ? 'bg-[#2563EB] text-white border-[#1D4ED8] shadow-sm font-bold ring-2 ring-blue-300'
                      : isReadOnly || (selectedDayKey && selectedDayKey !== 'h7')
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-40 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-blue-50 hover:border-blue-300'
                  ]"
                >
                  <span>H7</span>
                  <span class="text-[10px] opacity-80">(Minggu)</span>
                </button>
              </div>
            </div>

            <!-- Choice of Visit Pattern (F2 vs F4) -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <label class="block text-[14px] font-semibold text-[#1F2937]">
                Periode Frekuensi Kunjungan Salesman (JKS): <span class="text-rose-500 font-bold">*</span>
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Option F2 -->
                <button
                  type="button"
                  @click="selectVisitPattern('F2')"
                  :disabled="isReadOnly"
                  :class="[
                    'p-3 rounded-xl border text-left transition-all flex items-center justify-between cursor-pointer',
                    selectedVisitPattern === 'F2'
                      ? 'bg-purple-50 border-purple-500 ring-2 ring-purple-400 text-purple-950 font-bold shadow-xs'
                      : isReadOnly
                      ? 'bg-slate-100 border-slate-200 text-slate-400 opacity-60 cursor-not-allowed'
                      : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-purple-300 hover:bg-purple-50/50'
                  ]"
                >
                  <div class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                    <span class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center shrink-0" :class="selectedVisitPattern === 'F2' ? 'border-purple-600 bg-purple-600' : 'border-slate-400'">
                      <span v-if="selectedVisitPattern === 'F2'" class="w-1.5 h-1.5 bg-white rounded-full"></span>
                    </span>
                    <span>PERIODE F2 (2 MINGGU SEKALI)</span>
                  </div>
                </button>

                <!-- Option F4 -->
                <button
                  type="button"
                  @click="selectVisitPattern('F4')"
                  :disabled="isReadOnly"
                  :class="[
                    'p-3 rounded-xl border text-left transition-all flex items-center justify-between cursor-pointer',
                    selectedVisitPattern === 'F4'
                      ? 'bg-purple-50 border-purple-500 ring-2 ring-purple-400 text-purple-950 font-bold shadow-xs'
                      : isReadOnly
                      ? 'bg-slate-100 border-slate-200 text-slate-400 opacity-60 cursor-not-allowed'
                      : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-purple-300 hover:bg-purple-50/50'
                  ]"
                >
                  <div class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                    <span class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center shrink-0" :class="selectedVisitPattern === 'F4' ? 'border-purple-600 bg-purple-600' : 'border-slate-400'">
                      <span v-if="selectedVisitPattern === 'F4'" class="w-1.5 h-1.5 bg-white rounded-full"></span>
                    </span>
                    <span>PERIODE F4 (SETIAP MINGGU)</span>
                  </div>
                </button>
              </div>
            </div>

            <!-- Button-Only Pola Minggu (M1-M4) dengan Auto Lock/Disable -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="block text-[14px] font-medium text-[#4B5563]">
                  Jadwal Minggu Kunjungan (M1 - M4):
                </label>
                <span v-if="!selectedVisitPattern" class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                  ⚠️ Pilih Periode F2/F4 dahulu
                </span>
                <span v-else-if="selectedVisitPattern === 'F2'" class="text-[11px] font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200">
                  <template v-if="f2SelectedType === 'GANJIL'">Pilihan anda Minggu Ganjil</template>
                  <template v-else-if="f2SelectedType === 'GENAP'">Pilihan anda Minggu Genap</template>
                  <template v-else>Klik M1/M3 (untuk minggu ganjil) atau M2/M4 (untuk minggu genap)</template>
                </span>
                <span v-else-if="selectedVisitPattern === 'F4'" class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  ✓ Semua Minggu (M1, M2, M3, M4) Terpilih
                </span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button
                  type="button"
                  @click="handleWeekClick('m1')"
                  :disabled="isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'"
                  :class="[
                    'py-2.5 px-3 rounded-lg text-xs font-semibold border transition-all flex items-center justify-center space-x-1.5',
                    approveForm.m1 === 'Y'
                      ? 'bg-[#7C3AED] text-white border-[#6D28D9] shadow-sm font-bold ring-2 ring-purple-300'
                      : isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-50 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-purple-50 hover:border-purple-300'
                  ]"
                >
                  <span>M1</span>
                  <span class="text-[11px] opacity-80">(Minggu 1)</span>
                </button>

                <button
                  type="button"
                  @click="handleWeekClick('m2')"
                  :disabled="isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'"
                  :class="[
                    'py-2.5 px-3 rounded-lg text-xs font-semibold border transition-all flex items-center justify-center space-x-1.5',
                    approveForm.m2 === 'Y'
                      ? 'bg-[#7C3AED] text-white border-[#6D28D9] shadow-sm font-bold ring-2 ring-purple-300'
                      : isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-50 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-purple-50 hover:border-purple-300'
                  ]"
                >
                  <span>M2</span>
                  <span class="text-[11px] opacity-80">(Minggu 2)</span>
                </button>

                <button
                  type="button"
                  @click="handleWeekClick('m3')"
                  :disabled="isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'"
                  :class="[
                    'py-2.5 px-3 rounded-lg text-xs font-semibold border transition-all flex items-center justify-center space-x-1.5',
                    approveForm.m3 === 'Y'
                      ? 'bg-[#7C3AED] text-white border-[#6D28D9] shadow-sm font-bold ring-2 ring-purple-300'
                      : isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-50 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-purple-50 hover:border-purple-300'
                  ]"
                >
                  <span>M3</span>
                  <span class="text-[11px] opacity-80">(Minggu 3)</span>
                </button>

                <button
                  type="button"
                  @click="handleWeekClick('m4')"
                  :disabled="isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'"
                  :class="[
                    'py-2.5 px-3 rounded-lg text-xs font-semibold border transition-all flex items-center justify-center space-x-1.5',
                    approveForm.m4 === 'Y'
                      ? 'bg-[#7C3AED] text-white border-[#6D28D9] shadow-sm font-bold ring-2 ring-purple-300'
                      : isReadOnly || !selectedVisitPattern || selectedVisitPattern === 'F4'
                      ? 'bg-[#F3F4F6] text-[#9CA3AF] border-[#E5E7EB] opacity-50 cursor-not-allowed'
                      : 'bg-[#F8FAFC] text-[#1E293B] border-[#CBD5E1] hover:bg-purple-50 hover:border-purple-300'
                  ]"
                >
                  <span>M4</span>
                  <span class="text-[11px] opacity-80">(Minggu 4)</span>
                </button>
              </div>
            </div>

            <!-- Catatan SPV Area -->
            <div>
              <label class="block text-[14px] font-medium text-[#4B5563] mb-1">
                Catatan Supervisor Area (Opsional)
              </label>
              <textarea
                v-model="approveForm.spv_notes"
                :readonly="isReadOnly"
                rows="2"
                placeholder="Catatan persetujuan untuk EDP Principal..."
                :class="[
                  'w-full px-4 py-2 text-[15px] font-normal rounded-lg bg-white border text-[#374151] focus:ring-2 focus:ring-[#3B82F6]',
                  isReadOnly ? 'bg-[#F8FAFC] border-[#E2E8F0] text-[#64748B] cursor-not-allowed' : 'border-[#D1D5DB]'
                ]"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Footer Action Modal -->
        <div class="px-6 py-4 bg-[#F1F5F9] border-t border-[#E5E7EB] flex items-center justify-between">
          <button
            @click="showDetailModal = false"
            class="px-4 py-2 text-[14px] font-semibold text-[#4B5563] bg-white border border-[#D1D5DB] rounded-lg hover:bg-gray-50 transition"
          >
            Tutup
          </button>

          <div v-if="selectedSubmission.status === 'PUSHED_TO_SPV'" class="flex items-center space-x-3">
            <button
              @click="openRejectModal(selectedSubmission)"
              class="px-4 py-2 text-[14px] font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] rounded-lg transition shadow-sm"
            >
              Rejected
            </button>

            <button
              @click="submitApproveSpv"
              :disabled="approveForm.processing || !isRouteValid"
              :title="!isRouteValid ? 'Pilih minimal 1 Hari Kunjungan (H1-H7) dan 1 Pola Minggu (M1-M4)' : ''"
              class="px-5 py-2 text-[14px] font-semibold text-white bg-[#10B981] hover:bg-[#059669] active:bg-[#047857] rounded-lg transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
            >
              <svg v-if="approveForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ approveForm.processing ? 'Memproses...' : 'Approved' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

    <!-- MODAL CONFIRMATION REJECT SPV (LEVEL 2 Z-INDEX 999999) -->
    <Teleport to="body">
      <div v-if="showRejectModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl border border-[#E5E7EB] space-y-4 my-auto">
          <h3 class="text-lg font-bold text-[#111827]">🚫 Tolak Submisi Toko (SPV Area)</h3>
          <p class="text-xs text-[#6B7280]">
            Silakan masukkan alasan penolakan toko <strong class="text-[#111827]">{{ selectedSubmission?.nama_noo }}</strong>.
          </p>

          <div>
            <label class="block text-xs font-semibold text-[#374151] mb-1">Alasan Penolakan SPV <span class="text-[#DC2626]">*</span></label>
            <textarea
              v-model="rejectForm.reject_reason"
              rows="3"
              placeholder="Contoh: Rute tidak efektif, atau lokasi toko berada di luar cakupan distribusi..."
              class="w-full px-3 py-2 text-sm rounded-lg border border-[#D1D5DB] focus:ring-2 focus:ring-[#DC2626]"
            ></textarea>
          </div>

          <div class="flex items-center justify-end space-x-3 pt-2">
            <button
              :disabled="rejectForm.processing"
              @click="showRejectModal = false"
              class="px-4 py-2 text-xs font-semibold text-[#4B5563] bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50"
            >
              Batal
            </button>
            <button
              @click="submitRejectSpv"
              :disabled="rejectForm.processing || !rejectForm.reject_reason.trim()"
              class="px-4 py-2 text-xs font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
            >
              <svg v-if="rejectForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ rejectForm.processing ? 'Memproses...' : 'Konfirmasi Tolak' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MODAL ZOOM FOTO FULLSCREEN (LEVEL 3 Z-INDEX 9999999) -->
    <Teleport to="body">
      <div v-if="activePhotoZoom" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[9999999] bg-black/80 flex items-center justify-center p-4 select-none" @click="activePhotoZoom = null">
        <div class="relative max-w-3xl max-h-[90vh] overflow-hidden rounded-lg" @click.stop>
          <img
            :src="activePhotoZoom"
            alt="Zoom Foto"
            class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl pointer-events-none select-none"
            draggable="false"
            oncontextmenu="return false;"
          />
          <!-- Watermark Security Overlay -->
          <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none">
            <p class="text-[14px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
              CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
            </p>
          </div>
          <button
            @click="activePhotoZoom = null"
            class="absolute top-2 right-2 bg-white/40 text-white rounded-full p-2 text-sm hover:bg-white/60 z-20"
          >
            ✕ Close
          </button>
        </div>
      </div>
    </Teleport>
  </SpvLayout>
</template>
