<?php

namespace App\Models;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Shipment extends Model
{
    protected $guarded = [ "id", "created_at", "updated_at" ];


    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function courier() {
        return $this->belongsTo(Courier::class);
    }

    public function payment() {
        return $this->hasOne(Payment::class);
    }


    protected static function booted() {
        static::creating(function($shipment) {
            if (!empty($shipment->slip_link)) {

                $pdfContent = file_get_contents($shipment->slip_link);

                $fileName = 'shipments/' . Str::uuid() . '.pdf';

                Storage::disk('local')->put($fileName, $pdfContent);

                $shipment->slip_link = $fileName;
            }
        });

        static::created(function($shipment) {
            $charges = resolve("leopard")->getCharges($shipment->tracking_number);
            foreach($charges["data"] as $charge) {
                $shipment->shippingCharges()->create([
                    "shipment_id" => $shipment->id,
                    ...$charge
                ]);
            }

            Payment::create([
                "shipment_id" => $shipment->id,
            ]);
        });
    }
    public function shippingCharges() {
        return $this->hasOne(Charge::class);
    }
}
