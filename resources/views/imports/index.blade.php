@extends('layouts.app')

@section('title', 'Import')

@section('content')
    <div class="page-head">
        <div>
            <h1>Importer un historique</h1>
            <p class="page-sub">Vos tournois, résultats et sessions sont calculés automatiquement à partir de vos mains.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="panel dropzone" data-dropzone>
        @csrf
        <label class="dropzone-area">
            <input type="file" name="files[]" accept=".txt,.zip" multiple required data-dropzone-input>
            <span class="feature-icon chip-blue">♣</span>
            <strong data-dropzone-label>Déposez vos fichiers ici ou cliquez pour les choisir</strong>
            <span class="hint">Export d'historique de mains Betclic, en .txt ou .zip · 10 Mo max par fichier · 10 fichiers max</span>
        </label>
        @error('files') <small class="error">{{ $message }}</small> @enderror
        @foreach ($errors->get('files.*') as $messages)
            <small class="error">{{ $messages[0] }}</small>
        @endforeach
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Importer</button>
        </div>
    </form>

    <section class="panel import-rules">
        <h2 class="panel-title">Ce que fait l'import</h2>
        <ul>
            <li><strong>Aucun doublon :</strong> un fichier déjà importé est ignoré, une main déjà connue aussi. Vous pouvez réimporter sans risque.</li>
            <li><strong>Tournois :</strong> buy-in, multiplicateur, place finale et gain sont lus dans les mains et comptés une seule fois par tournoi.</li>
            <li><strong>Sessions :</strong> les tournois espacés de moins de 30 min forment une session, ajoutée à votre dashboard.</li>
            <li><strong>Privé :</strong> vos fichiers et vos mains ne sont visibles que par vous.</li>
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">Historique des imports <span class="count">{{ $imports->count() }}</span></h2>

        @if ($imports->isEmpty())
            <p class="page-sub">Aucun import pour l'instant.</p>
        @else
            <div class="table-wrap">
                <table class="imports">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Fichier</th>
                            <th scope="col" class="num">Mains</th>
                            <th scope="col" class="num">Doublons</th>
                            <th scope="col" class="num">Tournois</th>
                            <th scope="col">Erreurs</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($imports as $import)
                            <tr>
                                <td class="date">{{ $import->created_at->format('d/m/Y') }} <small>{{ $import->created_at->format('H:i') }}</small></td>
                                <td class="filename">{{ $import->filename }}</td>
                                <td class="num">{{ $import->hands_imported }}</td>
                                <td class="num">{{ $import->duplicates }}</td>
                                <td class="num">{{ $import->tournaments_touched }}</td>
                                <td>
                                    @if ($import->errors)
                                        <details class="import-errors">
                                            <summary>{{ count($import->errors) }} erreur{{ count($import->errors) > 1 ? 's' : '' }}</summary>
                                            <ul>
                                                @foreach ($import->errors as $error)
                                                    <li>
                                                        @if ($error['block'] ?? 0) Main n°{{ $error['block'] }} @endif
                                                        @if ($error['hand_id'] ?? null) <code>{{ $error['hand_id'] }}</code> @endif
                                                        {{ $error['message'] }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        <span class="ok">Aucune</span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <form method="POST" action="{{ route('imports.destroy', $import) }}"
                                          data-confirm="Supprimer l'import « {{ $import->filename }} » ? Ses {{ $import->hands_imported }} mains seront retirées et les statistiques recalculées.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn is-danger" title="Supprimer l'import">
                                            <span class="sr-only">Supprimer l'import {{ $import->filename }}</span>✕
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/dashboard.js') }}"></script>
    <script src="{{ asset('js/import.js') }}"></script>
@endpush
