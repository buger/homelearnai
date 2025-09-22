<!-- Import Preview -->
<div class="space-y-6">
    <!-- Back Button -->
    <div class="flex items-center">
        <button
            type="button"
            {{-- hx-get="{{ route('units.flashcards.import.show', $unit->id) }}" --}}
            hx-target="#import-content"
            hx-swap="innerHTML"
            class="text-gray-500 hover:text-gray-700 flex items-center">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Import
        </button>
    </div>

    <!-- Import Summary -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-blue-900 mb-2">Import Summary</h4>
        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
            <div>
                <span class="font-medium text-blue-800">Total Lines:</span> 
                <span class="text-blue-700">{{ $totalLines }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-800">Parsed Cards:</span> 
                <span class="text-blue-700">{{ $parsedCards }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-800">Delimiter:</span> 
                <span class="text-blue-700">{{ $delimiter }}</span>
            </div>
            <div>
                <span class="font-medium text-blue-800">Import Method:</span> 
                <span class="text-blue-700">{{ ucfirst($importMethod) }}</span>
            </div>
        </div>
        
        @php
            $cardTypeCounts = collect($cards)->groupBy('card_type')->map->count()->toArray();
        @endphp
        
        @if(!empty($cardTypeCounts))
            <div class="border-t border-blue-200 pt-3">
                <span class="font-medium text-blue-800">Detected Card Types:</span>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($cardTypeCounts as $type => $count)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ ucwords(str_replace('_', ' ', $type ?? 'basic')) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Validation Errors -->
    @if(!empty($validationErrors))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center mb-2">
                <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h4 class="text-lg font-semibold text-red-900">Validation Errors</h4>
            </div>
            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                @foreach($validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Parse Errors -->
    @if(!empty($parseErrors))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center mb-2">
                <svg class="w-5 h-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 19c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <h4 class="text-lg font-semibold text-yellow-900">Parse Warnings</h4>
            </div>
            <p class="text-sm text-yellow-700 mb-2">Some lines could not be parsed but the import can still proceed:</p>
            <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                @foreach($parseErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Preview Cards -->
    @if(count($cards) > 0)
        <div>
            <h4 class="text-lg font-semibold text-gray-900 mb-4">
                Preview (showing first {{ min(10, count($cards)) }} of {{ count($cards) }} cards)
            </h4>
            
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hint</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(array_slice($cards, 0, 10) as $index => $card)
                                <tr class="{{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                                    <td class="px-4 py-4 text-sm">
                                        @php
                                            $cardType = $card['card_type'] ?? 'basic';
                                            $typeColors = [
                                                'basic' => 'bg-gray-100 text-gray-800',
                                                'multiple_choice' => 'bg-blue-100 text-blue-800',
                                                'true_false' => 'bg-green-100 text-green-800',
                                                'cloze' => 'bg-purple-100 text-purple-800',
                                                'typed_answer' => 'bg-yellow-100 text-yellow-800',
                                                'image_occlusion' => 'bg-red-100 text-red-800',
                                            ];
                                            $colorClass = $typeColors[$cardType] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                            {{ ucwords(str_replace('_', ' ', $cardType)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-xs">
                                        <div class="truncate" title="{{ $card['question'] }}">
                                            {{ Str::limit($card['question'], 60) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-xs">
                                        <div class="truncate" title="{{ $card['answer'] }}">
                                            {{ Str::limit($card['answer'], 60) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 max-w-xs">
                                        @if(!empty($card['hint']))
                                            <div class="truncate" title="{{ $card['hint'] }}">
                                                {{ Str::limit($card['hint'], 40) }}
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500">
                                        @if(!empty($card['tags']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach(array_slice($card['tags'], 0, 2) as $tag)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                        {{ $tag }}
                                                    </span>
                                                @endforeach
                                                @if(count($card['tags']) > 2)
                                                    <span class="text-xs text-gray-400">+{{ count($card['tags']) - 2 }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if(count($cards) > 10)
                    <div class="bg-gray-50 px-6 py-3 text-sm text-gray-500 text-center border-t">
                        ... and {{ count($cards) - 10 }} more cards
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Import Actions -->
    <div class="flex justify-between items-center pt-6 border-t">
        <div class="text-sm text-gray-600">
            @if($canImport)
                <span class="text-green-600 font-medium">✓ Ready to import {{ count($cards) }} flashcard(s)</span>
            @else
                <span class="text-red-600 font-medium">✗ Cannot import due to validation errors</span>
            @endif
        </div>
        
        <div class="flex space-x-3">
            <button
                type="button"
                {{-- hx-get="{{ route('units.flashcards.import.show', $unit->id) }}" --}}
                hx-target="#import-content"
                hx-swap="innerHTML"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            
            @if($canImport)
                <form hx-post="{{ route('flashcards.import.execute', $unit->id) }}" 
                      hx-target="#flashcards-list" 
                      hx-swap="innerHTML"
                      hx-on::after-request="document.getElementById('flashcard-modal-overlay')?.remove()"
                      class="inline">
                    @csrf
                    <input type="hidden" name="import_method" value="{{ $importMethod }}">
                    <input type="hidden" name="import_data" value="{{ $importData }}">
                    <input type="hidden" name="confirm_import" value="1">
                    
                    <button 
                        type="submit" 
                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors"
                        hx-confirm="Are you sure you want to import {{ count($cards) }} flashcard(s) to {{ $unit->name }}?">
                        Import {{ count($cards) }} Flashcard(s)
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>