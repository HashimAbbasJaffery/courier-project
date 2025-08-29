<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate total payments based on date range if provided
        $toPay = $this->toPay;
        
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            
            // Calculate total payments only from items within the date range
            $toPay = $this->items()
                ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->get()
                ->sum(function($item) {
                    return $item->purchase_cost - $item->payment;
                });
        }
        
        return [
            "id" => $this->id,
            "name" => $this->name,
            "toPay" => $toPay,
        ];
    }
}
