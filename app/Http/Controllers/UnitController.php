<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnitController extends Controller
{
    /**
     * Display a listing of units for a subject.
     */
    public function index(Request $request, int $subjectId)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return redirect()->route('login')->with('error', 'Please log in to continue.');
            }

            $subject = Subject::find($subjectId);
            if (! $subject || $subject->user_id != $userId) {
                return redirect()->route('subjects.index')->with('error', 'Subject not found.');
            }

            $units = Unit::forSubject($subjectId);

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return view('units.partials.units-list', compact('units', 'subject'));
            }

            return view('units.index', compact('units', 'subject'));
        } catch (\Exception $e) {
            Log::error('Error fetching units: '.$e->getMessage());

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error loading units. Please try again.').'</div>', 500);
            }

            return redirect()->route('subjects.index')->with('error', 'Unable to load units. Please try again.');
        }
    }

    /**
     * Show the form for creating a new unit.
     */
    public function create(Request $request, string $subject)
    {
        $subjectId = (int) $subject;
        Log::info('UnitController::create - START', ['subject_id' => $subjectId]);

        try {
            Log::info('UnitController::create - Getting user ID');
            $userId = auth()->id();
            if (! $userId) {
                Log::warning('UnitController::create - No user authenticated');

                return response('Unauthorized', 401);
            }
            Log::info('UnitController::create - User authenticated', ['user_id' => $userId]);

            Log::info('UnitController::create - Finding subject', ['subject_id' => $subjectId]);
            // Use the same approach as debug route that works
            $subjectModel = Subject::query()->find($subjectId);
            Log::info('UnitController::create - Subject query completed', ['found' => ! is_null($subjectModel)]);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                Log::warning('UnitController::create - Subject not found or access denied', [
                    'subject_found' => ! is_null($subjectModel),
                    'subject_user_id' => $subjectModel?->user_id,
                    'current_user_id' => $userId,
                ]);

                return response('Subject not found', 404);
            }
            Log::info('UnitController::create - Subject found and authorized', ['subject_name' => $subjectModel->name]);

            Log::info('UnitController::create - Checking request type');
            if ($request->header('HX-Request')) {
                Log::info('UnitController::create - Returning HTMX partial view');

                return view('units.partials.create-form', ['subject' => $subjectModel]);
            }

            Log::info('UnitController::create - Returning full page view');

            return view('units.create', ['subject' => $subjectModel]);
        } catch (\Exception $e) {
            Log::error('Error loading unit creation form: '.$e->getMessage(), [
                'subject_id' => $subjectId,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Unable to load form.', 500);
        }
    }

    /**
     * Store a newly created unit in storage.
     */
    public function store(Request $request, string $subject)
    {
        $subjectId = (int) $subject;
        Log::info('UnitController::store - START', ['subject_id' => $subjectId]);

        try {
            Log::info('UnitController::store - Getting user ID');
            $userId = auth()->id();
            if (! $userId) {
                Log::warning('UnitController::store - No user authenticated');

                return response('Unauthorized', 401);
            }
            Log::info('UnitController::store - User authenticated', ['user_id' => $userId]);

            Log::info('UnitController::store - Finding subject', ['subject_id' => $subjectId]);
            $subjectModel = Subject::query()->find($subjectId);
            Log::info('UnitController::store - Subject query completed', ['found' => ! is_null($subjectModel)]);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                Log::warning('UnitController::store - Subject not found or access denied', [
                    'subject_found' => ! is_null($subjectModel),
                    'subject_user_id' => $subjectModel?->user_id,
                    'current_user_id' => $userId,
                ]);

                return response('Subject not found', 404);
            }
            Log::info('UnitController::store - Subject found and authorized', ['subject_name' => $subjectModel->name]);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'target_completion_date' => 'nullable|date',
            ]);

            $unit = Unit::create([
                'subject_id' => $subjectId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?
                    \Carbon\Carbon::parse($validated['target_completion_date']) : null,
            ]);

            Log::info('Unit created successfully', ['unit_id' => $unit->id, 'name' => $unit->name, 'subject_id' => $subjectId]);

            if ($request->header('HX-Request')) {
                Log::info('UnitController::store - Returning updated units list for HTMX');

                // Return the updated units list and trigger modal close
                $units = Unit::forSubject($subjectId);
                $subject = $subjectModel;

                return response()
                    ->view('units.partials.units-list', compact('units', 'subject'))
                    ->header('HX-Trigger', 'unitCreated')
                    ->header('HX-Retarget', '#units-list')
                    ->header('HX-Reswap', 'innerHTML');
            }

            return redirect()->route('subjects.units.show', [$subjectId, $unit->id])->with('success', 'Unit created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating unit: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error creating unit. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to create unit. Please try again.']);
        }
    }

    /**
     * Display the specified unit.
     */
    public function show(Request $request, string $subject, string $unit)
    {
        $subjectId = (int) $subject;
        $unitId = (int) $unit;

        try {
            $userId = auth()->id();
            if (! $userId) {
                return redirect()->route('login');
            }

            $subjectModel = Subject::find($subjectId);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return redirect()->route('subjects.index')->with('error', 'Subject not found.');
            }

            $unitModel = Unit::find($unitId);
            if (! $unitModel || $unitModel->subject_id !== $subjectId) {
                return redirect()->route('subjects.show', $subjectId)->with('error', 'Unit not found.');
            }

            $topics = $unitModel->topics()->with('flashcards')->get();

            if ($request->header('HX-Request')) {
                return view('units.partials.unit-details', [
                    'unit' => $unitModel,
                    'subject' => $subjectModel,
                    'topics' => $topics,
                ]);
            }

            return view('units.show', [
                'unit' => $unitModel,
                'subject' => $subjectModel,
                'topics' => $topics,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unit: '.$e->getMessage());

            return redirect()->route('subjects.show', $subjectId)->with('error', 'Unable to load unit. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified unit.
     */
    public function edit(Request $request, int $subjectId, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::find($subjectId);
            if (! $subject || $subject->user_id != $userId) {
                return response('Subject not found', 404);
            }

            $unit = Unit::find($id);
            if (! $unit || $unit->subject_id !== $subjectId) {
                return response('Unit not found', 404);
            }

            if ($request->header('HX-Request')) {
                return view('units.partials.edit-form', compact('unit', 'subject'));
            }

            return view('units.edit', compact('unit', 'subject'));
        } catch (\Exception $e) {
            Log::error('Error loading unit for edit: '.$e->getMessage());

            return response('Unable to load unit for editing.', 500);
        }
    }

    /**
     * Update the specified unit in storage.
     */
    public function update(Request $request, int $subjectId, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::find($subjectId);
            if (! $subject || $subject->user_id != $userId) {
                return response('Subject not found', 404);
            }

            $unit = Unit::find($id);
            if (! $unit || $unit->subject_id !== $subjectId) {
                return response('Unit not found', 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'target_completion_date' => 'nullable|date',
            ]);

            $unit->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?
                    \Illuminate\Support\Carbon::parse($validated['target_completion_date']) : null,
            ]);

            if ($request->header('HX-Request')) {
                // Return updated units list
                $units = Unit::forSubject($subjectId);

                return view('units.partials.units-list', compact('units', 'subject'));
            }

            return redirect()->route('subjects.show', $subjectId)->with('success', 'Unit updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating unit: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error updating unit. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to update unit. Please try again.']);
        }
    }

    /**
     * Remove the specified unit from storage.
     */
    public function destroy(Request $request, int $subjectId, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::find($subjectId);
            if (! $subject || $subject->user_id != $userId) {
                return response('Subject not found', 404);
            }

            $unit = Unit::find($id);
            if (! $unit || $unit->subject_id !== $subjectId) {
                return response('Unit not found', 404);
            }

            // Check if unit has topics - prevent deletion if it has topics
            if ($unit->topics()->count() > 0) {
                if ($request->header('HX-Request')) {
                    return response('<div class="text-red-500">'.__('Cannot delete unit with existing topics. Please delete all topics first.').'</div>', 400);
                }

                return back()->withErrors(['error' => 'Cannot delete unit with existing topics. Please delete all topics first.']);
            }

            $unit->delete();

            if ($request->header('HX-Request')) {
                // Return updated units list
                $units = Unit::forSubject($subjectId);

                return view('units.partials.units-list', compact('units', 'subject'));
            }

            return redirect()->route('subjects.show', $subjectId)->with('success', 'Unit deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting unit: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error deleting unit. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to delete unit. Please try again.']);
        }
    }

    /**
     * Display the specified unit directly (without subject context).
     */
    public function showDirect(Request $request, string $unit)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return redirect()->route('login');
            }

            $unitId = (int) $unit;
            $unitModel = Unit::find($unitId);

            if (! $unitModel) {
                return redirect()->route('subjects.index')->with('error', 'Unit not found.');
            }

            $subjectModel = Subject::find($unitModel->subject_id);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return redirect()->route('subjects.index')->with('error', 'Access denied.');
            }

            $topics = $unitModel->topics;

            if ($request->header('HX-Request')) {
                return view('units.partials.unit-details', [
                    'unit' => $unitModel,
                    'subject' => $subjectModel,
                    'topics' => $topics,
                ]);
            }

            return view('units.show', [
                'unit' => $unitModel,
                'subject' => $subjectModel,
                'topics' => $topics,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unit directly: '.$e->getMessage());

            return redirect()->route('subjects.index')->with('error', 'Unable to load unit. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified unit directly.
     */
    public function editDirect(Request $request, string $unit)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $unitId = (int) $unit;
            $unitModel = Unit::find($unitId);

            if (! $unitModel) {
                return response('Unit not found', 404);
            }

            $subjectModel = Subject::find($unitModel->subject_id);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return response('Access denied', 403);
            }

            if ($request->header('HX-Request')) {
                return view('units.partials.edit-form', [
                    'unit' => $unitModel,
                    'subject' => $subjectModel,
                ]);
            }

            return view('units.edit', [
                'unit' => $unitModel,
                'subject' => $subjectModel,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading unit for edit directly: '.$e->getMessage());

            return response('Unable to load unit for editing.', 500);
        }
    }

    /**
     * Update the specified unit directly.
     */
    public function updateDirect(Request $request, string $unit)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $unitId = (int) $unit;
            $unitModel = Unit::find($unitId);

            if (! $unitModel) {
                return response('Unit not found', 404);
            }

            $subjectModel = Subject::find($unitModel->subject_id);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return response('Access denied', 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'target_completion_date' => 'nullable|date',
            ]);

            $unitModel->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'target_completion_date' => $validated['target_completion_date'] ?
                    \Illuminate\Support\Carbon::parse($validated['target_completion_date']) : null,
            ]);

            if ($request->header('HX-Request')) {
                // Return updated units list
                $units = Unit::forSubject($unitModel->subject_id);

                return view('units.partials.units-list', compact('units', 'subjectModel'));
            }

            return redirect()->route('units.show', $unitModel->id)->with('success', 'Unit updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating unit directly: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error updating unit. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to update unit. Please try again.']);
        }
    }

    /**
     * Remove the specified unit directly.
     */
    public function destroyDirect(Request $request, string $unit)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $unitId = (int) $unit;
            $unitModel = Unit::find($unitId);

            if (! $unitModel) {
                return response('Unit not found', 404);
            }

            $subjectModel = Subject::find($unitModel->subject_id);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return response('Access denied', 403);
            }

            // Check if unit has topics - prevent deletion if it has topics
            if ($unitModel->topics()->count() > 0) {
                if ($request->header('HX-Request')) {
                    return response('<div class="text-red-500">'.__('Cannot delete unit with existing topics. Please delete all topics first.').'</div>', 400);
                }

                return back()->withErrors(['error' => 'Cannot delete unit with existing topics. Please delete all topics first.']);
            }

            $subjectId = $unitModel->subject_id;
            $unitModel->delete();

            if ($request->header('HX-Request')) {
                // Return updated units list
                $units = Unit::forSubject($subjectId);
                $subject = $subjectModel;

                return view('units.partials.units-list', compact('units', 'subject'));
            }

            return redirect()->route('subjects.show', $subjectId)->with('success', 'Unit deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting unit directly: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error deleting unit. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to delete unit. Please try again.']);
        }
    }
}
