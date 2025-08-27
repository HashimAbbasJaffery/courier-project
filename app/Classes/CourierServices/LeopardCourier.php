<?php

namespace App\Classes\CourierServices;
use App\Interfaces\Courier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class LeopardCourier implements Courier
{
    public function __construct(
        private String $secretKey,
        private String $password,
        private String $endpoint
    )
    {
        //
    }
    public function cancelOrder(String $trackingNumber) {
        $endpoint = "{$this->endpoint}/cancelBookedPackets/format/json";
        $response = Http::post($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "cn_numbers" => $trackingNumber
        ]);


        return $response->json();
    }
    public function createOrder(array $data): array {
        $endpoint = "{$this->endpoint}/bookPacket/format/json";

        $response = Http::post($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "destination_city" => $data["destination_city"],
            "shipment_type" => $data["shipment_type"],
            "origin_city" => 'self',
            "shipment_name_eng" => 'self',
            "shipment_phone" => 'self',
            "shipment_address" => 'self',
            "booked_packet_weight" => $data["booked_packet_weight"],
            "booked_packet_no_piece" => $data["booked_packet_no_piece"],
            "booked_packet_collect_amount" => $data["booked_packet_collect_amount"],
            "booked_packet_order_id" => $data["order_id"],
            "consignment_name_eng" => $data["consignee_name"],
            "consignment_phone" => $data["consignee_phone"],
            "consignment_address" => $data["consignee_address"],
            "special_instructions" => $data["special_instructions"],
        ]);

        return $response->json();
    }
    public function getCities(): array {
        $endpoint = "{$this->endpoint}/getAllCities/format/json";
        $response = Http::get($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password
        ]);
        return $response->json()["city_list"];
    }
    public function track(String $trackingNumber) {
        $endpoint = "{$this->endpoint}/trackBookedPacket/format/json";

        $response = Http::get($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "track_numbers" => $trackingNumber
        ]);


        return $response->json();
    }
    public function getLastStatus(String $trackingNumber) {
        $endpoint = "{$this->endpoint}/trackBookedPacket/format/json/";

        $response = Http::get($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "track_numbers" => $trackingNumber
        ]);

        return $response->json();
    }

    public function getCharges(String $trackingNumber) {
        $endpoint = "{$this->endpoint}/getShippingCharges/format/json/";

        $response = Http::get($endpoint, [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "cn_numbers" => $trackingNumber
        ]);

        return $response->json();
    }

    public function getShipperAdvice(array $filters = []): array {
        $endpoint = "{$this->endpoint}/shipperAdviceList/format/json/";
        
        $payload = [
            "api_key" => $this->secretKey,
            "api_password" => $this->password,
            "from_date" => $filters['from_date'] ?? date('Y-m-d', strtotime('-30 days')),
            "to_date" => $filters['to_date'] ?? date('Y-m-d'),
            "origin_city" => $filters['origin_city'] ?? '',
            "destination_city" => $filters['destination_city'] ?? '',
        ];

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($endpoint, $payload);

        if (!$response->ok()) {
            return [
                'status' => 'error',
                'message' => 'Failed to fetch shipper advice data',
                'data' => []
            ];
        }

        $json = $response->json();
        
        return [
            'status' => $json['status'] ?? 'success',
            'message' => $json['error'] ?? null,
            'data' => $json['packet_list'] ?? []
        ];
    }
}
