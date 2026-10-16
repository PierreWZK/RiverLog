@extends('layouts.app')

@section('title', 'Créer un compte')

@section('content')
    <div class="auth">
        <form method="POST" action="{{ route('register') }}" class="panel auth-card" novalidate>
            @csrf
            <span class="feature-icon chip-green">♥</span>
            <h1>Créer un compte</h1>
            <p class="page-sub">Gratuit. Vos sessions ne sont visibles que par vous.</p>

            <label class="field">
                <span>Prénom ou pseudo</span>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="60" autocomplete="nickname"
                       @error('name') aria-invalid="true" @enderror>
                @error('name') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Adresse e-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                       @error('email') aria-invalid="true" @enderror>
                @error('email') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Mot de passe</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password"
                       @error('password') aria-invalid="true" @enderror>
                <small class="hint">8 caractères minimum.</small>
                @error('password') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Confirmer le mot de passe</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>

            <p class="auth-switch">
                Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a>
            </p>
        </form>
    </div>
@endsection
