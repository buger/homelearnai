<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubjectController extends Controller
{
    // No constructor needed - using Eloquent relationships

    /**
     * Display a listing of subjects.
     */
    public function index(Request $request)
    {
        try {
            if (! auth()->check()) {
                return redirect()->route('login')->with('error', 'Please log in to continue.');
            }

            // Get all children for this user using Eloquent relationships
            $user = auth()->user();
            $children = $user->children()->orderBy('name')->get();

            // Determine selected child - default to first child if none selected
            /** @var \App\Models\Child|null $firstChild */
            $firstChild = $children->first();
            $selectedChildId = $request->get('child_id', $firstChild?->id);
            $selectedChild = null;

            if ($selectedChildId) {
                /** @var \App\Models\Child|null $selectedChild */
                $selectedChild = $children->firstWhere('id', (int) $selectedChildId);
            }

            // Get subjects for the selected child or show empty state
            if ($selectedChild) {
                $subjects = $selectedChild->subjects()->withCount('units')->orderBy('name')->get();
            } else {
                $subjects = collect([]);
            }

            $showQuickStart = $selectedChild && $subjects->isEmpty();

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return view('subjects.partials.subjects-list', compact('subjects', 'showQuickStart', 'selectedChild'));
            }

            return view('subjects.index', compact('subjects', 'showQuickStart', 'children', 'selectedChild'));
        } catch (\Exception $e) {
            Log::error('Error fetching subjects: '.$e->getMessage());

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error loading subjects. Please try again.').'</div>', 500);
            }

            return back()->with('error', 'Unable to load subjects. Please try again.');
        }
    }

    /**
     * Show the form for creating a new subject.
     */
    public function create(Request $request)
    {
        $colors = Subject::getColorOptions();
        $childId = $request->get('child_id');

        if ($request->header('HX-Request')) {
            return view('subjects.partials.create-form', compact('colors', 'childId'));
        }

        return view('subjects.create', compact('colors', 'childId'));
    }

    /**
     * Store a newly created subject in storage.
     */
    public function store(Request $request)
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'color' => 'required|string',
                'child_id' => 'required|integer|exists:children,id',
            ]);

            // Validate color is in allowed options
            $allowedColors = array_keys(Subject::getColorOptions());
            if (! in_array($validated['color'], $allowedColors)) {
                return back()->withErrors(['color' => 'Invalid color selected.']);
            }

            // Verify child belongs to the current user using relationships
            /** @var \App\Models\Child|null $child */
            $child = auth()->user()->children()->find($validated['child_id']);
            if (! $child) {
                return response('Child not found or access denied', 403);
            }

            $subject = new Subject([
                'name' => $validated['name'],
                'color' => $validated['color'],
                'user_id' => auth()->id(),
                'child_id' => $validated['child_id'],
            ]);

            if ($subject->save()) {
                if ($request->header('HX-Request')) {
                    // Return updated subjects list for the specific child using relationships
                    $subjects = $child->subjects()->orderBy('name')->get();
                    $showQuickStart = false;
                    $selectedChild = $child;

                    return view('subjects.partials.subjects-list', compact('subjects', 'showQuickStart', 'selectedChild'));
                }

                return redirect()->route('subjects.index')->with('success', 'Subject created successfully.');
            } else {
                throw new \Exception('Failed to save subject');
            }
        } catch (\Exception $e) {
            Log::error('Error creating subject: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error creating subject. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to create subject. Please try again.']);
        }
    }

    /**
     * Display the specified subject.
     */
    public function show(Request $request, string $id)
    {
        try {
            if (! auth()->check()) {
                return redirect()->route('login');
            }

            $user = auth()->user();
            $subject = $user->children()
                ->with('subjects.units')
                ->get()
                ->pluck('subjects')
                ->flatten()
                ->where('id', $id)
                ->first();

            if (! $subject) {
                return redirect()->route('subjects.index')->with('error', 'Subject not found.');
            }

            $units = $subject->units;

            if ($request->header('HX-Request')) {
                return view((string) 'subjects.partials.subject-details', compact('subject', 'units'));
            }

            return view((string) 'subjects.show', compact('subject', 'units'));
        } catch (\Exception $e) {
            Log::error('Error fetching subject: '.$e->getMessage());

            return redirect()->route('subjects.index')->with('error', 'Unable to load subject. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified subject.
     */
    public function edit(Request $request, string $id)
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $subject) {
                return response('Subject not found', 404);
            }

            $colors = Subject::getColorOptions();

            if ($request->header('HX-Request')) {
                return view('subjects.partials.edit-form', compact('subject', 'colors'));
            }

            return view('subjects.edit', compact('subject', 'colors'));
        } catch (\Exception $e) {
            Log::error('Error loading subject for edit: '.$e->getMessage());

            return response('Unable to load subject for editing.', 500);
        }
    }

    /**
     * Update the specified subject in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $subject) {
                return response('Subject not found', 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'color' => 'required|string',
            ]);

            // Validate color is in allowed options
            $allowedColors = array_keys(Subject::getColorOptions());
            if (! in_array($validated['color'], $allowedColors)) {
                return back()->withErrors(['color' => 'Invalid color selected.']);
            }

            $subject->name = $validated['name'];
            $subject->color = $validated['color'];

            if ($subject->save()) {
                if ($request->header('HX-Request')) {
                    // Return updated subjects list using child relationship
                    /** @var \App\Models\Child $child */
                    $child = $subject->child;
                    $subjects = $child->subjects()->orderBy('name')->get();
                    $showQuickStart = false;
                    $selectedChild = $child;

                    return view('subjects.partials.subjects-list', compact('subjects', 'showQuickStart', 'selectedChild'));
                }

                return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
            } else {
                throw new \Exception('Failed to update subject');
            }
        } catch (\Exception $e) {
            Log::error('Error updating subject: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error updating subject. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to update subject. Please try again.']);
        }
    }

    /**
     * Remove the specified subject from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            $subject = Subject::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $subject) {
                return response('Subject not found', 404);
            }

            // Check if subject has units - prevent deletion if it has units
            $unitCount = $subject->units()->count();
            if ($unitCount > 0) {
                if ($request->header('HX-Request')) {
                    return response('<div class="text-red-500">'.__('Cannot delete subject with existing units. Please delete all units first.').'</div>', 400);
                }

                return back()->withErrors(['error' => 'Cannot delete subject with existing units. Please delete all units first.']);
            }

            if ($subject->delete()) {
                if ($request->header('HX-Request')) {
                    // Return updated subjects list using child relationship
                    /** @var \App\Models\Child $child */
                    $child = $subject->child;
                    $subjects = $child->subjects()->orderBy('name')->get();
                    $showQuickStart = false;
                    $selectedChild = $child;

                    return view('subjects.partials.subjects-list', compact('subjects', 'showQuickStart', 'selectedChild'));
                }

                return redirect()->route('subjects.index')->with('success', 'Subject deleted successfully.');
            } else {
                throw new \Exception('Failed to delete subject');
            }
        } catch (\Exception $e) {
            Log::error('Error deleting subject: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error deleting subject. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to delete subject. Please try again.']);
        }
    }

    /**
     * Show the quick start form for creating multiple subjects.
     */
    public function quickStartForm(Request $request)
    {
        if (! auth()->check()) {
            return response('Unauthorized', 401);
        }

        // Get child_id from request
        $childId = $request->get('child_id');
        if (! $childId) {
            return response('Child ID is required', 400);
        }

        // Verify child belongs to the current user using relationships
        /** @var \App\Models\Child|null $child */
        $child = auth()->user()->children()->find($childId);
        if (! $child) {
            return response('Child not found or access denied', 403);
        }

        // Get subject templates for all grade levels
        $templates = $this->getSubjectTemplates();

        if ($request->header('HX-Request')) {
            return view('subjects.partials.quick-start-modal', compact('templates', 'child'));
        }

        return response('This endpoint only supports HTMX requests', 400);
    }

    /**
     * Store multiple subjects from quick start.
     */
    public function quickStartStore(Request $request)
    {
        try {
            if (! auth()->check()) {
                return response('Unauthorized', 401);
            }

            $validated = $request->validate([
                'grade_level' => 'required|in:elementary,middle,high',
                'subjects' => 'required|array|min:1',
                'subjects.*' => 'string|max:255',
                'custom_subjects' => 'nullable|array',
                'custom_subjects.*' => 'nullable|string|max:255',
                'child_id' => 'required|integer|exists:children,id',
            ]);

            // Verify child belongs to the current user using relationships
            /** @var \App\Models\Child|null $child */
            $child = auth()->user()->children()->find($validated['child_id']);
            if (! $child) {
                return response('Child not found or access denied', 403);
            }

            $createdCount = 0;
            $colorOptions = array_keys(Subject::getColorOptions());
            $colorIndex = 0;

            // Create selected standard subjects
            foreach ($validated['subjects'] as $subjectName) {
                $subject = new Subject([
                    'name' => $subjectName,
                    'color' => $this->getSubjectColor($subjectName, $colorOptions, $colorIndex),
                    'user_id' => auth()->id(),
                    'child_id' => $validated['child_id'],
                ]);

                if ($subject->save()) {
                    $createdCount++;
                    $colorIndex++;
                }
            }

            // Create custom subjects if any
            if (! empty($validated['custom_subjects'])) {
                foreach ($validated['custom_subjects'] as $customName) {
                    if (trim($customName) !== '') {
                        $subject = new Subject([
                            'name' => trim($customName),
                            'color' => $colorOptions[$colorIndex % count($colorOptions)],
                            'user_id' => auth()->id(),
                            'child_id' => $validated['child_id'],
                        ]);

                        if ($subject->save()) {
                            $createdCount++;
                            $colorIndex++;
                        }
                    }
                }
            }

            if ($request->header('HX-Request')) {
                // Return updated subjects list for the specific child using relationships
                $subjects = $child->subjects()->orderBy('name')->get();
                $showQuickStart = false;
                $selectedChild = $child;

                return view('subjects.partials.subjects-list', compact('subjects', 'showQuickStart', 'selectedChild'))
                    ->with('success', __(':count subjects created successfully', ['count' => $createdCount]));
            }

            return redirect()->route('subjects.index')
                ->with('success', __(':count subjects created successfully', ['count' => $createdCount]));

        } catch (\Exception $e) {
            Log::error('Error in quick start: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error creating subjects. Please try again.').'</div>', 500);
            }

            return back()->with('error', 'Unable to create subjects. Please try again.');
        }
    }

    /**
     * Get subject color based on subject type.
     */
    private function getSubjectColor(string $subjectName, array $colorOptions, int &$index): string
    {
        // Map subjects to specific colors for consistency
        $colorMap = [
            'Mathematics' => '#3B82F6', // Blue
            'Science' => '#10B981', // Green
            'Biology' => '#10B981',
            'Chemistry' => '#10B981',
            'Physics' => '#10B981',
            'Life Science' => '#10B981',
            'Earth Science' => '#10B981',
            'Physical Science' => '#10B981',
            'Reading/Language Arts' => '#8B5CF6', // Purple
            'English Language Arts' => '#8B5CF6',
            'Social Studies' => '#F97316', // Orange
            'History' => '#F97316',
            'World History' => '#F97316',
            'U.S. History' => '#F97316',
            'Art' => '#EC4899', // Pink
            'Music' => '#EC4899',
            'Physical Education' => '#EF4444', // Red
            'Health' => '#EF4444',
            'Computer Science' => '#6B7280', // Gray
            'Foreign Language' => '#14B8A6', // Teal
            'World Language' => '#14B8A6',
        ];

        // Check if we have a specific color for this subject
        foreach ($colorMap as $key => $color) {
            if (stripos($subjectName, $key) !== false) {
                return $color;
            }
        }

        // Return next available color from the options
        return $colorOptions[$index % count($colorOptions)];
    }

    /**
     * Get subject templates by grade level.
     */
    private function getSubjectTemplates(): array
    {
        return [
            'elementary' => [
                __('reading_language_arts'),
                __('mathematics'),
                __('science'),
                __('social_studies'),
                __('art'),
                __('music'),
                __('physical_education'),
            ],
            'middle' => [
                __('english_language_arts'),
                __('mathematics'),
                __('life_science'),
                __('earth_science'),
                __('physical_science'),
                __('social_studies'),
                __('world_history'),
                __('physical_education'),
                __('world_language'),
                __('computer_science'),
                __('art'),
                __('music'),
                __('health'),
            ],
            'high' => [
                __('english_language_arts'),
                __('algebra'),
                __('geometry'),
                __('calculus'),
                __('biology'),
                __('chemistry'),
                __('physics'),
                __('world_history'),
                __('us_history'),
                __('foreign_language'),
                __('computer_science'),
                __('economics'),
                __('psychology'),
                __('art'),
                __('physical_education'),
            ],
        ];
    }
}
