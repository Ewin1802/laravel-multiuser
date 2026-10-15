@extends('layouts.app')

@section('title', 'Log Jam HP')

@section('content')
    @php $fmt = fn($d) => $d ? $d->translatedFormat('d M Y, H:i') : '-'; @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Log Jam HP</h1>
                <p>Kejadian jam HP dimundurkan, melompat, atau melewati batas offline, dari semua toko.</p>
            </div>
        </div>

        @if ($top->isNotEmpty())
            <section class="p-card p-section">
                <h3 style="margin:0 0 8px">Terbanyak 30 hari terakhir</h3>
                <div class="s-actions-row">
                    @foreach ($top as $t)
                        <a class="p-chip" href="{{ route('superadmin.stores.show', $t->store_id) }}">{{ $storeNames[$t->store_id] ?? '#' . $t->store_id }} · {{ $t->total }}</a>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="p-card">
            <form class="p-toolbar" method="GET">
                <select name="kind" onchange="this.form.submit()" aria-label="Jenis">
                    <option value="">Semua jenis</option>
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <div class="p-table-wrap">
                <table class="p-table p-table-plain">
                    <thead><tr><th>Diterima</th><th>Toko</th><th>Jenis</th><th>Jam HP</th><th>Jam sebenarnya</th><th class="text-end">Selisih jam HP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $t)
                            <tr>
                                <td>{{ $fmt($t->created_at) }}</td>
                                <td><a class="p-link" href="{{ route('superadmin.stores.show', $t->store_id) }}">{{ $storeNames[$t->store_id] ?? '#' . $t->store_id }}</a></td>
                                <td>{{ $kinds[$t->kind] ?? $t->kind }}</td>
                                        @include('pages.superadmin.tamper._cells')
                            </tr>
                        @empty
                            <tr><td colspan="6"><span class="p-hint">Belum ada kejadian.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-footer">{{ $logs->links('vendor.pagination.custom') }}</div>
        </div>
    </div>
@endsection
