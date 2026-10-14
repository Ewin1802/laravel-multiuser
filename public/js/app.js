/**
 * ==========================================================
 * GARASI PART ADMIN — app.js
 * Satu file untuk semua halaman admin:
 * sidebar, dropdown, modal, toast, loading, utilitas.
 *
 * Fungsi global yang tetap tersedia (dipakai di Blade):
 *   showToast(type, title, message)   hideToast(el)
 *   showLoading()  hideLoading()
 *   confirmDelete(message)            refreshIcons()
 * ==========================================================
 */

(() => {
    'use strict';

    const body = document.body;
    const MOBILE = window.matchMedia('(max-width: 768px)');

    /* ======================================================
       SIDEBAR
       - Laptop/tablet : ringkas ↔ penuh  (body.sidebar-collapse, diingat)
       - HP            : laci buka/tutup  (body.sidebar-open)
       Tombol: #sidebarToggle, .menu-button, .sidebar-toggle,
               atau [data-sidebar-toggle]
    ====================================================== */
    const STORAGE_KEY = 'sidebar';
    const TOGGLE = '#sidebarToggle, .menu-button, .sidebar-toggle, [data-sidebar-toggle]';

    const remember = (collapsed) => {
        try {
            collapsed
                ? localStorage.setItem(STORAGE_KEY, 'collapse')
                : localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            /* storage diblokir (mode private): abaikan */
        }
    };

    const syncAria = () => {
        const expanded = MOBILE.matches
            ? body.classList.contains('sidebar-open')
            : !body.classList.contains('sidebar-collapse');

        document.querySelectorAll(TOGGLE).forEach((btn) => {
            btn.setAttribute('aria-expanded', String(expanded));
        });
    };

    const closeSidebar = () => {
        body.classList.remove('sidebar-open');
        syncAria();
    };

    const toggleSidebar = () => {
        if (MOBILE.matches) {
            body.classList.toggle('sidebar-open');
        } else {
            remember(body.classList.toggle('sidebar-collapse'));
        }
        syncAria();
    };

    /* ======================================================
       DROPDOWN  (.dropdown > .dropdown-toggle)
    ====================================================== */
    const closeDropdowns = (except = null) => {
        document.querySelectorAll('.dropdown.active').forEach((dd) => {
            if (dd !== except) dd.classList.remove('active');
        });
    };

    /* ======================================================
       MODAL  ([data-modal="idModal"], .modal-close, .modal-overlay)
    ====================================================== */
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
        body.classList.add('modal-open');
        modal.querySelector('input, select, textarea, button')?.focus();
    };

    const closeModal = (modal) => {
        modal?.classList.remove('show');
        if (!document.querySelector('.modal.show')) {
            body.classList.remove('modal-open');
        }
    };

    /* ======================================================
       SATU LISTENER KLIK UNTUK SEMUA (event delegation)
       → tetap jalan untuk elemen yang ditambahkan belakangan
    ====================================================== */
    document.addEventListener('click', (e) => {
        const t = e.target;

        // sidebar
        if (t.closest(TOGGLE)) {
            e.preventDefault();
            toggleSidebar();
            return;
        }

        if (MOBILE.matches && body.classList.contains('sidebar-open')
            && t.closest('.sidebar-backdrop, .sidebar-menu a')) {
            closeSidebar();
        }

        // dropdown
        const ddToggle = t.closest('.dropdown-toggle');
        if (ddToggle) {
            const dd = ddToggle.closest('.dropdown');
            closeDropdowns(dd);
            dd?.classList.toggle('active');
            return;
        }
        if (!t.closest('.dropdown-menu')) closeDropdowns();

        // modal
        const opener = t.closest('[data-modal]');
        if (opener) {
            e.preventDefault();
            openModal(opener.dataset.modal);
            return;
        }
        if (t.closest('.modal-close') || t.classList.contains('modal-overlay')) {
            closeModal(t.closest('.modal'));
        }

        // toast
        const toastClose = t.closest('.toast-close');
        if (toastClose) hideToast(toastClose.closest('.toast'));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal.show').forEach(closeModal);
        closeDropdowns();
        closeSidebar();
    });

    // pindah HP ↔ layar besar (rotasi / resize): tutup laci
    MOBILE.addEventListener('change', closeSidebar);

    /* ======================================================
       TOAST — teks dimasukkan sebagai textContent (aman dari XSS)
    ====================================================== */
    const TOAST_ICON = {
        success: 'circle-check',
        danger: 'circle-alert',
        warning: 'triangle-alert',
        info: 'info',
    };

    window.showToast = (type = 'info', title = '', message = '', duration = 4000) => {
        const container = document.querySelector('.toast-container');
        if (!container) return null;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        toast.innerHTML = `
            <span class="toast-icon"><i data-lucide="${TOAST_ICON[type] || 'info'}"></i></span>
            <div class="toast-body">
                <div class="toast-title"></div>
                <div class="toast-text"></div>
            </div>
            <button type="button" class="toast-close" aria-label="Tutup">
                <i data-lucide="x"></i>
            </button>`;

        toast.querySelector('.toast-title').textContent = title;
        toast.querySelector('.toast-text').textContent = message;

        container.appendChild(toast);
        window.lucide?.createIcons(); // hanya memproses ikon yang belum dirender

        if (duration > 0) setTimeout(() => hideToast(toast), duration);
        return toast;
    };

    window.hideToast = (toast) => {
        if (!toast || toast.classList.contains('hide')) return;
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    };

    /* ======================================================
       LOADING
    ====================================================== */
    window.showLoading = () => document.querySelector('.loading')?.classList.add('show');
    window.hideLoading = () => document.querySelector('.loading')?.classList.remove('show');

    // tutup loading kalau user kembali lewat tombol Back (halaman dari cache)
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.hideLoading();
    });

    /* ======================================================
       UTILITAS
    ====================================================== */
    window.confirmDelete = (message = 'Yakin ingin menghapus data ini?') => confirm(message);

    window.refreshIcons = () => window.lucide?.createIcons();

    /* ======================================================
       FORM HELPER (dipakai otomatis lewat atribut HTML)

       1) Input uang   : <input data-money="#price">  + <input type="hidden" id="price" name="price">
                         → tampil "1.250.000", terkirim "1250000"
       2) Upload foto  : <label class="p-upload" data-upload data-max-mb="2"> <input type="file"> <img> ...
                         → pratinjau instan, seret & lepas, batas ukuran
       3) Form aman    : <form data-guard>
                         → cek isian wajib, kunci tombol Simpan (anti klik ganda),
                           peringatan kalau keluar sebelum menyimpan
    ====================================================== */

    // 1) INPUT UANG
    document.querySelectorAll('[data-money]').forEach((display) => {
        const hidden = document.querySelector(display.dataset.money);

        const format = (raw) => {
            const digits = String(raw).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            if (hidden) hidden.value = digits;
            return digits ? Number(digits).toLocaleString('id-ID') : '';
        };

        display.addEventListener('input', () => {
            const fromEnd = display.value.length - display.selectionStart;
            display.value = format(display.value);
            const pos = Math.max(0, display.value.length - fromEnd);
            display.setSelectionRange(pos, pos);
            display.setCustomValidity('');
        });

        if (display.value) display.value = format(display.value);
    });

    // 2) UPLOAD FOTO
    document.querySelectorAll('[data-upload]').forEach((box) => {
        const input = box.querySelector('input[type="file"]');
        const preview = box.querySelector('img');
        const empty = box.querySelector('.p-upload-empty');
        const meta = box.parentElement.querySelector('.p-upload-meta');
        const maxMb = Number(box.dataset.maxMb || 2);
        const originalSrc = preview?.getAttribute('src') || '';
        let objectUrl = null;

        if (!input || !preview) return;

        const show = (file) => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;

            if (!file) {
                input.value = '';
                preview.hidden = !originalSrc;
                if (originalSrc) preview.src = originalSrc;
                if (empty) empty.hidden = !!originalSrc;
                if (meta) meta.hidden = true;
                return;
            }

            if (file.size > maxMb * 1024 * 1024) {
                window.showToast('warning', 'Foto terlalu besar', `Maksimal ${maxMb} MB. Kompres dulu fotonya.`);
                show(null);
                return;
            }

            // object URL: tanpa mengubah foto jadi teks base64 (hemat memori)
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (empty) empty.hidden = true;

            if (meta) {
                meta.hidden = false;
                const name = meta.querySelector('[data-upload-name]');
                if (name) name.textContent = `${file.name} · ${Math.round(file.size / 1024)} KB`;
            }
        };

        input.addEventListener('change', () => show(input.files[0]));
        meta?.querySelector('[data-upload-reset]')?.addEventListener('click', () => show(null));

        ['dragenter', 'dragover'].forEach((ev) => box.addEventListener(ev, (e) => {
            e.preventDefault();
            box.classList.add('is-drag');
        }));

        ['dragleave', 'drop'].forEach((ev) => box.addEventListener(ev, (e) => {
            e.preventDefault();
            box.classList.remove('is-drag');
        }));

        box.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            show(file);
        });
    });

    // 3) FORM AMAN
    document.querySelectorAll('form[data-guard]').forEach((form) => {
        let dirty = false;
        const markDirty = () => { dirty = true; };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        form.addEventListener('submit', (e) => {
            // input uang wajib: cek nilai mentahnya
            form.querySelectorAll('[data-money][required]').forEach((display) => {
                const hidden = document.querySelector(display.dataset.money);
                display.setCustomValidity(hidden && hidden.value ? '' : 'Wajib diisi.');
            });

            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }

            dirty = false;
            form.querySelectorAll('button[type="submit"]').forEach((b) => {
                b.disabled = true;
                b.classList.add('is-loading');
            });
            window.showLoading();
        });

        window.addEventListener('beforeunload', (e) => {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    });

    // 4) TAMPILKAN / SEMBUNYIKAN PASSWORD: <button data-toggle-password="password">
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;

        const input = document.getElementById(btn.dataset.togglePassword);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        btn.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}"></i>`;
        window.lucide?.createIcons();
    });

    // 5) KONFIRMASI HARUS SAMA: <input data-match="#password">
    document.querySelectorAll('[data-match]').forEach((confirmInput) => {
        const source = document.querySelector(confirmInput.dataset.match);
        if (!source) return;

        const check = () => {
            const mismatch = confirmInput.value !== '' && confirmInput.value !== source.value;
            confirmInput.setCustomValidity(mismatch ? 'Konfirmasi password tidak sama.' : '');
            confirmInput.closest('.p-field')?.classList.toggle('is-mismatch', mismatch);
        };

        confirmInput.addEventListener('input', check);
        source.addEventListener('input', check);
    });

    // 6) FILTER TABEL INSTAN (tanpa reload): <input data-table-filter="#idTabel">
    //    Baris yang dicari: <tr data-filter-row> · baris "tidak ditemukan": <tr data-filter-empty hidden>
    document.querySelectorAll('[data-table-filter]').forEach((input) => {
        const table = document.querySelector(input.dataset.tableFilter);
        if (!table) return;

        const rows = [...table.querySelectorAll('[data-filter-row]')];
        const emptyRow = table.querySelector('[data-filter-empty]');
        const counter = document.querySelector(input.dataset.filterCount || '[data-filter-count]');
        rows.forEach((r) => { r.dataset.text = r.textContent.toLowerCase(); });

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const terms = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
                let shown = 0;
                rows.forEach((r) => {
                    const match = terms.every((t) => r.dataset.text.includes(t));
                    r.hidden = !match;
                    if (match) shown++;
                });
                if (emptyRow) emptyRow.hidden = shown > 0 || rows.length === 0;
                if (counter) counter.textContent = shown;
            }, 120);
        });
    });

    // konfirmasi hapus: <form data-confirm="Hapus produk X?">
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;

        if (!confirm(form.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        } else {
            window.showLoading();
        }
    });

    /* ======================================================
       INIT
    ====================================================== */
    syncAria();
    requestAnimationFrame(() => body.classList.remove('no-transition'));
})();
