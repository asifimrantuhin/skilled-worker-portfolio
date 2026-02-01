<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CannedResponseController extends Controller
{
    /**
     * Get all canned responses
     */
    public function index(Request $request): JsonResponse
    {
        $query = CannedResponse::query();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by active status
        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Search by title or content
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('shortcut', 'like', "%{$search}%");
            });
        }

        $cannedResponses = $query->ordered()->paginate($request->get('per_page', 20));

        return response()->json($cannedResponses);
    }

    /**
     * Get available categories
     */
    public function categories(): JsonResponse
    {
        $categories = CannedResponse::getCategories();

        return response()->json(['categories' => $categories]);
    }

    /**
     * Create a new canned response
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcut' => 'nullable|string|max:50|unique:canned_responses,shortcut',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cannedResponse = CannedResponse::create([
            ...$validator->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Canned response created successfully',
            'canned_response' => $cannedResponse,
        ], 201);
    }

    /**
     * Get a specific canned response
     */
    public function show(CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->load('creator');

        return response()->json(['canned_response' => $cannedResponse]);
    }

    /**
     * Update a canned response
     */
    public function update(Request $request, CannedResponse $cannedResponse): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'shortcut' => 'nullable|string|max:50|unique:canned_responses,shortcut,' . $cannedResponse->id,
            'content' => 'string',
            'category' => 'nullable|string|max:100',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cannedResponse->update($validator->validated());

        return response()->json([
            'message' => 'Canned response updated successfully',
            'canned_response' => $cannedResponse,
        ]);
    }

    /**
     * Delete a canned response
     */
    public function destroy(CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->delete();

        return response()->json(['message' => 'Canned response deleted successfully']);
    }

    /**
     * Find by shortcut and render with variables
     */
    public function findByShortcut(Request $request, string $shortcut): JsonResponse
    {
        $cannedResponse = CannedResponse::findByShortcut($shortcut);

        if (!$cannedResponse) {
            return response()->json(['error' => 'Canned response not found'], 404);
        }

        // Increment usage count
        $cannedResponse->incrementUsage();

        // Render with provided variables
        $variables = $request->get('variables', []);
        $renderedContent = $cannedResponse->render($variables);

        return response()->json([
            'canned_response' => $cannedResponse,
            'rendered_content' => $renderedContent,
        ]);
    }

    /**
     * Preview a canned response with variables
     */
    public function preview(Request $request, CannedResponse $cannedResponse): JsonResponse
    {
        $variables = $request->get('variables', []);
        $renderedContent = $cannedResponse->render($variables);

        return response()->json([
            'original_content' => $cannedResponse->content,
            'rendered_content' => $renderedContent,
            'available_variables' => $cannedResponse->variables,
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->update(['is_active' => !$cannedResponse->is_active]);

        return response()->json([
            'message' => $cannedResponse->is_active ? 'Canned response activated' : 'Canned response deactivated',
            'canned_response' => $cannedResponse,
        ]);
    }

    /**
     * Get usage statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => CannedResponse::count(),
            'active' => CannedResponse::active()->count(),
            'by_category' => CannedResponse::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
            'most_used' => CannedResponse::orderBy('usage_count', 'desc')
                ->take(10)
                ->get(['id', 'title', 'shortcut', 'usage_count']),
        ];

        return response()->json(['statistics' => $stats]);
    }
}
