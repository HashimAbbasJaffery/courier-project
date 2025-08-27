<?php

namespace App\Http\Controllers;

use App\Http\Resources\MaterialResource;
use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function get() {
        $materials = Material::query()->get();

        return MaterialResource::collection($materials);
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $material = Material::create([
            'name' => $request->name,
            'price' => $request->price,
        ]);

        return new MaterialResource($material);
    }

    public function destroy(Material $material) {
        $material->delete();
        return response()->json(['success' => true, 'message' => 'Material deleted successfully']);
    }

    public function update(Request $request, Material $material) {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $material->update([
            'name' => $request->name,
            'price' => $request->price,
        ]);

        return new MaterialResource($material);
    }
}
