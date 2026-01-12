<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiWeeklyNarrativeService
{
    public static function generate(array $summary, string $goal): ?string
    {
        $systemPrompt = <<<SYSTEM
You are a calm, supportive health reflection assistant.

You do NOT:
- calculate metrics
- contradict provided status
- give medical advice
- give step-by-step plans
- use urgency, guilt, or motivational language

You DO:
- explain patterns in simple language
- acknowledge uncertainty
- reduce anxiety
- focus on consistency, not optimisation

Your tone is:
- neutral
- reassuring
- observant
- non-judgemental

If data quality or confidence is low, say so calmly.
If progress is unclear, normalise it.
If progress exists, acknowledge it without celebration.
SYSTEM;

        $userPrompt = <<<USER
Here is a weekly health summary for a user.

Context:
- The goal is: {$goal}
- This summary is already calculated and correct
- You must not reinterpret or override it

Weekly summary:
- Status: {$summary['status']}
- Weight change (kg): {$summary['weight_change_kg']}
- Body fat change (%): {$summary['body_fat_change_percent']}
- Average sleep (hours): {$summary['average_sleep_hours']}
- Sleep consistency: {$summary['sleep_consistency']}
- Data quality: {$summary['data_quality']}
- Confidence score (0–1): {$summary['confidence']}

Task:
Write a short narrative (2–4 sentences) that:
1. Explains what likely happened this week
2. Acknowledges uncertainty if present
3. Highlights one thing that mattered most
4. Avoids advice phrasing ("you should", "try to")

Rules:
- Do not repeat raw numbers unless necessary
- Do not mention the word "AI"
- Do not sound motivational or instructional
- Do not exceed 80 words
- Be calm and factual
USER;

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
            ]);

        if (!$response->successful()) {
            return null;
        }

        return trim(
            $response->json('choices.0.message.content')
        );
    }
}
