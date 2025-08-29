<script lang="ts" setup>
import { nextTick, onMounted, ref, watch, computed } from 'vue';
import axios from 'axios';

const vendors = ref([]);
const platforms = ref([]);
const materials = ref([]);
const vendor_name = ref('');
const material_price = ref('');
const toDelete = ref(null);
const toEdit = ref(null);
const is_loading = ref(true);
const is_fetching = ref(false);
const is_fetching_materials = ref(false);
const is_fetching_platforms = ref(false);

// Update Payment state
const paymentSearchQuery = ref('');
const paymentSearchField = ref('order_id'); // Default search field
const foundPaymentData = ref(null);
const isSearchingPayment = ref(false);
const showUpdateForm = ref(false);

// Payment update form state
const paymentUpdateForm = ref({
    date: '',
    vendor_id: '',
    platform_id: '',
    item_name: '',
    purchase_cost: 0,
    item_price: 0,
    material_id: '',
    total_amount: 0
});

// Multiple items for payment update
const paymentItems = ref([]);
const isUpdatingPayment = ref(false);

// Computed profit calculation
const calculatedProfit = computed(() => {
    const materialPrice = getMaterialPrice(paymentUpdateForm.value.material_id);
    return (paymentUpdateForm.value.item_price || 0) - (paymentUpdateForm.value.purchase_cost || 0) - materialPrice;
});

// Check if required data is loaded
const isDataReady = computed(() => {
    return materials.value?.data && platforms.value?.data && vendors.value?.data;
});

// Get material price helper function
const getMaterialPrice = (id) => {
    if (!materials.value?.data || !Array.isArray(materials.value.data)) {
        return 0;
    }
    const material = materials.value.data.find((m) => m.id === id);
    return material ? material.price : 0;
};

// Add new row to payment items
const addPaymentRow = () => {
    const newItem = {
        date: '',
        id: paymentItems.value.length + 1,
        vendor_id: '',
        platform_id: '',
        item_name: '',
        purchase_cost: 0,
        item_price: 0,
        material_id: '',
    };
    paymentItems.value.push(newItem);
};

// Remove row from payment items
const removePaymentRow = (id) => {
    paymentItems.value = paymentItems.value.filter((item) => item.id !== id);
    paymentItems.value.forEach((item, index) => {
        item.id = index + 1; // Reassign IDs to maintain sequential order
    });
    if (paymentItems.value.length === 0) {
        addPaymentRow(); // Ensure at least one row remains
    }
};

// Computed total profit for all items
const totalPaymentProfit = computed(() => {
    return paymentItems.value.reduce((sum, item) => {
        const materialPrice = getMaterialPrice(item.material_id);
        const itemProfit = (item.item_price || 0) - (item.purchase_cost || 0) - materialPrice;
        return sum + itemProfit;
    }, 0);
});

// Watch for changes in form values to update profit calculation
watch([() => paymentUpdateForm.value.item_price, () => paymentUpdateForm.value.purchase_cost, () => paymentUpdateForm.value.material_id], () => {
    // This will trigger the computed property to recalculate
}, { deep: true });

// Make Payment Modal state
const makePaymentForm = ref({
    vendor_id: '',
    amount: 0
});

// Submit payment function
const submitPayment = async () => {
    try {
        const response = await axios.post(route('payment.create', { vendor: makePaymentForm.value.vendor_id }), {
            amount: makePaymentForm.value.amount
        });
        
        if (response.status === 200) {
            // Show success modal
            showSuccessModal();
            
            // Reset form
            makePaymentForm.value = {
                vendor_id: '',
                amount: 0
            };
            
            // Close payment modal
            const modal = document.getElementById('makePaymentModal');
            if (modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
            }
        }
    } catch (error) {
        console.error('Payment submission failed:', error);
        alert('Payment submission failed. Please try again.');
    }
};

// Show success modal
const showSuccessModal = () => {
    // Create and show success modal
    const successModal = document.createElement('div');
    successModal.className = 'modal fade';
    successModal.id = 'successPaymentModal';
    successModal.innerHTML = `
        <div class="modal-dialog modal-sm">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0 text-center">
                    <div class="w-100">
                        <div class="success-icon mx-auto mb-3">
                            <i class="ri-check-line"></i>
                        </div>
                        <h4 class="modal-title mb-2 fw-semibold text-success">Payment Successful!</h4>
                        <p class="text-muted mb-0 small">Your payment has been processed</p>
                    </div>
                </div>
                <div class="modal-body text-center pt-0">
                    <div class="success-message">
                        <i class="ri-money-dollar-circle-line text-success me-2"></i>
                        <span class="fw-medium">Payment has been created successfully!</span>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <button type="button" class="btn btn-success btn-lg px-4 shadow-sm" data-bs-dismiss="modal">
                        <i class="ri-check-line me-2"></i>Great!
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(successModal);
    
    // Show the modal
    const modal = new bootstrap.Modal(successModal);
    modal.show();
    
    // Remove modal from DOM after it's hidden
    successModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(successModal);
    });
};


async function getVendors() {
    try {
        is_fetching.value = true;
        const response = await axios.get(route('vendors.get'));
        vendors.value = response.data;
    } catch (error) {
        console.error('Error fetching vendors:', error);
    } finally {
        is_fetching.value = false;
    }
}

async function getPlatforms() {
    try {
        is_fetching_platforms.value = true;
        const response = await axios.get(route('platforms.get'));
        platforms.value = response.data;
        console.log('Platforms response:', response.data);
        console.log('Platforms value:', platforms.value);
    } catch (error) {
        console.error('Error fetching platforms:', error);
    } finally {
        is_fetching_platforms.value = false;
    }
}

async function getMaterials() {
    try {
        is_fetching_materials.value = true;
        const response = await axios.get(route('materials.get'));
        materials.value = response.data;
        console.log('Materials response:', response.data);
        console.log('Materials value:', materials.value);
    } catch (error) {
        console.error('Error fetching materials:', error);
    } finally {
        is_fetching_materials.value = false;
    }
}

async function addVendor(e) {
    e.preventDefault(); // Prevent form submission
    try {
        const response = await axios.post(route('vendor.create'), {
            name: vendor_name.value,
            price: material_price.value
        });
        if (response.status === 201) {
            closeCreateModal.click(); // Close the modal
            // Reset form fields
            vendor_name.value = '';
            // Refresh materials list
            await getVendors();
        } else {
            console.error('Error adding material:', response.data.message);
        }
    } catch (error) {
        console.error('Error adding material:', error);
    }
}

async function editVendor(e) {
    e.preventDefault(); // Prevent form submission
    try {
        const response = await axios.post(route('vendor.update', { id: toEdit.value }), {
            name: edit_vendor_name.value,
        });
        console.log(response);
        if (response.status === 200) {
            // Reset toEdit and refresh materials list
            cancelMaterialButton.click(); // Close the modal
            toEdit.value = null;// Close the modal
            await getVendors();
        } else {
            console.error('Error editing material:', response.data.message);
        }
    } catch (error) {
        console.error('Error editing material:', error);
    }
}

async function deleteVendor(e) {
    e.preventDefault(); // Prevent form submission
    try {
        const response = await axios.post(route('vendor.delete', { id: toDelete.value }));
        console.log(response);
        if (response.status === 200) {
            // Reset toDelete and refresh materials list
            toDelete.value = null;
            cancelModalButton.click(); // Close the modal
            await getVendors();
        } else {
            console.error('Error deleting material:', response.data.message);
        }
    } catch (error) {
        console.error('Error deleting material:', error);
    }
}

// Search payment function
const searchPayment = async (e) => {
    e.preventDefault();

    if (!paymentSearchQuery.value.trim()) {
        alert('Please enter a search term');
        return;
    }

    try {
        isSearchingPayment.value = true;
        showUpdateForm.value = false;

        console.log('Searching for:', {
            field: paymentSearchField.value,
            query: paymentSearchQuery.value.trim()
        });
        // Search by specific field
        const response = await axios.get(`/api/shipments/search?field=${paymentSearchField.value}&query=${encodeURIComponent(paymentSearchQuery.value.trim())}`);
        const items = response.data?.data?.items || [];
        console.log('Search response:', response.data);

        if (response.data.success && response.data.data) {
            foundPaymentData.value = response.data.data;
            showUpdateForm.value = true;
            // Reset payment form
            paymentUpdateForm.value = {
                vendor_id: '',
                platform_id: '',
                item_name: '',
                purchase_cost: 0,
                item_price: 0,
                material_id: '',
                advance_payment: 0
            };
            // Initialize payment items with one row

            if(items.length > 0) {
                items.forEach(item => {
                    paymentItems.value.push({
                        id: item.id,
                        date: item.created_at?.split("T")[0] || '',
                        vendor_id: item.vendor_id || '',
                        platform_id: item.platform_id || '',
                        item_name: item.item_name || '',
                        purchase_cost: item.purchase_cost || 0,
                        item_price: item.item_price || 0,
                        material_id: item.material_id || '',
                        total_amount: item.total_amount || 0
                    });
                })
            } else {
                paymentItems.value = [{
                    date: '',
                    id: 1,
                    vendor_id: '',
                    platform_id: '',
                    item_name: '',
                    purchase_cost: 0,
                    item_price: 0,
                    material_id: '',
                    advance_payment: 0
                }];
            }
        } else {
            alert('No payment/shipment found with the provided details. Please try again.');
            foundPaymentData.value = null;
            showUpdateForm.value = false;
        }
    } catch (error) {
        console.error('Error searching payment:', error);
        if (error.response?.status === 404) {
            alert('No shipment found with the provided details. Please try again.');
        } else {
            alert('Error searching for payment. Please try again.');
        }
        foundPaymentData.value = null;
        showUpdateForm.value = false;
    } finally {
        isSearchingPayment.value = false;
    }
};

// Update payment function
const updatePayment = async (e) => {
    e.preventDefault();
        // Helper: treat only null/undefined as invalid, but allow 0
        const isEmpty = val => val === null || val === undefined || val === '';

        const invalidItems = paymentItems.value.filter(item =>
            isEmpty(item.vendor_id) ||
            isEmpty(item.item_name) ||
            isEmpty(item.purchase_cost) ||
            isEmpty(item.item_price) ||
            isEmpty(item.material_id)
        );

    console.log(invalidItems);
    if (invalidItems.length > 0) {
        alert('Please fill in all required fields for all items');
        return;
    }

    try {
        isUpdatingPayment.value = true;

        // Process all payment items
        const promises = paymentItems.value.map(item =>
            axios.post('/api/shipments/payment/update', {
                date: item.date || new Date().toISOString().split('T')[0],
                id: item.id || null,
                shipment_id: foundPaymentData.value.id,
                vendor_id: item.vendor_id,
                platform_id: item.platform_id,
                item_name: item.item_name,
                purchase_cost: item.purchase_cost,
                item_price: item.item_price,
                material_id: item.material_id,
                total_amount: item.total_amount
            })
        );

        const responses = await Promise.all(promises);
        const allSuccessful = responses.every(response => response.data.success);

        if (allSuccessful) {
            alert(`Payment details updated successfully for ${paymentItems.value.length} item(s)!`);
            // Reset form and close modal
            showUpdateForm.value = false;
            foundPaymentData.value = null;
            paymentSearchQuery.value = '';
            // Close modal using Bootstrap
            const modal = bootstrap.Modal.getInstance(document.getElementById('updatePaymentModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            alert('Some items failed to update. Please try again.');
        }
    } catch (error) {
        console.error('Error updating payment:', error);
        if (error.response?.data?.message) {
            alert('Error updating payment: ' + error.response.data.message);
        } else {
            alert('Error updating payment. Please try again.');
        }
    } finally {
        isUpdatingPayment.value = false;
    }
};

// Reset payment form when modal is closed
const resetPaymentForm = () => {
    paymentSearchQuery.value = '';
    paymentSearchField.value = 'order_id';
    foundPaymentData.value = null;
    showUpdateForm.value = false;
    paymentUpdateForm.value = {
        vendor_id: '',
        platform_id: '',
        item_name: '',
        purchase_cost: 0,
        item_price: 0,
        material_id: '',
        advance_payment: 0
    };
    paymentItems.value = [];
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
};

const initializeDataTable = () => {
  const table = $('#myTable').DataTable();

  // Destroy existing instance
  if ( $.fn.DataTable.isDataTable('#myTable') ) {
    table.destroy();
  }

  // Re-initialize after DOM updates
  nextTick(() => {
    $('#myTable').DataTable({
      pageLength: 10,
      lengthMenu: [
        [10, 25, 50, -1],
        [10, 25, 50, "All"]
      ]
    });
  });
}

onMounted(async () => {
    await getVendors();
    await getPlatforms();
    await getMaterials();
    initializeDataTable();
    is_loading.value = false;

    // Add modal event listener to reset form when modal is hidden
    const updatePaymentModal = document.getElementById('updatePaymentModal');
    if (updatePaymentModal) {
        updatePaymentModal.addEventListener('hidden.bs.modal', resetPaymentForm);
    }
});




let hasWatchedOnce = false;
watch(vendors, () => {
  if (hasWatchedOnce) {
    initializeDataTable();
  } else {
    hasWatchedOnce = true;
  }
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
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
        }
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

/* Payment item row styling */
.payment-item-row {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6 !important;
    transition: all 0.3s ease;
}

.payment-item-row:hover {
    background-color: #e9ecef;
    border-color: #adb5bd !important;
}

.payment-item-row .btn-outline-danger {
    transition: all 0.2s ease;
}

.payment-item-row .btn-outline-danger:hover:not(:disabled) {
    transform: scale(1.05);
}


@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Material Design Modal Styles */
.payment-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white !important;
    font-size: 24px;
}

.payment-icon i {
    color: white !important;
}

.success-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white !important;
    font-size: 32px;
    margin: 0 auto;
}

.modal-content {
    border-radius: 16px;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 1.5rem 0 1.5rem;
}

.form-select-lg, .input-group-lg .form-control {
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-select-lg:focus, .input-group-lg .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.input-group-text {
    border-radius: 8px 0 0 8px;
    border-color: #dee2e6;
}

.btn-lg {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    transform: translateY(-1px);
}

.form-label {
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

.form-text {
    font-size: 0.8rem;
    line-height: 1.4;
}

.success-message {
    padding: 1rem;
    background: linear-gradient(135deg, #f8fff9 0%, #f0fff4 100%);
    border-radius: 12px;
    border: 1px solid #d4edda;
}
</style>
<template>
    <div v-show="!is_loading" class="container-xxl container-p-y flex-grow-1">
        <div class="card position-relative">
            <div class="card-header d-flex align-items-center justify-content-between ps-0 pb-0">
                <h5 class="card-header">Manage Vendors</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">Add Vendor</button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#makePaymentModal">Make</button>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#updatePaymentModal">Update Payment</button>
                </div>
            </div>

            <!-- Loading overlay -->
            <div v-if="is_fetching" class="loading-overlay">
                <div class="loading-spinner"></div>
                <div class="mt-3 text-muted">Fetching data...</div>
            </div>
            <div class="table-responsive text-nowrap">
            <table id="myTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>No#</th>
                            <th>Vendor Name</th>
                            <th>Total Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(vendor, index) in vendors.data" :key="vendor.id">
                            <td>{{ index + 1 }}</td>
                            <td>{{ vendor.name }}</td>
                            <td>{{ vendor.toPay }}</td>
                            <td style="display: flex; gap: 10px;">
                                <a :href="route('vendors.show', { vendor: vendor.id })" class="btn btn-sm btn-info">
                                    <i class="ri-eye-line me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-warning editBtn" @click="toEdit = vendor.id" data-bs-target="#editMaterialModal" data-bs-toggle="modal" data-id="1">Edit</button>
                                <button class="btn btn-sm btn-danger deleteBtn" @click="toDelete = vendor.id" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal" data-id="1">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div v-show="is_loading" style="display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100vh;">
        <span class="loader"></span>
    </div>


<!-- Delete Material Modal -->
<div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-simple modal-delete-user">
    <div class="modal-content">
      <div class="modal-body">
        <h4 class="text-center mb-4">Are you sure you want to delete <span id="delete_material_name"></span>?</h4>
        <form method="POST" class="text-center">
          <input type="hidden" name="material_id" id="delete_material_id">
          <button type="submit" @click="deleteVendor" name="delete_material" class="btn btn-danger me-3">Yes, Delete</button>
          <button type="button" @click="toDelete = null" id="cancelModalButton" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Material Modal -->
<div class="modal fade" id="editMaterialModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        <div class="text-center mb-6">
          <h4 class="mb-2">Edit Packaging Material</h4>
        </div>
        <form method="POST" class="row g-5">
          <input type="hidden" name="material_id" id="edit_material_id">
          <div class="col-12">
            <div class="form-floating form-floating-outline">
              <input type="text" :value="vendors.data?.filter(vendor => vendor.id == toEdit)[0]?.name ?? ''" name="vendor_name" id="edit_vendor_name" class="form-control" placeholder="Vendor Name" required />
              <label for="edit_vendor_name">Vendor Name</label>
            </div>
          </div>
          <div class="col-12 text-center">
            <button type="submit" @click="editVendor" name="update_material" id="updateMaterialButton" class="btn btn-primary me-3">Update</button>
            <button type="button" @click="toEdit = null" id="cancelMaterialButton" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-simple modal-edit-user">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    <div class="mb-6 text-center">
                        <h4 class="mb-2">Add Packaging Material</h4>
                    </div>
                    <form method="POST" class="row g-5">
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <input v-model="vendor_name" type="text" name="vendor_name" class="form-control" placeholder="Vendor Name" required />
                                <label for="vendor_name">Name</label>
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" name="add_material" @click="addVendor" class="btn btn-primary me-3">Submit</button>
                            <button type="button" id="closeCreateModal" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Payment Modal -->
    <div class="modal fade" id="updatePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" style="max-width: 95%; width: 95%;">
            <div class="modal-content" style="padding: 30px;">
                <div class="modal-body p-0">
                    <button type="button" class="btn-close" @click="resetPaymentForm" data-bs-dismiss="modal"></button>
                    <div class="mb-6 text-center">
                        <h4 class="mb-2">Update Payment</h4>
                        <p class="text-muted">Search and update existing payment records</p>
                    </div>

                    <!-- Search Payment Form -->
                    <form @submit.prevent="searchPayment" class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <select v-model="paymentSearchField" class="form-select" required>
                                    <option value="order_id">Order ID</option>
                                    <option value="tracking_number">Tracking Number</option>
                                    <option value="consignee_name">Customer Name</option>
                                    <option value="consignee_phone">Phone Number</option>
                                </select>
                                <label>Search Field</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating form-floating-outline">
                                <input v-model="paymentSearchQuery" type="text" class="form-control" :placeholder="`Enter ${paymentSearchField === 'order_id' ? 'Order ID' : paymentSearchField === 'tracking_number' ? 'Tracking Number' : paymentSearchField === 'consignee_name' ? 'Customer Name' : 'Phone Number'}`" required />
                                <label>{{ paymentSearchField === 'order_id' ? 'Order ID' : paymentSearchField === 'tracking_number' ? 'Tracking Number' : paymentSearchField === 'consignee_name' ? 'Customer Name' : 'Phone Number' }}</label>
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-warning me-3" :disabled="isSearchingPayment">
                                <span v-if="isSearchingPayment" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSearchingPayment ? 'Searching...' : 'Search Payment' }}
                            </button>
                            <button type="button" @click="resetPaymentForm" class="btn btn-outline-secondary me-2">Reset</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>

                    <!-- Payment Update Form (shown after successful search) -->
                    <div v-if="showUpdateForm && foundPaymentData && isDataReady" class="row g-4">
                        <!-- Found Order Details -->
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="ri-information-line me-2"></i>Found Order Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Order ID:</label>
                                            <p class="mb-0">{{ foundPaymentData.order_id || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tracking Number:</label>
                                            <p class="mb-0">{{ foundPaymentData.tracking_number || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Customer Name:</label>
                                            <p class="mb-0">{{ foundPaymentData.consignee_name || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Phone Number:</label>
                                            <p class="mb-0">{{ foundPaymentData.consignee_phone || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Destination City:</label>
                                            <p class="mb-0">{{ foundPaymentData.destination_city || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">COD Amount:</label>
                                            <p class="mb-0">{{ foundPaymentData.cod_amount || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Shipment Type:</label>
                                            <p class="mb-0">{{ foundPaymentData.shipment_type || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Weight:</label>
                                            <p class="mb-0">{{ foundPaymentData.weight ? `${foundPaymentData.weight} kg` : 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">No. of Pieces:</label>
                                            <p class="mb-0">{{ foundPaymentData.no_of_pieces || 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Status:</label>
                                            <p class="mb-0">
                                                <span class="badge bg-primary">{{ foundPaymentData.status || 'N/A' }}</span>
                                            </p>
                                        </div>
                                        <div class="col-12" v-if="foundPaymentData.consignee_address">
                                            <label class="form-label fw-bold">Delivery Address:</label>
                                            <p class="mb-0">{{ foundPaymentData.consignee_address }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Update Form -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0"><i class="ri-money-dollar-circle-line me-2"></i>Update Payment Details</h6>
                                    <button type="button" @click="addPaymentRow" class="btn btn-sm btn-success">+</button>
                                </div>
                                <div class="card-body">
                                    <form @submit.prevent="updatePayment">
                                        <!-- Desktop table -->
                                        <div class="table-responsive d-none d-md-block">
                                            <table class="table table-bordered align-middle" id="paymentItemsTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Vendor</th>
                                                        <th>Product Name</th>
                                                        <th>Cost</th>
                                                        <th>Selling</th>
                                                        <th>Packaging Material</th>
                                                        <th>Total Amount</th>
                                                        <th>Profit</th>
                                                        <th style="width:64px">
                                                            <button type="button" class="btn btn-sm btn-success" @click="addPaymentRow">+</button>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="item in paymentItems" :key="item.id">
                                                        <td>
                                                            <input type="date" v-model="item.date" class="form-control" required />
                                                        </td>
                                                        <td>
                                                            <select v-model="item.vendor_id" class="form-select" required>
                                                                <option value="">Select Vendor</option>
                                                                <option v-for="vendor in (vendors.data || [])" :key="vendor.id" :value="vendor.id">
                                                                    {{ vendor.name }}
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td><input type="text" v-model="item.item_name" class="form-control" required /></td>
                                                        <td><input type="number" step="0.01" v-model.number="item.purchase_cost" class="form-control" required /></td>
                                                        <td><input type="number" step="0.01" v-model.number="item.item_price" class="form-control" required /></td>
                                                        <td>
                                                            <select v-model="item.material_id" class="form-select" required>
                                                                <option value="">Select Material</option>
                                                                <option v-for="material in (materials.data || [])" :key="material.id" :value="material.id">
                                                                    {{ material.name }} ({{ material.price }})
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td><input type="number" readonly :value="item.item_price" step="0.01" class="form-control" /></td>
                                                        <td>
                                                            <input
                                                                type="number"
                                                                :value="(item.item_price || 0) - (item.purchase_cost || 0) - getMaterialPrice(item.material_id)"
                                                                class="form-control"
                                                                readonly
                                                            />
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-danger" @click="removePaymentRow(item.id)" :disabled="paymentItems.length === 1">-</button>
                                                        </td>
                                                    </tr>

                                                    <!-- Totals Row -->
                                                    <tr class="table-totals">
                                                        <td class="totals-label">
                                                            <strong>Total</strong>
                                                        </td>
                                                        <td class="totals-label">
                                                            <strong>{{ paymentItems.length }} Items</strong>
                                                        </td>
                                                        <td class="totals-label">
                                                            <strong>—</strong>
                                                        </td>
                                                        <td class="totals-value">
                                                            <strong>{{ formatCurrency(paymentItems.reduce((sum, item) => sum + (item.purchase_cost || 0), 0)) }}</strong>
                                                        </td>

                                                        <td class="totals-value">
                                                            <strong>{{ formatCurrency(paymentItems.reduce((sum, item) => sum + getMaterialPrice(item.material_id), 0)) }}</strong>
                                                        </td>
                                                        <td class="totals-value">
                                                            <strong>{{ formatCurrency(paymentItems.reduce((sum, item) => sum + (item.item_price || 0), 0)) }}</strong>
                                                        </td>
                                                        <td class="totals-value">
                                                            <strong :class="{ 'text-danger': totalPaymentProfit < 0, 'text-success': totalPaymentProfit > 0 }">
                                                                {{ formatCurrency(totalPaymentProfit) }}
                                                            </strong>
                                                        </td>
                                                        <td class="totals-label">
                                                            <strong>{{ paymentItems.reduce((sum, item) => sum + (item.item_price || 0), 0) > 0 ? ((totalPaymentProfit / paymentItems.reduce((sum, item) => sum + (item.item_price || 0), 0)) * 100).toFixed(1) : 0 }}%</strong>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Mobile cards -->
                                        <div class="d-block d-md-none">
                                            <div v-for="item in paymentItems" :key="'m-' + item.id" class="item-card mb-3 p-3 border rounded">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <h6 class="mb-0">Item #{{ item.id }}</h6>
                                                    <button
                                                        type="button"
                                                        @click="removePaymentRow(item.id)"
                                                        class="btn btn-sm btn-outline-danger"
                                                        :disabled="paymentItems.length === 1"
                                                    >
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>

                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small">Vendor</label>
                                                        <select v-model="item.vendor_id" class="form-select form-select-sm" required>
                                                            <option value="">Select Vendor</option>
                                                            <option v-for="vendor in (vendors.data || [])" :key="vendor.id" :value="vendor.id">
                                                                {{ vendor.name }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small">Product Name</label>
                                                        <input v-model="item.item_name" type="text" class="form-control form-control-sm" required />
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small">Cost</label>
                                                        <input v-model="item.purchase_cost" type="number" step="0.01" class="form-control form-control-sm" required />
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small">Selling</label>
                                                        <input v-model="item.item_price" type="number" step="0.01" class="form-control form-control-sm" required />
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small">Packaging Material</label>
                                                        <select v-model="item.material_id" class="form-select form-select-sm" required>
                                                            <option value="">Select Material</option>
                                                            <option v-for="material in (materials.data || [])" :key="material.id" :value="material.id">
                                                                {{ material.name }} ({{ material.price }})
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small">Total Amount</label>
                                                        <input :value="item.total_amount" type="number" step="0.01" class="form-control form-control-sm" />
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="alert alert-info mb-0 py-2">
                                                            <strong>Profit:</strong> {{ formatCurrency((item.item_price || 0) - (item.purchase_cost || 0) - getMaterialPrice(item.material_id)) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-center mt-3">
                                                <button type="button" @click="addPaymentRow" class="btn btn-success">
                                                    <i class="ri-add-line me-1"></i>Add Item
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Submit Buttons -->
                                        <div class="row mt-4">
                                            <div class="col-12 text-center">
                                                <button type="submit" class="btn btn-warning me-3" :disabled="isUpdatingPayment">
                                                    <span v-if="isUpdatingPayment" class="spinner-border spinner-border-sm me-2"></span>
                                                    {{ isUpdatingPayment ? 'Updating...' : 'Update Payment' }}
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading message when data is not ready -->
                    <div v-if="showUpdateForm && foundPaymentData && !isDataReady" class="row g-4">
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <div v-if="is_fetching_materials || is_fetching_platforms" class="spinner-border spinner-border-sm me-2"></div>
                                <span v-if="is_fetching_materials">Loading materials...</span>
                                <span v-else-if="is_fetching_platforms">Loading platforms...</span>
                                <span v-else>Loading payment form data...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Make Payment Modal -->
    <div class="modal fade" id="makePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center">
                        <div class="payment-icon me-3">
                            <i class="ri-money-dollar-circle-line text-primary"></i>
                        </div>
                        <div>
                            <h4 class="modal-title mb-1 fw-semibold text-dark">Make Payment</h4>
                            <p class="text-muted mb-0 small">Record a new payment transaction</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body pt-4">
                    <form @submit.prevent="submitPayment">
                        <div class="form-group mb-4">
                            <label for="payment_vendor_id" class="form-label fw-medium text-dark mb-2">
                                <i class="ri-user-line me-2 text-muted"></i>Select Vendor
                            </label>
                            <select 
                                v-model="makePaymentForm.vendor_id" 
                                id="payment_vendor_id" 
                                class="form-select form-select-lg border-2" 
                                :class="makePaymentForm.vendor_id ? 'border-success' : 'border-light'"
                                required
                            >
                                <option value="">Choose a vendor from the list...</option>
                                <option 
                                    v-for="vendor in vendors.data" 
                                    :key="vendor.id" 
                                    :value="vendor.id"
                                    class="py-2"
                                >
                                    {{ vendor.name }}
                                </option>
                            </select>
                            <div class="form-text text-muted small mt-1">
                                <i class="ri-information-line me-1"></i>Select the vendor you want to make payment to
                            </div>
                        </div>
                        
                        <div class="form-group mb-4" v-if="makePaymentForm.vendor_id">
                            <label for="payment_amount" class="form-label fw-medium text-dark mb-2">
                                <i class="ri-money-dollar-circle-line me-2 text-muted"></i>Payment Amount
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="ri-currency-line text-muted"></i>
                                </span>
                                <input 
                                    v-model.number="makePaymentForm.amount" 
                                    type="number" 
                                    step="0.01" 
                                    id="payment_amount" 
                                    class="form-control border-start-0 ps-0" 
                                    placeholder="0.00"
                                    required
                                />
                            </div>
                            <div class="form-text text-muted small mt-1">
                                <i class="ri-information-line me-1"></i>Enter the payment amount in your local currency
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 pt-3">
                            <button type="button" class="btn btn-outline-secondary btn-lg flex-fill" data-bs-dismiss="modal">
                                <i class="ri-close-line me-2"></i>Cancel
                            </button>
                            <button 
                                type="submit" 
                                class="btn btn-primary btn-lg flex-fill shadow-sm" 
                                :disabled="!makePaymentForm.vendor_id || !makePaymentForm.amount"
                            >
                                <i class="ri-check-line me-2"></i>
                                <span v-if="!makePaymentForm.vendor_id || !makePaymentForm.amount">Fill Required Fields</span>
                                <span v-else>Submit Payment</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</template>
