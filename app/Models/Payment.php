<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        "shipment_id",
        "billing_method",
        "status",
        "invoice_cheque_no",
        "invoice_cheque_date",
        "payment_method",
        "message",
        "slip_link"
     ];
}
