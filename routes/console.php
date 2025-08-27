<?php

use App\Mail\ShipmentStatusChanged;
use App\Models\Shipment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;


Schedule::call(function () {
    // 1) Fetch cities (HTTP response -> JSON)
    $citiesRes = Http::retry(2, 300)->connectTimeout(5)->timeout(20)
        ->get(route('courier.cities', ['courier' => 'leopard']));

    if (! $citiesRes->successful()) {
        \Log::error('Cities fetch failed: '.$citiesRes->body());
        return;
    }

    $cities = $citiesRes->json('data') ?? [];
    // Build quick map: id -> city name
    $cityById = [];
    foreach ($cities as $c) {
        if (isset($c['id'])) {
            $cityById[$c['id']] = $c['city'] ?? null;
        }
    }

    // 2) Load shipments to check (and index by tracking number)
    $shipments = Shipment::whereNotIn('status', ['Cancelled','Delivered'])->get();
    if ($shipments->isEmpty()) return;

    $byTrack = $shipments->keyBy('tracking_number');

    // 3) Build CSV of tracking numbers: trim, unique, no blanks, no trailing comma
    $csv = $byTrack->keys()
        ->map(fn ($t) => trim((string) $t))
        ->filter(fn ($t) => $t !== '')
        ->unique()
        ->implode(',');

    if ($csv === '') return;

    // 4) Call the courier service via your container binding (leopard)
    /** @var \App\Services\LeopardService $tracker */
    $tracker = resolve('leopard'); // or app(\App\Services\LeopardService::class)

    $resp = $tracker->track($csv); // returns Illuminate\Http\Client\Response

    if (! $resp->successful()) {
        \Log::error('Track API failed: '.$resp->body());
        return;
    }

    $data    = $resp->json();
    $packets = $data['packet_list'] ?? [];

    foreach ($packets as $packet) {
        $track = $packet['track_number'] ?? null;
        $new   = $packet['booked_packet_status'] ?? null;
        if (! $track || ! $new) continue;

        /** @var \App\Models\Shipment|null $shipment */
        $shipment = $byTrack->get($track);
        if (! $shipment) {
            \Log::warning("Unknown tracking number in response: {$track}");
            continue;
        }

        $old = $shipment->status;
        if ($old === $new) continue; // no change, skip

        // 5) Update status (preserve existing picking_time; only set if first time picked)
        $update = [
            'status' => $new,
            'last_actvity' => now(),
        ];

        if ($new === 'Shipment Picked' && is_null($shipment->picking_time)) {
            $update['picking_time'] = now();
        }
        $shipment->forceFill($update)->save();

        // 6) Email on change
        $subject = in_array($new, ['Pending','Being Return'], true)
            ? 'Delivery Failed - Zebtan Collection'
            : 'Delivery Status Changed';

        $cityName = $cityById[$shipment->destination_city] ?? 'Unknown City';

        Mail::to('habbas21219@gmail.com')->queue(new ShipmentStatusChanged(
            $shipment->order_id,
            $new,
            $shipment->consignee_name,
            (int) $shipment->cod_amount,
            $shipment->tracking_number,
            $shipment->picking_time,
            $cityName,
            $subject
        ));
    }
})->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
