<?php

namespace App\Http\Controllers;

use App\Models\CatchUpSession;
use App\Models\Child;
use App\Models\Session;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Services\QualityHeuristicsService;
use App\Services\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __construct(
        private SchedulingEngine $schedulingEngine,
        private QualityHeuristicsService $qualityHeuristics
    ) {}

    public function index(Request $request): View
    {
        $userId = auth()->id();
        $children = Child::where('user_id', $userId)->orderBy('name')->get();

        // Default to first child or selected child
        $selectedChildId = $request->get('child_id', $children->first()?->id);
        $selectedChild = $selectedChildId ? Child::find((int) $selectedChildId) : null;

        if (! $selectedChild) {
            return view('planning.index', [
                'children' => $children,
                'selectedChild' => null,
                'sessionsByStatus' => [],
                'availableTopics' => collect([]),
                'capacityData' => [],
            ]);
        }

        // Get all sessions for selected child organized by status
        $allSessions = Session::forChild($selectedChild->id)->with('topic.unit.subject')->get();
        $sessionsByStatus = [
            'backlog' => $allSessions->where('status', 'backlog'),
            'planned' => $allSessions->where('status', 'planned'),
            'scheduled' => $allSessions->where('status', 'scheduled'),
            'done' => $allSessions->where('status', 'done'),
        ];

        // Get catch-up sessions for selected child
        $catchUpSessions = CatchUpSession::pending($selectedChild->id)->with('topic.unit.subject')->get();

        // Get all topics for this child's subjects that don't have sessions yet
        $subjects = Subject::where('user_id', $userId)
            ->with(['units.topics' => function ($query) {
                $query->with('unit.subject');
            }])
            ->orderBy('name')
            ->get();

        $availableTopics = collect([]);
        $existingTopicIds = $allSessions->pluck('topic_id')->toArray();

        foreach ($subjects as $subject) {
            foreach ($subject->units as $unit) {
                foreach ($unit->topics as $topic) {
                    // Check if topic already has sessions for this child
                    if (! in_array($topic->id, $existingTopicIds)) {
                        $availableTopics->push($topic);
                    }
                }
            }
        }

        // Calculate weekly capacity data
        $capacityData = $this->calculateWeeklyCapacity($selectedChild);

        // If HTMX request, return partial planning board
        if ($request->header('HX-Request')) {
            return view('planning.partials.board', compact(
                'sessionsByStatus',
                'selectedChild',
                'availableTopics',
                'capacityData',
                'catchUpSessions'
            ));
        }

        return view('planning.index', compact(
            'children',
            'selectedChild',
            'sessionsByStatus',
            'availableTopics',
            'capacityData',
            'catchUpSessions'
        ));
    }

    public function createSession(Request $request): View|RedirectResponse
    {
        // Handle GET request - show form
        if ($request->isMethod('GET')) {
            $childId = $request->get('child_id');
            $child = Child::find((int) $childId);

            if (! $child || $child->user_id != auth()->id()) {
                abort(403);
            }

            // Get all topics for this user that don't have sessions yet for this child
            $subjects = Subject::where('user_id', auth()->id())->orderBy('name')->get();
            $availableTopics = collect([]);
            $existingSessions = Session::forChild($childId);

            foreach ($subjects as $subject) {
                /** @var \App\Models\Subject $subject */
                $units = $subject->units;
                foreach ($units as $unit) {
                    /** @var \App\Models\Unit $unit */
                    $topics = $unit->topics;
                    foreach ($topics as $topic) {
                        /** @var \App\Models\Topic $topic */
                        $existingSession = $existingSessions->where('topic_id', $topic->id)->first();
                        if (! $existingSession) {
                            $availableTopics->push($topic);
                        }
                    }
                }
            }

            return view('planning.partials.create-session-form', [
                'childId' => $childId,
                'availableTopics' => $availableTopics,
            ]);
        }

        // Handle POST request - create session
        $validated = $request->validate([
            'topic_id' => 'required|integer|exists:topics,id',
            'child_id' => 'required|integer|exists:children,id',
            'estimated_minutes' => 'nullable|integer|min:5|max:480',
        ]);

        // Verify child belongs to the current user
        $child = Child::find((int) $validated['child_id']);
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        // Get topic and use its estimated minutes if not provided
        $topic = Topic::find($validated['topic_id']);
        if (! $topic) {
            abort(404, 'Topic not found');
        }

        // Verify topic belongs to user's subjects (through unit -> subject)
        $subject = $topic->unit->subject;
        if (! $subject || $subject->user_id != auth()->id()) {
            abort(403, 'Topic does not belong to user');
        }

        // Check if session already exists for this topic and child
        $existingSession = Session::forChild($validated['child_id'])
            ->where('topic_id', $validated['topic_id'])
            ->first();

        if ($existingSession) {
            return back()->withErrors(['topic_id' => 'Session already exists for this topic']);
        }

        // Create the session
        $session = new Session([
            'topic_id' => $validated['topic_id'],
            'child_id' => $validated['child_id'],
            'estimated_minutes' => $validated['estimated_minutes'] ?? $topic->estimated_minutes,
            'status' => 'backlog',
        ]);

        $session->save();

        // Return updated backlog column
        $sessions = Session::forChildAndStatus($validated['child_id'], 'backlog');

        return view('planning.partials.column', [
            'status' => 'backlog',
            'statusTitle' => 'Backlog',
            'sessions' => $sessions,
            'selectedChild' => $child,
        ])->with('htmx_trigger', 'sessionCreated');
    }

    public function updateSessionStatus(Request $request, int $id): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'status' => 'required|string|in:backlog,planned,scheduled,done',
        ]);

        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $oldStatus = $session->status;
        $session->updateStatus($validated['status']);

        // Return updated columns for both old and new status
        $oldSessions = Session::forChildAndStatus($child->id, $oldStatus);
        $newSessions = Session::forChildAndStatus($child->id, $validated['status']);

        $statusTitles = [
            'backlog' => 'Backlog',
            'planned' => 'Planned',
            'scheduled' => 'Scheduled',
            'done' => 'Done',
        ];

        // Return multiple column updates
        $response = '';
        $response .= view('planning.partials.column', [
            'status' => $oldStatus,
            'statusTitle' => $statusTitles[$oldStatus],
            'sessions' => $oldSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.column', [
            'status' => $validated['status'],
            'statusTitle' => $statusTitles[$validated['status']],
            'sessions' => $newSessions,
            'selectedChild' => $child,
        ])->render();

        return response($response)->header('HX-Trigger', 'sessionStatusUpdated');
    }

    public function scheduleSession(Request $request, int $id): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'scheduled_day_of_week' => 'required|integer|min:1|max:7',
            'scheduled_start_time' => 'required|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'scheduled_end_time' => 'required|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/|after:scheduled_start_time',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
        ]);

        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        // Check capacity for the requested time slot
        $scheduledDate = $validated['scheduled_date'] ? \Carbon\Carbon::parse($validated['scheduled_date']) : null;

        $session->scheduleToTimeSlot(
            $validated['scheduled_day_of_week'],
            $validated['scheduled_start_time'].':00',
            $validated['scheduled_end_time'].':00',
            $scheduledDate
        );

        // Return updated columns and capacity meter
        $plannedSessions = Session::forChildAndStatus($child->id, 'planned');
        $scheduledSessions = Session::forChildAndStatus($child->id, 'scheduled');
        $capacityData = $this->calculateWeeklyCapacity($child);

        $response = '';
        $response .= view('planning.partials.column', [
            'status' => 'planned',
            'statusTitle' => 'Planned',
            'sessions' => $plannedSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.column', [
            'status' => 'scheduled',
            'statusTitle' => 'Scheduled',
            'sessions' => $scheduledSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.capacity-meter', compact('capacityData', 'child'))->render();

        return response($response)->header('HX-Trigger', 'sessionScheduled');
    }

    public function unscheduleSession(int $id): \Illuminate\Http\Response
    {
        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $session->unschedule();

        // Return updated columns and capacity meter
        $plannedSessions = Session::forChildAndStatus($child->id, 'planned');
        $scheduledSessions = Session::forChildAndStatus($child->id, 'scheduled');
        $capacityData = $this->calculateWeeklyCapacity($child);

        $response = '';
        $response .= view('planning.partials.column', [
            'status' => 'planned',
            'statusTitle' => 'Planned',
            'sessions' => $plannedSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.column', [
            'status' => 'scheduled',
            'statusTitle' => 'Scheduled',
            'sessions' => $scheduledSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.capacity-meter', compact('capacityData', 'child'))->render();

        return response($response)->header('HX-Trigger', 'sessionUnscheduled');
    }

    public function deleteSession(int $id): string
    {
        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $status = $session->status;
        $session->delete();

        // Return empty response for HTMX to remove element
        response()->noContent()->header('HX-Trigger', 'sessionDeleted')->send();

        return '';
    }

    public function showSkipDayModal(Request $request, int $id): View
    {
        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $defaultDate = $request->get('date', 'today');

        return view('planning.partials.skip-day-modal', [
            'session' => $session,
            'defaultDate' => $defaultDate,
        ]);
    }

    public function skipSessionDay(Request $request, int $id): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'skip_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        // Skip the session using the scheduling engine
        $skipDate = Carbon::parse($validated['skip_date']);
        $result = $this->schedulingEngine->skipSessionDay($session, $skipDate, $validated['reason']);

        // Get updated data
        $scheduledSessions = Session::forChildAndStatus($child->id, 'scheduled');
        $catchUpSessions = CatchUpSession::pending($child->id);
        $capacityData = $this->calculateWeeklyCapacity($child);

        $response = '';
        $response .= view('planning.partials.column', [
            'status' => 'scheduled',
            'statusTitle' => 'Scheduled',
            'sessions' => $scheduledSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.catch-up-column', [
            'catchUpSessions' => $catchUpSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.capacity-meter', compact('capacityData', 'child'))->render();

        return response($response)->header('HX-Trigger', 'sessionSkipped');
    }

    public function updateSessionCommitmentType(Request $request, int $id): View
    {
        $validated = $request->validate([
            'commitment_type' => 'required|string|in:fixed,preferred,flexible',
        ]);

        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $session->updateCommitmentType($validated['commitment_type']);

        // Return updated session card
        return view('planning.partials.session-card', [
            'session' => $session,
            'selectedChild' => $child,
        ])->with('htmx_trigger', 'commitmentTypeUpdated');
    }

    public function redistributeCatchUp(Request $request): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'child_id' => 'required|integer|exists:children,id',
            'max_sessions' => 'nullable|integer|min:1|max:10',
        ]);

        $child = Child::find((int) $validated['child_id']);
        /** @var \App\Models\Child $child */
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        // Redistribute catch-up sessions
        $maxSessions = $validated['max_sessions'] ?? 5;
        $redistributed = $this->schedulingEngine->redistributeCatchUpSessions($child, $maxSessions);

        // Get updated data
        $scheduledSessions = Session::forChildAndStatus($child->id, 'scheduled');
        $catchUpSessions = CatchUpSession::pending($child->id);
        $capacityData = $this->calculateWeeklyCapacity($child);

        $response = '';
        $response .= view('planning.partials.column', [
            'status' => 'scheduled',
            'statusTitle' => 'Scheduled',
            'sessions' => $scheduledSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.catch-up-column', [
            'catchUpSessions' => $catchUpSessions,
            'selectedChild' => $child,
        ])->render();

        $response .= view('planning.partials.capacity-meter', compact('capacityData', 'child'))->render();

        return response($response)->header('HX-Trigger', 'catchUpRedistributed');
    }

    public function updateCatchUpPriority(Request $request, int $id): View
    {
        $validated = $request->validate([
            'priority' => 'required|integer|min:1|max:5',
        ]);

        $catchUpSession = CatchUpSession::find($id);
        if (! $catchUpSession) {
            abort(404);
        }

        // Verify catch-up session belongs to user's child
        $child = $catchUpSession->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $catchUpSession->updatePriority($validated['priority']);

        // Return updated catch-up sessions
        $catchUpSessions = CatchUpSession::pending($child->id);

        return view('planning.partials.catch-up-column', [
            'catchUpSessions' => $catchUpSessions,
            'selectedChild' => $child,
        ])->with('htmx_trigger', 'catchUpPriorityUpdated');
    }

    public function deleteCatchUpSession(int $id): string
    {
        $catchUpSession = CatchUpSession::find($id);
        if (! $catchUpSession) {
            abort(404);
        }

        // Verify catch-up session belongs to user's child
        $child = $catchUpSession->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $catchUpSession->delete();

        // Return empty response for HTMX to remove element
        response()->noContent()->header('HX-Trigger', 'catchUpSessionDeleted')->send();

        return '';
    }

    public function getSchedulingSuggestions(Request $request, int $id): View
    {
        $validated = $request->validate([
            'original_date' => 'required|date',
        ]);

        $session = Session::find($id);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $originalDate = Carbon::parse($validated['original_date']);
        $suggestions = $this->schedulingEngine->generateRescheduleSuggestions($session, $originalDate);

        return view('planning.partials.scheduling-suggestions', [
            'session' => $session,
            'suggestions' => $suggestions,
            'originalDate' => $originalDate,
        ]);
    }

    public function getCapacityAnalysis(Request $request): View
    {
        $validated = $request->validate([
            'child_id' => 'required|integer|exists:children,id',
        ]);

        $child = Child::find((int) $validated['child_id']);
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $analysis = $this->schedulingEngine->analyzeCapacity($child);
        $suggestions = $this->schedulingEngine->suggestOptimalScheduling($child);

        return view('planning.partials.capacity-analysis', [
            'child' => $child,
            'analysis' => $analysis,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Get quality analysis for a child's schedule
     */
    public function getQualityAnalysis(Request $request): View
    {
        $validated = $request->validate([
            'child_id' => 'required|integer|exists:children,id',
        ]);

        $child = Child::find((int) $validated['child_id']);
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        $qualityAnalysis = $this->qualityHeuristics->validateSchedulingQuality($child);
        $recommendations = $this->qualityHeuristics->getSchedulingRecommendations($child);

        return view('planning.partials.quality-analysis', [
            'child' => $child,
            'analysis' => $qualityAnalysis,
            'recommendations' => $recommendations,
        ]);
    }

    private function calculateWeeklyCapacity(Child $child): array
    {
        $timeBlocks = $child->timeBlocks;
        $scheduledSessions = Session::forChildAndStatus($child->id, 'scheduled');

        $capacityData = [];

        // Initialize days
        for ($day = 1; $day <= 7; $day++) {
            $dayTimeBlocks = $timeBlocks->where('day_of_week', $day);
            $dayScheduledSessions = $scheduledSessions->where('scheduled_day_of_week', $day);

            $totalAvailableMinutes = $dayTimeBlocks->sum(function ($block) {
                return $block->getDurationMinutes();
            });

            $totalScheduledMinutes = $dayScheduledSessions->sum('estimated_minutes');

            $utilizationPercent = $totalAvailableMinutes > 0
                ? ($totalScheduledMinutes / $totalAvailableMinutes) * 100
                : 0;

            $status = 'green';
            if ($utilizationPercent >= 90) {
                $status = 'red';
            } elseif ($utilizationPercent >= 75) {
                $status = 'yellow';
            }

            $capacityData[$day] = [
                'day' => $day,
                'day_name' => $this->getDayName($day),
                'available_minutes' => $totalAvailableMinutes,
                'scheduled_minutes' => $totalScheduledMinutes,
                'remaining_minutes' => max(0, $totalAvailableMinutes - $totalScheduledMinutes),
                'utilization_percent' => round($utilizationPercent, 1),
                'status' => $status,
                'time_blocks_count' => $dayTimeBlocks->count(),
                'sessions_count' => $dayScheduledSessions->count(),
            ];
        }

        return $capacityData;
    }

    private function getDayName(int $day): string
    {
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return $days[$day] ?? '';
    }

    public function destroySession(int $sessionId): Response
    {
        $session = Session::find($sessionId);
        if (! $session) {
            abort(404);
        }

        // Verify session belongs to user's child
        $child = $session->child;
        if (! $child || $child->user_id != auth()->id()) {
            abort(403);
        }

        // Delete the session
        $session->delete();

        // Return empty content to remove the session card from the UI
        return response('')->header('HX-Trigger', 'sessionDeleted');
    }
}
