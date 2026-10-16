@extends('layouts.app')

@section('title', 'Nouvelle session')

@section('content')
    <div class="page-head">
        <div>
            <a href="{{ route('dashboard') }}" class="back">← Dashboard</a>
            <h1>Nouvelle session</h1>
        </div>
    </div>

    <form method="POST" action="{{ route('sessions.store') }}" class="panel form" novalidate>
        @csrf
        @include('sessions._form')

        <div class="form-actions">
            <a href="{{ route('dashboard') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary">Ajouter la session</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/session-form.js') }}"></script>
@endpush
