@extends('layouts.app')

@section('title', 'Toko')

@section('content')
    @php $fmt = fn($d) => $d ? $d->translatedFormat('d M Y') : '-'; @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Toko</h1>
                @php $cafeCount = $stores->where('feature_draft_order', true)->count(); @endphp
                <p>{{ $stores->count() }} toko{{ $state ? ' · ' . (\App\Support\Subscription::LABELS[$state] ?? $state) : '' }}
                    @if ($cafeCount)
                        · <span class="s-badge tone-cafe"><i data-lucide="coffee"></i> {{ $cafeCount }} cafe (draft order)</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="p-card">
            <form class="p-toolbar" method="GET">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Nama toko, email, no. HP..." aria-label="Cari toko">
                </label>
                <select name="state" onchange="this.form.submit()" aria-label="Filter status">
                    <option value="">Semua status</option>
                    @foreach (\App\Support\Subscription::LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="mode" onchange="this.form.submit()" aria-label="Filter jenis toko">
                    <option value="">Semua jenis</option>
                    <option value="cafe" @selected(($mode ?? null) === 'cafe')>Cafe · draft order aktif</option>
                    <option value="retail" @selected(($mode ?? null) === 'retail')>Toko biasa</option>
                </select>
            </form>

            <div class="p-table-wrap">
                <table class="p-table">
                    <thead>
                        <tr>
                            <th>Toko</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th class="cell-hide-sm">Berakhir</th>
                            <th class="cell-hide-sm">Daftar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $s)
                            @php $sub = $s->subscription(); @endphp
                            <tr>
                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb has-tone {{ $s->feature_draft_order ? 'tone-cafe' : 'tone-orange' }}">
                                            <i data-lucide="{{ $s->feature_draft_order ? 'coffee' : 'store' }}"></i>
                                        </div>
                                        <div class="p-item-text">
                                            <div class="p-name">{{ $s->name }}</div>
                                            <small class="p-desc">{{ $s->owner->name ?? '-' }} · {{ $s->owner->email ?? '' }} · {{ $s->owner->phone_number ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($s->feature_draft_order)
                                        <span class="s-badge tone-cafe" title="Draft order (cafe) diaktifkan pemilik toko">
                                            <i data-lucide="coffee"></i> Cafe · draft order
                                        </span>
                                    @else
                                        <span class="s-badge"><i data-lucide="store"></i> Toko</span>
                                    @endif
                                </td>
                                <td>@include('pages.superadmin._state', ['state' => $sub->state()])</td>
                                <td class="cell-hide-sm">{{ $fmt($sub->endsAt()) }}</td>
                                <td class="cell-hide-sm">{{ $fmt($s->created_at) }}</td>
                                <td class="cell-actions">
                                    <div class="p-actions">
                                        @if ($s->status === 'pending')
                                            <form method="POST" action="{{ route('superadmin.stores.approve', $s) }}"
                                                data-confirm="Setujui {{ $s->name }}? Trial langsung dimulai.">
                                                @csrf
                                                <button type="submit" class="p-icon-btn" title="Setujui"><i data-lucide="check"></i></button>
                                            </form>
                                        @endif
                                        <a href="{{ route('superadmin.stores.show', $s) }}" class="p-icon-btn" title="Detail"><i data-lucide="eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row"><td colspan="6"><div class="p-empty"><h4>Tidak ada toko</h4></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
