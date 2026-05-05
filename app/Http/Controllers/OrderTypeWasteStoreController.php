<?php

namespace App\Http\Controllers;

use App\Models\OrderTypeWaste;
use App\Http\Requests\OrderTypeWasteStoreRequest;

class OrderTypeWasteStoreController extends Controller
{
    public function __invoke(OrderTypeWasteStoreRequest $request)
    {
        $orderTypeWaste = OrderTypeWaste::create($request->validated());
        return response()->json($orderTypeWaste, 201);
    }
}
