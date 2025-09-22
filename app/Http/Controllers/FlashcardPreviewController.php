<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FlashcardPreviewController extends Controller
{
    /**
     * Start a flashcard preview session for a unit.
     *
     * CRITICAL: This is PREVIEW ONLY - no review records are created or modified.
     */
    public function startPreview(Request $request, Unit $unit): View|\Illuminate\Http\RedirectResponse
    {
        // Verify authentication
        if (! auth()->check()) {
            abort(401);
        }

        // Verify user owns this unit
        if ((int) $unit->subject->user_id !== auth()->id()) {
            abort(403, 'Access denied: Unit does not belong to current user');
        }

        // Block access in kids mode for security
        if (session('kids_mode')) {
            abort(403, 'Preview mode not available in kids mode');
        }

        try {

            // Get active flashcards for this unit
            $flashcards = $unit->allFlashcards()
                ->orderBy('created_at')
                ->get();

            if ($flashcards->isEmpty()) {
                return redirect()->route('units.show', $unit)
                    ->with('error', 'No flashcards available to preview in this unit.');
            }

            // Generate unique session ID for this preview session
            // Using timestamp + random to avoid collisions
            $randomNumber = mt_rand(1, 9999);
            $sessionId = 'preview_'.time().'_'.str_pad((string) $randomNumber, 4, '0', STR_PAD_LEFT);

            // Store session data in session (not database - this is preview only!)
            session()->put("flashcard_preview.{$sessionId}", [
                'unit_id' => $unit->id,
                'user_id' => auth()->id(),
                'flashcard_ids' => $flashcards->pluck('id')->toArray(),
                'current_index' => 0,
                'started_at' => now()->toISOString(),
                'answers' => [], // Store preview answers temporarily
                'is_preview' => true, // Critical flag to prevent database writes
            ]);

            // Get first flashcard
            $currentFlashcard = $flashcards->first();

            return view('flashcards.preview.session', [
                'unit' => $unit,
                'sessionId' => $sessionId,
                'currentFlashcard' => $currentFlashcard,
                'currentIndex' => 0,
                'totalCards' => $flashcards->count(),
                'isPreview' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Error starting flashcard preview session: '.$e->getMessage(), [
                'unit_id' => $unit->id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Unable to start preview session');
        }
    }

    /**
     * Get the next flashcard in preview session.
     */
    public function getNextCard(Request $request, string $sessionId): JsonResponse
    {
        try {
            // Verify authentication
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Block access in kids mode
            if (session('kids_mode')) {
                return response()->json(['error' => 'Preview mode not available in kids mode'], 403);
            }

            // Get session data
            $sessionKey = "flashcard_preview.{$sessionId}";
            $sessionData = session()->get($sessionKey);

            if (! $sessionData) {
                return response()->json(['error' => 'Preview session not found or expired'], 404);
            }

            // Verify user owns this session
            if ($sessionData['user_id'] !== auth()->id()) {
                return response()->json(['error' => 'Access denied to preview session'], 403);
            }

            // Verify this is a preview session
            if (! ($sessionData['is_preview'] ?? false)) {
                return response()->json(['error' => 'Invalid session type'], 400);
            }

            $currentIndex = $sessionData['current_index'];
            $flashcardIds = $sessionData['flashcard_ids'];

            // Check if we've reached the end
            if ($currentIndex >= count($flashcardIds)) {
                return response()->json([
                    'success' => true,
                    'session_complete' => true,
                    'total_cards' => count($flashcardIds),
                ]);
            }

            // Get current flashcard
            $flashcardId = $flashcardIds[$currentIndex];
            $flashcard = Flashcard::find($flashcardId);

            if (! $flashcard) {
                return response()->json(['error' => 'Flashcard not found'], 404);
            }

            return response()->json([
                'success' => true,
                'flashcard' => $flashcard->toArray(),
                'current_index' => $currentIndex,
                'total_cards' => count($flashcardIds),
                'session_complete' => false,
                'is_preview' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting next preview card: '.$e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
            ]);

            return response()->json(['error' => 'Unable to load next card'], 500);
        }
    }

    /**
     * Submit answer for preview (stored in session only - no database impact).
     */
    public function submitAnswer(Request $request, string $sessionId): JsonResponse
    {
        try {
            // Verify authentication
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Block access in kids mode
            if (session('kids_mode')) {
                return response()->json(['error' => 'Preview mode not available in kids mode'], 403);
            }

            $validated = $request->validate([
                'user_answer' => 'nullable|string',
                'selected_choices' => 'nullable|array',
                'cloze_answers' => 'nullable|array',
                'is_correct' => 'nullable|boolean',
                'time_spent' => 'nullable|integer|min:0',
            ]);

            // Get session data
            $sessionKey = "flashcard_preview.{$sessionId}";
            $sessionData = session()->get($sessionKey);

            if (! $sessionData) {
                return response()->json(['error' => 'Preview session not found'], 404);
            }

            // Verify user owns this session
            if ($sessionData['user_id'] !== auth()->id()) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Verify this is a preview session (critical for data isolation)
            if (! ($sessionData['is_preview'] ?? false)) {
                return response()->json(['error' => 'Invalid session type'], 400);
            }

            $currentIndex = $sessionData['current_index'];
            $flashcardIds = $sessionData['flashcard_ids'];

            if ($currentIndex >= count($flashcardIds)) {
                return response()->json(['error' => 'Session already complete'], 400);
            }

            $flashcardId = $flashcardIds[$currentIndex];

            // Store answer in session (NOT database)
            $sessionData['answers'][$currentIndex] = [
                'flashcard_id' => $flashcardId,
                'user_answer' => $validated['user_answer'] ?? null,
                'selected_choices' => $validated['selected_choices'] ?? null,
                'cloze_answers' => $validated['cloze_answers'] ?? null,
                'is_correct' => $validated['is_correct'] ?? null,
                'time_spent' => $validated['time_spent'] ?? null,
                'answered_at' => now()->toISOString(),
            ];

            // Move to next card
            $sessionData['current_index'] = $currentIndex + 1;

            // Update session
            session()->put($sessionKey, $sessionData);

            // Check if session is complete
            $sessionComplete = $sessionData['current_index'] >= count($flashcardIds);

            return response()->json([
                'success' => true,
                'next_index' => $sessionData['current_index'],
                'total_cards' => count($flashcardIds),
                'session_complete' => $sessionComplete,
                'is_preview' => true,
                'message' => 'Preview answer recorded (not saved to learning progress)',
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting preview answer: '.$e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
            ]);

            return response()->json(['error' => 'Unable to submit answer'], 500);
        }
    }

    /**
     * End preview session and show results.
     */
    public function endPreview(Request $request, string $sessionId): View
    {
        try {
            // Verify authentication
            if (! auth()->check()) {
                abort(401);
            }

            // Block access in kids mode
            if (session('kids_mode')) {
                abort(403, 'Preview mode not available in kids mode');
            }

            // Get session data
            $sessionKey = "flashcard_preview.{$sessionId}";
            $sessionData = session()->get($sessionKey);

            if (! $sessionData) {
                abort(404, 'Preview session not found');
            }

            // Verify user owns this session
            if ($sessionData['user_id'] !== auth()->id()) {
                abort(403, 'Access denied');
            }

            // Get unit for context
            $unit = Unit::find($sessionData['unit_id']);
            if (! $unit) {
                abort(404, 'Unit not found');
            }

            // Calculate preview statistics (from session data only)
            $answers = $sessionData['answers'] ?? [];
            $totalCards = count($sessionData['flashcard_ids']);
            $answeredCards = count($answers);

            $correctCount = 0;
            $totalTime = 0;
            foreach ($answers as $answer) {
                if ($answer['is_correct'] ?? false) {
                    $correctCount++;
                }
                $totalTime += $answer['time_spent'] ?? 0;
            }

            $previewStats = [
                'total_cards' => $totalCards,
                'answered_cards' => $answeredCards,
                'correct_answers' => $correctCount,
                'accuracy_percentage' => $answeredCards > 0 ? round(($correctCount / $answeredCards) * 100, 1) : 0,
                'total_time_seconds' => $totalTime,
                'average_time_per_card' => $answeredCards > 0 ? round($totalTime / $answeredCards, 1) : 0,
            ];

            // Clean up session data
            session()->forget($sessionKey);

            return view('flashcards.preview.complete', [
                'unit' => $unit,
                'previewStats' => $previewStats,
                'isPreview' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Error ending preview session: '.$e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
            ]);
            abort(500, 'Unable to end preview session');
        }
    }

    /**
     * Get preview session status.
     */
    public function getSessionStatus(Request $request, string $sessionId): JsonResponse
    {
        try {
            // Verify authentication
            if (! auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Get session data
            $sessionKey = "flashcard_preview.{$sessionId}";
            $sessionData = session()->get($sessionKey);

            if (! $sessionData) {
                return response()->json(['error' => 'Preview session not found'], 404);
            }

            // Verify user owns this session
            if ($sessionData['user_id'] !== auth()->id()) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            return response()->json([
                'success' => true,
                'current_index' => $sessionData['current_index'],
                'total_cards' => count($sessionData['flashcard_ids']),
                'answers_count' => count($sessionData['answers'] ?? []),
                'is_preview' => $sessionData['is_preview'] ?? false,
                'started_at' => $sessionData['started_at'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting preview session status: '.$e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
            ]);

            return response()->json(['error' => 'Unable to get session status'], 500);
        }
    }
}
