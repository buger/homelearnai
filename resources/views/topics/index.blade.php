@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold">{{ $unit->title }} {{ __('Topics') }}</h1>
                <p class="text-gray-600 mt-2">{{ $subject->name }} → {{ $unit->title }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('units.show', ['unit' => $unit->id]) }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                    {{ __('Back to Unit') }}
                </a>
                <a href="{{ route('topics.create', [$subject->id, $unit->id]) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    {{ __('Add Topic') }}
                </a>
            </div>
        </div>

        @include('topics.partials.topics-list', compact('topics', 'unit', 'subject'))
    </div>
</div>
@endsection