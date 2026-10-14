<header class="navbar">

    <div class="navbar-left">

        <button
            type="button"
            class="menu-button"
            id="sidebarToggle">

            <i data-lucide="panel-left"></i>

        </button>

        <div class="navbar-title">

            <h1>@yield('title')</h1>

            <span>
                {{ now()->format('l, d F Y') }}
            </span>

        </div>

    </div>

    <div class="navbar-right">

        {{-- peringatan jatuh tempo server / VPS --}}
        @if (auth()->user()?->role === 'superadmin')
            @include('components.server-due')
        @endif

        <button class="icon-button">

            <i data-lucide="bell"></i>

        </button>

        <button class="icon-button">

            <i data-lucide="settings"></i>

        </button>

        <div class="profile">

            <div class="avatar">

                {{ strtoupper(substr(auth()->user()->name ?? 'A',0,1)) }}

            </div>

            <div class="profile-info">

                <strong>

                    {{ auth()->user()->name ?? 'Administrator' }}

                </strong>

                <small>

                    {{ ucfirst(auth()->user()->role ?? 'Admin') }}

                </small>

            </div>

            <i data-lucide="chevron-down"></i>

        </div>

    </div>

</header>
