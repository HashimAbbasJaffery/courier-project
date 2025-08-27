<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    // If these columns exist, casting helps avoid string math
    protected $casts = [
        'booked_packet_collect_amount' => 'float',
        'billed_charges'               => 'float',
        'fuel_surcharge_percentage'    => 'float', // e.g. 15 or 0.15
        'gst'                          => 'float', // e.g. 13 or 0.13
        'osa_charges'                  => 'float',
    ];

    // Flat tax rate (percent). Rename to avoid clashing with "tax" accessor.
    protected float $taxRate = 4.0;

    // Make these computed fields appear in arrays/JSON automatically
    protected $appends = [
        'delivery',
        'fuel',
        'gst_amount',
        'osa',
        'tax_amount',
        'receivable',
    ];

    /** Helper: accept either 15 or 0.15 and return fraction (0.15) */
    private function toFraction(?float $p): float
    {
        if (!$p) return 0.0;
        return $p > 1 ? $p / 100 : $p;
    }

    /** DELIVERY (base billed charges) */
    protected function delivery(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->billed_charges ?? 0.0
        );
    }

    protected function fuel(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->toFraction($this->fuel_surcharge_percentage) * (float) $this->delivery;
            }
        );
    }

    protected function gstAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = $this->delivery + $this->fuel;
                return $this->toFraction( $this->gst) * (float) $total;
            }
        );
    }

    protected function osa(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->osa_charges ?? 0.0
        );
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::make(
            get: function() {
                if($this->shipment->status !== "Returned to shipper") {
                    return $this->toFraction(p: $this->taxRate) * (float) ($this->booked_packet_collect_amount ?? 0.0);
                }
                return 0;
            }
        );
    }

    public function shipment() {
        return $this->belongsTo(Shipment::class, "shipment_id");
    }

    protected function receivable(): Attribute
    {
        $total = $this->delivery + $this->fuel + $this->gst_amount + $this->osa;

        if($this->shipment->status !== "Returned to shipper") {
            $total += $this->tax_amount;
        }

        return Attribute::make(
            get: fn () =>
                (float) ($this->booked_packet_collect_amount ?? 0.0) - $total
        );
    }
}
