@php
    $cashPct = (float) ($cashPercent ?? 0);
    $transferPct = (float) ($transferPercent ?? 0);
    $tempoPct = (float) ($tempoPercent ?? 0);
    $paidPct = $cashPct + $transferPct;
    $hasPayment = $cashPct + $transferPct + $tempoPct > 0;
@endphp

<div class="dashboard-chart-grid">

    {{-- =====================================================
         PENDAPATAN VS PENGELUARAN
    ====================================================== --}}
    <div class="chart-card">
        <div class="chart-header">
            <div>
                <h3 class="chart-title">Penjualan vs Pengeluaran</h3>
                <p>Perbandingan keuangan 30 hari terakhir.</p>
            </div>
            <span class="chart-badge">30 Hari</span>
        </div>

        {{-- Tinggi dikunci sejak awal supaya halaman tidak "loncat" saat grafik muncul --}}
        <div class="chart-canvas chart-canvas-lg" data-chart="financial" aria-label="Grafik pendapatan dan pengeluaran">
            <span class="chart-skeleton"></span>
        </div>
    </div>

    {{-- =====================================================
         METODE PEMBAYARAN
    ====================================================== --}}
    <div class="chart-card payment-card">
        <div class="chart-header">
            <div>
                <h3 class="chart-title">Metode Pembayaran</h3>
                <p>Persentase penjualan bulan ini.</p>
            </div>
        </div>

        @if ($hasPayment)
            <div class="chart-canvas chart-canvas-sm" data-chart="payment" aria-label="Grafik metode pembayaran">
                <span class="chart-skeleton is-round"></span>
            </div>
        @else
            <div class="empty-state">
                <i data-lucide="credit-card"></i>
                Belum ada transaksi bulan ini.
            </div>
        @endif

        <div class="payment-info">
            <div class="payment-row">
                <span><span class="dot cash"></span> Cash</span>
                <strong>{{ number_format($cashPct, 1, ',', '.') }}%</strong>
            </div>
            <div class="payment-row">
                <span><span class="dot transfer"></span> Transfer</span>
                <strong>{{ number_format($transferPct, 1, ',', '.') }}%</strong>
            </div>
            <div class="payment-row">
                <span><span class="dot tempo"></span> Tempo</span>
                <strong>{{ number_format($tempoPct, 1, ',', '.') }}%</strong>
            </div>
        </div>
    </div>

</div>

@push('scripts')
    <script>
        (() => {
            /* =====================================================
               PERFORMA:
               - ApexCharts hanya diunduh saat grafik hampir terlihat
                 (lazy load), jadi tidak memperlambat halaman awal.
               - Kalau layout sudah memuat ApexCharts, yang itu dipakai.
               - Animasi grafik dimatikan supaya render lebih ringan.
            ====================================================== */
            const APEX_SRC = 'https://cdn.jsdelivr.net/npm/apexcharts@3/dist/apexcharts.min.js';
            const C = {
                accent: '#FF6A1A',
                ink: '#2A2F37',
                slate: '#64748B',
                grid: '#E8EAEE',
                muted: '#8A93A0'
            };

            const rupiah = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
            const shortRp = (v) => {
                const n = Math.abs(v);
                if (n >= 1e9) return (v / 1e9).toFixed(1).replace('.', ',') + ' M';
                if (n >= 1e6) return (v / 1e6).toFixed(1).replace('.', ',') + ' jt';
                if (n >= 1e3) return Math.round(v / 1e3) + ' rb';
                return v;
            };

            const LABELS = @json($chartLabels ?? []);

            const base = {
                fontFamily: 'inherit',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                },
                animations: {
                    enabled: false
                },
                parentHeightOffset: 0
            };

            const configs = {
                financial: (height) => ({
                    chart: {
                        ...base,
                        type: 'area',
                        height
                    },
                    series: [{
                            name: 'Penjualan',
                            data: @json($chartSeries ?? [])
                        },
                        {
                            name: 'Pengeluaran',
                            data: @json($expenseChartSeries ?? [])
                        }
                    ],
                    xaxis: {
                        categories: LABELS,
                        tickAmount: Math.min(6, Math.max(1, LABELS.length - 1)),
                        labels: {
                            rotate: 0,
                            hideOverlappingLabels: true,
                            style: {
                                colors: C.muted
                            }
                        },
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        }
                    },
                    yaxis: {
                        // selalu mulai dari 0 supaya skala tidak aneh saat datanya sedikit
                        min: 0,
                        forceNiceScale: true,
                        labels: {
                            formatter: shortRp,
                            style: {
                                colors: C.muted
                            }
                        }
                    },
                    colors: [C.accent, C.ink],
                    stroke: {
                        width: [3, 2],
                        curve: 'smooth'
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            opacityFrom: .28,
                            opacityTo: .02
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    markers: {
                        // data baru 1–2 hari: tampilkan titiknya, kalau tidak grafik terlihat kosong
                        size: LABELS.length <= 2 ? 5 : 0,
                        hover: {
                            size: 5
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                        markers: {
                            radius: 12
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: rupiah
                        }
                    },
                    grid: {
                        borderColor: C.grid,
                        strokeDashArray: 4,
                        padding: {
                            left: 6,
                            right: 6
                        }
                    },
                    responsive: [{
                        breakpoint: 576,
                        options: {
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center'
                            },
                            xaxis: {
                                tickAmount: 4
                            }
                        }
                    }]
                }),

                payment: (height) => ({
                    chart: {
                        ...base,
                        type: 'donut',
                        height
                    },
                    series: [{{ $cashPct }}, {{ $transferPct }}, {{ $tempoPct }}],
                    labels: ['Cash', 'Transfer', 'Tempo'],
                    colors: [C.accent, C.ink, '#FFB020'],
                    legend: {
                        show: false
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        width: 0
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '72%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: 'Lunas',
                                        formatter: () => '{{ number_format($paidPct, 0) }}%'
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: (v) => v + '%'
                        }
                    }
                })
            };

            let loader;
            const loadApex = () => {
                if (window.ApexCharts) return Promise.resolve();
                return loader ??= new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = APEX_SRC;
                    s.async = true;
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
            };

            /* Tinggi grafik diambil dari kotaknya sendiri (dalam piksel).
               Jangan pakai height: '100%' — ApexCharts menghitung persen dari
               INDUK kotak (seluruh kartu), sehingga grafik jadi lebih tinggi
               dari tempatnya dan menimpa konten di bawahnya. */
            const boxHeight = (el) => Math.round(el.clientHeight) || 280;
            const instances = [];

            const render = (el) => loadApex()
                .then(() => {
                    el.replaceChildren();
                    const chart = new ApexCharts(el, configs[el.dataset.chart](boxHeight(el)));
                    chart.render();
                    instances.push({ el, chart, height: boxHeight(el) });
                })
                .catch(() => {
                    el.innerHTML = '<div class="empty-state">Grafik gagal dimuat. Periksa koneksi internet.</div>';
                });

            const init = () => {
                const charts = document.querySelectorAll('[data-chart]');
                if (!charts.length) return;

                if (!('IntersectionObserver' in window)) {
                    charts.forEach(render);
                    return;
                }

                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        io.unobserve(entry.target);
                        render(entry.target);
                    });
                }, {
                    rootMargin: '250px 0px'
                });

                charts.forEach((el) => io.observe(el));
            };

            // tinggi kotak berubah di lebar tertentu (CSS) → samakan tinggi grafiknya
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    instances.forEach((item) => {
                        item.el.style.minHeight = ''; // ApexCharts mengunci min-height lama
                        const h = boxHeight(item.el);
                        if (h === item.height) return;
                        item.height = h;
                        item.chart.updateOptions({ chart: { height: h } }, false, false);
                    });
                }, 200);
            }, { passive: true });

            document.readyState === 'loading' ?
                document.addEventListener('DOMContentLoaded', init, {
                    once: true
                }) :
                init();
        })();
    </script>
@endpush
