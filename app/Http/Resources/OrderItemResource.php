<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_name' => $this->vendor->name ?? 'N/A',
            'product_name' => $this->item_name ?? 'N/A',
            'item_price' => $this->item_price ?? 0,
            'cost' => $this->purchase_cost ?? 0,
            'selling_cost' => $this->purchase_cost ?? 0, // Maps to purchase_cost in database
            'packaging_material' => $this->material->name ?? 'N/A',
            'total_amount' => $this->total_amount ?? 0,
            'profit' => $this->profit ?? 0,
        ];
    }
}
