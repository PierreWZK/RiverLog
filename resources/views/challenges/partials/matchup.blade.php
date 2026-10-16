{{-- Deux mains face à face, et le board s'il y en a un --}}
@use('App\Support\Cards')

<div class="matchup">
    <div class="matchup-hand">
        <span class="matchup-label">Main A</span>
        <span class="cards-lg">{{ Cards::html($q['hand_a']) }}</span>
    </div>
    <span class="matchup-vs">vs</span>
    <div class="matchup-hand">
        <span class="matchup-label">Main B</span>
        <span class="cards-lg">{{ Cards::html($q['hand_b']) }}</span>
    </div>
    <div class="matchup-board">
        <span class="matchup-label">Board</span>
        @if ($q['board'])
            <span class="cards-lg">{{ Cards::html($q['board']) }}</span>
        @else
            <span class="muted">Tapis avant le flop</span>
        @endif
    </div>
</div>
