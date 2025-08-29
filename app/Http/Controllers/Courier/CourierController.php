<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitiesResource;
use App\Models\Courier;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CourierController extends Controller
{
    public function getCities(Courier $courier) {
        $service = $courier->courier_service;
        if(Cache::has("cities.{$service}")) {
            return CitiesResource::collection(Cache::get("cities.{$service}"));
        } else {
            $response = resolve($service)->getCities();
            Cache::put("cities.{$service}", $response, 60 * 60 * 24); // Cache for 24 hours
            return CitiesResource::collection($response);
        }
    }
    public function createOrder(Request $request, Courier $courier) {

        $service = $courier->courier_service;

        $data = [
            "destination_city" => $request->destination_city,
            "shipment_type" => $request->shipment_type,
            "booked_packet_weight" => $request->booked_packet_weight,
            "booked_packet_no_piece" => $request->booked_packet_no_piece,
            "booked_packet_collect_amount" => $request->booked_packet_collect_amount,
            "order_id" => $request->order_id,
            "consignee_name" => $request->consignee_name,
            "consignee_phone" => $request->consignee_phone,
            "consignee_address" => $request->consignee_address,
            "special_instructions" => $request->special_instructions,
        ];

        $response = resolve($service)->createOrder($data);

        $shipment = Shipment::create([
            "vendor_id" => $request->vendor_id,
            "platform_id" => $request->platform_id,
            "consignee_name" => $request->consignee_name,
            "consignee_phone" => $request->consignee_phone,
            "consignee_address" => $request->consignee_address,
            "destination_city" => $request->destination_city,
            "advance_payment" => $request->advance_payment,
            "shipment_type" => $request->shipment_type,
            "division" => $request->division,
            "product_description" => $request->product_description ?? "",
            "weight" => $request->booked_packet_weight,
            "no_of_pieces" => $request->booked_packet_no_piece,
            "cod_amount" => $request->booked_packet_collect_amount,
            "tracking_number" => $response['track_number'] ?? null,
            "special_instructions" => $request->special_instructions ?? "",
            "slip_link" => $response['slip_link'] ?? "",
            "courier_id" => $courier->id,
            "order_id" => $request->order_id,
            "status" => "Pickup Request not Send"
        ]);



        $items = collect($request->items ?? [])
                    ->filter(function ($item) {
                        // Only require vendor_id, all other fields are optional
                        return !empty($item['vendor_id']);
                    })
                    ->values()
                    ->toArray();

        if(count($items) > 0) {
            foreach ($items as $item) {
                $shipment->items()->create([
                    "vendor_id" => $item["vendor_id"],
                    "item_name" => $item['item_name'] ?? null,
                    "purchase_cost" => $item['purchase_cost'] ?? 0,
                    "item_price" => $item['item_price'] ?? 0,
                    "material_id" => $item['material_id'] ?? null,
                    "advance_payment" => $item['advance_payment'] ?? 0
                ]);
            }
        }

        return response()->json($response);
    }
    public function cancelOrder(Request $request, Courier $courier) {
        $service = $courier->courier_service;

        $tracking_number = $request->tracking_number ?? "KI7505327195";
        $response = resolve($service)->cancelOrder($tracking_number);

        $shipment = Shipment::where("tracking_number", $tracking_number)->update([
            "is_cancelled" => true,
            "status" => "Cancelled"
        ]);

        return response()->json($response);
    }
    public function track(Request $request, Courier $courier) {
        $service = $courier->courier_service;

        $tracking_number = $request->tracking_number ?? "KI7505327195";
        $response = resolve($service)->track($tracking_number);

        return response()->json($response);
    }

    private function baseUrl(): string
    {
        $env = config('services.leopard.env', env('LEOPARD_ENV', 'production'));
        return $env === 'staging'
            ? 'https://merchantapistaging.leopardscourier.com/api'
            : 'https://merchantapi.leopardscourier.com/api';
    }

        private function credentials(): array
    {
        return [
            'api_key'      => env('LEOPARD_API_KEY'),
            'api_password' => env('LEOPARD_API_PASSWORD'),
        ];
    }

    /** GET /api/shipper-advices */
    public function shipperAdvices(Request $req)
    {
        $data = $req->validate([
            'from'              => 'nullable|date_format:Y-m-d',
            'to'                => 'nullable|date_format:Y-m-d',
            'origin_city'       => 'nullable|integer',
            'destination_city'  => 'nullable|integer',
            'start'             => 'nullable|integer',
            'length'            => 'nullable|integer',
        ]);

        $payload = array_merge($this->credentials(), [
            'from_date'        => $data['from'] ?? '',
            'to_date'          => $data['to'] ?? '',
            'origin_city'      => isset($data['origin_city']) ? (int)$data['origin_city'] : '',
            'destination_city' => isset($data['destination_city']) ? (int)$data['destination_city'] : '',
            'start'            => $data['start']  ?? 0,
            'length'           => $data['length'] ?? 100,
        ]);

        $res = Http::timeout(25)
            ->acceptJson()
            ->post($this->baseUrl().'/shipperAdviceList/format/json/', $payload);

        if (!$res->ok()) {
            return response()->json(['error' => 'Shipper advice fetch failed'], 502);
        }

        $json = $res->json();

        $rows = collect($json['packet_list'] ?? [])->map(function ($r) {
            return [
                'track_number'                 => $r['track_number'] ?? null,
                'booked_packet_date'           => $r['booked_packet_date'] ?? null,
                'consignment_name_eng'         => $r['consignment_name_eng'] ?? null,
                'consignment_phone'            => $r['consignment_phone'] ?? null,
                'consignment_address'          => $r['consignment_address'] ?? null,
                'booked_packet_collect_amount' => $r['booked_packet_collect_amount'] ?? null,
                'origin_city_name'             => $r['origin_city_name'] ?? null,
                'destination_city_name'        => $r['destination_city_name'] ?? null,
                'booked_packet_status'         => $r['booked_packet_status'] ?? null,
                'advice_text'                  => $r['shipper_remarks'] ?? ($r['remarks'] ?? null),
                'advice_date_created'          => $r['created_date'] ?? null,
            ];
        })->values();

        return response()->json([
            'status' => $json['status'] ?? 0,
            'error'  => $json['error'] ?? null,
            'data'   => $rows,
        ]);
    }

    /** GET /api/shipper-advices/{cn}/activity */
    public function activity(Request $req, string $cn)
    {
        if (!$cn) {
            throw ValidationException::withMessages(['cn' => 'CN/Tracking number is required']);
        }

        $payload = array_merge($this->credentials(), [
            'product'   => '',
            'status'    => '',
            'Cn_number' => $cn,
            'start'     => 0,
            'length'    => 100,
        ]);

        $res = Http::timeout(20)
            ->acceptJson()
            ->post($this->baseUrl().'/activityLog/format/json/', $payload);

        if (!$res->ok()) {
            return response()->json(['error' => 'Activity log fetch failed'], 502);
        }

        $json = $res->json();

        $history = collect($json['data'] ?? [])->map(fn ($h) => [
            'cn_number'     => $h['cn_number'] ?? $cn,
            'activity_date' => $h['created_date'] ?? null,
            'status'        => $h['status'] ?? ($h['status_description'] ?? null),
            'reason'        => $h['reason'] ?? null,
            'origin_name'   => $h['origin_name'] ?? null,
            'dst_name'      => $h['dst_name'] ?? null,
            'updated_by'    => $h['user_id'] ?? null,
            'station_id'    => $h['station_id'] ?? null,
            'activity'      => $h['activity'] ?? null,
        ])->values();

        return response()->json(['data' => $history], 200);
    }

    /** GET /api/shipper-advices/direct - Direct from LeopardCourier service */
    public function shipperAdvicesDirect(Request $req)
    {
        $data = $req->validate([
            'from_date'         => 'nullable|date_format:Y-m-d',
            'to_date'           => 'nullable|date_format:Y-m-d',
            'origin_city'       => 'nullable|string',
            'destination_city'  => 'nullable|string',
        ]);

        try {
            $leopardCourier = app('leopard');

            $result = $leopardCourier->getShipperAdvice($data);

            // Add some debugging
            \Log::info('Shipper Advice API Response', [
                'request_data' => $data,
                'api_result' => $result
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Shipper Advice API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch shipper advice: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

}
