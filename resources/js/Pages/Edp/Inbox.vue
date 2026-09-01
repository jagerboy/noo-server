<script setup lang="js">
/**
 * Komponen NOO Verification (Halaman Inbox & Review Detail NOO).
 * Implementasi Penyempurnaan Tambahan:
 * 1. Section Rute Kunjungan Salesman & Berkas Foto Bukti Toko/KTP diletakkan di paling bawah modal.
 * 2. Filter Branch mengikuti pilihan Filter Region (Filter terisolasi secara dinamis & auto-reset branch).
 * 3. Revisi Foto KTP Pemilik dibatasi Maksimal 1x (Button ter-disable dengan badge 🔒 Revisi Max (1x)).
 * 4. Refresh instan Foto KTP dengan Timestamp Cache-Buster.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import EdpLayout from '@/Layouts/EdpLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';

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
const selectedEdpMonths = ref(
  props.filters?.edp_months
    ? String(props.filters.edp_months).split(',')
    : (props.filters?.edp_month ? [String(props.filters.edp_month)] : [])
);
const selectedEdpYear = ref(props.filters?.edp_year ? String(props.filters.edp_year) : '');
const search = ref(props.filters?.search || '');

const isMonthDropdownOpen = ref(false);
const monthDropdownRef = ref(null);

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
const isUploadingKtp = ref(false);

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

// Modal Filter Draft State (Only commits when user clicks Terapkan Filter & Sort)
const modalRegion = ref(props.filters?.region_code || '');
const modalPrincipal = ref(props.filters?.principal || '');
const modalBranch = ref(props.filters?.branch_id || '');
const modalStatus = ref(props.filters?.status || '');
const modalEdpMonths = ref(
  props.filters?.edp_months
    ? String(props.filters.edp_months).split(',')
    : (props.filters?.edp_month ? [String(props.filters.edp_month)] : [])
);
const modalEdpYear = ref(props.filters?.edp_year ? String(props.filters.edp_year) : '');

function openFilterModal() {
  modalRegion.value = selectedRegion.value;
  modalPrincipal.value = selectedPrincipal.value;
  modalBranch.value = selectedBranch.value;
  modalStatus.value = selectedStatus.value;
  modalEdpMonths.value = [...selectedEdpMonths.value];
  modalEdpYear.value = selectedEdpYear.value;
  showFilterModal.value = true;
}

const entityOptionsModal = computed(() => {
  let list = props.filterOptions?.entities || [];
  if (modalRegion.value) {
    list = list.filter((e) => e.region_code === modalRegion.value);
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

const branchOptionsModal = computed(() => {
  let list = props.filterOptions?.branches || [];
  if (modalRegion.value) {
    list = list.filter((b) => (b.region_code || b.value) === modalRegion.value);
  }
  if (modalPrincipal.value) {
    list = list.filter((b) => (b.entity_code_principal || b.entity_name_principal) === modalPrincipal.value);
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

function onModalRegionChange() {
  if (modalRegion.value) {
    const isValidEntity = entityOptionsModal.value.some((e) => e.value === modalPrincipal.value);
    if (!isValidEntity) {
      modalPrincipal.value = '';
    }
    const isValidBranch = branchOptionsModal.value.some((b) => b.value === modalBranch.value);
    if (!isValidBranch) {
      modalBranch.value = '';
    }
  }
  // Data is NOT loaded here!
}

function onModalPrincipalChange() {
  if (modalPrincipal.value) {
    const isValidBranch = branchOptionsModal.value.some((b) => b.value === modalBranch.value);
    if (!isValidBranch) {
      modalBranch.value = '';
    }
  }
  // Data is NOT loaded here!
}

const statusOptions = [
  { value: 'SE_SUBMITTED', label: '1. Pending Admin' },
  { value: 'PUSHED_TO_SPV', label: '2. Pushed to SPV (Pending SPV)' },
  { value: 'APPROVED_SPV', label: '3. Approved SPV (Pending EDP)' },
  { value: 'APPROVED_EDP', label: '4. Approved EDP (Final/Completed)' },
  { value: 'REJECTED_ADMIN', label: '5. Ditolak Admin Distributor' },
  { value: 'REJECTED_SPV', label: '6. Ditolak SPV Area' },
  { value: 'REJECTED_EDP', label: '7. Ditolak EDP Principal' },
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

const edpMonthOptions = [
  { value: '1', label: 'Januari', short: 'Jan' },
  { value: '2', label: 'Februari', short: 'Feb' },
  { value: '3', label: 'Maret', short: 'Mar' },
  { value: '4', label: 'April', short: 'Apr' },
  { value: '5', label: 'Mei', short: 'Mei' },
  { value: '6', label: 'Juni', short: 'Jun' },
  { value: '7', label: 'Juli', short: 'Jul' },
  { value: '8', label: 'Agustus', short: 'Agu' },
  { value: '9', label: 'September', short: 'Sep' },
  { value: '10', label: 'Oktober', short: 'Okt' },
  { value: '11', label: 'November', short: 'Nov' },
  { value: '12', label: 'Desember', short: 'Des' },
];

function toggleSelectAllMonths() {
  if (selectedEdpMonths.value.length === 12) {
    selectedEdpMonths.value = [];
  } else {
    selectedEdpMonths.value = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  }
}

function getPhotoUrl(urlOrPath) {
  if (!urlOrPath) return null;
  if (urlOrPath.startsWith('http://') || urlOrPath.startsWith('https://') || urlOrPath.startsWith('blob:') || urlOrPath.startsWith('data:')) return urlOrPath;
  let clean = urlOrPath.replace(/^\/+/, '');
  if (clean.startsWith('public/')) clean = clean.substring(7);
  if (clean.startsWith('storage/')) clean = clean.substring(8);
  if (clean.startsWith('media-photo/')) clean = clean.substring(12);
  return `/media-photo/${clean}`;
}

function selectQuarter(q) {
  if (q === 1) selectedEdpMonths.value = ['1', '2', '3'];
  else if (q === 2) selectedEdpMonths.value = ['4', '5', '6'];
  else if (q === 3) selectedEdpMonths.value = ['7', '8', '9'];
  else if (q === 4) selectedEdpMonths.value = ['10', '11', '12'];
}

function selectSemester(s) {
  if (s === 1) selectedEdpMonths.value = ['1', '2', '3', '4', '5', '6'];
  else if (s === 2) selectedEdpMonths.value = ['7', '8', '9', '10', '11', '12'];
}

const selectedEdpMonthsLabel = computed(() => {
  const len = selectedEdpMonths.value.length;
  if (len === 0) return '-- Semua Bulan Approval --';
  if (len === 12) return 'Semua Bulan (12 Bulan)';
  if (len === 1) {
    const found = edpMonthOptions.find((m) => m.value === selectedEdpMonths.value[0]);
    return found ? found.label : '1 Bulan';
  }
  const shorts = edpMonthOptions
    .filter((m) => selectedEdpMonths.value.includes(m.value))
    .map((m) => m.short)
    .join(', ');
  return `${len} Bulan (${shorts})`;
});

function handleClickOutsideMonth(event) {
  if (monthDropdownRef.value && !monthDropdownRef.value.contains(event.target)) {
    isMonthDropdownOpen.value = false;
  }
}

function handleEscKeydown(e) {
  if (e.key === 'Escape') {
    if (previewImageModal.value) {
      previewImageModal.value = null;
      return;
    }
    if (showKtpModal.value) {
      showKtpModal.value = false;
      return;
    }
    if (showFilterModal.value) {
      showFilterModal.value = false;
      return;
    }
    if (showExportModal.value) {
      showExportModal.value = false;
      return;
    }
    if (showExportRejectedModal.value) {
      showExportRejectedModal.value = false;
      return;
    }
    if (activeModalSubmission.value) {
      activeModalSubmission.value = null;
      return;
    }
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutsideMonth);
  window.addEventListener('keydown', handleEscKeydown);
});
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutsideMonth);
  window.removeEventListener('keydown', handleEscKeydown);
});

const edpYearSelectOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const yearsFromProps = props.filterOptions?.edpYears || [];
  const years = yearsFromProps.length > 0 ? yearsFromProps : [currentYear, currentYear - 1, currentYear - 2];

  return years.map((y) => ({
    value: String(y),
    label: String(y),
  }));
});

const showFilterModal = ref(false);
const isLoadingFilters = ref(false);

const activeFilterCount = computed(() => {
  let count = 0;
  if (selectedRegion.value) count++;
  if (selectedPrincipal.value) count++;
  if (selectedBranch.value) count++;
  if (selectedStatus.value) count++;
  if (selectedEdpMonths.value.length > 0) count++;
  if (selectedEdpYear.value) count++;
  if (search.value) count++;
  return count;
});

const activeFilterChips = computed(() => {
  const chips = [];
  if (selectedRegion.value) {
    const found = regionOptions.value.find((r) => r.value === selectedRegion.value);
    chips.push({ key: 'region', label: `Region: ${found ? found.label : selectedRegion.value}` });
  }
  if (selectedPrincipal.value) {
    const found = entityOptions.value.find((e) => e.value === selectedPrincipal.value);
    chips.push({ key: 'principal', label: `Entity: ${found ? found.label : selectedPrincipal.value}` });
  }
  if (selectedBranch.value) {
    const found = branchOptions.value.find((b) => b.value === selectedBranch.value);
    chips.push({ key: 'branch', label: `Cabang: ${found ? found.label : selectedBranch.value}` });
  }
  if (selectedStatus.value) {
    if (selectedStatus.value === 'PENDING_EDP') {
      chips.push({ key: 'status', label: 'Status Principal: ⏳ Belum Diproses' });
    } else if (selectedStatus.value === 'APPROVED_EDP') {
      chips.push({ key: 'status', label: 'Status Principal: ✔ Disetujui' });
    } else if (selectedStatus.value === 'REJECTED_EDP') {
      chips.push({ key: 'status', label: 'Status Principal: ✖ Ditolak' });
    } else {
      const found = statusOptions.find((s) => s.value === selectedStatus.value);
      chips.push({ key: 'status', label: `Status: ${found ? found.label : selectedStatus.value}` });
    }
  }
  if (selectedEdpMonths.value && selectedEdpMonths.value.length > 0) {
    chips.push({ key: 'months', label: `Bulan: ${selectedEdpMonths.value.length} bulan terpilih` });
  }
  if (selectedEdpYear.value) {
    chips.push({ key: 'year', label: `Tahun: ${selectedEdpYear.value}` });
  }
  if (search.value) {
    chips.push({ key: 'search', label: `Pencarian: "${search.value}"` });
  }
  return chips;
});

function removeChip(key) {
  if (key === 'region') selectedRegion.value = '';
  if (key === 'principal') selectedPrincipal.value = '';
  if (key === 'branch') selectedBranch.value = '';
  if (key === 'status') selectedStatus.value = '';
  if (key === 'months') selectedEdpMonths.value = [];
  if (key === 'year') selectedEdpYear.value = '';
  if (key === 'search') search.value = '';
  applyFilters();
}

function applyFilters() {
  const queryParams = {};
  if (selectedRegion.value) queryParams.region_code = selectedRegion.value;
  if (selectedPrincipal.value) queryParams.principal = selectedPrincipal.value;
  if (selectedBranch.value) queryParams.branch_id = selectedBranch.value;
  if (selectedStatus.value) queryParams.status = selectedStatus.value;
  if (selectedEdpMonths.value && selectedEdpMonths.value.length > 0) {
    queryParams.edp_months = selectedEdpMonths.value.join(',');
  }
  if (selectedEdpYear.value) queryParams.edp_year = selectedEdpYear.value;
  if (search.value) queryParams.search = search.value;

  router.get(
    route('edp.inbox'),
    queryParams,
    {
      preserveScroll: true,
      replace: true,
      onStart: () => {
        isLoadingFilters.value = true;
      },
      onFinish: () => {
        isLoadingFilters.value = false;
      },
    }
  );
}

function applyFilterModal() {
  selectedRegion.value = modalRegion.value;
  selectedPrincipal.value = modalPrincipal.value;
  selectedBranch.value = modalBranch.value;
  selectedStatus.value = modalStatus.value;
  selectedEdpMonths.value = [...modalEdpMonths.value];
  selectedEdpYear.value = modalEdpYear.value;
  showFilterModal.value = false;
  applyFilters();
}

function resetFilters() {
  selectedRegion.value = '';
  selectedPrincipal.value = '';
  selectedBranch.value = '';
  selectedStatus.value = '';
  selectedEdpMonths.value = [];
  selectedEdpYear.value = '';
  search.value = '';
  modalRegion.value = '';
  modalPrincipal.value = '';
  modalBranch.value = '';
  modalStatus.value = '';
  modalEdpMonths.value = [];
  modalEdpYear.value = '';
  showFilterModal.value = false;
  applyFilters();
}

function getRowStyle(sub) {
  const isSelected = selectedRowIds.value.includes(sub.request_id);
  if (isSelected) {
    return 'bg-blue-50/90 hover:bg-blue-100/90 text-slate-900 font-medium';
  }

  const st = sub.status;
  if (['APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'].includes(st)) {
    return 'bg-emerald-50/50 hover:bg-emerald-100/60 text-slate-900';
  }
  if (['REJECTED_EDP', 'EDP_REJECTED', 'REJECTED_SPV', 'REJECTED_ADMIN'].includes(st)) {
    return 'bg-rose-50/50 hover:bg-rose-100/60 text-slate-900';
  }
  // Pending / Belum Approval
  return 'bg-white hover:bg-slate-50 text-slate-900';
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

function getActiveQueryParams() {
  const queryParams = {};
  if (selectedRegion.value) queryParams.region_code = selectedRegion.value;
  if (selectedPrincipal.value) queryParams.principal = selectedPrincipal.value;
  if (selectedBranch.value) queryParams.branch_id = selectedBranch.value;
  if (selectedStatus.value) queryParams.status = selectedStatus.value;
  if (selectedEdpMonths.value && selectedEdpMonths.value.length > 0) {
    queryParams.edp_months = selectedEdpMonths.value.join(',');
  }
  if (selectedEdpYear.value) queryParams.edp_year = selectedEdpYear.value;
  if (search.value) queryParams.search = search.value;
  return queryParams;
}

function saveStoreName() {
  if (!activeModalSubmission.value || !editedStoreName.value) return;
  router.post(
    route('edp.update_store_name', getActiveQueryParams()),
    {
      request_id: activeModalSubmission.value.request_id,
      nama_noo: editedStoreName.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
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
    route('edp.update_store_address', getActiveQueryParams()),
    {
      request_id: activeModalSubmission.value.request_id,
      alamat_noo: cleanAddr,
    },
    {
      preserveState: true,
      preserveScroll: true,
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
    route('edp.approve', getActiveQueryParams()),
    {
      request_id: currentReqId,
      edp_notes: edpNotesInput.value,
    },
    {
      preserveState: true,
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

const isProcessingToggleRo = ref(false);

function handleToggleRoStatus(newStatus) {
  if (!activeModalSubmission.value || isProcessingToggleRo.value) return;
  const currentReqId = activeModalSubmission.value.request_id;

  router.post(
    route('edp.toggle_ro_status', getActiveQueryParams()),
    {
      request_id: currentReqId,
      is_ro: newStatus,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onStart: () => {
        isProcessingToggleRo.value = true;
      },
      onFinish: () => {
        isProcessingToggleRo.value = false;
      },
      onSuccess: () => {
        if (activeModalSubmission.value) {
          activeModalSubmission.value.is_ro = newStatus;
        }
      },
    }
  );
}

const selectedRowIds = ref([]);
const isProcessingBulkToggleRo = ref(false);

const eligibleSubmissions = computed(() => {
  return (sortedSubmissions.value || []).filter((s) =>
    ['APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'].includes(s.status)
  );
});

const isAllSelected = computed(() => {
  if (!eligibleSubmissions.value.length) return false;
  return selectedRowIds.value.length === eligibleSubmissions.value.length;
});

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedRowIds.value = [];
  } else {
    selectedRowIds.value = eligibleSubmissions.value.map((s) => s.request_id);
  }
}

function handleToggleRoStatusRow(sub, newStatus) {
  if (!sub || isProcessingToggleRo.value) return;

  router.post(
    route('edp.toggle_ro_status', getActiveQueryParams()),
    {
      request_id: sub.request_id,
      is_ro: newStatus,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onStart: () => {
        isProcessingToggleRo.value = true;
      },
      onFinish: () => {
        isProcessingToggleRo.value = false;
      },
      onSuccess: () => {
        sub.is_ro = newStatus;
        if (activeModalSubmission.value && activeModalSubmission.value.request_id === sub.request_id) {
          activeModalSubmission.value.is_ro = newStatus;
        }
      },
    }
  );
}

function handleBulkToggleRoStatus(newStatus) {
  if (!selectedRowIds.value.length || isProcessingBulkToggleRo.value) return;

  const actionName = newStatus ? 'Mengaktifkan' : 'Menonaktifkan';
  if (!confirm(`Apakah Anda yakin ingin ${actionName} status Registered Outlet (RO) untuk ${selectedRowIds.value.length} toko terpilih?`)) {
    return;
  }

  router.post(
    route('edp.bulk_toggle_ro_status', getActiveQueryParams()),
    {
      request_ids: selectedRowIds.value,
      is_ro: newStatus,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onStart: () => {
        isProcessingBulkToggleRo.value = true;
      },
      onFinish: () => {
        isProcessingBulkToggleRo.value = false;
      },
      onSuccess: () => {
        const ids = [...selectedRowIds.value];
        const list = sortedSubmissions.value;
        list.forEach((sub) => {
          if (ids.includes(sub.request_id)) {
            sub.is_ro = newStatus;
          }
        });
        selectedRowIds.value = [];
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
const isLoadingExportBranches = ref(false);
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
    route('edp.reject', getActiveQueryParams()),
    {
      request_id: currentReqId,
      reject_reason: reason,
      edp_notes: reason,
    },
    {
      preserveState: true,
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

function onExportBranchChange() {
  if (exportBranch.value) {
    fetchExportData();
  } else {
    exportSubmissions.value = [];
    selectedExportIds.value = [];
    hasFetchedExport.value = false;
  }
}

async function fetchExportBranches() {
  if (!exportStartDate.value || !exportEndDate.value) return;
  isLoadingExportBranches.value = true;
  try {
    const params = new URLSearchParams();
    params.append('start_date', exportStartDate.value);
    params.append('end_date', exportEndDate.value);

    const res = await fetch(route('edp.export_approved_data') + '?' + params.toString());
    const data = await res.json();
    exportBranches.value = data.branches || [];

    // Auto-reset branch if currently selected branch is not in the filtered branches for this date range
    if (exportBranch.value && !exportBranches.value.some((b) => b.branch_id === exportBranch.value)) {
      exportBranch.value = '';
      exportSubmissions.value = [];
      selectedExportIds.value = [];
      hasFetchedExport.value = false;
    }
  } catch (e) {
    console.error('Gagal mengambil daftar distributor:', e);
  } finally {
    isLoadingExportBranches.value = false;
  }
}

async function fetchExportData() {
  if (!exportBranch.value) return;

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
    route('edp.cancel_rejection', getActiveQueryParams()),
    {
      request_id: currentReqId,
    },
    {
      preserveState: true,
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
  if (!ktpFile.value || !activeModalSubmission.value || isUploadingKtp.value) return;
  const currentReqId = activeModalSubmission.value.request_id;

  const formData = new FormData();
  formData.append('request_id', currentReqId);
  formData.append('photo_ktp', ktpFile.value);

  router.post(route('edp.revise_ktp', getActiveQueryParams()), formData, {
    preserveState: true,
    preserveScroll: true,
    onStart: () => {
      isUploadingKtp.value = true;
    },
    onFinish: () => {
      isUploadingKtp.value = false;
    },
    onError: () => {
      // Jika terjadi error dari server, jangan kunci button dan jangan update foto
    },
    onSuccess: (page) => {
      showKtpModal.value = false;
      const ts = Date.now();
      const rawList = page?.props?.submissions?.data || page?.props?.submissions || props.submissions?.data || props.submissions || [];
      const updatedList = Array.isArray(rawList) ? rawList : [];
      const updatedSub = updatedList.find((s) => s.request_id === currentReqId);

      if (updatedSub) {
        const freshUrl = updatedSub.photo_ktp_url
          ? (updatedSub.photo_ktp_url.includes('?') ? `${updatedSub.photo_ktp_url}&t=${ts}` : `${updatedSub.photo_ktp_url}?t=${ts}`)
          : (ktpPreviewUrl.value || null);
        
        activeModalSubmission.value = {
          ...activeModalSubmission.value,
          ...updatedSub,
          photo_ktp_path: updatedSub.photo_ktp_path || activeModalSubmission.value.photo_ktp_path,
          photo_ktp_url: freshUrl,
          is_ktp_revised: true,
        };
      }
      ktpFile.value = null;
      ktpPreviewUrl.value = null;
    },
  });
}

function handleUnlockKtpRevision() {
  if (!activeModalSubmission.value) return;
  if (!confirm('Apakah Anda yakin ingin membuka kembali kunci revisi KTP toko ini (Superadmin)?')) return;

  router.post(
    route('edp.reset_ktp_revision', getActiveQueryParams()),
    {
      request_id: activeModalSubmission.value.request_id,
    },
    {
      preserveState: true,
      preserveScroll: true,
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
    route('edp.reset_edp_approval', getActiveQueryParams()),
    {
      request_id: currentReqId,
    },
    {
      preserveState: true,
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
          activeModalSubmission.value.previous_code_noo_principal = activeModalSubmission.value.code_noo_principal || activeModalSubmission.value.previous_code_noo_principal;
          activeModalSubmission.value.code_noo_principal = null;
        }
        closeDetailModal();
      },
    }
  );
}

const sortKey = ref('created_at');
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
  const list = [...(props.submissions?.data || props.submissions || [])];
  if (!sortKey.value) return list;

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
          <h1 class="text-xl md:text-[24px] font-semibold text-[#111827] tracking-tight leading-[1.4] flex items-center gap-3">
            <span>NOO Verification</span>
          </h1>
          <p class="text-[14px] text-[#6B7280] leading-[1.5]">
            Verifikasi Final EDP Principal, Penerbitan Kode Customer Principal, & Rekapitulasi Approval. (Region Scope: <span class="font-semibold text-slate-800">{{ userRegion || 'Semua Region' }}</span>)
          </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap sm:flex-nowrap shrink-0">
          <BaseButton
            variant="primary"
            size="md"
            @click="openExportModal"
          >
            Export Approved (.xlsx)
          </BaseButton>
          <BaseButton
            variant="danger"
            size="md"
            @click="openExportRejectedModal"
          >
            Export Rejected (.xlsx)
          </BaseButton>
        </div>
      </div>

      <!-- INLINE FILTER TOOLBAR & ACTIVE CHIPS (DESIGN REFERENCE STYLED) -->
      <div class="bg-white p-4 rounded-2xl border border-[#E5E7EB] shadow-xs space-y-3">
        
        <!-- ROW 1: SEARCH BAR + QUICK FILTER DROPDOWNS + MODAL TRIGGER -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
          
          <!-- Instant Search Input -->
          <div class="relative w-full lg:w-80 shrink-0">
            <input
              type="text"
              v-model="search"
              @keyup.enter="applyFilters"
              placeholder="Search by name, code, salesman..."
              class="w-full pl-9 pr-8 py-2 text-xs font-semibold text-slate-800 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder:text-slate-400 shadow-2xs"
            />
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <button
              v-if="search"
              @click="search = ''; applyFilters();"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Inline Quick Filter Dropdowns Row -->
          <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 flex-wrap lg:flex-nowrap">
            
            <!-- Quick Principal Status Filter (Belum diproses, Disetujui, Ditolak EDP) -->
            <div class="relative inline-flex items-center shrink-0">
              <select
                v-model="selectedStatus"
                @change="applyFilters"
                class="pl-3.5 pr-9 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-slate-50 hover:bg-white text-slate-700 focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-2xs transition appearance-none max-w-[210px] truncate"
                style="-webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;"
              >
                <option value="">Status Principal: Semua</option>
                <option value="PENDING_EDP">⏳ Belum Diproses (Pending EDP)</option>
                <option value="APPROVED_EDP">✔ Disetujui (Approved EDP)</option>
                <option value="REJECTED_EDP">✖ Ditolak (Rejected EDP)</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 pointer-events-none text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>


            <!-- Quick Sort Select -->
            <div class="relative inline-flex items-center shrink-0">
              <select
                v-model="sortSelect"
                @change="applyFilters"
                class="pl-3.5 pr-9 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-slate-50 hover:bg-white text-slate-700 focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-2xs transition appearance-none max-w-[220px] truncate"
                style="-webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;"
              >
                <option value="created_at_desc">Sort: Waktu Submit Terbaru</option>
                <option value="created_at_asc">Sort: Waktu Submit Terlama</option>
                <option value="nama_noo_asc">Sort: Nama Outlet (A-Z)</option>
                <option value="branch_name_asc">Sort: Cabang (A-Z)</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 pointer-events-none text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <!-- Filter & Sort Dialog Button -->
            <button
              @click="showFilterModal = true"
              class="px-3.5 py-1.5 text-xs font-bold text-slate-800 bg-white hover:bg-slate-100 border border-slate-300 rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer shrink-0"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
              <span>Filters & Scope</span>
              <span
                v-if="activeFilterCount > 0"
                class="w-4 h-4 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center ml-0.5"
              >
                {{ activeFilterCount }}
              </span>
            </button>
          </div>
        </div>

        <!-- ROW 2: ACTIVE FILTER CHIPS (PILLS WITH REMOVE X BUTTON) -->
        <div v-if="activeFilterChips.length > 0" class="flex items-center gap-2 flex-wrap pt-2 border-t border-slate-100 text-xs">
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1">Active Filters:</span>
          
          <div
            v-for="chip in activeFilterChips"
            :key="chip.key"
            class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-900 font-bold text-[11px] flex items-center gap-1.5 shadow-2xs hover:bg-indigo-100 transition"
          >
            <span>{{ chip.label }}</span>
            <button
              type="button"
              @click="removeChip(chip.key)"
              class="w-3.5 h-3.5 rounded-full bg-indigo-200 hover:bg-indigo-300 text-indigo-800 flex items-center justify-center text-[9px] font-black cursor-pointer"
            >
              ✕
            </button>
          </div>

          <button
            @click="resetFilters"
            class="px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:underline cursor-pointer ml-1"
          >
            Reset All Filters
          </button>
        </div>
      </div>

      <!-- Table Submisi NOO -->
      <div class="bg-white rounded-[10px] border border-[#E5E7EB] shadow-xs overflow-hidden relative isolate min-h-[250px]" style="isolation: isolate;">
        <!-- LOADING SPINNER OVERLAY (STRICTLY SCOPED INSIDE TABLE CARD) -->
        <div v-if="isLoadingFilters" class="absolute inset-0 bg-white/85 z-20 flex flex-col items-center justify-center gap-3 transition-opacity">
          <div class="w-9 h-9 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <span class="text-xs font-bold text-slate-700">Memuat & memfilter data toko...</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-[14px] text-left text-[#374151]">
            <thead class="bg-[#F3F4F6] border-b border-[#E5E7EB] font-semibold text-[#111827] uppercase tracking-wider select-none">
              <tr>
                <th class="p-4">Cabang / Salesman</th>
                <th class="p-4">Nama Outlet & Pemilik</th>
                <th class="p-4">CustCode Dist</th>
                <th class="p-4">Customer Code Principal</th>
                <th class="p-4">Status NOO</th>
                <th class="p-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E7EB]">
              <tr v-if="sortedSubmissions.length === 0">
                <td colspan="6" class="text-center py-12 text-[#9CA3AF] text-[14px]">
                  Belum ada data submisi toko masuk untuk verifikasi EDP.
                </td>
              </tr>

              <tr
                v-for="sub in sortedSubmissions"
                :key="sub.request_id"
                class="transition border-b"
                :class="getRowStyle(sub)"
              >
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

                <!-- STATUS BADGE COLUMN -->
                <td class="p-4">
                  <div>
                    <span class="px-2.5 py-1 text-[12px] font-semibold rounded-[8px] border inline-flex items-center gap-1" :class="getStatusBadgeStyle(sub.status)">
                      {{ formatStatusLabel(sub.status) }}
                    </span>
                  </div>
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
          v-if="submissions?.links"
          :links="submissions.links"
          :from="submissions.from"
          :to="submissions.to"
          :total="submissions.total"
          :current-per-page="submissions.per_page"
        />
      </div>

      <!-- MODAL DETAIL KOMPREHENSIF (FULL SCREEN BACKDROP VIA TELEPORT - LEVEL 1 MODAL) -->
      <Teleport to="body">
        <div v-if="activeModalSubmission" class="fixed inset-0 min-h-screen min-w-full w-full h-full z-[99990] overflow-y-auto bg-slate-950/75 backdrop-blur-md flex items-center justify-center p-4 sm:p-6" @click.self="activeModalSubmission = null">
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
                <p v-if="activeModalSubmission.code_noo_principal" class="text-[15px] font-mono font-bold text-[#15803D]">
                  {{ activeModalSubmission.code_noo_principal }}
                </p>
                <div v-else-if="activeModalSubmission.previous_code_noo_principal" class="space-y-0.5">
                  <p class="text-[14px] font-mono font-bold text-amber-700 flex items-center gap-1">
                    <span>{{ activeModalSubmission.previous_code_noo_principal }}</span>
                    <span class="text-[10px] font-sans font-bold bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded border border-amber-300">Reuse on Approve</span>
                  </p>
                  <p class="text-[11px] text-amber-600 leading-tight">Kode sebelumnya (akan otomatis dipakai kembali saat di-approve)</p>
                </div>
                <p v-else class="text-[14px] font-mono font-medium text-slate-400">
                  BELUM TERGENERATE
                </p>
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
                      class="px-2 py-0.5 text-xs font-bold text-slate-900 bg-amber-400 hover:bg-amber-300 active:bg-amber-500 rounded-md transition shadow-xs flex items-center gap-1 cursor-pointer border border-amber-300"
                      title="Ubah / Rename Nama Toko"
                    >
                      <svg class="w-3.5 h-3.5 text-slate-950 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                      </svg>
                      <span>Ubah</span>
                    </button>
                  </div>
                  <div v-else class="flex flex-col gap-1.5 mt-1">
                    <input
                      type="text"
                      v-model="editedStoreName"
                      class="w-full px-3 py-1.5 text-[14px] font-normal text-[#374151] bg-white border border-[#2563EB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB]"
                    />
                    <div class="flex items-center gap-2">
                      <button @click="saveStoreName" class="px-3 py-1 text-[13px] font-semibold text-white bg-[#16A34A] hover:bg-[#15803D] rounded-[8px] cursor-pointer">Simpan ✓</button>
                      <button @click="isEditingStoreName = false" class="px-2.5 py-1 text-[13px] font-semibold text-[#374151] bg-[#F3F4F6] rounded-[8px] cursor-pointer">Batal ✕</button>
                    </div>
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
                  <span class="text-[14px] font-medium text-[#4B5563] block mb-1">GPS Koordinat</span>
                  <p class="font-mono text-[#111827] font-bold">{{ activeModalSubmission.la }}, {{ activeModalSubmission.lg }}</p>
                  <p class="text-[12px] text-[#15803D] font-semibold mt-0.5">Akurasi GPS: {{ activeModalSubmission.accuracy_m || '-' }} meter</p>
                </div>
              </div>
            </div>

            <!-- SECTION 3: HIRARKI CABANG & SALESMAN -->
            <div class="space-y-3">
              <h3 class="text-[14px] font-semibold text-[#1F2937] uppercase tracking-wider border-b border-[#E5E7EB] pb-2">🏢 Hirarki Cabang & Salesman</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 text-[14px]">
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
                <div>
                  <span class="text-[#6B7280] block text-[13px]">Tanggal Submit SE</span>
                  <span class="font-bold text-[#059669]">
                    {{ activeModalSubmission.submitted_at ? new Date(activeModalSubmission.submitted_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : (activeModalSubmission.created_at ? new Date(activeModalSubmission.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-') }}
                  </span>
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
                  <div v-if="getPhotoUrl(activeModalSubmission.photo_depan_url || activeModalSubmission.photo_depan_path)" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = getPhotoUrl(activeModalSubmission.photo_depan_url || activeModalSubmission.photo_depan_path)">
                    <img
                      :src="getPhotoUrl(activeModalSubmission.photo_depan_url || activeModalSubmission.photo_depan_path)"
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
                  <div v-if="getPhotoUrl(activeModalSubmission.photo_dalam_url || activeModalSubmission.photo_dalam_path)" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = getPhotoUrl(activeModalSubmission.photo_dalam_url || activeModalSubmission.photo_dalam_path)">
                    <img
                      :src="getPhotoUrl(activeModalSubmission.photo_dalam_url || activeModalSubmission.photo_dalam_path)"
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
                    <div v-if="getPhotoUrl(activeModalSubmission.photo_ktp_url || activeModalSubmission.photo_ktp_path)" class="relative group cursor-pointer overflow-hidden rounded-[8px]" @click="previewImageModal = getPhotoUrl(activeModalSubmission.photo_ktp_url || activeModalSubmission.photo_ktp_path)">
                      <img
                        :src="getPhotoUrl(activeModalSubmission.photo_ktp_url || activeModalSubmission.photo_ktp_path)"
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
                <button type="button" :disabled="isUploadingKtp" @click="showKtpModal = false" class="px-4 py-2 text-[15px] font-semibold bg-white border border-[#D1D5DB] hover:bg-[#F3F4F6] text-[#374151] rounded-[8px] disabled:opacity-50 cursor-pointer">Batal</button>
                <button
                  type="submit"
                  :disabled="!ktpFile || isUploadingKtp"
                  class="px-5 py-2 text-[15px] font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] rounded-[8px] shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="isUploadingKtp" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <span>{{ isUploadingKtp ? 'Mengupload Foto...' : 'Upload Foto KTP' }}</span>
                </button>
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
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
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
                    @change="onExportBranchChange"
                    :disabled="isLoadingExportBranches || exportBranches.length === 0"
                    class="w-full px-3 py-2 text-[14px] bg-white border border-[#D1D5DB] rounded-[8px] focus:ring-2 focus:ring-[#2563EB] font-medium text-[#111827] disabled:bg-slate-100 disabled:text-slate-400 cursor-pointer disabled:cursor-not-allowed"
                  >
                    <option v-if="isLoadingExportBranches" value="" disabled selected>🔄 Memuat daftar distributor...</option>
                    <option v-else-if="exportBranches.length === 0" value="" disabled selected>⚠️ Tidak Ada Distributor dengan Data Approved pada Tanggal Ini</option>
                    <option v-else value="">-- Pilih Distributor ({{ exportBranches.length }} Tersedia) --</option>
                    
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
              </div>
            </div>

            <!-- Table Result Section -->
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[13px] text-[#4B5563] font-medium">
                <span>{{ exportSubmissions.length }} data Approved untuk {{ exportBranch ? exportBranch : 'distributor' }}.</span>
                <span v-if="isLoadingExportData" class="text-[#2563EB] font-bold animate-pulse">🔄 Memuat data...</span>
              </div>

              <div class="border border-[#E5E7EB] rounded-[10px] overflow-x-auto max-h-72 overflow-y-auto relative isolate min-h-[220px]" style="isolation: isolate;">
                <!-- PROMINENT EXECUTIVE LOADING OVERLAY -->
                <div v-if="isLoadingExportData" class="absolute inset-0 bg-white/95 z-30 flex flex-col items-center justify-center gap-3 p-6 text-center transition-all">
                  <div class="relative flex items-center justify-center">
                    <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <div class="absolute w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-xs">⚡</div>
                  </div>
                  <div class="space-y-1">
                    <span class="text-sm font-extrabold text-slate-800 block">🔄 Memuat Data NOO Approved...</span>
                    <span class="text-xs text-slate-500 font-medium block">Sedang mengambil data toko untuk distributor <strong class="text-blue-600">{{ exportBranch }}</strong></span>
                  </div>
                </div>

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
                        <span class="text-[14px]">🏬 Silakan pilih <strong>Distributor</strong> terlebih dahulu untuk menampilkan data NOO Approved.</span>
                      </td>
                    </tr>
                    <tr v-else-if="exportSubmissions.length === 0">
                      <td colspan="8" class="p-6 text-center text-[#9CA3AF]">Tidak ada data NOO Approved pada filter tanggal & distributor ini.</td>
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

      <!-- DEDICATED MODAL FILTER & SORT -->
      <Teleport to="body">
        <div v-if="showFilterModal" class="fixed inset-0 min-h-screen min-w-full w-full h-full bg-black/60 backdrop-blur-xs z-[99995] flex items-center justify-center p-4 overflow-y-auto" @click.self="showFilterModal = false">
          <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl border border-slate-200 text-slate-800 my-auto">
            
            <!-- Modal Filter Header -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-3.5">
              <div>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Filter & Urutkan Data Verifikasi</h3>
                <p class="text-xs text-slate-500">Sesuaikan kriteria filter & urutan tampilan tabel</p>
              </div>
              <button
                @click="showFilterModal = false"
                class="text-slate-400 hover:text-slate-700 text-lg font-bold p-1 rounded-lg hover:bg-slate-100 transition cursor-pointer"
              >
                ✕
              </button>
            </div>

            <!-- Modal Filter Body -->
            <div class="space-y-4 text-xs font-semibold">
              <!-- REGION (IF SUPERADMIN / ADMIN PRINCIPAL) -->
              <div v-if="userRole === 'SUPERADMIN' || userRole === 'ADMIN_PRINCIPAL'">
                <label class="block text-xs font-bold text-slate-700 mb-1">Region / Wilayah Scope:</label>
                <SearchableSelect
                  v-model="modalRegion"
                  :options="regionOptions"
                  placeholder="-- Semua Region --"
                  searchPlaceholder="Ketik Region Code / Nama..."
                  @change="onModalRegionChange"
                />
              </div>

              <!-- ENTITY / PRINCIPAL -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Entity Principal:</label>
                <SearchableSelect
                  v-model="modalPrincipal"
                  :options="entityOptionsModal"
                  placeholder="-- Semua Principal --"
                  searchPlaceholder="Ketik Kode / Nama Entity..."
                  @change="onModalPrincipalChange"
                />
              </div>

              <!-- BRANCH -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Cabang / Branch:</label>
                <SearchableSelect
                  v-model="modalBranch"
                  :options="branchOptionsModal"
                  placeholder="-- Semua Cabang --"
                  searchPlaceholder="Ketik ID atau Nama Cabang..."
                />
              </div>

              <!-- STATUS -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status Submisi NOO:</label>
                <SearchableSelect
                  v-model="modalStatus"
                  :options="statusOptions"
                  placeholder="-- Semua Status --"
                  searchPlaceholder="Cari Status..."
                />
              </div>

              <!-- BULAN & TAHUN APPROVAL EDP (MULTISELECT CHECKBOX DROPDOWN LIKE MONITORING RO) -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- BULAN MULTISELECT -->
                <div class="relative" ref="monthDropdownRef">
                  <label class="block text-xs font-bold text-slate-700 mb-1">Bulan Approval EDP:</label>
                  
                  <button
                    type="button"
                    @click="isMonthDropdownOpen = !isMonthDropdownOpen"
                    class="w-full h-10 px-3 text-xs rounded-lg border border-slate-300 bg-white font-semibold text-slate-800 flex items-center justify-between shadow-2xs hover:border-blue-500 focus:outline-none transition cursor-pointer"
                  >
                    <span class="truncate pr-2">{{ modalEdpMonths.length === 0 ? '-- Semua Bulan --' : `${modalEdpMonths.length} Bulan` }}</span>
                    <div class="flex items-center gap-1.5 shrink-0">
                      <span
                        v-if="modalEdpMonths.length > 0 && modalEdpMonths.length < 12"
                        class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center"
                      >
                        {{ modalEdpMonths.length }}
                      </span>
                      <span class="text-[10px] text-slate-500">▼</span>
                    </div>
                  </button>

                  <!-- DROPDOWN OVERLAY WITH CHECKBOXES -->
                  <div
                    v-if="isMonthDropdownOpen"
                    class="absolute left-0 top-full mt-1.5 w-72 bg-white rounded-xl border border-slate-200 shadow-xl z-50 p-3 space-y-2 text-xs"
                  >

                    <!-- CHECKBOX LIST 12 BULAN -->
                    <div class="grid grid-cols-2 gap-1.5 max-h-48 overflow-y-auto pt-1">
                      <label
                        v-for="m in edpMonthOptions"
                        :key="m.value"
                        class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer select-none text-slate-700"
                      >
                        <input
                          type="checkbox"
                          :value="m.value"
                          v-model="modalEdpMonths"
                          class="w-3.5 h-3.5 text-blue-600 rounded border-slate-300 focus:ring-blue-500"
                        />
                        <span class="font-medium text-[11.5px]">{{ m.label }}</span>
                      </label>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-[11px]">
                      <span class="text-slate-500 font-semibold">{{ modalEdpMonths.length }} bulan terpilih</span>
                      <button
                        type="button"
                        @click="isMonthDropdownOpen = false"
                        class="text-blue-600 font-bold hover:underline cursor-pointer"
                      >
                        Selesai
                      </button>
                    </div>
                  </div>
                </div>

                <!-- TAHUN APPROVAL EDP -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Approval EDP:</label>
                  <div class="relative">
                    <select
                      v-model="modalEdpYear"
                      class="w-full h-10 pl-3 pr-8 text-xs font-semibold text-slate-800 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 shadow-2xs cursor-pointer appearance-none"
                    >
                      <option value="">-- Semua Tahun Approval --</option>
                      <option v-for="y in edpYearSelectOptions" :key="y.value" :value="y.value">
                        {{ y.label }}
                      </option>
                    </select>
                    <svg class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                  </div>
                </div>
              </div>

              <!-- SORT DROPDOWN -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Urutkan Tampilan Tabel (Sort):</label>
                <div class="relative">
                  <select
                    v-model="modalSort"
                    class="w-full text-xs font-semibold p-2.5 pr-8 rounded-lg border border-slate-300 bg-white text-slate-800 focus:ring-2 focus:ring-blue-500 shadow-2xs cursor-pointer appearance-none"
                  >
                    <option value="created_at_desc">Waktu Submit (Terbaru ke Terlama)</option>
                    <option value="created_at_asc">Waktu Submit (Terlama ke Terbaru)</option>
                    <option value="nama_noo_asc">Nama Outlet (A - Z)</option>
                    <option value="nama_noo_desc">Nama Outlet (Z - A)</option>
                    <option value="branch_name_asc">Cabang Distributor (A - Z)</option>
                    <option value="status_asc">Status Submisi</option>
                  </select>
                  <svg class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>
            </div>

            <!-- Modal Filter Footer -->
            <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-3">
              <button
                type="button"
                @click="resetFilters"
                class="px-4 py-2 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer"
              >
                Reset All Filter
              </button>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="showFilterModal = false"
                  class="px-4 py-2.5 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="button"
                  @click="applyFilterModal"
                  :disabled="isLoadingFilters"
                  class="px-5 py-2.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm transition cursor-pointer flex items-center gap-2 disabled:opacity-50"
                >
                  <svg v-if="isLoadingFilters" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>Terapkan Filter & Sort</span>
                </button>
              </div>
            </div>

          </div>
        </div>
      </Teleport>

    </div>
  </EdpLayout>
</template>
