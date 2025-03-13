<?php

namespace App\Http\Controllers;

use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Order;
use App\Models\Item;
use App\Models\OrderDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Define custom filters
        $customDateFilter = AllowedFilter::callback('date', function ($query, $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if (is_string($value)) {
                Log::info('Date filter value: ' . $value);
                $dates = explode(',', $value);
                if (count($dates) == 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                    Log::info('Start Date: ' . $startDate->toDateTimeString() . ', End Date: ' . $endDate->toDateTimeString());
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } else {
                    Log::warning('Invalid date range format: ' . $value);
                }
            } else {
                Log::warning('Date filter is not a string: ' . json_encode($value));
            }
        });

        $customWeekFilter = AllowedFilter::callback('week', function ($query, $value) {
            if (is_string($value)) {
                $date = Carbon::createFromFormat('Y-m-d', $value);
                $startOfWeek = $date->copy()->startOfWeek()->startOfDay();
                $endOfWeek = $date->copy()->endOfWeek()->endOfDay();
                Log::info('Week filter value: ' . $value);
                Log::info('Start of Week: ' . $startOfWeek->toDateTimeString() . ', End of Week: ' . $endOfWeek->toDateTimeString());
                $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
            } else {
                Log::warning('Week filter is not a string: ' . json_encode($value));
            }
        });

        $customMonthFilter = AllowedFilter::callback('month', function ($query, $value) {
            if (is_string($value)) {
                $date = Carbon::createFromFormat('Y-m', $value);
                $startOfMonth = $date->copy()->startOfMonth()->startOfDay();
                $endOfMonth = $date->copy()->endOfMonth()->endOfDay();
                Log::info('Month filter value: ' . $value);
                Log::info('Start of Month: ' . $startOfMonth->toDateTimeString() . ', End of Month: ' . $endOfMonth->toDateTimeString());
                $query->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            } else {
                Log::warning('Month filter is not a string: ' . json_encode($value));
            }
        });

        // Pagination parameters
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        // Query to get orders with filters and pagination
        $ordersQuery = QueryBuilder::for(Order::class)
            ->allowedFilters([
                AllowedFilter::exact('cashier_id'),
                AllowedFilter::exact('shop_id'),
                $customDateFilter,
                $customWeekFilter,
                $customMonthFilter,
            ])
            ->with(['user', 'shop', 'orderDetails' => function ($query) {
                $query->with(['item']);
            }])
            ->orderBy('id');

        // Paginate the results
        $orders = $ordersQuery->paginate($perPage, ['*'], 'page', $page);

        // Log the raw SQL query for debugging
        // Log::info('Generated Query: ' . $ordersQuery->toSql());

        return response()->json($orders);
    }

    public function getAllOrders(Request $request)
    {
        $orders = QueryBuilder::for(Order::class)
            ->allowedFilters([
                AllowedFilter::partial('casier_id'),
                AllowedFilter::partial('shop_id'),
            ])
            ->with(['shop'])
            ->get();
        return response()->json($orders);
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
        $validator = Validator::make($request->all(), [
            'casier_id' => 'required|exists:users,id',
            'shop_id' => 'required|exists:shops,id',
            'total_selling_price' => 'required|numeric|min:0',
            'total_actual_price' => 'required|numeric|min:0',
            'order_details' => 'required|array|min:1',
            'order_details.*.item_id' => 'required|exists:items,id',
            'order_details.*.type' => 'required|string|in:QTY,G,Kalang,L,ML',
            'order_details.*.neededAmount' => 'required|numeric|min:0',
            'order_details.*.num_of_items' => 'required|numeric|min:1',
            'order_details.*.total_price_per_units' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the order
            $order = Order::create($request->only(['casier_id', 'shop_id', 'total_selling_price', 'total_actual_price']));

            // Create the order details and update stock
            $orderDetails = $request->input('order_details');
            foreach ($orderDetails as $detail) {
                // Update the stock in the items table
                $item = Item::find($detail['item_id']);
                if ($item) {
                    $item->num_of_items -= $detail['neededAmount'];
                    if ($item->num_of_items < 0) {
                        throw new \Exception('Insufficient stock for item ID: ' . $detail['item_id']);
                    }
                    $item->save();
                } else {
                    throw new \Exception('Item not found with ID: ' . $detail['item_id']);
                }

                // Create the order detail record
                $detail['order_id'] = $order->id;
                OrderDetails::create($detail);
            }

            DB::commit();

            return response()->json($order->load('orderDetails.item'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, Order $order)
    {
        DB::beginTransaction();

        try {
            // Update the order
            $order->update($request->only(['casier_id', 'shop_id', 'total_price']));

            // Update or create order details
            $orderDetails = $request->input('order_details');
            foreach ($orderDetails as $detail) {
                OrderDetails::updateOrCreate(
                    ['id' => $detail['id'] ?? null],
                    [
                        'order_id' => $order->id,
                        'item_id' => $detail['item_id'],
                        'type' => $detail['type'],
                        'num_of_items' => $detail['num_of_items'],
                        'total_price_per_units' => $detail['total_price_per_units']
                    ]
                );
            }

            DB::commit();

            return response()->json($order->load('orderDetails.item'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        DB::beginTransaction();

        try {
            // Delete order details
            $order->orderDetails()->delete();

            // Delete order
            $order->delete();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete order: ' . $e->getMessage()], 500);
        }
    }
}
