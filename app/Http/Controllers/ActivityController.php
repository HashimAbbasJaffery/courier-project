<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function get(String $tracking_number) {
        $response = resolve("leopard")->track($tracking_number);
        return response()->json($response[0]["Tracking Detail"]);
    }
}
