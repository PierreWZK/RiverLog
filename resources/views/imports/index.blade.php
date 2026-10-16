@extends('layouts.app')

@section('title', 'Import')

@section('content')
    <div class="page-head">
        <div>
            <h1>Importer un historique</h1>
            <p class="page-sub">Vos parties, résultats et sessions sont calculés automatiquement à partir de vos mains.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="panel dropzone" data-dropzone
          data-max-files="{{ \App\Http\Controllers\HandImportController::MAX_FILES }}" data-max-size="{{ 10 * 1024 * 1024 }}">
        @csrf
        <label class="dropzone-area">
            <input type="file" name="files[]" accept=".txt,.zip" multiple required data-dropzone-input>
            <span class="feature-icon chip-blue">♣</span>
            <strong data-dropzone-label>Déposez vos fichiers ici ou cliquez pour les choisir</strong>
            <span class="hint">Export d'historique de mains Betclic, en .txt ou .zip · 10 Mo max par fichier · {{ \App\Http\Controllers\HandImportController::MAX_FILES }} fichiers max</span>
        </label>
        @error('files') <small class="error">{{ $message }}</small> @enderror
        @foreach ($errors->get('files.*') as $messages)
            <small class="error">{{ $messages[0] }}</small>
        @endforeach
        <small class="error" data-dropzone-error hidden></small>

        <div class="import-progress" data-import-progress hidden>
            <div class="import-progress-head">
                <span data-progress-label>Préparation…</span>
                <strong data-progress-percent>0 %</strong>
            </div>
            <div class="import-progress-track" role="progressbar" aria-label="Avancement de l'import" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress-track>
                <div class="import-progress-bar" data-progress-bar></div>
            </div>
            <ol class="import-progress-files" data-progress-files></ol>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Importer</button>
            <button type="button" class="btn" data-progress-retry hidden>Réessayer les fichiers en échec</button>
            <a href="{{ route('imports.index') }}" class="btn" data-progress-done hidden>Voir l'historique</a>
        </div>
    </form>

    @if ($hasTournaments)
        <section id="parties" class="imported">
            <h2 class="section-title">Parties importées</h2>
            @include('tournaments._results', ['baseUrl' => route('imports.index'), 'showRoute' => 'tournaments.show'])
        </section>
    @endif

    <section class="panel import-rules">
        <h2 class="panel-title">Ce que fait l'import</h2>
        <ul>
            <li><strong>Aucun doublon :</strong> un fichier déjà importé est ignoré, une main déjà connue aussi. Vous pouvez réimporter sans risque.</li>
            <li><strong>Parties :</strong> buy-in, multiplicateur, place finale et gain sont lus dans les mains et comptés une seule fois par partie.</li>
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
                            <th scope="col" class="num">Parties</th>
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
                                    @if ($import->errors)
                                        <form method="POST" action="{{ route('imports.retry', $import) }}" data-retry>
                                            @csrf
                                            <button type="submit" class="icon-btn" title="Relancer l'import (relit le fichier et ajoute les mains manquantes)">
                                                <span class="sr-only">Relancer l'import {{ $import->filename }}</span>↻
                                            </button>
                                        </form>
                                    @endif
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
    <script src="{{ asset('js/rows.js') }}"></script>
@endpush
