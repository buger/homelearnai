@extends('layouts.app')

@section('content')
    <div class="bg-white shadow rounded-lg">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('subjects.index') }}" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 rounded-full" style="background-color: {{ $subject->color }}"></div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 whitespace-nowrap">{{ $subject->name }}</h1>
                            <p class="text-sm text-gray-600 mt-1">{{ trans_choice('messages.units_count', $units->count(), ['count' => $units->count()]) }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3">
                    <button 
                        type="button"
                        data-testid="add-unit-header-button"
                        hx-get="{{ route('units.create', $subject->id) }}"
                        hx-target="#unit-modal"
                        hx-swap="innerHTML"
                        hx-indicator=".htmx-indicator"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        {{ __('add_unit') }}
                    </button>
                    <button 
                        type="button"
                        hx-get="{{ route('subjects.edit', $subject->id) }}"
                        hx-target="#subject-modal"
                        hx-swap="innerHTML"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        {{ __('edit_subject') }}
                    </button>
                </div>
            </div>

            <!-- Flash Messages -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4" role="alert">
                    {{ session('error') }}
                </div>
            @endif
        </div>

        <!-- Units List -->
        <div class="p-6">
            <div id="units-list">
                @include('units.partials.units-list', compact('units', 'subject'))
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="htmx-indicator fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span>Loading...</span>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="unit-modal"></div>
    <div id="subject-modal"></div>
@endsection