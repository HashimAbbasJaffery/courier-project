<?php

use App\Classes\CourierServices\LeopardCourier;
use App\Http\Controllers\AdviceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\MergeSlipsController;
use App\Mail\ShipmentStatusChanged;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Shipment;
use App\TwoUpSlipSheet;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Jobs\ImportLeopardCsvJob;
use App\Models\Vendor;


Route::get("/cities", function() {
    $courier = resolve("leopard")->getCities();
    dd($courier);
});


Route::get("import", function() {
    set_time_limit(0);                    // disable PHP max execution time for this request
ini_set('max_execution_time', '0');   // belt & suspenders
ignore_user_abort(true);
     $path = storage_path('app/up_to_date.csv');

    if (!is_readable($path)) {
        return 'CSV not readable';
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return 'Could not open file';
    }

    // --- detect delimiter ---
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        return 'CSV empty';
    }
    $delims = [",", ";", "\t", "|"];
    $bestDelim = ",";
    $maxParts = 0;
    foreach ($delims as $d) {
        $parts = str_getcsv($firstLine, $d);
        if (count($parts) > $maxParts) {
            $maxParts = count($parts);
            $bestDelim = $d;
        }
    }
    rewind($handle);

    // header
    $header = fgetcsv($handle, 0, $bestDelim, '"', "\\");
    if (!$header) {
        return 'Header missing/invalid';
    }
    $header = array_map(function ($h) {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h); // remove BOM
        return trim($h);
    }, $header);

    $colCount = count($header);

    // find CN column
    $cnHeaderCandidates = ['CN #', 'CN', 'cn_number', 'tracking_number'];
    $cnKey = null;
    foreach ($cnHeaderCandidates as $cand) {
        if (in_array($cand, $header, true)) {
            $cnKey = $cand;
            break;
        }
    }
    if (!$cnKey) {
        return 'No CN column found';
    }

    $rowNum = 1;
    $imported = 0;
    $skipped = 0;

    while (($row = fgetcsv($handle, 0, $bestDelim, '"', "\\")) !== false) {
        $rowNum++;

        // normalize row length
        $rc = count($row);
        if ($rc < $colCount) {
            $row = array_pad($row, $colCount, null);
        } elseif ($rc > $colCount) {
            $row = array_slice($row, 0, $colCount);
        }

        $assoc = @array_combine($header, $row);
        if ($assoc === false) {
            $skipped++;
            continue;
        }

        $cn = trim((string)($assoc[$cnKey] ?? ''));
        if ($cn === '') {
            $skipped++;
            continue;
        }

        try {
            $resp    = resolve('leopard')->track($cn);
            $packets = is_array($resp['packet_list'] ?? null) ? $resp['packet_list'] : [];

            if (!$packets) {
                $skipped++;
                continue;
            }

            foreach ($packets as $packet) {
                $trackingDetail = is_array($packet['Tracking Detail'] ?? null) ? $packet['Tracking Detail'] : [];
                $firstActivity  = $trackingDetail[0]['Activity_Date'] ?? null;
                $lastActivity   = $trackingDetail ? ($trackingDetail[count($trackingDetail)-1]['Activity_Date'] ?? null) : null;

                $payload = [
                    'consignee_name'       => $packet['consignment_name_eng']     ?? null,
                    'consignee_phone'      => $packet['consignment_phone']        ?? null,
                    'consignee_address'    => $packet['consignment_address']      ?? null,
                    'destination_city'     => $packet['destination_city_name']    ?? null,
                    'origin_city'          => $packet['origin_city_name']         ?? null,
                    'shipment_type'        => 'OVERNIGHT',
                    'product_description'  => 'I am the product description',
                    'weight'               => $packet['arival_dispatch_weight']   ?? null,
                    'no_of_pieces'         => 1,
                    'cod_amount'           => $packet['booked_packet_collect_amount'] ?? null,
                    'is_cancelled'         => ($packet['booked_packet_status'] ?? '') === 'Cancelled',
                    'tracking_number'      => $packet['track_number'] ?? $cn,
                    'status'               => $packet['booked_packet_status']    ?? null,
                    'special_instructions' => $packet['special_instructions']    ?? null,
                    'slip_link'            => null,
                    'division'             => 'EXPRESS',
                    'courier_id'           => 1,
                    'order_id'             => $packet['booked_packet_id']        ?? null,
                    'vendor_id'            => 1,
                    'picking_time'         => $firstActivity,
                    'advance_payment'      => 1,
                    'last_activity'        => $lastActivity,
                ];

                Shipment::updateOrCreate(
                    ['tracking_number' => $payload['tracking_number']],
                    $payload
                );
                $imported++;
            }

        } catch (\Throwable $e) {
            $skipped++;
            Log::error('Import failed', [
                'row' => $rowNum,
                'cn'  => $cn,
                'err' => $e->getMessage(),
            ]);
            continue;
        }
    }

    fclose($handle);

    return "Done. Imported: {$imported}, Skipped: {$skipped}";
});



Route::get("create-order", function() {
    $data = [
        "destination_city" => 789,
        "shipment_type" => "OVERNIGHT",
        "booked_packet_weight" => 1000,
        "booked_packet_no_piece" => 1,
        "booked_packet_collect_amount" => 500,
        "order_id" => 12,
        "consignee_name" => "Hashim Abbas",
        "consignee_phone" => 1231456,
        "consignee_address" => "Testing address",
        "special_instructions" => "Please handle it with care!",
    ];

    $courier = resolve("leopard")->createOrder($data);

    dd($courier);
});


Route::get("cancel-order", function() {
    $trackingNumber = "KI7507427633";

    $response = resolve("leopard")->track($trackingNumber);

    dd($response);
});




Route::get("track", function() {
    $trackingNumber = "KI7507439560";

    $courier = resolve("leopard")->track($trackingNumber);

    dd( $courier );
});

Route::get("test", function() {
    Mail::to("habbas21219@gmail.com")->send(new ShipmentStatusChanged(
        "1",
        "Pickup Request Sent",
        "Hashim Abbas",
        100,
        "3243264734",
        now(),
        "Karachi",
    ));
});

Route::get("charges", function() {
    $response = resolve("leopard")->getCharges("KI7505698225");


    dd($response["data"]);
});

Route::get("dummy", function(TwoUpSlipSheet $svc) {

        $url = $svc->buildForShipments(28, 29);

        return response()->json([
            'message' => '2-up slip created',
            'url'     => $url,
        ]);
});



Route::get('/slips/merged', MergeSlipsController::class)->name('slips.merged');

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get("booking/create", [BookingController::class, "create"])->name("booking.create");
Route::get("bookings", [BookingController::class, "index"])->name("booking.index");
Route::get("booking/{booking:tracking_no}", [BookingController::class, "show"])->name("booking.show");

Route::get("advices", [AdviceController::class, "get"])->name("shipment.advice");

Route::get("shipper-advice", function() { return Inertia::render("ShipperAdvice/Index"); })->name("shipper.advice");

Route::get("bookings/expanded", function() { return Inertia::render("Bookings/Expanded"); })->name("booking.expanded");

Route::get("test-shipper-advice", function() {
    try {
        $leopardCourier = app('leopard');
        $result = $leopardCourier->getShipperAdvice([
            'from_date' => date('Y-m-d', strtotime('-7 days')),
            'to_date' => date('Y-m-d')
        ]);
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get("get-amounts", function() {
    $vendor = Vendor::find(25);

    dd($vendor->toPay);
});

Route::get("debug-env", function() {
    return response()->json([
        'LEOPARD_API_KEY' => env('LEOPARD_API_KEY') ? 'SET' : 'NOT SET',
        'LEOPARD_API_PASSWORD' => env('LEOPARD_API_PASSWORD') ? 'SET' : 'NOT SET',
        'LEOPARD_ENV' => env('LEOPARD_ENV', 'NOT SET'),
        'endpoint' => env('LEOPARD_ENV') === 'staging'
            ? 'https://merchantapistaging.leopardscourier.com/api'
            : 'https://merchantapi.leopardscourier.com/api'
    ]);
});

Route::get("materials", function() {
    return Inertia::render("Materials/Index");
})->name("materials.index");

Route::get("platforms", function() {
    return Inertia::render("Platforms/Index");
})->name("platforms.index");



Route::get("vendors", function() {
    return Inertia::render("Vendors/Index");
})->name("vendors.index");

Route::get("vendors/{vendor}", function(\App\Models\Vendor $vendor) {
    return Inertia::render("Vendors/Show", ["vendor" => $vendor]);
})->name("vendors.show");

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
