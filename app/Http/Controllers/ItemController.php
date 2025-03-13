<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Item;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        // Query to get paginated items with filters
        $itemsQuery = QueryBuilder::for(Item::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('item_id'),
                AllowedFilter::partial('type'),
                AllowedFilter::exact('shop_id'),
            ])
            ->with(['shop'])
            ->orderBy('item_id')
            ->paginate($perPage, ['*'], 'page', $page);

        // Log the generated SQL query for debugging
        // Log::info('Generated Query: ' . $itemsQuery->toSql());

        // Return paginated items as JSON response
        return response()->json($itemsQuery);
    }


    public function getItems(Request $request)
    {
        $query = Item::query();

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        // Apply item_id filter only if it's present and not null
        if ($request->filled('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $limit = $request->input('limit', 20); // Default to 10 if limit is not provided

        $items = $query->orderBy('name')->limit($limit)->get();

        return response()->json($items);
    }

    public function getAllItems(Request $request)
    {
        // Query to get paginated items with filters
        $itemsQuery = QueryBuilder::for(Item::class)
            ->allowedFilters([
                AllowedFilter::exact('name'),
                AllowedFilter::exact('item_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('shop_id'),
            ])
            ->with(['shop'])
            ->get();

        return response()->json($itemsQuery);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => ['required', 'integer'],
                'name' => 'required|string',
                'type' => 'required|string',
                'num_of_items' => 'required|numeric',
                'selling_price_per_unit' => 'required|numeric',
                'actual_price_per_unit' => 'required|numeric',
                'shop_id' => 'required|integer|exists:shops,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $item = Item::create($request->all());

            return response()->json($item, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $shop = Item::find($id);
        if ($shop) {
            return response()->json($shop, 200);
        }
        return response()->json(['error' => 'Item not found'], 404);
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
        $validator = Validator::make($request->all(), [
            'item_id' => ['required'],
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'num_of_items' => 'required|numeric',
            'selling_price_per_unit' => 'required|numeric',
            'actual_price_per_unit' => 'required|numeric',
            'shop_id' => 'required|integer|exists:shops,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Find the item by ID
        $item = Item::find($id);

        if ($item) {
            // Update the item
            $item->update($request->all());
            return response()->json($item, 200);
        }

        // Item not found
        return response()->json(['error' => 'Item not found'], 404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $shop = Item::find($id);
        if ($shop) {
            $shop->delete();
            return response()->json(null, 204);
        }
        return response()->json(['error' => 'Item not found'], 404);
    }
}
