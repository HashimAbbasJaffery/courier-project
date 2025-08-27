<template>
  <AuthCheck>
    <div class="container-xxl container-p-y flex-grow-1">
    <!-- Header -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
          </div>
          
          <div class="card-body">
            <!-- Loading overlay -->
            <div v-if="is_fetching" class="loading-overlay">
              <div class="loading-spinner"></div>
              <div class="mt-3 text-muted">Fetching data...</div>
            </div>
            
            <!-- Search and Controls -->
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="input-group">
                  <span class="input-group-text">
                    <i class="ri-search-line"></i>
                  </span>
                  <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Search shipments..." 
                    v-model="searchQuery"
                    @input="filterShipments"
                  >
                </div>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <label class="form-label mb-0 me-2">Show:</label>
                  <select class="form-select form-select-sm" style="width: auto;" v-model="itemsPerPage" @change="changeItemsPerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                  </select>
                  <span class="text-muted ms-2">per page</span>
                </div>
              </div>
            </div>

            <!-- Simple Table Structure -->
            <div class="table-responsive">
              <table class="table align-middle" style="font-size: 12px; border: 1px solid #e9ecef;">
                <thead>
                  <tr>
                    <th style="font-size: 10px; padding-left: 5px; padding-right: 5px; width: 3%;"></th>
                    <th style="font-size: 10px; padding-left: 5px; padding-right: 5px; width: 4%;">S.no</th>
                    <th style="font-size: 10px; padding-left: 5px; width: 1%;">Order ID</th>
                    <th style="font-size: 10px; padding-left: 0px; width: 15%; text-align: center;">Tracking</th>
                    <th style="font-size: 10px; padding-left: 0px;">Status</th>
                    <th style="font-size: 10px; padding-left: 0px;">Customer Name</th>
                    <th style="font-size: 10px; padding-left: 0px; width: 3%;">Address</th>
                    <th style="font-size: 10px; padding-left: 0px;" class="text-center">COD</th>
                    <th style="font-size: 10px; padding-left: 0px;" class="text-center">Recieve Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Data Rows -->
                  <template v-for="(shipment, index) in paginatedShipments" :key="shipment.id">
                    <tr>
                      <td style="text-align: center; padding-left: 5px; padding-right: 5px;">
                        <button 
                          type="button" 
                          class="v-btn v-btn--icon v-theme--light text-primary v-btn--density-comfortable v-btn--size-default v-btn--variant-text"
                          @click="toggleRowExpansion(shipment.id)"
                        >
                          <span class="v-btn__overlay"></span>
                          <span class="v-btn__underlay"></span>
                          <span class="v-btn__content" data-no-activator="">
                            <i 
                              :class="[
                                'v-icon notranslate v-theme--light v-icon--size-default flip-in-rtl',
                                expandedRows.includes(shipment.id) ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line'
                              ]" 
                              aria-hidden="true"
                            ></i>
                          </span>
                        </button>
                      </td>
                      <td style="padding-left: 0px; padding-right: 0px;">{{ index + 1 }}</td>
                      <td style="padding-left: 5px;">{{ shipment.order_id }}</td>
                      <td style="padding-left: 0px; text-align: center;">
                        <span style="font-weight: bold">{{ shipment.tracking_number }}</span><br />
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
                          {{ formatDate(shipment.picking_time) == "N/A" ? "Not Picked Yet" : formatDate(shipment.picking_time) }}
                        </span>
                      </td>
                      <td style="padding-left: 0px; padding-right: 0px;">
                        <span class="badge" :class="getStatusClass(shipment.status)">
                          {{ shipment.status || '—' }}
                        </span>
                        <br />
                        <span style="font-size: 10px; padding: 2px 5px; margin-top: 10px; border-radius: 10px; margin-top: 20px">
                          {{ formatDate(shipment.updated_at) == 'N/A' ? "Status not changed yet" : formatDate(shipment.updated_at) }}
                        </span>
                      </td>
                      <td class="text-truncate" style="max-width: 160px; padding-left: 0px;">
                        <span style="font-weight: bold">{{ shipment.consignee_name || '—' }}</span><br />
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
                          {{ shipment.consignee_phone || '-' }}
                        </span>
                      </td>
                      <td class="address-cell" style="width: 3%;">
                        <div 
                          class="address-container"
                          :data-shipment-id="shipment.id"
                          @mouseenter="showTooltip(shipment.id, shipment.consignee_address)"
                          @mouseleave="hideTooltip(shipment.id)"
                        >
                          <span v-html="formatAddress(shipment.consignee_address)"></span>
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
                            {{ shipment.destination_city }}
                          </span>
                          

                        </div>
                      </td>
                      <td style="width: 10%; padding-left: 0px; padding-right: 0px; text-align: center;">
                        <span style="font-weight: bold">{{ money(shipment.cod_amount) }}/-</span>
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
                          DC {{ money(shipment?.shippingCharges?.billed_charges || 0) }}/-
                        </span>
                      </td>
                      <td style="padding-left: 0px; padding-right: 0px; text-align: center;">
                        <span class="link-btn">
                          {{ money(shipment?.shippingCharges?.receivable || 0) }}/-
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
                          {{ formatDate(shipment.created_at) }}
                        </span>
                      </td>
                    </tr>
                    
                    <!-- Expandable Detail Row -->
                    <tr v-if="expandedRows.includes(shipment.id)" class="detail-row">
                      <td colspan="9" style="background-color: #f8f9fa; padding: 0;">
                        <div class="detail-content">
                          <!-- Package Details Table -->
                          <div class="row">
                            <div class="col-12">
                              <div class="table-responsive" style="padding-left: 160px;">
                                <table class="table table-sm nested-table">
                                  <thead>
                                    <tr>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Vendor Name</th>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Product Name</th>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Cost</th>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Article Selling</th>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Packaging Material</th>
                                      <th style="color: #7e57c2; font-weight: 600; font-size: 12px;">Article Cost</th>
                                      <th style="color: #7e57c2; font-size: 12px;">Profit</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <!-- Loading state -->
                                    <tr v-if="vendorsLoading[shipment.id]">
                                      <td colspan="7" class="text-center py-4">
                                        <div class="d-flex align-items-center justify-content-center">
                                          <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                          </div>
                                          <span class="text-muted">Loading vendors...</span>
                                        </div>
                                      </td>
                                    </tr>
                                    
                                    <!-- No vendors message -->
                                    <tr v-else-if="!shipmentVendors[shipment.id] || shipmentVendors[shipment.id].length === 0">
                                      <td colspan="7" class="text-center py-5">
                                        <div class="no-vendors-container">
                                          <div class="no-vendors-icon">
                                            <i class="ri-store-2-line"></i>
                                          </div>
                                          <h6 class="no-vendors-title">No Order Items Available</h6>
                                          <p class="no-vendors-description">
                                            This shipment doesn't have any order items yet.
                                          </p>
                                          <div class="no-vendors-hint">
                                            <i class="ri-information-line"></i>
                                            Order items will appear here once they are added to this shipment
                                          </div>
                                        </div>
                                      </td>
                                    </tr>
                                    
                                    <!-- Order item data rows -->
                                    <tr v-else v-for="item in shipmentVendors[shipment.id]" :key="item.id">
                                      <td style="color: #000000; font-size: 12px;">{{ item.vendor_name || 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">{{ item.product_name || 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">{{ item.cost ? item.cost : 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">{{ item.item_price ? item.item_price : 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">{{ item.packaging_material || 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">{{ item.total_amount ? item.total_amount : 'N/A' }}</td>
                                      <td style="color: #000000; font-size: 12px;">
                                        <span v-if="item.profit" class="badge" style="background-color: #c8e6c2; color: #2e7d32;">
                                          {{ item.profit }}
                                        </span>
                                        <span v-else class="text-muted">N/A</span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            
            <hr class="my-3">
            
            <!-- Pagination Info and Controls -->
            <div class="d-flex justify-content-between align-items-center p-3">
              <div class="text-muted">
                Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ filteredShipments.length }} shipments
              </div>
              
                                <div class="d-flex align-items-center gap-2">
                    <button 
                      class="btn btn-outline-secondary btn-sm" 
                      :disabled="currentPage === 1"
                      @click="changePage(1)"
                    >
                      <i class="ri-skip-back-mini-line"></i>
                    </button>
                    <button 
                      class="btn btn-outline-secondary btn-sm" 
                      :disabled="currentPage === 1"
                      @click="changePage(currentPage - 1)"
                    >
                      <i class="ri-arrow-left-s-line"></i>
                    </button>
                    
                    <!-- Page Numbers -->
                    <div class="d-flex align-items-center gap-1">
                      <button 
                        v-for="page in visiblePages" 
                        :key="page"
                        class="btn btn-sm"
                        :class="page === currentPage ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="changePage(page)"
                      >
                        {{ page }}
                      </button>
                    </div>
                    
                    <button 
                      class="btn btn-outline-secondary btn-sm" 
                      :disabled="currentPage === totalPages"
                      @click="changePage(currentPage + 1)"
                    >
                      <i class="ri-arrow-right-s-line"></i>
                    </button>
                    <button 
                      class="btn btn-outline-secondary btn-sm" 
                      :disabled="currentPage === totalPages"
                      @click="changePage(totalPages)"
                    >
                      <i class="ri-skip-forward-mini-line"></i>
                    </button>
                  </div>
            </div>
          </div>
        </div>
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
              {{ allShipments.find(s => s.id === activeTooltip)?.consignee_address || 'N/A' }}
          </div>
      </div>
      <div class="tooltip-arrow"></div>
  </div>

    </AuthCheck>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AuthCheck from '@/components/AuthCheck.vue';

// Reactive state for row expansion
const expandedRows = ref([]);

// Reactive state for vendors
const shipmentVendors = ref({});
const vendorsLoading = ref({});

// Search and pagination state
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(25);

// Tooltip state
const activeTooltip = ref(null);
const tooltipPositions = ref({});

// Sample booking data (all shipments)
const allShipments = ref([
    {
      id: 1,
      order_id: 'ORD001',
      tracking_number: 'LEO10001',
    status: 'Delivered',
      consignee_name: 'Ahmed Ali Khan',
      consignee_phone: '+92-300-1234567',
      consignee_address: 'House #123, Block 6, PECHS, Karachi',
      destination_city: 'Karachi',
      cod_amount: 25000,
      picking_time: '2024-01-15',
    shippingCharges: {
      billed_charges: 500,
      receivable: 20000
    }
    },
    {
      id: 2,
      order_id: 'ORD002',
      tracking_number: 'LEO10002',
    status: 'Dispatched',
      consignee_name: 'Fatima Zahra',
      consignee_phone: '+92-300-2345678',
      consignee_address: 'Flat #45, Building A, Gulberg III, Lahore',
      destination_city: 'Lahore',
      cod_amount: 20000,
      picking_time: '2024-01-14',
    shippingCharges: {
      billed_charges: 400,
      receivable: 17000
    }
  },
        {
          id: 3,
    order_id: 'ORD003',
    tracking_number: 'LEO10003',
    status: 'In Transit',
    consignee_name: 'Muhammad Hassan',
    consignee_phone: '+92-300-3456789',
    consignee_address: 'Shop #12, Main Bazar, Islamabad',
    destination_city: 'Islamabad',
    cod_amount: 15000,
          picking_time: '2024-01-13',
    shippingCharges: {
      billed_charges: 300,
      receivable: 12000
    }
        },
        {
          id: 4,
    order_id: 'ORD004',
    tracking_number: 'LEO10004',
    status: 'Pending',
    consignee_name: 'Sara Ahmed',
    consignee_phone: '+92-300-4567890',
    consignee_address: 'Villa #78, Phase 5, DHA, Karachi',
    destination_city: 'Karachi',
    cod_amount: 30000,
          picking_time: null,
    shippingCharges: {
      billed_charges: 600,
      receivable: 25000
    }
  },
  {
    id: 5,
    order_id: 'ORD005',
    tracking_number: 'LEO10005',
    status: 'Pickup Request Sent',
    consignee_name: 'Ali Raza',
    consignee_phone: '+92-300-5678901',
    consignee_address: 'Office #23, Business District, Faisalabad',
    destination_city: 'Faisalabad',
    cod_amount: 18000,
          picking_time: null,
    shippingCharges: {
      billed_charges: 450,
      receivable: 15000
    }
  },
  {
    id: 6,
    order_id: 'ORD006',
    tracking_number: 'LEO10006',
    status: 'Shipment Picked',
    consignee_name: 'Ayesha Khan',
    consignee_phone: '+92-300-6789012',
    consignee_address: 'House #90, Sector G, Bahria Town, Rawalpindi',
    destination_city: 'Rawalpindi',
    cod_amount: 22000,
    picking_time: '2024-01-16',
    shippingCharges: {
      billed_charges: 550,
      receivable: 18000
    }
  },
  {
    id: 7,
    order_id: 'ORD007',
    tracking_number: 'LEO10007',
    status: 'Cancelled',
    consignee_name: 'Usman Ali',
    consignee_phone: '+92-300-7890123',
    consignee_address: 'Shop #45, Mall Road, Peshawar',
    destination_city: 'Peshawar',
    cod_amount: 12000,
    picking_time: null,
    last_activity: '2024-01-12',
    shipping_charges: {
      shipment_charges: 350,
      receivable: 8000
    }
  },
  {
    id: 8,
    order_id: 'ORD008',
    tracking_number: 'LEO10008',
    status: 'Returned to Shipper',
    consignee_name: 'Hina Malik',
    consignee_phone: '+92-300-8901234',
    consignee_address: 'Flat #67, Block 8, Clifton, Karachi',
    destination_city: 'Karachi',
    cod_amount: 28000,
          picking_time: '2024-01-10',
    shippingCharges: {
      billed_charges: 700,
      receivable: 22000
    }
  }
]);

// Helper functions
const money = (n) => ((n ?? n === 0) ? new Intl.NumberFormat('en-PK').format(Number(n)) : '—');

function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);

  const day = String(date.getDate()).padStart(2, "0");
  const month = date.toLocaleString("en-US", { month: "short" }); // Aug, Sep, etc.
  const year = String(date.getFullYear()).slice(-2); // last 2 digits of year

  return `${day}-${month}-${year}`;
}

// Status class mapping
const statusClassMap = {
  'Drop Off at Express Center': 'drop-off',
  'Shipment Picked': 'shipment-picked',
  'Being Return': 'being-return',
  'Ready for Return': 'ready-for-return',
  'Dispatched': 'dispatched',
  'Delivered': 'delivered',
  'Pending': 'pending',
  'Missroute': 'missroute',
  'Returned to shipper': 'returned-to-shipper',
  'Arrived at Station': 'arrived-at-station',
  'Assign to Courier': 'assign-to-courier',
  'Consignment Booked': 'consignment-booked',
  'Cancelled': 'cancelled',
  'Pickup Request Sent': 'pickup-request-sent',
  '48 Hours Auto Canceled': 'auto-canceled',
  'Pickup Request not Send': 'pickup-request-not-sent',
  'In Transit': 'dispatched'
};

const getStatusClass = (status) => {
  return statusClassMap[status] || 'pending';
};

// Toggle row expansion
const toggleRowExpansion = (rowId) => {
  const index = expandedRows.value.indexOf(rowId);
  if (index > -1) {
    // Collapse row
    expandedRows.value.splice(index, 1);
    
    // Clean up data to free memory
    delete shipmentVendors.value[rowId];
    delete vendorsLoading.value[rowId];
  } else {
    // Expand row - only if not already loading
    if (!vendorsLoading.value[rowId]) {
      expandedRows.value.push(rowId);
      // Fetch vendors when expanding
      fetchVendorsForShipment(rowId);
    }
  }
};

// Fetch vendors for a specific shipment
const fetchVendorsForShipment = async (shipmentId) => {
  if (shipmentVendors.value[shipmentId]) {
    return; // Already fetched
  }
  
  // Set loading state immediately for better UX
  vendorsLoading.value[shipmentId] = true;
  
  try {
    const response = await axios.get(`/api/shipments/${shipmentId}/vendors`);
    shipmentVendors.value[shipmentId] = response.data.data || [];
  } catch (error) {
    console.error('Error fetching vendors:', error);
    shipmentVendors.value[shipmentId] = false;
  } finally {
    vendorsLoading.value[shipmentId] = false;
  }
};

// Get total count
const getTotalCount = () => {
  return allShipments.value.length;
};

// Filtered shipments based on search query
const filteredShipments = computed(() => {
  if (!searchQuery.value.trim()) {
    return allShipments.value;
  }
  
  const query = searchQuery.value.toLowerCase().trim();
  return allShipments.value.filter(shipment => {
    return (
      shipment.order_id?.toLowerCase().includes(query) ||
      shipment.tracking_number?.toLowerCase().includes(query) ||
      shipment.status?.toLowerCase().includes(query) ||
      shipment.consignee_name?.toLowerCase().includes(query) ||
      shipment.consignee_phone?.toLowerCase().includes(query) ||
      shipment.consignee_address?.toLowerCase().includes(query) ||
      shipment.destination_city?.toLowerCase().includes(query) ||
      shipment.cod_amount?.toString().includes(query) ||
      shipment.shippingCharges?.billed_charges?.toString().includes(query) ||
      shipment.shippingCharges?.receivable?.toString().includes(query)
    );
  });
});

// Pagination computed properties
const totalPages = computed(() => Math.ceil(filteredShipments.value.length / itemsPerPage.value));
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value);
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage.value, filteredShipments.value.length));

// Paginated shipments
const paginatedShipments = computed(() => {
  return filteredShipments.value.slice(startIndex.value, endIndex.value);
});

// Visible pages for pagination
const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
  let end = Math.min(totalPages.value, start + maxVisible - 1);
  
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1);
  }
  
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  return pages;
});

// Search and pagination functions
const filterShipments = () => {
  currentPage.value = 1; // Reset to first page when searching
};

const changeItemsPerPage = () => {
  currentPage.value = 1; // Reset to first page when changing items per page
};

const changePage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

// Format address with exactly 25 characters per line
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
  
  // If more than 3 lines (75 characters), add ellipsis
  if (address.length > 75) {
    // Show only first 3 lines and add ellipsis
    const displayChunks = chunks.slice(0, 3);
    return displayChunks.join('<br>') + '<br><span style="color: #6c757d; font-style: italic;">...</span>';
  }
  
  return chunks.join('<br>');
};

// Tooltip functions
const showTooltip = (shipmentId, address) => {
  if (address && address.length > 75) {
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
      top: rect.top - 120 // Position above the cell with enough space for tooltip
    };
  }
};

const getTooltipPosition = (shipmentId) => {
  const position = tooltipPositions.value[shipmentId];
  if (position) {
    return {
      left: `${position.left}px`,
      top: `${position.top}px`
    };
  }
  return {};
};







// ---- API ----
const fetchShipments = async () => {
    try {
        is_fetching.value = true;
        const response = await axios.get('/api/shipments');
        allShipments.value = response.data.data || [];
    } catch (error) {
        console.error('Error fetching shipments:', error);
    } finally {
        is_fetching.value = false;
    }
};

// Existing reactive state
const is_fetching = ref(false);
const totalItems = ref(getTotalCount());

// Refresh data function
const refreshData = async () => {
    await fetchShipments();
    totalItems.value = getTotalCount();
};

// On mount
onMounted(async () => {
    await fetchShipments();
    totalItems.value = getTotalCount();
});

// Cleanup on unmount
onUnmounted(() => {
    // Clear all loading states and data to prevent memory leaks
    vendorsLoading.value = {};
    shipmentVendors.value = {};
    expandedRows.value = [];
});
</script>

<style scoped>
/* Materio Vuetify-like Table Styling */
.v-card {
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  background: white;
  margin-bottom: 1rem;
}

.v-card__loader {
  position: relative;
}

.v-progress-linear {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  overflow: hidden;
}

.v-progress-linear__background {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 100%;
  background-color: #e0e0e0;
}

.v-progress-linear__buffer {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  background-color: #e0e0e0;
  transition: width 0.3s ease;
}

.v-progress-linear__indeterminate {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 100%;
  overflow: hidden;
}

.v-progress-linear__indeterminate.long {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
  background: linear-gradient(90deg, transparent, #696cff, transparent);
  animation: indeterminate-long 2s infinite;
}

.v-progress-linear__indeterminate.short {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
  background: linear-gradient(90deg, transparent, #696cff, transparent);
  animation: indeterminate-short 2s infinite;
}

@keyframes indeterminate-long {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

@keyframes indeterminate-short {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.v-card-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

.v-card-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #2c3e50;
  margin: 0;
}

.v-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.2s ease;
  text-decoration: none;
}

.v-btn--icon {
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
}

.v-btn--variant-text {
  background: transparent;
}

.v-btn--density-comfortable {
  padding: 0.5rem 1rem;
}

.v-btn--density-default {
  padding: 0.75rem 1.5rem;
}

.v-btn--size-default {
  font-size: 0.875rem;
}

.v-btn--color-primary {
  color: #696cff;
}

.v-btn--color-default {
  color: #6c757d;
}

.v-btn--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.v-btn:hover:not(.v-btn--disabled) {
  background-color: rgba(105, 108, 255, 0.1);
}

.v-btn__overlay,
.v-btn__underlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: inherit;
  pointer-events: none;
}

.v-btn__content {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.v-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1em;
  height: 1em;
}

.v-icon--size-default {
  font-size: 1.25rem;
}

.v-table {
  width: 100%;
  border-collapse: collapse;
}

.v-table__wrapper {
  overflow-x: auto;
}

.v-table table {
  width: 100%;
  border-collapse: collapse;
}

.v-table th {
  background-color: #f8f9fa;
  color: #566a7f;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e0e0e0;
}

.v-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: middle;
}

.v-data-table-group-header-row {
  background-color: #f8f9fa;
  font-weight: 600;
}

.v-data-table-group-header-row td:first-child {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #696cff;
}

.v-data-table-group-header-row td:first-child span:last-child {
  color: #6c757d;
  font-weight: 400;
}

.v-divider {
  height: 1px;
  background-color: #e0e0e0;
  border: none;
  margin: 0;
}

.v-data-table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background-color: #f8f9fa;
  border-top: 1px solid #e0e0e0;
}

.v-data-table-footer__items-per-page {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6c757d;
}

.v-input {
  position: relative;
}

.v-input__control {
  position: relative;
}

.v-field {
  position: relative;
  border: 1px solid #d0d0d0;
  border-radius: 4px;
  background: white;
  padding: 0.5rem;
  min-height: 2.5rem;
}

.v-field--active {
  border-color: #696cff;
}

.v-field__overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: inherit;
  pointer-events: none;
}

.v-field__input {
  position: relative;
  display: flex;
  align-items: center;
}

.v-select__selection {
  display: flex;
  align-items: center;
  min-height: 1.5rem;
}

.v-select__selection-text {
  font-size: 0.875rem;
  color: #2c3e50;
}

.v-field__append-inner {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.v-field__outline {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: inherit;
  pointer-events: none;
}

.v-field__outline__start,
.v-field__outline__end {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1px;
  background-color: transparent;
}

.v-field__outline__start {
  left: 0;
}

.v-field__outline__end {
  right: 0;
}

.v-data-table-footer__info {
  font-size: 0.875rem;
  color: #6c757d;
}

.v-pagination {
  display: flex;
  align-items: center;
}

.v-pagination__list {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  gap: 0.25rem;
}

.v-pagination__first,
.v-pagination__prev,
.v-pagination__next,
.v-pagination__last {
  display: flex;
}

.v-pagination .v-btn {
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border-radius: 50%;
  color: #696cff;
}

.v-pagination .v-btn--disabled {
  color: #6c757d;
  opacity: 0.5;
}

.v-pagination .v-btn:hover:not(.v-btn--disabled) {
  background-color: rgba(105, 108, 255, 0.1);
}

/* Avatar styling */
.v-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  overflow: hidden;
}

.v-avatar--size-32 {
  width: 2rem;
  height: 2rem;
}

.v-avatar--variant-tonal {
  background-color: rgba(105, 108, 255, 0.1);
}

.v-avatar-light-bg {
  background-color: #f8f9fa;
}

.primary--text {
  color: #696cff !important;
}

.v-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Chip styling */
.v-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
}

.v-chip--size-small {
  padding: 0.125rem 0.5rem;
  font-size: 0.75rem;
}

.v-chip--color-success {
  background-color: rgba(40, 199, 111, 0.16);
  color: #28c76f;
}

.v-chip--color-primary {
  background-color: rgba(105, 108, 255, 0.16);
  color: #696cff;
}

.v-chip--color-info {
  background-color: rgba(0, 207, 232, 0.16);
  color: #00cfe8;
}

.v-chip--color-error {
  background-color: rgba(234, 84, 85, 0.16);
  color: #ea5455;
}

.v-chip--color-warning {
  background-color: rgba(255, 159, 67, 0.16);
  color: #ff9f43;
}

/* Utility classes */
.d-flex {
  display: flex;
}

.align-center {
  align-items: center;
}

.ms-3 {
  margin-left: 0.75rem;
}

.text-sm {
  font-size: 0.875rem;
}

.font-weight-medium {
  font-weight: 500;
}

.font-weight-bold {
  font-weight: 600;
}

.text-high-emphasis {
  color: #2c3e50;
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.text-no-wrap {
  white-space: nowrap;
}

/* Status badge colors */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 9999px;
  font: 600 13px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  white-space: nowrap;
  border: 1px solid transparent;
}

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

/* Clickable number look */
.link-btn {
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
  font-weight: 600;
}

/* Table styling */
.table {
  border: none;
}

.table th {
  font-size: 10px;
  padding-left: 5px;
  padding-right: 5px;
  border: none;
}

.table td {
  padding-left: 0px;
  padding-right: 0px;
  border: none;
}

.text-truncate {
  max-width: 160px;
}

/* Detail row styling */
.detail-row {
  background-color: #f8f9fa;
}

.detail-content {
  padding: 0;
}

.detail-item {
  margin-bottom: 8px;
  padding: 4px 0;
}

.detail-item strong {
  color: #495057;
  margin-right: 8px;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  border-radius: 0.2rem;
}

.btn-outline-primary {
  color: #0d6efd;
  border-color: #0d6efd;
}

.btn-outline-primary:hover {
  color: #fff;
  background-color: #0d6efd;
  border-color: #0d6efd;
}

.btn-outline-warning {
  color: #ffc107;
  border-color: #ffc107;
}

.btn-outline-warning:hover {
  color: #000;
  background-color: #ffc107;
  border-color: #ffc107;
}

.btn-outline-danger {
  color: #dc3545;
  border-color: #dc3545;
}

.btn-outline-danger:hover {
  color: #fff;
  background-color: #dc3545;
  border-color: #dc3545;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .v-data-table-footer {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .v-table__wrapper {
    font-size: 0.75rem;
  }
  
  .v-table th,
  .v-table td {
    padding: 0.5rem 0.25rem;
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
  border-radius: 0.5rem;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #696cff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Bootstrap overrides for compatibility */
.bg-primary {
  background-color: #696cff !important;
}

.text-muted {
  color: #6c757d !important;
}

.btn-primary {
  background-color: #696cff;
  border-color: #696cff;
}

.btn-primary:hover {
  background-color: #5a5fef;
  border-color: #5a5fef;
}

/* Smooth animations for dropdowns */
.v-data-table-group-header-row {
  background-color: #f8f9fa;
  font-weight: 600;
  transition: background-color 0.2s ease;
}

.v-data-table-group-header-row:hover {
  background-color: #e9ecef;
}

.v-data-table-group-header-row td:first-child {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #696cff;
}

.v-data-table-group-header-row td:first-child span:last-child {
  color: #6c757d;
  font-weight: 400;
}

/* Dropdown animation for rows */
.v-data-table-group-header-row + template tr {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Arrow rotation animation */
.v-icon.flip-in-rtl {
  transition: transform 0.2s ease;
}

.v-icon.ri-arrow-down-s-line {
  transform: rotate(90deg);
}

/* Button hover effects */
.v-btn--icon:hover {
  background-color: rgba(105, 108, 255, 0.1);
  transform: scale(1.05);
}

.v-btn--icon {
  transition: all 0.2s ease;
}

/* Nested table styling */
.nested-table {
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(126, 87, 194, 0.1);
  border: none;
  width: 100%;
  margin-top: 15px;
}

.nested-table thead th {
  background-color: #ffffff !important;
  border-bottom: none;
  padding: 12px 8px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.nested-table tbody td {
  padding: 10px 8px;
  border-bottom: 1px solid #f5f5f5;
  vertical-align: middle;
}

.nested-table tbody tr:hover {
  background-color: #faf5ff;
  transition: background-color 0.2s ease;
}

.nested-table .badge {
  font-size: 10px;
  padding: 4px 8px;
  border-radius: 12px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: normal;
}

/* Detail row enhancements */
.detail-row {
  background: #ffffff !important;
}

.detail-row td {
  background: #ffffff !important;
}

.detail-content h6 {
  color: #7e57c2;
  font-weight: 600;
  border-bottom: 2px solid #ba68c8;
  padding-bottom: 8px;
  margin-bottom: 16px;
}

.detail-item {
  margin-bottom: 12px;
  padding: 8px 0;
}

.detail-item strong {
  color: #6a1b9a;
  font-weight: 600;
}

/* Loading and no data states */
.spinner-border-sm {
  width: 1rem;
  height: 1rem;
}

/* Simple no vendors message styling */
.no-vendors-container {
  padding: 2rem;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.no-vendors-icon {
  margin-bottom: 1rem;
}

.no-vendors-icon i {
  font-size: 2.5rem;
  color: #6c757d;
}

.no-vendors-title {
  color: #495057;
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 0.5rem;
}

.no-vendors-description {
  color: #6c757d;
  font-size: 0.9rem;
  margin-bottom: 1rem;
  line-height: 1.4;
}

.no-vendors-hint {
  background: #e9ecef;
  border-radius: 4px;
  padding: 0.5rem 0.75rem;
  color: #6c757d;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.no-vendors-hint i {
  font-size: 0.9rem;
  color: #6c757d;
}

/* Fixed address cell styling for perfect alignment */
.address-cell {
  width: 200px !important;
  min-width: 200px !important;
  max-width: 200px !important;
  height: auto !important;
  min-height: 60px !important;
  word-wrap: break-word !important;
  white-space: pre-line !important;
  font-size: 13px !important;
  line-height: 1.3 !important;
  vertical-align: top !important;
  padding: 8px 4px !important;
}

/* Light gray table borders - horizontal lines only */
.table {
  border-collapse: collapse;
}

.table th,
.table td {
  border: none !important;
  border-bottom: 1px solid #e9ecef !important;
}

.table thead th {
  border-bottom: 2px solid #dee2e6 !important;
  background-color: #f8f9fa;
}

/* Materio Tooltip Styling */
.address-container {
  position: relative;
  cursor: pointer;
}

.address-cell {
  position: relative;
}

.materio-tooltip {
  position: absolute;
  z-index: 9999;
  pointer-events: none;
  animation: tooltipFadeIn 0.2s ease-out;
  transform: translateX(-50%);
}

.tooltip-content {
  background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
  color: white;
  padding: 12px 16px;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(124, 58, 237, 0.3);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  max-width: 300px;
  min-width: 200px;
}

.tooltip-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
}

.tooltip-header i {
  font-size: 16px;
  color: #fbbf24;
}

.tooltip-body {
  font-size: 13px;
  line-height: 1.4;
  color: rgba(255, 255, 255, 0.95);
  word-wrap: break-word;
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
  border-bottom: 8px solid #7c3aed;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

@keyframes tooltipFadeIn {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}

/* Hover effect for address container */
.address-container:hover {
  background-color: rgba(124, 58, 237, 0.05);
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

/* Global Address Tooltip Styling */
.address-tooltip {
  position: fixed;
  z-index: 9999;
  transform: translateX(-50%);
  pointer-events: none;
}

.address-tooltip .tooltip-content {
  background: #2d3748;
  color: white;
  border-radius: 8px;
  padding: 12px 16px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  max-width: 300px;
  word-wrap: break-word;
}

.address-tooltip .tooltip-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
  color: #e2e8f0;
}

.address-tooltip .tooltip-header i {
  color: #63b3ed;
  font-size: 16px;
}

.address-tooltip .tooltip-body {
  font-size: 13px;
  line-height: 1.4;
  color: #f7fafc;
}

.address-tooltip .tooltip-arrow {
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
</style>
