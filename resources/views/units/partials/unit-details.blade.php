<div class="bg-white rounded-lg shadow-sm border">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold">{{ $unit->title }}</h2>
                <p class="text-gray-600">{{ $subject->name }} → {{ $unit->title }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('units.edit', ['unit' => $unit->id]) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                    {{ __('Edit Unit') }}
                </a>
                <form method="POST" action="{{ route('units.destroy', ['unit' => $unit->id]) }}" 
                      onsubmit="return confirm('{{ __('Are you sure you want to delete this unit?') }}')" 
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>

        @if($unit->description)
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">{{ __('Description') }}</h3>
                <p class="text-gray-600">{{ $unit->description }}</p>
            </div>
        @endif

        @if($unit->target_completion_date)
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">{{ __('Target Completion') }}</h3>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-gray-600">{{ $unit->target_completion_date->format('M j, Y') }}</span>
                </div>
            </div>
        @endif

        <div class="border-t pt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">{{ __('Topics') }}</h3>
                <a href="{{ route('topics.create', [$subject->id, $unit->id]) }}" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                    {{ __('Add Topic') }}
                </a>
            </div>

            @if($topics->count() > 0)
                <div class="grid gap-4">
                    @foreach($topics as $topic)
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="font-medium">{{ $topic->title }}</h4>
                                    @if($topic->description)
                                        <p class="text-sm text-gray-600 mt-1">{{ $topic->description }}</p>
                                    @endif
                                    
                                    <div class="flex items-center gap-4 mt-2">
                                        @if($topic->estimated_duration_minutes)
                                            <span class="text-xs text-blue-600 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                {{ $topic->estimated_duration_minutes }} {{ __('min') }}
                                            </span>
                                        @endif
                                        
                                        @if($topic->difficulty_level)
                                            <span class="text-xs text-purple-600 capitalize">
                                                {{ $topic->difficulty_level }} {{ __('level') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('units.topics.show', [$unit->id, $topic->id]) }}" 
                                       class="text-blue-600 hover:text-blue-700 text-sm">
                                        {{ __('View') }}
                                    </a>
                                    <a href="{{ route('topics.edit', ['topic' => $topic->id]) }}" 
                                       class="text-gray-600 hover:text-gray-700 text-sm">
                                        {{ __('Edit') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <p>{{ __('No topics yet. Add your first topic to get started.') }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 pt-6 border-t">
            <a href="{{ route('planning.index') }}" 
               class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                {{ __('Schedule Learning Sessions') }}
            </a>
        </div>
    </div>
</div>