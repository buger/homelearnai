@php
  $topic = $session->topic;
  $unit = $topic ? $topic->unit : null;
  $subject = $unit ? $unit->subject : null;
@endphp

<div class="session-card bg-white rounded-lg border border-gray-200 p-3 cursor-move hover:shadow-md"
     data-session-id="{{ $session->id }}"
     data-status="{{ $session->status }}"
     draggable="true">
  
  <!-- Topic Title and Subject -->
  <div class="flex items-start justify-between mb-2">
    <div class="flex-1 min-w-0">
      <h4 class="text-sm font-medium text-gray-900 truncate">{{ $topic?->title ?? __('unknown_topic') }}</h4>
      @if($subject)
      <div class="flex items-center mt-1">
        <div class="w-2 h-2 rounded-full mr-1" style="background-color: {{ $subject->color }}"></div>
        <span class="text-xs text-gray-600">{{ $subject->name }}</span>
      </div>
      @endif
    </div>
    
    <!-- Session Status and Commitment Type -->
    <div class="flex flex-col items-end space-y-1">
      <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $session->getStatusColor() }}">
        {{ ucfirst($session->status) }}
      </span>
      
      <!-- Commitment Type Badge -->
      <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $session->getCommitmentTypeColor() }}" 
            title="{{ $session->getCommitmentTypeLabel() }} commitment">
        {{ $session->getCommitmentTypeLabel() }}
      </span>
    </div>
  </div>

  <!-- Unit Context -->
  @if($unit)
  <div class="text-xs text-gray-500 mb-2 truncate">
    {{ __('unit', ['name' => $unit->name]) }}
  </div>
  @endif

  <!-- Duration -->
  <div class="flex items-center justify-between mb-2">
    <div class="flex items-center text-xs text-gray-600">
      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      {{ $session->getFormattedDuration() }}
    </div>
    
    @if($session->status === 'scheduled' && $session->getScheduledTimeRange())
    <div class="text-xs text-green-600 font-medium">
      {{ $session->getScheduledDayName() }} {{ $session->getScheduledTimeRange() }}
    </div>
    @endif
  </div>

  <!-- Actions -->
  <div class="flex items-center justify-between pt-2 border-t border-gray-100">
    <div class="flex space-x-1">
      @if($session->status !== 'done')
      <!-- Quick Status Change Buttons -->
      @if($session->status === 'backlog')
      <button
        hx-put="{{ route('planning.sessions.status', $session->id) }}"
        hx-vals='{"status": "planned"}'
        hx-target="#planning-board"
        hx-swap="innerHTML"
        class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 rounded transition-colors"
        title="{{ __('move_to_planned') }}"
      >
        {{ __('plan') }}
      </button>
      @elseif($session->status === 'planned')
      <button
        hx-get="{{ route('planning.sessions.schedule', $session->id) }}"
        hx-target="#modal-container"
        hx-swap="innerHTML"
        class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-700 hover:bg-green-200 rounded transition-colors"
        title="{{ __('schedule_this_session') }}"
      >
        {{ __('schedule') }}
      </button>
      @elseif($session->status === 'scheduled')
      <button
        hx-put="{{ route('planning.sessions.unschedule', $session->id) }}"
        hx-target="#planning-board"
        hx-swap="innerHTML"
        class="inline-flex items-center px-2 py-1 text-xs bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded transition-colors"
        title="{{ __('unschedule_session') }}"
      >
        {{ __('unschedule') }}
      </button>
      
      <!-- Skip Day Button -->
      <button
        onclick="openSkipDayModal({{ $session->id }}, '{{ $session->scheduled_date?->format('Y-m-d') ?? 'today' }}')"
        class="inline-flex items-center px-2 py-1 text-xs bg-orange-100 text-orange-700 hover:bg-orange-200 rounded transition-colors"
        title="{{ __('skip_day') }}"
      >
        {{ __('skip_day') }}
      </button>
      
      <button
        hx-put="{{ route('planning.sessions.status', $session->id) }}"
        hx-vals='{"status": "done"}'
        hx-target="#planning-board"
        hx-swap="innerHTML"
        class="inline-flex items-center px-2 py-1 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 rounded transition-colors"
        title="{{ __('mark_as_done') }}"
      >
        {{ __('complete') }}
      </button>
      @endif
      @endif
    </div>
    
    <!-- Additional Actions Dropdown -->
    <div class="relative" x-data="{ open: false }">
      <button @click="open = !open" class="inline-flex items-center text-xs text-gray-600 hover:text-gray-800 transition-colors p-1 rounded">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
        </svg>
      </button>
      
      <div x-show="open" @click.away="open = false" 
           class="absolute right-0 bottom-full mb-1 w-40 bg-white rounded-md shadow-lg z-10 border">
        <div class="py-1">
          <!-- Change Commitment Type -->
          <div class="px-3 py-2 text-xs text-gray-500 font-medium">{{ __('commitment_type') }}</div>
          @foreach(['fixed' => __('fixed'), 'preferred' => __('preferred'), 'flexible' => __('flexible')] as $type => $label)
            @if($type !== $session->commitment_type)
            <button
              hx-patch="{{ route('planning.sessions.commitment-type', $session->id) }}"
              hx-vals='{"commitment_type": "{{ $type }}"}'
              hx-target="closest .session-card"
              hx-swap="outerHTML"
              class="block w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-100"
            >
              {{ $label }}
            </button>
            @endif
          @endforeach
          
          <div class="border-t border-gray-100 my-1"></div>
          
          <!-- Delete Session -->
          <button
            hx-delete="{{ route('planning.sessions.destroy', $session->id) }}"
            hx-confirm="{{ __('are_you_sure_you_want_to_delete_this_session') }}"
            hx-target="closest .session-card"
            hx-swap="outerHTML"
            class="block w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50"
          >
            {{ __('delete_session') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  @if($session->notes)
  <!-- Notes -->
  <div class="mt-2 pt-2 border-t border-gray-100">
    <p class="text-xs text-gray-600">{{ $session->notes }}</p>
  </div>
  @endif

  @if($session->completed_at)
  <!-- Completion Info -->
  <div class="mt-2 pt-2 border-t border-gray-100">
    <p class="text-xs text-gray-500">
      {{ __('completed_date_time', ['date' => $session->completed_at->translatedFormat('M j, Y g:i A')]) }}
    </p>
  </div>
  @endif
  
  @if($session->wasSkipped())
  <!-- Skipped Info -->
  <div class="mt-2 pt-2 border-t border-orange-100 bg-orange-50 rounded px-2 py-1">
    <p class="text-xs text-orange-700">
      {{ __('skipped_from', ['date' => $session->getFormattedSkippedDate()]) }}
    </p>
  </div>
  @endif
</div>