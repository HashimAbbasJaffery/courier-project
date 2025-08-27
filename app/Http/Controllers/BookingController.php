<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function create() {
        return Inertia::render("Bookings/Create");
    }

    public function index() {
        return Inertia::render("Bookings/Index");
    }

    public function show(Request $request, $tracking_no) {
        // Fetch the booking details using the tracking number
        // This is a placeholder; you would typically fetch from a model or service

        return Inertia::render("Bookings/Show");
    }
}
