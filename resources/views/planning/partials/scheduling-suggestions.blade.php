@php
  $topic = $session->topic;
  $subject = $session->subject();
@endphp

<!-- Scheduling Suggestions Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" id="scheduling-modal" data-testid="scheduling-modal">
  <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-200">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">{{ __('reschedule_session') }}</h3>
        <p class="text-sm text-gray-600 mt-1">{{ $topic->title ?? 'Session' }} ({{ $session->getFormattedDuration() }})</p>
        @if($subject)
          <div class="flex items-center mt-2">
            <div class="w-2 h-2 rounded-full mr-2" style="background-color: {{ $subject->color }}"></div>
            <span class="text-xs text-gray-500">{{ $subject->name }}</span>
          </div>
        @endif
      </div>
      
      <button onclick="closeModal()" 
              class="text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Modal Body -->
    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
      @if(count($suggestions) > 0)
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-3">
            {{ __('here_are_the_best_available_time_slots_for_rescheduling_this_session') }}
          </p>
          
          <!-- Original date info -->
          <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
            <div class="flex items-center">
              <svg class="w-4 h-4 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span class="text-sm text-orange-800">
                {{ __('originally_scheduled_for', ['date' => $originalDate->translatedFormat('l, F j, Y')]) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Suggestions List -->
        <div class="space-y-3">
          @foreach($suggestions as $index => $suggestion)
            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors
                        {{ $suggestion['recommended'] ? 'border-green-300 bg-green-50' : 'border-gray-200' }}">
              
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <!-- Date and Time -->
                  <div class="flex items-center space-x-3">
                    <h4 class="font-medium text-gray-900">
                      {{ $suggestion['day_name'] }}, {{ \Carbon\Carbon::parse($suggestion['date'])->translatedFormat('M j') }}
                    </h4>
                    
                    <span class="text-sm text-gray-600">
                      {{ \Carbon\Carbon::createFromFormat('H:i:s', $suggestion['start_time'])->translatedFormat('g:i A') }} - 
                      {{ \Carbon\Carbon::createFromFormat('H:i:s', $suggestion['end_time'])->translatedFormat('g:i A') }}
                    </span>
                    
                    @if($suggestion['recommended'])
                      <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        {{ __('recommended') }}
                      </span>
                    @endif
                  </div>

                  <!-- Capacity and Difficulty Info -->
                  <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span class="flex items-center">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H9a2 2 0 01-2-2z"/>
                      </svg>
                      {{ __('capacity_used_percentage', ['percentage' => round($suggestion['capacity_used'])]) }}
                    </span>
                    
                    <span class="flex items-center">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                      </svg>
                      {{ __('difficulty', ['difficulty' => $suggestion['difficulty']]) }}
                    </span>
                    
                    <span>
                      {{ __('days_from_original', ['days' => \Carbon\Carbon::parse($suggestion['date'])->diffInDays(\Carbon\Carbon::parse($originalDate))]) }}
                    </span>
                  </div>
                </div>
                
                <!-- Select Button -->
                <button
                  hx-post="{{ route('planning.sessions.schedule', $session->id) }}"
                  hx-vals='{
                    "scheduled_day_of_week": {{ $suggestion['day_of_week'] }}, 
                    "scheduled_start_time": "{{ \Carbon\Carbon::createFromFormat('H:i:s', $suggestion['start_time'])->format('H:i') }}", 
                    "scheduled_end_time": "{{ \Carbon\Carbon::createFromFormat('H:i:s', $suggestion['end_time'])->format('H:i') }}",
                    "scheduled_date": "{{ $suggestion['date'] }}"
                  }'
                  hx-target="#planning-board"
                  hx-swap="innerHTML"
                  onclick="closeModal()"
                  class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                >
                  {{ __('schedule_here') }}
                </button>
              </div>
            </div>
          @endforeach
        </div>
        
        <!-- Auto-reschedule note -->
        @if($session->commitment_type === 'flexible')
          <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center">
              <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div class="text-sm text-blue-800">
                <p class="font-medium">{{ __('flexible_session') }}</p>
                <p class="text-xs">{{ __('this_session_can_be_automatically_rescheduled_if_needed') }}</p>
              </div>
            </div>
          </div>
        @endif
        
      @else
        <!-- No suggestions available -->
        <div class="text-center py-8">
          <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('no_available_slots') }}</h3>
          <p class="text-gray-600 mb-4">
            {{ __('no_suitable_time_slots_were_found_for_rescheduling_this_session_in_the_next_two_weeks') }}
          </p>
          <div class="space-y-2 text-sm text-gray-500">
            <p>• {{ __('try_reducing_the_session_duration') }}</p>
            <p>• {{ __('consider_adding_more_time_blocks_to_the_weekly_schedule') }}</p>
            <p>• {{ __('look_for_available_slots_further_in_the_future') }}</p>
          </div>
        </div>
      @endif
    </div>

    <!-- Modal Footer -->
    <div class="flex items-center justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
      <button onclick="closeModal()" 
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
        {{ __('cancel') }}
      </button>
      
      @if(count($suggestions) > 0)
        <button
          hx-get="{{ route('planning.capacity-analysis') }}"
          hx-vals='{"child_id": {{ $session->child_id }}}'
          hx-target="#modal-container"
          hx-swap="innerHTML"
          class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 border border-blue-300 rounded-lg hover:bg-blue-200 transition-colors"
        >
          {{ __('view_capacity_analysis') }}
        </button>
      @endif
    </div>
  </div>
</div>

<script>
function closeModal() {
  document.getElementById('scheduling-modal').remove();
}

// Close modal on background click
document.getElementById('scheduling-modal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});
</script>