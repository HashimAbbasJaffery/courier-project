<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request) {
        $destination_city = $request->destination_city ?? null;
        $date_from = $request->date_from ?? null;
        $date_to = $request->date_to ?? null;
        $status = $request->status ?? null;
        $origin_city = $request->origin_city ?? null;

        $shipments = Shipment::query()
                            ->when($destination_city, function ($query) use ($destination_city) {
                                return $query->where('destination_city', $destination_city);
                            })
                            ->when($origin_city, function ($query) use ($origin_city) {
                                return $query->where('origin_city', $origin_city);
                            })
                            ->when($status, function ($query) use ($status) {
                                return $query->where('status', $status);
                            })
                            ->when($date_from, function($query) use($date_from) {
                                return $query->where('created_at', ">=", $date_from);
                            })
                            ->when($date_to, function($query) use($date_to) {
                                return $query->where('created_at', '<=', $date_to);
                            })
                            ->latest()
                            ->with("shippingCharges")
                            ->get();

        return response()->json(["data" => $shipments]);
    }

    public function search(Request $request) {
        $query = $request->get('query');
        $field = $request->get('field', 'order_id'); // Default to order_id if no field specified
        
        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }

        // Validate the field parameter
        $allowedFields = ['order_id', 'tracking_number', 'consignee_name', 'consignee_phone'];
        if (!in_array($field, $allowedFields)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search field'
            ], 400);
        }

        // Search by specific field
        $shipment = Shipment::where($field, 'LIKE', "%{$query}%")
            ->with(['shippingCharges'])
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'No shipment found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $shipment
        ]);
    }

    public function updatePayment(Request $request) {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'vendor_id' => 'required|exists:vendors,id',
            'platform_id' => 'required|exists:platforms,id',
            'item_name' => 'required|string|max:255',
            'purchase_cost' => 'required|numeric|min:0',
            'item_price' => 'required|numeric|min:0',
            'material_id' => 'required|exists:materials,id',
            'advance_payment' => 'required|numeric|min:0'
        ]);

        try {
            // Get the shipment
            $shipment = Shipment::findOrFail($request->shipment_id);
            
            // Get material price for profit calculation
            $material = \App\Models\Material::findOrFail($request->material_id);
            $materialPrice = $material->price;
            
            // Calculate profit
            $profit = $request->item_price - $request->purchase_cost - $materialPrice;
            
            // Create or update order item with payment details
            $orderItem = \App\Models\OrderItem::updateOrCreate(
                [
                    'shipment_id' => $request->shipment_id,
                    'vendor_id' => $request->vendor_id,
                    'platform_id' => $request->platform_id
                ],
                [
                    'shipment_id' => $request->shipment_id,
                    'vendor_id' => $request->vendor_id,
                    'platform_id' => $request->platform_id,
                    'item_name' => $request->item_name,
                    'purchase_cost' => $request->purchase_cost,
                    'item_price' => $request->item_price,
                    'material_id' => $request->material_id,
                    'advance_payment' => $request->advance_payment,
                    'total_amount' => $request->item_price,
                    'profit' => $profit,
                    'material_price' => $materialPrice,
                    'updated_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment details updated successfully',
                'data' => $orderItem
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function track($trackingNumber) {
        try {
            // Use the LeopardCourier service to track the shipment
            $leopardCourier = app('leopard');
            $trackingData = $leopardCourier->track($trackingNumber);
            
            if (isset($trackingData['status']) && $trackingData['status'] == 1) {
                return response()->json([
                    'success' => true,
                    'data' => $trackingData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $trackingData['error'] ?? 'Failed to fetch tracking data'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tracking data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook endpoint to update shipment status from courier service
     * 
     * Expected payload format:
     * {
     *   "data": [
     *     {
     *       "cn_number": "string",        // Tracking number
     *       "status": "string",           // New status
     *       "receiver_name": "string",    // Optional: receiver name
     *       "reason": "string",           // Optional: status reason
     *       "activity_date": "yyyy-mm-dd H:i:s"  // Activity timestamp
     *     }
     *   ]
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request) {
        try {
            // Optional: Add webhook signature verification here for security
            // if (config('app.webhook_secret')) {
            //     $signature = $request->header('X-Webhook-Signature');
            //     if (!$this->verifyWebhookSignature($request, $signature)) {
            //         return response()->json(['error' => 'Invalid webhook signature'], 401);
            //     }
            // }

            // Validate the incoming webhook data
            $request->validate([
                'data' => 'required|array',
                'data.*.cn_number' => 'required|string',
                'data.*.status' => 'required|string',
                'data.*.receiver_name' => 'nullable|string',
                'data.*.reason' => 'nullable|string',
                'data.*.activity_date' => 'required|date_format:Y-m-d H:i:s'
            ]);

            $data = $request->data;
            $updatedCount = 0;
            $errors = [];

            foreach ($data as $webhookData) {
                try {
                    // Find shipment by tracking number (cn_number)
                    $shipment = Shipment::where("tracking_number", $webhookData["cn_number"])->first();

                    if ($shipment) {
                        // Store original status for logging
                        $originalStatus = $shipment->status;
                        
                        // Update shipment status and last activity
                        $shipment->update([
                            "status" => $webhookData["status"],
                            "last_activity" => $webhookData["activity_date"],
                            "updated_at" => now()
                        ]);

                        // Refresh the model to get updated values
                        $shipment->refresh();

                        // Log the status update for tracking
                        \Log::info('Shipment status updated via webhook', [
                            'tracking_number' => $webhookData["cn_number"],
                            'old_status' => $originalStatus,
                            'new_status' => $webhookData["status"],
                            'activity_date' => $webhookData["activity_date"],
                            'receiver_name' => $webhookData["receiver_name"] ?? null,
                            'reason' => $webhookData["reason"] ?? null
                        ]);

                        $updatedCount++;
                    } else {
                        $errors[] = "Shipment with tracking number {$webhookData['cn_number']} not found";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error updating shipment {$webhookData['cn_number']}: " . $e->getMessage();
                    \Log::error('Webhook shipment update error', [
                        'tracking_number' => $webhookData["cn_number"],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Return success response
            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} shipment(s)",
                'updated_count' => $updatedCount,
                'errors' => $errors,
                'total_processed' => count($data)
            ]);

        } catch (\Exception $e) {
            \Log::error('Webhook updateStatus error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify webhook signature for security (optional)
     * Uncomment and configure if you want to add webhook authentication
     */
    // private function verifyWebhookSignature(Request $request, $signature)
    // {
    //     if (!$signature) {
    //         return false;
    //     }
    //     
    //     $payload = $request->getContent();
    //     $expectedSignature = hash_hmac('sha256', $payload, config('app.webhook_secret'));
    //     
    //     return hash_equals($expectedSignature, $signature);
    // }
}
