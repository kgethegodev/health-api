<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyHealthSummary;
use App\Models\User;
use App\Services\DailyHealthSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyHealthSummaryController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'weight' => ['nullable', 'numeric'],
            'body_fat_percent' => ['nullable', 'numeric'],
            'sleep_hours' => ['nullable', 'numeric'],
        ]);

        info(json_encode($request->all(), JSON_PRETTY_PRINT));

        $user = User::firstOrCreate([
            'device_id' => $request->input('device_id'),
        ]);

        DailyHealthSummaryService::create(
            user_id: $user->id,
            date: Carbon::parse($request->input('date')),
            weight_kg: $request->input('weight'),
            body_fat_percent: $request->input('body_fat_percent'),
            sleep_hours: $request->input('sleep_hours')
        );

        return response()->json([
            'message' => 'Daily health summary created.',
        ]);
    }

    public function weeklySummary()
    {
        $user = User::query()->first();

        return response()->json(DailyHealthSummaryService::generateWeeklySummary(user_id: $user->id));
    }
}
