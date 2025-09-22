@php
  $topic = $catchUpSession->topic;
  $unit = $catchUpSession->unit;
  $subject = $catchUpSession->subject();
@endphp

<!-- Catch-Up Session Card -->
<div class="bg-white border-l-4 border-{{ $catchUpSession->priority <= 2 ? 'red' : ($catchUpSession->priority == 3 ? 'orange' : 'yellow') }}-400 rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow catch-up-card catch-up-session" 
     data-catch-up-id="{{ $catchUpSession->id }}"
     data-priority="{{ $catchUpSession->priority }}">
  
  <!-- Header with priority and actions -->
  <div class="flex items-start justify-between mb-2">
    <div class="flex items-center space-x-2">
      <!-- Priority indicator -->
      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $catchUpSession->getPriorityColor() }}">
        {{ $catchUpSession->getPriorityLabel() }}
      </span>
      
      <!-- Days since missed indicator -->
      @if($catchUpSession->getDaysSinceMissed() > 7)
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
          {{ $catchUpSession->getDaysSinceMissed() }}d overdue
        </span>
      @elseif($catchUpSession->getDaysSinceMissed() > 3)
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
          {{ $catchUpSession->getDaysSinceMissed() }}d ago
        </span>
      @endif
    </div>
    
    <!-- Actions dropdown -->
    <div class="relative" x-data="{ open: false }">
      <button @click="open = !open" class="p-1 text-gray-400 hover:text-gray-600 rounded">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
        </svg>
      </button>
      
      <div x-show="open" @click.away="open = false" 
           class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg z-10 border">
        <div class="py-1">
          <!-- Change priority -->
          <div class="px-4 py-2 text-xs text-gray-500 font-medium">Change Priority</div>
          @foreach([1 => 'Critical', 2 => 'High', 3 => 'Medium', 4 => 'Low', 5 => 'Later'] as $priority => $label)
            @if($priority != $catchUpSession->priority)
            <button
              hx-put="{{ route('planning.catch-up.priority', $catchUpSession->id) }}"
              hx-vals='{"priority": {{ $priority }}}'
              hx-target="#catch-up-column"
              hx-swap="outerHTML"
              class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              {{ $label }}
            </button>
            @endif
          @endforeach
          
          <div class="border-t border-gray-100 my-1"></div>
          
          <!-- Cancel/Delete -->
          <button
            hx-delete="{{ route('planning.catch-up.delete', $catchUpSession->id) }}"
            hx-confirm="Are you sure you want to cancel this catch-up session?"
            hx-target=".catch-up-card[data-catch-up-id='{{ $catchUpSession->id }}']"
            hx-swap="outerHTML swap:1s"
            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
          >
            Cancel Catch-Up
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Topic info -->
  <div class="mb-3">
    <h4 class="font-medium text-gray-900 text-sm line-clamp-2">
      {{ $topic->title ?? 'Unknown Topic' }}
    </h4>
    
    <div class="flex items-center text-xs text-gray-500 mt-1 space-x-2">
      @if($subject)
        <span class="inline-flex items-center">
          <div class="w-2 h-2 rounded-full mr-1" style="background-color: {{ $subject->color }}"></div>
          {{ $subject->name }}
        </span>
      @endif
      
      @if($unit)
        <span>→ {{ $unit->name }}</span>
      @endif
    </div>
  </div>

  <!-- Session details -->
  <div class="space-y-2 text-xs text-gray-600">
    <!-- Duration and missed date -->
    <div class="flex items-center justify-between">
      <span class="flex items-center">
        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ $catchUpSession->getFormattedDuration() }}
      </span>
      
      <span class="text-gray-400">
        Missed: {{ $catchUpSession->getFormattedMissedDate() }}
      </span>
    </div>
    
    <!-- Reason if provided -->
    @if($catchUpSession->reason)
    <div class="text-gray-500 italic">
      "{{ $catchUpSession->reason }}"
    </div>
    @endif
    
    <!-- Status if reassigned -->
    @if($catchUpSession->status === 'reassigned')
      <div class="flex items-center text-blue-600">
        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        Reassigned to new session
      </div>
    @endif
  </div>

  <!-- Quick reschedule suggestions -->
  <div class="mt-3 pt-3 border-t border-gray-100">
    <button
      hx-get="{{ route('planning.scheduling-suggestions', $catchUpSession->original_session_id) }}"
      hx-vals='{"original_date": "{{ $catchUpSession->missed_date->format('Y-m-d') }}"}'
      hx-target="#modal-container"
      hx-swap="innerHTML"
      class="w-full text-xs bg-orange-100 text-orange-700 py-2 px-3 rounded hover:bg-orange-200 transition-colors flex items-center justify-center"
    >
      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      Find Time Slots
    </button>
  </div>
</div>