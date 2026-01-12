<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserGoalController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'goal' => ['required', 'string'],
            'starts_at' => ['nullable', 'date']
        ]);
        $user = User::query()->first();

        return response()->json($user->goals()->create([
            'goal' => $request->goal,
            'starts_at' => $request->starts_at ?? null,
        ]));
    }

    public function index()
    {
        $user = User::query()->first();

        return response()->json($user->activeGoal());
    }
}
