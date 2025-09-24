@if($subjects->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($subjects as $subject)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <!-- Subject Color Badge -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $subject->color }}"></div>
                        <h3 class="text-lg font-semibold text-gray-900 truncate">
                            <a href="{{ route('subjects.show', $subject->id) }}" class="hover:text-blue-600 transition-colors block truncate">
                                {{ $subject->name }}
                            </a>
                        </h3>
                    </div>
                    
                    <!-- Actions Dropdown -->
                    <div class="relative inline-block text-left" x-data="{ open: false }">
                        <button @click="open = !open" type="button" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                            <div class="py-1">
                                <button 
                                    hx-get="{{ route('subjects.edit', $subject->id) }}"
                                    hx-target="#subject-modal"
                                    hx-swap="innerHTML"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    {{ __('edit') }}
                                </button>
                                <button 
                                    hx-delete="{{ route('subjects.destroy', $subject->id) }}"
                                    hx-target="#subjects-list"
                                    hx-swap="innerHTML"
                                    hx-confirm="{{ __('confirm_delete_subject') }}"
                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    {{ __('delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Stats -->
                <div class="text-sm text-gray-600 mb-4">
                    <p>{{ trans_choice('messages.units_count', $subject->units_count, ['count' => $subject->units_count]) }}</p>
                    <p class="text-xs text-gray-500">{{ __('created_date', ['date' => $subject->created_at?->translatedFormat('M j, Y') ?? __('recently')]) }}</p>
                </div>

                <!-- View Subject Button -->
                <a href="{{ route('subjects.show', $subject->id) }}" 
                   class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white rounded-md shadow-sm"
                   style="background-color: {{ $subject->color }}">
                    {{ __('view_subject') }}
                    <svg class="ml-2 -mr-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
        @endforeach
    </div>
@else
    @if(isset($selectedChild) && $selectedChild)
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('no_subjects_yet') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('get_started_creating_first_subject') }}</p>
        
        @if(isset($showQuickStart) && $showQuickStart)
            <div class="mt-6 space-y-3">
                <!-- Quick Start Button (Primary) -->
                <div>
                    @if(isset($selectedChild) && $selectedChild)
                        <button 
                            type="button"
                            hx-get="{{ route('subjects.quick-start.form') }}?child_id={{ $selectedChild->id }}"
                            hx-target="#quick-start-modal"
                            hx-swap="innerHTML"
                            class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            {{ __('quick_start_subjects') }}
                        </button>
                        <p class="mt-2 text-xs text-gray-500">{{ __('quick_start_description') }}</p>
                    @else
                        <div class="text-center p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                            <p class="text-sm text-yellow-700">{{ __('please_select_child_to_continue') }}</p>
                        </div>
                    @endif
                </div>
                
                <!-- OR Divider -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="px-2 bg-white text-gray-500">{{ __('or') }}</span>
                    </div>
                </div>
                
                <!-- Manual Create Button (Secondary) -->
                <div>
                    @if(isset($selectedChild) && $selectedChild)
                        <button 
                            type="button"
                            hx-get="{{ route('subjects.create') }}?child_id={{ $selectedChild->id }}"
                            hx-target="#subject-modal"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            {{ __('skip_quick_start') }}
                        </button>
                    @endif
                </div>
            </div>
        @else
            <!-- Original simple create button if quick start not available -->
            <div class="mt-6">
                <button 
                    type="button"
                    hx-get="{{ route('subjects.create') }}"
                    hx-target="#subject-modal"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    {{ __('add_subject') }}
                </button>
            </div>
        @endif
        </div>
    @else
        <!-- No child selected - show empty state -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('select_child_to_view_subjects') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('choose_child_from_selector_above') }}</p>
        </div>
    @endif
@endif

<!-- Container for Quick Start Modal -->
<div id="quick-start-modal"></div>