@extends('layouts.app')

@section('title', 'Kunjungan Web')

@section('content')
    @php $fmt = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y, H:i') : '-'; @endphp

    <style>
        .v-spark {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 90px;
            margin-top: 4px;
        }

        .v-spark .bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: var(--muted, #6b7280);
            font-size: 10px;
        }

        .v-spark .bar i {
            display: block;
            width: 100%;
            max-width: 22px;
            min-height: 3px;
            border-radius: 6px 6px 2px 2px;
            background: linear-gradient(180deg, #ff8a3d, #ff6a1a);
        }

        .v-ipcell {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .v-ua {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--muted, #6b7280);
            font-size: 12px;
        }
    </style>

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Kunjungan Web</h1>
                <p>Berapa kali halaman depan dikunjungi dan dari IP mana saja. Kunjungan bot/crawler disembunyikan
                    secara default.</p>
            </div>
        </div>

        {{-- ringkasan --}}
        <section class="p-stats" aria-label="Ringkasan kunjungan">
            <div class="p-stat">
                <div class="p-stat-main">
                    <span class="p-stat-label">Total kunjungan</span>
                    <strong class="p-stat-value">{{ number_format($summary['total'], 0, ',', '.') }}</strong>
                </div>
            </div>
            <div class="p-stat">
                <div class="p-stat-main">
                    <span class="p-stat-label">Pengunjung unik (IP)</span>
                    <strong class="p-stat-value">{{ number_format($summary['unique'], 0, ',', '.') }}</strong>
                </div>
            </div>
            <div class="p-stat">
                <div class="p-stat-main">
                    <span class="p-stat-label">Hari ini</span>
                    <strong class="p-stat-value">{{ number_format($summary['today'], 0, ',', '.') }}</strong>
                </div>
            </div>
            <div class="p-stat">
                <div class="p-stat-main">
                    <span class="p-stat-label">7 hari terakhir</span>
                    <strong class="p-stat-value">{{ number_format($summary['week'], 0, ',', '.') }}</strong>
                </div>
            </div>
        </section>

        {{-- tren 14 hari --}}
        <section class="p-card p-section">
            <h3 style="margin:0 0 4px">Tren 14 hari terakhir</h3>
            <div class="v-spark">
                @foreach ($daily as $d)
                    <div class="bar" title="{{ $d['label'] }}: {{ $d['total'] }} kunjungan">
                        <i style="height: {{ max(3, (int) round($d['total'] / $dailyMax * 78)) }}px"></i>
                        <span>{{ $d['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- filter --}}
        <div class="p-card">
            <form class="p-toolbar" method="GET">
                <input type="text" name="ip" value="{{ $ip }}" placeholder="Cari / filter IP…"
                    inputmode="numeric" aria-label="Filter IP" style="min-width:200px">
                <label class="p-check" style="display:inline-flex;align-items:center;gap:6px">
                    <input type="checkbox" name="bots" value="1" onchange="this.form.submit()"
                        @checked($withBots)>
                    <span>Tampilkan bot/crawler</span>
                </label>
                <button class="p-btn p-btn-primary" type="submit"><i data-lucide="search"></i> Terapkan</button>
                @if ($ip !== '' || $withBots)
                    <a class="p-btn p-btn-ghost" href="{{ route('superadmin.visits.index') }}">Reset</a>
                @endif
            </form>

            {{-- kelompok per IP --}}
            <div class="p-table-wrap">
                <table class="p-table p-table-plain">
                    <thead>
                        <tr>
                            <th>Alamat IP</th>
                            <th class="text-end">Jumlah kunjungan</th>
                            <th>Pertama</th>
                            <th>Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perIp as $row)
                            <tr>
                                <td><span class="v-ipcell">{{ $row->ip }}</span></td>
                                <td class="text-end">{{ number_format($row->total, 0, ',', '.') }}</td>
                                <td>{{ $fmt($row->first_at) }}</td>
                                <td>{{ $fmt($row->last_at) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><span class="p-hint">Belum ada kunjungan tercatat.</span></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-footer">{{ $perIp->links('vendor.pagination.custom') }}</div>
        </div>

        {{-- kunjungan terbaru --}}
        <div class="p-card">
            <h3 style="margin:0 0 10px">Kunjungan terbaru{{ $ip !== '' ? ' · IP mengandung "' . $ip . '"' : '' }}</h3>
            <div class="p-table-wrap">
                <table class="p-table p-table-plain">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>IP</th>
                            <th>Halaman</th>
                            <th>Perangkat / browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $v)
                            <tr>
                                <td>{{ $fmt($v->visited_at) }}</td>
                                <td><span class="v-ipcell">{{ $v->ip }}</span>
                                    @if ($v->is_bot)
                                        <span class="p-chip" style="font-size:10px">bot</span>
                                    @endif
                                </td>
                                <td>{{ $v->path }}</td>
                                <td><span class="v-ua" title="{{ $v->user_agent }}">{{ $v->user_agent ?: '-' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><span class="p-hint">Belum ada kunjungan.</span></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
