<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Http\Requests\ShopRequests;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $shops = QueryBuilder::for(Shop::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('branch'),
            ])
            ->with(['users'])
            ->orderBy('name')
            ->get();

        return response()->json($shops);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
       
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $shop = Shop::create($request->all());

        return response()->json($shop, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $shop = Shop::find($id);
        if ($shop) {
            return response()->json($shop, 200);
        }
        return response()->json(['error' => 'Shop not found'], 404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $shop = Shop::find($id);
        if ($shop) {
            $shop->update($request->all());
            return response()->json($shop, 200);
        }
        return response()->json(['error' => 'Shop not found'], 404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $shop = Shop::find($id);
        if ($shop) {
            $shop->delete();
            return response()->json(null, 204);
        }
        return response()->json(['error' => 'Shop not found'], 404);
    }
}
