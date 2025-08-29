<script lang="ts" setup>
import { ref, computed, onMounted, reactive, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps<{
    vendor: {
        id: number;
        name: string;
        toPay: number;
        created_at: string;
        updated_at: string;
    };
}>();

// ---- State ----
const filters = reactive({
    dateRange: getDefaultDateRange()
});

// Helper function to get default date range (today to last 7 days)
function getDefaultDateRange() {
    const today = new Date();
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(today.getDate() - 7);
    
    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };
    
    return `${formatDate(sevenDaysAgo)} - ${formatDate(today)}`;
}

// Table data structure - populated from order_items table
const tableData = ref([]);
const is_loading = ref(false);
const is_saving = ref(false);

// Track which rows are in edit mode
const editingRows = ref(new Set());

// Track selected rows
const selectedRows = ref(new Set());

// Modal state
const showModal = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');
const modalType = ref('success'); // 'success' or 'error'

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
};

const goBack = () => {
    router.visit(route('vendors.index'));
};

// Helpers
function clearAll() {
    filters.dateRange = getDefaultDateRange();
    // Refetch data with default date range
    fetchOrderItems();
}

// Show modal function
const showNotificationModal = (title, message, type = 'success') => {
    modalTitle.value = title;
    modalMessage.value = message;
    modalType.value = type;
    showModal.value = true;
};

// Close modal function
const closeModal = () => {
    showModal.value = false;
};

// Fetch order items data from database
const fetchOrderItems = async () => {
    try {
        is_loading.value = true;
        
        // Prepare query parameters for date filtering
        const params = {};
        if (filters.dateRange && filters.dateRange.includes(' - ')) {
            const [dateFrom, dateTo] = filters.dateRange.split(' - ');
            params.date_from = dateFrom;
            params.date_to = dateTo;
        }
        
        const response = await axios.get(`/api/vendors/${props.vendor.id}/order-items`, { params });

        if (response.data.success && response.data.data) {
            console.log('Raw API response:', response.data.data);
            
            tableData.value = response.data.data.map(item => {
                const mappedItem = {
                    id: item.id,
                    date: item.created_at ? new Date(item.created_at).toISOString().split('T')[0] : new Date().toISOString().split('T')[0], // Default to today if no date
                    description: item.item_name || 'No description',
                    debit: item.purchase_cost || 0,
                    credit: item.payment || 0,
                    amount: (item.purchase_cost || 0) - (item.payment || 0),
                    selected: false // Initialize checkbox selection state
                };
                
                console.log('Mapped item:', mappedItem);
                return mappedItem;
            });
            
            console.log('Final table data:', tableData.value);
        }
    } catch (error) {
        console.error('Error fetching order items:', error);
        // Keep empty array if fetch fails
        tableData.value = [];
    } finally {
        is_loading.value = false;
    }
};

// Save payment changes to database
const savePayments = async () => {
    console.log('savePayments function called');
    console.log('Current table data:', tableData.value);
    console.log('Editing rows:', editingRows.value);
    
    try {
        // Validate data before saving
        console.log('Validating table data:', tableData.value);
        
        const invalidRows = tableData.value.filter(row => {
            const hasDate = row.date && row.date.trim() !== '';
            const hasDescription = row.description && row.description.trim() !== '';
            
            console.log('Validating row:', {
                id: row.id,
                date: row.date,
                description: row.description,
                hasDate,
                hasDescription
            });
            
            return !hasDate || !hasDescription;
        });

        console.log('Invalid rows found:', invalidRows);

        if (invalidRows.length > 0) {
            showNotificationModal(
                'Validation Error',
                `Please fill in Date and Description for ${invalidRows.length} row(s). Invalid rows: ${invalidRows.map(r => r.id).join(', ')}`,
                'error'
            );
            return;
        }

        is_saving.value = true;

        // Prepare data for saving
        const paymentsData = tableData.value.map(row => ({
            id: row.id,
            date: row.date,
            description: row.description.trim(),
            debit: row.debit || 0,
            credit: row.credit || 0,
            amount: row.amount || 0
        }));

        const response = await axios.post(`/api/vendors/${props.vendor.id}/save-payments`, {
            payments: paymentsData
        });

        if (response.data.success) {
            showNotificationModal(
                'Success!',
                'Payments saved successfully!',
                'success'
            );
            // Refresh data from server to get updated values
            await fetchOrderItems();
        } else {
            showNotificationModal(
                'Error',
                'Error saving payments: ' + response.data.message,
                'error'
            );
        }
    } catch (error) {
        console.error('Error saving payments:', error);
        showNotificationModal(
            'Error',
            'Error saving payments. Please try again.',
            'error'
        );
    } finally {
        is_saving.value = false;
    }
};

// Add new row to table
const addRow = () => {
    const newId = tableData.value.length > 0 ? Math.max(...tableData.value.map(row => row.id)) + 1 : 1;
    const newRow = {
        id: newId,
        date: new Date().toISOString().split('T')[0], // Default to today
        description: 'New item', // Default description
        debit: 0,
        credit: 0,
        amount: 0,
        selected: false // Initialize checkbox selection state
    };
    tableData.value.push(newRow);
    // New rows start in edit mode
    editingRows.value.add(newId);
    
    console.log('Added new row:', newRow);
};

// Toggle edit mode for a specific row
const toggleEditRow = (rowId) => {
    if (editingRows.value.has(rowId)) {
        // Save the row - exit edit mode
        console.log('Saving row:', rowId, 'Current data:', tableData.value.find(row => row.id === rowId));
        editingRows.value.delete(rowId);
    } else {
        // Enter edit mode
        console.log('Entering edit mode for row:', rowId);
        editingRows.value.add(rowId);
    }
};

// Checkbox selection functions
const toggleSelectAll = () => {
    const newState = !isAllSelected.value;
    tableData.value.forEach(row => {
        row.selected = newState;
    });
    updateSelection();
};

const updateSelection = () => {
    // This function can be used for any additional logic when selection changes
    console.log('Selection updated. Selected rows:', selectedCount.value);
};

// Check if a row is in edit mode
const isRowEditing = (rowId) => {
    return editingRows.value.has(rowId);
};

// Check if all rows have valid data
const hasValidData = computed(() => {
    if (tableData.value.length === 0) return false;
    
    return tableData.value.every(row => {
        const hasDate = row.date && row.date.trim() !== '';
        const hasDescription = row.description && row.description.trim() !== '';
        return hasDate && hasDescription;
    });
});

// Checkbox selection computed properties
const isAllSelected = computed(() => {
    return tableData.value.length > 0 && tableData.value.every(row => row.selected);
});

const isIndeterminate = computed(() => {
    const selectedCount = tableData.value.filter(row => row.selected).length;
    return selectedCount > 0 && selectedCount < tableData.value.length;
});

const selectedCount = computed(() => {
    return tableData.value.filter(row => row.selected).length;
});

// Calculate amount based on debit and credit
const calculateAmount = (row) => {
    return (row.debit || 0) - (row.credit || 0);
};

// Update amount when debit or credit changes
const updateAmount = (row) => {
    row.amount = calculateAmount(row);
};

// Calculate total amounts
const totalDebit = computed(() => {
    return tableData.value.reduce((sum, row) => sum + (row.debit || 0), 0);
});

const totalCredit = computed(() => {
    return tableData.value.reduce((sum, row) => sum + (row.credit || 0), 0);
});

const totalAmount = computed(() => {
    return totalDebit.value - totalCredit.value;
});

// Computed property for last total amount
const lastTotalAmount = computed(() => {
    return totalAmount.value;
});

// Computed property for last payment date
const lastPaymentDate = computed(() => {
    if (tableData.value.length === 0) {
        return 'No payments yet';
    }
    const lastPayment = tableData.value[tableData.value.length - 1];
    return lastPayment ? lastPayment.date : 'No payments yet';
});

onMounted(async () => {
    await fetchOrderItems();
    
    // Initialize daterangepicker exactly like booking page
    nextTick(() => {
        $('#daterange').daterangepicker({ 
            locale: { format: 'YYYY-MM-DD' },
            startDate: moment().subtract(7, 'days'),
            endDate: moment()
        }, function (start: any, end: any) {
            filters.dateRange = `${start.format('YYYY-MM-DD')} - ${end.format('YYYY-MM-DD')}`;
            // Manually trigger data fetch when date range is selected
            fetchOrderItems();
        });
    });
});

// Watch for filter changes and refetch data
watch(filters, async (newFilters) => {
    if (newFilters.dateRange) {
        await fetchOrderItems();
    }
}, { deep: true });
</script>

<template>
    <div class="container-xxl container-p-y flex-grow-1">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-content">
                <div class="hero-left">
                    <div class="vendor-avatar">
                        <i class="ri-store-2-line"></i>
                    </div>
                    <div class="vendor-info">
                        <h1 class="vendor-name">{{ vendor.name }}</h1>
                        <p class="vendor-subtitle">Vendor Account Details</p>
                    </div>
                </div>
                <div class="hero-actions">
                    <button @click="goBack" class="btn btn-outline-light btn-lg">
                        <i class="ri-arrow-left-line me-2"></i>Back to Vendors
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-section">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="stat-card primary">
                        <div class="stat-icon">
                            <i class="ri-money-dollar-circle-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value">{{ formatCurrency(lastTotalAmount) }}</h3>
                            <p class="stat-label">Current Balance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i class="ri-calendar-check-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value">{{ lastPaymentDate || 'No payments yet' }}</h3>
                            <p class="stat-label">Last Payment Date</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i class="ri-file-list-3-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value">{{ tableData.length }}</h3>
                            <p class="stat-label">Total Transactions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-muted mb-2">
                            <i class="ri-calendar-line me-2"></i>Date Range
                        </label>
                        <input 
                            type="text" 
                            v-model="filters.dateRange" 
                            name="daterange" 
                            id="daterange" 
                            class="form-control form-control-lg" 
                        />
                    </div>
                    <div class="col-md-8 text-md-end">
                        <button @click="clearAll" class="btn btn-outline-secondary me-2">
                            <i class="ri-refresh-line me-2"></i>Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="transactions-section">
                <div class="section-header">
                    <div class="section-title">
                        <h4 class="mb-0">
                            <i class="ri-exchange-line me-2"></i>Transaction History
                        </h4>
                        <p class="text-muted mb-0">Manage vendor payments and transactions</p>
                    </div>
                    <div class="section-actions">
                        <div class="selection-info" v-if="selectedCount > 0">
                            <span class="badge bg-primary me-2">
                                <i class="ri-checkbox-multiple-line me-1"></i>
                                {{ selectedCount }} row{{ selectedCount !== 1 ? 's' : '' }} selected
                            </span>
                        </div>
                        <div class="action-buttons">
                            <button @click="addRow" class="btn btn-success btn-lg">
                                <i class="ri-add-line me-2"></i>Add Transaction
                            </button>
                            <button 
                                @click="savePayments" 
                                :disabled="is_saving || !hasValidData" 
                                class="btn btn-primary btn-lg ms-3"
                            >
                                <span v-if="is_saving" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="ri-save-line me-2"></i>
                                {{ is_saving ? 'Saving...' : hasValidData ? 'Save Changes' : 'Fill Required Fields' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="is_loading" class="loading-state">
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <h5 class="mt-3">Loading Transactions</h5>
                        <p class="text-muted">Please wait while we fetch your data...</p>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div v-else class="transactions-table">
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="table-header text-center" style="width: 50px;">
                                        <div class="form-check d-flex justify-content-center">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                id="selectAll"
                                                @change="toggleSelectAll"
                                                :checked="isAllSelected"
                                                :indeterminate="isIndeterminate"
                                            />
                                        </div>
                                    </th>
                                    <th class="table-header">
                                        <i class="ri-calendar-line me-2"></i>Date
                                    </th>
                                    <th class="table-header">
                                        <i class="ri-file-text-line me-2"></i>Description
                                    </th>
                                    <th class="table-header text-end">
                                        <i class="ri-arrow-down-line me-2"></i>Debit
                                    </th>
                                    <th class="table-header text-end">
                                        <i class="ri-arrow-up-line me-2"></i>Credit
                                    </th>
                                    <th class="table-header text-end">
                                        <i class="ri-calculator-line me-2"></i>Balance
                                    </th>
                                    <th class="table-header text-center">
                                        <i class="ri-settings-3-line me-2"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tableData" :key="row.id" class="table-row">
                                    <td class="checkbox-cell text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                :id="'row-' + row.id"
                                                v-model="row.selected"
                                                @change="updateSelection"
                                            />
                                        </div>
                                    </td>
                                    <td class="date-cell">
                                        <div v-if="isRowEditing(row.id)" class="edit-input">
                                            <input
                                                v-model="row.date"
                                                type="date"
                                                class="form-control"
                                            />
                                        </div>
                                        <div v-else class="display-value">
                                            <i class="ri-calendar-line me-2 text-muted"></i>
                                            {{ row.date || 'No date' }}
                                        </div>
                                    </td>
                                    <td class="description-cell">
                                        <div v-if="isRowEditing(row.id)" class="edit-input">
                                            <input
                                                v-model="row.description"
                                                type="text"
                                                class="form-control"
                                                placeholder="Enter description"
                                            />
                                        </div>
                                        <div v-else class="display-value">
                                            <i class="ri-file-text-line me-2 text-muted"></i>
                                            {{ row.description || 'No description' }}
                                        </div>
                                    </td>
                                    <td class="debit-cell text-end">
                                        <div v-if="isRowEditing(row.id)" class="edit-input">
                                            <input
                                                v-model.number="row.debit"
                                                type="number"
                                                step="0.01"
                                                class="form-control text-end"
                                                @input="updateAmount(row)"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <div v-else class="display-value debit-value">
                                            <i class="ri-arrow-down-line me-2"></i>
                                            {{ formatCurrency(row.debit) }}
                                        </div>
                                    </td>
                                    <td class="credit-cell text-end">
                                        <div v-if="isRowEditing(row.id)" class="edit-input">
                                            <input
                                                v-model.number="row.credit"
                                                type="number"
                                                step="0.01"
                                                class="form-control text-end"
                                                @input="updateAmount(row)"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <div v-else class="display-value credit-value">
                                            <i class="ri-arrow-up-line me-2"></i>
                                            {{ formatCurrency(row.credit) }}
                                        </div>
                                    </td>
                                    <td class="balance-cell text-end">
                                        <span class="balance-amount" :class="{ 
                                            'text-danger': row.amount < 0, 
                                            'text-success': row.amount > 0,
                                            'text-muted': row.amount === 0
                                        }">
                                            <i class="ri-calculator-line me-2"></i>
                                            {{ formatCurrency(row.amount) }}
                                        </span>
                                    </td>
                                    <td class="actions-cell text-center">
                                        <button
                                            @click="toggleEditRow(row.id)"
                                            type="button"
                                            class="btn btn-action"
                                            :class="isRowEditing(row.id) ? 'btn-warning' : 'btn-outline-primary'"
                                            :title="isRowEditing(row.id) ? 'Save changes' : 'Edit row'"
                                        >
                                            <i :class="isRowEditing(row.id) ? 'ri-check-line' : 'ri-edit-line'"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td class="text-start">
                                        <h6 class="mb-0 fw-semibold text-muted">Totals</h6>
                                    </td>
                                    <td></td>
                                    <td class="text-end">
                                        <span class="total-amount debit-total">
                                            {{ formatCurrency(totalDebit) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="total-amount credit-total">
                                            {{ formatCurrency(totalCredit) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="total-amount balance-total" :class="{ 
                                            'text-danger': totalAmount < 0, 
                                            'text-success': totalAmount > 0,
                                            'text-muted': totalAmount === 0
                                        }">
                                            {{ formatCurrency(totalAmount) }}
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Notification Modal -->
        <div v-if="showModal" class="modal-overlay" @click="closeModal">
            <div class="modal-content" @click.stop>
                <div class="modal-header" :class="modalType === 'error' ? 'error-header' : 'success-header'">
                    <div class="modal-icon">
                        <i :class="modalType === 'error' ? 'ri-error-warning-line' : 'ri-check-line'"></i>
                    </div>
                    <div class="modal-title-content">
                        <h5 class="modal-title">{{ modalTitle }}</h5>
                        <p class="modal-subtitle">{{ modalMessage }}</p>
                    </div>
                    <button type="button" class="btn-close" @click="closeModal">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" @click="closeModal">
                        <i class="ri-check-line me-2"></i>Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 3rem 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.hero-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.vendor-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.vendor-avatar i {
    font-size: 2.5rem;
    color: white;
}

.vendor-info {
    color: white;
}

.vendor-name {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.vendor-subtitle {
    font-size: 1.1rem;
    margin: 0;
    opacity: 0.9;
    font-weight: 300;
}

.hero-actions .btn {
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.hero-actions .btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

/* Stats Section */
.stats-section {
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-color);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
}

.stat-card.primary {
    --card-color: #667eea;
}

.stat-card.success {
    --card-color: #10b981;
}

.stat-card.info {
    --card-color: #3b82f6;
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    background: var(--card-color);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.stat-card .stat-icon i {
    font-size: 1.8rem;
    color: white;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.stat-card .stat-label {
    font-size: 0.9rem;
    color: #6b7280;
    margin: 0;
    font-weight: 500;
}

/* Main Card */
.main-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

/* Filters Section */
.filters-section {
    padding: 2rem;
    border-bottom: 1px solid #f3f4f6;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.filters-section .form-label {
    color: #374151;
    font-size: 0.9rem;
}

.filters-section .form-control {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.filters-section .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Transactions Section */
.transactions-section {
    padding: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title h4 {
    color: #1f2937;
    font-weight: 700;
    display: flex;
    align-items: center;
}

.section-title h4 i {
    color: #667eea;
}

.section-title p {
    color: #6b7280;
    font-size: 0.9rem;
}

.section-actions .btn {
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.section-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.section-actions .selection-info {
    margin-bottom: 1rem;
}

.section-actions .action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.section-actions .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 4rem 2rem;
}

.loading-content {
    max-width: 400px;
    margin: 0 auto;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.table {
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    color: #374151;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 1.25rem 1rem;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-header i {
    color: #667eea;
}

.table-row {
    transition: all 0.2s ease;
}

.table-row:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    transform: scale(1.01);
}

.table-row td {
    padding: 1.25rem 1rem;
    border: none;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

/* Cell Styles */
.checkbox-cell {
    width: 50px;
    vertical-align: middle;
}

.checkbox-cell .form-check {
    margin: 0;
}

.checkbox-cell .form-check-input {
    cursor: pointer;
    width: 18px;
    height: 18px;
    margin: 0;
}

.date-cell, .description-cell, .debit-cell, .credit-cell, .balance-cell, .actions-cell {
    position: relative;
}

.edit-input .form-control {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.edit-input .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.display-value {
    display: flex;
    align-items: center;
    font-weight: 500;
    color: #374151;
}

.display-value i {
    font-size: 1rem;
}

.debit-value {
    color: #dc2626;
    font-weight: 600;
}

.credit-value {
    color: #059669;
    font-weight: 600;
}

.balance-amount {
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.balance-amount.text-danger {
    color: #dc2626 !important;
}

.balance-amount.text-success {
    color: #059669 !important;
}

.balance-amount.text-muted {
    color: #6b7280 !important;
}

/* Action Buttons */
.btn-action {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border: none;
}

.btn-action:hover {
    transform: scale(1.1);
}

.btn-action i {
    font-size: 1rem;
}

/* Totals Row */
.totals-row {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-top: 2px solid #e5e7eb;
}

.totals-row td {
    padding: 1.5rem 1rem;
    font-weight: 600;
}

.total-amount {
    font-size: 1.1rem;
    font-weight: 700;
}

.debit-total {
    color: #dc2626;
}

.credit-total {
    color: #059669;
}

.balance-total.text-danger {
    color: #dc2626 !important;
}

.balance-total.text-success {
    color: #059669 !important;
}

.balance-total.text-muted {
    color: #6b7280 !important;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    animation: fadeIn 0.3s ease-out;
}

.modal-content {
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 500px;
    width: 90%;
    animation: modalSlideIn 0.3s ease-out;
    overflow: hidden;
}

.modal-header {
    padding: 2rem 2rem 1.5rem 2rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    position: relative;
}

.modal-header.success-header {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
}

.modal-header.error-header {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
}

.modal-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.modal-icon i {
    font-size: 1.5rem;
}

.modal-title-content {
    flex: 1;
}

.modal-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.modal-subtitle {
    margin: 0;
    font-size: 0.95rem;
    opacity: 0.9;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    color: inherit;
}

.btn-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.modal-footer {
    padding: 1.5rem 2rem 2rem 2rem;
    display: flex;
    justify-content: center;
    border-top: 1px solid #f3f4f6;
}

.modal-footer .btn {
    border-radius: 12px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.modal-footer .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-section {
        padding: 2rem 1rem;
        border-radius: 16px;
    }
    
    .hero-content {
        flex-direction: column;
        gap: 2rem;
        text-align: center;
    }
    
    .vendor-name {
        font-size: 2rem;
    }
    
    .vendor-avatar {
        width: 60px;
        height: 60px;
    }
    
    .vendor-avatar i {
        font-size: 2rem;
    }
    
    .stats-section .col-md-4 {
        margin-bottom: 1rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .section-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .section-actions .btn {
        width: 100%;
    }
    
    .filters-section .row {
        gap: 1rem;
    }
    
    .filters-section .col-md-4,
    .filters-section .col-md-8 {
        width: 100%;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table th,
    .table td {
        min-width: 120px;
        white-space: nowrap;
    }
    
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 1.5rem 1rem;
    }
    
    .vendor-name {
        font-size: 1.75rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
    }
    
    .transactions-section {
        padding: 1rem;
    }
    
    .filters-section {
        padding: 1.5rem;
    }
}
</style>
