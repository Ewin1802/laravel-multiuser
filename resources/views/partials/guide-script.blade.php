{{-- ============================================================
     Pembuka modal panduan (.s-guide) — dipakai banyak halaman.
     Tombol:  data-guide-open="idModal"  (+ data-guide-tab="namaTab")
     Tutup:   data-guide-close di dalam modal, klik latar, atau Esc.
     Tab:     data-guide-tab-btn="nama"  ↔  data-guide-pane="nama"
============================================================ --}}
@once
    @push('scripts')
        <script>
            (() => {
                const setTab = (modal, name) => {
                    if (!modal) return;
                    const tabs = modal.querySelectorAll('[data-guide-tab-btn]');
                    if (!name) name = tabs[0]?.dataset.guideTabBtn;
                    tabs.forEach((t) => {
                        const on = t.dataset.guideTabBtn === name;
                        t.classList.toggle('is-active', on);
                        t.setAttribute('aria-selected', on ? 'true' : 'false');
                        if (on) t.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
                    });
                    modal.querySelectorAll('[data-guide-pane]').forEach((p) => {
                        const on = p.dataset.guidePane === name;
                        p.hidden = !on;
                        p.classList.toggle('is-active', on);
                    });
                    const body = modal.querySelector('.s-guide-body');
                    if (body) body.scrollTop = 0;
                };

                let opener = null;
                const open = (id, tab, trigger) => {
                    const modal = document.getElementById(id);
                    if (!modal) return;
                    // panduan lain yang masih terbuka (mis. buka contoh dari dalam panduan) ditutup dulu
                    document.querySelectorAll('.s-guide.is-open').forEach((m) => {
                        if (m !== modal) { m.classList.remove('is-open'); m.setAttribute('aria-hidden', 'true'); }
                    });
                    if (!trigger.closest('.s-guide')) opener = trigger;
                    setTab(modal, tab);
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    window.lucide?.createIcons();
                    setTimeout(() => modal.querySelector('.s-guide-close')?.focus(), 30);
                };
                const close = (modal) => {
                    if (!modal) return;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    opener?.focus?.();
                };

                document.addEventListener('click', (e) => {
                    const o = e.target.closest('[data-guide-open]');
                    if (o) {
                        e.preventDefault();
                        open(o.dataset.guideOpen, o.dataset.guideTab, o);
                        return;
                    }
                    const c = e.target.closest('[data-guide-close]');
                    if (c) return close(c.closest('.s-guide'));

                    const t = e.target.closest('[data-guide-tab-btn]');
                    if (t) setTab(t.closest('.s-guide'), t.dataset.guideTabBtn);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape') return;
                    document.querySelectorAll('.s-guide.is-open').forEach(close);
                });
            })();
        </script>
    @endpush
@endonce
