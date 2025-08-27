<?php

namespace App\Models;
use App\Models\OrderItem;

use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = [ "id", "created_at", "updated_at" ];

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function items() {
        return $this->hasMany(OrderItem::class);
    }

    public function toPay(): Attribute {
        return Attribute::make(
            get: function($value) {
                return $this->items->sum(function($item) {
                    return $item->purchase_cost - $item->payment;
                });
            }
        );
    }
}
