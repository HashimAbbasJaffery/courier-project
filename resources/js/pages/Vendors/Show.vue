<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
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

// Table data structure - populated from order_items table
const tableData = ref([]);
const is_loading = ref(false);
const is_saving = ref(false);

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
        const response = await axios.get(`/api/vendors/${props.vendor.id}/order-items`);

        if (response.data.success && response.data.data) {
            tableData.value = response.data.data.map(item => ({
                id: item.id,
                date: item.created_at ? new Date(item.created_at).toISOString().split('T')[0] : '',
                description: item.item_name,
                debit: item.purchase_cost || 0,
                credit: item.payment || 0,
                amount: (item.purchase_cost || 0) - (item.payment || 0)
            }));
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
    try {
        // Validate data before saving
        const invalidRows = tableData.value.filter(row =>
            !row.date || !row.description || row.description.trim() === ''
        );

        if (invalidRows.length > 0) {
            showNotificationModal(
                'Validation Error',
                'Please fill in Date and Description for all rows before saving.',
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
    tableData.value.push({
        id: newId,
        date: '',
        description: '',
        debit: 0,
        credit: 0,
        amount: 0
    });
};

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
});
</script>

<template>
    <div class="container-xxl container-p-y flex-grow-1">
        <div class="card">
            <div class="vendor-header">
                <h1 style="font-size: 25px; margin-bottom: 0px; padding-bottom: 0px;">{{ vendor.name }}</h1>
                <div class="vendor-stats">
                    <div class="stat-item">
                        <span class="stat-label">Last Total Amount:</span>
                        <span class="stat-value amount">{{ formatCurrency(lastTotalAmount) }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Last Payment Date:</span>
                        <span class="stat-value date">{{ lastPaymentDate || 'No payments yet' }}</span>
                    </div>
                </div>
            </div>
            <div class="card-header d-flex align-items-center justify-content-between" style="padding-top: 0px; margin-top: 0px;">
                <h5 class="card-header">Transactions</h5>
                <button @click="goBack" class="btn btn-secondary">
                    <i class="ri-arrow-left-line me-2"></i>Back to Vendors
                </button>
            </div>

            <div class="card-body">
                <div v-if="is_loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading transactions...</p>
                </div>

                <div v-else>
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <button @click="addRow" class="btn btn-sm btn-success">
                                <i class="ri-add-line me-1"></i>Add Payment
                            </button>
                        </div>
                        <div>
                            <button @click="savePayments" :disabled="is_saving" class="btn btn-primary">
                                <span v-if="is_saving" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="ri-save-line me-1"></i>
                                {{ is_saving ? 'Saving...' : 'Save Payments' }}
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px">Date</th>
                                    <th style="width: 200px">Description</th>
                                    <th style="width: 120px">Debit</th>
                                    <th style="width: 120px">Credit</th>
                                    <th style="width: 120px">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tableData" :key="row.id">
                                    <td>
                                        <input
                                            v-model="row.date"
                                            type="date"
                                            class="form-control form-control-sm"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model="row.description"
                                            type="text"
                                            class="form-control form-control-sm"
                                            placeholder="Enter description"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="row.debit"
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm"
                                            @input="updateAmount(row)"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            v-model.number="row.credit"
                                            type="number"
                                            step="0.01"
                                            class="form-control form-control-sm"
                                            @input="updateAmount(row)"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            :value="formatCurrency(row.amount)"
                                            type="text"
                                            class="form-control form-control-sm"
                                            readonly
                                            :class="{ 'text-danger': row.amount < 0, 'text-success': row.amount > 0 }"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="2" class="text-end"><strong>Totals:</strong></td>
                                    <td><strong>{{ formatCurrency(totalDebit) }}</strong></td>
                                    <td><strong>{{ formatCurrency(totalCredit) }}</strong></td>
                                    <td>
                                        <strong :class="{ 'text-danger': totalAmount < 0, 'text-success': totalAmount > 0 }">
                                            {{ formatCurrency(totalAmount) }}
                                        </strong>
                                    </td>
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
                    <h5 class="modal-title">
                        <i :class="modalType === 'error' ? 'ri-error-warning-line' : 'ri-check-line'" class="me-2"></i>
                        {{ modalTitle }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ modalMessage }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.vendor-header {
    padding: 1.5rem 1.5rem 0 1.5rem;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 1rem;
}

.vendor-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
}

.stat-value {
    font-size: 1.125rem;
    font-weight: 600;
}

.stat-value.amount {
    color: #198754;
}

.stat-value.date {
    color: #0d6efd;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.form-control-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}

.text-danger {
    color: #dc3545 !important;
}

.text-success {
    color: #198754 !important;
}

/* Custom Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
    animation: modalSlideIn 0.3s ease-out;
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.success-header {
    background-color: #d1e7dd;
    color: #0f5132;
    border-color: #badbcc;
}

.error-header {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.modal-body {
    padding: 1.5rem;
    text-align: center;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: auto;
    height: auto;
    opacity: 0.7;
}

.btn-close:hover {
    opacity: 1;
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
</style>
