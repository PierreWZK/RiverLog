@extends('layouts.app')

@section('title', 'Modifier une session')

@section('content')
    <div class="page-head">
        <div>
            <a href="{{ route('dashboard') }}" class="back">← Dashboard</a>
            <h1>Modifier la session</h1>
            <p class="page-sub">Du {{ $session->played_at->format('d/m/Y à H:i') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('sessions.update', $session) }}" class="panel form" novalidate>
        @csrf
        @method('PUT')
        @include('sessions._form')

        <div class="form-actions">
            <a href="{{ route('dashboard') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/session-form.js') }}"></script>
@endpush
