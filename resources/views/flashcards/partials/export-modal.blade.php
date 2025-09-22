<!-- Export Flashcards Modal -->
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center p-4" x-data="{ open: true }" x-show="open" data-testid="export-modal">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-screen overflow-y-auto relative z-50" data-testid="modal-content">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Export Flashcards</h3>
            <button 
                type="button"
                class="text-gray-400 hover:text-gray-600 p-1"
                @click="$event.target.closest('.fixed').remove()"
                data-testid="close-modal">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6" id="export-content">
            <!-- Export Statistics -->
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <h4 class="text-sm font-medium text-blue-800 mb-2">Export Summary</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-blue-600">Total cards:</span>
                        <span class="font-medium">{{ $totalCards }}</span>
                    </div>
                    <div>
                        <span class="text-blue-600">Unit:</span>
                        <span class="font-medium">{{ $unit->name }}</span>
                    </div>
                </div>
            </div>

            <!-- Export Format Selection -->
            <div class="mb-6">
                <label class="text-sm font-medium text-gray-700 mb-3 block">Export Format</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($exportFormats as $key => $name)
                        <label class="relative">
                            <input type="radio" name="export_format" value="{{ $key }}" 
                                   class="sr-only peer" 
                                   @if($loop->first) checked @endif>
                            <div class="border-2 border-gray-200 rounded-lg p-3 cursor-pointer 
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50 
                                        hover:border-gray-300 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-sm">{{ $name }}</div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            @switch($key)
                                                @case('anki')
                                                    Complete Anki package with SQLite database
                                                    @break
                                                @case('quizlet')
                                                    Simple tab-separated format for Quizlet import
                                                    @break
                                                @case('csv')
                                                    Extended CSV with all card data and metadata
                                                    @break
                                                @case('json')
                                                    Complete JSON backup with full relationships
                                                    @break
                                                @case('mnemosyne')
                                                    XML format for Mnemosyne spaced repetition
                                                    @break
                                                @case('supermemo')
                                                    Simple Q&A text format for SuperMemo
                                                    @break
                                            @endswitch
                                        </div>
                                    </div>
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-300 
                                                peer-checked:border-blue-500 peer-checked:bg-blue-500
                                                flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full bg-white opacity-0 
                                                    peer-checked:opacity-100"></div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Card Selection -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-medium text-gray-700">Card Selection</label>
                    <button type="button" 
                            class="text-sm text-blue-600 hover:text-blue-800"
                            onclick="toggleBulkSelection()">
                        Advanced Selection
                    </button>
                </div>
                
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input type="radio" name="selection_mode" value="all" class="mr-2" checked>
                        <span class="text-sm">All cards ({{ $totalCards }})</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="selection_mode" value="selected" class="mr-2">
                        <span class="text-sm">Selected cards only</span>
                    </label>
                </div>

                <!-- Hidden selected cards input -->
                <input type="hidden" name="selected_cards" id="selected-cards-input">
            </div>

            <!-- Format-Specific Options -->
            <div class="mb-6" id="format-options">
                <!-- Anki Options -->
                <div class="anki-options hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Deck Name
                    </label>
                    <input type="text" 
                           name="deck_name" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                           placeholder="Enter deck name (e.g., {{ $unit->name }})"
                           value="{{ $unit->name }}">
                    <p class="text-xs text-gray-500 mt-1">
                        The name that will appear in Anki when you import this deck.
                    </p>
                </div>

                <!-- JSON Options -->
                <div class="json-options hidden">
                    <label class="flex items-center">
                        <input type="checkbox" name="include_metadata" class="mr-2" checked>
                        <span class="text-sm">Include metadata (timestamps, IDs)</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">
                        Include creation dates, IDs, and other metadata for complete backup.
                    </p>
                </div>
            </div>

            <!-- Export Size Warning -->
            @if($totalCards > $maxExportSize)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-medium text-yellow-800">Large Export Warning</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                You have {{ $totalCards }} cards, but the maximum export size is {{ $maxExportSize }}. 
                                Please select specific cards or split into multiple exports.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 
                               hover:bg-gray-50 transition-colors"
                        @click="$event.target.closest('.fixed').remove()">
                    Cancel
                </button>
                <button type="button" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium 
                               hover:bg-blue-700 transition-colors"
                        onclick="previewExport()"
                        data-testid="preview-export">
                    Preview Export
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleBulkSelection() {
    // Load bulk selection interface via HTMX
    htmx.ajax('GET', '{{ route('flashcards.export.bulk_selection', $unit->id) }}', {
        target: '#export-content',
        swap: 'innerHTML'
    });
}

function previewExport() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('flashcards.export.preview', $unit->id) }}';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);
    
    // Add form data
    const formData = new FormData();
    
    // Export format
    const selectedFormat = document.querySelector('input[name="export_format"]:checked');
    if (selectedFormat) {
        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'export_format';
        formatInput.value = selectedFormat.value;
        form.appendChild(formatInput);
    }
    
    // Card selection
    const selectionMode = document.querySelector('input[name="selection_mode"]:checked');
    if (selectionMode && selectionMode.value === 'selected') {
        const selectedCards = document.getElementById('selected-cards-input').value;
        if (selectedCards) {
            const cardsInput = document.createElement('input');
            cardsInput.type = 'hidden';
            cardsInput.name = 'selected_cards';
            cardsInput.value = selectedCards;
            form.appendChild(cardsInput);
        }
    }
    
    // Format-specific options
    const deckNameInput = document.querySelector('input[name="deck_name"]');
    if (deckNameInput && deckNameInput.value) {
        const deckInput = document.createElement('input');
        deckInput.type = 'hidden';
        deckInput.name = 'deck_name';
        deckInput.value = deckNameInput.value;
        form.appendChild(deckInput);
    }
    
    const metadataCheckbox = document.querySelector('input[name="include_metadata"]');
    if (metadataCheckbox) {
        const metadataInput = document.createElement('input');
        metadataInput.type = 'hidden';
        metadataInput.name = 'include_metadata';
        metadataInput.value = metadataCheckbox.checked ? '1' : '0';
        form.appendChild(metadataInput);
    }
    
    // Submit form via HTMX
    document.body.appendChild(form);
    htmx.ajax('POST', form.action, {
        source: form,
        target: '#export-content',
        swap: 'innerHTML'
    });
    document.body.removeChild(form);
}

// Show/hide format-specific options based on selection
document.addEventListener('change', function(e) {
    if (e.target.name === 'export_format') {
        // Hide all format options
        document.querySelectorAll('[class*="-options"]').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show relevant options
        const selectedFormat = e.target.value;
        const optionsElement = document.querySelector(`.${selectedFormat}-options`);
        if (optionsElement) {
            optionsElement.classList.remove('hidden');
        }
    }
});

// Initialize format options visibility
document.addEventListener('DOMContentLoaded', function() {
    const selectedFormat = document.querySelector('input[name="export_format"]:checked');
    if (selectedFormat) {
        const optionsElement = document.querySelector(`.${selectedFormat.value}-options`);
        if (optionsElement) {
            optionsElement.classList.remove('hidden');
        }
    }
});
</script>