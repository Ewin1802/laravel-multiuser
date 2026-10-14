@php
    $sidebarUser = auth()->user();
    $isSuper = $sidebarUser?->role === 'superadmin';
    $sidebarStore = $sidebarUser?->store;

    // Superadmin memakai nama platform; pemilik toko memakai nama tokonya
    $sidebarSetting = $appSetting ?? \App\Models\Setting::first();
    $sidebarName = $isSuper
        ? $sidebarSetting?->store_name ?? config('app.name')
        : $sidebarStore?->name ?? config('app.name');
    $sidebarLogo = $isSuper ? ($sidebarSetting?->logo ? $sidebarSetting->logo_url : null) : $sidebarStore?->logo_url;
    $homeRoute = $isSuper ? 'superadmin.dashboard' : 'dashboard';

    if ($isSuper) {
        $pendingStores = \App\Models\Store::where('status', 'pending')->count();
        $pendingPayments = \App\Models\SubscriptionPayment::where('status', 'pending')->count();

        $menuGroups = [
            'Platform' => [
                [
                    'route' => 'superadmin.dashboard',
                    'active' => 'superadmin.dashboard',
                    'icon' => 'layout-dashboard',
                    'label' => 'Ringkasan',
                ],
                [
                    'route' => 'superadmin.stores.index',
                    'active' => 'superadmin.stores.*',
                    'icon' => 'store',
                    'label' => 'Toko',
                    'badge' => $pendingStores,
                    'badgeClass' => 'is-warning',
                    'badgeTitle' => $pendingStores . ' toko menunggu persetujuan',
                    'params' => $pendingStores ? ['state' => 'pending'] : [],
                ],
                [
                    'route' => 'superadmin.payments.index',
                    'active' => 'superadmin.payments.*',
                    'icon' => 'banknote',
                    'label' => 'Pembayaran',
                    'badge' => $pendingPayments,
                    'badgeClass' => 'is-warning',
                    'badgeTitle' => $pendingPayments . ' pembayaran menunggu konfirmasi',
                ],
                [
                    'route' => 'superadmin.plans.index',
                    'active' => 'superadmin.plans.*',
                    'icon' => 'tags',
                    'label' => 'Paket',
                ],
                [
                    'route' => 'superadmin.promos.index',
                    'active' => 'superadmin.promos.*',
                    'icon' => 'ticket-percent',
                    'label' => 'Kode Promo',
                ],
                [
                    'route' => 'superadmin.tamper.index',
                    'active' => 'superadmin.tamper.*',
                    'icon' => 'clock-alert',
                    'label' => 'Log Jam HP',
                ],
            ],
            'Sistem' => [
                [
                    'route' => 'superadmin.settings.edit',
                    'active' => 'superadmin.settings.*',
                    'icon' => 'settings',
                    'label' => 'Pengaturan',
                ],
            ],
        ];
    } else {
        // Pengingat hutang supplier: nota tempo yang lewat / segera jatuh tempo
        $payables = \App\Support\PayableAlerts::summary();

        // Piutang pelanggan (nota Tempo) yang sudah lewat jatuh tempo
        $receivableOverdue = \App\Models\Order::credit()
            ->openReceivable()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $receivableOpen = \App\Models\Order::credit()->openReceivable()->count();

        // Batch kedaluwarsa yang belum dicatat rusak/retur
        $expiredBatches = \App\Models\StockBatch::where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->count();

        $subState = $sidebarStore?->subscription()->state();

        $menuGroups = [
            'Utama' => [
                ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                ['route' => 'orders.index', 'active' => 'orders.*', 'icon' => 'receipt-text', 'label' => 'Transaksi'],
            ],
            'Keuangan' => [
                [
                    'route' => 'receivables.index',
                    'active' => 'receivables.*',
                    'icon' => 'hand-coins',
                    'label' => 'Piutang',
                    'badge' => $receivableOverdue > 0 ? $receivableOverdue : $receivableOpen,
                    'badgeClass' => $receivableOverdue > 0 ? 'is-danger' : 'is-warning',
                    'badgeTitle' =>
                        $receivableOverdue > 0
                            ? $receivableOverdue . ' nota tempo lewat jatuh tempo'
                            : $receivableOpen . ' nota tempo belum lunas',
                    'params' => $receivableOverdue > 0 ? ['status' => 'overdue'] : [],
                ],
                ['route' => 'expenses.index', 'active' => 'expenses.*', 'icon' => 'wallet', 'label' => 'Pengeluaran'],
            ],
            'Katalog' => [
                ['route' => 'products.index', 'active' => 'products.*', 'icon' => 'package', 'label' => 'Produk'],
                [
                    'route' => 'categories.index',
                    'active' => 'categories.*',
                    'icon' => 'layers-3',
                    'label' => 'Kategori',
                ],
            ],
            'Stok & Pembelian' => [
                [
                    'route' => 'stock-ins.index',
                    'active' => 'stock-ins.*',
                    'icon' => 'package-plus',
                    'label' => 'Stok Masuk',
                    'badge' => $payables->count,
                    'badgeClass' => $payables->overdue_count > 0 ? 'is-danger' : 'is-warning',
                    'badgeTitle' =>
                        $payables->overdue_count > 0
                            ? $payables->overdue_count . ' nota lewat jatuh tempo'
                            : $payables->soon_count . ' nota segera jatuh tempo',
                    'params' =>
                        $payables->count > 0
                            ? ['payment' => 'unpaid', 'start_date' => '2000-01-01', 'end_date' => now()->toDateString()]
                            : [],
                ],
                [
                    'route' => 'expiry.index',
                    'active' => 'expiry.*',
                    'icon' => 'calendar-x',
                    'label' => 'Expire & Barang Keluar',
                    'badge' => $expiredBatches,
                    'badgeClass' => 'is-danger',
                    'badgeTitle' => $expiredBatches . ' batch kedaluwarsa belum dicatat',
                ],
                [
                    'route' => 'stock-conversions.index',
                    'active' => 'stock-conversions.*',
                    'icon' => 'package-open',
                    'label' => 'Buka Kemasan',
                ],
                ['route' => 'suppliers.index', 'active' => 'suppliers.*', 'icon' => 'truck', 'label' => 'Supplier'],
                [
                    'route' => 'reports.supplier-prices',
                    'active' => 'reports.supplier-prices',
                    'icon' => 'scale',
                    'label' => 'Supplier Termurah',
                ],
            ],
            'Toko' => [
                ['route' => 'store.profile', 'active' => 'store.*', 'icon' => 'store', 'label' => 'Profil & Perangkat'],
                [
                    'route' => 'billing.index',
                    'active' => 'billing.*',
                    'icon' => 'credit-card',
                    'label' => 'Langganan',
                    'badge' => in_array($subState, ['grace', 'expired', 'suspended'], true) ? 1 : 0,
                    'badgeClass' => 'is-danger',
                    'badgeTitle' => 'Perlu perpanjangan',
                ],
            ],
        ];
    }
@endphp

<aside class="sidebar" id="sidebar">

    {{-- Brand --}}
    <a href="{{ route($homeRoute) }}" class="sidebar-brand" title="{{ $sidebarName }}">
        <span class="brand-mark">
            @if ($sidebarLogo)
                <img src="{{ $sidebarLogo }}" alt="{{ $sidebarName }}">
            @else
                <i data-lucide="cog"></i>
            @endif
        </span>
        <span class="brand-copy">
            <strong>{{ $sidebarName }}</strong>
            <small>{{ $isSuper ? 'Superadmin' : 'Panel Toko' }}</small>
        </span>
    </a>

    {{-- Menu --}}
    <nav class="sidebar-menu" aria-label="Menu admin">
        @foreach ($menuGroups as $group => $items)
            <span class="menu-group">{{ $group }}</span>

            @foreach ($items as $item)
                @continue(!Route::has($item['route']))

                <a href="{{ route($item['route'], $item['params'] ?? []) }}"
                    title="{{ $item['badgeTitle'] ?? $item['label'] }}"
                    class="{{ request()->routeIs($item['active']) ? 'active' : '' }}"
                    @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                    <span class="menu-icon"><i data-lucide="{{ $item['icon'] }}"></i></span>
                    <span class="menu-title">{{ $item['label'] }}</span>

                    @if (($item['badge'] ?? 0) > 0)
                        <span class="menu-badge {{ $item['badgeClass'] ?? '' }}">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        @endforeach
    </nav>

    {{-- Footer --}}
    <div class="sidebar-footer">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sidebar-logout" title="Logout">
                <span class="menu-icon"><i data-lucide="log-out"></i></span>
                <span class="menu-title">Logout</span>
            </button>
        </form>
    </div>

</aside>

{{-- Latar gelap di HP; klik untuk menutup sidebar --}}
<div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
