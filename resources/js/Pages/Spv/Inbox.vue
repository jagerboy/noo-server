<script setup lang="js">
/**
 * UI Portal Web SPV Area (Vue 3 Composition API).
 * Light Mode Theme dengan Inter Font Family & Tokoh Desain System.
 * Menampilkan daftar submisi toko dari Admin Distributor, pengisian rute H1-H7 & M1-M4,
 * persetujuan SPV (Approve & Pushed ke EDP), dan penolakan SPV.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import SpvLayout from '@/Layouts/SpvLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import ProgressTrackingModal from './Components/ProgressTrackingModal.vue';

const props = defineProps({
  submissions: {
    type: [Array, Object],
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => null,
  },
  myBranches: {
    type: Array,
    default: () => [],
  },
  subGroups: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

// State Pencarian & Multi-Filter
const searchQuery = ref(props.filters?.search || '');
const branchFilter = ref(props.filters?.branch_id || 'ALL');
const spvStatusFilter = ref(props.filters?.spv_status || 'ALL'); // 'ALL' | 'PENDING' | 'PROCESSED'
const edpStatusFilter = ref(props.filters?.edp_status || 'ALL'); // 'ALL' | 'PENDING' | 'APPROVED' | 'REJECTED'
const sortSelect = ref(props.filters?.sort || 'submitted_at_desc');

// State Modal Detail & Action
const showDetailModal = ref(false);
const showRejectModal = ref(false);
const showProgressModal = ref(false);
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

const rawSubmissions = computed(() => {
  if (Array.isArray(props.submissions)) return props.submissions;
  if (props.submissions && Array.isArray(props.submissions.data)) return props.submissions.data;
  return [];
});

function onFilterChange() {
  triggerServerFilter(true);
}

let searchTimer = null;
function onSearchInput() {
  triggerServerFilter(false);
}

function triggerServerFilter(immediate = true) {
  if (searchTimer) clearTimeout(searchTimer);

  const execute = () => {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (branchFilter.value && branchFilter.value !== 'ALL') params.branch_id = branchFilter.value;
    if (spvStatusFilter.value && spvStatusFilter.value !== 'ALL') params.spv_status = spvStatusFilter.value;
    if (edpStatusFilter.value && edpStatusFilter.value !== 'ALL') params.edp_status = edpStatusFilter.value;
    if (sortSelect.value && sortSelect.value !== 'default') params.sort = sortSelect.value;
    params.page = 1;

    router.get(route('spv.inbox'), params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  if (immediate) {
    execute();
  } else {
    searchTimer = setTimeout(execute, 400);
  }
}

const hasActiveFilter = computed(() => {
  return (
    Boolean(searchQuery.value) ||
    branchFilter.value !== 'ALL' ||
    spvStatusFilter.value !== 'ALL' ||
    edpStatusFilter.value !== 'ALL' ||
    (sortSelect.value !== 'submitted_at_desc' && sortSelect.value !== 'default')
  );
});

function resetAllFilters() {
  searchQuery.value = '';
  branchFilter.value = 'ALL';
  spvStatusFilter.value = 'ALL';
  edpStatusFilter.value = 'ALL';
  sortSelect.value = 'submitted_at_desc';
  triggerServerFilter(true);
}

function filterByMetric(statType) {
  if (statType === 'pendingSpv') {
    if (spvStatusFilter.value === 'PENDING') {
      spvStatusFilter.value = 'ALL';
    } else {
      spvStatusFilter.value = 'PENDING';
      edpStatusFilter.value = 'ALL';
    }
  } else if (statType === 'approvedSpv') {
    if (spvStatusFilter.value === 'PROCESSED' && edpStatusFilter.value === 'PENDING') {
      spvStatusFilter.value = 'ALL';
      edpStatusFilter.value = 'ALL';
    } else {
      spvStatusFilter.value = 'PROCESSED';
      edpStatusFilter.value = 'PENDING';
    }
  } else if (statType === 'approvedEdp') {
    if (spvStatusFilter.value === 'PROCESSED' && edpStatusFilter.value === 'APPROVED') {
      spvStatusFilter.value = 'ALL';
      edpStatusFilter.value = 'ALL';
    } else {
      spvStatusFilter.value = 'PROCESSED';
      edpStatusFilter.value = 'APPROVED';
    }
  } else if (statType === 'rejected') {
    if (edpStatusFilter.value === 'REJECTED') {
      spvStatusFilter.value = 'ALL';
      edpStatusFilter.value = 'ALL';
    } else {
      spvStatusFilter.value = 'ALL';
      edpStatusFilter.value = 'REJECTED';
    }
  }
  triggerServerFilter(true);
}

function hasRoute(item) {
  if (!item) return false;
  const days = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'];
  const weeks = ['m1', 'm2', 'm3', 'm4'];
  return days.some((d) => item[d] === 'Y' || item[d] === 'YES') || weeks.some((w) => item[w] === 'Y' || item[w] === 'YES');
}

// Filter Submisi Data Toko (Client-side fast responsiveness)
const filteredSubmissions = computed(() => {
  let list = rawSubmissions.value;

  // Filter Cabang Binaan SPV
  if (branchFilter.value && branchFilter.value !== 'ALL') {
    const targetBranch = String(branchFilter.value).toLowerCase().trim();
    list = list.filter((item) => {
      const bId = String(item.branch_id || '').toLowerCase().trim();
      const bName = String(item.branch_name || '').toLowerCase().trim();
      return bId === targetBranch || bName === targetBranch || bId.includes(targetBranch) || bName.includes(targetBranch);
    });
  }

  // Filter Status Review Area (SPV)
  if (spvStatusFilter.value === 'PENDING') {
    list = list.filter((item) => ['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(item.status));
  } else if (spvStatusFilter.value === 'PROCESSED') {
    list = list.filter((item) => [
      'APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP',
      'APPROVED_EDP', 'EDP_APPROVED',
      'REJECTED_SPV', 'SPV_REJECTED',
      'REJECTED_EDP', 'EDP_REJECTED'
    ].includes(item.status));
  }

  // Filter EDP Status
  if (edpStatusFilter.value === 'PENDING') {
    list = list.filter((item) => ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(item.status));
  } else if (edpStatusFilter.value === 'APPROVED') {
    list = list.filter((item) => ['APPROVED_EDP', 'EDP_APPROVED'].includes(item.status));
  } else if (edpStatusFilter.value === 'REJECTED') {
    list = list.filter((item) => ['REJECTED_EDP', 'EDP_REJECTED'].includes(item.status));
  }

  if (!searchQuery.value) return list;
  const q = searchQuery.value.toLowerCase().trim();

  return list.filter((item) => {
    return (
      (item.nama_noo || '').toLowerCase().includes(q) ||
      (item.nama_pemilik_outlet || '').toLowerCase().includes(q) ||
      (item.no_hp_noo || '').toLowerCase().includes(q) ||
      (item.no_hp || '').toLowerCase().includes(q) ||
      (item.salesman_name || '').toLowerCase().includes(q) ||
      (item.salesman_code || '').toLowerCase().includes(q) ||
      (item.branch_name || '').toLowerCase().includes(q) ||
      (item.branch_id || '').toLowerCase().includes(q) ||
      (item.alamat_noo || '').toLowerCase().includes(q) ||
      (item.kec_noo || '').toLowerCase().includes(q) ||
      (item.kab_kota_noo || '').toLowerCase().includes(q) ||
      (item.custcode_distributor || '').toLowerCase().includes(q) ||
      (item.code_noo_principal || '').toLowerCase().includes(q)
    );
  });
});

const sortedSubmissions = computed(() => {
  const list = [...filteredSubmissions.value];
  if (sortSelect.value === 'submitted_at_asc') {
    return list.sort((a, b) => {
      const aTime = a.pushed_to_spv_at ? new Date(a.pushed_to_spv_at).getTime() : (a.submitted_at ? new Date(a.submitted_at).getTime() : 0);
      const bTime = b.pushed_to_spv_at ? new Date(b.pushed_to_spv_at).getTime() : (b.submitted_at ? new Date(b.submitted_at).getTime() : 0);
      return aTime - bTime;
    });
  }
  if (sortSelect.value === 'nama_noo_asc') {
    return list.sort((a, b) => String(a.nama_noo || '').localeCompare(String(b.nama_noo || '')));
  }
  if (sortSelect.value === 'nama_noo_desc') {
    return list.sort((a, b) => String(b.nama_noo || '').localeCompare(String(a.nama_noo || '')));
  }
  if (sortSelect.value === 'salesman_name_asc') {
    return list.sort((a, b) => String(a.salesman_name || '').localeCompare(String(b.salesman_name || '')));
  }

  // default: 'submitted_at_desc' (Prioritas Pending SPV lalu waktu terbaru)
  return list.sort((a, b) => {
    const aPending = a.status === 'PUSHED_TO_SPV' ? 0 : 1;
    const bPending = b.status === 'PUSHED_TO_SPV' ? 0 : 1;
    if (aPending !== bPending) return aPending - bPending;
    const aTime = a.pushed_to_spv_at ? new Date(a.pushed_to_spv_at).getTime() : (a.submitted_at ? new Date(a.submitted_at).getTime() : 0);
    const bTime = b.pushed_to_spv_at ? new Date(b.pushed_to_spv_at).getTime() : (b.submitted_at ? new Date(b.submitted_at).getTime() : 0);
    return bTime - aTime;
  });
});

// Stats Metric Counter
const stats = computed(() => {
  if (props.stats) return props.stats;
  const list = rawSubmissions.value;
  const total = list.length;
  const pendingSpv = list.filter((i) => ['PUSHED_TO_SPV', 'ADMIN_APPROVED'].includes(i.status)).length;
  const approvedSpv = list.filter((i) => ['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(i.status)).length;
  const approvedEdp = list.filter((i) => ['APPROVED_EDP', 'EDP_APPROVED'].includes(i.status)).length;
  const rejected = list.filter((i) => ['REJECTED_SPV', 'SPV_REJECTED', 'REJECTED_EDP', 'EDP_REJECTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(i.status)).length;

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

// Helper Resolusi URL Foto (/storage/ Direct Route)
function getPhotoUrl(pathOrUrl) {
  if (!pathOrUrl) return null;
  if (pathOrUrl.startsWith('http://') || pathOrUrl.startsWith('https://') || pathOrUrl.startsWith('blob:') || pathOrUrl.startsWith('data:')) return pathOrUrl;
  let cleanPath = pathOrUrl.replace(/^\/+/, '');
  if (cleanPath.startsWith('public/')) cleanPath = cleanPath.substring(7);
  if (cleanPath.startsWith('storage/')) cleanPath = cleanPath.substring(8);
  if (cleanPath.startsWith('media-photo/')) cleanPath = cleanPath.substring(12);
  return `/storage/${cleanPath}`;
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

function closeDetailModal() {
  showDetailModal.value = false;
  selectedSubmission.value = null;
}

// Buka Modal Reject SPV
function openRejectModal(item) {
  selectedSubmission.value = item;
  rejectForm.request_id = item.request_id;
  rejectForm.reject_reason = '';
  showRejectModal.value = true;
}

// Buka Pengisian Rute dari Modal Progress Tracking
function handleSelectFromTracking(item) {
  showProgressModal.value = false;
  openDetailModal(item);
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

// Helper Format Label Status (Informatif, rapi, bebas dari redundansi SPV/Area)
function formatStatusLabel(status) {
  if (!status) return '-';
  switch (status) {
    case 'SE_SUBMITTED':
    case 'SUBMITTED':
      return 'Pending Admin';
    case 'PUSHED_TO_SPV':
    case 'ADMIN_APPROVED':
      return 'Menunggu Review';
    case 'APPROVED_SPV':
    case 'APPROVED_BY_SPV':
    case 'PUSHED_TO_EDP':
      return 'Disetujui Area';
    case 'APPROVED_EDP':
    case 'EDP_APPROVED':
      return 'Approved Principal';
    case 'ADMIN_REJECTED':
    case 'REJECTED_ADMIN':
      return 'Ditolak Admin';
    case 'SPV_REJECTED':
    case 'REJECTED_SPV':
      return 'Ditolak Area';
    case 'EDP_REJECTED':
    case 'REJECTED_EDP':
      return 'Ditolak Principal';
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
  const st = item?.status || '';
  if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'].includes(st)) {
    return 'bg-emerald-50/40 hover:bg-emerald-100/50 text-slate-900';
  }
  if (['SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(st)) {
    return 'bg-rose-50/40 hover:bg-rose-100/50 text-slate-900';
  }
  if (st === 'PUSHED_TO_SPV') {
    return 'bg-amber-50/30 hover:bg-amber-100/40 text-slate-900';
  }
  // Pending lainnya
  return 'bg-white hover:bg-slate-50 text-slate-900';
}

function getCardAccentClass(item) {
  const st = item?.status || '';
  if (st === 'PUSHED_TO_SPV') return 'border-l-4 border-l-amber-500 bg-amber-50/15';
  if (['APPROVED_SPV', 'APPROVED_BY_SPV', 'PUSHED_TO_EDP'].includes(st)) return 'border-l-4 border-l-purple-500';
  if (['APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'].includes(st)) return 'border-l-4 border-l-emerald-500';
  if (['SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP', 'ADMIN_REJECTED', 'REJECTED_ADMIN'].includes(st)) return 'border-l-4 border-l-rose-500';
  return 'border-l-4 border-l-slate-300';
}
</script>

<template>
  <Head title="Portal SPV Area - Inbox NOO+" />

  <SpvLayout>
    <div class="mx-auto max-w-7xl px-3 sm:px-4 md:px-6 lg:px-8 space-y-4 md:space-y-6">

      <!-- Header & Stats Counter Cards: Responsif Desktop & Tablet -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 md:gap-6">
        <div>
          <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap">
            <h1 class="text-lg sm:text-xl md:text-[22px] font-bold text-[#111827] tracking-tight whitespace-nowrap">Inbox Submisi Toko</h1>
            
            <!-- Tombol Modal Progress Tracking NOO (Sejajar dengan Judul) -->
            <button
              type="button"
              @click="showProgressModal = true"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 active:bg-indigo-200 border border-indigo-200 transition cursor-pointer shadow-2xs shrink-0 whitespace-nowrap"
              title="Buka Pelacakan Progres NOO Cabang Binaan"
            >
              <svg class="w-3.5 h-3.5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <span>Progress Tracking NOO</span>
            </button>

            <span class="px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-[#F3E8FF] text-[#7E22CE] border border-[#C084FC] shrink-0 whitespace-nowrap">
              Verifikasi & Approval
            </span>
          </div>
          <p class="text-[12.5px] md:text-[14px] leading-[1.5] font-normal text-[#6B7280] mt-1">
            Verifikasi pendaftaran outlet baru, alokasi jadwal kunjungan sales (H1-H7 & M1-M4), dan persetujuan ke Principal.
          </p>
        </div>

        <!-- Metric Stat Badges (Klik untuk filter cepat) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 md:gap-3 w-full md:w-auto shrink-0">
          <div
            @click="filterByMetric('pendingSpv')"
            class="p-2.5 sm:p-3 md:p-3.5 rounded-xl border shadow-[0_1px_3px_rgba(0,0,0,0.06)] text-center flex flex-col justify-between h-full min-w-[110px] md:min-w-[125px] cursor-pointer transition select-none hover:shadow-sm"
            :class="spvStatusFilter === 'PENDING' ? 'bg-blue-50/50 border-[#2563EB] ring-2 ring-[#2563EB]/20' : 'bg-white border-[#E5E7EB] hover:border-blue-300'"
            title="Klik untuk filter data yang belum diproses review"
          >
            <div class="min-h-[26px] md:min-h-[32px] flex items-center justify-center">
              <span class="text-[10px] md:text-[11px] font-semibold uppercase tracking-wider text-[#1D4ED8]">Pending Review</span>
            </div>
            <div class="text-xl md:text-2xl font-bold text-[#2563EB] mt-0.5 md:mt-1">{{ stats.pendingSpv }}</div>
          </div>
          <div
            @click="filterByMetric('approvedSpv')"
            class="p-2.5 sm:p-3 md:p-3.5 rounded-xl border shadow-[0_1px_3px_rgba(0,0,0,0.06)] text-center flex flex-col justify-between h-full min-w-[110px] md:min-w-[125px] cursor-pointer transition select-none hover:shadow-sm"
            :class="spvStatusFilter === 'PROCESSED' && edpStatusFilter === 'PENDING' ? 'bg-purple-50/50 border-[#9333EA] ring-2 ring-[#9333EA]/20' : 'bg-white border-[#E5E7EB] hover:border-purple-300'"
            title="Klik untuk filter data yang disetujui area & menunggu Principal"
          >
            <div class="min-h-[26px] md:min-h-[32px] flex items-center justify-center">
              <span class="text-[10px] md:text-[11px] font-semibold uppercase tracking-wider text-[#7E22CE]">Disetujui Area</span>
            </div>
            <div class="text-xl md:text-2xl font-bold text-[#9333EA] mt-0.5 md:mt-1">{{ stats.approvedSpv }}</div>
          </div>
          <div
            @click="filterByMetric('approvedEdp')"
            class="p-2.5 sm:p-3 md:p-3.5 rounded-xl border shadow-[0_1px_3px_rgba(0,0,0,0.06)] text-center flex flex-col justify-between h-full min-w-[110px] md:min-w-[125px] cursor-pointer transition select-none hover:shadow-sm"
            :class="spvStatusFilter === 'PROCESSED' && edpStatusFilter === 'APPROVED' ? 'bg-emerald-50/50 border-[#16A34A] ring-2 ring-[#16A34A]/20' : 'bg-white border-[#E5E7EB] hover:border-emerald-300'"
            title="Klik untuk filter data yang telah disetujui Principal"
          >
            <div class="min-h-[26px] md:min-h-[32px] flex items-center justify-center">
              <span class="text-[10px] md:text-[11px] font-semibold uppercase tracking-wider text-[#15803D]">Approved Principal</span>
            </div>
            <div class="text-xl md:text-2xl font-bold text-[#16A34A] mt-0.5 md:mt-1">{{ stats.approvedEdp }}</div>
          </div>
          <div
            @click="filterByMetric('rejected')"
            class="p-2.5 sm:p-3 md:p-3.5 rounded-xl border shadow-[0_1px_3px_rgba(0,0,0,0.06)] text-center flex flex-col justify-between h-full min-w-[110px] md:min-w-[125px] cursor-pointer transition select-none hover:shadow-sm"
            :class="edpStatusFilter === 'REJECTED' ? 'bg-rose-50/50 border-[#DC2626] ring-2 ring-[#DC2626]/20' : 'bg-white border-[#E5E7EB] hover:border-rose-300'"
            title="Klik untuk filter data yang ditolak"
          >
            <div class="min-h-[26px] md:min-h-[32px] flex items-center justify-center">
              <span class="text-[10px] md:text-[11px] font-semibold uppercase tracking-wider text-[#B91C1C]">Ditolak</span>
            </div>
            <div class="text-xl md:text-2xl font-bold text-[#DC2626] mt-0.5 md:mt-1">{{ stats.rejected }}</div>
          </div>
        </div>
      </div>

      <!-- Toolbar Pencarian & Multi-Filter Satu Baris Sejajar -->
      <div class="relative bg-white p-3 sm:p-3.5 rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
        <div class="flex items-center gap-2 sm:gap-2.5 w-full flex-nowrap overflow-x-auto pb-1 sm:pb-0">
          
          <!-- Search Input (Compact, fleksibel) -->
          <div class="relative flex-1 min-w-[160px] sm:min-w-[200px] max-w-[260px] shrink-0">
            <svg class="w-4 h-4 absolute left-3 top-2.5 text-[#9CA3AF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              v-model="searchQuery"
              @input="onSearchInput"
              type="text"
              placeholder="Cari toko, sales, cabang..."
              class="w-full pl-9 pr-3 py-1.5 text-[12px] rounded-lg bg-white border border-[#D1D5DB] text-[#374151] placeholder-[#9CA3AF] focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition"
            />
          </div>

          <!-- 1. Dropdown Cabang -->
          <div v-if="myBranches && myBranches.length > 0" class="relative inline-flex items-center shrink-0">
            <label class="text-[11.5px] font-medium text-slate-500 mr-1 hidden xl:inline">Cabang:</label>
            <select
              v-model="branchFilter"
              @change="onFilterChange"
              class="appearance-none pl-2.5 pr-7 py-1.5 text-[12px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-2xs cursor-pointer max-w-[155px] truncate"
            >
              <option value="ALL">Semua Cabang</option>
              <option
                v-for="b in myBranches"
                :key="b.branch_id || b"
                :value="b.branch_id || b"
              >
                {{ typeof b === 'object' ? `${b.branch_id} - ${b.branch_name || b.branch_id}` : b }}
              </option>
            </select>
            <svg class="pointer-events-none absolute right-2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- 2. Dropdown Status SPV (Review Area) -->
          <div class="relative inline-flex items-center shrink-0">
            <label class="text-[11.5px] font-medium text-slate-500 mr-1 hidden xl:inline">Review:</label>
            <select
              v-model="spvStatusFilter"
              @change="onFilterChange"
              class="appearance-none pl-2.5 pr-7 py-1.5 text-[12px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-2xs cursor-pointer max-w-[145px]"
            >
              <option value="ALL">Semua Review</option>
              <option value="PENDING">Menunggu Review</option>
              <option value="PROCESSED">Sudah Diproses</option>
            </select>
            <svg class="pointer-events-none absolute right-2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- 3. Dropdown Status EDP (Principal) -->
          <div class="relative inline-flex items-center shrink-0">
            <label class="text-[11.5px] font-medium text-slate-500 mr-1 hidden xl:inline">Principal:</label>
            <select
              v-model="edpStatusFilter"
              @change="onFilterChange"
              class="appearance-none pl-2.5 pr-7 py-1.5 text-[12px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-2xs cursor-pointer max-w-[145px]"
            >
              <option value="ALL">Semua Principal</option>
              <option value="PENDING">Pending Principal</option>
              <option value="APPROVED">Approved Principal</option>
              <option value="REJECTED">Ditolak Principal</option>
            </select>
            <svg class="pointer-events-none absolute right-2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- 4. Dropdown Urutkan -->
          <div class="relative inline-flex items-center shrink-0">
            <label class="text-[11.5px] font-medium text-slate-500 mr-1 hidden xl:inline">Urutkan:</label>
            <select
              v-model="sortSelect"
              @change="onFilterChange"
              class="appearance-none pl-2.5 pr-7 py-1.5 text-[12px] font-medium rounded-lg bg-white border border-[#D1D5DB] text-[#1F2937] focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-2xs cursor-pointer max-w-[155px]"
            >
              <option value="submitted_at_desc">Terbaru (Submisi)</option>
              <option value="submitted_at_asc">Terlama (Submisi)</option>
              <option value="nama_noo_asc">Nama Toko (A - Z)</option>
              <option value="nama_noo_desc">Nama Toko (Z - A)</option>
              <option value="salesman_name_asc">Salesman (A - Z)</option>
            </select>
            <svg class="pointer-events-none absolute right-2 w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>

        <!-- Button Reset dibuat kecil di pojok kanan bawah container -->
        <div v-if="hasActiveFilter" class="flex justify-end pt-1.5">
          <button
            type="button"
            @click="resetAllFilters"
            class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2.5 py-0.5 rounded transition cursor-pointer"
            title="Reset semua filter ke kondisi awal"
          >
            <span>✕</span> Reset Filter
          </button>
        </div>
      </div>

      <!-- TABEL SUBMISI OUTLET (7 KOLOM PAS 100% TANPA SCROLL HORIZONTAL) -->
      <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
        <div class="w-full">
          <table class="w-full text-left text-[12.5px] leading-[17px] text-[#374151] table-fixed">
            <thead class="bg-[#F8FAFC] text-[11.5px] font-semibold text-[#475569] border-b border-[#E2E8F0] select-none">
              <tr>
                <th class="w-[26%] px-3 py-3">Toko & Salesman</th>
                <th class="w-[22%] px-3 py-3">Alamat</th>
                <th class="w-[13%] px-2.5 py-3">Status</th>
                <th class="w-[11%] px-2 py-3">Cust Dist.</th>
                <th class="w-[11%] px-2 py-3">Cust Principal</th>
                <th class="w-[10%] px-2 py-3">Rute Kunjungan</th>
                <th class="w-[7%] px-2 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E2E8F0]">
              <!-- Empty State -->
              <tr v-if="sortedSubmissions.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-slate-500 font-normal">
                  <div class="text-[13.5px] font-semibold text-slate-800">Tidak Ada Data Submisi</div>
                  <p class="text-[12px] text-slate-500 mt-1">Tidak ada data submisi toko yang sesuai dengan filter atau kata kunci pencarian Anda.</p>
                </td>
              </tr>

              <!-- Row Item -->
              <tr
                v-for="item in sortedSubmissions"
                :key="item.id || item.request_id"
                @click="openDetailModal(item)"
                class="transition border-b cursor-pointer group hover:bg-slate-50/80 select-none bg-white"
                title="Klik untuk membuka detail submisi & kelola rute toko"
              >
                <!-- 1. Toko & Salesman (Nama Outlet [GT04] / Nama Salesman) -->
                <td class="px-3 py-2.5 align-top">
                  <div class="flex items-center gap-1.5 truncate">
                    <span class="font-bold text-[13px] text-slate-900 group-hover:text-blue-600 transition truncate" :title="item.nama_noo">
                      {{ item.nama_noo }}
                    </span>
                    <span
                      v-if="item.type_outlet_code"
                      class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 shrink-0"
                    >
                      {{ item.type_outlet_code }}
                    </span>
                  </div>
                  <div class="text-[11.5px] text-slate-600 truncate mt-0.5" :title="item.salesman_name">
                    {{ item.salesman_name || '-' }}
                  </div>
                </td>

                <!-- 2. Alamat (Alamat / Nama Distributor) -->
                <td class="px-3 py-2.5 align-top">
                  <div class="text-[12px] text-slate-800 truncate" :title="item.alamat_noo">
                    {{ item.alamat_noo || '-' }}
                  </div>
                  <div class="text-[11px] text-slate-500 font-medium truncate mt-0.5" :title="item.branch_name || item.branch_id">
                    {{ item.branch_name || item.branch_id || '-' }}
                  </div>
                </td>

                <!-- 3. Status -->
                <td class="px-2.5 py-2.5 align-top">
                  <span class="inline-block px-2 py-0.5 rounded-full text-[10.5px] font-semibold border shadow-2xs whitespace-nowrap" :class="getStatusBadgeStyle(item.status)">
                    {{ formatStatusLabel(item.status) }}
                  </span>
                </td>

                <!-- 4. Cust Dist. -->
                <td class="px-2 py-2.5 align-top">
                  <span
                    v-if="item.custcode_distributor"
                    class="text-[11px] font-mono font-medium px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 inline-block whitespace-nowrap"
                    :title="item.custcode_distributor"
                  >
                    {{ item.custcode_distributor }}
                  </span>
                  <span v-else class="text-slate-400 text-xs italic">-</span>
                </td>

                <!-- 5. Cust Principal -->
                <td class="px-2 py-2.5 align-top">
                  <span
                    v-if="item.code_noo_principal"
                    class="text-[11px] font-mono font-medium px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 inline-block whitespace-nowrap"
                    :title="item.code_noo_principal"
                  >
                    {{ item.code_noo_principal }}
                  </span>
                  <span v-else class="text-slate-400 text-xs italic">-</span>
                </td>

                <!-- 6. Rute Kunjungan -->
                <td class="px-2 py-2.5 align-top">
                  <div v-if="hasRoute(item)" class="text-[11px] space-y-0.5 whitespace-nowrap leading-tight">
                    <div class="text-slate-700">Hari: <span class="font-medium text-slate-900">{{ getRouteDaysSummary(item) }}</span></div>
                    <div class="text-slate-500">Mg: <span class="font-medium text-slate-800">{{ getRouteWeeksSummary(item) }}</span></div>
                  </div>
                  <div v-else class="text-[11px] text-slate-400 italic">
                    Belum di-set
                  </div>
                </td>

                <!-- 7. Button Detail (Kelola) -->
                <td class="px-2 py-2.5 align-top text-center" @click.stop>
                  <button
                    type="button"
                    @click="openDetailModal(item)"
                    class="inline-flex items-center justify-center px-2.5 py-1 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-lg shadow-2xs transition cursor-pointer shrink-0 whitespace-nowrap"
                  >
                    Kelola
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
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

    <!-- MODAL SLIDE-OVER PREVIEW DETAIL & PENGATURAN RUTE SPV (LEVEL 1 Z-INDEX 99990) -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedSubmission" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 md:p-6" @click.self="closeDetailModal">
      <div class="bg-white rounded-xl md:rounded-2xl max-w-4xl w-full max-h-[92vh] sm:max-h-[88vh] md:max-h-[85vh] flex flex-col shadow-[0_15px_40px_rgba(0,0,0,0.18)] border border-[#E5E7EB] overflow-hidden text-[#374151]">
        
        <!-- Header Modal: Responsif Tablet & Desktop -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-[#1E3A8A] text-white flex items-center justify-between shrink-0">
          <div>
            <div class="flex items-center space-x-2 sm:space-x-3 flex-wrap gap-1">
              <h3 class="text-base sm:text-lg md:text-[22px] font-semibold leading-snug text-white">{{ selectedSubmission.nama_noo }}</h3>
              <span class="px-2 py-0.5 rounded text-[10.5px] sm:text-xs font-semibold bg-white/20 text-white border border-white/30">
                {{ selectedSubmission.type_outlet_code }} - {{ selectedSubmission.type_outlet_desc }}
              </span>
            </div>
            <p class="text-[11px] sm:text-xs text-blue-200 mt-0.5">Request ID: {{ selectedSubmission.request_id }} | Branch: {{ selectedSubmission.branch_name }}</p>
          </div>
          <button @click="showDetailModal = false" class="text-blue-200 hover:text-white text-xl font-bold p-1 hover:bg-blue-800 rounded-lg">
            ✕
          </button>
        </div>

        <!-- Body Modal Detail -->
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6 overflow-y-auto flex-1 bg-[#F8FAFC]">

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
              <p class="text-[12px] font-medium text-[#4B5563] uppercase">GPS Koordinat</p>
              <p class="text-[14px] font-mono text-[#374151] mt-0.5">{{ selectedSubmission.la }}, {{ selectedSubmission.lg }}</p>
              <p class="text-[12px] text-[#15803D] font-semibold mt-0.5">Akurasi GPS: {{ selectedSubmission.accuracy_m }} meter</p>
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
                <div v-if="getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)">
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_depan_url || selectedSubmission.photo_depan_path)"
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
                <div v-if="getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)">
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_dalam_url || selectedSubmission.photo_dalam_path)"
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
                <div v-if="getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)" class="relative group cursor-pointer overflow-hidden rounded-lg" @click="activePhotoZoom = getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)">
                  <img
                    :src="getPhotoUrl(selectedSubmission.photo_ktp_url || selectedSubmission.photo_ktp_path)"
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
      <div v-if="showRejectModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[999999] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" @click.self="showRejectModal = false">
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

    <!-- MODAL PROGRESS TRACKING NOO CABANG BINAAN SPV -->
    <ProgressTrackingModal
      :show="showProgressModal"
      :my-branches="myBranches"
      @close="showProgressModal = false"
      @select-submission="handleSelectFromTracking"
    />
  </SpvLayout>
</template>
