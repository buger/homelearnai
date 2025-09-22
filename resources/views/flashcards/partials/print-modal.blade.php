<!-- Print Options Modal -->
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="print-modal-backdrop">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    Print Flashcards - {{ $unit->name }}
                </h3>
                <button 
                    type="button" 
                    class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600"
                    onclick="document.getElementById('print-modal-backdrop').remove()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Print Form -->
            <form id="print-form" class="mt-6">
                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Layout Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Print Layout</label>
                        <div class="space-y-2">
                            @foreach($layouts as $key => $label)
                                <label class="flex items-center">
                                    <input type="radio" name="layout" value="{{ $key }}" 
                                           class="mr-2 text-blue-600 focus:ring-blue-500" 
                                           {{ $key === 'index' ? 'checked' : '' }}
                                           onchange="updatePreview()">
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Page Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Page Size</label>
                        <select name="page_size" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="updatePreview()">
                            @foreach($pageSizes as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'letter' ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Font Size -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Font Size</label>
                        <select name="font_size" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="updatePreview()">
                            <option value="small">Small (10pt)</option>
                            <option value="medium" selected>Medium (12pt)</option>
                            <option value="large">Large (14pt)</option>
                        </select>
                    </div>

                    <!-- Margins -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Margins</label>
                        <select name="margin" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="updatePreview()">
                            <option value="tight">Tight (10mm)</option>
                            <option value="normal" selected>Normal (15mm)</option>
                            <option value="wide">Wide (25mm)</option>
                        </select>
                    </div>

                    <!-- Color Mode -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Mode</label>
                        <select name="color_mode" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="updatePreview()">
                            <option value="color" selected>Color</option>
                            <option value="grayscale">Grayscale</option>
                        </select>
                    </div>

                    <!-- Content Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content Options</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="include_answers" value="1" checked 
                                       class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updatePreview()">
                                <span class="text-sm text-gray-700">Include answers</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_hints" value="1" checked 
                                       class="mr-2 text-blue-600 focus:ring-blue-500" onchange="updatePreview()">
                                <span class="text-sm text-gray-700">Include hints</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Card Selection -->
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Cards to Print</label>
                        <button type="button" 
                                hx-get="{{ route('units.flashcards.print.bulk', $unit->id) }}"
                                hx-target="#bulk-selection-container"
                                hx-swap="innerHTML"
                                class="text-sm text-blue-600 hover:text-blue-700 focus:outline-none">
                            Select specific cards
                        </button>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        <span id="selected-count">{{ $totalCards }}</span> of {{ $totalCards }} cards selected
                    </div>
                    <div id="bulk-selection-container" class="hidden">
                        <!-- Bulk selection interface will be loaded here -->
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-medium text-gray-700">Preview</h4>
                        <button type="button" onclick="updatePreview()" 
                                class="text-sm text-blue-600 hover:text-blue-700 focus:outline-none">
                            Refresh Preview
                        </button>
                    </div>
                    <div id="print-preview" class="border border-gray-200 rounded-lg p-4 bg-gray-50 min-h-32">
                        <div class="text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p>Click "Preview" to see how your flashcards will look when printed</p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Action Buttons -->
            <div class="mt-6 flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" 
                        onclick="document.getElementById('print-modal-backdrop').remove()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="button" 
                        onclick="generatePreview()"
                        class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 border border-blue-300 rounded-md hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Preview
                </button>
                <button type="button" 
                        onclick="downloadPDF()"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function generatePreview() {
    const form = document.getElementById('print-form');
    const formData = new FormData(form);
    
    // Show loading state
    document.getElementById('print-preview').innerHTML = `
        <div class="text-center text-gray-500">
            <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-2"></div>
            <p>Generating preview...</p>
        </div>
    `;

    fetch('{{ route('flashcards.print.preview', $unit->id) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'text/html'
        }
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('print-preview').innerHTML = html;
    })
    .catch(error => {
        console.error('Preview error:', error);
        document.getElementById('print-preview').innerHTML = `
            <div class="text-center text-red-500">
                <p>Unable to generate preview. Please try again.</p>
            </div>
        `;
    });
}

function downloadPDF() {
    const form = document.getElementById('print-form');
    const formData = new FormData(form);

    // Show loading state on button
    const downloadButton = event.target;
    const originalText = downloadButton.innerHTML;
    downloadButton.innerHTML = '<div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full inline mr-2"></div>Generating PDF...';
    downloadButton.disabled = true;

    fetch('{{ route('flashcards.print.download', $unit->id) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('PDF generation failed');
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `flashcards-{{ $unit->name }}-${Date.now()}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        // Close modal after successful download
        document.getElementById('print-modal-backdrop').remove();
    })
    .catch(error => {
        console.error('Download error:', error);
        alert('Unable to generate PDF. Please try again.');
    })
    .finally(() => {
        downloadButton.innerHTML = originalText;
        downloadButton.disabled = false;
    });
}

function updatePreview() {
    // Auto-generate preview when settings change (debounced)
    clearTimeout(window.previewTimeout);
    window.previewTimeout = setTimeout(generatePreview, 500);
}

function updateSelectedCount(count) {
    document.getElementById('selected-count').textContent = count;
}

// Initialize preview
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate initial preview
    setTimeout(generatePreview, 100);
});
</script>