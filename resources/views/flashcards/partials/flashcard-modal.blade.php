<!-- Flashcard Modal -->
<div id="flashcard-modal-overlay" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50" x-data="{ open: true }" x-show="open" data-testid="flashcard-modal-overlay">
    <div id="flashcard-modal-content" class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto relative z-50" data-testid="flashcard-modal">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ $isEdit ? 'Edit Flashcard' : 'Add New Flashcard' }}
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="$event.target.closest('.fixed').remove()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <form
            @if($isEdit)
                @if(isset($topic) && $flashcard && $flashcard->topic_id)
                    hx-put="{{ route('topics.flashcards.update', [$flashcard->topic_id, $flashcard->id]) }}"
                @else
                    hx-put="{{ route('units.flashcards.update', [$unit->id, $flashcard->id]) }}"
                @endif
            @else
                @if(isset($topic))
                    hx-post="{{ route('topics.flashcards.store', $topic->id) }}"
                @else
                    hx-post="{{ route('units.flashcards.store', $unit->id) }}"
                @endif
            @endif
            hx-target="{{ isset($topic) ? '#topic-flashcards-list' : '#flashcards-list' }}"
            hx-swap="innerHTML"
            hx-on::before-request="console.log('HTMX: Flashcard form submission starting...', event.detail)"
            hx-on::after-request="console.log('HTMX: Flashcard form submission completed', event.detail); if(event.detail.xhr.status >= 200 && event.detail.xhr.status < 300) { setTimeout(() => { const modal = event.target.closest('.fixed'); if(modal) { console.log('Removing flashcard modal...'); modal.remove(); } }, 100); }"
            hx-on::response-error="console.error('HTMX: Flashcard form error occurred', event.detail)"
            hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
            class="px-6 py-4 space-y-6">
            
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <!-- Topic Selection (when creating from unit level) -->
            @if(!isset($topic) && !$isEdit)
                <div class="space-y-2">
                    <label for="topic_id" class="flex items-center text-sm font-medium text-gray-700">
                        Topic (Optional)
                        <x-help-tooltip
                            title="Topic Assignment"
                            content="Assign this flashcard to a specific topic within the unit. This helps organize flashcards by learning objectives. You can leave this blank to keep it as a unit-level flashcard."
                            position="right"
                            size="md"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <select name="topic_id" id="topic_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Unit-level flashcard (no specific topic)</option>
                        @foreach($unit->topics as $unitTopic)
                            <option value="{{ $unitTopic->id }}">
                                {{ $unitTopic->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif($isEdit && !$flashcard->topic_id)
                <!-- Allow moving unit-level flashcard to a topic -->
                <div class="space-y-2">
                    <label for="topic_id" class="flex items-center text-sm font-medium text-gray-700">
                        Move to Topic (Optional)
                        <x-help-tooltip
                            title="Topic Assignment"
                            content="Move this unit-level flashcard to a specific topic. This helps organize flashcards by learning objectives."
                            position="right"
                            size="md"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <select name="topic_id" id="topic_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Keep as unit-level flashcard</option>
                        @foreach($unit->topics as $unitTopic)
                            <option value="{{ $unitTopic->id }}">
                                {{ $unitTopic->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif($isEdit && $flashcard->topic_id)
                <!-- Show current topic and allow changing -->
                <div class="space-y-2">
                    <label for="topic_id" class="flex items-center text-sm font-medium text-gray-700">
                        Topic
                        <x-help-tooltip
                            title="Topic Assignment"
                            content="Change the topic assignment for this flashcard or remove it from the topic to make it a unit-level flashcard."
                            position="right"
                            size="md"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <select name="topic_id" id="topic_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Move to unit-level (no specific topic)</option>
                        @foreach($unit->topics as $unitTopic)
                            <option value="{{ $unitTopic->id }}" {{ $flashcard->topic_id == $unitTopic->id ? 'selected' : '' }}>
                                {{ $unitTopic->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @elseif(isset($topic))
                <!-- Hidden field when creating for specific topic -->
                <input type="hidden" name="topic_id" value="{{ $topic->id }}">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm text-blue-800">
                            This flashcard will be assigned to: <strong>{{ $topic->title }}</strong>
                        </span>
                    </div>
                </div>
            @endif

            <!-- Card Type Selection -->
            <div class="space-y-2">
                <label for="card_type" class="flex items-center text-sm font-medium text-gray-700">
                    Card Type
                    <x-help-tooltip
                        title="Card Types Explained"
                        content="Basic: Traditional Q&A format&#10;Multiple Choice: Select from 2-6 options&#10;True/False: Quick T/F questions&#10;Cloze: Fill-in-the-blank with @{{syntax}}&#10;Typed Answer: Requires exact spelling&#10;Image Occlusion: Hide parts of images (coming soon)"
                        position="right"
                        size="lg"
                        trigger="hover"
                        theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                    />
                </label>
                <select name="card_type" id="card_type"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        onchange="toggleCardTypeFields(this.value)">
                    <option value="basic" {{ ($flashcard?->card_type ?? 'basic') === 'basic' ? 'selected' : '' }}>Basic</option>
                    <option value="multiple_choice" {{ ($flashcard?->card_type) === 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                    <option value="true_false" {{ ($flashcard?->card_type) === 'true_false' ? 'selected' : '' }}>True/False</option>
                    <option value="cloze" {{ ($flashcard?->card_type) === 'cloze' ? 'selected' : '' }}>Cloze Deletion</option>
                    <option value="typed_answer" {{ ($flashcard?->card_type) === 'typed_answer' ? 'selected' : '' }}>Typed Answer</option>
                    <option value="image_occlusion" {{ ($flashcard?->card_type) === 'image_occlusion' ? 'selected' : '' }}>Image Occlusion</option>
                </select>
            </div>

            <!-- Basic Card Fields -->
            <div id="basic-fields">
                <!-- Question -->
                <div class="space-y-2">
                    <label for="question" class="flex items-center text-sm font-medium text-gray-700">
                        Question *
                        <x-help-tooltip 
                            title="Writing Good Questions"
                            content="Be specific and clear. Ask one thing at a time. Use simple language appropriate for the learner's age. For kids mode, avoid trick questions."
                            position="right"
                            size="md"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <textarea name="question" id="question" rows="3" required
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Enter your question here...">{{ $flashcard?->question ?? '' }}</textarea>
                </div>

                <!-- Answer -->
                <div class="space-y-2">
                    <label for="answer" class="block text-sm font-medium text-gray-700">Answer *</label>
                    <textarea name="answer" id="answer" rows="3" required
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Enter the answer here...">{{ $flashcard?->answer ?? '' }}</textarea>
                </div>

                <!-- Hint -->
                <div class="space-y-2">
                    <label for="hint" class="flex items-center text-sm font-medium text-gray-700">
                        Hint (Optional)
                        <x-help-tooltip 
                            title="Creating Helpful Hints"
                            content="Hints should guide without giving away the answer. Use them especially for kids mode. Examples: 'Think about what you eat for breakfast' or 'This number is between 5 and 10'"
                            position="right"
                            size="md"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <textarea name="hint" id="hint" rows="2"
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Enter a helpful hint...">{{ $flashcard?->hint ?? '' }}</textarea>
                </div>
            </div>

            <!-- Multiple Choice Fields -->
            <div id="multiple-choice-fields" class="hidden space-y-4">
                <div class="space-y-2">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        Answer Choices
                        <x-help-tooltip 
                            title="Multiple Choice Best Practices"
                            content="• Use 2-6 answer choices&#10;• Make wrong answers plausible but clearly incorrect&#10;• You can have multiple correct answers&#10;• Keep choices roughly the same length&#10;• Avoid 'All of the above' or 'None of the above'"
                            position="right"
                            size="lg"
                            trigger="hover"
                            theme="{{ session('kids_mode', false) ? 'kids' : 'light' }}"
                        />
                    </label>
                    <div id="choices-container" class="space-y-2">
                        <!-- Choices will be dynamically added here -->
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" onclick="addChoice()" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add Choice
                        </button>
                        <span class="text-sm text-gray-500 flex items-center">
                            Check the box next to correct answers (multiple selections allowed)
                        </span>
                    </div>
                </div>
            </div>

            <!-- True/False Fields -->
            <div id="true-false-fields" class="hidden space-y-2">
                <label class="block text-sm font-medium text-gray-700">Correct Answer</label>
                <div class="space-y-2">
                    <label class="inline-flex items-center">
                        <input type="radio" name="true_false_answer" value="true" class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2">True</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="true_false_answer" value="false" class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2">False</span>
                    </label>
                </div>
            </div>

            <!-- Cloze Deletion Fields -->
            <div id="cloze-fields" class="hidden space-y-4">
                <div class="space-y-2">
                    <label for="cloze_text" class="block text-sm font-medium text-gray-700">Cloze Text</label>
                    <textarea name="cloze_text" id="cloze_text" rows="4"
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Use @{{word}} or @{{c1::word}} syntax for deletions...">{{ $flashcard?->cloze_text ?? '' }}</textarea>
                    <div class="text-sm text-gray-500">
                        <p><strong>Examples:</strong></p>
                        <ul class="list-disc list-inside space-y-1">
                            <li><code>The @{{capital}} of France is @{{Paris}}.</code></li>
                            <li><code>The @{{c1::mitochondria}} is the @{{c2::powerhouse}} of the cell.</code></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Auto-generate from Q&A -->
                <div class="pt-2 border-t border-gray-200">
                    <button type="button" onclick="generateClozeFromQA()" 
                            class="text-sm text-blue-600 hover:text-blue-800 underline">
                        Generate cloze from question/answer above
                    </button>
                </div>
            </div>

            <!-- Image Occlusion Fields -->
            <div id="image-occlusion-fields" class="hidden space-y-4">
                <div class="space-y-2">
                    <label for="question_image_url" class="block text-sm font-medium text-gray-700">Image URL</label>
                    <input type="url" name="question_image_url" id="question_image_url"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="https://example.com/image.jpg"
                           value="{{ $flashcard?->question_image_url ?? '' }}">
                </div>
                <p class="text-sm text-gray-500">Image occlusion editing is not yet implemented in this interface.</p>
            </div>

            <!-- Common Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Difficulty -->
                <div class="space-y-2">
                    <label for="difficulty_level" class="block text-sm font-medium text-gray-700">Difficulty</label>
                    <select name="difficulty_level" id="difficulty_level"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="easy" {{ ($flashcard?->difficulty_level ?? 'medium') === 'easy' ? 'selected' : '' }}>Easy</option>
                        <option value="medium" {{ ($flashcard?->difficulty_level ?? 'medium') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="hard" {{ ($flashcard?->difficulty_level ?? 'medium') === 'hard' ? 'selected' : '' }}>Hard</option>
                    </select>
                </div>

                <!-- Tags -->
                <div class="space-y-2">
                    <label for="tags" class="block text-sm font-medium text-gray-700">Tags (comma-separated)</label>
                    <input type="text" name="tags" id="tags"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="math, geometry, basic"
                           value="{{ $flashcard && !empty($flashcard->tags) ? implode(', ', $flashcard->tags) : '' }}">
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" @click="$event.target.closest('.fixed').remove()" 
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    {{ $isEdit ? 'Update' : 'Create' }} Flashcard
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for dynamic form behavior -->
<script>
    // Avoid global variable conflicts by scoping variables but keeping functions global
    (function() {
        // Private variables to avoid conflicts
        let modalChoiceCount = 0;

        // Modal form response is now handled by HTMX events

        // Make functions global so they can be called from HTML
        window.toggleCardTypeFields = function(cardType) {
        // Hide all card-specific fields
        const fieldGroups = ['basic-fields', 'multiple-choice-fields', 'true-false-fields', 'cloze-fields', 'image-occlusion-fields'];
        fieldGroups.forEach(group => {
            const element = document.getElementById(group);
            if (element) element.classList.add('hidden');
        });
        
        // Show relevant fields for selected card type
        switch (cardType) {
            case 'basic':
            case 'typed_answer':
                document.getElementById('basic-fields').classList.remove('hidden');
                break;
            case 'multiple_choice':
                document.getElementById('basic-fields').classList.remove('hidden');
                document.getElementById('multiple-choice-fields').classList.remove('hidden');
                initializeChoices();
                break;
            case 'true_false':
                document.getElementById('basic-fields').classList.remove('hidden');
                document.getElementById('true-false-fields').classList.remove('hidden');
                break;
            case 'cloze':
                document.getElementById('cloze-fields').classList.remove('hidden');
                break;
            case 'image_occlusion':
                document.getElementById('image-occlusion-fields').classList.remove('hidden');
                break;
        }
    }
    
    function initializeChoices() {
        const container = document.getElementById('choices-container');
        container.innerHTML = '';
        choiceCount = 0;
        
        @if($flashcard && $flashcard->card_type === 'multiple_choice' && !empty($flashcard->choices))
            @foreach($flashcard->choices as $index => $choice)
                addChoice('{{ $choice }}', {{ in_array($index, $flashcard->correct_choices ?? []) ? 'true' : 'false' }});
            @endforeach
        @else
            // Add two default choices
            addChoice();
            addChoice();
        @endif
    }
    
    function addChoice(value = '', isCorrect = false) {
        const container = document.getElementById('choices-container');
        const choiceDiv = document.createElement('div');
        choiceDiv.className = 'flex items-center space-x-2';
        choiceDiv.innerHTML = `
            <input type="checkbox" name="correct_choices[]" value="${choiceCount}" ${isCorrect ? 'checked' : ''}
                   class="text-blue-600 focus:ring-blue-500" title="Mark as correct answer">
            <input type="text" name="choices[]" value="${value}" placeholder="Choice ${choiceCount + 1}" required
                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <button type="button" onclick="removeChoice(this)" 
                    class="text-red-600 hover:text-red-800 p-1" title="Remove choice"
                    ${container.children.length <= 1 ? 'style="visibility: hidden;"' : ''}>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        `;
        container.appendChild(choiceDiv);
        choiceCount++;
        
        // Ensure at least 2 choices always visible
        updateChoiceVisibility();
    }
    
    function removeChoice(button) {
        const container = document.getElementById('choices-container');
        if (container.children.length > 2) {
            button.parentElement.remove();
            updateChoiceVisibility();
        }
    }
    
    function updateChoiceVisibility() {
        const container = document.getElementById('choices-container');
        const choices = container.children;
        
        // Hide/show remove buttons based on choice count
        Array.from(choices).forEach((choice, index) => {
            const removeButton = choice.querySelector('button');
            if (choices.length <= 2) {
                removeButton.style.visibility = 'hidden';
            } else {
                removeButton.style.visibility = 'visible';
            }
        });
    }
    
    function generateClozeFromQA() {
        const question = document.getElementById('question').value;
        const answer = document.getElementById('answer').value;
        const clozeTextarea = document.getElementById('cloze_text');
        
        if (question && answer) {
            // Simple cloze generation - replace answer in question with cloze syntax
            const clozeText = question.replace(new RegExp(answer, 'gi'), '{{' + answer + '}}');
            clozeTextarea.value = clozeText;
        } else {
            alert('Please enter both question and answer first to generate cloze text.');
        }
    }
    
    // Initialize form based on current card type
    document.addEventListener('DOMContentLoaded', function() {
        const cardType = document.getElementById('card_type').value;
        toggleCardTypeFields(cardType);
        
        @if($flashcard && $flashcard->card_type === 'true_false' && !empty($flashcard->correct_choices))
            // Set true/false selection for editing
            const correctAnswer = {{ $flashcard->correct_choices[0] ?? 0 }} === 0 ? 'true' : 'false';
            document.querySelector(`input[name="true_false_answer"][value="${correctAnswer}"]`).checked = true;
        @endif
    });
    
    // Close modal when clicking outside (handled by Alpine.js click away)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('fixed') && e.target.hasAttribute('data-testid') && e.target.getAttribute('data-testid') === 'flashcard-modal') {
            e.target.remove();
        }
    });
</script>