<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Export Flashcards</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <form id="export-options-form" hx-post="{{ route('flashcards.export.preview', $unit->id) }}" hx-target="#export-preview-container">
            <div class="mb-3">
                <label for="export_format" class="form-label">Export Format</label>
                <select class="form-select" id="export_format" name="export_format" required>
                    <option value="">Select format...</option>
                    @foreach($exportFormats as $key => $name)
                        <option value="{{ $key }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="include_inactive" name="include_inactive" value="1">
                    <label class="form-check-label" for="include_inactive">
                        Include inactive flashcards
                    </label>
                </div>
            </div>

            @if($totalCards > $maxExportSize)
                <div class="alert alert-warning">
                    <h6>Large Export Warning</h6>
                    <p>This unit contains {{ $totalCards }} flashcards, which exceeds the maximum export size of {{ $maxExportSize }}. You'll need to select specific cards to export.</p>
                </div>
            @else
                <div class="alert alert-info">
                    <strong>{{ $totalCards }}</strong> active flashcards will be exported.
                </div>
            @endif

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Preview Export</button>
            </div>
        </form>
    </div>
</div>

<div id="export-preview-container"></div>