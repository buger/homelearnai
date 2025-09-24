<!-- Create Session Modal Form -->
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="create-session-modal">
  <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white relative z-10">
    <div class="mt-3">
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-3">
        <h3 class="text-lg font-medium text-gray-900">{{ __('create_learning_session') }}</h3>
        <button 
          type="button"
          onclick="document.getElementById('create-session-modal').remove()"
          class="text-gray-400 hover:text-gray-600"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form 
        hx-post="{{ route('planning.sessions.store') }}"
        hx-target="#planning-board"
        hx-swap="innerHTML"
        hx-ext="json-enc"
      >
        @csrf
        <input type="hidden" name="child_id" value="{{ $childId }}">
        
        <!-- Topic Selection -->
        <div class="mb-4">
          <label for="topic_id" class="block text-sm font-medium text-gray-700 mb-2">{{ __('select_topic') }}</label>
          <select 
            name="topic_id" 
            id="topic_id" 
            required
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
          >
            <option value="">{{ __('choose_a_topic') }}</option>
            @foreach($availableTopics as $topic)
              <option value="{{ $topic->id }}" data-minutes="{{ $topic->estimated_minutes }}">
                {{ $topic->title }}
                @if($topic->unit && $topic->unit->subject) ({{ $topic->unit->subject->name }}) @endif
                - {{ $topic->estimated_minutes }} min
              </option>
            @endforeach
          </select>
          
          @if($availableTopics->isEmpty())
          <p class="mt-2 text-sm text-gray-500">
            {{ __('no_topics_available') }} 
            <a href="{{ route('subjects.index') }}" class="text-blue-600 hover:text-blue-500">
              {{ __('create_subjects_and_units_first') }}
            </a>.
          </p>
          @endif
        </div>

        <!-- Custom Duration (Optional) -->
        <div class="mb-4">
          <label for="estimated_minutes" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('custom_duration_minutes') }}
            <span class="text-gray-500 text-xs">({{ __('optional_defaults_to_topic_estimate') }})</span>
          </label>
          <input 
            type="number" 
            name="estimated_minutes" 
            id="estimated_minutes"
            min="5" 
            max="480"
            placeholder="{{ __('session_duration_placeholder') }}"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
          >
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end space-x-2 pt-4">
          <button 
            type="button"
            onclick="document.getElementById('create-session-modal').remove()"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            {{ __('cancel') }}
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <span class="htmx-indicator" style="display: none;">{{ __('Creating...') }}</span>
            <span class="htmx-regular">{{ __('create_session') }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Auto-fill estimated minutes when topic is selected
  document.getElementById('topic_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const minutes = selectedOption.getAttribute('data-minutes');
    if (minutes) {
      document.getElementById('estimated_minutes').value = minutes;
    }
  });

  // Close modal on successful form submission
  document.addEventListener('htmx:afterRequest', function(event) {
    if (event.target.closest('#create-session-modal') && event.detail.xhr.status >= 200 && event.detail.xhr.status < 300) {
      document.getElementById('create-session-modal').remove();
    }
  });
</script>