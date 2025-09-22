@php
  $progressData = $unit->getProgressBarData($childId);
@endphp

<!-- Unit Progress Bar -->
<div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
  <!-- Unit Header -->
  <div class="flex items-start justify-between mb-3">
    <div class="flex-1 min-w-0">
      <h3 class="text-lg font-medium text-gray-900 truncate">{{ $unit->name }}</h3>
      @if($unit->description)
        <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $unit->description }}</p>
      @endif
    </div>
    
    <!-- Unit Status Badge -->
    <div class="flex items-center space-x-2">
      @if($unit->isOverdue())
        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
          Overdue
        </span>
      @endif
      
      <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
        {{ $progressData['status'] === 'complete' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
        {{ $unit->getCompletionStatus($childId) }}
      </span>
    </div>
  </div>

  <!-- Progress Bar -->
  <div class="mb-3">
    <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
      <span>Progress</span>
      <span class="font-medium">{{ round($progressData['percentage']) }}%</span>
    </div>
    
    <!-- Progress Bar Track -->
    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
      <div class="h-3 rounded-full transition-all duration-500 {{ $progressData['color'] }}"
           style="width: {{ $progressData['percentage'] }}%">
      </div>
    </div>
    
    <!-- Progress Text -->
    <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
      <span>{{ $progressData['text'] }}</span>
      @if($unit->target_completion_date)
        <span>Due: {{ $unit->target_completion_date->translatedFormat('M j, Y') }}</span>
      @endif
    </div>
  </div>

  <!-- Completion Gate Status -->
  @php
    $meetsGate = $unit->meetsCompletionGate($childId);
  @endphp
  
  @if(!$meetsGate)
    <div class="mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
      <div class="flex items-center">
        <svg class="w-4 h-4 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <div class="text-sm text-yellow-800">
          <p class="font-medium">Completion Gate Not Met</p>
          <p class="text-xs">All required topics must be completed before this unit can be marked as done.</p>
        </div>
      </div>
    </div>
  @else
    <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
      <div class="flex items-center">
        <svg class="w-4 h-4 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-green-800">
          <p class="font-medium">Unit Ready for Completion</p>
          <p class="text-xs">All required topics have been completed. This unit can be marked as done.</p>
        </div>
      </div>
    </div>
  @endif

  <!-- Next Topics to Work On -->
  @php
    $nextTopics = $unit->getNextTopics($childId, 3);
  @endphp
  
  @if($nextTopics->count() > 0)
    <div class="border-t border-gray-100 pt-3">
      <h4 class="text-sm font-medium text-gray-900 mb-2">Next Topics to Work On</h4>
      <div class="space-y-2">
        @foreach($nextTopics as $topic)
          <div class="flex items-center justify-between text-sm">
            <div class="flex items-center flex-1 min-w-0">
              @if($topic->required)
                <span class="inline-flex items-center w-4 h-4 text-xs font-bold text-red-600 mr-2" title="Required">★</span>
              @else
                <span class="inline-flex items-center w-4 h-4 text-xs text-gray-400 mr-2" title="Optional">○</span>
              @endif
              <span class="text-gray-900 truncate">{{ $topic->title }}</span>
            </div>
            
            <div class="flex items-center space-x-2 ml-2">
              <span class="text-xs text-gray-500">{{ $topic->estimated_minutes }} min</span>
              
              <!-- Quick add session button -->
              <button
                hx-post="{{ route('planning.create-session') }}"
                hx-vals='{"topic_id": {{ $topic->id }}, "child_id": {{ $childId }}, "estimated_minutes": {{ $topic->estimated_minutes }}}'
                hx-target="#planning-board"
                hx-swap="innerHTML"
                class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 rounded transition-colors"
                title="Add session for this topic"
              >
                + Add
              </button>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <!-- Unit Stats (Collapsible) -->
  <div x-data="{ expanded: false }" class="border-t border-gray-100 pt-3 mt-3">
    <button @click="expanded = !expanded" 
            class="flex items-center justify-between w-full text-sm text-gray-600 hover:text-gray-800">
      <span class="font-medium">Unit Details</span>
      <svg class="w-4 h-4 transform transition-transform" :class="{'rotate-180': expanded}" 
           fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    
    <div x-show="expanded" x-collapse class="mt-3 space-y-2 text-xs text-gray-600">
      @php
        $stats = $unit->getProgressForChild($childId);
      @endphp
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <span class="font-medium">Total Topics:</span>
          <span>{{ $stats['total_topics'] }}</span>
        </div>
        <div>
          <span class="font-medium">Required Topics:</span>
          <span>{{ $stats['required_topics'] }}</span>
        </div>
        <div>
          <span class="font-medium">Completed:</span>
          <span>{{ $stats['completed_topics'] }}</span>
        </div>
        <div>
          <span class="font-medium">Remaining Required:</span>
          <span>{{ $stats['remaining_required'] }}</span>
        </div>
      </div>
      
      @if($unit->target_completion_date)
        <div class="pt-2 border-t border-gray-100">
          <span class="font-medium">Target Date:</span>
          <span>{{ $unit->target_completion_date->translatedFormat('F j, Y') }}</span>
          @if($unit->getDaysUntilTarget() !== null)
            <span class="ml-2 
              {{ $unit->getDaysUntilTarget() < 0 ? 'text-red-600' : ($unit->getDaysUntilTarget() < 7 ? 'text-yellow-600' : 'text-green-600') }}">
              ({{ abs($unit->getDaysUntilTarget()) }} days {{ $unit->getDaysUntilTarget() < 0 ? 'overdue' : 'remaining' }})
            </span>
          @endif
        </div>
      @endif
    </div>
  </div>
</div>