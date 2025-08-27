<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ShipmentController;
use App\Models\Platform;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get("/cities/{courier:courier_service}", [CourierController::class, "getCities"])->name("courier.cities");
Route::post("/order/{courier:courier_service}/create", [CourierController::class, "createOrder"])->name("courier.createOrder");
Route::post("/order/{courier:courier_service}/cancel", [CourierController::class, "cancelOrder"])->name("order.cancel");
Route::get("/order/{courier:courier_service}/track", [CourierController::class, "track"])->name("order.track");

Route::get("/platforms", [PlatformController::class, "get"])->name("platforms.get");
Route::post("platform/create", [PlatformController::class, "store"])->name("platform.create");
Route::post("platform/{platform}/update", [PlatformController::class, "update"])->name("platform.update");
Route::post("platform/{platform}/delete", [PlatformController::class, "destroy"])->name("platform.delete");

Route::get("/materials", [\App\Http\Controllers\MaterialController::class, "get"])->name("materials.get");
Route::post("/material/create", [\App\Http\Controllers\MaterialController::class, "store"])->name("material.add");
Route::post("/material/{material}/delete", [\App\Http\Controllers\MaterialController::class, "destroy"])->name("material.delete");
Route::post("/material/{material}/update", [\App\Http\Controllers\MaterialController::class, "update"])->name("material.update");



Route::get("/vendors", [\App\Http\Controllers\VendorController::class, "get"])->name("vendors.get");
Route::post("/vendor/create", [\App\Http\Controllers\VendorController::class, "store"])->name("vendor.create");
Route::post("/vendor/{vendor}/update", [\App\Http\Controllers\VendorController::class, "update"])->name("vendor.update");
Route::post("/vendor/{vendor}/delete", [\App\Http\Controllers\VendorController::class, "destroy"])->name("vendor.delete");

// Get order items for a specific vendor
Route::get("/vendors/{vendor}/order-items", [\App\Http\Controllers\VendorController::class, "getOrderItems"])->name("vendors.order-items.get");

// Save payment changes for a vendor
Route::post("/vendors/{vendor}/save-payments", [\App\Http\Controllers\VendorController::class, "savePayments"])->name("vendors.save-payments");

Route::get("shipments", [ShipmentController::class, "index"])->name("shipments.index");
Route::get("shipments/search", [ShipmentController::class, "search"])->name("shipments.search");
Route::post("shipments/payment/update", [ShipmentController::class, "updatePayment"])->name("shipments.payment.update");
Route::get("shipments/track/{trackingNumber}", [ShipmentController::class, "track"])->name("shipments.track");
Route::get("shipments/{shipmentId}/vendors", [\App\Http\Controllers\Api\ShipmentVendorController::class, "getVendors"])->name("shipments.vendors");

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('shipper-advices', [CourierController::class, 'shipperAdvices']);
Route::get('shipper-advices/direct', [CourierController::class, 'shipperAdvicesDirect']);
Route::get('shipper-advices/{cn}/activity', [CourierController::class, 'activity']);


// Webhook endpoint for courier service to update shipment status
// No CSRF protection needed as this is called by external service
Route::post("/shipment/status/update", [ShipmentController::class, "updateStatus"])
    ->name("shipment.status.update")
    ->withoutMiddleware(['web']); // Explicitly exclude web middleware (which includes CSRF)

Route::get("/shipment/{shipment}/activity", [ActivityController::class, "get"])->name("activity.get");