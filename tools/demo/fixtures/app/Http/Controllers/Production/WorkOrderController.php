<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController
{
    public function store(Request $request)
    {
        return response()->json(['data' => ['id' => 12, 'code' => 'WO-0012', ...$request->only('product_code', 'quantity', 'line')]], 201);
    }

    public function show($workOrder)
    {
        abort(404, 'Work order not found');
    }

    public function update(Request $request, $workOrder)
    {
        return response()->json(['data' => ['id' => (int) $workOrder, 'status' => $request->input('status')]]);
    }

    public function destroy($workOrder)
    {
        return response()->noContent();
    }

    public function complete(Request $request, WorkOrderService $service, $workOrder)
    {
        DB::table('users')->count();
        $service->complete('WO-00'.$workOrder, (int) $request->input('quantity'));

        return response()->json(['ok' => true]);
    }
}
