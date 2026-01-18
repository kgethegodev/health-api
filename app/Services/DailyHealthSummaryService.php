<?php

namespace App\Services;

use App\Models\DailyHealthSummary;
use App\Models\User;
use Carbon\Carbon;

class DailyHealthSummaryService
{
    /**
     * @param Carbon $date
     * @param float|null $weight_kg
     * @param float|null $body_fat_percent
     * @param float|null $sleep_hours
     * @return DailyHealthSummary
     */
    public static function create(int $user_id, Carbon $date, float|null $weight_kg, float|null $body_fat_percent, float|null $sleep_hours): DailyHealthSummary
    {
        return DailyHealthSummary::query()->updateOrCreate([
            'user_id' => $user_id,
            'date' => $date,
        ], [
            'weight_kg' => $weight_kg,
            'body_fat_percent' => $body_fat_percent,
            'sleep_hours' => $sleep_hours > 0 ? $sleep_hours : null,
        ]);
    }

    /**
     * @return array|string[]
     */
    public static function generateWeeklySummary(int $user_id): array
    {
        $user = User::query()->findOrFail($user_id);

        $end = Carbon::today();
        $start = $end->copy()->subDays(6);

        $days = DailyHealthSummary::query()
            ->where('user_id', $user_id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        if ($days->count() < 3) {
            return [
                'status' => 'insufficient_data',
                'message' => 'Not enough data yet',
                'confidence' => 0.0,
            ];
        }

        $goalThresholds = [
            'fat_loss' => [
                'improving' => -0.3,
                'regressing' => 0.2,
            ],
            'maintenance' => [
                'improving' => -0.2,
                'regressing' => 0.2,
            ],
            'muscle_gain' => [
                'improving' => 0.2,
                'regressing' => -0.2,
            ],
        ];

        $goalRecord = $user->goals()->latest()->first();
        $activeGoal = $goalRecord?->goal ?? 'maintenance';
        $startingWeight = $goalRecord?->weight;
        $startingBodyFat = $goalRecord?->body_fat_percent;

        /* -------------------------
           Weight trend (vs starting weight)
        ------------------------- */

        $currentWeight = $days->whereNotNull('weight_kg')->last()?->weight_kg;

        $weightChange = ($startingWeight && $currentWeight)
            ? round($currentWeight - $startingWeight, 2)
            : null;

        /* -------------------------
           Data quality
        ------------------------- */

        $daysWithWeight = $days->whereNotNull('weight_kg')->count();
        $daysWithSleep = $days->whereNotNull('sleep_hours')->count();

        $dataQuality = ($daysWithWeight < 4 || $daysWithSleep < 4)
            ? 'low'
            : 'good';

        /* -------------------------
           Body fat trend (vs starting body fat)
        ------------------------- */

        $currentBodyFat = $days->whereNotNull('body_fat_percent')->last()?->body_fat_percent;

        $bodyFatChange = ($startingBodyFat && $currentBodyFat)
            ? round($currentBodyFat - $startingBodyFat, 2)
            : null;

        /* -------------------------
           Sleep analysis
        ------------------------- */

        $avgSleep = round(
            $days->whereNotNull('sleep_hours')->avg('sleep_hours'),
            2
        );

        $sleepStdDev = round(
            sqrt(
                $days->whereNotNull('sleep_hours')
                    ->map(fn($d) => pow($d->sleep_hours - $avgSleep, 2))
                    ->avg()
            ),
            2
        );

        $sleepConsistency = $sleepStdDev < 0.75
            ? 'consistent'
            : 'inconsistent';

        /* -------------------------
           Status logic (goal-aware)
        ------------------------- */

        $threshold = $goalThresholds[$activeGoal] ?? $goalThresholds['maintenance'];

        $status = 'stable';

        if ($weightChange !== null) {
            if ($weightChange <= $threshold['improving']) {
                $status = 'improving';
            } elseif ($weightChange >= $threshold['regressing']) {
                $status = 'regressing';
            }
        }

        /* -------------------------
           Confidence calculation
        ------------------------- */

        // 1. Data coverage (0–1)
        $dataCoverageScore = (
                min($daysWithWeight, 7) +
                min($daysWithSleep, 7)
            ) / 14;

        // 2. Signal agreement (weight vs body fat)
        $signalAgreementScore = 1.0;

        if ($weightChange !== null && $bodyFatChange !== null) {
            $signalAgreementScore =
                ($weightChange < 0 && $bodyFatChange < 0) ||
                ($weightChange > 0 && $bodyFatChange > 0)
                    ? 1.0
                    : 0.6;
        }

        // 3. Sleep stability (noise proxy)
        $sleepStabilityScore = match (true) {
            $sleepStdDev < 0.75 => 1.0,
            $sleepStdDev < 1.25 => 0.8,
            default => 0.6,
        };

        // Final confidence (weighted)
        $confidence = round(
            ($dataCoverageScore * 0.5) +
            ($signalAgreementScore * 0.3) +
            ($sleepStabilityScore * 0.2),
            2
        );

        /* -------------------------
           Recommendation logic
        ------------------------- */

        if ($activeGoal === 'fat_loss' && $status === 'stable') {
            $recommendation = 'Progress is steady. Focus on consistency rather than tightening further.';
        } elseif ($activeGoal === 'muscle_gain' && $status === 'stable') {
            $recommendation = 'Ensure recovery and nutrition support training adaptations.';
        } elseif ($sleepConsistency === 'inconsistent') {
            $recommendation = 'Focus on consistent sleep timing to support recovery.';
        } elseif ($status === 'improving') {
            $recommendation = 'Progress looks good. Maintain current habits.';
        } elseif ($status === 'regressing') {
            $recommendation = 'Review calorie intake and daily movement this week.';
        } else {
            $recommendation = 'Stay consistent and monitor trends next week.';
        }

        /* -------------------------
           Final summary
        ------------------------- */

        $summary = [
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'weight_change_kg' => $weightChange,
            'body_fat_change_percent' => $bodyFatChange,
            'average_sleep_hours' => $avgSleep,
            'sleep_consistency' => $sleepConsistency,
            'data_quality' => $dataQuality,
            'confidence' => $confidence,
            'status' => $status,
            'recommendation' => $recommendation,
        ];

        $aiNarrative = AiWeeklyNarrativeService::generate($summary, $activeGoal);

        return [
            ...$summary,
            'ai_narrative' => $aiNarrative,
        ];
    }
}
