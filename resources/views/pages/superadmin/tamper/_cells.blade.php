{{-- Sel Jam HP · Perkiraan jam asli · Selisih (dipakai di Log Jam HP & detail toko) --}}
@php
    $dev = \App\Support\ClockFormat::parse($t->device_time);
    $real = \App\Support\ClockFormat::parse($t->trusted_time);
    $gap = \App\Support\ClockFormat::drift($t->drift_seconds);
    $sameDay = $dev && $real && $dev->isSameDay($real);
@endphp
<td title="{{ $t->device_time }}">
    @if ($dev)
        <span class="s-when {{ $sameDay ? '' : 'is-off' }}">
            <b>{{ \App\Support\ClockFormat::date($dev) }}</b>
            <span>{{ \App\Support\ClockFormat::time($dev) }}</span>
        </span>
    @else
        -
    @endif
</td>
<td title="{{ $t->trusted_time }}">
    @if ($real)
        <span class="s-when">
            <b>{{ \App\Support\ClockFormat::date($real) }}</b>
            <span>{{ \App\Support\ClockFormat::time($real) }}</span>
        </span>
    @else
        -
    @endif
</td>
<td class="text-end">
    @if ($gap['tone'])
        <span class="s-badge {{ $gap['tone'] }}">{{ $gap['text'] }}</span>
    @else
        {{ $gap['text'] }}
    @endif
</td>
