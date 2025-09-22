<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Services\KidsGamificationService;
use App\Services\RichContentService;
use App\Services\TopicMaterialService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TopicController extends Controller
{
    protected TopicMaterialService $materialService;

    protected RichContentService $richContentService;

    public function __construct(
        TopicMaterialService $materialService,
        RichContentService $richContentService
    ) {
        $this->materialService = $materialService;
        $this->richContentService = $richContentService;
    }

    /**
     * Display a listing of topics for a unit.
     */
    public function index(Request $request, int $subjectId, int $unitId)
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

            $unit = Unit::find($unitId);
            if (! $unit || $unit->subject_id !== $subjectId) {
                return redirect()->route('subjects.show', $subjectId)->with('error', 'Unit not found.');
            }

            $topics = Topic::forUnit($unitId);

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return view('topics.partials.topics-list', compact('topics', 'unit', 'subject'));
            }

            return view('topics.index', compact('topics', 'unit', 'subject'));
        } catch (\Exception $e) {
            Log::error('Error fetching topics: '.$e->getMessage());

            if ($request->expectsJson() || $request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error loading topics. Please try again.').'</div>', 500);
            }

            return redirect()->route('subjects.show', $subjectId)->with('error', 'Unable to load topics. Please try again.');
        }
    }

    /**
     * Show the form for creating a new topic.
     */
    public function create(Request $request, string $subject, string $unit)
    {
        try {
            $subjectId = (int) $subject;
            $unitId = (int) $unit;

            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $subjectModel = Subject::find($subjectId);
            if (! $subjectModel || $subjectModel->user_id != $userId) {
                return response('Subject not found', 404);
            }

            $unitModel = Unit::find($unitId);
            if (! $unitModel || $unitModel->subject_id !== $subjectId) {
                return response('Unit not found', 404);
            }

            if ($request->header('HX-Request')) {
                return view('topics.partials.create-form', [
                    'unit' => $unitModel,
                    'subject' => $subjectModel,
                ]);
            }

            return view('topics.create', [
                'unit' => $unitModel,
                'subject' => $subjectModel,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading topic creation form: '.$e->getMessage());

            return response('Unable to load form.', 500);
        }
    }

    /**
     * Store a newly created topic in storage (unit-specific route).
     */
    public function storeForUnit(Request $request, int $unitId)
    {
        $userId = auth()->id();
        if (! $userId) {
            return response('Unauthorized', 401);
        }

        $unit = Unit::find($unitId);
        if (! $unit) {
            return response('Unit not found', 404);
        }

        $subject = Subject::find($unit->subject_id);
        if (! $subject || $subject->user_id != $userId) {
            return response('Access denied', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'estimated_minutes' => 'required|integer|min:5|max:480',
            'required' => 'boolean',
        ]);

        try {
            // Process rich content if provided
            $contentFormat = $validated['content_format'] ?? 'plain';
            $description = $validated['description'] ?? null;
            $contentMetadata = null;

            if (! empty($description) && $contentFormat !== 'plain') {
                $richContent = $this->richContentService->processRichContent($description, $contentFormat);
                $contentMetadata = $richContent['metadata'];
            }

            // Use 'name' field but store as 'title' in the model
            $topic = Topic::create([
                'unit_id' => $unitId,
                'title' => $validated['title'], // Store title as title
                'description' => $description,
                'content_format' => $contentFormat,
                'content_metadata' => $contentMetadata,
                'estimated_minutes' => $validated['estimated_minutes'],
                'required' => $validated['required'] ?? true,
                'prerequisites' => [], // Empty for now
                'learning_materials' => null,
            ]);

            if ($request->header('HX-Request')) {
                // Return updated topics list
                $topics = Topic::forUnit($unitId);

                return view('topics.partials.topics-list', compact('topics', 'unit', 'subject'));
            }

            return redirect()->route('subjects.units.show', [$subject->id, $unitId])->with('success', 'Topic created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating topic for unit: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error creating topic. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to create topic. Please try again.']);
        }
    }

    /**
     * Store a newly created topic in storage.
     */
    public function store(Request $request, int $subjectId, int $unitId)
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

            $unit = Unit::find($unitId);
            if (! $unit || $unit->subject_id !== $subjectId) {
                return response('Unit not found', 404);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'content_format' => 'nullable|in:plain,markdown,html',
                'estimated_minutes' => 'required|integer|min:5|max:480',
                'required' => 'boolean',
            ]);

            // Process rich content if provided
            $contentFormat = $validated['content_format'] ?? 'plain';
            $description = $validated['description'] ?? null;
            $contentMetadata = null;

            if (! empty($description) && $contentFormat !== 'plain') {
                $richContent = $this->richContentService->processRichContent($description, $contentFormat);
                $contentMetadata = $richContent['metadata'];
            }

            // Use 'name' field but store as 'title' in the model
            $topic = Topic::create([
                'unit_id' => $unitId,
                'title' => $validated['title'], // Store title as title
                'description' => $description,
                'content_format' => $contentFormat,
                'content_metadata' => $contentMetadata,
                'estimated_minutes' => $validated['estimated_minutes'],
                'required' => $validated['required'] ?? true,
                'prerequisites' => [], // Empty for now
                'learning_materials' => null,
            ]);

            if ($request->header('HX-Request')) {
                // Return updated topics list
                $topics = Topic::forUnit($unitId);

                return view('topics.partials.topics-list', compact('topics', 'unit', 'subject'));
            }

            return redirect()->route('subjects.units.show', [$subjectId, $unitId])->with('success', 'Topic created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error creating topic. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to create topic. Please try again.']);
        }
    }

    /**
     * Display the specified topic.
     */
    public function show(Request $request, int $unitId, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return redirect()->route('login');
            }

            $unit = Unit::find($unitId);
            if (! $unit) {
                return redirect()->route('subjects.index')->with('error', 'Unit not found.');
            }

            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return redirect()->route('subjects.index')->with('error', 'Access denied.');
            }

            $topic = Topic::find($id);
            if (! $topic || $topic->unit_id !== $unitId) {
                return redirect()->route('subjects.units.show', [$subject->id, $unitId])->with('error', 'Topic not found.');
            }

            if ($request->header('HX-Request')) {
                return view('topics.partials.topic-details', compact('topic', 'unit', 'subject'));
            }

            return view('topics.show', compact('topic', 'unit', 'subject'));
        } catch (\Exception $e) {
            Log::error('Error fetching topic: '.$e->getMessage());

            return redirect()->route('subjects.index')->with('error', 'Unable to load topic. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified topic.
     */
    public function edit(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            $unit = Unit::find($topic->unit_id);
            if (! $unit) {
                return response('Unit not found', 404);
            }

            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            if ($request->header('HX-Request')) {
                return view('topics.partials.edit-form', compact('topic', 'unit', 'subject'));
            }

            return view('topics.edit', compact('topic', 'unit', 'subject'));
        } catch (\Exception $e) {
            Log::error('Error loading topic for edit: '.$e->getMessage());

            return response('Unable to load topic for editing.', 500);
        }
    }

    /**
     * Update the specified topic in storage.
     */
    public function update(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            $unit = Unit::find($topic->unit_id);
            if (! $unit) {
                return response('Unit not found', 404);
            }

            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'content_format' => 'nullable|in:plain,markdown,html',
                'estimated_minutes' => 'required|integer|min:5|max:480',
                'required' => 'boolean',
            ]);

            // Process rich content if provided
            $contentFormat = $validated['content_format'] ?? $topic->content_format ?? 'plain';
            $description = $validated['description'] ?? null;
            $contentMetadata = $topic->content_metadata;

            if (! empty($description) && $contentFormat !== 'plain') {
                $richContent = $this->richContentService->processRichContent($description, $contentFormat);
                $contentMetadata = $richContent['metadata'];
            }

            // Use 'name' field but store as 'title' in the model
            $topic->update([
                'title' => $validated['title'], // Store title as title
                'description' => $description,
                'content_format' => $contentFormat,
                'content_metadata' => $contentMetadata,
                'estimated_minutes' => $validated['estimated_minutes'],
                'required' => $validated['required'] ?? true,
            ]);

            if ($request->header('HX-Request')) {
                // Return updated topics list
                $topics = Topic::forUnit($unit->id);

                return view('topics.partials.topics-list', compact('topics', 'unit', 'subject'));
            }

            return redirect()->route('subjects.units.show', [$subject->id, $unit->id])->with('success', 'Topic updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error updating topic. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to update topic. Please try again.']);
        }
    }

    /**
     * Remove the specified topic from storage.
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            $unit = Unit::find($topic->unit_id);
            if (! $unit) {
                return response('Unit not found', 404);
            }

            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            // TODO: Check if topic has sessions - prevent deletion if it has active sessions
            // For now, allow deletion

            // Clean up any uploaded files
            if ($topic->hasLearningMaterials()) {
                $this->materialService->cleanupTopicFiles($topic);
            }

            $topic->delete();

            if ($request->header('HX-Request')) {
                // Return updated topics list
                $topics = Topic::forUnit($unit->id);

                return view('topics.partials.topics-list', compact('topics', 'unit', 'subject'));
            }

            return redirect()->route('subjects.units.show', [$subject->id, $unit->id])->with('success', 'Topic deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.__('Error deleting topic. Please try again.').'</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to delete topic. Please try again.']);
        }
    }

    /**
     * Add a video to a topic
     */
    public function addVideo(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            $validated = $request->validate([
                'video_url' => 'required|url',
                'video_title' => 'nullable|string|max:255',
                'video_description' => 'nullable|string|max:1000',
            ]);

            $videoData = $this->materialService->processVideoUrl(
                $validated['video_url'],
                $validated['video_title'],
                $validated['video_description']
            );

            $topic->addMaterial('videos', $videoData);

            if ($request->header('HX-Request')) {
                return view('topics.partials.materials-section', compact('topic'));
            }

            return back()->with('success', 'Video added successfully.');

        } catch (\InvalidArgumentException $e) {
            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.$e->getMessage().'</div>', 400);
            }

            return back()->withErrors(['error' => $e->getMessage()]);

        } catch (\Exception $e) {
            Log::error('Error adding video to topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">Error adding video. Please try again.</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to add video. Please try again.']);
        }
    }

    /**
     * Add a link to a topic
     */
    public function addLink(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            $validated = $request->validate([
                'link_url' => 'required|url',
                'link_title' => 'nullable|string|max:255',
                'link_description' => 'nullable|string|max:1000',
            ]);

            $linkData = $this->materialService->processLink(
                $validated['link_url'],
                $validated['link_title'],
                $validated['link_description']
            );

            $topic->addMaterial('links', $linkData);

            if ($request->header('HX-Request')) {
                return view('topics.partials.materials-section', compact('topic'));
            }

            return back()->with('success', 'Link added successfully.');

        } catch (\InvalidArgumentException $e) {
            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.$e->getMessage().'</div>', 400);
            }

            return back()->withErrors(['error' => $e->getMessage()]);

        } catch (\Exception $e) {
            Log::error('Error adding link to topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">Error adding link. Please try again.</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to add link. Please try again.']);
        }
    }

    /**
     * Upload a file to a topic
     */
    public function uploadFile(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'file_title' => 'nullable|string|max:255',
            ]);

            $fileData = $this->materialService->uploadFile(
                $topic,
                $validated['file'],
                $validated['file_title']
            );

            $topic->addMaterial('files', $fileData);

            if ($request->header('HX-Request')) {
                return view('topics.partials.materials-section', compact('topic'));
            }

            return back()->with('success', 'File uploaded successfully.');

        } catch (\InvalidArgumentException $e) {
            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">'.$e->getMessage().'</div>', 400);
            }

            return back()->withErrors(['error' => $e->getMessage()]);

        } catch (\Exception $e) {
            Log::error('Error uploading file to topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">Error uploading file. Please try again.</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to upload file. Please try again.']);
        }
    }

    /**
     * Remove a material from a topic
     */
    public function removeMaterial(Request $request, int $id, string $type, int $index)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response('Unauthorized', 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response('Topic not found', 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response('Access denied', 403);
            }

            // If it's a file, delete from storage
            if ($type === 'files') {
                $files = $topic->getFiles();
                if (isset($files[$index])) {
                    $this->materialService->deleteFile($files[$index]);
                }
            }

            $topic->removeMaterial($type, $index);

            if ($request->header('HX-Request')) {
                return view('topics.partials.materials-section', compact('topic'));
            }

            return back()->with('success', 'Material removed successfully.');

        } catch (\Exception $e) {
            Log::error('Error removing material from topic: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">Error removing material. Please try again.</div>', 500);
            }

            return back()->withErrors(['error' => 'Unable to remove material. Please try again.']);
        }
    }

    /**
     * Upload image for rich content
     */
    public function uploadContentImage(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $validated = $request->validate([
                'image' => 'required|image|max:5120', // 5MB max
                'alt_text' => 'nullable|string|max:255',
            ]);

            $imageData = $this->richContentService->uploadContentImage(
                $topic->id,
                $validated['image'],
                $validated['alt_text']
            );

            // Add to topic's embedded images
            $embeddedImages = $topic->embedded_images ?? [];
            $embeddedImages[] = $imageData;

            $topic->update(['embedded_images' => $embeddedImages]);

            // Generate markdown reference
            $markdown = $this->richContentService->generateImageMarkdown($imageData);

            return response()->json([
                'success' => true,
                'image' => $imageData,
                'markdown' => $markdown,
                'message' => 'Image uploaded successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);

        } catch (\Exception $e) {
            Log::error('Error uploading content image: '.$e->getMessage());

            return response()->json(['error' => 'Unable to upload image. Please try again.'], 500);
        }
    }

    /**
     * Get all content images for a topic
     */
    public function getContentImages(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $images = $topic->embedded_images ?? [];

            return response()->json([
                'success' => true,
                'images' => $images,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting content images: '.$e->getMessage());

            return response()->json(['error' => 'Unable to get images.'], 500);
        }
    }

    /**
     * Delete content image
     */
    public function deleteContentImage(Request $request, int $id, int $imageIndex)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $embeddedImages = $topic->embedded_images ?? [];

            if (! isset($embeddedImages[$imageIndex])) {
                return response()->json(['error' => 'Image not found'], 404);
            }

            // Delete file from storage
            $imageData = $embeddedImages[$imageIndex];
            if (isset($imageData['path'])) {
                Storage::disk('public')->delete($imageData['path']);
                if (isset($imageData['thumbnail_path'])) {
                    Storage::disk('public')->delete($imageData['thumbnail_path']);
                }
            }

            // Remove from array
            unset($embeddedImages[$imageIndex]);
            $embeddedImages = array_values($embeddedImages); // Reindex

            $topic->update(['embedded_images' => $embeddedImages]);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting content image: '.$e->getMessage());

            return response()->json(['error' => 'Unable to delete image.'], 500);
        }
    }

    /**
     * Preview rich content rendering with enhanced features
     */
    public function previewContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'format' => 'required|in:plain,markdown,html',
            ]);

            // Use enhanced processing for markdown content
            if ($validated['format'] === 'markdown') {
                $result = $this->richContentService->processUnifiedContent($validated['content']);
            } else {
                $result = $this->richContentService->processRichContent(
                    $validated['content'],
                    $validated['format']
                );
            }

            if ($request->header('HX-Request')) {
                return view('topics.partials.content-preview', [
                    'html' => $result['html'],
                    'metadata' => $result['metadata'],
                ]);
            }

            return response()->json([
                'success' => true,
                'html' => $result['html'],
                'metadata' => $result['metadata'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error previewing content: '.$e->getMessage());

            if ($request->header('HX-Request')) {
                return response('<div class="text-red-500">Error previewing content.</div>', 500);
            }

            return response()->json(['error' => 'Unable to preview content.'], 500);
        }
    }

    /**
     * Enhanced real-time preview for unified markdown editor
     */
    public function previewUnifiedContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'cache_key' => 'sometimes|string|max:50',
                'performance_mode' => 'sometimes|in:fast,auto,quality',
            ]);

            // Set performance optimizations based on content length and mode
            $contentLength = strlen($validated['content']);
            $performanceMode = $validated['performance_mode'] ?? $this->detectPerformanceMode($contentLength);

            // Generate cache key if not provided
            $cacheKey = $validated['cache_key'] ?? md5($validated['content']);

            // Try cache first for performance
            if ($performanceMode === 'fast' && Cache::has("preview_cache_{$cacheKey}")) {
                $result = Cache::get("preview_cache_{$cacheKey}");
            } else {
                // Process with enhanced markdown rendering
                $result = $this->richContentService->processUnifiedContent($validated['content']);

                // Cache result for performance (shorter TTL for fast mode)
                $ttl = $performanceMode === 'fast' ? 300 : 60; // 5 minutes for fast mode, 1 minute otherwise
                Cache::put("preview_cache_{$cacheKey}", $result, $ttl);
            }

            // Add performance metadata
            $result['metadata']['performance_mode'] = $performanceMode;
            $result['metadata']['content_length'] = $contentLength;
            $result['metadata']['cache_hit'] = isset($validated['cache_key']) && Cache::has("preview_cache_{$cacheKey}");
            $result['metadata']['cache_key'] = $cacheKey;

            // Return HTML directly for unified editor
            return response($result['html'])
                ->header('X-Performance-Mode', $performanceMode)
                ->header('X-Cache-Key', $cacheKey)
                ->header('X-Content-Length', $contentLength)
                ->header('Content-Type', 'text/html; charset=utf-8');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response('<div class="text-red-500">Invalid content format</div>', 422);
        } catch (\Exception $e) {
            Log::error('Error previewing unified content: '.$e->getMessage());

            return response('<div class="text-red-500">Preview temporarily unavailable</div>', 500);
        }
    }

    /**
     * Export content in different formats
     */
    public function exportContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'from_format' => 'required|in:plain,markdown,html',
                'to_format' => 'required|in:plain,markdown,html',
            ]);

            $result = $this->richContentService->convertContentFormat(
                $validated['content'],
                $validated['from_format'],
                $validated['to_format']
            );

            return response($result)
                ->header('Content-Type', $this->getContentTypeForFormat($validated['to_format']));

        } catch (\Exception $e) {
            Log::error('Error exporting content: '.$e->getMessage());

            return response()->json(['error' => 'Export failed'], 500);
        }
    }

    /**
     * Get video metadata for enhanced preview
     */
    public function getVideoMetadata(Request $request)
    {
        try {
            $validated = $request->validate([
                'url' => 'required|url',
            ]);

            $metadata = $this->richContentService->validateVideoUrl($validated['url']);

            return response()->json($metadata);

        } catch (\Exception $e) {
            Log::error('Error getting video metadata: '.$e->getMessage());

            return response()->json(['valid' => false, 'error' => 'Could not fetch video metadata'], 500);
        }
    }

    /**
     * Detect optimal performance mode based on content length
     */
    private function detectPerformanceMode(int $contentLength): string
    {
        if ($contentLength > 10000) {
            return 'fast';
        } elseif ($contentLength > 5000) {
            return 'auto';
        } else {
            return 'quality';
        }
    }

    /**
     * Get content type for export format
     */
    private function getContentTypeForFormat(string $format): string
    {
        return match ($format) {
            'html' => 'text/html; charset=utf-8',
            'markdown' => 'text/markdown; charset=utf-8',
            'plain' => 'text/plain; charset=utf-8',
            default => 'text/plain; charset=utf-8',
        };
    }

    /**
     * Enhanced markdown editor file upload with progress tracking
     */
    public function markdownFileUpload(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'type' => 'required|in:image,document,video,audio',
            ]);

            $file = $validated['file'];
            $fileType = $validated['type'];

            // Validate file type based on category
            $this->validateFileByType($file, $fileType);

            // Generate unique filename for unified content storage
            $filename = $this->generateUnifiedContentFilename($topic->id, $file);

            // Store file in unified content directory
            $path = $file->storeAs("topics/{$topic->id}/unified-content", $filename, 'public');
            $url = Storage::url($path);

            // Update content assets tracking
            $contentAssets = $topic->getContentAssets();

            $assetData = [
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => $url,
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'category' => $fileType,
                'uploaded_at' => now()->toISOString(),
                'referenced_in_content' => false, // Will be updated when content is saved
            ];

            if ($fileType === 'image') {
                $contentAssets['images'][] = $assetData;
            } else {
                $contentAssets['files'][] = $assetData;
            }

            $topic->update(['content_assets' => $contentAssets]);

            // Generate markdown based on file type
            $markdown = $this->generateMarkdownForFile($assetData, $fileType);

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'url' => $url,
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'category' => $fileType,
                'markdown' => $markdown,
                'message' => 'File uploaded successfully',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading markdown file: '.$e->getMessage());

            return response()->json([
                'error' => 'Upload failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate file type based on category
     */
    private function validateFileByType($file, string $type): void
    {
        $mimeType = $file->getMimeType();

        switch ($type) {
            case 'image':
                if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'])) {
                    throw new \InvalidArgumentException('Invalid image format. Allowed: JPEG, PNG, GIF, WebP, SVG');
                }
                break;
            case 'document':
                if (! in_array($mimeType, [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                    'text/csv',
                ])) {
                    throw new \InvalidArgumentException('Invalid document format. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV');
                }
                break;
            case 'video':
                if (! str_starts_with($mimeType, 'video/')) {
                    throw new \InvalidArgumentException('Invalid video format');
                }
                break;
            case 'audio':
                if (! str_starts_with($mimeType, 'audio/')) {
                    throw new \InvalidArgumentException('Invalid audio format');
                }
                break;
        }
    }

    /**
     * Generate unique filename for unified content
     */
    private function generateUnifiedContentFilename(int $topicId, $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $random = substr(md5(uniqid()), 0, 8);

        return "topic_{$topicId}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Generate appropriate markdown for uploaded file
     */
    private function generateMarkdownForFile(array $assetData, string $fileType): string
    {
        $filename = $assetData['original_name'];
        $url = $assetData['url'];

        switch ($fileType) {
            case 'image':
                return "![{$filename}]({$url})";
            case 'video':
                return "<video controls>\n  <source src=\"{$url}\" type=\"{$assetData['type']}\">\n  Your browser does not support the video tag.\n</video>";
            case 'audio':
                return "<audio controls>\n  <source src=\"{$url}\" type=\"{$assetData['type']}\">\n  Your browser does not support the audio tag.\n</audio>";
            default:
                return "[{$filename}]({$url})";
        }
    }

    /**
     * Start chunked upload session for large files - Phase 5 enhanced file handling
     */
    public function startChunkedUpload(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            // Verify ownership
            $unit = Unit::find($topic->unit_id);
            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $validated = $request->validate([
                'filename' => 'required|string|max:255',
                'size' => 'required|integer|min:1|max:104857600', // 100MB max
                'type' => 'required|string',
                'chunks' => 'required|integer|min:1',
            ]);

            // Create chunked upload session
            $sessionId = uniqid('chunk_session_', true);
            $sessionDir = storage_path("app/temp/chunks/{$sessionId}");

            if (! file_exists($sessionDir)) {
                mkdir($sessionDir, 0755, true);
            }

            // Store session metadata
            $sessionData = [
                'session_id' => $sessionId,
                'filename' => $validated['filename'],
                'total_size' => $validated['size'],
                'file_type' => $validated['type'],
                'total_chunks' => $validated['chunks'],
                'uploaded_chunks' => [],
                'topic_id' => $id,
                'user_id' => $userId,
                'created_at' => now()->toISOString(),
                'expires_at' => now()->addHours(24)->toISOString(),
            ];

            file_put_contents(
                storage_path("app/temp/chunks/{$sessionId}/session.json"),
                json_encode($sessionData)
            );

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'message' => 'Chunked upload session created',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error starting chunked upload: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to start chunked upload: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload individual chunk
     */
    public function uploadChunk(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'chunk' => 'required|file',
                'session_id' => 'required|string',
                'chunk_index' => 'required|integer|min:0',
            ]);

            // Validate session_id to prevent path traversal - only allow alphanumeric, underscore, and dash
            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $validated['session_id'])) {
                return response()->json(['error' => 'Invalid session ID format'], 400);
            }
            $sessionId = $validated['session_id'];
            $chunkIndex = $validated['chunk_index'];
            $chunkFile = $validated['chunk'];

            // Load session data
            $sessionPath = storage_path("app/temp/chunks/{$sessionId}/session.json");
            if (! file_exists($sessionPath)) {
                return response()->json(['error' => 'Upload session not found'], 404);
            }

            $sessionData = json_decode(file_get_contents($sessionPath), true);

            // Verify session ownership and topic
            if ($sessionData['user_id'] != $userId || $sessionData['topic_id'] != $id) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Check if session expired
            if (now() > Carbon::parse($sessionData['expires_at'])) {
                return response()->json(['error' => 'Upload session expired'], 410);
            }

            // Store chunk
            $chunkPath = storage_path("app/temp/chunks/{$sessionId}/chunk_{$chunkIndex}");
            $chunkFile->move(dirname($chunkPath), basename($chunkPath));

            // Update session data
            $sessionData['uploaded_chunks'][] = $chunkIndex;
            $sessionData['uploaded_chunks'] = array_unique($sessionData['uploaded_chunks']);
            sort($sessionData['uploaded_chunks']);

            file_put_contents($sessionPath, json_encode($sessionData));

            return response()->json([
                'success' => true,
                'chunk_index' => $chunkIndex,
                'uploaded_chunks' => count($sessionData['uploaded_chunks']),
                'total_chunks' => $sessionData['total_chunks'],
                'message' => "Chunk {$chunkIndex} uploaded successfully",
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading chunk: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to upload chunk: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalize chunked upload and assemble file
     */
    public function finalizeChunkedUpload(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            $validated = $request->validate([
                'session_id' => 'required|string',
            ]);

            // Validate session_id to prevent path traversal - only allow alphanumeric, underscore, and dash
            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $validated['session_id'])) {
                return response()->json(['error' => 'Invalid session ID format'], 400);
            }
            $sessionId = $validated['session_id'];

            // Load session data
            $sessionPath = storage_path("app/temp/chunks/{$sessionId}/session.json");
            if (! file_exists($sessionPath)) {
                return response()->json(['error' => 'Upload session not found'], 404);
            }

            $sessionData = json_decode(file_get_contents($sessionPath), true);

            // Verify session ownership and topic
            if ($sessionData['user_id'] != $userId || $sessionData['topic_id'] != $id) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Check if all chunks are uploaded
            if (count($sessionData['uploaded_chunks']) !== $sessionData['total_chunks']) {
                return response()->json([
                    'error' => 'Not all chunks uploaded',
                    'uploaded' => count($sessionData['uploaded_chunks']),
                    'expected' => $sessionData['total_chunks'],
                ], 400);
            }

            // Assemble file from chunks using memory-efficient streaming
            $assembledFilePath = storage_path("app/temp/assembled_{$sessionId}");
            $assembledFile = fopen($assembledFilePath, 'wb');
            $bufferSize = 8192; // 8KB buffer for optimal memory usage

            try {
                for ($i = 0; $i < $sessionData['total_chunks']; $i++) {
                    $chunkPath = storage_path("app/temp/chunks/{$sessionId}/chunk_{$i}");
                    if (! file_exists($chunkPath)) {
                        throw new \Exception("Missing chunk {$i}");
                    }

                    // Stream chunk data instead of loading into memory
                    $chunkFile = fopen($chunkPath, 'rb');
                    if ($chunkFile === false) {
                        throw new \Exception("Cannot open chunk {$i}");
                    }

                    try {
                        // Read and write chunk in small buffers to minimize memory usage
                        while (! feof($chunkFile)) {
                            $buffer = fread($chunkFile, $bufferSize);
                            if ($buffer === false) {
                                throw new \Exception("Error reading chunk {$i}");
                            }
                            if (strlen($buffer) > 0) {
                                $written = fwrite($assembledFile, $buffer);
                                if ($written === false) {
                                    throw new \Exception('Error writing assembled file');
                                }
                            }
                        }
                    } finally {
                        fclose($chunkFile);
                    }
                }
            } catch (\Exception $e) {
                fclose($assembledFile);
                if (file_exists($assembledFilePath)) {
                    unlink($assembledFilePath);
                }

                return response()->json(['error' => $e->getMessage()], 400);
            }

            fclose($assembledFile);

            // Create UploadedFile object from assembled file
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $assembledFilePath,
                $sessionData['filename'],
                $sessionData['file_type'],
                null,
                true
            );

            // Detect file type
            $fileType = $this->detectFileType($uploadedFile);

            // Validate file type
            $this->validateFileByType($uploadedFile, $fileType);

            // Generate unique filename
            $filename = $this->generateUnifiedContentFilename($topic->id, $uploadedFile);

            // Store file
            $path = $uploadedFile->storeAs("topics/{$topic->id}/unified-content", $filename, 'public');
            $url = Storage::url($path);

            // Update content assets
            $contentAssets = $topic->getContentAssets();

            $assetData = [
                'filename' => $filename,
                'original_name' => $sessionData['filename'],
                'path' => $path,
                'url' => $url,
                'size' => $sessionData['total_size'],
                'type' => $sessionData['file_type'],
                'category' => $fileType,
                'uploaded_at' => now()->toISOString(),
                'referenced_in_content' => false,
                'chunked_upload' => true,
            ];

            if ($fileType === 'image') {
                $contentAssets['images'][] = $assetData;
            } else {
                $contentAssets['files'][] = $assetData;
            }

            $topic->update(['content_assets' => $contentAssets]);

            // Generate markdown
            $markdown = $this->generateMarkdownForFile($assetData, $fileType);

            // Cleanup session files
            $this->cleanupChunkedUploadSession($sessionId);

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'original_name' => $sessionData['filename'],
                'url' => $url,
                'size' => $sessionData['total_size'],
                'type' => $sessionData['file_type'],
                'category' => $fileType,
                'markdown' => $markdown,
                'message' => 'Chunked upload completed successfully',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error finalizing chunked upload: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to finalize chunked upload: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cleanup chunked upload session files
     */
    private function cleanupChunkedUploadSession(string $sessionId)
    {
        try {
            // Validate session_id to prevent path traversal - only allow alphanumeric, underscore, and dash
            if (! preg_match('/^[a-zA-Z0-9_-]+$/', $sessionId)) {
                Log::warning('Invalid session ID format in cleanup: '.$sessionId);

                return; // Silently return for invalid session IDs in cleanup
            }
            $sessionDir = storage_path("app/temp/chunks/{$sessionId}");
            $assembledFile = storage_path("app/temp/assembled_{$sessionId}");

            // Remove assembled file
            if (file_exists($assembledFile)) {
                unlink($assembledFile);
            }

            // Remove session directory and all chunks
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir.'/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($sessionDir);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup chunked upload session: '.$e->getMessage());
        }
    }

    /**
     * Show topic in kids-friendly view
     */
    public function showKidsView(Request $request, int $unitId, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return redirect()->route('login');
            }

            // Check if kids mode is active
            $isKidsMode = $request->session()->get('kids_mode_active', false);
            $childId = $request->session()->get('kids_mode_child_id');

            if (! $isKidsMode || ! $childId) {
                return redirect()->route('topics.show', [$unitId, $id])
                    ->with('error', 'Kids mode is not active.');
            }

            $unit = Unit::find($unitId);
            if (! $unit) {
                return redirect()->route('subjects.index')->with('error', 'Unit not found.');
            }

            $subject = Subject::find($unit->subject_id);
            if (! $subject || $subject->user_id != $userId) {
                return redirect()->route('subjects.index')->with('error', 'Access denied.');
            }

            $topic = Topic::find($id);
            if (! $topic || $topic->unit_id !== $unitId) {
                return redirect()->route('subjects.units.show', [$subject->id, $unitId])
                    ->with('error', 'Topic not found.');
            }

            // Get the child
            $child = Child::find($childId);
            if (! $child || $child->user_id !== $userId) {
                return redirect()->route('topics.show', [$unitId, $id])
                    ->with('error', 'Child not found.');
            }

            // Render kids view with enhanced content
            return view('topics.partials.unified-content-kids-view',
                compact('topic', 'unit', 'subject', 'child'));

        } catch (\Exception $e) {
            Log::error('Error loading kids topic view: '.$e->getMessage());

            return redirect()->route('topics.show', [$unitId, $id])
                ->with('error', 'Unable to load kids view. Please try again.');
        }
    }

    /**
     * Track kids learning activity
     */
    public function trackKidsActivity(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Verify kids mode
            $isKidsMode = $request->session()->get('kids_mode_active', false);
            $childId = $request->session()->get('kids_mode_child_id');

            if (! $isKidsMode || ! $childId) {
                return response()->json(['error' => 'Kids mode not active'], 403);
            }

            $child = Child::find($childId);
            if (! $child || $child->user_id !== $userId) {
                return response()->json(['error' => 'Child not found'], 404);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            $validated = $request->validate([
                'activity_type' => 'required|string|in:read_paragraph,complete_task,highlight_text,use_feature,complete_topic,focus_time',
                'data' => 'nullable|array',
                'data.reading_progress' => 'nullable|numeric|min:0|max:100',
                'data.interactions' => 'nullable|integer|min:0',
                'data.time_spent' => 'nullable|integer|min:0',
                'data.tasks_completed' => 'nullable|integer|min:0',
                'data.accuracy' => 'nullable|numeric|min:0|max:100',
            ]);

            // Track activity using gamification service
            $gamificationService = app(KidsGamificationService::class);
            $result = $gamificationService->trackActivity(
                $child,
                $validated['activity_type'],
                $validated['data'] ?? []
            );

            return response()->json([
                'success' => true,
                'result' => $result,
                'child_level' => $gamificationService->getChildLevel($child),
                'encouragement' => $gamificationService->getEncouragementMessages($child),
            ]);

        } catch (\Exception $e) {
            Log::error('Error tracking kids activity: '.$e->getMessage());

            return response()->json(['error' => 'Unable to track activity'], 500);
        }
    }

    /**
     * Complete topic for child
     */
    public function completeForChild(Request $request, int $id)
    {
        try {
            $userId = auth()->id();
            if (! $userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Verify kids mode
            $isKidsMode = $request->session()->get('kids_mode_active', false);
            $childId = $request->session()->get('kids_mode_child_id');

            if (! $isKidsMode || ! $childId) {
                return response()->json(['error' => 'Kids mode not active'], 403);
            }

            $child = Child::find($childId);
            if (! $child || $child->user_id !== $userId) {
                return response()->json(['error' => 'Child not found'], 404);
            }

            $topic = Topic::find($id);
            if (! $topic) {
                return response()->json(['error' => 'Topic not found'], 404);
            }

            $validated = $request->validate([
                'reading_progress' => 'required|numeric|min:0|max:100',
                'interaction_score' => 'required|numeric|min:0|max:100',
                'time_spent' => 'required|integer|min:0',
                'tasks_completed' => 'nullable|integer|min:0',
            ]);

            // Generate session summary
            $gamificationService = app(KidsGamificationService::class);
            $sessionSummary = $gamificationService->generateSessionSummary($child, $validated);

            // Track completion activity
            $result = $gamificationService->trackActivity($child, 'complete_topic', $validated);

            // Log completion for parental tracking
            Log::info('Child completed topic', [
                'child_id' => $child->id,
                'topic_id' => $topic->id,
                'session_data' => $validated,
                'summary' => $sessionSummary,
            ]);

            return response()->json([
                'success' => true,
                'message' => '🎉 Great job completing this topic!',
                'session_summary' => $sessionSummary,
                'achievements' => $result['new_achievements'],
                'total_points' => $result['total_points'],
                'current_level' => $result['current_level'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing topic for child: '.$e->getMessage());

            return response()->json(['error' => 'Unable to complete topic'], 500);
        }
    }

    /**
     * Detect file type for enhanced processing
     */
    private function detectFileType(\Illuminate\Http\UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'document';
        }
    }
}
