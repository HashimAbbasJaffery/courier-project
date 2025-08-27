<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class ShipmentVendorController extends Controller
{
    /**
     * Get order items for a specific shipment - Fast API endpoint
     */
    public function getVendors($shipmentId): JsonResponse
    {
        try {
            // Ultra-fast query with eager loading and resource transformation
            $orderItems = OrderItem::select([
                'id', 'item_name', 'purchase_cost', "item_price", 'total_amount', 'vendor_id', 'material_id', 'profit'
            ])
            ->where('shipment_id', $shipmentId)
            ->with([
                'vendor:id,name',
                'material:id,name'
            ])
            ->get();

            return response()->json([
                'success' => true,
                'data' => OrderItemResource::collection($orderItems),
                'message' => 'Order items retrieved successfully',
                'count' => $orderItems->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error retrieving order items: ' . $e->getMessage()
            ], 500);
        }
    }
}
