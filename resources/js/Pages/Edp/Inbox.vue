<script setup lang="js">
/**
 * Komponen NOO Verification (Halaman Inbox & Review Detail NOO).
 * Implementasi Penyempurnaan Tambahan:
 * 1. Section Rute Kunjungan Salesman & Berkas Foto Bukti Toko/KTP diletakkan di paling bawah modal.
 * 2. Filter Branch mengikuti pilihan Filter Region (Filter terisolasi secara dinamis & auto-reset branch).
 * 3. Revisi Foto KTP Pemilik dibatasi Maksimal 1x (Button ter-disable dengan badge 🔒 Revisi Max (1x)).
 * 4. Refresh instan Foto KTP dengan Timestamp Cache-Buster.
 */
import { ref, computed } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  submissions: Array,
  userRegion: String,
  userRole: {
    type: String,
    default: 'EDP_REGION',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  filterOptions: {
    type: Object,
    default: () => ({}),
  },
});

const selectedRegion = ref(props.filters?.region_code || '');
const selectedPrincipal = ref(props.filters?.principal || '');
const selectedBranch = ref(props.filters?.branch_id || '');
const selectedStatus = ref(props.filters?.status || '');
const search = ref(props.filters?.search || '');

const activeModalSubmission = ref(null);
const isEditingStoreName = ref(false);
const editedStoreName = ref('');
const isEditingAddress = ref(false);
const editedAddress = ref('');
const edpNotesInput = ref('');
const validatorMode = ref('streetview');

const isProcessingApprove = ref(false);
const isProcessingReject = ref(false);
const isProcessingCancelReject = ref(false);
const isProcessingResetApproval = ref(false);

const showKtpModal = ref(false);
const previewImageModal = ref(null);
const ktpFile = ref(null);
const ktpPreviewUrl = ref('');

const isLocked = computed(() => {
  if (!activeModalSubmission.value) return false;
  return activeModalSubmission.value.status === 'APPROVED_EDP' || activeModalSubmission.value.status === 'REJECTED_EDP';
});

const regionOptions = computed(() => {
  return (props.filterOptions?.regions || []).map((r) => {
    if (typeof r === 'object' && r !== null) {
      return {
        value: r.region_code || r.value,
        label: r.region_name ? `${r.region_code} - ${r.region_name}` : String(r.region_code || r.value),
      };
    }
    return { value: r, label: String(r) };
  });
});

const entityOptions = computed(() => {
  let list = props.filterOptions?.entities || [];
  if (selectedRegion.value) {
    list = list.filter((e) => e.region_code === selectedRegion.value);
  }
  return list.map((e) => {
    if (typeof e === 'object' && e !== null) {
      return {
        value: e.entity_code_principal || e.entity_name_principal || e.value,
        label: e.entity_name_principal ? `${e.entity_code_principal || ''} - ${e.entity_name_principal}` : String(e.value || e),
      };
    }
    return { value: e, label: String(e) };
  });
});

// FILTER BRANCH HANYA MENAMPILKAN CABANG DARI REGION & ENTITY TERPILIH
const branchOptions = computed(() => {
  let list = props.filterOptions?.branches || [];
  if (selectedRegion.value) {
    list = list.filter((b) => (b.region_code || b.value) === selectedRegion.value);
  }
  if (selectedPrincipal.value) {
    list = list.filter((b) => (b.entity_code_principal || b.entity_name_principal) === selectedPrincipal.value);
  }
  return list.map((b) => {
    if (typeof b === 'object' && b !== null) {
      return {
        value: b.branch_id || b.value,
        label: b.branch_name ? `${b.branch_id} - ${b.branch_name}` : String(b.value || b),
      };
    }
    return { value: b, label: String(b) };
  });
});

function onRegionChange() {
  if (selectedRegion.value) {
    const isValidEntity = entityOptions.value.some((e) => e.value === selectedPrincipal.value);
    if (!isValidEntity) {
      selectedPrincipal.value = '';
    }
    const isValidBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!isValidBranch) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

function onPrincipalChange() {
  if (selectedPrincipal.value) {
    const isValidBranch = branchOptions.value.some((b) => b.value === selectedBranch.value);
    if (!isValidBranch) {
      selectedBranch.value = '';
    }
  }
  applyFilters();
}

const statusOptions = [
  { value: 'SE_SUBMITTED', label: 'SE Submisi Baru' },
  { value: 'PUSHED_TO_SPV', label: 'Approved Admin (Menunggu SPV)' },
  { value: 'APPROVED_SPV', label: 'Approved SPV (Menunggu EDP)' },
  { value: 'REJECTED_ADMIN', label: 'Rejected Admin Distributor' },
  { value: 'REJECTED_SPV', label: 'Rejected SPV Area' },
  { value: 'APPROVED_EDP', label: 'Approved EDP (Final)' },
  { value: 'REJECTED_EDP', label: 'Rejected EDP Principal' },
];

const dayLabels = [
  { key: 'h1', name: 'Senin (H1)' },
  { key: 'h2', name: 'Selasa (H2)' },
  { key: 'h3', name: 'Rabu (H3)' },
  { key: 'h4', name: 'Kamis (H4)' },
  { key: 'h5', name: 'Jumat (H5)' },
  { key: 'h6', name: 'Sabtu (H6)' },
  { key: 'h7', name: 'Minggu (H7)' },
];

const weekLabels = [
  { key: 'm1', name: 'Minggu 1 (M1)' },
  { key: 'm2', name: 'Minggu 2 (M2)' },
  { key: 'm3', name: 'Minggu 3 (M3)' },
  { key: 'm4', name: 'Minggu 4 (M4)' },
];

function sanitizePluscode(str) {
  if (!str) return '';
  return str.replace(/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,4}(?:\s*,\s*|\s+)/i, '').trim();
}

function applyFilters() {
  router.get(
    route('edp.inbox'),
    {
      region_code: selectedRegion.value,
      principal: selectedPrincipal.value,
      branch_id: selectedBranch.value,
      status: selectedStatus.value,
      search: search.value,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
  selectedStatus.value = '';
  search.value = '';
  applyFilters();
}

function openDetailModal(sub) {
  activeModalSubmission.value = sub;
  isEditingStoreName.value = false;
  editedStoreName.value = sub.nama_noo || '';
  isEditingAddress.value = false;
  editedAddress.value = sanitizePluscode(sub.alamat_noo || '');
  edpNotesInput.value = sub.edp_notes || '';
  validatorMode.value = 'streetview';
}

function closeDetailModal() {
  activeModalSubmission.value = null;
  isEditingStoreName.value = false;
  isEditingAddress.value = false;
}

function saveStoreName() {
  if (!activeModalSubmission.value || !editedStoreName.value) return;
  router.post(
    route('edp.update_store_name'),
    {
      request_id: activeModalSubmission.value.request_id,
      nama_noo: editedStoreName.value,
    },
    {
      onSuccess: () => {
        activeModalSubmission.value.nama_noo = editedStoreName.value;
        isEditingStoreName.value = false;
      },
    }
  );
}

function saveStoreAddress() {
  if (!activeModalSubmission.value || !editedAddress.value) return;
  const cleanAddr = sanitizePluscode(editedAddress.value);
  router.post(
    route('edp.update_store_address'),
    {
      request_id: activeModalSubmission.value.request_id,
      alamat_noo: cleanAddr,
    },
    {
      onSuccess: () => {
        activeModalSubmission.value.alamat_noo = cleanAddr;
        isEditingAddress.value = false;
      },
    }
  );
}

function handleApprove() {
  if (!activeModalSubmission.value || isProcessingApprove.value || isProcessingReject.value) return;
  const currentReqId = activeModalSubmission.value.request_id;

  router.post(
    route('edp.approve'),
    {
      request_id: currentReqId,
      edp_notes: edpNotesInput.value,
    },
    {
      preserveScroll: true,
      onStart: () => {
        isProcessingApprove.value = true;
      },
      onFinish: () => {
        isProcessingApprove.value = false;
      },
      onSuccess: () => {
        closeDetailModal();
      },
    }
  );
}

const showRejectModal = ref(false);
const rejectNotesInput = ref('');

const showExportModal = ref(false);
const exportStartDate = ref('');
const exportEndDate = ref('');
const exportBranch = ref('');
const exportBranches = ref([]);
const exportSubmissions = ref([]);
const selectedExportIds = ref([]);
const isLoadingExportData = ref(false);
const hasFetchedExport = ref(false);
const isExportingExcel = ref(false);

const showExportRejectedModal = ref(false);
const exportRejectedStartDate = ref('');
const exportRejectedEndDate = ref('');
const exportRejectedBranch = ref('');
const exportRejectedBranches = ref([]);
const exportRejectedSubmissions = ref([]);
const selectedExportRejectedIds = ref([]);
const isLoadingExportRejectedData = ref(false);
const hasFetchedExportRejected = ref(false);
const isExportingRejectedExcel = ref(false);

function handleReject() {
  if (!activeModalSubmission.value) return;
  rejectNotesInput.value = '';
  showRejectModal.value = true;
}

function submitReject() {
  if (!activeModalSubmission.value || isProcessingReject.value || isProcessingApprove.value) return;
  const reason = rejectNotesInput.value ? rejectNotesInput.value.trim() : '';

  if (!reason) {
    alert('Harap masukkan alasan penolakan EDP Principal.');
    return;
  }

  const currentReqId = activeModalSubmission.value.request_id;

  router.post(
    route('edp.reject'),
    {
      request_id: currentReqId,
      reject_reason: reason,
      edp_notes: reason,
    },
    {
      preserveScroll: true,
      onStart: () => {
        isProcessingReject.value = true;
      },
      onFinish: () => {
        isProcessingReject.value = false;
      },
      onSuccess: () => {
        showRejectModal.value = false;
        closeDetailModal();
      },
    }
  );
}

function getLocalDateString(d) {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function openExportModal() {
  const today = new Date();
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(today.getDate() - 30);

  exportEndDate.value = getLocalDateString(today);
  exportStartDate.value = getLocalDateString(thirtyDaysAgo);
  exportBranch.value = '';
  exportBranches.value = [];
  exportSubmissions.value = [];
  selectedExportIds.value = [];
  hasFetchedExport.value = false;
  showExportModal.value = true;
  fetchExportBranches();
}

function onExportDateChange() {
  exportBranch.value = '';
  exportSubmissions.value = [];
  selectedExportIds.value = [];
  hasFetchedExport.value = false;
  fetchExportBranches();
}

async function fetchExportBranches() {
  if (!exportStartDate.value || !exportEndDate.value) return;
  isLoadingExportData.value = true;
  try {
    const params = new URLSearchParams();
    params.append('start_date', exportStartDate.value);
    params.append('end_date', exportEndDate.value);

    const res = await fetch(route('edp.export_approved_data') + '?' + params.toString());
    const data = await res.json();
    exportBranches.value = data.branches || [];
  } catch (e) {
    console.error('Gagal mengambil daftar distributor:', e);
  } finally {
    isLoadingExportData.value = false;
  }
}

async function fetchExportData() {
  if (!exportBranch.value) {
    alert('Silakan pilih distributor terlebih dahulu.');
    return;
  }

  isLoadingExportData.value = true;
  hasFetchedExport.value = true;
  try {
    const params = new URLSearchParams();
    if (exportStartDate.value) params.append('start_date', exportStartDate.value);
    if (exportEndDate.value) params.append('end_date', exportEndDate.value);
    params.append('branch_id', exportBranch.value);

    const res = await fetch(route('edp.export_approved_data') + '?' + params.toString());
    const data = await res.json();
    exportSubmissions.value = data.submissions || [];
    selectedExportIds.value = exportSubmissions.value.map(s => s.request_id);
  } catch (e) {
    console.error('Gagal mengambil data export:', e);
  } finally {
    isLoadingExportData.value = false;
  }
}

const groupedExportBranches = computed(() => {
  if (!exportBranches.value.length) return {};
  const groups = {};

  const sorted = [...exportBranches.value].sort((a, b) => {
    const regCompare = (a.region_code || '').localeCompare(b.region_code || '');
    if (regCompare !== 0) return regCompare;
    return (a.branch_id || '').localeCompare(b.branch_id || '');
  });

  sorted.forEach((b) => {
    const regionKey = b.region_code || 'LAINNYA';
    if (!groups[regionKey]) {
      const labelText = b.region_name ? `${b.region_code} - ${b.region_name}` : regionKey;
      groups[regionKey] = {
        label: labelText,
        branches: [],
      };
    }
    groups[regionKey].branches.push(b);
  });

  return groups;
});

const isAllExportSelected = computed(() => {
  if (!exportSubmissions.value.length) return false;
  return selectedExportIds.value.length === exportSubmissions.value.length;
});

function toggleSelectAllExport() {
  if (isAllExportSelected.value) {
    selectedExportIds.value = [];
  } else {
    selectedExportIds.value = exportSubmissions.value.map((s) => s.request_id);
  }
}

async function submitExportSelected() {
  if (!selectedExportIds.value.length) {
    alert('Silakan pilih minimal 1 data NOO Approved untuk diekspor.');
    return;
  }

  isExportingExcel.value = true;
  try {
    const response = await axios.post(
      route('edp.export_approved_selected'),
      {
        request_ids: selectedExportIds.value,
        start_date: exportStartDate.value,
        end_date: exportEndDate.value,
        branch_id: exportBranch.value,
      },
      { responseType: 'blob' }
    );

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;

    const branchSuffix = exportBranch.value || 'APPROVED';
    link.download = `EXPORT_TEMPLATE_NOO_${branchSuffix}_${getLocalDateString(new Date())}.xlsx`;

    document.body.appendChild(link);
    link.click();
    window.URL.revokeObjectURL(downloadUrl);
    document.body.removeChild(link);
  } catch (err) {
    console.error('Gagal export Excel:', err);
    alert('Terjadi kesalahan saat mengunduh berkas Excel. Silakan coba lagi.');
  } finally {
    isExportingExcel.value = false;
  }
}

function triggerDatePicker(event) {
  if (event?.target && typeof event.target.showPicker === 'function') {
    try {
      event.target.showPicker();
    } catch (e) {
      // Ignore if user clicks fast or browser blocks showPicker
    }
  }
}

function openExportRejectedModal() {
  const today = new Date();
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(today.getDate() - 30);

  exportRejectedEndDate.value = getLocalDateString(today);
  exportRejectedStartDate.value = getLocalDateString(thirtyDaysAgo);
  exportRejectedSubmissions.value = [];
  selectedExportRejectedIds.value = [];
  hasFetchedExportRejected.value = false;
  showExportRejectedModal.value = true;
}

function onExportRejectedDateChange() {
  exportRejectedBranch.value = '';
  exportRejectedSubmissions.value = [];
  selectedExportRejectedIds.value = [];
  hasFetchedExportRejected.value = false;
  fetchExportRejectedBranches();
}

async function fetchExportRejectedBranches() {
  if (!exportRejectedStartDate.value || !exportRejectedEndDate.value) return;
  isLoadingExportRejectedData.value = true;
  try {
    const params = new URLSearchParams();
    params.append('start_date', exportRejectedStartDate.value);
    params.append('end_date', exportRejectedEndDate.value);

    const res = await fetch(route('edp.export_rejected_data') + '?' + params.toString());
    const data = await res.json();
    exportRejectedBranches.value = data.branches || [];
  } catch (e) {
    console.error('Gagal mengambil daftar distributor rejected:', e);
  } finally {
    isLoadingExportRejectedData.value = false;
  }
}

async function fetchExportRejectedData() {
  isLoadingExportRejectedData.value = true;
  hasFetchedExportRejected.value = true;
  try {
    const params = new URLSearchParams();
    if (exportRejectedStartDate.value) params.append('start_date', exportRejectedStartDate.value);
    if (exportRejectedEndDate.value) params.append('end_date', exportRejectedEndDate.value);
    if (exportRejectedBranch.value) params.append('branch_id', exportRejectedBranch.value);

    const res = await fetch(route('edp.export_rejected_data') + '?' + params.toString());
    const data = await res.json();
    exportRejectedSubmissions.value = data.submissions || [];
    selectedExportRejectedIds.value = exportRejectedSubmissions.value.map((s) => s.request_id);
  } catch (e) {
    console.error('Gagal mengambil data export rejected:', e);
  } finally {
    isLoadingExportRejectedData.value = false;
  }
}

const groupedExportRejectedBranches = computed(() => {
  if (!exportRejectedBranches.value.length) return {};
  const groups = {};

  const sorted = [...exportRejectedBranches.value].sort((a, b) => {
    const regCompare = (a.region_code || '').localeCompare(b.region_code || '');
    if (regCompare !== 0) return regCompare;
    return (a.branch_id || '').localeCompare(b.branch_id || '');
  });

  sorted.forEach((b) => {
    const regionKey = b.region_code || 'LAINNYA';
    if (!groups[regionKey]) {
      const labelText = b.region_name ? `${b.region_code} - ${b.region_name}` : regionKey;
      groups[regionKey] = {
        label: labelText,
        branches: [],
      };
    }
    groups[regionKey].branches.push(b);
  });

  return groups;
});

const isAllExportRejectedSelected = computed(() => {
  if (!exportRejectedSubmissions.value.length) return false;
  return selectedExportRejectedIds.value.length === exportRejectedSubmissions.value.length;
});

function toggleSelectAllExportRejected() {
  if (isAllExportRejectedSelected.value) {
    selectedExportRejectedIds.value = [];
  } else {
    selectedExportRejectedIds.value = exportRejectedSubmissions.value.map((s) => s.request_id);
  }
}

async function submitExportRejectedSelected() {
  if (!selectedExportRejectedIds.value.length) {
    alert('Silakan pilih minimal 1 data NOO Rejected untuk diekspor.');
    return;
  }

  isExportingRejectedExcel.value = true;
  try {
    const response = await axios.post(
      route('edp.export_rejected_selected'),
      {
        request_ids: selectedExportRejectedIds.value,
        start_date: exportRejectedStartDate.value,
        end_date: exportRejectedEndDate.value,
        branch_id: exportRejectedBranch.value,
      },
      { responseType: 'blob' }
    );

    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;

    const branchSuffix = exportRejectedBranch.value || 'REJECTED';
    link.download = `EXPORT_REJECTED_NOO_${branchSuffix}_${getLocalDateString(new Date())}.xlsx`;

    document.body.appendChild(link);
    link.click();
    window.URL.revokeObjectURL(downloadUrl);
    document.body.removeChild(link);
  } catch (err) {
    console.error('Gagal export Excel Rejected:', err);
    alert('Terjadi kesalahan saat mengunduh berkas Excel Rejected. Silakan coba lagi.');
  } finally {
    isExportingRejectedExcel.value = false;
  }
}

function handleCancelRejection() {
  if (!activeModalSubmission.value || isProcessingCancelReject.value) return;
  const currentReqId = activeModalSubmission.value.request_id;

  router.post(
    route('edp.cancel_rejection'),
    {
      request_id: currentReqId,
    },
    {
      preserveScroll: true,
      onStart: () => {
        isProcessingCancelReject.value = true;
      },
      onFinish: () => {
        isProcessingCancelReject.value = false;
      },
      onSuccess: () => {
        closeDetailModal();
      },
    }
  );
}

function openKtpModal() {
  showKtpModal.value = true;
  ktpFile.value = null;
  ktpPreviewUrl.value = '';
}

function handleKtpFileSelect(event) {
  const file = event.target.files[0];
  if (!file) return;
  ktpFile.value = file;
  ktpPreviewUrl.value = URL.createObjectURL(file);
}

function handleKtpDrop(event) {
  event.preventDefault();
  const file = event.dataTransfer.files[0];
  if (!file) return;
  ktpFile.value = file;
  ktpPreviewUrl.value = URL.createObjectURL(file);
}

function submitKtpRevision() {
  if (!ktpFile.value || !activeModalSubmission.value) return;
  const currentReqId = activeModalSubmission.value.request_id;

  const formData = new FormData();
  formData.append('request_id', currentReqId);
  formData.append('photo_ktp', ktpFile.value);

  router.post(route('edp.revise_ktp'), formData, {
    onSuccess: (page) => {
      showKtpModal.value = false;
      const ts = Date.now();
      const updatedList = page?.props?.submissions || props.submissions || [];
      const updatedSub = updatedList.find((s) => s.request_id === currentReqId);

      if (updatedSub) {
        const freshUrl = updatedSub.photo_ktp_url
          ? (updatedSub.photo_ktp_url.includes('?') ? `${updatedSub.photo_ktp_url}&t=${ts}` : `${updatedSub.photo_ktp_url}?t=${ts}`)
          : (ktpPreviewUrl.value || null);
        
        activeModalSubmission.value = {
          ...updatedSub,
          photo_ktp_url: freshUrl,
          is_ktp_revised: true,
        };
      } else if (activeModalSubmission.value) {
        activeModalSubmission.value.is_ktp_revised = true;
        if (ktpPreviewUrl.value) {
          activeModalSubmission.value.photo_ktp_url = ktpPreviewUrl.value;
        }
      }
    },
  });
}

function handleUnlockKtpRevision() {
  if (!activeModalSubmission.value) return;
  if (!confirm('Apakah Anda yakin ingin membuka kembali kunci revisi KTP toko ini (Superadmin)?')) return;

  router.post(
    route('edp.reset_ktp_revision'),
    {
      request_id: activeModalSubmission.value.request_id,
    },
    {
      onSuccess: () => {
        if (activeModalSubmission.value) {
          activeModalSubmission.value.is_ktp_revised = false;
        }
      },
    }
  );
}

function handleResetEdpApproval() {
  if (!activeModalSubmission.value || isProcessingResetApproval.value) return;
  if (!confirm('Apakah Anda yakin ingin mereset approval EDP & kode customer principal toko ini?')) return;

  const currentReqId = activeModalSubmission.value.request_id;
  router.post(
    route('edp.reset_edp_approval'),
    {
      request_id: currentReqId,
    },
    {
      preserveScroll: true,
      onStart: () => {
        isProcessingResetApproval.value = true;
      },
      onFinish: () => {
        isProcessingResetApproval.value = false;
      },
      onSuccess: () => {
        if (activeModalSubmission.value) {
          activeModalSubmission.value.status = 'APPROVED_SPV';
          activeModalSubmission.value.code_noo_principal = null;
        }
        closeDetailModal();
      },
    }
  );
}

const sortKey = ref('created_at');
const sortDir = ref('desc');

function handleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
}

const sortedSubmissions = computed(() => {
  const list = [...(props.submissions?.data || props.submissions || [])];
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

function exportExcel(type) {
  window.open(route('edp.export_excel', { type }), '_blank');
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
</script>

<template>
  <EdpLayout>
    <Head title="NOO Verification - Portal NOO+" />

    <div class="space-y-6">
      
      <!-- Page Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 bg-white p-6 sm:p-7 rounded-xl border border-[#E5E7EB] shadow-xs">
        <div class="space-y-1.5 max-w-3xl">
          <h1 class="text-2xl sm:text-3xl font-extrabold text-[#111827] tracking-tight flex items-center gap-3">
            <span>📋 NOO Verification</span>
          </h1>
          <p class="text-xs sm:text-sm text-[#6B7280] leading-relaxed">
            Verifikasi Final EDP Principal, Penerbitan Kode Customer Principal, & Rekapitulasi Approval. (Region Scope: <span class="font-semibold text-slate-800">{{ userRegion || 'Semua Region' }}</span>)
          </p>
        </div>

        <div class="flex items-center gap-3.5 flex-wrap sm:flex-nowrap shrink-0">
          <button
            @click="openExportModal"
            class="px-5 py-2.5 text-xs sm:text-sm font-bold text-white bg-[#16A34A] hover:bg-[#15803D] border border-emerald-600 rounded-xl shadow-xs hover:shadow transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap"
          >
            <span class="text-base">📊</span>
            <span>Export Approved (.xlsx)</span>
          </button>
          <button
            @click="openExportRejectedModal"
            class="px-5 py-2.5 text-xs sm:text-sm font-bold text-white bg-[#DC2626] hover:bg-[#B91C1C] border border-red-600 rounded-xl shadow-xs hover:shadow transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap"
          >
            <span class="text-base">📊</span>
            <span>Export Rejected (.xlsx)</span>
          </button>
        </div>
      </div>

      <!-- FILTER BAR DINAMIS BERBASIS PERAN (ROLE-BASED FILTER) -->
      <div class="bg-white p-6 rounded-[10px] border border-[#E5E7EB] shadow-xs space-y-4">
        <h2 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-[#2563EB]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          <span>Filter Data Verifikasi NOO</span>
        </h2>

        <div :class="['grid grid-cols-1 sm:grid-cols-2 gap-4', userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL' ? 'md:grid-cols-4' : 'md:grid-cols-3']">
          <!-- REGION -->
          <div v-if="userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL'">
            <label class="block text-[14px] font-medium text-[#4B5563] mb-1">REGION</label>
            <SearchableSelect
              v-model="selectedRegion"
              :options="regionOptions"
              placeholder="-- Semua Region --"
              searchPlaceholder="Ketik Region Code / Nama..."
              @change="onRegionChange"
            />
          </div>

          <!-- ENTITY / PRINCIPAL -->
          <div>
            <label class="block text-[14px] font-medium text-[#4B5563] mb-1">ENTITY / PRINCIPAL</label>
            <SearchableSelect
              v-model="selectedPrincipal"
              :options="entityOptions"
              placeholder="-- Semua Principal --"
              searchPlaceholder="Ketik Kode / Nama Entity..."
              @change="onPrincipalChange"
            />
          </div>

          <!-- BRANCH (MENGIKUTI REGION TERPILIH) -->
          <div>
            <label class="block text-[14px] font-medium text-[#4B5563] mb-1">CABANG / BRANCH</label>
            <SearchableSelect
              v-model="selectedBranch"
              :options="branchOptions"
              placeholder="-- Semua Cabang --"
              searchPlaceholder="Ketik ID atau Nama Cabang..."
              @change="applyFilters"
            />
          </div>

          <!-- STATUS -->
          <div>
            <label class="block text-[14px] font-medium text-[#4B5563] mb-1">STATUS</label>
            <SearchableSelect
              v-model="selectedStatus"
              :options="statusOptions"
              placeholder="-- Semua Status --"
              searchPlaceholder="Cari Status..."
              @change="applyFilters"
            />
          </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
          <div class="w-full sm:w-80">
            <input
              type="text"
              v-model="search"
              @keyup.enter="applyFilters"
              placeholder="Cari Nama Toko, CustCode, Salesman..."
              class="w-full px-3.5 py-2.5 text-[16px] font-normal text-[#374151] bg-white border border-[#D1D5DB] hover:border-[#9CA3AF] focus:border-[#2563EB] rounded-[8px] placeholder-[#9CA3AF] transition"
            />
          </div>

          <div class="flex items-center gap-2">
            <button @click="resetFilters" class="px-4 py-2.5 text-[15px] font-semibold text-[#374151] bg-white border border-[#D1D5DB] hover:bg-[#F3F4F6] rounded-[8px] transition cursor-pointer">Reset Filter</button>
            <button @click="applyFilters" class="px-5 py-2.5 text-[15px] font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] rounded-[8px] transition shadow-sm cursor-pointer">Cari & Filter</button>
          </div>
        </div>
      </div>

      <!-- Table Submisi NOO -->
      <div class="bg-white rounded-[10px] border border-[#E5E7EB] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[14px] text-left text-[#374151]">
            <thead class="bg-[#F3F4F6] border-b border-[#E5E7EB] font-semibold text-[#111827] uppercase tracking-wider select-none">
              <tr>
                <th @click="handleSort('created_at')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>Waktu Submit</span>
                    <span v-if="sortKey === 'created_at'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('branch_name')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>Cabang / Salesman</span>
                    <span v-if="sortKey === 'branch_name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('nama_noo')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>Nama Outlet & Pemilik</span>
                    <span v-if="sortKey === 'nama_noo'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('custcode_distributor')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>CustCode Dist</span>
                    <span v-if="sortKey === 'custcode_distributor'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('code_noo_principal')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>Customer Code Principal</span>
                    <span v-if="sortKey === 'code_noo_principal'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th @click="handleSort('status')" class="p-4 cursor-pointer hover:bg-[#E5E7EB] transition">
                  <div class="flex items-center gap-1">
                    <span>Status</span>
                    <span v-if="sortKey === 'status'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                    <span v-else class="opacity-30">↕</span>
                  </div>
                </th>
                <th class="p-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="sortedSubmissions.length === 0">
                <td colspan="7" class="text-center py-12 text-[#9CA3AF] text-[14px]">
                  Belum ada data submisi toko masuk untuk verifikasi EDP.
                </td>
              </tr>

              <tr v-for="sub in sortedSubmissions" :key="sub.request_id" class="hover:bg-[#EFF6FF] transition">
                <td class="p-4 text-[#6B7280] font-mono text-[13px]">{{ sub.submitted_at || sub.created_at }}</td>
                <td class="p-4 font-medium text-[#111827]">
                  {{ sub.branch_name }} <br>
                  <span class="text-[12px] text-[#6B7280] font-normal">👤 {{ sub.salesman_name }}</span>
                </td>
                <td class="p-4 font-semibold text-[#111827]">
                  {{ sub.nama_noo }}
                  <div v-if="sub.nama_pemilik_outlet" class="text-[12px] font-normal text-[#6B7280] mt-0.5">
                    Pemilik: {{ sub.nama_pemilik_outlet }} ({{ sub.no_hp_noo || sub.no_hp || '-' }})
                  </div>
                </td>
                <td class="p-4 font-mono font-bold text-[#1D4ED8]">{{ sub.custcode_distributor || '-' }}</td>
                <td class="p-4">
                  <span
                    v-if="sub.code_noo_principal"
                    class="px-2.5 py-1 text-[13px] font-mono font-bold text-[#15803D] bg-[#DCFCE7] border border-[#86EFAC] rounded-[8px] inline-block"
                  >
                    {{ sub.code_noo_principal }}
                  </span>
                  <span v-else class="text-[12px] text-[#9CA3AF] italic">
                    Belum tergenerate
                  </span>
                </td>
                <td class="p-4">
                  <!-- BADGE STATUS NOO+ ARCHITECTURE -->
                  <span class="px-2.5 py-1 text-[12px] font-semibold rounded-[8px] border inline-flex items-center gap-1" :class="getStatusBadgeStyle(sub.status)">
                    {{ formatStatusLabel(sub.status) }}
                  </span>
                </td>
                <td class="p-4 text-center">
                  <button
                    @click="openDetailModal(sub)"
                    class="px-4 py-2 text-[15px] font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] rounded-[8px] transition shadow-xs flex items-center gap-1.5 mx-auto cursor-pointer"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span>Detail & Verifikasi</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination Links -->
        <Pagination
          :links="submissions.links"
          :from="submissions.from"
          :to="submissions.to"
          :total="submissions.total"
        />
      </div>

      <!-- MODAL DETAIL KOMPREHENSIF (FULL SCREEN BACKDROP VIA TELEPORT - LEVEL 1 MODAL) -->
      <Teleport to="body">
        <div v-if="activeModalSubmission" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[85vh] flex flex-col shadow-[0_15px_40px_rgba(0,0,0,0.18)] border border-[#E5E7EB] overflow-hidden text-[#374151]">
          
          <!-- Modal Header -->
          <div class="px-6 py-4 bg-[#1E3A8A] text-white flex items-center justify-between shrink-0">
            <div>
              <div class="flex items-center space-x-3">
                <h3 class="text-[22px] font-semibold leading-[28px] text-white">{{ activeModalSubmission.nama_noo }}</h3>
                <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-white/20 text-white border border-white/30">
                  {{ activeModalSubmission.type_outlet_code }} - {{ activeModalSubmission.type_outlet_desc || 'Retail' }}
                </span>
              </div>
              <p class="text-xs text-blue-200 mt-0.5">Request ID: {{ activeModalSubmission.request_id }} | Branch: {{ activeModalSubmission.branch_name || activeModalSubmission.branch_id }}</p>
            </div>
            <button @click="closeDetailModal" class="text-blue-200 hover:text-white text-xl font-bold p-1 hover:bg-blue-800 rounded-lg">
              ✕
            </button>
          </div>

          <!-- Modal Body (Scrollable) -->
          <div class="p-6 space-y-6 overflow-y-auto flex-1 bg-[#F8FAFC] text-[14px]">
            
            <!-- SECTION 1: STATUS & KODE CUSTOMER -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-[#EFF6FF] p-4 rounded-[10px] border border-[#BFDBFE]">
              <div>
                <span class="text-[12px] font-semibold text-[#6B7280] uppercase tracking-wider block mb-1">STATUS SAAT INI</span>
                <div class="mt-0.5">
                  <span class="px-2.5 py-0.5 rounded-full text-[12px] font-semibold border inline-block" :class="getStatusBadgeStyle(activeModalSubmission.status)">
                    {{ formatStatusLabel(activeModalSubmission.status) }}
                  </span>
                </div>
              </div>
              <div>
                <span class="text-[12px] font-semibold text-[#6B7280] uppercase tracking-wider block mb-1">KODE CUST PRINCIPAL</span>
                <p class="text-[15px] font-mono font-bold text-[#15803D]">{{ activeModalSubmission.code_noo_principal || 'BELUM TERGENERATE' }}</p>
              </div>
              <div>
                <span class="text-[12px] font-semibold text-[#6B7280] uppercase tracking-wider block mb-1">KODE CUST DISTRIBUTOR</span>
                <p class="text-[15px] font-mono font-bold text-[#1E3A8A]">{{ activeModalSubmission.custcode_distributor || '-' }}</p>
              </div>
            </div>

            <!-- SECTION 2: DATA TOKO & PEMILIK -->
            <div class="space-y-3">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider border-b border-[#E5E7EB] pb-2">📍 Data Lokasi Toko & Kontak</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                
                <!-- NAMA TOKO / OUTLET -->
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Nama Toko / Outlet</span>
                  <div v-if="!isEditingStoreName" class="flex items-center gap-2">
                    <span class="font-bold text-[#111827] text-[15px]">{{ activeModalSubmission.nama_noo }}</span>
                    <button
                      v-if="!isLocked"
                      @click="isEditingStoreName = true"
                      class="p-1 text-[#6B7280] hover:text-[#2563EB] hover:bg-[#EFF6FF] rounded-[8px] transition cursor-pointer"
                      title="Edit Nama Toko / Outlet"
                    >
                      ✏️
                    </button>
                  </div>
                  <div v-else class="flex items-center gap-1.5 mt-1">
                    <input
                      type="text"
                      v-model="editedStoreName"
                      class="px-3 py-1.5 text-[14px] font-normal text-[#374151] bg-white border border-[#2563EB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB]"
                    />
                    <button @click="saveStoreName" class="px-2.5 py-1.5 text-[13px] font-semibold text-white bg-[#16A34A] hover:bg-[#15803D] rounded-[8px] cursor-pointer">Simpan ✓</button>
                    <button @click="isEditingStoreName = false" class="px-2 py-1.5 text-[13px] font-semibold text-[#374151] bg-[#F3F4F6] rounded-[8px] cursor-pointer">Batal ✕</button>
                  </div>
                </div>

                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Nama Pemilik</span>
                  <span class="font-semibold text-[#111827]">{{ activeModalSubmission.nama_pemilik_outlet || '-' }}</span>
                </div>
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">No HP / Telepon</span>
                  <span class="font-semibold text-[#111827]">{{ activeModalSubmission.no_hp_noo || activeModalSubmission.no_hp || '-' }}</span>
                </div>

                <!-- TIPE OUTLET BESERTA DESCRIPTION -->
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Tipe Outlet</span>
                  <span class="font-bold text-[#1D4ED8]">
                    {{ activeModalSubmission.type_outlet_code }} ({{ activeModalSubmission.type_outlet_desc || 'Retail' }})
                  </span>
                </div>

                <!-- ALAMAT LENGKAP -->
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Alamat Lengkap</span>
                  <div v-if="!isEditingAddress" class="flex items-start gap-2">
                    <span class="font-semibold text-[#111827] leading-[20px]">{{ activeModalSubmission.alamat_noo || '-' }}</span>
                    <button
                      v-if="!isLocked"
                      @click="isEditingAddress = true"
                      class="p-1 text-[#6B7280] hover:text-[#2563EB] hover:bg-[#EFF6FF] rounded-[8px] transition cursor-pointer flex-shrink-0"
                      title="Edit Alamat Lengkap"
                    >
                      ✏️
                    </button>
                  </div>
                  <div v-else class="flex flex-col gap-1.5 mt-1">
                    <textarea
                      v-model="editedAddress"
                      rows="2"
                      class="w-full p-2.5 text-[14px] font-normal text-[#374151] bg-white border border-[#2563EB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB]"
                      placeholder="Masukkan Alamat Lengkap..."
                    ></textarea>
                    <div class="flex items-center gap-2">
                      <button @click="saveStoreAddress" class="px-3 py-1 text-[13px] font-semibold text-white bg-[#16A34A] hover:bg-[#15803D] rounded-[8px] cursor-pointer">Simpan ✓</button>
                      <button @click="isEditingAddress = false" class="px-2.5 py-1 text-[13px] font-semibold text-[#374151] bg-[#F3F4F6] rounded-[8px] cursor-pointer">Batal ✕</button>
                    </div>
                  </div>
                </div>

                <!-- KELURAHAN / KECAMATAN -->
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Kelurahan / Kecamatan</span>
                  <span class="font-semibold text-[#111827]">
                    {{ activeModalSubmission.kel_noo || '-' }} / {{ activeModalSubmission.kec_noo || '-' }}
                  </span>
                </div>

                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Kabupaten / Kota / Prov</span>
                  <span class="font-semibold text-[#111827]">{{ activeModalSubmission.kab_kota_noo || '-' }}, {{ activeModalSubmission.provinsi_noo || '-' }}</span>
                </div>
                <div>
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">Koordinat GPS</span>
                  <span class="font-mono text-[#111827] font-bold">{{ activeModalSubmission.la }}, {{ activeModalSubmission.lg }} (Akurasi: {{ activeModalSubmission.accuracy_m || '-' }}m)</span>
                </div>
              </div>
            </div>

            <!-- SECTION 3: HIRARKI CABANG & SALESMAN -->
            <div class="space-y-3">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider border-b border-[#E5E7EB] pb-2">🏢 Hirarki Cabang & Salesman</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-[14px]">
                <div>
                  <span class="text-[#6B7280] block text-[13px]">Region Code</span>
                  <span class="font-bold text-[#1D4ED8]">{{ activeModalSubmission.region_code || '-' }}</span>
                </div>
                <div>
                  <span class="text-[#6B7280] block text-[13px]">Entity / Principal</span>
                  <span class="font-bold text-[#1D4ED8]">{{ activeModalSubmission.principal }} (Code: {{ activeModalSubmission.principal_code }})</span>
                </div>
                <div>
                  <span class="text-[#6B7280] block text-[13px]">Cabang / Branch ID</span>
                  <span class="font-bold text-[#1D4ED8]">{{ activeModalSubmission.branch_id }} - {{ activeModalSubmission.branch_name }}</span>
                </div>
                <div>
                  <span class="text-[#6B7280] block text-[13px]">Sales Executive (SE)</span>
                  <span class="font-bold text-[#111827]">{{ activeModalSubmission.salesman_code }} - {{ activeModalSubmission.salesman_name }}</span>
                </div>
              </div>
            </div>

            <!-- SECTION 4: TRACK RECORD PERSETUJUAN (TIMELINE PROGRESS TRACKER) -->
            <div class="bg-white p-5 rounded-xl border border-[#E5E7EB] shadow-xs space-y-4">
              <div class="flex items-center justify-between border-b border-[#F3F4F6] pb-3 flex-wrap gap-2">
                <h3 class="text-[14px] font-semibold text-[#111827] uppercase tracking-wider flex items-center gap-2">
                  <span>📈 TRACK RECORD PERSETUJUAN (PROGRESS TRACKER)</span>
                </h3>
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200">
                  3-Step Audit Trail
                </span>
              </div>

              <!-- Vertical Timeline List -->
              <div class="relative pl-7 sm:pl-9 space-y-4 pt-1 pb-1 before:absolute before:left-3.5 sm:before:left-4.5 before:top-4 before:bottom-4 before:w-0.5 before:bg-slate-200">
                
                <!-- STEP 1: Admin Distributor -->
                <div class="relative flex items-start">
                  <!-- Step Circle Node -->
                  <div :class="getStepNodeStyle(1, activeModalSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                    <span v-if="getStepStatus(1, activeModalSubmission) === 'COMPLETED'">✓</span>
                    <span v-else-if="getStepStatus(1, activeModalSubmission) === 'REJECTED'">✕</span>
                    <span v-else-if="getStepStatus(1, activeModalSubmission) === 'PENDING'">⏳</span>
                    <span v-else>1</span>
                  </div>

                  <!-- Step Content Card -->
                  <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-blue-300 transition-all space-y-2.5">
                    <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-800 text-[13.5px]">1. Admin Distributor</span>
                        <span :class="getStepBadgeStyle(1, activeModalSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                          {{ getStepLabel(1, activeModalSubmission) }}
                        </span>
                      </div>
                      <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                        🗓️ {{ getStepTimestamp(1, activeModalSubmission) }}
                      </span>
                    </div>

                    <!-- Details Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                        <span class="font-medium text-blue-900 bg-blue-50/90 px-2.5 py-0.5 rounded border border-blue-200 text-[11.5px]">
                          👤 {{ getApproverName(1, activeModalSubmission) || 'Admin Cabang' }}
                        </span>
                      </div>
                      <div v-if="activeModalSubmission.custcode_distributor" class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Kode Dist:</span>
                        <span class="font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 text-[11.5px]">
                          {{ activeModalSubmission.custcode_distributor }}
                        </span>
                      </div>
                    </div>

                    <!-- Admin Notes -->
                    <div v-if="activeModalSubmission.admin_notes" class="p-2.5 rounded-lg bg-amber-50/90 border border-amber-200 text-amber-900 text-xs">
                      <span class="font-bold block text-[11px] text-amber-800 mb-0.5">💬 Catatan Admin Distributor:</span>
                      <p class="whitespace-pre-line leading-relaxed">{{ activeModalSubmission.admin_notes }}</p>
                    </div>
                    <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan admin distributor.</div>
                  </div>
                </div>

                <!-- STEP 2: SPV Area -->
                <div class="relative flex items-start">
                  <!-- Step Circle Node -->
                  <div :class="getStepNodeStyle(2, activeModalSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                    <span v-if="getStepStatus(2, activeModalSubmission) === 'COMPLETED'">✓</span>
                    <span v-else-if="getStepStatus(2, activeModalSubmission) === 'REJECTED'">✕</span>
                    <span v-else-if="getStepStatus(2, activeModalSubmission) === 'PENDING'">⏳</span>
                    <span v-else>2</span>
                  </div>

                  <!-- Step Content Card -->
                  <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-purple-300 transition-all space-y-2.5">
                    <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-800 text-[13.5px]">2. SPV Area</span>
                        <span :class="getStepBadgeStyle(2, activeModalSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                          {{ getStepLabel(2, activeModalSubmission) }}
                        </span>
                      </div>
                      <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                        🗓️ {{ getStepTimestamp(2, activeModalSubmission) }}
                      </span>
                    </div>

                    <!-- Details Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                        <span v-if="getApproverName(2, activeModalSubmission)" class="font-medium text-purple-900 bg-purple-50/90 px-2.5 py-0.5 rounded border border-purple-200 text-[11.5px]">
                          👤 {{ getApproverName(2, activeModalSubmission) }}
                        </span>
                        <span v-else class="text-slate-400 italic text-[11px]">-</span>
                      </div>
                      <div v-if="getRouteDaysSummary(activeModalSubmission) !== 'Belum di-set'" class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Rute Sales:</span>
                        <span class="font-semibold text-purple-800 bg-purple-50/90 px-2 py-0.5 rounded border border-purple-200 text-[11.5px]">
                          📅 Rute: {{ getRouteDaysSummary(activeModalSubmission) }} | Periode: {{ getRouteWeeksSummary(activeModalSubmission) }}
                        </span>
                      </div>
                    </div>

                    <!-- SPV Notes -->
                    <div v-if="activeModalSubmission.spv_notes" class="p-2.5 rounded-lg bg-purple-50/90 border border-purple-200 text-purple-900 text-xs">
                      <span class="font-bold block text-[11px] text-purple-800 mb-0.5">💬 Catatan SPV Area:</span>
                      <p class="whitespace-pre-line leading-relaxed">{{ activeModalSubmission.spv_notes }}</p>
                    </div>
                    <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan SPV area.</div>
                  </div>
                </div>

                <!-- STEP 3: EDP Principal -->
                <div class="relative flex items-start">
                  <!-- Step Circle Node -->
                  <div :class="getStepNodeStyle(3, activeModalSubmission)" class="absolute -left-7 sm:-left-9 top-0.5 w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shadow-xs z-10 transition-all">
                    <span v-if="getStepStatus(3, activeModalSubmission) === 'COMPLETED'">✓</span>
                    <span v-else-if="getStepStatus(3, activeModalSubmission) === 'REJECTED'">✕</span>
                    <span v-else-if="getStepStatus(3, activeModalSubmission) === 'PENDING'">⏳</span>
                    <span v-else>3</span>
                  </div>

                  <!-- Step Content Card -->
                  <div class="w-full bg-slate-50/80 p-4 rounded-xl border border-slate-200/90 shadow-2xs hover:border-emerald-300 transition-all space-y-2.5">
                    <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-200/70 pb-2">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-800 text-[13.5px]">3. EDP Principal</span>
                        <span :class="getStepBadgeStyle(3, activeModalSubmission)" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border">
                          {{ getStepLabel(3, activeModalSubmission) }}
                        </span>
                      </div>
                      <span class="text-[11px] font-medium text-slate-700 bg-white px-2.5 py-1 rounded-md border border-slate-200 shadow-2xs">
                        🗓️ {{ getStepTimestamp(3, activeModalSubmission) }}
                      </span>
                    </div>

                    <!-- Details Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Approver:</span>
                        <span v-if="getApproverName(3, activeModalSubmission)" class="font-medium text-emerald-900 bg-emerald-50/90 px-2.5 py-0.5 rounded border border-emerald-200 text-[11.5px]">
                          👤 {{ getApproverName(3, activeModalSubmission) }}
                        </span>
                        <span v-else class="text-slate-400 italic text-[11px]">-</span>
                      </div>
                      <div v-if="activeModalSubmission.code_noo_principal" class="flex items-center gap-2">
                        <span class="font-semibold text-slate-500 min-w-[70px]">Kode NOO:</span>
                        <span class="font-mono font-bold text-emerald-800 bg-emerald-50/90 px-2 py-0.5 rounded border border-emerald-200 text-[11.5px]">
                          {{ activeModalSubmission.code_noo_principal }}
                        </span>
                      </div>
                    </div>

                    <!-- EDP Notes -->
                    <div v-if="activeModalSubmission.edp_notes" class="p-2.5 rounded-lg bg-emerald-50/90 border border-emerald-200 text-emerald-900 text-xs">
                      <span class="font-bold block text-[11px] text-emerald-800 mb-0.5">💬 Catatan EDP Principal:</span>
                      <p class="whitespace-pre-line leading-relaxed">{{ activeModalSubmission.edp_notes }}</p>
                    </div>
                    <div v-else class="text-slate-400 italic text-[11px]">Tidak ada catatan EDP principal.</div>
                  </div>
                </div>

                <!-- Dedicated Rejection & Reset Reason Section -->
                <div v-if="activeModalSubmission.reject_reason || activeModalSubmission.reset_reason" class="pt-3 border-t border-slate-200 space-y-2">
                  <div v-if="activeModalSubmission.reject_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-xs shadow-2xs">
                    <span class="font-bold block text-[11px] text-rose-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                      🚫 Alasan Penolakan (Rejected Reason):
                    </span>
                    <p class="whitespace-pre-line leading-relaxed font-medium text-rose-950">{{ activeModalSubmission.reject_reason }}</p>
                  </div>
                  <div v-if="activeModalSubmission.reset_reason" class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs shadow-2xs">
                    <span class="font-bold block text-[11px] text-amber-800 uppercase tracking-wider mb-1 flex items-center gap-1">
                      ↩️ Alasan Pembatalan / Reset:
                    </span>
                    <p class="whitespace-pre-line leading-relaxed font-medium text-amber-950">{{ activeModalSubmission.reset_reason }}</p>
                  </div>
                </div>

              </div>
            </div>

            <!-- SECTION 5: VALIDATOR 360 (GOOGLE STREET VIEW 360 & MAPS) -->
            <div class="space-y-3 bg-[#F8FAFC] p-4 rounded-[10px] border border-[#E5E7EB]">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                  <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider flex items-center gap-2">
                    <span>🌐 Validator 360 (Google Street View & Peta)</span>
                  </h3>
                  <!-- Mode Switcher -->
                  <div class="inline-flex rounded-[8px] border border-[#D1D5DB] p-0.5 bg-white">
                    <button
                      @click="validatorMode = 'streetview'"
                      :class="[
                        'px-2.5 py-1 text-[12px] font-semibold rounded-[6px] transition cursor-pointer',
                        validatorMode === 'streetview' ? 'bg-[#2563EB] text-white' : 'text-[#4B5563] hover:text-[#111827]'
                      ]"
                    >
                      👁️ Street View 360°
                    </button>
                    <button
                      @click="validatorMode = 'roadmap'"
                      :class="[
                        'px-2.5 py-1 text-[12px] font-semibold rounded-[6px] transition cursor-pointer',
                        validatorMode === 'roadmap' ? 'bg-[#2563EB] text-white' : 'text-[#4B5563] hover:text-[#111827]'
                      ]"
                    >
                      🗺️ Peta Lokasi
                    </button>
                  </div>
                </div>

                <a
                  :href="`https://www.google.com/maps?q=${activeModalSubmission.la},${activeModalSubmission.lg}`"
                  target="_blank"
                  class="text-[13px] font-semibold text-[#2563EB] hover:text-[#1D4ED8] flex items-center gap-1"
                >
                  <span>Buka Google Maps 360 ↗</span>
                </a>
              </div>

              <!-- Interactive Google Street View / Map Embed -->
              <div class="w-full h-72 bg-[#E5E7EB] rounded-[8px] overflow-hidden border border-[#D1D5DB] relative shadow-inner">
                <iframe
                  v-if="validatorMode === 'streetview'"
                  class="w-full h-full border-0"
                  :src="`https://maps.google.com/maps?layer=c&cbll=${activeModalSubmission.la},${activeModalSubmission.lg}&cbp=12,0,0,0,0&output=svembed`"
                  allowfullscreen=""
                  loading="lazy"
                ></iframe>
                <iframe
                  v-else
                  class="w-full h-full border-0"
                  :src="`https://maps.google.com/maps?q=${activeModalSubmission.la},${activeModalSubmission.lg}&z=17&output=embed`"
                  allowfullscreen=""
                  loading="lazy"
                ></iframe>
              </div>
            </div>

            <!-- SECTION 6: BERKAS FOTO TOKO & REVISI KTP ✏️ (PALING BAWAH - Point 1) -->
            <div class="space-y-3">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider border-b border-[#E5E7EB] pb-2">📸 Berkas Foto Bukti Toko & KTP</h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                
                <!-- 1. Foto Depan Toko -->
                <div class="space-y-1">
                  <div class="h-6 flex items-center">
                    <span class="text-[13px] font-medium text-[#4B5563]">1. Foto Depan Toko</span>
                  </div>
                  <div v-if="activeModalSubmission.photo_depan_url" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = activeModalSubmission.photo_depan_url">
                    <img
                      :src="activeModalSubmission.photo_depan_url"
                      class="w-full h-44 object-cover rounded-[8px] border border-[#E5E7EB] pointer-events-none select-none shadow-xs"
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
                  <div v-else class="h-44 bg-[#F3F4F6] rounded-[8px] flex items-center justify-center text-[13px] text-[#9CA3AF] border border-[#E5E7EB]">Tidak ada foto depan</div>
                </div>

                <!-- 2. Foto Dalam Toko -->
                <div class="space-y-1">
                  <div class="h-6 flex items-center">
                    <span class="text-[13px] font-medium text-[#4B5563]">2. Foto Dalam Toko</span>
                  </div>
                  <div v-if="activeModalSubmission.photo_dalam_url" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = activeModalSubmission.photo_dalam_url">
                    <img
                      :src="activeModalSubmission.photo_dalam_url"
                      class="w-full h-44 object-cover rounded-[8px] border border-[#E5E7EB] pointer-events-none select-none shadow-xs"
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
                  <div v-else class="h-44 bg-[#F3F4F6] rounded-[8px] flex items-center justify-center text-[13px] text-[#9CA3AF] border border-[#E5E7EB]">Tidak ada foto dalam</div>
                </div>

                <!-- 3. Foto KTP Pemilik + STATUS REVISI KTP ✏️ (Header Sejajar Rata H-6) -->
                <div class="space-y-1">
                  <div class="h-6 flex items-center justify-between">
                    <span class="text-[13px] font-medium text-[#4B5563]">3. Foto KTP Pemilik</span>
                    <div class="flex items-center gap-1">
                      <button
                        v-if="!activeModalSubmission.is_ktp_revised && !isLocked"
                        @click="openKtpModal"
                        class="px-2 py-0.5 text-[11px] font-semibold text-[#1D4ED8] bg-[#DBEAFE] border border-[#93C5FD] rounded-[6px] hover:bg-[#BFDBFE] transition flex items-center gap-1 cursor-pointer"
                        title="Revisi Foto KTP Pemilik (Maks 1x)"
                      >
                        <span>✏️ Revisi</span>
                      </button>
                      
                      <!-- Status Badge Revisi Max (Rapi Single Row) -->
                      <span
                        v-else
                        class="px-1.5 py-0.5 text-[10px] font-semibold text-[#475569] bg-[#F1F5F9] border border-[#CBD5E1] rounded-[4px] cursor-default"
                        title="Foto KTP telah direvisi (Maksimal 1 kali)"
                      >
                        🔒 Revisi Max (1x)
                      </span>

                      <!-- BUTTON UNLOCK REVISI KTP SUPERADMIN -->
                      <button
                        v-if="activeModalSubmission.is_ktp_revised && (userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL')"
                        @click="handleUnlockKtpRevision"
                        class="px-1.5 py-0.5 text-[10px] font-semibold text-[#B45309] bg-[#FEF3C7] border border-[#FCD34D] rounded-[4px] hover:bg-[#FDE68A] transition flex items-center gap-0.5 cursor-pointer"
                        title="Superadmin: Unlock Akses Revisi KTP Toko"
                      >
                        <span>🔓 Unlock</span>
                      </button>
                    </div>
                  </div>

                  <div>
                    <div v-if="activeModalSubmission.photo_ktp_url" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = activeModalSubmission.photo_ktp_url">
                      <img
                        :src="activeModalSubmission.photo_ktp_url"
                        class="w-full h-44 object-cover rounded-[8px] border border-[#93C5FD] pointer-events-none select-none shadow-xs"
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
                    <div v-else class="h-44 bg-[#F3F4F6] rounded-[8px] flex items-center justify-center text-[13px] text-[#9CA3AF] border border-[#E5E7EB]">Tidak ada foto KTP</div>
                  </div>
                </div>

              </div>
            </div>

            <!-- SECTION 7: RUTING SPV AREA (HARI H1-H7 & MINGGU M1-M4 - PALING BAWAH - Point 1) -->
            <div class="space-y-3">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider border-b border-[#E5E7EB] pb-2">📅 Rute Kunjungan Salesman (Hari & Minggu Kunjungan)</h3>
              
              <div class="p-4 bg-[#F8FAFC] rounded-[10px] space-y-4 border border-[#E5E7EB]">
                <!-- HARI KUNJUNGAN (H1-H7) -->
                <div>
                  <span class="text-[13px] font-semibold text-[#4B5563] block mb-2">Hari Kunjungan Mingguan:</span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2 text-center text-[13px]">
                    <div
                      v-for="d in dayLabels"
                      :key="d.key"
                      :class="[
                        'p-2.5 rounded-[8px] border font-semibold transition flex flex-col items-center justify-center gap-0.5',
                        activeModalSubmission[d.key] === 'Y' || activeModalSubmission[d.key] === 'YES'
                          ? 'bg-[#16A34A] text-white border-[#16A34A] shadow-xs'
                          : 'bg-white text-[#9CA3AF] border-[#E5E7EB] opacity-60'
                      ]"
                    >
                      <span class="text-[11px] opacity-90">{{ d.name }}</span>
                      <span class="text-[14px]">
                        {{ activeModalSubmission[d.key] === 'Y' || activeModalSubmission[d.key] === 'YES' ? '✓ YA' : '✕ T' }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- MINGGU KUNJUNGAN (M1-M4) -->
                <div>
                  <span class="text-[13px] font-semibold text-[#4B5563] block mb-2">Minggu Kunjungan Bulanan:</span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-[13px]">
                    <div
                      v-for="w in weekLabels"
                      :key="w.key"
                      :class="[
                        'p-2.5 rounded-[8px] border font-semibold transition flex flex-col items-center justify-center gap-0.5',
                        activeModalSubmission[w.key] === 'Y' || activeModalSubmission[w.key] === 'YES'
                          ? 'bg-[#2563EB] text-white border-[#2563EB] shadow-xs'
                          : 'bg-white text-[#9CA3AF] border-[#E5E7EB] opacity-60'
                      ]"
                    >
                      <span class="text-[11px] opacity-90">{{ w.name }}</span>
                      <span class="text-[14px]">
                        {{ activeModalSubmission[w.key] === 'Y' || activeModalSubmission[w.key] === 'YES' ? '✓ YA' : '✕ T' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="p-4 bg-[#F9FAFB] border-t border-[#E5E7EB] flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
              <!-- BUTTON RESET APPROVAL EDP KEPUNYAAN SUPERADMIN & ADMIN PRINCIPAL -->
              <button
                v-if="(activeModalSubmission.status === 'APPROVED_EDP' || activeModalSubmission.status === 'REJECTED_EDP') && (userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL')"
                @click="handleResetEdpApproval"
                :disabled="isProcessingResetApproval"
                class="px-4 py-2.5 text-[15px] font-semibold text-[#B45309] bg-[#FEF3C7] border border-[#FCD34D] rounded-[8px] hover:bg-[#FDE68A] transition cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                title="Superadmin/Admin: Reset status approval EDP & hapus kode principal"
              >
                <svg v-if="isProcessingResetApproval" class="animate-spin h-4 w-4 text-[#B45309]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isProcessingResetApproval ? 'Memproses...' : '↩️ Reset Approval EDP & Kode Principal' }}</span>
              </button>

              <button
                v-else-if="activeModalSubmission.status === 'REJECTED_EDP' && (userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL')"
                @click="handleCancelRejection"
                :disabled="isProcessingCancelReject"
                class="px-4 py-2.5 text-[15px] font-semibold text-[#B45309] bg-[#FEF3C7] border border-[#FCD34D] rounded-[8px] hover:bg-[#FDE68A] transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <svg v-if="isProcessingCancelReject" class="animate-spin h-4 w-4 text-[#B45309]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isProcessingCancelReject ? 'Memproses...' : '↩️ Pembatalan Reject (Un-Reject)' }}</span>
              </button>
            </div>

            <div class="flex items-center gap-3">
              <!-- JIKA BELUM APPROVED ATAU REJECTED (TERBUKA UTK APPROVAL EDP) -->
              <template v-if="activeModalSubmission.status !== 'APPROVED_EDP' && activeModalSubmission.status !== 'REJECTED_EDP'">
                <!-- REJECT BUTTON -->
                <button
                  :disabled="isProcessingReject || isProcessingApprove"
                  @click="handleReject"
                  class="px-5 py-2.5 text-[15px] font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] rounded-[8px] shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="isProcessingReject" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isProcessingReject ? 'Memproses...' : '✖ Tolak (Reject EDP)' }}</span>
                </button>

                <!-- APPROVE BUTTON -->
                <button
                  :disabled="isProcessingApprove || isProcessingReject"
                  @click="handleApprove"
                  class="px-6 py-2.5 text-[15px] font-semibold text-white bg-[#16A34A] hover:bg-[#15803D] rounded-[8px] shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="isProcessingApprove" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isProcessingApprove ? 'Memproses...' : '✔ Setujui (Approved EDP)' }}</span>
                </button>
              </template>

              <!-- JIKA SUDAH APPROVED ATAU REJECTED (STATUS TERKUNCI) -->
              <template v-else>
                <div v-if="activeModalSubmission.status === 'APPROVED_EDP'" class="px-5 py-2.5 text-[14px] font-semibold text-[#15803D] bg-[#DCFCE7] border border-[#86EFAC] rounded-[8px] flex items-center gap-2 shadow-xs">
                  <span>🔒</span> Disetujui oleh EDP
                </div>
                <div v-else class="px-5 py-2.5 text-[14px] font-semibold text-[#B91C1C] bg-[#FEE2E2] border border-[#FCA5A5] rounded-[8px] flex items-center gap-2 shadow-xs">
                  <span>🔒</span> Ditolak oleh EDP
                </div>
              </template>
            </div>
          </div>

        </div>
      </div>
      </Teleport>

      <!-- KTP REVISION MODAL (DRAG & DROP LOCAL FILE PICKER - LEVEL 2 MODAL Z-INDEX 999999) -->
      <Teleport to="body">
        <div v-if="showKtpModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/70 z-[999999] flex items-center justify-center p-4 overflow-y-auto">
          <div class="bg-white rounded-[10px] max-w-md w-full p-6 space-y-4 shadow-2xl border border-[#E5E7EB] my-auto">
            <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
              <h3 class="text-[16px] font-semibold text-[#111827] flex items-center gap-2">
                <span>📷 Revisi Foto KTP Pemilik (Maks 1x)</span>
              </h3>
              <button @click="showKtpModal = false" class="text-[#6B7280] hover:text-[#111827] cursor-pointer">✕</button>
            </div>

            <form @submit.prevent="submitKtpRevision" class="space-y-4">
              <!-- DRAG & DROP ZONE -->
              <div
                @dragover.prevent
                @drop="handleKtpDrop"
                class="border-2 border-dashed border-[#3B82F6] bg-[#EFF6FF] rounded-[8px] p-6 text-center hover:bg-[#DBEAFE] transition cursor-pointer"
              >
                <input type="file" id="ktpFileSelect" @change="handleKtpFileSelect" accept="image/*" class="hidden" />
                <label for="ktpFileSelect" class="cursor-pointer space-y-2 block">
                  <span class="text-3xl block">📁</span>
                  <span class="text-[14px] font-semibold text-[#1D4ED8] block">Klik untuk memilih file foto KTP</span>
                  <span class="text-[12px] text-[#6B7280] block">atau drag & drop foto KTP di sini</span>
                </label>
              </div>

              <!-- PREVIEW FOTO KTP TERPILIH -->
              <div v-if="ktpPreviewUrl" class="space-y-1">
                <span class="text-[13px] font-medium text-[#4B5563]">Preview Foto Baru:</span>
                <img :src="ktpPreviewUrl" class="w-full h-36 object-cover rounded-[8px] border border-[#93C5FD]" />
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showKtpModal = false" class="px-4 py-2 text-[15px] font-semibold bg-white border border-[#D1D5DB] hover:bg-[#F3F4F6] text-[#374151] rounded-[8px]">Batal</button>
                <button type="submit" :disabled="!ktpFile" class="px-5 py-2 text-[15px] font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] rounded-[8px] shadow-sm cursor-pointer disabled:opacity-50">Upload Foto KTP</button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <!-- IMAGE PREVIEW MODAL FULL SCREEN VIA TELEPORT - LEVEL 3 MODAL Z-INDEX 9999999 -->
      <Teleport to="body">
        <div 
          v-if="previewImageModal" 
          class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/90 z-[9999999] flex items-center justify-center p-4 cursor-pointer"
          @click.self="previewImageModal = null"
        >
          <div class="relative max-w-5xl max-h-[92vh] flex items-center justify-center overflow-hidden rounded-[10px]" @click.stop>
            <!-- Single Red Circle Close Button Top-Right -->
            <button 
              @click="previewImageModal = null" 
              class="absolute top-2 right-2 w-9 h-9 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-full font-bold flex items-center justify-center shadow-lg border-2 border-white cursor-pointer z-30"
              title="Tutup (Esc)"
            >
              ✕
            </button>
            <img
              :src="previewImageModal"
              class="max-w-full max-h-[88vh] object-contain rounded-[10px] shadow-2xl border border-white/10 pointer-events-none select-none"
              draggable="false"
              oncontextmenu="return false;"
            />
            <!-- Watermark Security Overlay -->
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center overflow-hidden opacity-40 select-none z-20">
              <p class="text-[14px] font-black text-slate-800 uppercase tracking-widest -rotate-45 whitespace-nowrap drop-shadow-md">
                CONFIDENTIAL • NOO+ SYSTEM SECURITY WATERMARK • DO NOT COPY
              </p>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- REJECT EDP CONFIRMATION MODAL VIA TELEPORT - LEVEL 2 MODAL Z-INDEX 999999 -->
      <Teleport to="body">
        <div
          v-if="showRejectModal"
          class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/70 z-[999999] flex items-center justify-center p-4 backdrop-blur-xs overflow-y-auto"
          @click.self="showRejectModal = false"
        >
          <div class="bg-white rounded-[12px] max-w-lg w-full p-6 space-y-4 shadow-2xl border border-[#E5E7EB] relative my-auto">
            <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
              <h3 class="text-[16px] font-semibold text-[#DC2626] flex items-center gap-2">
                <span>❌ Alasan Penolakan NOO (Reject EDP)</span>
              </h3>
              <button @click="showRejectModal = false" class="text-[#6B7280] hover:text-[#111827] cursor-pointer">✕</button>
            </div>

            <div v-if="activeModalSubmission" class="bg-[#FEF2F2] p-3 rounded-[8px] border border-[#FCA5A5] text-[13px] text-[#991B1B]">
              <span class="font-semibold block">Toko: {{ activeModalSubmission.nama_noo }}</span>
              <span>Cabang: {{ activeModalSubmission.branch_id }} - {{ activeModalSubmission.branch_name }}</span>
            </div>

            <div class="space-y-1">
              <label class="block text-[14px] font-medium text-[#374151]">Tuliskan Alasan Penolakan EDP (Wajib):</label>
              <textarea
                v-model="rejectNotesInput"
                rows="4"
                placeholder="Masukkan alasan penolakan EDP Principal secara detail..."
                class="w-full text-[15px] p-3 border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] bg-white text-[#111827]"
              ></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button
                type="button"
                :disabled="isProcessingReject"
                @click="showRejectModal = false"
                class="px-4 py-2 text-[15px] font-semibold bg-white border border-[#D1D5DB] hover:bg-[#F3F4F6] text-[#374151] rounded-[8px] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Batal
              </button>
              <button
                type="button"
                :disabled="isProcessingReject"
                @click="submitReject"
                class="px-5 py-2 text-[15px] font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] rounded-[8px] shadow-sm cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="isProcessingReject" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isProcessingReject ? 'Memproses...' : 'Konfirmasi Tolak (Reject EDP)' }}</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- EXPORT APPROVED MODAL VIA TELEPORT - LEVEL 2 MODAL Z-INDEX 999999 -->
      <Teleport to="body">
        <div
          v-if="showExportModal"
          class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/70 z-[999999] flex items-center justify-center p-4 backdrop-blur-xs overflow-y-auto"
          @click.self="showExportModal = false"
        >
          <div class="bg-white rounded-[16px] max-w-4xl w-full p-6 space-y-5 shadow-2xl border border-[#E5E7EB] relative max-h-[90vh] overflow-y-auto my-auto">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-4">
              <div>
                <h3 class="text-[20px] font-bold text-[#111827] flex items-center gap-2">
                  <span>Export Approved NOO</span>
                </h3>
                <p class="text-[13px] text-[#6B7280] mt-0.5">Pilih range tanggal berdasarkan NOO Approved</p>
              </div>
              <button @click="showExportModal = false" class="w-8 h-8 rounded-full bg-[#F3F4F6] hover:bg-[#E5E7EB] text-[#4B5563] flex items-center justify-center font-bold cursor-pointer">✕</button>
            </div>

            <!-- Filter Card -->
            <div class="bg-[#F8FAFC] p-4 rounded-[12px] border border-[#E5E7EB] space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                  <label class="block text-[13px] font-semibold text-[#4B5563] mb-1">Dari Tanggal</label>
                  <input
                    type="date"
                    v-model="exportStartDate"
                    @change="onExportDateChange"
                    @click="triggerDatePicker"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB] cursor-pointer"
                  />
                </div>
                <div>
                  <label class="block text-[13px] font-semibold text-[#4B5563] mb-1">Sampai Tanggal</label>
                  <input
                    type="date"
                    v-model="exportEndDate"
                    @change="onExportDateChange"
                    @click="triggerDatePicker"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB] cursor-pointer"
                  />
                </div>
                <div>
                  <label class="block text-[13px] font-semibold text-[#4B5563] mb-1">Filter Distributor</label>
                  <select
                    v-model="exportBranch"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB]"
                  >
                    <option value="" disabled selected>-- Pilih Distributor --</option>
                    <optgroup
                      v-for="(group, regionKey) in groupedExportBranches"
                      :key="regionKey"
                      :label="group.label"
                      class="font-bold text-[#374151] bg-[#E5E7EB]"
                    >
                      <option
                        v-for="b in group.branches"
                        :key="b.branch_id"
                        :value="b.branch_id"
                        class="font-normal text-[#111827] bg-white pl-4"
                      >
                        {{ b.branch_id }} - {{ b.branch_name }}
                      </option>
                    </optgroup>
                  </select>
                </div>
                <div>
                  <button
                    @click="fetchExportData"
                    :disabled="!exportBranch || isLoadingExportData"
                    class="w-full py-2 px-4 text-[14px] font-semibold text-white bg-[#111827] hover:bg-[#1F2937] disabled:bg-[#9CA3AF] disabled:cursor-not-allowed rounded-[8px] transition cursor-pointer flex items-center justify-center gap-1.5"
                  >
                    <span v-if="isLoadingExportData" class="animate-pulse">Memuat...</span>
                    <span v-else>Tampilkan Data</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Table Result Section -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[13px] text-[#4B5563] font-medium">
                <span>{{ exportSubmissions.length }} data Approved untuk {{ exportBranch ? exportBranch : 'distributor' }}.</span>
                <span v-if="isLoadingExportData" class="text-[#2563EB] animate-pulse">Memuat data...</span>
              </div>

              <div class="border border-[#E5E7EB] rounded-[10px] overflow-x-auto max-h-72 overflow-y-auto">
                <table class="w-full text-[13px] text-left text-[#374151]">
                  <thead class="bg-[#F3F4F6] border-b border-[#E5E7EB] font-semibold text-[#111827] sticky top-0 bg-[#F3F4F6]">
                    <tr>
                      <th class="p-3 w-10 text-center whitespace-nowrap">
                        <input
                          type="checkbox"
                          :checked="isAllExportSelected"
                          @change="toggleSelectAllExport"
                          class="w-4 h-4 text-[#2563EB] rounded focus:ring-[#2563EB] cursor-pointer"
                        />
                      </th>
                      <th class="p-3 whitespace-nowrap">BRANCH_ID</th>
                      <th class="p-3 whitespace-nowrap">BRANCH_NAME</th>
                      <th class="p-3 whitespace-nowrap">CODE_NOO_PRINCIPAL</th>
                      <th class="p-3 whitespace-nowrap">CUSTCODE_DISTRIBUTOR</th>
                      <th class="p-3 whitespace-nowrap">NAMA_NOO</th>
                      <th class="p-3 whitespace-nowrap">EDP_DECISION</th>
                      <th class="p-3 whitespace-nowrap">SUBMITTED_AT</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!exportBranch">
                      <td colspan="8" class="p-8 text-center text-[#4B5563]">
                        <span class="text-[14px]">🏬 Silakan pilih <strong>Distributor</strong> terlebih dahulu, lalu klik <strong>Tampilkan Data</strong>.</span>
                      </td>
                    </tr>
                    <tr v-else-if="!hasFetchedExport">
                      <td colspan="8" class="p-8 text-center text-[#4B5563]">
                        <span class="text-[14px]">📅 Klik <strong>Tampilkan Data</strong> untuk memuat list NOO Approved distributor <strong>{{ exportBranch }}</strong>.</span>
                      </td>
                    </tr>
                    <tr v-else-if="exportSubmissions.length === 0">
                      <td colspan="8" class="p-6 text-center text-[#9CA3AF]">Tidak ada data NOO Approved pada filter ini.</td>
                    </tr>
                    <tr
                      v-for="sub in exportSubmissions"
                      :key="sub.request_id"
                      class="border-b border-[#E5E7EB] hover:bg-[#F9FAFB] transition"
                    >
                      <td class="p-3 text-center whitespace-nowrap">
                        <input
                          type="checkbox"
                          v-model="selectedExportIds"
                          :value="sub.request_id"
                          class="w-4 h-4 text-[#2563EB] rounded focus:ring-[#2563EB] cursor-pointer"
                        />
                      </td>
                      <td class="p-3 font-semibold text-[#111827] whitespace-nowrap">{{ sub.branch_id }}</td>
                      <td class="p-3 text-[#4B5563] whitespace-nowrap">{{ sub.branch_name }}</td>
                      <td class="p-3 font-mono font-bold text-[#1D4ED8] whitespace-nowrap">{{ sub.code_noo_principal || '-' }}</td>
                      <td class="p-3 font-mono text-[#374151] whitespace-nowrap">{{ sub.custcode_distributor }}</td>
                      <td class="p-3 font-semibold text-[#111827] whitespace-nowrap">{{ sub.nama_noo }}</td>
                      <td class="p-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 text-[11px] font-semibold text-[#15803D] bg-[#DCFCE7] rounded-full">
                          {{ sub.edp_decision }}
                        </span>
                      </td>
                      <td class="p-3 text-[#6B7280] whitespace-nowrap">{{ sub.submitted_at ? new Date(sub.submitted_at).toLocaleString('id-ID') : '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-[#E5E7EB]">
              <div class="text-[13px] text-[#6B7280] font-medium">
                <span>{{ selectedExportIds.length }} data dipilih</span>
              </div>
              <button
                @click="submitExportSelected"
                :disabled="selectedExportIds.length === 0 || isExportingExcel"
                class="px-5 py-2.5 text-[15px] font-semibold text-white bg-[#10B981] hover:bg-[#059669] disabled:bg-[#9CA3AF] disabled:cursor-not-allowed rounded-[8px] shadow-sm transition flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="isExportingExcel" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <span>Mengunduh File Excel...</span>
                </span>
                <span v-else>Export Selected to Excel</span>
              </button>
            </div>

          </div>
        </div>
      </Teleport>

      <!-- EXPORT REJECTED MODAL VIA TELEPORT - LEVEL 2 MODAL Z-INDEX 999999 -->
      <Teleport to="body">
        <div
          v-if="showExportRejectedModal"
          class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/70 z-[999999] flex items-center justify-center p-4 backdrop-blur-xs overflow-y-auto"
          @click.self="showExportRejectedModal = false"
        >
          <div class="bg-white rounded-[16px] max-w-4xl w-full p-6 space-y-5 shadow-2xl border border-[#E5E7EB] relative max-h-[90vh] overflow-y-auto my-auto">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-4">
              <div>
                <h3 class="text-[20px] font-bold text-[#111827] flex items-center gap-2">
                  <span>Export Rejected NOO</span>
                </h3>
                <p class="text-[13px] text-[#6B7280] mt-0.5">Pilih range tanggal berdasarkan NOO Ditolak (edp_reviewed_at & edp_decision = REJECTED)</p>
              </div>
              <button @click="showExportRejectedModal = false" class="w-8 h-8 rounded-full bg-[#F3F4F6] hover:bg-[#E5E7EB] text-[#4B5563] flex items-center justify-center font-bold cursor-pointer">✕</button>
            </div>

            <!-- Filter Card -->
            <div class="bg-[#F8FAFC] p-4 rounded-[12px] border border-[#E5E7EB] space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                  <label class="block text-[13px] font-semibold text-[#4B5563] mb-1">Dari Tanggal</label>
                  <input
                    type="date"
                    v-model="exportRejectedStartDate"
                    @click="triggerDatePicker"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#EF4444] cursor-pointer"
                  />
                </div>
                <div>
                  <label class="block text-[13px] font-semibold text-[#4B5563] mb-1">Sampai Tanggal</label>
                  <input
                    type="date"
                    v-model="exportRejectedEndDate"
                    @click="triggerDatePicker"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#EF4444] cursor-pointer"
                  />
                </div>
                <div>
                  <button
                    @click="fetchExportRejectedData"
                    :disabled="isLoadingExportRejectedData"
                    class="w-full py-2 px-4 text-[14px] font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] disabled:bg-[#9CA3AF] disabled:cursor-not-allowed rounded-[8px] transition cursor-pointer flex items-center justify-center gap-1.5"
                  >
                    <span v-if="isLoadingExportRejectedData" class="animate-pulse">Memuat...</span>
                    <span v-else>Tampilkan Data</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Table Result Section -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[13px] text-[#4B5563] font-medium">
                <span>{{ exportRejectedSubmissions.length }} data Rejected ditemukan.</span>
                <span v-if="isLoadingExportRejectedData" class="text-[#EF4444] animate-pulse">Memuat data...</span>
              </div>

              <div class="border border-[#E5E7EB] rounded-[10px] overflow-x-auto max-h-72 overflow-y-auto">
                <table class="w-full text-[13px] text-left text-[#374151]">
                  <thead class="bg-[#F3F4F6] border-b border-[#E5E7EB] font-semibold text-[#111827] sticky top-0 bg-[#F3F4F6]">
                    <tr>
                      <th class="p-3 w-10 text-center whitespace-nowrap">
                        <input
                          type="checkbox"
                          :checked="isAllExportRejectedSelected"
                          @change="toggleSelectAllExportRejected"
                          class="w-4 h-4 text-[#EF4444] rounded focus:ring-[#EF4444] cursor-pointer"
                        />
                      </th>
                      <th class="p-3 whitespace-nowrap">BRANCH_ID</th>
                      <th class="p-3 whitespace-nowrap">NAMA_NOO</th>
                      <th class="p-3 whitespace-nowrap">ALAMAT_NOO</th>
                      <th class="p-3 whitespace-nowrap">ALASAN REJECT EDP</th>
                      <th class="p-3 whitespace-nowrap">EDP_DECISION</th>
                      <th class="p-3 whitespace-nowrap">SUBMITTED_AT</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!hasFetchedExportRejected">
                      <td colspan="7" class="p-8 text-center text-[#4B5563]">
                        <span class="text-[14px]">📅 Silakan tentukan <strong>Tanggal Range</strong>, lalu klik <strong>Tampilkan Data</strong> untuk memuat list NOO Rejected.</span>
                      </td>
                    </tr>
                    <tr v-else-if="exportRejectedSubmissions.length === 0">
                      <td colspan="7" class="p-6 text-center text-[#9CA3AF]">Tidak ada data NOO Rejected pada filter ini.</td>
                    </tr>
                    <tr
                      v-for="sub in exportRejectedSubmissions"
                      :key="sub.request_id"
                      class="border-b border-[#E5E7EB] hover:bg-[#FEF2F2] transition"
                    >
                      <td class="p-3 text-center whitespace-nowrap">
                        <input
                          type="checkbox"
                          v-model="selectedExportRejectedIds"
                          :value="sub.request_id"
                          class="w-4 h-4 text-[#EF4444] rounded focus:ring-[#EF4444] cursor-pointer"
                        />
                      </td>
                      <td class="p-3 font-semibold text-[#111827] whitespace-nowrap">{{ sub.branch_id }}</td>
                      <td class="p-3 font-semibold text-[#111827] whitespace-nowrap">{{ sub.nama_noo }}</td>
                      <td class="p-3 text-[#4B5563] text-[12px] max-w-xs truncate whitespace-nowrap">{{ sub.alamat_noo || '-' }}</td>
                      <td class="p-3 font-medium text-[#B91C1C] max-w-xs truncate whitespace-nowrap">{{ sub.edp_notes || '-' }}</td>
                      <td class="p-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 text-[11px] font-semibold text-[#991B1B] bg-[#FEE2E2] rounded-full">
                          {{ sub.edp_decision }}
                        </span>
                      </td>
                      <td class="p-3 text-[#6B7280] whitespace-nowrap">{{ sub.submitted_at ? new Date(sub.submitted_at).toLocaleString('id-ID') : '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-[#E5E7EB]">
              <div class="text-[13px] text-[#6B7280] font-medium">
                <span>{{ selectedExportRejectedIds.length }} data dipilih</span>
              </div>
              <button
                @click="submitExportRejectedSelected"
                :disabled="selectedExportRejectedIds.length === 0 || isExportingRejectedExcel"
                class="px-5 py-2.5 text-[15px] font-semibold text-white bg-[#DC2626] hover:bg-[#B91C1C] disabled:bg-[#9CA3AF] disabled:cursor-not-allowed rounded-[8px] shadow-sm transition flex items-center gap-1.5 cursor-pointer"
              >
                <span v-if="isExportingRejectedExcel" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <span>Mengunduh File Excel...</span>
                </span>
                <span v-else>Export Rejected to Excel</span>
              </button>
            </div>

          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>
