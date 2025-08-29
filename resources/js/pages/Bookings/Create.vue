<script setup lang="ts">
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import Input from '@/components/ui/input/Input.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { nextTick, onMounted, ref, watch, computed } from 'vue';

import Multiselect from 'vue-multiselect';

// Add Font Awesome CDN
const fontAwesomeScript = document.createElement('script');
fontAwesomeScript.src = 'https://kit.fontawesome.com/231b67747d.js';
fontAwesomeScript.crossOrigin = 'anonymous';
document.head.appendChild(fontAwesomeScript);

const cities = ref([]);
const selectedCity = ref("");
const shipment_types = {
    EXPRESS: [
        ['OVERNIGHT', 'OVERNIGHT'],
        ['ECONOMY', 'ECONOMY'],
    ],
    LOGISTICS: [['OVERLAND LOGISTICS', 'OVERLAND LOGISTICS']],
};
const materials = ref([]);
const division = ref('EXPRESS');
const item_details = ref(false);
const selectedMaterial = ref([]);
const is_loading = ref(false);
const vendors = ref([]);
const platforms = ref([]);
const is_fetching = ref(true);
const items = ref([
    {
        id: 1,
        vendor_id: '',
        platform_id: '',
        item_name: '',
        purchase_cost: 0,
        item_price: 0,
        material_id: '',
        total_cost: 0,
        profit: 0,
        advance_payment: 0,
    },
]);

const commonForm = ref({
    name: '',
    phone: '',
    address: '',
});


const vendor_id = ref(null);
const platform_id = ref(null);

const shipmentForm = ref({
    division: '',
    shipment_type: '',
    product_description: '',
    weight: '',
    pieces: '',
    advance_payment: 0,
    payment_type: 'COD',
    cod_amount: '',
    order_id: '',
    special_instructions: '',
});

async function getCities() {
    const response = await axios.get(route('courier.cities', { courier: 'leopard' }));
    cities.value = response.data.data;
}

async function getPlatforms() {
    const response = await axios.get(route('platforms.get'));
    platforms.value = response.data.data;
}

async function getMaterials() {
    const response = await axios.get(route('materials.get'));
    materials.value = response.data.data;
}
async function getVendors() {
    const response = await axios.get(route('vendors.get'));
    vendors.value = response.data.data;
}

const getMaterialPrice = (id) => {
    const material = materials.value.find((m) => m.id === id);
    return material ? material.price : 0;
};

const addRow = () => {
    const newItem = {
        id: items.value.length + 1,
        vendor_id: '',
        platform_id: '',
        item_name: '',
        purchase_cost: 0,
        item_price: 0,
        material_id: '',
        total_cost: 0,
        profit: 0,
        advance_payment: 0,
    };
    items.value.push(newItem);
};

const removeRow = (id) => {
    items.value = items.value.filter((item) => item.id !== id);
    items.value.forEach((item, index) => {
        item.id = index + 1; // Reassign IDs to maintain sequential order
    });
    if (items.value.length === 0) {
        addRow(); // Ensure at least one row remains
    }
    console.log('Row removed, current items:', items.value);
};

// Computed properties for totals
const totalCost = computed(() => {
    return items.value.reduce((sum, item) => sum + (Number(item.purchase_cost) || 0), 0);
});

const totalSelling = computed(() => {
    return items.value.reduce((sum, item) => sum + (Number(item.item_price) || 0), 0);
});

const totalPackagingMaterial = computed(() => {
    return items.value.reduce((sum, item) => sum + getMaterialPrice(item.material_id), 0);
});

const totalAmount = computed(() => {
    return items.value.reduce((sum, item) => sum + (Number(item.item_price) || 0), 0);
});

const totalProfit = computed(() => {
    return items.value.reduce((sum, item) => {
        const itemProfit = (Number(item.item_price) || 0) - (Number(item.purchase_cost) || 0) - getMaterialPrice(item.material_id);
        return sum + itemProfit;
    }, 0);
});

// Helper function to format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PK', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

async function submit(event) {
    event.preventDefault();
    
    // Validate that vendor_id is selected for all items
    const invalidItems = items.value.filter(item => !item.vendor_id);
    if (invalidItems.length > 0) {
        alert('Please select a vendor for all items.');
        return;
    }
    
    console.log(
        ...items.value.map((item) => ({
            vendor_id: item.vendor_id,
            platform_id: item.platform_id,
            item_name: item.item_name || '',
            purchase_cost: item.purchase_cost || 0,
            item_price: item.item_price || 0,
            material_id: item.material_id || null,
            advance_payment: item.advance_payment || 0,
        })),
    );
    is_loading.value = true;
    try {
        const response = await axios.post(route('courier.createOrder', { courier: 'leopard' }), {
            destination_city: selectedCity.value,
            shipment_type: shipment_types[division.value][0][0],
            division: division.value,
            booked_packet_weight: shipmentForm.value.weight,
            booked_packet_no_piece: shipmentForm.value.pieces,
            booked_packet_collect_amount: shipmentForm.value.cod_amount,
            order_id: shipmentForm.value.order_id,
            consignee_name: commonForm.value.name,
            consignee_phone: commonForm.value.phone,
            consignee_address: commonForm.value.address,
            advance_payment: shipmentForm.value.advance_payment,
            special_instructions: shipmentForm.value.special_instructions,
            platform_id: platform_id.value,
            items: items.value.map((item) => ({
                vendor_id: item.vendor_id,
                item_name: item.item_name || '',
                platform_id: '1',
                purchase_cost: item.purchase_cost || 0,
                item_price: item.item_price || 0,
                material_id: item.material_id || null,
                advance_payment: item.advance_payment || 0,
            })),
        });

        successModalButton.click(); // Trigger the success modal
    } catch (error) {
        console.log(error);
    } finally {
        is_loading.value = false;
    }
}

onMounted(async () => {
    try {
        await Promise.all([getCities(), getPlatforms(), getMaterials(), getVendors()]);
    } catch (e) {
        console.log(e);
    }


    is_fetching.value = false;
    nextTick(() => {
        // Initialize Select2
        $('.select2').select2({
            placeholder: 'Select Destination City',
            allowClear: true,
        });

        $('#destination_city').select2().on('change', (e) => {
            selectedCity.value = $(e.target).val();
        });

    });
});

function goToIndexPage() {
    router.visit(route('booking.index'));
}

watch(selectedCity, (newValue) => {
    console.log('Selected city:', newValue);
});

watch(selectedMaterial, (newValue) => {
    console.log('Selected material:', newValue);
});
</script>

<style scoped>
.spinner {
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

    /* Mobile item card look */
.item-card{
  border:1px solid rgba(0,0,0,.08);
  border-radius:14px;
  padding:14px;
  background:#fff;
  box-shadow:0 6px 16px rgba(16,24,40,.06);
}
.item-card__header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:8px;
}

/* Larger tap targets on mobile */
@media (max-width: 767.98px){
  .form-control, .form-select, .btn{ min-height: 44px; }
  label.form-label{ font-size:.9rem; color:#667085; }
}

/* Make desktop table a bit nicer */
.table thead th{
    background-color: #f8f9fa;
    border-color: #dee2e6;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-responsive{
    border-radius: 8px;
    overflow: hidden;
}

/* Totals Row Styling */
.table-totals {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 2px solid #dee2e6;
}

.table-totals td {
    padding: 0.75rem 0.5rem;
    border-color: #dee2e6;
}

.totals-label {
    text-align: center;
    color: #495057;
    font-size: 0.875rem;
}

.totals-value {
    text-align: center;
    color: #212529;
    font-size: 0.9rem;
}

.table-totals:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
}
</style>
<template>
    <button data-bs-toggle="modal" id="successModalButton" data-bs-target="#successModal" style="display: none">Testing</button>
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <div class="modal-body">
                    <button type="button" class="btn-close position-absolute end-0 top-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>

                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle" style="font-size: 3rem"></i>
                    </div>

                    <h4 class="mb-2">Shipment has been created</h4>
                    <p class="text-muted">Your shipment was added successfully.</p>

                    <button type="button" @click="goToIndexPage" class="btn btn-success mt-3" data-bs-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <div v-show="!is_fetching" class="container-xxl container-p-y flex-grow-1">
        <form class="card-body" method="POST" @submit="submit">
            <!-- Bootstrap Multi-column Form Layout -->
            <div class="card mb-4 p-3">
                <h5 class="card-header">Create New Order</h5>

                <h6>Order Detail</h6>
                <div class="row g-6">

                    <Input
                        @update:modelValue="shipmentForm.order_id = $event"
                        label="Order ID"
                        type="text"
                        :length="4"
                        name="order_id"
                        placeholder="Order ID *"
                    />


                    <DropdownMenu
                        label="Division *"
                        name="division"
                        :length="4"
                        nonvalueitem="Select Division"
                        @update:modelValue="division = $event"
                        :items="[
                            ['EXPRESS', 'EXPRESS'],
                            ['LOGISTICS', 'LOGISTICS'],
                        ]"
                        selected="EXPRESS"
                        value-key="0"
                        item-key="1"
                    />


                    <DropdownMenu
                        label="Shipment Type *"
                        name="shipment_type"
                        :length="4"
                        nonvalueitem="Select Shipment Type"
                        :items="shipment_types[division]"
                        selected="OVERNIGHT"
                        value-key="0"
                        item-key="1"
                    />


                </div>

                <hr class="mx-n4 my-6" />
                <h6>Customer Detail</h6>
                <div class="row g-6">

                    <Input
                        @update:modelValue="commonForm.name = $event"
                        label="Consignee Name *"
                        type="text"
                        name="consignee_name"
                        placeholder="Consignee Name"
                    />
                    <Input
                        @update:modelValue="commonForm.phone = $event"
                        label="Consignee Phone *"
                        type="text"
                        name="consignee_phone"
                        placeholder="Consignee Phone"
                    />

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline form-floating-select2">
                            <select
                            v-model="selectedCity"
                                name="destination_city"
                                id="destination_city"
                                class="select2 form-select select2-hidden-accessible"
                                data-allow-clear="true"
                                required
                            >
                                <option value="">Select Destination City</option>
                                <option v-for="city in cities" :value="city.id">{{ city.city }}</option>
                            </select>
                            <label for="destination_city">Destination City *</label>
                        </div>
                    </div>



                    <DropdownMenu
                        @update:modelValue="platform_id = $event"
                        label="Platform *"
                        name="platform"
                        :length="6"
                        nonvalueitem="Select Platform"
                        :items="platforms.map(platform => [platform.id, platform.name])"
                        value-key="0"
                        item-key="1"
                    />


                        <Input
                        @update:modelValue="commonForm.address = $event"
                        label="Consignee Address *"
                        type="text"
                        name="consignee_address"
                        placeholder="Consignee Address"
                        :length="12"
                    />
                    <!-- <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <Multiselect
                                :options="cities"
                                label="city"
                                track-by="id"
                                v-model="selectedCity"
                                :required="true"
                                class="form-control"
                                id="selectInput"
                            />
                            <label for="selectInput">Delivery Places</label>
                        </div>
                    </div> -->



                </div>


                <hr class="mx-n4 my-6" />
                <h6>Shipment Detail</h6>
                <div class="row g-6">


                    <Input
                        @update:modelValue="shipmentForm.weight = $event"
                        label="Weigh (grams) *"
                        type="text"
                        :length="4"
                        name="weight"
                        placeholder="Weight (grams) *"
                    />


                    <Input
                        @update:modelValue="shipmentForm.pieces = $event"
                        label="No. of Pieces *"
                        type="text"
                        name="pieces"
                        :length="4"
                        placeholder="No. of Pieces *"
                    />

                         <Input
                        @update:modelValue="shipmentForm.product_description = $event"
                        label="Product Description *"
                        type="text"
                        name="product_description"
                        :length="4"
                        placeholder="Product Description *"
                    />

                    <Input
                        @update:modelValue="shipmentForm.cod_amount = $event"
                        label="COD Amount *"
                        type="number"
                        name="cod_amount"
                        placeholder="COD Amount *"
                    />

                    <Input
                        @update:modelValue="shipmentForm.advance_payment = $event"
                        label="Advance Payment *"
                        type="number"
                        name="advance_payment"
                        placeholder="Advance Payment *"
                    />



                    <Input
                        @update:modelValue="shipmentForm.special_instructions = $event"
                        label="Special Instructions *"
                        type="text"
                        name="special_instructions"
                        :length="12"
                        placeholder="Handle with care *"
                        is-text-area
                    />
                </div>



            </div>

            <!-- Desktop table -->
<div class="card mb-4 p-3 d-none d-md-block" v-if="item_details">
  <h6>3. Items Details</h6>
  <div class="table-responsive">
    <table class="table table-bordered align-middle" id="itemsTable">
      <thead class="table-light">
        <tr>
            <th>Vendor</th>
          <th>Product Name</th>
          <th>Cost</th>
          <th>Selling</th>
          <th>Packaging Material</th>
          <th>Total Amount</th>
          <th>Profit</th>
          <th style="width:64px">
            <button type="button" style="color: white;" class="btn btn-sm btn-success" @click="addRow">+</button>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
            <td>
            <select v-model="item.vendor_id" class="form-select" required>
              <option value="">Select Vendor</option>
              <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
                {{ vendor.name }}
              </option>
            </select>
          </td>
          <td><input type="text" v-model="item.item_name" class="form-control" placeholder="Product Name (Optional)" /></td>
          <td><input type="number" step="0.01" v-model.number="item.purchase_cost" class="form-control" placeholder="Cost (Optional)" /></td>
          <td><input type="number" step="0.01" v-model.number="item.item_price" class="form-control" placeholder="Selling Price (Optional)" /></td>
          <td>
            <select v-model="item.material_id" class="form-select">
              <option value="">Select Material (Optional)</option>
              <option v-for="material in materials" :key="material.id" :value="material.id">
                {{ material.name }}
              </option>
            </select>
          </td>
          <td><input type="number" :value="item.item_price || 0" class="form-control" readonly /></td>
          <td>
            <input
              type="number"
              :value="parseInt((item.item_price || 0) - (item.purchase_cost || 0)) - getMaterialPrice(item.material_id)"
              class="form-control"
              readonly
            />
          </td>
          <td>
            <button type="button" class="btn btn-sm btn-danger" @click="removeRow(item.id)">-</button>
          </td>
        </tr>

        <!-- Totals Row -->
        <tr class="table-totals">
          <td class="totals-label">
            <strong>Total</strong>
          </td>
          <td class="totals-label">
            <strong>{{ items.length }} Items</strong>
          </td>
          <td class="totals-value">
            <strong>{{ formatCurrency(totalCost) }}</strong>
          </td>
          <td class="totals-value">
            <strong>{{ formatCurrency(totalSelling) }}</strong>
          </td>
          <td class="totals-value">
            <strong>{{ formatCurrency(totalPackagingMaterial) }}</strong>
          </td>
          <td class="totals-value">
            <strong>{{ formatCurrency(totalAmount) }}</strong>
          </td>
          <td class="totals-value">
            <strong :class="{ 'text-danger': totalProfit < 0, 'text-success': totalProfit > 0 }">
              {{ formatCurrency(totalProfit) }}
            </strong>
          </td>
          <td class="totals-label">
            <strong>{{ totalSelling > 0 ? ((totalProfit / totalSelling) * 100).toFixed(1) : 0 }}%</strong>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Mobile cards -->
<div class="card mb-4 p-3 d-block d-md-none" v-if="item_details">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="mb-0">Items</h6>
    <button type="button" class="btn btn-sm btn-success" @click="addRow">Add</button>
  </div>

  <div v-for="item in items" :key="'m-' + item.id" class="item-card mb-3">



    <div class="item-card__header">
      <strong>#{{ item.id }}</strong>
      <button type="button" class="btn btn-outline-danger btn-sm" @click="removeRow(item.id)">Remove</button>
    </div>

    <div class="row g-2">
      <div class="col-12">
        <label class="form-label">Vendor</label>
        <select v-model="item.vendor_id" class="form-select" required>
          <option value="">Select Vendor</option>
          <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
            {{ vendor.name }}
          </option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Product Name</label>
        <input type="text" v-model="item.item_name" class="form-control" placeholder="e.g. T-Shirt (Optional)" />
      </div>

      <div class="col-6">
        <label class="form-label">Cost</label>
        <input type="number" step="0.01" v-model.number="item.purchase_cost" class="form-control" placeholder="Cost (Optional)" />
      </div>

      <div class="col-6">
        <label class="form-label">Selling</label>
        <input type="number" step="0.01" v-model.number="item.item_price" class="form-control" placeholder="Selling Price (Optional)" />
      </div>

      <div class="col-12">
        <label class="form-label">Packaging Material</label>
        <select v-model="item.material_id" class="form-select">
          <option value="">Select Material (Optional)</option>
          <option v-for="material in materials" :key="material.id" :value="material.id">
            {{ material.name }}
          </option>
        </select>
      </div>

      <div class="col-6">
        <label class="form-label">Total</label>
        <input type="number" :value="item.item_price || 0" class="form-control" readonly />
      </div>

      <div class="col-6">
        <label class="form-label">Profit</label>
        <input
          type="number"
          :value="parseInt((item.item_price || 0) - (item.purchase_cost || 0)) - getMaterialPrice(item.material_id)"
          class="form-control"
          readonly
        />
      </div>

      <div class="col-12">
        <label class="form-label">Advance Payment</label>
        <input type="number" step="0.01" v-model.number="item.advance_payment" class="form-control" placeholder="Advance Payment (Optional)" />
      </div>
    </div>
  </div>
</div>



            <div class="pt-6" style="display: flex; justify-content: flex-start">
                <button
                    :disabled="is_loading"
                    type="submit"
                    class="btn btn-primary waves-effect waves-light d-flex align-items-center justify-content-center me-4"
                    style="min-width: 120px"
                >
                    <template v-if="is_loading">
                        <span class="loader small"></span>
                    </template>
                    <template v-else>
                        <span>Book Order</span>
                    </template>
                </button>
                <a
                    @click="item_details = !item_details"
                    type="cancel"
                    class="btn btn-primary waves-effect waves-light d-flex align-items-center justify-content-center me-4"
                    style="min-width: 120px; color: white; gap: 10px; align-items: center;"
                >
                {{ item_details ? "Hide" : "Show" }} Chart
                <i class="fas fa-chevron-down ms-1" v-if="!item_details"></i>
                <i class="fas fa-chevron-up ms-1" v-else></i>
            </a>
                <button type="reset" class="btn btn-outline-secondary waves-effect me-4">Cancel</button>

            </div>
        </form>
    </div>

    <div v-if="is_fetching" style="display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100vh;">
        <span class="spinner"></span>
    </div>
</template>
