@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <div class="auth">
        <form method="POST" action="{{ route('login') }}" class="panel auth-card" novalidate>
            @csrf
            <span class="feature-icon chip-red">♠</span>
            <h1>Connexion</h1>
            <p class="page-sub">Retrouvez votre bankroll et vos sessions.</p>

            <label class="field">
                <span>Adresse e-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       @error('email') aria-invalid="true" @enderror>
                @error('email') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Mot de passe</span>
                <input type="password" name="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" @enderror>
                @error('password') <small class="error">{{ $message }}</small> @enderror
            </label>

            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Rester connecté</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>

            <p class="auth-switch">
                Pas encore de compte ? <a href="{{ route('register') }}">Créer un compte</a>
                <br>
                Ou <a href="{{ route('demo') }}">voir un exemple de dashboard</a>
            </p>
        </form>
    </div>
@endsection
