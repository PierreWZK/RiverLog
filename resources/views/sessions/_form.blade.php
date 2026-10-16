{{-- Champs communs à l'ajout et à la modification d'une session --}}
@php
    // Le résultat est affiché à la française dans le champ : « -12,50 »
    $netResult = old('net_result', $session->net_result !== null ? str_replace('.', ',', $session->net_result) : '');
@endphp

@if ($errors->any())
    <p class="form-alert" role="alert">Certains champs sont à corriger.</p>
@endif

<div class="form-grid">
    <label class="field">
        <span>Date et heure</span>
        <input type="datetime-local" name="played_at" required
               max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ old('played_at', $session->played_at?->format('Y-m-d\TH:i')) }}"
               @error('played_at') aria-invalid="true" @enderror>
        @error('played_at') <small class="error">{{ $message }}</small> @enderror
    </label>

    <fieldset class="field">
        <legend>Format</legend>
        <div class="segmented">
            @foreach ($formats as $value => $label)
                <label>
                    <input type="radio" name="format" value="{{ $value }}" required
                           @checked(old('format', $session->format?->value) === $value)>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('format') <small class="error">{{ $message }}</small> @enderror
    </fieldset>

    <label class="field">
        <span>Durée (minutes)</span>
        <input type="number" name="duration_minutes" required min="1" max="1440" step="1" inputmode="numeric"
               placeholder="ex. 150 pour 2 h 30"
               value="{{ old('duration_minutes', $session->duration_minutes) }}"
               @error('duration_minutes') aria-invalid="true" @enderror>
        <small class="hint" data-duration-hint></small>
        @error('duration_minutes') <small class="error">{{ $message }}</small> @enderror
    </label>

    <label class="field">
        <span>Résultat net (€)</span>
        <input type="text" name="net_result" required inputmode="decimal" autocomplete="off"
               placeholder="ex. 42,50 ou -20"
               value="{{ $netResult }}"
               @error('net_result') aria-invalid="true" @enderror>
        <small class="hint">Négatif pour une perte, 0 à l'équilibre.</small>
        @error('net_result') <small class="error">{{ $message }}</small> @enderror
    </label>

    <label class="field field-wide">
        <span>Notes <em>(facultatif)</em></span>
        <textarea name="notes" rows="3" maxlength="1000"
                  placeholder="Table, erreurs, ressenti…">{{ old('notes', $session->notes) }}</textarea>
        @error('notes') <small class="error">{{ $message }}</small> @enderror
    </label>
</div>
