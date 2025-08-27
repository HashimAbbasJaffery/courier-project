<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

// ---- State ----
const shipperAdvices = ref<any[]>([]);
const cities = ref<any[]>([]);
const filters = reactive({
    dateRange: '',
    originCity: '',
    destination_city: '',
});
const is_loading = ref(true);
const is_fetching = ref(false);
const is_processing = ref(false);
const message = ref<{ type: 'success' | 'error' | 'info', text: string } | null>(null);

// NEW: selection state
const selectedIds = ref<number[]>([]);
const allVisibleChecked = ref(false);

// Modal state for shipper advice update
const shipperAdviceModal = ref<any | null>(null);
const shipperAdviceForm = reactive({
    shipper_advice_status: '',
    shipper_remarks: ''
});
const isSubmitting = ref(false);

// Helpers
const money = (n?: number | string) => ((n ?? n === 0) ? new Intl.NumberFormat('en-PK').format(Number(n)) : '—');

function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);

  const day = String(date.getDate()).padStart(2, "0");
  const month = date.toLocaleString("en-US", { month: "short" }); // Aug, Sep, etc.
  const year = String(date.getFullYear()).slice(-2); // last 2 digits of year

  return `${day}-${month}-${year}`;
}

// ---- API ----
const fetchShipperAdvices = async () => {
    try {
        is_fetching.value = true;
        message.value = null;
        
        console.log('Fetching shipper advices with filters:', filters);
        
        const response = await axios.get('/api/shipper-advices/direct', {
            params: {
                from_date: filters.dateRange.split(' - ')[0] || undefined,
                origin_city: filters.originCity || undefined,
                destination_city: filters.destination_city || undefined,
            }
        });
        
        console.log('API Response:', response.data);
        
        if (response.data.status === 'success' || response.data.data) {
            shipperAdvices.value = response.data.data || [];
            if (shipperAdvices.value.length > 0) {
                message.value = {
                    type: 'success',
                    text: `Successfully fetched ${shipperAdvices.value.length} shipper advice records`
                };
            }
        } else {
            console.error('API Error:', response.data.message);
            message.value = {
                type: 'error',
                text: response.data.message || 'API returned an error'
            };
        }
    } catch (error) {
        console.error('Error fetching shipper advices:', error);
        console.error('Error response:', error.response?.data);
        console.error('Error status:', error.response?.status);
        
        message.value = {
            type: 'error',
            text: 'Failed to fetch shipper advice data. Please check your connection and try again.'
        };
    } finally {
        is_fetching.value = false;
    }
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
    nextTick(() => {
        const boxes = document.querySelectorAll<HTMLInputElement>('input.row-check');
        const idsOnPage = Array.from(boxes).map((b) => Number(b.dataset.id));
        const allOnPageSelected = idsOnPage.length > 0 && idsOnPage.every((id) => selectedIds.value.includes(id));
        allVisibleChecked.value = allOnPageSelected;
    });
};

const initializeDataTable = () => {
  const id = '#shipperAdviceTable';
  if ($.fn.DataTable.isDataTable(id)) {
    $(id).DataTable().destroy();
  }
  nextTick(() => {
    const dt = $(id).DataTable({
    autoWidth: true,     // ✅ stop recalculating widths
    scrollX: true,
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
    await getCities();
    
    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    filters.dateRange = `${thirtyDaysAgo.toISOString().split('T')[0]} - ${today.toISOString().split('T')[0]}`;
    
    await fetchShipperAdvices();

    nextTick(() => {
        initializeDataTable();

        nextTick(() => {
            $('#origin_city')
                .select2()
                .on('select2:open', () => {
                    isDropdownOpen.value = true;
                })
                .on('select2:close', () => {
                    isDropdownOpen.value = false;
                })
                .on('change', (e: any) => {
                    filters.originCity = $(e.target).val() as string;
                });
            $('#destination_city')
                .select2()
                .on('select2:open', () => {
                    isDropdownOpen.value = true;
                })
                .on('select2:close', () => {
                    isDropdownOpen.value = false;
                })
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
let filterTimeout: NodeJS.Timeout | null = null;
let isDropdownOpen = ref(false);

watch(filters, async (newFilters) => {
    // Clear any existing timeout
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
    
    console.log('Filters changed:', newFilters);
    
    // Don't fetch immediately if dropdowns are being opened or already processing
    if (isDropdownOpen.value || is_processing.value) {
        return;
    }
    
    // Set a new timeout to debounce the API calls
    filterTimeout = setTimeout(async () => {
        if (newFilters.dateRange && newFilters.dateRange.includes(' - ') && !is_processing.value) {
            console.log('Fetching data with filters:', newFilters);
            is_processing.value = true;
            
            try {
                // Only fetch if we have a valid date range
                await fetchShipperAdvices();
                initializeDataTable();
            } finally {
                is_processing.value = false;
            }
        }
    }, 800); // Increased delay to 800ms for smoother experience
}, { deep: true });

// Remove manual refresh function since it's no longer needed
// const refreshData = async () => {
//     if (filters.dateRange && filters.dateRange.includes(' - ')) {
//         await fetchShipperAdvices();
//         initializeDataTable();
//     }
// };

// Add refresh function for real data
const refreshData = async () => {
    is_fetching.value = true;
    try {
        await fetchShipperAdvices();
    } catch (error) {
        console.error('Error refreshing data:', error);
        message.value = {
            type: 'error',
            text: 'Failed to refresh data. Please try again.'
        };
    } finally {
        is_fetching.value = false;
    }
};

// Export selected records
const exportSelected = () => {
    if (selectedIds.value.length === 0) return;
    
    const selectedData = shipperAdvices.value.filter(advice => 
        selectedIds.value.includes(advice.tracking_number || advice.id)
    );
    
    // Create CSV content
    const csvContent = [
        ['Tracking #', 'Customer Name', 'Phone', 'Address', 'Status', 'Amount', 'Origin', 'Destination', 'Date'],
        ...selectedData.map(advice => [
            advice.tracking_number || advice.track_number || 'N/A',
            advice.shipper_name || advice.consignment_name_eng || 'N/A',
            advice.shipper_phone || advice.consignment_phone || 'N/A',
            advice.shipper_address || advice.consignment_address || 'N/A',
            advice.status || advice.booked_packet_status || 'N/A',
            advice.amount || advice.booked_packet_collect_amount || 'N/A',
            advice.origin_city || advice.origin_city_name || 'N/A',
            advice.destination_city || advice.destination_city_name || 'N/A',
            advice.booking_date || advice.advice_date_created || 'N/A'
        ])
    ].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
    
    // Download CSV file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `shipper_advice_export_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    message.value = {
        type: 'success',
        text: `Exported ${selectedIds.value.length} records successfully`
    };
};

// Action functions
const viewDetails = (advice: any) => {
    message.value = {
        type: 'info',
        text: `Viewing details for ${advice.tracking_number || advice.track_number || 'N/A'}`
    };
    // You can implement a modal or redirect here
    console.log('View details:', advice);
};

const editAdvice = (advice: any) => {
    message.value = {
        type: 'info',
        text: `Editing ${advice.tracking_number || advice.track_number || 'N/A'}`
    };
    // You can implement edit functionality here
    console.log('Edit advice:', advice);
};

const deleteAdvice = (advice: any) => {
    if (confirm(`Are you sure you want to delete ${advice.tracking_number || advice.track_number || 'N/A'}?`)) {
        // Remove from the list
        const index = shipperAdvices.value.findIndex(item => 
            item.tracking_number === advice.tracking_number || item.track_number === advice.track_number
        );
        if (index > -1) {
            shipperAdvices.value.splice(index, 1);
            message.value = {
                type: 'success',
                text: 'Record deleted successfully'
            };
            
            // Reinitialize DataTable
            nextTick(() => {
                initializeDataTable();
            });
        }
    }
};

// Clear filters
const clearFilters = () => {
    filters.dateRange = '';
    filters.originCity = '';
    filters.destination_city = '';
    
    // Reset Select2 dropdowns
    $('#origin_city').val('').trigger('change');
    $('#destination_city').val('').trigger('change');
    $('#daterange').val('');
    
    message.value = {
        type: 'info',
        text: 'Filters cleared. Fetching fresh data...'
    };
    
    // Fetch fresh data from API
    fetchShipperAdvices();
};

// Open shipper advice modal
const openShipperAdviceModal = (advice: any) => {
    shipperAdviceModal.value = advice;
    shipperAdviceForm.shipper_advice_status = '';
    shipperAdviceForm.shipper_remarks = '';
    
    // Open the modal
    (document.getElementById('shipperAdviceModalOpen') as HTMLButtonElement)?.click();
};

// Submit shipper advice update
const submitShipperAdvice = async () => {
    if (!shipperAdviceForm.shipper_advice_status) {
        message.value = {
            type: 'error',
            text: 'Please select a status'
        };
        return;
    }
    
    try {
        isSubmitting.value = true;
        
        const payload = {
            api_key: 'your_api_key', // This should come from your config
            api_password: 'your_api_password', // This should come from your config
            data: [{
                id: shipperAdviceModal.value.id,
                cn_number: shipperAdviceModal.value.tracking_number || shipperAdviceModal.value.track_number,
                shipper_advice_status: shipperAdviceForm.shipper_advice_status,
                shipper_remarks: shipperAdviceForm.shipper_remarks
            }]
        };
        
        // Call the API
        const response = await axios.post('/api/shipper-advice/update', payload);
        
        if (response.data.status === 'success') {
            message.value = {
                type: 'success',
                text: 'Shipper advice updated successfully!'
            };
            
            // Close modal
            (document.getElementById('hideShipperAdviceModal') as HTMLButtonElement)?.click();
            
            // Refresh data
            await fetchShipperAdvices();
        } else {
            message.value = {
                type: 'error',
                text: response.data.message || 'Failed to update shipper advice'
            };
        }
    } catch (error) {
        console.error('Error updating shipper advice:', error);
        message.value = {
            type: 'error',
            text: 'Network error: Failed to update shipper advice'
        };
    } finally {
        isSubmitting.value = false;
    }
};



// ---- Selection handlers ----
const toggleSelectAll = () => {
    const boxes = document.querySelectorAll<HTMLInputElement>('input.row-check');
    const idsOnPage: number[] = [];
    boxes.forEach((el) => {
        const id = Number(el.dataset.id);
        idsOnPage.push(id);
    });

    if (!allVisibleChecked.value) {
        // add all visible ids
        const set = new Set(selectedIds.value);
        idsOnPage.forEach((id) => set.add(id));
        selectedIds.value = Array.from(set);
        allVisibleChecked.value = true;
    } else {
        // remove all visible ids
        const remove = new Set(idsOnPage);
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
    // Update header checkbox state for the current page
    nextTick(() => {
        const boxes = document.querySelectorAll<HTMLInputElement>('input.row-check');
        const idsOnPage = Array.from(boxes).map((b) => Number(b.dataset.id));
        const allOnPageSelected = idsOnPage.length > 0 && idsOnPage.every((id) => selectedIds.value.includes(id));
        allVisibleChecked.value = allOnPageSelected;
    });
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
    #shipperAdviceTable td,
    #shipperAdviceTable th {
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

:deep(#shipperAdviceTable thead th) {
  padding-left: 0 !important;
  padding-right: 0 !important;
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

/* Message styling */
.alert {
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background-color: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-info {
    background-color: #dbeafe;
    color: #1e40af;
    border-left: 4px solid #3b82f6;
}

.alert i {
    margin-right: 8px;
    font-size: 16px;
}

/* Table improvements */
.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

#shipperAdviceTable {
    margin-bottom: 0;
}

#shipperAdviceTable th {
    background-color: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #374151;
}

#shipperAdviceTable td {
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

#shipperAdviceTable tbody tr:hover {
    background-color: #f9fafb;
}

/* Filter form improvements */
#filterForm {
    border-radius: 12px;
}

#filterForm label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

#filterForm .form-control,
#filterForm .select2-container--default .select2-selection--single {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    transition: all 0.2s ease;
}

#filterForm .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Select2 dropdown improvements */
.select2-dropdown {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    animation: dropdownFadeIn 0.2s ease-out;
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.select2-results__option {
    padding: 8px 12px;
    transition: background-color 0.15s ease;
}

.select2-results__option--highlighted {
    background-color: #3b82f6 !important;
    color: white !important;
}

/* Button improvements */
.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    padding: 10px 20px;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:disabled {
    opacity: 0.7;
    transform: none;
    box-shadow: none;
}

/* Action buttons styling */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.btn-outline-primary:hover {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.btn-outline-success:hover {
    background-color: #198754;
    border-color: #198754;
    color: white;
}

.btn-outline-warning:hover {
    background-color: #ffc107;
    border-color: #ffc107;
    color: black;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

.btn-outline-info:hover {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
    color: black;
}

/* Table action buttons */
.table .btn-sm {
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 2px;
}

/* Filter form styling */
#filterForm {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

#filterForm label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

#filterForm .form-control {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

#filterForm .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Simple modal styling */
#shipperAdviceModal .modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

#shipperAdviceModal .modal-header {
    border-bottom: 1px solid #e9ecef;
    padding: 1rem 1.5rem;
}

#shipperAdviceModal .modal-body {
    padding: 1.5rem;
}

#shipperAdviceModal .form-label {
    font-weight: 500;
    color: #566a7f;
    margin-bottom: 0.5rem;
}

#shipperAdviceModal .form-select,
#shipperAdviceModal .form-control {
    border: 1px solid #d9dee3;
    border-radius: 6px;
}

#shipperAdviceModal .form-select:focus,
#shipperAdviceModal .form-control:focus {
    border-color: #696cff;
    box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
}

/* Spinner animation */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
</style>

<template>
    <!-- Page -->
    <div v-if="!is_loading" class="container-xxl container-p-y flex-grow-1">
        <div class="card mb-4 p-3">
            <form id="filterForm">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>Date Range:</strong></label>
                        <input type="text" v-model="filters.dateRange" name="daterange" id="daterange" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label><strong>Origin City:</strong></label>
                        <select id="origin_city" class="form-control select2">
                            <option value="">All Origins</option>
                            <option v-for="city in cities" :key="city.id + '-o'" :value="city.city">{{ city.city }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label><strong>Destination City:</strong></label>
                        <select id="destination_city" class="form-control select2">
                            <option value="">All Destinations</option>
                            <option v-for="city in cities" :key="city.id + '-d'" :value="city.city">{{ city.city }}</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div class="card position-relative">
            
            
            <!-- Message Display -->
            <div v-if="message" class="px-3 pb-2">
                <div :class="`alert alert-${message.type === 'error' ? 'danger' : message.type === 'success' ? 'success' : 'info'} alert-dismissible fade show`" role="alert">
                    <i :class="`bi bi-${message.type === 'error' ? 'exclamation-triangle' : message.type === 'success' ? 'check-circle' : 'info-circle'}`"></i>
                    {{ message.text }}
                    <button type="button" class="btn-close" @click="message = null"></button>
                </div>
            </div>
            
            <!-- Loading overlay -->
            <div v-if="is_fetching" class="loading-overlay">
                <div class="loading-spinner"></div>
                <div class="mt-3 text-muted">Fetching data...</div>
            </div>
            
            <div class="table-responsive text-nowrap" id="shipperAdviceTableWrapper">
                <table id="shipperAdviceTable" class="table-bordered table align-middle" style="font-size: 12px">
                    <thead>
                        <tr>
                            <th style="text-align: center; padding-left: 5px; padding-right: 0px;">
                                <input type="checkbox" :checked="allVisibleChecked" @change="toggleSelectAll" />
                            </th>
                            <th style="font-size: 10px; padding-left: 5px; padding-right: 5px; width: 4%;">S.no</th>
                            <th style="font-size: 10px; padding-left: 5px; width: 1%;">Tracking #</th>
                            <th style="font-size: 10px; padding-left: 0px;">Status</th>
                            <th style="font-size: 10px; padding-left: 0px;">Customer Name</th>
                            <th style="font-size: 10px; padding-left: 0px; width: 3%;">Address</th>
                            <th style="font-size: 10px; padding-left: 0px;" class="text-center">COD Amount</th>
                            <th style="font-size: 10px; padding-left: 0px;">Origin → Destination</th>
                            <th style="font-size: 10px; padding-left: 0px;">Advice Date</th>
                            <th style="font-size: 10px; padding-left: 0px;">Advice Text</th>
                            <th style="font-size: 10px; padding-left: 0px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="shipperAdviceTableBody">
                        <tr v-for="(advice, index) in shipperAdvices" :key="advice.track_number || index">
                            <td style="text-align: center">
                                <input
                                    class="row-check"
                                    type="checkbox"
                                    :data-id="advice.track_number || index"
                                    :checked="selectedIds.includes(advice.track_number || index)"
                                    @change="onRowToggle(advice.track_number || index, $event)"
                                />
                            </td>
                            <td style="padding-left: 0px; padding-right: 0px;">{{ index + 1 }}</td>
                            <td style="padding-left: 5px;">
                                <span style="font-weight: bold">{{ advice.track_number || '—' }}</span>
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
                                    {{ formatDate(advice.booked_packet_date) }}
                                </span>
                            </td>
                            <td style="padding-left: 0px; padding-right: 0px;">
                                <span class="badge" :class="statusClassMap[advice.booked_packet_status] || 'pending'">
                                    {{ advice.booked_packet_status || '—' }}
                                </span>
                            </td>
                            <td class="text-truncate" style="max-width: 160px; padding-left: 0px;">
                                <span style="font-weight: bold">{{ advice.consignment_name_eng || '—' }}</span>
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
                                    {{ advice.consignment_phone || '-' }}
                                </span>
                            </td>
                            <td style="font-size: 12px; width: 10%; padding-left: 0px;">
                                <span style="margin-bottom: 10px">{{ advice.consignment_address || '—' }}</span>
                            </td>
                            <td style="width: 10%; padding-left: 0px; padding-right: 0px; text-align: center;">
                                <span style="font-weight: bold">{{ money(advice.booked_packet_collect_amount) }}/-</span>
                            </td>
                            <td style="padding-left: 0px;">
                                <span style="font-weight: bold">{{ advice.origin_city_name || '—' }} → {{ advice.destination_city_name || '—' }}</span>
                            </td>
                            <td style="padding-left: 0px;">
                                <span style="font-weight: bold">{{ formatDate(advice.advice_date_created) }}</span>
                            </td>
                            <td style="padding-left: 0px; max-width: 200px;">
                                <span style="font-size: 11px; color: #666;">
                                    {{ advice.advice_text || 'No advice text' }}
                                </span>
                            </td>
                            <td style="padding-left: 0px; text-align: center;">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button @click="openShipperAdviceModal(advice)" class="btn btn-sm btn-outline-success" title="Update Shipper Advice">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <button @click="viewDetails(advice)" class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button @click="editAdvice(advice)" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button @click="deleteAdvice(advice)" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div v-else style="display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100vh">
        <span class="loader"></span>
    </div>

    <!-- Shipper Advice Update Modal -->
    <div class="modal fade" id="shipperAdviceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Shipper Advice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <form @submit.prevent="submitShipperAdvice">
                        <div class="mb-3">
                            <label for="shipper_advice_status" class="form-label">Status</label>
                            <select 
                                id="shipper_advice_status" 
                                v-model="shipperAdviceForm.shipper_advice_status"
                                class="form-select"
                                required
                            >
                                <option value="">Select Status</option>
                                <option value="RA">Re Attempt</option>
                                <option value="RT">Return</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipper_remarks" class="form-label">Remarks</label>
                            <textarea 
                                id="shipper_remarks" 
                                v-model="shipperAdviceForm.shipper_remarks"
                                class="form-control" 
                                rows="3"
                                placeholder="Optional remarks..."
                            ></textarea>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button 
                                type="submit" 
                                class="btn btn-primary" 
                                :disabled="isSubmitting"
                            >
                                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                {{ isSubmitting ? 'Updating...' : 'Update' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden buttons for modal control -->
    <button id="shipperAdviceModalOpen" data-bs-toggle="modal" data-bs-target="#shipperAdviceModal" style="display: none;"></button>
    <button id="hideShipperAdviceModal" data-bs-dismiss="modal" style="display: none;"></button>
</template>
            