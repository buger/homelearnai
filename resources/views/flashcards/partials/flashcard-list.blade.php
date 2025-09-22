<!-- Import Result Message -->
@if(isset($import_result))
    <div class="mb-6 p-4 rounded-lg {{ $import_result['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
        <div class="flex items-center">
            @if($import_result['success'])
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-green-900">Import Successful!</h4>
                    <p class="text-sm text-green-700">
                        Successfully imported {{ $import_result['imported'] }} out of {{ $import_result['total'] }} flashcard(s).
                        @if($import_result['failed'] > 0)
                            {{ $import_result['failed'] }} card(s) failed to import.
                        @endif
                    </p>
                    @if(!empty($import_result['errors']))
                        <details class="mt-2">
                            <summary class="text-sm text-green-600 cursor-pointer hover:text-green-700">Show errors</summary>
                            <ul class="list-disc list-inside text-xs text-green-600 mt-1 space-y-1">
                                @foreach($import_result['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @else
                <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-red-900">Import Failed</h4>
                    <p class="text-sm text-red-700">{{ $import_result['error'] ?? 'Unknown error occurred during import.' }}</p>
                </div>
            @endif
        </div>
    </div>
@endif

@if($flashcards->count() > 0)
    <!-- Action Bar -->
    @unless(session('kids_mode'))
        <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="text-sm text-gray-700">
                <strong>{{ $flashcards->total() ?? $flashcards->count() }}</strong> flashcard{{ ($flashcards->total() ?? $flashcards->count()) !== 1 ? 's' : '' }}
                @if(isset($topic))
                    in {{ $topic->title }}
                @else
                    in this unit
                @endif
            </div>
            <div class="flex space-x-2">
                @if(isset($topic))
                    <!-- Topic-specific actions -->
                    <button
                        type="button"
                        hx-get="{{ route('topics.flashcards.create', $topic->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-opacity"
                        style="background-color: {{ $unit->subject->color }}">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Flashcard to Topic
                    </button>
                @else
                    <!-- Unit-level actions -->
                    <button
                        type="button"
                        hx-get="{{ route('flashcards.print.options', $unit->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print Flashcards
                    </button>
                    <button
                        type="button"
                        hx-get="{{ route('flashcards.export.options', $unit->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-4-4m4 4l4-4m-4-4V3"/>
                        </svg>
                        Export
                    </button>
                    {{-- Import functionality not implemented yet
                    <button
                        type="button"
                        hx-get="{{ route('flashcards.import', $unit->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Import
                    </button>
                    --}}
                    <button
                        type="button"
                        {{-- hx-get="{{ route('units.flashcards.create', $unit->id) }}" --}}
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-opacity"
                        style="background-color: {{ $unit->subject->color }}">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Flashcard
                    </button>
                @endif
            </div>
        </div>
    @endunless

    <div class="space-y-4">
        @foreach($flashcards as $flashcard)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <h4 class="text-sm font-medium text-gray-900 truncate max-w-md">
                                {{ Str::limit($flashcard->question, 100) }}
                            </h4>

                            @if(isset($flashcard->topic) && !isset($topic))
                                <!-- Show topic label when viewing unit-level but flashcard belongs to topic -->
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $flashcard->topic->title }}
                                </span>
                            @endif
                            
                            <!-- Card Type Badge -->
                            @php
                                $typeColors = [
                                    'basic' => 'bg-blue-100 text-blue-800',
                                    'multiple_choice' => 'bg-green-100 text-green-800', 
                                    'true_false' => 'bg-yellow-100 text-yellow-800',
                                    'cloze' => 'bg-purple-100 text-purple-800',
                                    'typed_answer' => 'bg-indigo-100 text-indigo-800',
                                    'image_occlusion' => 'bg-pink-100 text-pink-800'
                                ];
                                $typeLabels = [
                                    'basic' => 'Basic',
                                    'multiple_choice' => 'Multiple Choice',
                                    'true_false' => 'True/False', 
                                    'cloze' => 'Cloze',
                                    'typed_answer' => 'Typed Answer',
                                    'image_occlusion' => 'Image Occlusion'
                                ];
                            @endphp
                            
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColors[$flashcard->card_type] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $typeLabels[$flashcard->card_type] ?? ucfirst(str_replace('_', ' ', $flashcard->card_type)) }}
                            </span>
                            
                            <!-- Difficulty Badge -->
                            @php
                                $difficultyColors = [
                                    'easy' => 'bg-green-100 text-green-800',
                                    'medium' => 'bg-yellow-100 text-yellow-800',
                                    'hard' => 'bg-red-100 text-red-800'
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $difficultyColors[$flashcard->difficulty_level] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($flashcard->difficulty_level) }}
                            </span>
                        </div>
                        
                        <div class="text-sm text-gray-600 space-y-1">
                            <p class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Answer: {{ Str::limit($flashcard->answer, 80) }}
                            </p>
                            
                            @if($flashcard->hint)
                                <p class="flex items-center text-gray-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    Hint: {{ Str::limit($flashcard->hint, 60) }}
                                </p>
                            @endif
                            
                            @if(!empty($flashcard->tags))
                                <div class="flex items-center flex-wrap gap-1 mt-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    @foreach($flashcard->tags as $tag)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Actions (only show in parent mode) -->
                    @unless(session('kids_mode'))
                        <div class="flex items-center space-x-2">
                            <button
                                data-testid="edit-flashcard-button"
                                @if(isset($topic) && $flashcard->topic_id)
                                    hx-get="{{ route('topics.flashcards.edit', [$flashcard->topic_id, $flashcard->id]) }}"
                                @else
                                    hx-get="{{ route('units.flashcards.edit', [$unit->id, $flashcard->id]) }}"
                                @endif
                                hx-target="#flashcard-modal"
                                hx-swap="innerHTML"
                                class="text-gray-400 hover:text-gray-600 p-1"
                                title="Edit flashcard">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button
                                data-testid="delete-flashcard-button"
                                @if(isset($topic) && $flashcard->topic_id)
                                    hx-delete="{{ route('topics.flashcards.destroy', [$flashcard->topic_id, $flashcard->id]) }}"
                                @else
                                    hx-delete="{{ route('units.flashcards.destroy', [$unit->id, $flashcard->id]) }}"
                                @endif
                                hx-target="#flashcards-list"
                                hx-swap="innerHTML"
                                hx-confirm="Are you sure you want to delete this flashcard? This action cannot be undone."
                                class="text-red-400 hover:text-red-600 p-1"
                                title="Delete flashcard">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    @if($flashcards->hasPages())
        <div class="mt-6">
            {{ $flashcards->links('pagination::tailwind') }}
        </div>
    @endif
@else
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No flashcards yet</h3>
        <p class="mt-1 text-sm text-gray-500">
            @if(isset($topic))
                Get started by creating your first flashcard for this topic.
            @else
                Get started by creating your first flashcard for this unit.
            @endif
        </p>
        @unless(session('kids_mode'))
            <div class="mt-6 flex flex-wrap gap-3 justify-center">
                @if(isset($topic))
                    <button
                        type="button"
                        hx-get="{{ route('topics.flashcards.create', $topic->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:opacity-90 transition-opacity"
                        style="background-color: {{ $unit->subject->color }}">
                        Add Flashcard to Topic
                    </button>
                @else
                    {{-- Import functionality not implemented yet
                    <button
                        type="button"
                        hx-get="{{ route('flashcards.import', $unit->id) }}"
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Import Flashcards
                    </button>
                    --}}
                    <button
                        type="button"
                        {{-- hx-get="{{ route('units.flashcards.create', $unit->id) }}" --}}
                        hx-target="#flashcard-modal"
                        hx-swap="innerHTML"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:opacity-90 transition-opacity"
                        style="background-color: {{ $unit->subject->color }}">
                        Add Flashcard
                    </button>
                @endif
            </div>
        @endunless
    </div>
@endif