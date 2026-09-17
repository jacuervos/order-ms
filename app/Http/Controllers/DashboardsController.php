<?php

namespace App\Http\Controllers;

use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\State;
use App\Services\AuthMicroserviceService;

class DashboardsController extends Controller
{

    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
    }


    public function allOrders()
    {
        $orders = Order::count();

        $stateIds = State::whereIn('name', [
            State::ORDER_CREATION,
            State::ASSIGNED_TO_COLLECTOR,
            State::SERVICE_START,
            State::RESCHEDULED,
        ])->pluck('id');

        $stateOrders = Order::whereIn('state_id', $stateIds)->count();

        return response()->json([
            'orders' => $orders,
            'stateOrders' => $stateOrders,
        ]);
    }

    public function listTotalUsers()
    {
        $token = request()->bearerToken();

        $topUsers = Order::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        $userIds = $topUsers->pluck('user_id')->toArray();

        $userData = $this->authService->getUserByName(
            $userIds,
            $token
        );

        $usersById = collect($userData)->keyBy('id');

        $result = $topUsers->map(function ($item) use ($usersById) {
            $user = $usersById->get($item->user_id);

            return [
                'user_id' => $item->user_id,
                'name' => $user
                    ? $user['name']
                    : 'Usuario no encontrado',
                'total_orders' => $item->total_orders,
            ];
        });

        return response()->json([
            'users' => $result,
        ]);
    }

    public function listDateUsers()
    {
        $token = request()->bearerToken();

        $lastOrders = Order::query()
            ->select('user_id')
            ->selectRaw('MAX(date) as last_order_date')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('last_order_date')
            ->limit(10)
            ->get();

        $userIds = $lastOrders->pluck('user_id')->toArray();

        $userData = $this->authService->getUserByName(
            $userIds,
            $token
        );

        $usersById = collect($userData)->keyBy('id');

        $result = $lastOrders->map(function ($item) use ($usersById) {
            $user = $usersById->get($item->user_id);

            return [
                'user_id' => $item->user_id,
                'name' => $user
                    ? $user['name']
                    : 'Usuario no encontrado',
                'date' => $item->last_order_date,
            ];
        });

        return response()->json([
            'users' => $result,
        ]);
    }

    public function listTopCollectors()
    {
        $token = request()->bearerToken();

        $topCollectors = CollectorOrder::query()
            ->select('collector_id')
            ->selectRaw('COUNT(order_id) as total_orders')
            ->whereNotNull('collector_id')
            ->groupBy('collector_id')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get();

        $collectorIds = $topCollectors
            ->pluck('collector_id')
            ->toArray();


        $userIds = $this->authService->getIdUserByCollector(
            $collectorIds,
            $token
        );

        $userData = $this->authService->getUserByName(
            $userIds,
            $token
        );

        $collectorsById = collect($userData)->keyBy('id');

        $result = $topCollectors->map(function ($item) use ($collectorsById) {
            $collector = $collectorsById->get($item->collector_id);

            return [
                'collector_id' => $item->collector_id,
                'name' => $collector
                    ? $collector['name']
                    : 'Recolector no encontrado',
                'total_orders' => $item->total_orders,
            ];
        });

        return response()->json([
            'collectors' => $result,
        ]);
    }
}
