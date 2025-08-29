<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

// ---- State ----
const courier_service = ref(1);
const shipments = ref<any[]>([]);
const cities = ref<any[]>([]);
const filters = reactive({
    dateRange: '',
    status: '',
    originCity: '',
    destination_city: '',
});
const toCancel = ref<string | null>(null);
const is_loading = ref(true);
const is_fetching = ref(false);

// NEW: selection state
const selectedIds = ref<number[]>([]);
const allVisibleChecked = ref(false);

// NEW: selected row for the view modal
const selectedShipment = ref<any | null>(null);

// NEW: tooltip state for address
const activeTooltip = ref<string | null>(null);
const tooltipPositions = ref<Record<string, { left: number; top: number }>>({});

// NEW: tracking data state
const trackingData = ref<any>(null);
const isFetchingTracking = ref(false);

// Helpers
const money = (n?: number | string) => ((n ?? n === 0) ? new Intl.NumberFormat('en-PK').format(Number(n)) : '—');

function clearAll() {
    filters.dateRange = '';
    filters.status = '';
    filters.originCity = '';
    filters.destination_city = '';
}

function formatDate(dateString) {
  if (!dateString) return "17-March-2003";
  const date = new Date(dateString);

  const day = String(date.getDate()).padStart(2, "0");
  const month = date.toLocaleString("en-US", { month: "long" }); // Full month name
  const year = date.getFullYear(); // Full year

  return `${day}-${month}-${year}`;
}

// Format address with exactly 25 characters per line, max 2 lines
const formatAddress = (address) => {
  if (!address) return '—';

  if (address.length <= 25) {
    return address;
  }

  // Break into chunks of exactly 25 characters
  const chunks = [];
  for (let i = 0; i < address.length; i += 25) {
    chunks.push(address.substring(i, i + 25));
  }

  // If more than 2 lines (50 characters), add ellipsis
  if (address.length > 50) {
    // Show only first 2 lines and add ellipsis
    const displayChunks = chunks.slice(0, 2);
    return displayChunks.join('<br>') + '<br><span style="color: #6c757d; font-style: italic;">...</span>';
  }

  return chunks.join('<br>');
};

// Tooltip functions for address
const showTooltip = (shipmentId, address) => {
  if (address && address.length > 50) {
    activeTooltip.value = shipmentId;
    // Calculate position on next tick to ensure DOM is updated
    nextTick(() => {
      calculateTooltipPosition(shipmentId);
    });
  }
};

const hideTooltip = (shipmentId) => {
  activeTooltip.value = null;
};

const calculateTooltipPosition = (shipmentId) => {
  const addressCell = document.querySelector(`[data-shipment-id="${shipmentId}"] .address-container`);
  if (addressCell) {
    const rect = addressCell.getBoundingClientRect();

    tooltipPositions.value[shipmentId] = {
      left: rect.left + rect.width / 2,
      top: rect.top - 120 // Position above the cell
    };
  }
};

// ---- API ----
const fetchShipments = async () => {
    try {
        is_fetching.value = true;
        const response = await axios.get('/api/shipments');
        shipments.value = response.data.data || [];
    } catch (error) {
        console.error('Error fetching shipments:', error);
    } finally {
        is_fetching.value = false;
    }
};

const cancelOrder = async (tracking_number: string) => {
    await axios.post(route('order.cancel', { tracking_number, courier: 'leopard' }));
    shipments.value = shipments.value.map((s) => (s.tracking_number === tracking_number ? { ...s, is_cancelled: true } : s));
    (document.getElementById('hideCancelModal') as HTMLButtonElement)?.click();
};

const getCities = async () => {
    try {
        const response = await axios.get(route('courier.cities', { courier: 'leopard' }));
        cities.value = response.data.data || [];
    } catch (error) {
        console.error('Error fetching cities:', error);
    }
};

// ---- DataTables init ----
const rebindSelectAllOnDraw = () => {
    // Optimized - use requestAnimationFrame and avoid DOM queries
    requestAnimationFrame(() => {
        updateHeaderCheckboxState();
    });
};


const initializeDataTable = () => {
  const id = '#myTable';
  if ($.fn.DataTable.isDataTable(id)) {
    $(id).DataTable().destroy();
  }
  nextTick(() => {
    const dt = $(id).DataTable({
    autoWidth: true,     // ✅ stop recalculating widths
    scrollX: true,
    pageLength: 50,
    lengthMenu: [[50, 100, 250, -1], [50, 100, 250, 'ALL']],
    columnDefs: [
        { targets: '_all', className: 'align-middle' } // keep vertical center
    ]
    });

    // Recalc on resize / orientation change / modals/tabs
    const adjust = () => dt.columns.adjust();
    $(window).on('resize.dt', adjust);
    window.addEventListener('orientationchange', adjust);
    document.addEventListener('shown.bs.modal', adjust);
    document.addEventListener('shown.bs.tab', adjust);

    // Keep your select-all logic
    dt.on('draw', () => rebindSelectAllOnDraw());
    rebindSelectAllOnDraw();
  });
};

// ---- Lifecycle ----
onMounted(async () => {
    await fetchShipments();
    await getCities();

    nextTick(() => {
        initializeDataTable();

        nextTick(() => {
            $('#origin_city')
                .select2()
                .on('change', (e: any) => {
                    filters.originCity = $(e.target).val() as string;
                });
            $('#destination_city')
                .select2()
                .on('change', (e: any) => {
                    filters.destination_city = $(e.target).val() as string;

                });
            $('#daterange').daterangepicker({ locale: { format: 'YYYY-MM-DD' } }, function (start: any, end: any) {
                filters.dateRange = `${start.format('YYYY-MM-DD')} - ${end.format('YYYY-MM-DD')}`;
            });
        });
    });

    is_loading.value = false;
});

// ---- Filters watcher ----
watch(filters, async (newFilters) => {
    const response = await axios.get(route('shipments.index'), {
        params: {
            date_from: newFilters.dateRange.split(' - ')[0],
            date_to: newFilters.dateRange.split(' - ')[1],
            status: newFilters.status,
            origin_city: newFilters.originCity,
            destination_city: newFilters.destination_city,
        },
    });
    shipments.value = response.data.data || [];
    initializeDataTable();
});

// ---- Selection handlers ----
const toggleSelectAll = () => {
    // Optimized - use shipments data instead of DOM queries
    const visibleIds = shipments.value.map(s => s.id);

    if (!allVisibleChecked.value) {
        // add all visible ids
        const set = new Set(selectedIds.value);
        visibleIds.forEach((id) => set.add(id));
        selectedIds.value = Array.from(set);
        allVisibleChecked.value = true;
    } else {
        // remove all visible ids
        const remove = new Set(visibleIds);
        selectedIds.value = selectedIds.value.filter((id) => !remove.has(id));
        allVisibleChecked.value = false;
    }
};

const onRowToggle = (id: number, e: Event) => {
    const checked = (e.target as HTMLInputElement).checked;
    if (checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    }

    // Optimized header checkbox state update - only update when needed
    updateHeaderCheckboxState();
};

// Optimized computed property for checkbox states
const checkboxStates = computed(() => {
    const states = new Map();
    shipments.value.forEach(shipment => {
        states.set(shipment.id, selectedIds.value.includes(shipment.id));
    });
    return states;
});

// Debounced function to update header checkbox state
let headerCheckboxUpdateTimeout: number | null = null;
const updateHeaderCheckboxState = () => {
    // Clear existing timeout
    if (headerCheckboxUpdateTimeout) {
        clearTimeout(headerCheckboxUpdateTimeout);
    }

    // Debounce the update to avoid excessive recalculations
    headerCheckboxUpdateTimeout = setTimeout(() => {
        const visibleIds = shipments.value.map(s => s.id);
        const allVisibleSelected = visibleIds.length > 0 && visibleIds.every(id => selectedIds.value.includes(id));
        allVisibleChecked.value = allVisibleSelected;
        headerCheckboxUpdateTimeout = null;
    }, 50); // 50ms debounce
};

// ---- Actions ----
const viewOrder = (shipment: any) => {
    selectedShipment.value = shipment;
    (document.getElementById('viewModalOpen') as HTMLButtonElement)?.click();
};

const cancellationProposal = (tracking_number: string) => {
    toCancel.value = tracking_number;
    (document.getElementById('cancelModalPopup') as HTMLButtonElement)?.click();
};

const mergeSelected = () => {
    const ids = selectedIds.value.join(',');
    const url = ids.length ? route('slips.merged', { ids }) : route('slips.merged'); // fallback: server merges all
    window.open(url as string, '_blank');
};

// ---- Badge map ----
const statusClassMap: Record<string, string> = {
    'Drop Off at Express Center': 'drop-off',
    'Shipment Picked': 'shipment-picked',
    'Being Return': 'being-return',
    'Ready for Return': 'ready-for-return',
    Dispatched: 'dispatched',
    Delivered: 'delivered',
    Pending: 'pending',
    Missroute: 'missroute',
    'Returned to shipper': 'returned-to-shipper',
    'Arrived at Station': 'arrived-at-station',
    'Assign to Courier': 'assign-to-courier',
    'Consignment Booked': 'consignment-booked',
    Cancelled: 'cancelled',
    'Pickup Request Sent': 'pickup-request-sent',
    '48 Hours Auto Canceled': 'auto-canceled',
    'Pickup Request not Send': 'pickup-request-not-sent',
};


// NEW: invoice modal state
const invoiceShipment = ref<any | null>(null);

// NEW: open invoice modal from "Receive Amount" top value
const openInvoice = (shipment: any) => {
  invoiceShipment.value = shipment;
  (document.getElementById('invoiceModalOpen') as HTMLButtonElement)?.click();
};

// status modal shipment (if you don't already have it)
const statusModalShipment = ref<any|null>(null);

// map status -> image (replace with your actual paths later)
const STATUS_IMG: Record<string, string> = {
  'Delivered': '/images/status/delivered.png',
  'Dispatched': '/images/status/dispatched.png',
  'Pending': '/images/status/pending.png',
  'Cancelled': '/images/status/cancelled.png',
  // ...add all your statuses here...
};

const STATUS_PLACEHOLDER = '/images/status/placeholder.png';

// helpers to get current status text & image
const currentStatus = computed(() => {
  if (!statusModalShipment.value) return '—';
  return statusModalShipment.value.is_cancelled
    ? 'Cancelled'
    : (statusModalShipment.value.status || '—');
});
const currentStatusImg = computed(() => STATUS_IMG[currentStatus.value] || STATUS_PLACEHOLDER);

// call this when clicking a badge in the table:
const openStatusModal = (shipment: any) => {
  statusModalShipment.value = shipment;
  fetchTrackingData(shipment.tracking_number);
  (document.getElementById('statusModalOpen') as HTMLButtonElement)?.click();
};

// Fetch tracking data from LeopardCourier API
const fetchTrackingData = async (trackingNumber: string) => {
  if (!trackingNumber) return;

  try {
    isFetchingTracking.value = true;
    trackingData.value = null;

    const response = await axios.get(`/api/shipments/track/${trackingNumber}`);

    console.log('Tracking API Response:', response.data);

    if (response.data.success) {
      trackingData.value = response.data.data;
      console.log('Tracking Data Set:', trackingData.value);

      // Debug the structure
      if (trackingData.value?.packet_list?.[0]) {
        console.log('First Packet:', trackingData.value.packet_list[0]);
        console.log('Tracking Detail:', trackingData.value.packet_list[0]['Tracking Detail']);
      }
    } else {
      console.error('Failed to fetch tracking data:', response.data.message);
    }
  } catch (error) {
    console.error('Error fetching tracking data:', error);
  } finally {
    isFetchingTracking.value = false;
  }
};

// Put this with your other helpers
const formatKg = (w?: number | string) => {
  const n = Number(w);
  return Number.isFinite(n) ? (n / 1000).toFixed(3) : '—';
};

// Format tracking date (yyyy-mm-dd to dd MMM yy)
const formatTrackingDate = (dateString: string) => {
  if (!dateString) return '17-March-2003';
  try {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = date.toLocaleString('en-US', { month: 'long' });
    const year = date.getFullYear();
    return `${day}-${month}-${year}`;
  } catch (error) {
    return '17-March-2003';
  }
};

// Format tracking time (HH:MM AM/PM format)
const formatTrackingTime = (timeString: string) => {
  if (!timeString) return '1:00 AM';
  try {
    // If it's a full date string, extract just the time
    if (timeString.includes('-') || timeString.includes('/')) {
      const date = new Date(timeString);
      return date.toLocaleString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    }
    // If it's already a time string, try to format it
    // Try to parse as time and format to 12-hour
    if (timeString.includes(':') || timeString.includes('AM') || timeString.includes('PM')) {
      const timeParts = timeString.split(':');
      if (timeParts.length >= 2) {
        let hour = parseInt(timeParts[0]);
        const minute = timeParts[1].replace(/\D/g, '');
        const period = timeString.includes('PM') ? 'PM' : 'AM';

        // Convert to 12-hour format
        if (hour === 0) hour = 12;
        else if (hour > 12) hour = hour - 12;

        return `${hour}:${minute.padStart(2, '0')} ${period}`;
      }
    }
    return timeString;
  } catch (error) {
    return '1:00 AM';
  }
};

// Check if required data is loaded
const isDataReady = computed(() => {
    return materials.value?.data && platforms.value?.data && vendors.value?.data;
});

// Get tracking activities from API response
const trackingActivities = computed(() => {
    if (!trackingData.value?.packet_list?.[0]) return [];

    const packet = trackingData.value.packet_list[0];
    console.log('Processing packet:', packet);
    console.log('Available keys:', Object.keys(packet));

    // Try different possible field names for tracking details
    const activities = packet['Tracking Detail'] || packet['tracking_detail'] || packet['trackingDetail'] || [];

    console.log('Found activities:', activities);
    if (activities.length > 0) {
        console.log('First activity keys:', Object.keys(activities[0]));
    }
    return Array.isArray(activities) ? activities : [];
});

// Get tracking activities in descending order (newest first)
const reversedActivities = computed(() => {
    const activities = trackingActivities.value;
    if (!activities || activities.length === 0) return [];

    // Sort by date and time in descending order (newest first)
    return [...activities].sort((a, b) => {
        const dateA = a['Activity_Date'] || a['Activity Date'] || a['activity_date'] || a['activityDate'] || '';
        const timeA = a['Activity_Time'] || a['Activity Time'] || a['activity_time'] || a['activityTime'] || '';
        const dateB = b['Activity_Date'] || b['Activity Date'] || b['activity_date'] || b['activityDate'] || '';
        const timeB = b['Activity_Time'] || b['Activity Time'] || b['activity_time'] || b['activityTime'] || '';

        // Combine date and time for comparison
        const datetimeA = new Date(`${dateA} ${timeA}`);
        const datetimeB = new Date(`${dateB} ${timeB}`);

        // Sort in descending order (newest first)
        return datetimeB - datetimeA;
    });
});

</script>

<style scoped>
.loader {
    width: 48px;
    height: 48px;
    border: 5px solid black;
    border-bottom-color: transparent;
    border-radius: 50%;
    display: inline-block;
    box-sizing: border-box;
    animation: rotation 1s linear infinite;
}
@keyframes rotation {
    to {
        transform: rotate(360deg);
    }
}

/* Expand toggle button */
.expand-btn {
    width: 32px;
    height: 32px;
    line-height: 1;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-weight: 700;
    background-color: #f3f4f6;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease-in-out;
}
.expand-btn:hover {
    background-color: #e0f2fe;
    border-color: #38bdf8;
    color: #0369a1;
}
.expand-btn.expanded {
    background: #dbeafe;
    border-color: #2563eb;
    color: #1e3a8a;
}

/* Child details (rendered by DataTables) */
.details-inner {
    background: #fff;
    border-radius: 12px;
    margin: 8px 0;
    padding: 16px 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    position: relative;
    overflow: hidden;
}
.details-inner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #3b82f6 0%, #60a5fa 100%);
}
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px 20px;
}
.label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #111827;
    margin-top: 2px;
    word-break: break-word;
}

/* Mobile table spacing */
@media (max-width: 576px) {
    #myTable td,
    #myTable th {
        padding: 0.55rem 0.625rem;
        font-size: 0.9rem;
        white-space: nowrap;
    }
}

.dataTables_info {
    display: none !important;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 9999px;
    font:
        600 13px/1.2 system-ui,
        -apple-system,
        Segoe UI,
        Roboto,
        Arial,
        sans-serif;
    white-space: nowrap;
    border: 1px solid transparent;
}

/* Status badge colors */
.badge.drop-off {
    color: #1565c0;
    background: #e3f2fd;
    border-color: #bbdefb;
}
.badge.shipment-picked {
    color: #283593;
    background: #e8eaf6;
    border-color: #c5cae9;
}
.badge.being-return {
    color: #ef6c00;
    background: #fff3e0;
    border-color: #ffe0b2;
}
.badge.ready-for-return {
    color: #ff8f00;
    background: #fff8e1;
    border-color: #ffecb3;
}
.badge.dispatched {
    color: #00695c;
    background: #e0f2f1;
    border-color: #b2dfdb;
}
.badge.delivered {
    color: #2e7d32;
    background: #e6f4ea;
    border-color: #b7dfb9;
}
.badge.pending {
    color: #616161;
    background: #f5f5f5;
    border-color: #e0e0e0;
}
.badge.missroute {
    color: #d84315;
    background: #fbe9e7;
    border-color: #ffccbc;
}
.badge.returned-to-shipper {
    color: #c62828;
    background: #ffebee;
    border-color: #ffcdd2;
}
.badge.arrived-at-station {
    color: #0277bd;
    background: #e1f5fe;
    border-color: #b3e5fc;
}
.badge.assign-to-courier {
    color: #00838f;
    background: #e0f7fa;
    border-color: #b2ebf2;
}
.badge.consignment-booked {
    color: #6a1b9a;
    background: #f3e5f5;
    border-color: #e1bee7;
}
.badge.cancelled {
    color: #b71c1c;
    background: #ffebee;
    border-color: #ffcdd2;
}
.badge.pickup-request-sent {
    color: #388e3c;
    background: #e8f5e9;
    border-color: #c8e6c9;
}
.badge.auto-canceled {
    color: #e53935;
    background: #ffebee;
    border-color: #ffcdd2;
}
.badge.pickup-request-not-sent {
    color: #f9a825;
    background: #fffde7;
    border-color: #fff9c4;
}

/* nicer checkbox */
input[type='checkbox'] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

:deep(#myTable thead th) {
  padding-left: 0 !important;
  padding-right: 0 !important;
}


/* Clickable number look */
.link-btn {
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
  font-weight: 600;
}

/* Address tooltip styling */
.address-tooltip {
  position: fixed;
  z-index: 9999;
  transform: translateX(-50%);
  pointer-events: none;
}

.tooltip-content {
  background: #2d3748;
  color: white;
  border-radius: 8px;
  padding: 12px 16px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  max-width: 300px;
  word-wrap: break-word;
}

.tooltip-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
  color: #e2e8f0;
}

.tooltip-header i {
  color: #63b3ed;
  font-size: 16px;
}

.tooltip-body {
  font-size: 13px;
  line-height: 1.4;
  color: #f7fafc;
}

.tooltip-arrow {
  position: absolute;
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 8px solid transparent;
  border-right: 8px solid transparent;
  border-top: 8px solid #2d3748;
}

.address-container {
  cursor: pointer;
}

/* Pretty invoice card */
.invoice-card {
  border-radius: 14px;
  border: 1px solid #eee;
  padding: 18px;
  background: #fff;
}

.invoice-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  padding: 6px 0;
}

.invoice-row.total {
  border-top: 1px dashed #ddd;
  margin-top: 6px;
  padding-top: 10px;
  font-weight: 700;
}

.invoice-title {
  font-weight: 700;
  margin-bottom: 6px;
}

.invoice-number {
  color: #1e40af;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.locations-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f9fafb; /* subtle grey bg */
  border-radius: 14px;
  padding: 20px 28px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.05);
  gap: 40px; /* spacing between from / arrow / to */
}

.location {
  text-align: center;
  flex: 1; /* each takes equal width */
}

.label {
  font-size: 0.8rem;
  color: #64748b; /* slate-500 */
  text-transform: uppercase;
  margin-bottom: 4px;
  font-weight: 500;
}

.city {
  font-size: 1.4rem;
  font-weight: 700;
  color: #1e293b; /* slate-800 */
  margin: 0;
}

.arrow {
  font-size: 1.8rem;
  color: #2563eb; /* blue-600 */
}

.locations-card {
  display: grid;
  grid-template-columns: 1fr auto 1fr; /* left / center / right */
  align-items: center;
  background: linear-gradient(135deg, #eef2ff, #f8fafc);
  border-radius: 14px;
  padding: 20px 28px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.06);
  gap: 28px;
}

.location {
  text-align: center;
}

.label {
  font-size: 0.8rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0;
}

.city {
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a; /* slate-900 */
  margin: 2px 0 0 0;
  word-break: break-word;
}

/* Center status block */
.status-center {
  display: grid;
  justify-items: center;
  align-items: center;
  gap: 8px;
  min-width: 160px;
}

.status-img {
  width: 56px;
  height: 56px;
  object-fit: contain;
  border-radius: 12px;
  box-shadow: 0 6px 14px rgba(2, 6, 23, 0.08);
  background: #fff;
}

.status-chip {
  padding: 6px 10px;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 999px;
  background: #eaf2ff;
  color: #1e40af;
  border: 1px solid #cfe0ff;
  white-space: nowrap;
}

.status-meta {
  font-size: 0.75rem;
  color: #64748b;
}

/* responsive */
@media (max-width: 576px) {
  .locations-card {
    grid-template-columns: 1fr;
    text-align: center;
  }
  .status-center {
    order: 2; /* keep center in the middle on small screens */
  }
}

/* ===== Slip (eye-catchy) card ===== */
.slip-card {
  margin-top: 14px;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid #eaeaea;
  background: #ffffff;
  box-shadow: 0 12px 24px rgba(17, 24, 39, 0.08);
  position: relative;
}

.slip-header {
  background: linear-gradient(90deg, #3b82f6 0%, #22c55e 50%, #f59e0b 100%);
  color: #fff;
  padding: 12px 16px;
  font-weight: 800;
  letter-spacing: .3px;
  text-transform: uppercase;
}

.slip-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.slip-col {
  padding: 10px 16px 16px;
  border-right: 1px dashed #e5e7eb;
}
.slip-col:last-child {
  border-right: none;
}

.slip-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.slip-table th {
  width: 46%;
  text-align: left;
  color: #6b7280;
  font-weight: 700;
  padding: 10px 8px;
  border-bottom: 1px dashed #eef2f7;
  text-transform: uppercase;
  letter-spacing: .2px;
}
.slip-table td {
  color: #111827;
  font-weight: 600;
  padding: 10px 8px;
  border-bottom: 1px dashed #eef2f7;
  word-break: break-word;
}
.slip-table tr:hover td, .slip-table tr:hover th {
  background: #f9fafb;
}

@media (max-width: 576px) {
  .slip-grid { grid-template-columns: 1fr; }
  .slip-col { border-right: none; border-bottom: 1px dashed #e5e7eb; }
  .slip-col:last-child { border-bottom: none; }
}


/* ===== Compact mode just for the Status modal ===== */
#statusModal .modal-content { padding: 16px !important; }

/* top "locations-card" strip */
#statusModal .locations-card .label { font-size: 11px; letter-spacing: .2px; }
#statusModal .locations-card .city  { font-size: 14px; font-weight: 700; line-height: 1.2; }
#statusModal .status-center .status-chip { font-size: 12px; padding: 4px 8px; line-height: 1.2; }
#statusModal .status-center .status-meta { font-size: 11px; margin-top: 2px; opacity: .8; }

/* slip card */
#statusModal .slip-card     { border-radius: 12px; margin-top: 10px; }
#statusModal .slip-header   { padding: 8px 12px; font-size: 12px; }
#statusModal .slip-grid     { grid-template-columns: 1fr 1fr; gap: 0 8px; }
#statusModal .slip-col      { padding: 8px 12px 12px; border-right: 1px dashed #e5e7eb; }
#statusModal .slip-col:last-child { border-right: none; }

#statusModal .slip-table          { font-size: 12px; line-height: 1.25; }
#statusModal .slip-table th       { width: 42%; padding: 6px 6px; border-bottom: 1px dashed #eef2f7; }
#statusModal .slip-table td       { padding: 6px 6px; border-bottom: 1px dashed #eef2f7; font-weight: 600; }
#statusModal .slip-table tr:hover th,
#statusModal .slip-table tr:hover td { background: #f9fafb; }

/* extra-tight on small screens */
@media (max-width: 576px) {
  #statusModal .slip-grid    { grid-template-columns: 1fr; }
  #statusModal .slip-col     { border-right: none; border-bottom: 1px dashed #e5e7eb; }
  #statusModal .slip-col:last-child { border-bottom: none; }
  #statusModal .slip-header  { font-size: 11px; }
  #statusModal .slip-table   { font-size: 11px; }
}


/* ===== Ultra-compact Shipment Summary (Status modal only) ===== */
#statusModal .slip-card       { border-radius: 10px; margin-top: 8px; }
#statusModal .slip-header     { padding: 6px 10px; font-size: 11px; letter-spacing: .05px; }

#statusModal .slip-grid       { grid-template-columns: 1fr 1fr; gap: 0 6px; }
#statusModal .slip-col        { padding: 6px 10px 10px; }

#statusModal .slip-table            { font-size: 10px; line-height: 1.15; }
#statusModal .slip-table th         { width: 44%; padding: 4px 6px; border-bottom: 1px dashed #eef2f7; }
#statusModal .slip-table td         { padding: 4px 6px; border-bottom: 1px dashed #eef2f7; font-weight: 600; }
#statusModal .slip-table tr:hover th,
#statusModal .slip-table tr:hover td { background: #fafafa; }

/* If you need it EVEN smaller, uncomment below (9.5px text) */
/*
#statusModal .slip-table      { font-size: 9.5px; }
#statusModal .slip-header     { font-size: 10px; }
#statusModal .slip-col        { padding: 5px 8px 8px; }
#statusModal .slip-table th,
#statusModal .slip-table td   { padding: 3px 5px; }
*/

@media (max-width: 576px) {
  #statusModal .slip-grid { grid-template-columns: 1fr; }
  #statusModal .slip-col  { border-right: none; border-bottom: 1px dashed #e5e7eb; }
  #statusModal .slip-col:last-child { border-bottom: none; }
}

/* Materio-style gradient for the Shipment Summary header */
#statusModal .slip-header {
  /* Primary → Info (Materio palette) */
  background: linear-gradient(
    100deg,
    var(--bs-primary, #7367f0) 0%,
    var(--bs-info,    #00cfe8) 100%
  );
  color: #fff;
  padding: 6px 10px;         /* keep compact */
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .05px;
  text-transform: uppercase;
  border-bottom: 1px solid rgba(255,255,255,.25);
  box-shadow:
    0 1px 0 rgba(0,0,0,.04) inset,
    0 8px 20px rgba(115,103,240,.25);
}

/* ===== Horizontal Activities (Materio) ===== */
#statusModal .activities-card {
  border-radius: 12px;
  background: #fff;
  border: 1px solid #eceef2;
  box-shadow: 0 10px 22px rgba(17, 24, 39, 0.08);
  overflow: hidden;
}

#statusModal .activities-header {
  background: linear-gradient(
    100deg,
    var(--bs-primary, #7367f0) 0%,
    var(--bs-info,    #00cfe8) 100%
  );
  color: #fff;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: .05px;
  text-transform: uppercase;
  border-bottom: 1px solid rgba(255,255,255,.25);
}

/* Horizontal scroll area */
#statusModal .activities-hscroll {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: max(230px, 34%);
  gap: 12px;
  padding: 14px 12px 16px;
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
}

/* Optional: nicer scrollbar */
#statusModal .activities-hscroll::-webkit-scrollbar { height: 8px; }
#statusModal .activities-hscroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 999px; }
#statusModal .activities-hscroll::-webkit-scrollbar-thumb { background: #c7cbe0; border-radius: 999px; }
#statusModal .activities-hscroll:hover::-webkit-scrollbar-thumb { background: #a8adc8; }

/* Each activity card */
#statusModal .activity-h {
  position: relative;
  scroll-snap-align: start;
  background: #fff;
  border: 1px solid #eceef2;
  border-radius: 12px;
  box-shadow: 0 8px 18px rgba(17,24,39,.06);
  padding: 28px 12px 12px; /* top space for dot/line */
  min-height: 92px;
  transition: transform .15s ease, box-shadow .15s ease;
}
#statusModal .activity-h:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 22px rgba(17,24,39,.12);
}

/* Connecting line to the next card */
#statusModal .activity-h::before {
  content: '';
  position: absolute;
  top: 16px;            /* aligns with dot center */
  left: 22px;           /* starts a bit after card edge */
  right: -12px;         /* extends towards next card */
  height: 2px;
  background: linear-gradient(90deg, rgba(115,103,240,.35), rgba(0,207,232,.35));
  pointer-events: none;
}
#statusModal .activity-h:last-child::before { display: none; }

/* Dot on the timeline */
#statusModal .activity-h .dot {
  position: absolute;
  top: 10px;
  left: 16px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7367f0, #00cfe8);
  box-shadow: 0 0 0 2px #fff, 0 6px 14px rgba(115,103,240,.35);
  border: 1px solid rgba(0,0,0,.06);
}

/* Text + chips */
#statusModal .activity-title {
  color: #111827;
  font-weight: 700;
  letter-spacing: .1px;
  font-size: 11.5px;
  line-height: 1.25;
  margin-bottom: 6px;
}

#statusModal .activity-meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

#statusModal .chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 9999px;
  font-weight: 700;
  font-size: 10px;
  border: 1px solid #e6e7ee;
  background: #f9fafb;
  color: #475569;
}
#statusModal .chip-date {
  background: rgba(115,103,240,0.08);
  color: #4c4ad9;
  border-color: rgba(115,103,240,0.22);
}
#statusModal .chip-time {
  background: rgba(0,207,232,0.10);
  color: #018aa0;
  border-color: rgba(0,207,232,0.24);
}

/* Responsive: show bigger cards on small screens */
@media (max-width: 576px) {
  #statusModal .activities-hscroll {
    grid-auto-columns: 86%;
    gap: 10px;
  }
}

/* One full-width card per "page" in the horizontal timeline */
#statusModal .activities-hscroll {
  grid-auto-columns: 100% !important; /* 👈 full width of the scroll viewport */
  gap: 12px;                           /* keep a little spacing between pages */
  scroll-snap-type: x mandatory;
  scroll-padding-left: 12px;           /* so first card aligns cleanly */
}

/* Make sure the card itself stretches */
#statusModal .activity-h {
  width: 100%;
  scroll-snap-align: start;
  scroll-snap-stop: always;            /* snap to each full-width card */
}

/* Mobile: same behavior */
@media (max-width: 576px) {
  #statusModal .activities-hscroll {
    grid-auto-columns: 100% !important;
    gap: 10px;
  }
}

/* ===== Materio single-color look (no gradients) for Status Modal ===== */
#statusModal {
  /* pick the accent once; change to info/success if you like */
  --m-accent: var(--bs-primary, #7367f0); /* or var(--bs-info, #00cfe8) / var(--bs-success, #28c76f) */
}

/* top status chip */
#statusModal .status-center .status-chip {
  background: var(--m-accent);
  color: #fff;
  box-shadow: none;
}

/* shipment summary header */
#statusModal .slip-header {
  background: var(--m-accent);
  color: #fff;
  border-bottom: none;
  box-shadow: none;
}

/* activities section title underline bar */
#statusModal .activities-header::before {
  background: var(--m-accent);
}

/* activity dot */
#statusModal .activity-h .dot {
  background: var(--m-accent);
  box-shadow: none; /* clean, no colored ring */
}

/* optional: subtle accent borders for cards (still single color) */
#statusModal .slip-card,
#statusModal .activities-card,
#statusModal .locations-card {
  border-color: color-mix(in srgb, var(--m-accent) 18%, #e9ecef);
}

/* chips keep neutral fill; add accent text/border for a touch of color */
#statusModal .chip { border-color: color-mix(in srgb, var(--m-accent) 25%, #e9ecef); }
#statusModal .chip-date,
#statusModal .chip-time { color: var(--m-accent); }

/* === Single-color Materio accent for Tracking Activities === */
#statusModal { --m-accent: var(--bs-primary, #7367f0); } /* change once if needed */

/* Card + header */
#statusModal .activities-card {
  border-color: color-mix(in srgb, var(--m-accent) 18%, #e9ecef);
  box-shadow: 0 10px 24px rgba(0,0,0,.06);
}
#statusModal .activities-header {
  background: var(--m-accent);   /* solid, no gradient */
  color: #fff;
  border-bottom: none;
}

/* Scrollbar tint */
#statusModal .activities-hscroll::-webkit-scrollbar-thumb {
  background: var(--m-accent);
}

/* Activity item */
#statusModal .activity-h {
  border-color: color-mix(in srgb, var(--m-accent) 20%, #e9ecef);
}
#statusModal .activity-h:hover {
  border-color: var(--m-accent);
  box-shadow: 0 8px 18px color-mix(in srgb, var(--m-accent) 20%, transparent);
}

/* Leading dot */
#statusModal .activity-h .dot {
  background: var(--m-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--m-accent) 18%, transparent);
}

/* Chips (choose ONE style below) */
/* 1) Filled chips (bold) */
#statusModal .chip-date,
#statusModal .chip-time {
  background: var(--m-accent);
  border-color: var(--m-accent);
  color: #fff;
}

/* 2) Outline chips (subtle) */
/*
#statusModal .chip-date,
#statusModal .chip-time {
  background: color-mix(in srgb, var(--m-accent) 10%, #fff);
  border-color: color-mix(in srgb, var(--m-accent) 30%, #e9ecef);
  color: var(--m-accent);
}
*/

/* Make Tracking Activities span full width inside the 2-col grid */
#statusModal .slip-grid .activities-card {
  grid-column: 1 / -1;   /* span both columns */
  width: 100%;
}

/* Container: horizontal row scroll */
#statusModal .activities-hscroll {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 8px 4px;
  flex-wrap: nowrap;
  scrollbar-width: thin; /* Firefox */
}
#statusModal .activities-hscroll::-webkit-scrollbar {
  height: 6px;
}

/* Each activity card */
#statusModal .activity-h {
  flex: 0 0 180px;        /* fixed smaller width */
  min-width: 160px;       /* prevent shrinking too much */
  max-width: 200px;       /* prevent too wide */
  background: #f5f6f8;
  border: 1px solid #e4e6eb;
  border-radius: 10px;
  padding: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,.05);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

/* Dot inline with title */
#statusModal .activity-h .dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--m-accent, #7367f0);
  margin-bottom: 4px;
}

/* Title text */
#statusModal .activity-title {
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #444;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Meta info (chips) */
#statusModal .activity-meta {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
#statusModal .chip {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 9999px;
  background: #eef0f6;
  color: #333;
}

/* ============ Activities: tidy one-line layout inside narrow cards ============ */
#statusModal .activities-hscroll {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  flex-wrap: nowrap;
  padding: 8px 6px;
  white-space: nowrap;
}

/* each card stays small; remove inline width if you use this */
#statusModal .activity-h {
  width: 200px;                 /* 👈 adjust if needed */
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  background: #f5f6f8;
  border: 1px solid #e4e6eb;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
  overflow: hidden;             /* clip any overflow */
}

/* dot inline */
#statusModal .activity-h .dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--m-accent, #7367f0);
  flex: 0 0 7px;
}

/* title uses remaining space; ellipsis if long */
#statusModal .activity-title {
  flex: 1 1 auto;
  min-width: 0;                 /* REQUIRED for ellipsis in flex */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
  font-weight: 600;
  color: #424242;
  margin: 0;
}

/* chips stay visible, no wrap */
#statusModal .activity-meta {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
  margin: 0 0 0 6px;
}

#statusModal .chip {
  font-size: 10px;
  line-height: 1;
  padding: 2px 6px;
  border-radius: 9999px;
  border: 1px solid color-mix(in srgb, var(--m-accent, #7367f0) 25%, #e9ecef);
  background: #f8f9fb;
  color: var(--m-accent, #7367f0);
  font-weight: 600;
}

/* Remove the "progress line/stripe" on cards inside Status Modal */

/* If any card uses a left vertical stripe via ::before (e.g., details-inner) */
#statusModal .details-inner::before,
#statusModal .slip-card::before,
#statusModal .activities-card::before {
  content: none !important;
  display: none !important;
}

/* If your Activities header had a top accent line via ::before */
#statusModal .activities-header::before {
  content: none !important;
  display: none !important;
}

/* Safety: ensure there's no leftover border acting like a stripe */
#statusModal .details-inner,
#statusModal .slip-card,
#statusModal .activities-card {
  border-left: none !important;
  background-image: none !important; /* in case a gradient created a line */
}

/* ===== Materio tokens just for this modal ===== */
#statusModal {
  --m-primary: var(--bs-primary, #7367f0);
  --m-info:    var(--bs-info,    #00cfe8);
  --m-success: var(--bs-success, #28c76f);
  --m-ink:     #1f2340;
  --m-ink-2:   #475569;
  --m-card:    #ffffff;
  --m-border:  #eceef2;
  --m-soft:    #f7f7fb;
}

/* ===== Modal shell (frosted + glow) ===== */
#statusModal .m-status{
  border-radius: 20px;
  border: 1px solid rgba(115,103,240,.22);
  background:
    radial-gradient(1200px 260px at -5% -10%, rgba(115,103,240,.08), transparent 60%),
    radial-gradient(900px 220px at 110% -10%, rgba(0,207,232,.08), transparent 60%),
    var(--m-card);
  box-shadow: 0 24px 64px rgba(31,35,64,.22);
  overflow: hidden;
}

/* ===== Hero / Locations ===== */
#statusModal .m-hero{
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 18px;
  padding: 18px 22px;
  background: linear-gradient(180deg, rgba(115,103,240,.08), rgba(0,207,232,.06));
  border-bottom: 1px solid var(--m-border);
}

#statusModal .m-loc{
  text-align: center;
}
#statusModal .m-label{
  font-size: 11px;
  letter-spacing: .3px;
  text-transform: uppercase;
  color: #7c88a2;
  margin-bottom: 2px;
}
#statusModal .m-city{
  font-size: 18px;
  font-weight: 800;
  letter-spacing: .4px;
  color: var(--m-ink);
}

#statusModal .m-state{
  text-align: center;
}
#statusModal .m-chip{
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  font-weight: 800;
  font-size: 12px;
  text-transform: capitalize;
  color: #fff;
  border-radius: 9999px;
  background: linear-gradient(110deg, var(--m-primary) 0%, var(--m-info) 100%);
  box-shadow: 0 8px 20px rgba(115,103,240,.35);
}
#statusModal .m-date{
  font-size: 11px;
  margin-top: 6px;
  color: #667085;
}

/* ===== Cards (summary + activities) ===== */
#statusModal .m-card{
  margin: 14px 16px 18px;
  border: 1px solid var(--m-border);
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 12px 24px rgba(17,24,39,.08);
  overflow: hidden;
}

/* ----- Shipment summary ----- */
#statusModal .slip-header{
  background: linear-gradient(100deg, var(--m-primary) 0%, var(--m-info) 100%);
  color: #fff;
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .3px;
  text-transform: uppercase;
}
#statusModal .slip-grid{
  display: grid;
  grid-template-columns: 1fr 1fr;
}
#statusModal .slip-col{
  padding: 10px 14px 12px;
  border-right: 1px dashed #e9edf5;
}
#statusModal .slip-col:last-child{ border-right: none; }

#statusModal .slip-table{
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;      /* compact */
  line-height: 1.25;
}
#statusModal .slip-table th{
  width: 46%;
  padding: 6px 8px;
  text-transform: uppercase;
  letter-spacing: .2px;
  color: #7c88a2;
  border-bottom: 1px dashed #eef2f7;
}
#statusModal .slip-table td{
  padding: 6px 8px;
  font-weight: 700;
  color: #1f2937;
  border-bottom: 1px dashed #eef2f7;
}
#statusModal .slip-table tr:hover th,
#statusModal .slip-table tr:hover td{ background: #fafbff; }

/* Mobile: stack summary columns */
@media (max-width: 576px){
  #statusModal .slip-grid{ grid-template-columns: 1fr; }
  #statusModal .slip-col{ border-right: none; border-bottom: 1px dashed #e9edf5; }
  #statusModal .slip-col:last-child{ border-bottom: none; }
}

/* ----- Activities (horizontal) ----- */
#statusModal .activities-header{
  background: linear-gradient(100deg, var(--m-primary) 0%, var(--m-info) 100%);
  color: #fff;
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .3px;
  text-transform: uppercase;
}
#statusModal .activities-hint{
  font-size: 10px;
  color: #98a2b3;
  padding: 8px 14px 0;
}

#statusModal .activities-hscroll{
  --gap: 12px;
  display: grid;
  grid-auto-flow: column;
  gap: var(--gap);
  padding: 12px 14px 16px;
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  /* Desktop: 3 cards in view */
  grid-auto-columns: calc((100% - (var(--gap) * 2)) / 3);
}

/* Tablet: 2 cards */
@media (max-width: 992px){
  #statusModal .activities-hscroll{ grid-auto-columns: calc((100% - var(--gap)) / 2); }
}
/* Mobile: 1 card */
@media (max-width: 576px){
  #statusModal .activities-hscroll{ grid-auto-columns: 100%; scroll-padding-left: 14px; }
}

/* Activity card */
#statusModal .activity-h{
  position: relative;
  width: 100%;
  scroll-snap-align: start;
  scroll-snap-stop: always;
  border: 1px solid var(--m-border);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 10px 20px rgba(17,24,39,.08);
  padding: 28px 12px 12px; /* space for dot/line */
  transition: transform .15s ease, box-shadow .15s ease;
}
#statusModal .activity-h:hover{
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(17,24,39,.12);
}

/* connector to next card */
#statusModal .activity-h::before{
  content:'';
  position:absolute;
  top:16px; left:22px; right:-12px;
  height:2px;
  background: linear-gradient(90deg, rgba(115,103,240,.35), rgba(0,207,232,.35));
  pointer-events:none;
}
#statusModal .activity-h:last-child::before{ display:none; }

/* dot with subtle pulse */
#statusModal .activity-h .dot{
  position:absolute;
  top:10px; left:16px;
  width:12px; height:12px;
  border-radius:50%;
  background: linear-gradient(135deg, var(--m-primary), var(--m-info));
  box-shadow: 0 0 0 2px #fff, 0 6px 14px rgba(115,103,240,.35);
}
#statusModal .activity-h .dot::after{
  content:'';
  position:absolute; inset:-3px;
  border-radius:50%;
  border:2px solid rgba(115,103,240,.35);
  animation: m-pulse 1.8s infinite ease-out;
}
@keyframes m-pulse{
  0%{ transform: scale(.7); opacity: .8; }
  70%{ transform: scale(1.35); opacity: 0; }
  100%{ transform: scale(1.35); opacity: 0; }
}

/* Title + chips */
#statusModal .activity-title{
  color:#111827;
  font-weight:800;
  letter-spacing:.1px;
  font-size:12px;
  line-height:1.25;
  margin-bottom:6px;
  padding-left:8px;
}
#statusModal .activity-meta{
  display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
  padding-left:8px;
}
#statusModal .chip{
  display:inline-flex; align-items:center;
  padding:3px 8px; border-radius:9999px;
  font-weight:800; font-size:10px;
  border:1px solid #e6e7ee; background:#f9fafb; color:#475569;
}
#statusModal .chip-date{
  background: rgba(115,103,240,.10);
  color:#4c4ad9; border-color: rgba(115,103,240,.24);
}
#statusModal .chip-time{
  background: rgba(0,207,232,.10);
  color:#018aa0; border-color: rgba(0,207,232,.24);
}


/* ============== Neutral palette (no gradients) ============== */
#statusModal{
  --sm-text:   #1f2937;
  --sm-muted:  #6b7280;
  --sm-bg:     #ffffff;
  --sm-bg-soft:#f6f7f9;
  --sm-border: #e6e8eb;
  --sm-shadow: 0 12px 28px rgba(0,0,0,.08);
}

/* Shell */
#statusModal .status-modal{
  border-radius: 16px;
  border: 1px solid var(--sm-border);
  background: var(--sm-bg);
  box-shadow: var(--sm-shadow);
  overflow: hidden;
}

/* --------- HERO (From | Status | To) --------- */
#statusModal .sm-hero{
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 16px;
  align-items: center;
  padding: 16px 18px;
  background: var(--sm-bg-soft);
  border-bottom: 1px solid var(--sm-border);
}
#statusModal .sm-loc{ text-align: center; }
#statusModal .sm-label{
  font-size: 11px; text-transform: uppercase; letter-spacing: .2px; color: var(--sm-muted);
}
#statusModal .sm-city{
  font-size: 18px; font-weight: 800; color: var(--sm-text);
}
#statusModal .sm-state{ text-align: center; }
#statusModal .sm-chip{
  display: inline-flex; align-items: center;
  padding: 6px 12px; border-radius: 999px;
  background: #f1f3f5; border: 1px solid var(--sm-border);
  color: var(--sm-text); font-weight: 700; font-size: 12px;
}
#statusModal .sm-date{
  font-size: 11px; color: var(--sm-muted); margin-top: 6px;
}

/* --------- Card container --------- */
#statusModal .sm-card{
  margin: 14px 16px 18px;
  border: 1px solid var(--sm-border);
  border-radius: 12px;
  background: var(--sm-bg);
  box-shadow: 0 8px 18px rgba(0,0,0,.04);
  overflow: hidden;
}
#statusModal .sm-card-title{
  padding: 10px 12px;
  border-bottom: 1px solid var(--sm-border);
  font-size: 12px; font-weight: 800; letter-spacing: .3px;
  color: var(--sm-text); text-transform: uppercase;
  background: var(--sm-bg-soft);
}

/* --------- Summary --------- */
#statusModal .sm-summary{
  display: grid;
  grid-template-columns: 1fr 1fr;
}
#statusModal .sm-col{
  padding: 10px 12px 12px;
  border-right: 1px dashed var(--sm-border);
}
#statusModal .sm-col:last-child{ border-right: none; }

#statusModal .sm-table{
  width: 100%; border-collapse: collapse;
  font-size: 11px; line-height: 1.3;
}
#statusModal .sm-table th{
  width: 44%; text-align: left;
  color: var(--sm-muted); text-transform: uppercase; letter-spacing: .2px;
  padding: 6px 6px; border-bottom: 1px dashed var(--sm-border);
}
#statusModal .sm-table td{
  padding: 6px 6px; border-bottom: 1px dashed var(--sm-border);
  color: var(--sm-text); font-weight: 700; word-break: break-word;
}
#statusModal .sm-table tr:hover td,
#statusModal .sm-table tr:hover th{ background: #fafafa; }

/* Stack summary on small screens */
@media (max-width: 576px){
  #statusModal .sm-summary{ grid-template-columns: 1fr; }
  #statusModal .sm-col{ border-right: none; border-bottom: 1px dashed var(--sm-border); }
  #statusModal .sm-col:last-child{ border-bottom: none; }
}

/* --------- Activities (Horizontal, multi per frame) --------- */
#statusModal .sm-activities{
  --gap: 12px;
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: calc((100% - (var(--gap) * 2)) / 3); /* 3 cards on desktop */
  gap: var(--gap);
  padding: 12px;
  overflow-x: auto; overflow-y: hidden;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
}
/* Tablet: 2 in view */
@media (max-width: 992px){
  #statusModal .sm-activities{ grid-auto-columns: calc((100% - var(--gap)) / 2); }
}
/* Mobile: 1 in view */
@media (max-width: 576px){
  #statusModal .sm-activities{ grid-auto-columns: 100%; }
}

#statusModal .sm-activity{
  position: relative;
  width: 100%;
  border: 1px solid var(--sm-border);
  border-radius: 10px;
  background: var(--sm-bg);
  padding: 24px 10px 10px;  /* space for dot */
  scroll-snap-align: start;
  scroll-snap-stop: always;
  transition: transform .15s ease, box-shadow .15s ease;
  box-shadow: 0 4px 10px rgba(0,0,0,.03);
}
#statusModal .sm-activity:hover{
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(0,0,0,.06);
}

/* Minimal connector */
#statusModal .sm-activity::before{
  content:''; position:absolute; top:14px; left:20px; right:-10px; height:1px;
  background: #e9eaee; pointer-events:none;
}
#statusModal .sm-activity:last-child::before{ display:none; }

/* Dot */
#statusModal .sm-dot{
  position:absolute; top:9px; left:14px; width:10px; height:10px;
  border-radius:50%; background:#cfd3d8; border:1px solid #bfc5cc;
}

/* Activity text */
#statusModal .sm-activity-title{
  font-size: 12px; font-weight: 800; color: var(--sm-text);
  margin-bottom: 6px; padding-left: 6px;
}
#statusModal .sm-activity-meta{
  display: inline-flex; align-items: center; gap: 6px; padding-left: 6px;
}
#statusModal .sm-chip-muted{
  display:inline-flex; align-items:center; padding: 3px 8px;
  font-size: 10px; font-weight: 700; border-radius: 999px;
  background: var(--sm-bg-soft); border: 1px solid var(--sm-border); color: var(--sm-muted);
}

/* ===== Vertical Timeline (clean) ===== */
#statusModal .sm-timeline .vtl{
  --rail: #e6e8eb;
  --dot:  #cfd3d8;
  --dot-border: #bfc5cc;
  --done: #22c55e;         /* green for completed */
  --done-rail: #bfe7cc;    /* light green rail */

  max-height: 320px;       /* <-- vertical scroll height */
  overflow-y: auto;
  padding: 12px 10px 12px 0;
}

/* Row layout: date | rail | content */
#statusModal .vtl-row{
  display: grid;
  grid-template-columns: 140px 24px 1fr;
  gap: 10px 14px;
  position: relative;
  padding: 8px 0;
  align-items: start;
}

/* subtle divider between rows */
#statusModal .vtl-row + .vtl-row{
  border-top: 1px dashed var(--sm-border);
}

/* left meta (date/time) */
#statusModal .vtl-meta{
  text-align: right;
  padding-right: 4px;
}
#statusModal .vtl-date{
  font-weight: 800;
  font-size: 12px;
  color: var(--sm-text);
}
#statusModal .vtl-time{
  font-size: 11px;
  color: var(--sm-muted);
  margin-top: 2px;
}

/* middle rail + dot */
#statusModal .vtl-rail{
  position: relative;
  min-height: 32px;
}
#statusModal .vtl-rail::before{
  content:'';
  position:absolute;
  left: 11px;
  top: 0; bottom: 0;
  width: 2px;
  background: var(--rail);
}
#statusModal .vtl-dot{
  position:absolute;
  left: 6px; top: 8px;           /* aligns to date/time row */
  width: 12px; height: 12px;
  border-radius: 50%;
  background: var(--dot);
  border: 1px solid var(--dot-border);
}

/* right content */
#statusModal .vtl-title{
  font-size: 12.5px;
  font-weight: 800;
  color: var(--sm-text);
  margin-bottom: 2px;
}
#statusModal .vtl-sub{
  font-size: 12px;
  color: var(--sm-muted);
}

/* Completed state turns rail segments & dot green */
#statusModal .vtl-row.is-complete .vtl-rail::before{
  background: var(--done-rail);
}
#statusModal .vtl-row.is-complete .vtl-dot{
  background: var(--done);
  border-color: #19a154;
}

/* Upcoming: keep everything muted */
#statusModal .vtl-row.is-upcoming .vtl-title{ color: #4b5563; }
#statusModal .vtl-row.is-upcoming .vtl-sub{ color: #9aa3af; }

/* Compact on small screens */
@media (max-width: 576px){
  #statusModal .vtl-row{
    grid-template-columns: 120px 20px 1fr;
    gap: 8px 12px;
  }
  #statusModal .vtl-title{ font-size: 12px; }
  #statusModal .vtl-date{ font-size: 11.5px; }
  #statusModal .vtl-time, #statusModal .vtl-sub{ font-size: 11px; }
}

/* ===== Make ALL timeline dots the same color & center the block ===== */

/* 1) Center the entire timeline block inside the card */
#statusModal .sm-timeline .vtl {
  max-width: 680px;     /* tweak width as you like */
  margin: 0 auto;       /* centers the scroll area */
}

/* 2) Single-color dots + uniform rail */
#statusModal .sm-timeline .vtl {
  --rail-color:  #e6e8eb; /* rail line */
  --dot-color:   #6b7280; /* dot fill (neutral slate) */
  --dot-border:  #9ca3af; /* dot border */
}

/* base rail & dot */
#statusModal .vtl-rail::before { background: var(--rail-color); }
#statusModal .vtl-dot {
  background: var(--dot-color);
  border-color: var(--dot-border);
}

/* remove state styling so all rows look the same */
#statusModal .vtl-row.is-complete .vtl-rail::before,
#statusModal .vtl-row.is-upcoming .vtl-rail::before {
  background: var(--rail-color);
}
#statusModal .vtl-row.is-complete .vtl-dot,
#statusModal .vtl-row.is-upcoming .vtl-dot {
  background: var(--dot-color);
  border-color: var(--dot-border);
}

/* (optional) center the section title too */
#statusModal .sm-timeline .sm-card-title { text-align: center; }


/* Center the section title and the whole timeline block */
#statusModal .sm-timeline .sm-card-title { text-align: center; }
#statusModal .sm-timeline .vtl {
  max-width: 720px;   /* tweak width as you like */
  margin: 0 auto;     /* centers the scroll area in the card */
}

/* Make the rail exactly in the middle; left/right columns symmetric */
#statusModal .vtl-row {
  grid-template-columns: 1fr 28px 1fr;   /* left | rail | right */
  align-items: start;
}

/* Center the vertical line within its column */
#statusModal .vtl-rail { position: relative; width: 28px; }
#statusModal .vtl-rail::before {
  left: 50%;
  transform: translateX(-50%);           /* center the 2px line */
  width: 2px;
}

/* Center the dot on the line */
#statusModal .vtl-dot {
  left: 50%;
  transform: translateX(-50%);
}

/* Keep left meta right-aligned and right content left-aligned */
#statusModal .vtl-meta    { text-align: right;  padding-right: 6px; }
#statusModal .vtl-content { text-align: left;   padding-left: 6px; }

/* (optional) narrow on mobile but keep the rail centered */
@media (max-width: 576px) {
  #statusModal .sm-timeline .vtl { max-width: 100%; }
  #statusModal .vtl-row { grid-template-columns: 1fr 24px 1fr; }
}

/* Remove row separators so the vertical rail looks continuous */
#statusModal .vtl-row + .vtl-row { border-top: none; }

/* Keep the rail column centered (from earlier) */
#statusModal .vtl-row { grid-template-columns: 1fr 28px 1fr; }
#statusModal .vtl-rail { position: relative; width: 28px; }

/* Draw connectors: top-half + bottom-half for every row */
#statusModal .vtl-rail::before,
#statusModal .vtl-rail::after {
  content: '';
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  width: 2px;
  background: var(--rail-color, #e6e8eb); /* use your neutral rail */
}

/* top half connects to previous row; bottom half to next row */
#statusModal .vtl-rail::before { top: 0;    bottom: 50%; }
#statusModal .vtl-rail::after  { top: 50%;  bottom: 0;   }

/* Hide the first top segment and the last bottom segment */
#statusModal .vtl > .vtl-row:first-child .vtl-rail::before { display: none; }
#statusModal .vtl > .vtl-row:last-child  .vtl-rail::after  { display: none; }

/* Center the dot exactly on the joint of the two halves */
#statusModal .vtl-dot {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--dot-color, #6b7280);
  border: 1px solid var(--dot-border, #9ca3af);
  z-index: 1; /* sit above the rail */
}

/* ================= Materio "near-white purple" skin ================= */
#statusModal{
  /* Palette */
  --m-bg:        #ffffff;                 /* cards/background */
  --m-soft:      #f7f6ff;                 /* very light purple band */
  --m-soft-2:    #f1efff;                 /* chip bg */
  --m-border:    #e9e5ff;                 /* purple-tinted border */
  --m-ink:       #2f2b3d;                 /* primary text */
  --m-muted:     #6f6b7d;                 /* secondary text (Materio-ish) */
  --m-accent:    var(--bs-primary, #7367f0);
  --m-accent-ink:#5b57c7;                 /* chip text */
  --m-rail:      #e6e2ff;                 /* timeline rail */
  --m-dot:       #6f66f1;                 /* timeline dot */
  --m-dot-brd:   #cfcaff;                 /* dot border */
  --m-shadow:    0 12px 28px rgba(115,103,240,.07); /* soft purple shadow */
}

/* Shell */
#statusModal .status-modal{
  border-radius: 16px;
  border: 1px solid var(--m-border);
  background: var(--m-bg);
  box-shadow: var(--m-shadow);
  overflow: hidden;
}

/* ---------- HERO (From | Status | To) ---------- */
#statusModal .sm-hero{
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 16px; align-items: center;
  padding: 16px 18px;
  background: var(--m-soft);
  border-bottom: 1px solid var(--m-border);
}
#statusModal .sm-label{
  font-size: 11px; text-transform: uppercase; letter-spacing: .2px;
  color: var(--m-muted);
}
#statusModal .sm-city{ font-size: 18px; font-weight: 800; color: var(--m-ink); }
#statusModal .sm-chip{
  display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px;
  background: var(--m-soft-2);
  border: 1px solid var(--m-border);
  color: var(--m-accent-ink); font-weight: 700; font-size: 12px;
}
#statusModal .sm-date{ font-size: 11px; color: var(--m-muted); margin-top: 6px; }

/* ---------- Card container ---------- */
#statusModal .sm-card{
  margin: 14px 16px 18px;
  border: 1px solid var(--m-border);
  border-radius: 12px;
  background: var(--m-bg);
  box-shadow: 0 8px 18px rgba(115,103,240,.04);
  overflow: hidden;
}
#statusModal .sm-card-title{
  padding: 10px 12px;
  border-bottom: 1px solid var(--m-border);
  font-size: 12px; font-weight: 800; letter-spacing: .3px;
  color: var(--m-ink); text-transform: uppercase;
  background: var(--m-soft);
}

/* ---------- Shipment Summary tables ---------- */
#statusModal .sm-summary{ display:grid; grid-template-columns:1fr 1fr; }
#statusModal .sm-col{
  padding: 10px 12px 12px;
  border-right: 1px dashed var(--m-border);
}
#statusModal .sm-col:last-child{ border-right: none; }

#statusModal .sm-table{
  width:100%; border-collapse:collapse; font-size:11px; line-height:1.3;
}
#statusModal .sm-table th{
  width:44%; text-align:left; color:var(--m-muted);
  text-transform:uppercase; letter-spacing:.2px;
  padding:6px 6px; border-bottom:1px dashed var(--m-border);
}
#statusModal .sm-table td{
  padding:6px 6px; border-bottom:1px dashed var(--m-border);
  color:var(--m-ink); font-weight:700; word-break:break-word;
}
/* Subtle row hover */
#statusModal .sm-table tr:hover th,
#statusModal .sm-table tr:hover td{ background:#fbfaff; }

/* Stack summary on small screens */
@media (max-width: 576px){
  #statusModal .sm-summary{ grid-template-columns:1fr; }
  #statusModal .sm-col{ border-right:none; border-bottom:1px dashed var(--m-border); }
  #statusModal .sm-col:last-child{ border-bottom:none; }
}

/* ---------- (Optional) Vertical timeline tones, if present ---------- */
#statusModal .sm-timeline .sm-card-title{ text-align:center; }
#statusModal .sm-timeline .vtl{
  max-width: 720px;
  margin: 0 auto;
  --rail-color: var(--m-rail);
  --dot-color:  var(--m-dot);
  --dot-border: var(--m-dot-brd);
}
/* center layout (from your last version) */
#statusModal .vtl-row{ grid-template-columns:1fr 28px 1fr; }
#statusModal .vtl-rail{ position:relative; width:28px; }
#statusModal .vtl-rail::before{ left:50%; transform:translateX(-50%); width:2px; }
#statusModal .vtl-dot{
  left:50%; top:50%; transform:translate(-50%,-50%);
  width:10px; height:10px; border-radius:50%;
  background:var(--dot-color); border:1px solid var(--dot-border); z-index:1;
}
/* connected halves */
#statusModal .vtl-rail::before,
#statusModal .vtl-rail::after{
  content:''; position:absolute; left:50%; transform:translateX(-50%);
  width:2px; background:var(--rail-color);
}
#statusModal .vtl-rail::before{ top:0; bottom:50%; }
#statusModal .vtl-rail::after { top:50%; bottom:0; }
#statusModal .vtl > .vtl-row:first-child .vtl-rail::before{ display:none; }
#statusModal .vtl > .vtl-row:last-child  .vtl-rail::after { display:none; }

#statusModal .vtl-title{ font-size:12.5px; font-weight:800; color:var(--m-ink); }
#statusModal .vtl-sub  { font-size:12px;   color:var(--m-muted); }
#statusModal .vtl-date { font-weight:800;  font-size:12px; color:var(--m-ink); }
#statusModal .vtl-time { font-size:11px;   color:var(--m-muted); }


/* ===== Compact / Chhote fonts for Status Modal ===== */
#statusModal {
  --sm-fxs: 10px;   /* extra small */
  --sm-fs:  11px;   /* small */
  --sm-fm:  12px;   /* medium (reduced) */
}

/* Hero */
#statusModal .sm-hero{ padding: 12px 14px; }
#statusModal .sm-label{ font-size: 10px; }
#statusModal .sm-city{ font-size: 15px; font-weight: 800; }
#statusModal .sm-chip{ font-size: 10px; padding: 4px 10px; }
#statusModal .sm-date{ font-size: 10px; margin-top: 4px; }

/* Card titles */
#statusModal .sm-card{ margin: 10px 12px 12px; }
#statusModal .sm-card-title{
  font-size: var(--sm-fs);
  padding: 8px 10px;
}

/* Summary tables */
#statusModal .sm-col{ padding: 8px 10px 10px; }
#statusModal .sm-table{ font-size: var(--sm-fs); line-height: 1.2; }
#statusModal .sm-table th,
#statusModal .sm-table td{ padding: 4px 5px; }

/* Timeline wrapper (if using vertical timeline) */
#statusModal .sm-timeline .vtl{ max-height: 300px; }

/* Timeline rows centered rail (keep your existing rail CSS) */
#statusModal .vtl-row{ gap: 8px 12px; padding: 6px 0; }
#statusModal .vtl-date{ font-size: var(--sm-fm); }
#statusModal .vtl-time{ font-size: var(--sm-fxs); }
#statusModal .vtl-title{ font-size: 11.5px; }
#statusModal .vtl-sub{ font-size: 10.5px; }

/* Chips in timeline (date/time badges if any) */
#statusModal .sm-chip-muted{ font-size: var(--sm-fxs); padding: 2px 6px; }

/* Mobile: a pinch smaller */
@media (max-width: 576px){
  #statusModal .sm-city{ font-size: 14px; }
  #statusModal .sm-label{ font-size: 9.5px; }
  #statusModal .sm-card-title{ font-size: 10.5px; }
  #statusModal .sm-table{ font-size: 10px; }
  #statusModal .vtl-title{ font-size: 11px; }
  #statusModal .vtl-sub,
  #statusModal .vtl-time{ font-size: 10px; }
}

.badge {

    transition: transform 0.2s ease-in-out;
}
.badge:hover {
    transform: scale(1.05);
    cursor: pointer;
}

/* Loading overlay */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 8px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-overlay .text-muted {
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ===== No Tracking Data States ===== */
.no-tracking-state,
.no-activities-state {
    padding: 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.no-tracking-icon,
.no-activities-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 1.75rem;
}

.no-tracking-title {
    color: #334155;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
}

.no-activities-title {
    color: #475569;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.no-tracking-message,
.no-activities-message {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.no-tracking-hint {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.no-tracking-hint i {
    font-size: 0.9rem;
    margin-right: 0.25rem;
}

/* Hover effects */
.no-tracking-state:hover,
.no-activities-state:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.no-tracking-icon:hover,
.no-activities-icon:hover {
    background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
    color: #475569;
    transition: all 0.3s ease;
}

/* ===== Reset Filters Button Styling ===== */
.reset-filters-btn {
    padding: 0.75rem 2rem;
    font-weight: 600;
    font-size: 0.95rem;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    color: #64748b;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.reset-filters-btn:hover {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-color: #cbd5e1;
    color: #475569;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.reset-filters-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.reset-filters-btn i {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.reset-filters-btn:hover i {
    transform: rotate(180deg);
}

/* Add a subtle animation on hover */
.reset-filters-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s ease;
}

.reset-filters-btn:hover::before {
    left: 100%;
}

</style>

<template>

        <!-- Modal -->
    <!-- Modal -->
<!-- Status Modal (Clean / Minimal) -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content status-modal p-0">

      <!-- Header: From | Status | To -->
      <div class="sm-hero">
        <div class="sm-loc">
          <div class="sm-label">From</div>
          <div class="sm-city">{{ statusModalShipment?.origin_city ?? '—' }}</div>
        </div>

        <div class="sm-state">
          <span class="sm-chip">{{ currentStatus }}</span>
          <div class="sm-date">{{ formatDate(statusModalShipment?.last_activity) }}</div>
        </div>

        <div class="sm-loc">
          <div class="sm-label">To</div>
          <div class="sm-city">{{ statusModalShipment?.destination_city ?? '—' }}</div>
        </div>
      </div>

      <!-- Shipment Summary -->
      <section class="sm-card">
        <header class="sm-card-title">Shipment Summary</header>

        <div class="sm-summary">
          <!-- Left -->
          <div class="sm-col">
            <table class="sm-table">
              <tbody>
                <tr>
                  <th>Tracking #</th>
                  <td>{{ statusModalShipment?.tracking_number ?? '—' }}</td>
                </tr>
                <tr>
                  <th>No. of pieces</th>
                  <td>{{ trackingData?.packet_list?.[0]?.['booked_packet_no_piece'] ?? statusModalShipment?.no_of_pieces ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Shipper name</th>
                  <td>{{ trackingData?.packet_list?.[0]?.['shipment_name_eng'] ?? 'ZEBTAN COLLECTION' }}</td>
                </tr>
                <tr>
                  <th>Shipper address</th>
                  <td>
                    {{ trackingData?.packet_list?.[0]?.['shipment_address'] ?? 'FLAT NO 8 REHMAN MANZIL STREET NO 6 BURNS ROAD KARACHI' }}
                    <template v-if="statusModalShipment?.origin_city">, {{ statusModalShipment?.origin_city }}</template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Right -->
          <div class="sm-col">
            <table class="sm-table">
              <tbody>
                <tr>
                  <th>Reference # / Order ID</th>
                  <td>{{ trackingData?.packet_list?.[0]?.['booked_packet_order_id'] ?? statusModalShipment?.order_id ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Packet weight</th>
                  <td>{{ formatKg(trackingData?.packet_list?.[0]?.['booked_packet_weight'] ?? statusModalShipment?.weight) }} (Kgs)</td>
                </tr>
                <tr>
                  <th>Consignee name</th>
                  <td>{{ trackingData?.packet_list?.[0]?.['consignment_name_eng'] ?? statusModalShipment?.consignee_name ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Consignee address</th>
                  <td>
                    {{ trackingData?.packet_list?.[0]?.['consignment_address'] ?? statusModalShipment?.consignee_address ?? '—' }}
                    <template v-if="statusModalShipment?.destination_city">, {{ statusModalShipment?.destination_city }}</template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>



      <!-- Tracking Activities (Horizontal, multi per frame) -->
      <!-- TRACKING TIMELINE (Vertical, Scrollable) -->
<section class="sm-card sm-timeline">
  <header class="sm-card-title">Tracking Timeline</header>

  <!-- Loading state -->
  <div v-if="isFetchingTracking" class="text-center py-4">
    <div class="spinner-border spinner-border-sm me-2"></div>
    <span>Loading tracking data...</span>
  </div>

  <!-- No tracking data -->
  <div v-else-if="!trackingData || !trackingData.packet_list || trackingData.packet_list.length === 0" class="text-center py-5">
    <div class="no-tracking-state">
      <div class="no-tracking-icon">
        <i class="ri-search-line"></i>
      </div>
      <h5 class="no-tracking-title">No Tracking Information Available</h5>
      <p class="no-tracking-message">
        We couldn't find any tracking details for this shipment at the moment.
        This could mean the shipment is still being processed or hasn't been picked up yet.
      </p>
      <div class="no-tracking-hint">
        <i class="ri-time-line me-1"></i>
        <span>Tracking updates usually appear within 24-48 hours after pickup</span>
      </div>
    </div>
  </div>

  <!-- Real tracking timeline -->
  <div v-else class="vtl" role="list">
    <!-- Timeline items -->
    <div
      v-for="(activity, index) in reversedActivities"
      :key="index"
      class="vtl-row"
      :class="index === 0 ? 'is-complete' : 'is-complete'"
      role="listitem"
    >
      <div class="vtl-meta">
        <div class="vtl-date">{{ formatTrackingDate(activity['Activity_Date'] || activity['Activity Date'] || activity['activity_date'] || activity['activityDate']) }}</div>
        <div class="vtl-time">{{ formatTrackingTime(activity['Activity_Time'] || activity['Activity Time'] || activity['activity_time'] || activity['activityTime']) }}</div>
      </div>
      <div class="vtl-rail">
        <span class="vtl-dot" aria-hidden="true"></span>
      </div>
      <div class="vtl-content">
        <div class="vtl-title">{{ activity['Staus'] || activity['Status'] || activity['status'] || 'Status Update' }}</div>
        <div class="vtl-sub">
          <span v-if="activity['Reciever Name'] || activity['Receiver Name'] || activity['receiver_name']">
            Receiver: {{ activity['Reciever Name'] || activity['Receiver Name'] || activity['receiver_name'] }}
          </span>
          <span v-else-if="activity['Reason'] || activity['reason']">Reason: {{ activity['Reason'] || activity['reason'] }}</span>
        </div>
      </div>
    </div>

    <!-- Fallback if no tracking detail found -->
    <div v-if="trackingActivities.length === 0" class="text-center py-4">
      <div class="no-activities-state">
        <div class="no-activities-icon">
          <i class="ri-route-line"></i>
        </div>
        <h6 class="no-activities-title">No Activity Updates Yet</h6>
        <p class="no-activities-message">
          This shipment doesn't have any tracking activities recorded yet.
          The courier service may still be processing the shipment.
        </p>
      </div>
    </div>
  </div>
</section>


    </div>
  </div>
</div>


<!-- hidden trigger (if you're using the button approach) -->
<button id="statusModalOpen" data-bs-toggle="modal" data-bs-target="#statusModal" style="display:none">open</button>


    <!-- Cancel confirm modal -->
    <button data-bs-toggle="modal" data-bs-target="#cancelConfirmModal" id="cancelModalPopup" style="display: none">open</button>
    <div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <div class="modal-body">
                    <button type="button" class="btn-close position-absolute end-0 top-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="text-danger mb-3"><i class="bi bi-exclamation-circle" style="font-size: 3rem"></i></div>
                    <h4 class="mb-2">Are you sure?</h4>
                    <p class="text-muted">Do you really want to cancel this shipment?</p>
                    <div class="d-flex justify-content-center mt-3 gap-3">
                        <button @click="toCancel = null" id="hideCancelModal" type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            No
                        </button>
                        <button @click="cancelOrder(toCancel as string)" type="button" class="btn btn-danger">Yes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View details modal -->
    <button id="viewModalOpen" data-bs-toggle="modal" data-bs-target="#viewShipmentModal" style="display: none">open</button>
    <div class="modal fade" id="viewShipmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content p-3">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Shipment Details
                        <small v-if="selectedShipment" class="ms-2 text-muted"> ({{ selectedShipment.tracking_number || '—' }}) </small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div v-if="selectedShipment" class="details-inner">
                        <div class="details-grid">
                            <div>
                                <div class="label">Tracking #</div>
                                <div class="value">{{ selectedShipment.tracking_number || '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Order ID</div>
                                <div class="value">{{ selectedShipment.order_id || '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Consignee</div>
                                <div class="value">{{ selectedShipment.consignee_name || '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Phone</div>
                                <div class="value">{{ selectedShipment.consignee_phone || '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Origin → Destination</div>
                                <div class="value">{{ selectedShipment.origin_city || '—' }} → {{ selectedShipment.destination_city || '—' }}</div>
                            </div>
                            <div>
                                <div class="label">COD Amount</div>
                                <div class="value">{{ money(selectedShipment.cod_amount) }} PKR</div>
                            </div>
                            <div>
                                <div class="label">Weight</div>
                                <div class="value">{{ selectedShipment.weight ? selectedShipment.weight + ' g' : '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Pieces</div>
                                <div class="value">{{ selectedShipment.no_of_pieces }}</div>
                            </div>
                            <div>
                                <div class="label">Status</div>
                                <div class="value">
                                    <span
                                        class="badge"
                                        :class="statusClassMap[selectedShipment.is_cancelled ? 'Cancelled' : selectedShipment.status]"
                                    >
                                        {{ selectedShipment.is_cancelled ? 'Cancelled' : selectedShipment.status || '—' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div class="label">Booking Date</div>
                                <div class="value">{{ formatDate(selectedShipment.created_at) }}</div>
                            </div>
                            <div>
                                <div class="label">Consignee Address</div>
                                <div class="value">{{ selectedShipment.consignee_address }}</div>
                            </div>
                            <div>
                                <div class="label">Advance Payment</div>
                                <div class="value">
                                    {{ selectedShipment.advance_payment == 0 ? 'No Advance Payment' : selectedShipment.advance_payment }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button
                        v-if="selectedShipment && !selectedShipment.is_cancelled && selectedShipment.status === 'Pickup Request not Send'"
                        class="btn btn-danger"
                        @click="cancellationProposal(selectedShipment.tracking_number)"
                        data-bs-dismiss="modal"
                    >
                        Cancel Shipment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice modal trigger (hidden) -->
<button id="invoiceModalOpen" data-bs-toggle="modal" data-bs-target="#invoiceModal" style="display:none">open</button>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title">Charges Breakdown</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="invoice-card">
          <div class="invoice-title">Invoice</div>
          <!-- Dummy content for now as requested -->
          <div class="invoice-row" style="margin-bottom: 6px;">
            <div>Invoice #</div>
            <div class="invoice-number">{{ invoiceShipment?.shipping_charges.invoice_cheque_no }}</div>
          </div>

          <div class="invoice-row">
            <div>Delivery Charges</div>
            <div>{{ invoiceShipment?.shipping_charges.delivery }}</div>
          </div>
          <div class="invoice-row">
            <div>Fuel ({{ invoiceShipment?.shipping_charges.fuel_surcharge_percentage }}%)</div>
            <div>{{ invoiceShipment?.shipping_charges.fuel_surcharge_amount }}</div>
          </div>
          <div class="invoice-row">
            <div>GST ({{ invoiceShipment?.shipping_charges.gst }}%)</div>
            <div>{{ invoiceShipment?.shipping_charges.gst_amount }}</div>
          </div>
          <div class="invoice-row">
               <div>Govt Tax (4%)</div>
            <div>{{ invoiceShipment?.shipping_charges.tax_amount.toFixed(2) }}</div>

          </div>
          <div class="invoice-row total">
            <div>Total</div>
            <div>{{ invoiceShipment?.shipping_charges.gross_charges }}</div>
          </div>

          </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


    <!-- Page -->
    <div v-if="!is_loading" class="container-xxl container-p-y flex-grow-1">
        <div class="card mb-4 p-3">
            <form id="filterForm">
                <div class="row">
                    <div class="col-md-6">
                        <label><strong>Date Range:</strong></label>
                        <input type="text" v-model="filters.dateRange" name="daterange" id="daterange" class="form-control" />
                    </div>
                    <div class="col-md-6">
                        <label><strong>Status:</strong></label>
                        <select name="status" v-model="filters.status" id="status" class="form-control select2">
                            <option value="">All Status</option>
                            <option value="Drop Off at Express Center">Drop Off at Express Center</option>
                            <option value="Shipment Picked">Shipment Picked</option>
                            <option value="Being Return">Being Return</option>
                            <option value="Ready for Return">Ready for Return</option>
                            <option value="Dispatched">Dispatched</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Pending">Pending</option>
                            <option value="Missroute">Missroute</option>
                            <option value="Returned to shipper">Returned to shipper</option>
                            <option value="Arrived at Station">Arrived at Station</option>
                            <option value="Assign to Courier">Assign to Courier</option>
                            <option value="Consignment Booked">Consignment Booked</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Pickup Request Sent">Pickup Request Sent</option>
                            <option value="48 Hours Auto Canceled">48 Hours Auto Canceled</option>
                            <option value="Pickup Request not Send">Pickup Request not Send</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <label><strong>Origin City:</strong></label>
                        <select id="origin_city" class="form-control select2">
                            <option value="">All Origins</option>
                            <option v-for="city in cities" :key="city.id + '-o'" :value="city.city">{{ city.city }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label><strong>Destination City:</strong></label>
                        <select id="destination_city" class="form-control select2">
                            <option value="">All Destinations</option>
                            <option v-for="city in cities" :key="city.id + '-d'" :value="city.city">{{ city.city }}</option>
                        </select>
                    </div>
                </div>

                <!-- Reset All Button -->
                <div class="row mt-3">
                    <div class="col-12">
                        <button @click="clearAll" type="button" class="btn btn-outline-secondary reset-filters-btn">
                            <i class="ri-refresh-line me-2"></i>
                            Reset All Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card position-relative">
            <div class="d-flex justify-content-between align-items-center px-3 pt-3">
                <h5 class="card-header border-0 p-0">Manage Booked Packets</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" @click="mergeSelected">Bulk Slips</button>
                </div>
            </div>

            <!-- Loading overlay -->
            <div v-if="is_fetching" class="loading-overlay">
                <div class="loading-spinner"></div>
                <div class="mt-3 text-muted">Fetching data...</div>
            </div>
            <div class="table-responsive text-nowrap" id="packetTableWrapper">
                <table id="myTable" class="table-bordered table align-middle" style="font-size: 12px">
                    <thead>
                        <tr>
                            <th style="text-align: center; padding-left: 5px; padding-right: 0px;">
                                <input type="checkbox" :checked="allVisibleChecked" @change="toggleSelectAll" />
                            </th>
                            <th style="font-size: 10px; padding-left: 5px; padding-right: 5px; width: 4%;">S.no</th>
                            <th style="font-size: 10px; padding-left: 5px; width: 1%;">Order ID</th>
                            <th style="font-size: 10px; padding-left: 0px;">Tracking</th>
                            <th style="font-size: 10px; padding-left: 0px;">Status</th>
                            <th style="font-size: 10px; padding-left: 0px;">Customer Name</th>
                            <th style="font-size: 10px; padding-left: 0px; width: 15%;">Address</th>
                            <th style="font-size: 10px; padding-left: 0px;" class="text-center">COD</th>
                            <th style="font-size: 10px; padding-left: 0px;" class="text-center">Recieve Amount</th>
                            <th style="font-size: 10px; padding-left: 0px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="packetTableBody">
                        <tr v-for="(shipment, index) in shipments" :key="shipment.id">

                            <td style="text-align: center">
                                <input
                                    class="row-check"
                                    type="checkbox"
                                    :data-id="shipment.id"
                                    :checked="checkboxStates.get(shipment.id)"
                                    @change="onRowToggle(shipment.id, $event)"
                                    :key="`checkbox-${shipment.id}`"
                                />
                            </td>
                            <td style="padding-left: 0px; padding-right: 0px;">{{ index + 1 }}</td>
                            <td style="padding-left: 5px;">{{ shipment.order_id }}</td>
                            <td style="padding-left: 0px;">
                                <span style="font-weight: bold">{{ shipment.tracking_number }}</span
                                ><br />
                                <span
                                    style="
                                        font-size: 10px;
                                        background-color: #faf5ff; /* near white with a purple tint */
                                        padding: 2px 5px;
                                        border-radius: 10px;
                                        margin-top: 20px;
                                        border: 1px solid #ede7f6; /* faint lavender border */
                                        color: #7e57c2; /* subtle purple text */
                                    "
                                >
                                    {{ formatDate(shipment.picking_time) == "N/A" ? "Not Picked Yet" : formatDate(shipment.picking_time) }}
                                </span>
                            </td>
                            <td style="padding-left: 0px; padding-right: 0px;">
                                <span class="badge" @click="openStatusModal(shipment)" :class="statusClassMap[shipment.is_cancelled ? 'Cancelled' : shipment.status]">
                                    {{ shipment.is_cancelled ? 'Cancelled' : shipment.status || '—' }}
                                </span>
                                <br />
                                <span style="font-size: 10px; padding: 2px 5px; margin-top: 10px; border-radius: 10px; margin-top: 20px">
                                    {{ formatDate(shipment.last_activity) == 'N/A' ? "Status not changed yet" : formatDate(shipment.last_activity) }}
                                </span>
                            </td>
                            <td class="text-truncate" style="max-width: 160px; padding-left: 0px;">
                                <span style="font-weight: bold">{{ shipment.consignee_name || '—' }}</span
                                ><br />
                                <span
                                    style="
                                        font-size: 10px;
                                        background-color: #faf5ff; /* near white with a purple tint */
                                        padding: 2px 5px;
                                        border-radius: 10px;
                                        margin-top: 20px;
                                        border: 1px solid #ede7f6; /* faint lavender border */
                                        color: #7e57c2; /* subtle purple text */
                                    "
                                >
                                    {{ shipment.consignee_phone || '-' }}
                                </span>
                            </td>
                            <td style="font-size: 12px; width: 15%; padding-left: 0px;" :data-shipment-id="shipment.id">
                                <div class="address-container"
                                     @mouseenter="showTooltip(shipment.id, shipment.consignee_address)"
                                     @mouseleave="hideTooltip(shipment.id)">
                                    <span style="margin-bottom: 10px" v-html="formatAddress(shipment.consignee_address)"></span>
                                </div>
                                <br />
                                <span
                                       style="
                                        font-size: 10px;
                                        background-color: #faf5ff; /* near white with a purple tint */
                                        padding: 2px 5px;
                                        border-radius: 10px;
                                        margin-top: 20px;
                                        border: 1px solid #ede7f6; /* faint lavender border */
                                        color: #7e57c2; /* subtle purple text */
                                    "
                                >
                                    {{ cities.filter(city => city.id == shipment.destination_city)[0]?.city ?? shipment.destination_city }}
                                </span>
                            </td>
                            <!-- <td>{{ shipment.origin_city || '—' }} -> {{ shipment.destination_city || '—' }}</td> -->
                            <td style="width: 10%; padding-left: 0px; padding-right: 0px; text-align: center;">
                                <span style="font-weight: bold">{{ money(shipment.cod_amount) }}</span>
                                <br />
                                <span
                                       style="
                                        font-size: 10px;
                                        background-color: #faf5ff; /* near white with a purple tint */
                                        padding: 2px 5px;
                                        border-radius: 10px;
                                        margin-top: 20px;
                                        border: 1px solid #ede7f6; /* faint lavender border */
                                        color: #7e57c2; /* subtle purple text */
                                    "
                                    >DC {{ money(shipment?.shipping_charges?.shipment_charges) }}</span
                                >
                            </td>

                            <td style="padding-left: 0px; padding-right: 0px; text-align: center;">
                                <!-- TOP value (click to open invoice modal) -->
                                <span style="cursor: pointer; font-weight: 600;"
                                        @click="openInvoice(shipment)">
                                    {{ money(shipment?.shipping_charges?.receivable) }}
                                </span>
                                <br />
                                <span
                                    style="
                                    font-size: 10px;
                                    background-color: #faf5ff;
                                    padding: 2px 5px;
                                    border-radius: 10px;
                                    margin-top: 20px;
                                    border: 1px solid #ede7f6;
                                    color: #7e57c2;
                                    "
                                >
                                    {{ '17-Mar-2025' }}
                                </span>
                                </td>

                            <td class="d-flex gap-2" style="padding-top: 30px;">
                                <button v-if="shipment.status === 'Pickup Request not Send'" :disabled="shipment.is_cancelled" @click="cancellationProposal(shipment.tracking_number)" style="color: #dc3545; border: none; background: none; padding: 5px;">
                                    <i class="ri-close-line text-[28px]" style="color: #dc3545;"></i>
                                </button>
                                <button @click="viewOrder(shipment)">
                                    <i class="ri-eye-line text-[28px]"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    <!-- Address Tooltip -->
    <div v-if="activeTooltip"
         class="address-tooltip"
         :style="{
           left: tooltipPositions[activeTooltip]?.left + 'px',
           top: tooltipPositions[activeTooltip]?.top + 'px'
         }">
        <div class="tooltip-content">
            <div class="tooltip-header">
                <i class="ri-map-pin-line"></i>
                <span>Full Address</span>
            </div>
            <div class="tooltip-body">
                {{ shipments.find(s => s.id === activeTooltip)?.consignee_address || 'N/A' }}
            </div>
        </div>
        <div class="tooltip-arrow"></div>
    </div>

    <div v-else style="display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100vh">
        <span class="loader"></span>
    </div>
</template>
