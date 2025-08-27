<script setup lang="ts">
// @ts-ignore (jQuery globals available)
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { nextTick, onMounted, reactive, ref, watch } from 'vue'

// ---- State ----
const advices = ref<any[]>([])
const cities  = ref<any[]>([])
const is_loading = ref(true)
const is_fetching = ref(false)

const filters = reactive({
  dateRange: '',
  status: '',
  originCity: '',
  destination_city: '',
})

// ---- Helpers ----
const money = (n?: number | string) =>
  (n ?? n === 0) ? new Intl.NumberFormat('en-PK').format(Number(n)) : '—'

function formatDate(dateString?: string) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const d = String(date.getDate()).padStart(2, '0')
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const y = date.getFullYear()
  return `${d}/${m}/${y}`
}

// ---- DataTables ----
const tableId = '#adviceTable'
const initDT = () => {
  if ($.fn.DataTable.isDataTable(tableId)) $(tableId).DataTable().destroy()
  nextTick(() => {
    $(tableId).DataTable({
      info: false,
      searching: false,
      lengthChange: false,
      scrollX: true,
      autoWidth: false,
      pageLength: 10,
      columnDefs: [{ targets: -1, orderable: false }],
    })
  })
}

// ---- API ----
const fetchCities = async () => {
  try {
    const res = await axios.get(route('courier.cities', { courier: 'leopard' }))
    cities.value = res.data?.data ?? []
  } catch (e) { console.error('cities error', e) }
}

const fetchAdvices = async () => {
  try {
    is_fetching.value = true;
    const [from, to] = (filters.dateRange || '').split(' - ')
    const res = await axios.get('/api/shipper-advices', {
      params: {
        from: from || '',
        to: to || '',
        origin_city: filters.originCity || '',
        destination_city: filters.destination_city || '',
        status: filters.status || '',
      },
    })

    advices.value = (res.data?.data ?? []).map((x: any) => ({
      track_number: x.track_number,
      booked_packet_date: x.booked_packet_date,
      consignment_name_eng: x.consignment_name_eng || x.consignee_name || x.consignee || '—',
      consignment_phone: x.consignment_phone || x.consignee_phone || '—',
      origin_city_name: x.origin_city_name || '—',
      destination_city_name: x.destination_city_name || '—',
      booked_packet_collect_amount: x.booked_packet_collect_amount,
      booked_packet_status: x.booked_packet_status || '—',
      pending_reason: x.pending_reason || '',
      advice_text: x.advice_text || '—',
      advice_date_created: x.advice_date_created,
    }))

    initDT()
  } catch (e) { console.error('advices error', e) } finally {
    is_fetching.value = false;
  }
}

// ---- Lifecycle ----
onMounted(async () => {
  await Promise.all([fetchCities(), fetchAdvices()])

  nextTick(() => {
    // EXACT same widgets & IDs as your shipments page
    $('#origin_city')
      .select2()
      .on('change', (e: any) => { filters.originCity = $(e.target).val() as string })

    $('#destination_city')
      .select2()
      .on('change', (e: any) => { filters.destination_city = $(e.target).val() as string })

    $('#status')
      .select2()
      .on('change', (e: any) => { filters.status = $(e.target).val() as string })

    $('#daterange').daterangepicker(
      { locale: { format: 'YYYY-MM-DD' } },
      function (start: any, end: any) {
        filters.dateRange = `${start.format('YYYY-MM-DD')} - ${end.format('YYYY-MM-DD')}`
      }
    )
  })

  is_loading.value = false
})

// ---- Filters watcher (same pattern as shipments page) ----
watch(filters, async () => {
  await fetchAdvices()
})
</script>

<template>
  <div v-if="!is_loading" class="container-xxl container-p-y flex-grow-1">
    <!-- EXACT SAME FILTER BAR (IDs, structure, classes, options) -->
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
              <!-- Origin uses CITY NAME as value (same as shipments page) -->
              <option v-for="city in cities" :key="city.id + '-o'" :value="city.city">{{ city.city }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label><strong>Destination City:</strong></label>
            <select id="destination_city" class="form-control select2">
              <option value="">All Destinations</option>
              <!-- Destination uses CITY ID as value (same as shipments page) -->
              <option v-for="city in cities" :key="city.id + '-d'" :value="city.id">{{ city.city }}</option>
            </select>
          </div>
        </div>
      </form>
    </div>

    <!-- Advice table -->
    <div class="card position-relative">
      <div class="d-flex justify-content-between align-items-center px-3 pt-3">
        <h5 class="card-header border-0 p-0">Shipper Advices</h5>
      </div>
      
      <!-- Loading overlay -->
      <div v-if="is_fetching" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="mt-3 text-muted">Fetching data...</div>
      </div>
      <div class="table-responsive text-nowrap">
        <table id="adviceTable" class="table-bordered table align-middle">
          <thead>
            <tr>
              <th>CN</th>
              <th>Booked On</th>
              <th>Consignee</th>
              <th>Journey</th>
              <th>COD Value</th>
              <th>Status</th>
              <th>Pending Reason</th>
              <th>Advice</th>
              <th>Advice Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in advices" :key="row.track_number">
              <td><strong>{{ row.track_number }}</strong></td>
              <td>{{ formatDate(row.booked_packet_date) }}</td>
              <td class="text-truncate" style="max-width: 180px;">
                <div class="fw-semibold">{{ row.consignment_name_eng }}</div>
                <div class="text-muted" style="font-size:12px">{{ row.consignment_phone }}</div>
              </td>
              <td>{{ row.origin_city_name }} -> {{ row.destination_city_name }}</td>
              <td>{{ money(row.booked_packet_collect_amount) }} PKR</td>
              <td>{{ row.booked_packet_status }}</td>
              <td>{{ row.pending_reason }}</td>
              <td class="text-truncate" style="max-width: 260px;">{{ row.advice_text }}</td>
              <td>{{ formatDate(row.advice_date_created) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <div v-else style="display:flex;align-items:center;justify-content:center;flex-direction:column;height:60vh">
    <span class="loader"></span>
  </div>
</template>

<style scoped>
.loader{width:48px;height:48px;border:5px solid black;border-bottom-color:transparent;border-radius:50%;display:inline-block;box-sizing:border-box;animation:rotation 1s linear infinite}
@keyframes rotation{to{transform:rotate(360deg)}}
@media (max-width:576px){
  #adviceTable td,#adviceTable th{padding:.55rem .625rem;font-size:.9rem;white-space:nowrap}
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
</style>
