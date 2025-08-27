<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function get() {
        $vendors = \App\Models\Vendor::query()->get();

        return \App\Http\Resources\VendorResource::collection($vendors);
    }
    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $vendor = \App\Models\Vendor::create([
            'name' => $request->name,
        ]);

        return response()->json(['message' => 'Vendor created successfully', 'vendor' => $vendor], 201);
    }
    public function update(Request $request, $id) {
        $vendor = \App\Models\Vendor::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $vendor->update([
            'name' => $request->name,
        ]);

        return response()->json(['message' => 'Vendor updated successfully', 'vendor' => $vendor]);
    }
    public function destroy($id) {
        $vendor = \App\Models\Vendor::findOrFail($id);
        $vendor->delete();
        return response()->json(['message'=> '']);
    }

    public function getOrderItems($vendorId) {
        try {
            $orderItems = \App\Models\OrderItem::where('vendor_id', $vendorId)
                ->select('id', 'created_at', 'item_name', 'purchase_cost', 'payment')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orderItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching order items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function savePayments($vendorId, Request $request) {
        try {
            $request->validate([
                'payments' => 'required|array',
                'payments.*.id' => 'required|integer',
                'payments.*.date' => 'required|date',
                'payments.*.description' => 'required|string|max:255',
                'payments.*.debit' => 'nullable|numeric|min:0',
                'payments.*.credit' => 'nullable|numeric|min:0',
            ]);

            $payments = $request->input('payments');
            $updatedCount = 0;

            foreach ($payments as $payment) {
                $orderItem = \App\Models\OrderItem::find($payment['id']);
                
                if ($orderItem && $orderItem->vendor_id == $vendorId) {
                    \Log::info('Updating order item', [
                        'id' => $orderItem->id,
                        'old_created_at' => $orderItem->created_at,
                        'new_date' => $payment['date'],
                        'description' => $payment['description'],
                        'debit' => $payment['debit'] ?? 0,
                        'credit' => $payment['credit'] ?? 0,
                    ]);
                    
                    $orderItem->update([
                        'created_at' => $payment['date'],
                        'item_name' => $payment['description'],
                        'purchase_cost' => $payment['debit'] ?? 0,
                        'payment' => $payment['credit'] ?? 0,
                    ]);
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} payment(s)",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving payments: ' . $e->getMessage()
            ], 500);
        }
    }
}
