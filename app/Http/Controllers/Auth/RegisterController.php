<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Création de compte : le compte est connecté tout de suite et arrive sur son dashboard (vide).
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // « Pierre@Mail.com » et « pierre@mail.com » sont la même adresse
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Indique ton prénom ou un pseudo.',
            'name.max' => 'Le nom ne peut pas dépasser 60 caractères.',
            'email.required' => 'Indique ton adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n\'est pas valide.',
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
            'password.required' => 'Choisis un mot de passe.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        // Le mot de passe est haché automatiquement (cast « hashed » du modèle User)
        $user = User::create($data);

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('status', 'Bienvenue '.$user->name.' ! Ajoutez votre première session.');
    }
}
