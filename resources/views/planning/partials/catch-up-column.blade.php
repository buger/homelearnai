<!-- Catch-Up Lane Column -->
<div id="catch-up-column" class="kanban-column bg-orange-50 rounded-lg p-4" data-status="catch-up">
  <div class="flex items-center justify-between mb-4">
    <h3 class="font-semibold text-gray-900 flex items-center">
      <div class="w-3 h-3 bg-orange-400 rounded-full mr-2"></div>
      {{ __('catchup_lane') }}
      <span class="ml-2 bg-orange-200 text-orange-700 px-2 py-1 text-xs rounded-full">
        {{ $catchUpSessions->count() }}
      </span>
    </h3>
    
    <!-- Auto-redistribute button -->
    @if($catchUpSessions->count() > 0)
    <button
      hx-post="{{ route('planning.redistribute-catchup') }}"
      hx-vals='{"child_id": {{ $selectedChild->id }}, "max_sessions": 5}'
      hx-target="body"
      hx-swap="outerHTML"
      class="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600 transition-colors"
      title="Automatically reschedule up to 5 catch-up sessions"
    >
      Auto-Redistribute
    </button>
    @endif
  </div>
  
  <div class="space-y-3 min-h-12">
    @foreach($catchUpSessions->sortBy('priority') as $catchUpSession)
      @include('planning.partials.catch-up-card', ['catchUpSession' => $catchUpSession])
    @endforeach
  </div>
  
  @if($catchUpSessions->isEmpty())
  <div class="text-center py-8 text-gray-400">
    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <p class="text-sm">{{ __('no_catchup_sessions') }}</p>
    <p class="text-xs text-gray-300">{{ __('skipped_sessions_appear_here') }}</p>
  </div>
  @else
  <div class="mt-4 pt-4 border-t border-orange-200">
    <div class="text-xs text-orange-600 space-y-1">
      @php
        $highPriority = $catchUpSessions->where('priority', 1)->count();
        $mediumPriority = $catchUpSessions->where('priority', 2)->count();
        $lowPriority = $catchUpSessions->where('priority', '>=', 3)->count();
      @endphp
      
      @if($highPriority > 0)
        <div class="flex items-center">
          <span class="w-2 h-2 bg-red-400 rounded-full mr-2"></span>
          {{ $highPriority }} Critical
        </div>
      @endif
      
      @if($mediumPriority > 0)
        <div class="flex items-center">
          <span class="w-2 h-2 bg-orange-400 rounded-full mr-2"></span>
          {{ $mediumPriority }} High
        </div>
      @endif
      
      @if($lowPriority > 0)
        <div class="flex items-center">
          <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2"></span>
          {{ $lowPriority }} Medium/Low
        </div>
      @endif
    </div>
  </div>
  @endif
</div>