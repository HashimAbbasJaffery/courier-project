<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AdviceController extends Controller
{
    public function get() {
        return Inertia::render("Advices/Index");
    }
}
