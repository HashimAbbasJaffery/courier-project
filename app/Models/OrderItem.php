<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ["id", "updated_at"];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

}
