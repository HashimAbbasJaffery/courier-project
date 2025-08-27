<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlatformResource;
use App\Models\Platform;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function get() {
        $platforms = Platform::all();

        return PlatformResource::collection($platforms);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            "platform" => [ "required" ]
        ]);

        $platform = Platform::create([
            "name" => $request->platform
        ]);

        return response()->json(["message" => "Platform has been created!"], 201);
    }
    public function update(Request $request, Platform $platform) {
        $validated = $request->validate([
            "platform" => [ "required" ]
        ]);

        $platform = $platform->update([
            "name" => $request->platform
        ]);


        return response()->json(["message" => "Platform has been updated!"]);
    }

    public function destroy(Request $request, Platform $platform) {
        if(!$platform->exists()) {
            throw new ModelNotFoundException("Platform not found!");
        }
        $platform->delete();
        return response()->json(["message"=> "Platform has been deleted!"]);
    }
}
