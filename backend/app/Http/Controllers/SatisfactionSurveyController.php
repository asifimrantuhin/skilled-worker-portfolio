<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SatisfactionSurveyController extends Controller
{
    /**
     * Get all surveys (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = SatisfactionSurvey::with(['user', 'surveyable']);

        // Filter by status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'completed':
                    $query->completed();
                    break;
                case 'pending':
                    $query->pending();
                    break;
                case 'expired':
                    $query->expired();
                    break;
            }
        }

        // Filter by rating
        if ($request->has('rating')) {
            $query->byRating($request->rating);
        }

        // Filter by satisfaction level
        if ($request->has('satisfaction')) {
            switch ($request->satisfaction) {
                case 'positive':
                    $query->positive();
                    break;
                case 'neutral':
                    $query->neutral();
                    break;
                case 'negative':
                    $query->negative();
                    break;
            }
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $surveys = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($surveys);
    }

    /**
     * Get survey by token (public endpoint for customers)
     */
    public function showByToken(string $token): JsonResponse
    {
        $survey = SatisfactionSurvey::findByToken($token);

        if (!$survey) {
            return response()->json(['error' => 'Survey not found'], 404);
        }

        if ($survey->isExpired()) {
            return response()->json(['error' => 'This survey has expired'], 410);
        }

        if ($survey->isCompleted()) {
            return response()->json([
                'message' => 'Survey already completed',
                'survey' => [
                    'rating' => $survey->rating,
                    'completed_at' => $survey->completed_at,
                ],
            ]);
        }

        return response()->json([
            'survey' => [
                'survey_token' => $survey->survey_token,
                'expires_at' => $survey->expires_at,
                'surveyable_type' => class_basename($survey->surveyable_type),
            ],
        ]);
    }

    /**
     * Submit survey response (public endpoint)
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $survey = SatisfactionSurvey::findByToken($token);

        if (!$survey) {
            return response()->json(['error' => 'Survey not found'], 404);
        }

        if ($survey->isExpired()) {
            return response()->json(['error' => 'This survey has expired'], 410);
        }

        if ($survey->isCompleted()) {
            return response()->json(['error' => 'Survey already completed'], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:2000',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $survey->complete(
            $request->rating,
            $request->feedback,
            $request->categories
        );

        return response()->json([
            'message' => 'Thank you for your feedback!',
            'survey' => $survey,
        ]);
    }

    /**
     * Get a specific survey
     */
    public function show(SatisfactionSurvey $satisfactionSurvey): JsonResponse
    {
        $satisfactionSurvey->load(['user', 'surveyable']);

        return response()->json(['survey' => $satisfactionSurvey]);
    }

    /**
     * Send survey for a ticket
     */
    public function sendForTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|integer|exists:tickets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::find($request->ticket_id);

        // Check if survey already exists
        $existingSurvey = SatisfactionSurvey::forModel($ticket)->pending()->first();
        if ($existingSurvey) {
            return response()->json([
                'message' => 'Survey already sent',
                'survey' => $existingSurvey,
            ]);
        }

        $survey = SatisfactionSurvey::createForTicket($ticket);

        // Here you would typically send an email with the survey link
        // Mail::to($ticket->user)->send(new SatisfactionSurveyMail($survey));

        return response()->json([
            'message' => 'Survey sent successfully',
            'survey' => $survey,
            'survey_url' => url("/survey/{$survey->survey_token}"),
        ], 201);
    }

    /**
     * Resend survey
     */
    public function resend(SatisfactionSurvey $satisfactionSurvey): JsonResponse
    {
        if ($satisfactionSurvey->isCompleted()) {
            return response()->json(['error' => 'Cannot resend completed survey'], 400);
        }

        // Reset expiry
        $satisfactionSurvey->update([
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        // Here you would resend the email
        // Mail::to($satisfactionSurvey->user)->send(new SatisfactionSurveyMail($satisfactionSurvey));

        return response()->json([
            'message' => 'Survey resent successfully',
            'survey' => $satisfactionSurvey,
        ]);
    }

    /**
     * Get survey statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $surveyableType = $request->get('type', Ticket::class);

        $stats = [
            'average_rating' => SatisfactionSurvey::averageRating($surveyableType),
            'satisfaction_rate' => SatisfactionSurvey::satisfactionRate($surveyableType),
            'response_rate' => SatisfactionSurvey::responseRate($surveyableType),
            'rating_distribution' => SatisfactionSurvey::ratingDistribution($surveyableType),
            'total_surveys' => SatisfactionSurvey::where('surveyable_type', $surveyableType)->count(),
            'completed_surveys' => SatisfactionSurvey::where('surveyable_type', $surveyableType)->completed()->count(),
            'pending_surveys' => SatisfactionSurvey::where('surveyable_type', $surveyableType)->pending()->count(),
        ];

        // Recent feedback
        $stats['recent_feedback'] = SatisfactionSurvey::where('surveyable_type', $surveyableType)
            ->completed()
            ->whereNotNull('feedback')
            ->orderBy('completed_at', 'desc')
            ->take(10)
            ->get(['rating', 'feedback', 'completed_at']);

        // Trend over time (last 30 days)
        $stats['daily_trend'] = SatisfactionSurvey::where('surveyable_type', $surveyableType)
            ->completed()
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(completed_at) as date, AVG(rating) as avg_rating, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['statistics' => $stats]);
    }

    /**
     * Get feedback categories summary
     */
    public function categorySummary(): JsonResponse
    {
        $surveys = SatisfactionSurvey::completed()
            ->whereNotNull('categories')
            ->get(['categories', 'rating']);

        $categoryCounts = [];
        $categoryRatings = [];

        foreach ($surveys as $survey) {
            if (!$survey->categories) continue;
            
            foreach ($survey->categories as $category) {
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
                $categoryRatings[$category][] = $survey->rating;
            }
        }

        $summary = [];
        foreach ($categoryCounts as $category => $count) {
            $summary[$category] = [
                'count' => $count,
                'average_rating' => round(array_sum($categoryRatings[$category]) / count($categoryRatings[$category]), 2),
            ];
        }

        arsort($summary);

        return response()->json(['categories' => $summary]);
    }
}
