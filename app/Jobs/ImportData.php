<?php

namespace App\Jobs;

use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportData implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $path = storage_path('app/sleep.csv'); // CSV placed in storage/app/

        $data = [];
        if (($handle = fopen($path, "r")) !== false) {
            $header = fgetcsv($handle, 1000, ","); // get first row as header

            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                $data[] = array_combine($header, $row); // map header to row
            }
            fclose($handle);
        }


        foreach ($data as $row) {
            $packets = resolve("leopard")->track($row["CN #"])["packet_list"];

            foreach ($packets as $packet) {
                Shipment::create([
                    "consignee_name" => $packet["consignment_name_eng"],
                    "consignee_phone" => $packet["consignment_phone"],
                    "consignee_address" => $packet["consignment_address"],
                    "destination_city" => $packet["destination_city_name"],
                    "origin_city" => $packet["origin_city_name"],
                    "shipment_type" => "OVERNIGHT",
                    "product_description" => "I am the product description",
                    "weight" => $packet["arival_dispatch_weight"],
                    "no_of_pieces" => 1,
                    "cod_amount" => $packet["booked_packet_collect_amount"],
                    "is_cancelled" => $packet["booked_packet_status"] === "Cancelled" ? true : false,
                    "tracking_number" => $packet["track_number"],
                    "status" => $packet["booked_packet_status"],
                    "special_instructions" => $packet["special_instructions"],
                    "slip_link" => null,
                    "division" => "EXPRESS",
                    "courier_id" => 1,
                    "order_id" => $packet["booked_packet_id"],
                    "vendor_id" => 1,
                    "picking_time" => $packet["Tracking Detail"][0]["Activity_Date"],
                    "advance_payment" => 1,
                    "last_activity" => $packet["Tracking Detail"][count($packet["Tracking Detail"]) - 1]["Activity_Date"]
                ]);
            }
            // Shipment::create(
            //     "consignee_name" =>
            // )
        }

    }
}
