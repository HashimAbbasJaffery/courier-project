<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const shipment = ref<Record<string, any>>({})
const tracking_number = route().params.booking || 'N/A';
const is_fetching_data = ref(true);

onMounted(async () => {
  const response = await axios.get(route('order.track', { courier: 'leopard', tracking_number: tracking_number }))
  is_fetching_data.value = false;
  console.log(response);
  shipment.value = response.data.packet_list[0] ?? {}
  console.log(shipment.value);
})

const back = () => {
    window.history.back();
}
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
</style>
<template>
  <div v-if="!is_fetching_data" class="container">
    <h3>📦 Packet Details for Tracking Number: {{ shipment.track_number || 'N/A' }}</h3>

    <table class="custom-table">
      <tbody>
        <tr><th>Booking Date</th><td>{{ shipment.booking_date || 'N/A' }}</td></tr>
        <tr><th>Track Number</th><td>{{ shipment.track_number || 'N/A' }}</td></tr>
        <tr><th>Weight (g)</th><td>{{ shipment.booked_packet_weight || 'N/A' }}</td></tr>
        <tr><th>Pieces</th><td>{{ shipment.booked_packet_no_piece || 'N/A' }}</td></tr>
        <tr><th>COD Amount</th><td>{{ shipment.booked_packet_collect_amount || 'N/A' }}</td></tr>
        <tr><th>Order ID</th><td>{{ shipment.booked_packet_order_id || 'N/A' }}</td></tr>
        <tr><th>Origin City</th><td>{{ shipment.origin_city_name || 'N/A' }}</td></tr>
        <tr><th>Destination City</th><td>{{ shipment.destination_city_name || 'N/A' }}</td></tr>
        <tr><th>Shipment Name</th><td>{{ shipment.shipment_name_eng || 'N/A' }}</td></tr>
        <tr><th>Shipment Phone</th><td>{{ shipment.shipment_phone || 'N/A' }}</td></tr>
        <tr><th>Shipment Address</th><td>{{ shipment.shipment_address || 'N/A' }}</td></tr>
        <tr><th>Consignee Name</th><td>{{ shipment.consignment_name_eng || 'N/A' }}</td></tr>
        <tr><th>Consignee Phone</th><td>{{ shipment.consignment_phone || 'N/A' }}</td></tr>
        <tr><th>Consignee Address</th><td>{{ shipment.consignment_address || 'N/A' }}</td></tr>
        <tr><th>Status</th><td>{{ shipment.booked_packet_status || 'N/A' }}</td></tr>
      </tbody>
    </table>

    <button @click="back" class="btn-back">🔙 Back to Manage Booked Packets</button>
  </div>
  <div v-else style="display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100vh;">
    <span class="loader"></span>
  </div>
</template>

<style scoped>
/* Container full width with horizontal padding */
.container {
  width: 100%;
  padding: 0 1rem;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  box-sizing: border-box;
}

h3 {
  margin-bottom: 1.5rem;
  font-weight: 600;
  font-size: 1.5rem;
  color: #222;
}

.custom-table {
  width: 100%; /* fill container width */
  border-collapse: collapse;
  box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}

.custom-table th,
.custom-table td {
  padding: 12px 15px;
  text-align: left;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: middle;
}

.custom-table th {
  background-color: #f5f7fa;
  font-weight: 600;
  width: 40%;
  color: #555;
}

.custom-table tr:last-child td {
  border-bottom: none;
}

.btn-back {
  display: inline-block;
  margin-top: 1.5rem;
  padding: 10px 20px;
  background-color: #0d6efd;
  color: white;
  text-decoration: none;
  font-weight: 600;
  border-radius: 6px;
  transition: background-color 0.3s ease;
}

.btn-back:hover {
  background-color: #084cd8;
}
</style>
