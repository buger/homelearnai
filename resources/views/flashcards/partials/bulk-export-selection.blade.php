<!-- Bulk Export Selection -->
<div class="space-y-6" x-data="bulkExportSelection()">
    <!-- Back Button -->
    <div class="flex items-center justify-between">
        <button type="button" 
                class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center"
                onclick="showExportOptions()"
                data-testid="back-to-options">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Export Options
        </button>
        
        <div class="text-sm text-gray-600">
            <span x-text="selectedCount"></span> of {{ $totalCards }} cards selected
        </div>
    </div>

    <!-- Selection Controls -->
    <div class="bg-gray-50 rounded-lg p-4">
        <div class="flex flex-wrap items-center gap-4">
            <button type="button" 
                    class="text-sm text-blue-600 hover:text-blue-800"
                    @click="selectAll()">
                Select All
            </button>
            <button type="button" 
                    class="text-sm text-blue-600 hover:text-blue-800"
                    @click="selectNone()">
                Select None
            </button>
            <button type="button" 
                    class="text-sm text-blue-600 hover:text-blue-800"
                    @click="invertSelection()">
                Invert Selection
            </button>
            
            <div class="text-sm text-gray-600">|</div>
            
            <!-- Filter by type -->
            <select class="text-sm border border-gray-300 rounded px-2 py-1" @change="filterByType($event.target.value)">
                <option value="">All Types</option>
                <option value="basic">Basic Cards</option>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="true_false">True/False</option>
                <option value="cloze">Cloze Deletion</option>
                <option value="typed_answer">Typed Answer</option>
                <option value="image_occlusion">Image Occlusion</option>
            </select>
            
            <!-- Filter by difficulty -->
            <select class="text-sm border border-gray-300 rounded px-2 py-1" @change="filterByDifficulty($event.target.value)">
                <option value="">All Difficulties</option>
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
            </select>
        </div>
    </div>

    <!-- Flashcard List -->
    <div class="border border-gray-200 rounded-lg">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
            <div class="flex items-center">
                <input type="checkbox" 
                       class="mr-3" 
                       :checked="allVisible"
                       @change="toggleAllVisible()"
                       data-testid="select-all-checkbox">
                <h5 class="text-sm font-medium text-gray-900">
                    Select Flashcards to Export
                </h5>
            </div>
        </div>
        
        <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
            @forelse($flashcards as $flashcard)
                <div class="p-4 hover:bg-gray-50 flashcard-item" 
                     data-type="{{ $flashcard->card_type }}" 
                     data-difficulty="{{ $flashcard->difficulty_level }}">
                    <div class="flex items-start space-x-3">
                        <input type="checkbox" 
                               class="mt-1 card-checkbox" 
                               value="{{ $flashcard->id }}"
                               @change="updateSelection({{ $flashcard->id }}, $event.target.checked)"
                               data-testid="card-checkbox-{{ $flashcard->id }}">
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           @switch($flashcard->card_type)
                                               @case('basic')
                                                   bg-blue-100 text-blue-800
                                                   @break
                                               @case('multiple_choice')
                                                   bg-green-100 text-green-800
                                                   @break
                                               @case('true_false')
                                                   bg-yellow-100 text-yellow-800
                                                   @break
                                               @case('cloze')
                                                   bg-purple-100 text-purple-800
                                                   @break
                                               @case('typed_answer')
                                                   bg-indigo-100 text-indigo-800
                                                   @break
                                               @case('image_occlusion')
                                                   bg-pink-100 text-pink-800
                                                   @break
                                               @default
                                                   bg-gray-100 text-gray-800
                                           @endswitch">
                                    {{ ucfirst(str_replace('_', ' ', $flashcard->card_type)) }}
                                </span>
                                
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                           @switch($flashcard->difficulty_level)
                                               @case('easy')
                                                   bg-green-100 text-green-700
                                                   @break
                                               @case('medium')
                                                   bg-yellow-100 text-yellow-700
                                                   @break
                                               @case('hard')
                                                   bg-red-100 text-red-700
                                                   @break
                                           @endswitch">
                                    {{ ucfirst($flashcard->difficulty_level) }}
                                </span>
                            </div>
                            
                            <div class="text-sm">
                                <div class="font-medium text-gray-900 mb-1">
                                    {{ Str::limit($flashcard->question, 100) }}
                                </div>
                                <div class="text-gray-600">
                                    {{ Str::limit($flashcard->answer, 100) }}
                                </div>
                                
                                @if($flashcard->hint)
                                    <div class="text-xs text-gray-500 mt-1">
                                        Hint: {{ Str::limit($flashcard->hint, 80) }}
                                    </div>
                                @endif
                                
                                @if(!empty($flashcard->tags))
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach(array_slice($flashcard->tags, 0, 3) as $tag)
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">
                                                #{{ $tag }}
                                            </span>
                                        @endforeach
                                        @if(count($flashcard->tags) > 3)
                                            <span class="text-xs text-gray-400">+{{ count($flashcard->tags) - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    No flashcards found.
                </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if($flashcards->hasPages())
            <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                {{ $flashcards->links() }}
            </div>
        @endif
    </div>

    <!-- Export Options -->
    <div class="bg-blue-50 rounded-lg p-4">
        <h5 class="text-sm font-medium text-blue-800 mb-3">Export Selected Cards</h5>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <!-- Export Format -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Format</label>
                <select name="export_format" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    @foreach($exportFormats as $key => $name)
                        <option value="{{ $key }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Deck Name (for Anki) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Deck Name</label>
                <input type="text" 
                       name="deck_name" 
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                       placeholder="Enter deck name"
                       value="{{ $unit->name }}">
            </div>
        </div>
        
        <div class="flex items-center mb-4">
            <input type="checkbox" name="include_metadata" class="mr-2" checked>
            <label class="text-sm text-gray-700">Include metadata (for JSON format)</label>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" 
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 
                           hover:bg-gray-50 transition-colors"
                    onclick="showExportOptions()">
                Cancel
            </button>
            <button type="button" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium 
                           hover:bg-blue-700 transition-colors"
                    @click="previewSelectedExport()"
                    :disabled="selectedCount === 0"
                    :class="{ 'opacity-50 cursor-not-allowed': selectedCount === 0 }"
                    data-testid="preview-selected">
                Preview Selected (<span x-text="selectedCount"></span>)
            </button>
        </div>
    </div>
</div>

<script>
function bulkExportSelection() {
    return {
        selectedIds: new Set(),
        
        get selectedCount() {
            return this.selectedIds.size;
        },
        
        get allVisible() {
            const visibleCheckboxes = document.querySelectorAll('.flashcard-item:not([style*="display: none"]) .card-checkbox');
            return visibleCheckboxes.length > 0 && Array.from(visibleCheckboxes).every(cb => cb.checked);
        },
        
        updateSelection(id, checked) {
            if (checked) {
                this.selectedIds.add(id);
            } else {
                this.selectedIds.delete(id);
            }
        },
        
        selectAll() {
            document.querySelectorAll('.card-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                this.selectedIds.add(parseInt(checkbox.value));
            });
        },
        
        selectNone() {
            document.querySelectorAll('.card-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                this.selectedIds.delete(parseInt(checkbox.value));
            });
            this.selectedIds.clear();
        },
        
        toggleAllVisible() {
            const visibleCheckboxes = document.querySelectorAll('.flashcard-item:not([style*="display: none"]) .card-checkbox');
            const shouldCheck = !this.allVisible;
            
            visibleCheckboxes.forEach(checkbox => {
                checkbox.checked = shouldCheck;
                const id = parseInt(checkbox.value);
                if (shouldCheck) {
                    this.selectedIds.add(id);
                } else {
                    this.selectedIds.delete(id);
                }
            });
        },
        
        invertSelection() {
            document.querySelectorAll('.card-checkbox').forEach(checkbox => {
                const id = parseInt(checkbox.value);
                checkbox.checked = !checkbox.checked;
                if (checkbox.checked) {
                    this.selectedIds.add(id);
                } else {
                    this.selectedIds.delete(id);
                }
            });
        },
        
        filterByType(type) {
            document.querySelectorAll('.flashcard-item').forEach(item => {
                if (!type || item.dataset.type === type) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        },
        
        filterByDifficulty(difficulty) {
            document.querySelectorAll('.flashcard-item').forEach(item => {
                if (!difficulty || item.dataset.difficulty === difficulty) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        },
        
        previewSelectedExport() {
            if (this.selectedCount === 0) {
                alert('Please select at least one flashcard to export.');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('flashcards.export.preview', $unit->id) }}';
            form.style.display = 'none';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            // Add export format
            const formatSelect = document.querySelector('select[name="export_format"]');
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export_format';
            formatInput.value = formatSelect.value;
            form.appendChild(formatInput);
            
            // Add selected cards
            Array.from(this.selectedIds).forEach(id => {
                const cardInput = document.createElement('input');
                cardInput.type = 'hidden';
                cardInput.name = 'selected_cards[]';
                cardInput.value = id;
                form.appendChild(cardInput);
            });
            
            // Add deck name
            const deckNameInput = document.querySelector('input[name="deck_name"]');
            if (deckNameInput.value) {
                const deckInput = document.createElement('input');
                deckInput.type = 'hidden';
                deckInput.name = 'deck_name';
                deckInput.value = deckNameInput.value;
                form.appendChild(deckInput);
            }
            
            // Add metadata option
            const metadataCheckbox = document.querySelector('input[name="include_metadata"]');
            const metadataInput = document.createElement('input');
            metadataInput.type = 'hidden';
            metadataInput.name = 'include_metadata';
            metadataInput.value = metadataCheckbox.checked ? '1' : '0';
            form.appendChild(metadataInput);
            
            // Submit form via HTMX
            document.body.appendChild(form);
            htmx.ajax('POST', form.action, {
                source: form,
                target: '#export-content',
                swap: 'innerHTML'
            });
            document.body.removeChild(form);
        }
    };
}

function showExportOptions() {
    htmx.ajax('GET', '{{ route('flashcards.export.options', $unit->id) }}', {
        target: '#export-content',
        swap: 'innerHTML'
    });
}
</script>