@php
    $__tones = [
        'trial' => 'tone-blue', 'active' => 'tone-green', 'grace' => 'tone-amber',
        'expired' => 'tone-red', 'suspended' => 'tone-red', 'pending' => 'tone-gray',
    ];
@endphp
<span class="s-badge {{ $__tones[$state] ?? 'tone-gray' }}">{{ \App\Support\Subscription::LABELS[$state] ?? $state }}</span>
