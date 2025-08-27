<?php
// app/Jobs/ImportLeopardCsvJob.php
namespace App\Jobs;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportLeopardCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $path;

    // optional tuning
    public int $tries = 3;
    public int $timeout = 300; // seconds

    public function __construct(string $path)
    {
        $this->path = $path;          // e.g. storage_path('app/sleep.csv')
        $this->onQueue('imports');    // optional queue name
    }

    public function handle(): void
    {
        $path = $this->path;

        $data = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ','); // first row as header

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) continue;
                $assoc = @array_combine($header, $row);
                if (!$assoc) continue;
                $data[] = $assoc;
            }
            fclose($handle);
        }

        foreach ($data as $row) {
            $resp    = resolve('leopard')->track($row['CN #']);
            $packets = $resp['packet_list'] ?? [];

            foreach ($packets as $packet) {
                Shipment::create([
                    'consignee_name'       => $packet['consignment_name_eng'],
                    'consignee_phone'      => $packet['consignment_phone'],
                    'consignee_address'    => $packet['consignment_address'],
                    'destination_city'     => $packet['destination_city_name'],
                    'origin_city'          => $packet['origin_city_name'],
                    'shipment_type'        => 'OVERNIGHT',
                    'product_description'  => 'I am the product description',
                    'weight'               => $packet['arival_dispatch_weight'],
                    'no_of_pieces'         => 1,
                    'cod_amount'           => $packet['booked_packet_collect_amount'],
                    'is_cancelled'         => $packet['booked_packet_status'] === 'Cancelled',
                    'tracking_number'      => $packet['track_number'],
                    'status'               => $packet['booked_packet_status'],
                    'special_instructions' => $packet['special_instructions'],
                    'slip_link'            => null,
                    'division'             => 'EXPRESS',
                    'courier_id'           => 1,
                    'order_id'             => $packet['booked_packet_id'],
                    'vendor_id'            => 1,
                    'picking_time'         => $packet['Tracking Detail'][0]['Activity_Date'] ?? null,
                    'advance_payment'      => 1,
                    'last_activity'        => $packet['Tracking Detail'][count($packet['Tracking Detail']) - 1]['Activity_Date'] ?? null,
                ]);
            }
        }
    }
}
