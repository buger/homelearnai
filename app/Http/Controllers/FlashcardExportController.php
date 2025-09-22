<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\Unit;
use App\Services\FlashcardExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FlashcardExportController extends Controller
{
    public function __construct(private FlashcardExportService $exportService) {}

    /**
     * Show export options modal
     */
    public function options(Request $request, int $unitId): View|Response
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            // Verify unit exists and user has access
            $unit = Unit::with(['subject'])->findOrFail($unitId);
            if ((int) $unit->subject->user_id !== auth()->id()) {
                return response('Access denied', 403);
            }

            // Get total flashcard count
            $totalCards = Flashcard::whereHas('topic', function ($query) use ($unitId) {
                $query->where('unit_id', $unitId);
            })->where('is_active', true)->count();

            // Return error if no flashcards available
            if ($totalCards === 0) {
                return response('No flashcards available to export', 422);
            }

            $exportFormats = FlashcardExportService::getExportFormats();

            return view('flashcards.partials.export-modal', [
                'unit' => $unit,
                'totalCards' => $totalCards,
                'exportFormats' => $exportFormats,
                'maxExportSize' => FlashcardExportService::MAX_EXPORT_SIZE,
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing export options: '.$e->getMessage());

            return response('Unable to load export options', 500);
        }
    }

    /**
     * Show export preview
     */
    public function preview(Request $request, int $unitId): View|JsonResponse
    {
        try {
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Verify unit exists and user has access
            $unit = Unit::with(['subject'])->findOrFail($unitId);
            if ((int) $unit->subject->user_id !== auth()->id()) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $validated = $request->validate([
                'export_format' => 'required|string|in:'.implode(',', array_keys(FlashcardExportService::getExportFormats())),
                'selected_cards' => 'sometimes|array',
                'selected_cards.*' => 'integer|exists:flashcards,id',
                'include_inactive' => 'sometimes|boolean',
                'deck_name' => 'sometimes|nullable|string|max:100',
                'include_metadata' => 'sometimes|boolean',
            ]);

            // Additional format-specific validation
            if ($validated['export_format'] === 'anki') {
                // Check if deck_name is provided and empty
                if (array_key_exists('deck_name', $validated) && (is_null($validated['deck_name']) || trim($validated['deck_name']) === '')) {
                    return response()->json(['error' => 'Deck name cannot be empty'], 422);
                }
            }

            // Get flashcards
            if (isset($validated['selected_cards']) && ! empty($validated['selected_cards'])) {
                $flashcards = Flashcard::whereIn('id', $validated['selected_cards'])
                    ->whereHas('topic', function ($query) use ($unitId) {
                        $query->where('unit_id', $unitId);
                    })
                    ->with(['topic'])
                    ->get();
            } elseif (isset($validated['selected_cards']) && empty($validated['selected_cards'])) {
                // Empty selection explicitly provided - return error
                return response()->json(['error' => 'No flashcards available to export'], 422);
            } else {
                // No selection provided - get all active flashcards
                $query = Flashcard::whereHas('topic', function ($query) use ($unitId) {
                    $query->where('unit_id', $unitId);
                });

                if (! ($validated['include_inactive'] ?? false)) {
                    $query->where('is_active', true);
                }

                $flashcards = $query->with(['topic'])->get();
            }

            if ($flashcards->isEmpty()) {
                return response()->json(['error' => 'No flashcards found to export'], 422);
            }

            // Get format info
            $exportFormats = FlashcardExportService::getExportFormats();
            $formatName = $exportFormats[$validated['export_format']] ?? 'Unknown Format';

            // Generate preview data
            $previewData = $this->exportService->generatePreviewData($flashcards, $validated['export_format']);

            // Prepare options for display
            $options = [];
            if (isset($validated['deck_name'])) {
                $options['deck_name'] = $validated['deck_name'];
            }
            if (isset($validated['include_metadata'])) {
                $options['include_metadata'] = $validated['include_metadata'];
            }

            return view('flashcards.partials.export-preview', [
                'unit' => $unit,
                'flashcards' => $flashcards,
                'format' => $validated['export_format'],
                'formatName' => $formatName,
                'totalCards' => $flashcards->count(),
                'canExport' => $flashcards->count() <= FlashcardExportService::MAX_EXPORT_SIZE,
                'previewData' => $previewData,
                'options' => $options,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->validator->errors()->toArray(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error showing export preview: '.$e->getMessage());

            return response()->json(['error' => 'Unable to generate export preview'], 500);
        }
    }

    /**
     * Download export file
     */
    public function download(Request $request, int $unitId): Response|JsonResponse
    {
        try {
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Verify unit exists and user has access
            $unit = Unit::with(['subject'])->findOrFail($unitId);
            if ((int) $unit->subject->user_id !== auth()->id()) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $validated = $request->validate([
                'export_format' => 'required|string|in:'.implode(',', array_keys(FlashcardExportService::getExportFormats())),
                'selected_cards' => 'sometimes|array',
                'selected_cards.*' => 'integer|exists:flashcards,id',
                'include_inactive' => 'sometimes|boolean',
                'deck_name' => 'sometimes|nullable|string|max:100',
                'include_metadata' => 'sometimes|boolean',
            ]);

            // Additional format-specific validation
            if ($validated['export_format'] === 'anki') {
                // Check if deck_name is provided and empty
                if (array_key_exists('deck_name', $validated) && (is_null($validated['deck_name']) || trim($validated['deck_name']) === '')) {
                    return response()->json(['error' => 'Deck name cannot be empty'], 422);
                }
            }

            // Get flashcards
            if (isset($validated['selected_cards']) && ! empty($validated['selected_cards'])) {
                $flashcards = Flashcard::whereIn('id', $validated['selected_cards'])
                    ->whereHas('topic', function ($query) use ($unitId) {
                        $query->where('unit_id', $unitId);
                    })
                    ->with(['topic.unit'])
                    ->get();
            } elseif (isset($validated['selected_cards']) && empty($validated['selected_cards'])) {
                // Empty selection explicitly provided - return error
                return response()->json(['error' => 'No flashcards available to export'], 422);
            } else {
                // No selection provided - get all active flashcards
                $query = Flashcard::whereHas('topic', function ($query) use ($unitId) {
                    $query->where('unit_id', $unitId);
                });

                if (! ($validated['include_inactive'] ?? false)) {
                    $query->where('is_active', true);
                }

                $flashcards = $query->with(['topic.unit'])->get();
            }

            if ($flashcards->isEmpty()) {
                return response()->json(['error' => 'No flashcards found to export'], 422);
            }

            // Prepare export options
            $exportOptions = [];
            if (isset($validated['deck_name'])) {
                $exportOptions['deck_name'] = $validated['deck_name'];
            } else {
                $exportOptions['deck_name'] = $unit->name.' Flashcards';
            }

            if (isset($validated['include_metadata'])) {
                $exportOptions['include_metadata'] = $validated['include_metadata'];
            }

            // Export flashcards
            $result = $this->exportService->exportFlashcards(
                $flashcards,
                $validated['export_format'],
                $exportOptions
            );

            if (! $result['success']) {
                return response()->json(['error' => $result['error']], 422);
            }

            return response($result['content'])
                ->header('Content-Type', $result['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="'.$result['filename'].'"');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->validator->errors()->toArray(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error downloading export: '.$e->getMessage());

            return response()->json(['error' => 'Unable to generate export'], 500);
        }
    }

    /**
     * Show bulk export selection modal
     */
    public function bulkExportSelection(Request $request, int $unitId): View|Response
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            // Verify unit exists and user has access
            $unit = Unit::with(['subject'])->findOrFail($unitId);
            if ((int) $unit->subject->user_id !== auth()->id()) {
                return response('Access denied', 403);
            }

            // Get all active flashcards with pagination
            $flashcards = Flashcard::whereHas('topic', function ($query) use ($unitId) {
                $query->where('unit_id', $unitId);
            })
                ->where('is_active', true)
                ->with(['topic'])
                ->orderBy('created_at')
                ->paginate(50); // 50 flashcards per page

            $exportFormats = FlashcardExportService::getExportFormats();

            return view('flashcards.partials.bulk-export-selection', [
                'unit' => $unit,
                'flashcards' => $flashcards,
                'totalCards' => $flashcards->total(),
                'exportFormats' => $exportFormats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing bulk export selection: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'unitId' => $unitId,
            ]);

            return response('Unable to load export selection: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get export statistics
     */
    public function exportStats(Request $request, int $unitId): JsonResponse
    {
        try {
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Verify unit exists and user has access
            $unit = Unit::with(['subject'])->findOrFail($unitId);
            if ((int) $unit->subject->user_id !== auth()->id()) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Get flashcard statistics
            $flashcardsQuery = Flashcard::whereHas('topic', function ($query) use ($unitId) {
                $query->where('unit_id', $unitId);
            })->where('is_active', true);

            $totalCards = $flashcardsQuery->count();

            // Get all flashcards for grouping operations
            $allFlashcards = $flashcardsQuery->get();
            $byType = $allFlashcards->groupBy('card_type')->map->count();
            $byDifficulty = $allFlashcards->groupBy('difficulty_level')->map->count();

            // Count cards with images (clone query for each count)
            $withImages = (clone $flashcardsQuery)->where(function ($query) {
                $query->whereNotNull('question_image_url')
                    ->orWhereNotNull('answer_image_url');
            })->count();

            // Count cards with hints
            $withHints = (clone $flashcardsQuery)->whereNotNull('hint')->count();

            // Count cards with tags (PostgreSQL compatible)
            $withTags = (clone $flashcardsQuery)->where(function ($query) {
                $query->whereNotNull('tags')
                    ->whereRaw('json_array_length(tags) > 0');
            })->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_cards' => $totalCards,
                    'by_type' => $byType,
                    'by_difficulty' => $byDifficulty,
                    'with_images' => $withImages,
                    'with_hints' => $withHints,
                    'with_tags' => $withTags,
                    'max_export_size' => FlashcardExportService::MAX_EXPORT_SIZE,
                    'can_export_all' => $totalCards <= FlashcardExportService::MAX_EXPORT_SIZE,
                ],
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting export stats: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'unitId' => $unitId,
            ]);

            return response()->json(['error' => 'Unable to get export statistics: '.$e->getMessage()], 500);
        }
    }
}
