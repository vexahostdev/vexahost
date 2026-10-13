<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $seoTitle = $title ?? 'VexaHost — Cloud VPS NVMe KVM Indonesia Mulai Rp 80rb/bln';
        $seoDescription = $description ?? 'Cloud VPS KVM dengan datacenter Jakarta & Singapore. Penyimpanan NVMe SSD, akses root penuh, Managed DB, & AI Stack siap pakai mulai Rp 80rb.';
        $seoImage = asset('images/promo-banner.webp');
        // Halaman transaksi (checkout) mengirim 'noindex, follow' lewat @extends:
        // formulir kosong tidak boleh bersaing dengan beranda untuk pencarian merek.
        $seoRobots = $robots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        $canonicalUrl = url()->current();
        $googleSiteVerification = config('services.google.site_verification');
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="title" content="{{ $seoTitle }}">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="author" content="VexaHost">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    @if(!empty($googleSiteVerification))
    <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif

    <!-- Geo Targeting (Dominasi Pencarian Lokal Indonesia) -->
    <meta name="geo.region" content="ID-JK">
    <meta name="geo.placename" content="Jakarta, Indonesia">
    <meta name="geo.position" content="-6.2088;106.8456">
    <meta name="ICBM" content="-6.2088, 106.8456">

    <!-- Open Graph / Facebook / WhatsApp Preview -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="VexaHost Cloud Indonesia">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="VexaHost Cloud Infrastructure">
    <meta property="og:image:width" content="800">
    <meta property="og:image:height" content="560">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $canonicalUrl }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <!-- Schema.org JSON-LD Structured Data -->
    @php
        // Entitas VexaHost untuk mesin pencari.
        //
        // `sameAs` sengaja belum ada: menautkan profil yang belum dibuat tidak
        // menambah sinyal apa pun, dan tautan mati justru melemahkannya. Isi
        // begitu profil resminya benar-benar hidup — itulah yang paling
        // menentukan apakah Google memisahkan kita dari penyedia bernama mirip.
        //
        // `parentOrganization` sengaja TIDAK menyebut vendor pengembang: itu
        // pernyataan relasi entitas yang mengikat, bukan kredit. Kreditnya tetap
        // tampil sebagai teks biasa di footer dan halaman auth.
        $waGatewayUrl = rtrim(config('whatsapp.url', 'https://wa.vexahostcloud.my.id'), '/');

        // Harga dibaca dari paket yang benar-benar aktif; angka mati di sini akan
        // diam-diam berbeda dari halaman harga begitu katalog berubah.
        try {
            $hargaAktif = \App\Models\VpsSpec::where('is_active', true)->pluck('sell_price');
            $hargaTerendah = (int) ($hargaAktif->min() ?: 80000);
            $hargaTertinggi = (int) ($hargaAktif->max() ?: 350000);
            $jumlahPaket = max(1, $hargaAktif->count());
        } catch (\Throwable $e) {
            // Halaman tetap harus terkirim walau database belum siap.
            [$hargaTerendah, $hargaTertinggi, $jumlahPaket] = [80000, 350000, 10];
        }

        $schemaData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/') . '#organization',
                    'name' => 'VexaHost',
                    'alternateName' => [
                        'VexaHost Cloud',
                        'VexaHost Indonesia',
                        'VexaHost Cloud VPS',
                        'vexahostcloud',
                    ],
                    'url' => url('/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => asset('images/favicon-512x512.png'),
                        'width' => 512,
                        'height' => 512,
                        'caption' => 'VexaHost',
                    ],
                    'image' => asset('images/favicon-512x512.png'),
                    'description' => 'Penyedia infrastruktur Cloud VPS NVMe KVM, AI Hub, dan Managed Database berkinerja tinggi di Indonesia.',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressCountry' => 'ID',
                    ],
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'Indonesia',
                    ],
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'telephone' => '+'.\App\Support\NomorWhatsApp::internasional(),
                        'contactType' => 'customer service',
                        'email' => \App\Support\MailIdentity::contactEmail(),
                        'areaServed' => 'ID',
                        'availableLanguage' => ['Indonesian', 'English'],
                    ],
                    // WA Gateway berjalan di subdomain terpisah. Tanpa relasi ini
                    // Google membacanya sebagai situs asing yang kebetulan bernama
                    // mirip. `@id`-nya wajib sama persis dengan yang ditulis repo
                    // WA Gateway — beda satu garis miring, relasinya tidak terbaca.
                    'subOrganization' => [
                        '@type' => 'Organization',
                        '@id' => $waGatewayUrl . '#organization',
                        'name' => 'VexaHost WA Gateway',
                        'url' => $waGatewayUrl,
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/') . '#website',
                    'url' => url('/'),
                    'name' => 'VexaHost',
                    'alternateName' => [
                        'VexaHost Cloud',
                        'VexaHost Indonesia',
                    ],
                    'description' => 'Infrastruktur Cloud VPS Cepat, Andal, & Transparan',
                    'publisher' => [
                        '@id' => url('/') . '#organization',
                    ],
                    'inLanguage' => 'id-ID',
                ],
                [
                    '@type' => 'Product',
                    'name' => 'VexaHost Cloud VPS KVM Indonesia',
                    'image' => asset('images/favicon-512x512.png'),
                    'description' => 'Server Virtual Private Server (VPS) berbasis KVM dengan penyimpanan 100% NVMe dan Datacenter Jakarta.',
                    'brand' => [
                        '@type' => 'Brand',
                        'name' => 'VexaHost',
                    ],
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'priceCurrency' => 'IDR',
                        'lowPrice' => (string) $hargaTerendah,
                        'highPrice' => (string) $hargaTertinggi,
                        'offerCount' => (string) $jumlahPaket,
                        'priceValidUntil' => '2028-12-31',
                        'availability' => 'https://schema.org/InStock',
                        'url' => route('packages.vps'),
                    ],
                ],
            ],
        ];

        // Tanya jawab. Google membatasi rich result FAQ ke situs pemerintah dan
        // kesehatan sejak Agustus 2023, jadi ini tidak dipasang untuk mendapat
        // kotak lipat di hasil pencarian. Yang membacanya AI Overview dan mesin
        // jawab lain — dan di sanalah merek kita paling sering tertukar dengan
        // penyedia asing bernama mirip. Tanya jawab terstruktur jauh lebih sulit
        // disalahartikan daripada paragraf bebas.
        if (! empty($faq ?? [])) {
            $schemaData['@graph'][] = [
                '@type' => 'FAQPage',
                '@id' => $canonicalUrl . '#faq',
                'mainEntity' => collect($faq)->values()
                    ->map(fn (array $butir) => [
                        '@type' => 'Question',
                        'name' => $butir['tanya'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $butir['jawab'],
                        ],
                    ])->all(),
            ];
        }

        // Remah jejak. Halaman yang mengirim $breadcrumbs mendapat jalur navigasi
        // di hasil pencarian menggantikan URL mentah; butir terakhir sengaja tanpa
        // 'item' karena itu halaman yang sedang dibuka.
        if (! empty($breadcrumbs ?? [])) {
            $schemaData['@graph'][] = [
                '@type' => 'BreadcrumbList',
                '@id' => $canonicalUrl . '#breadcrumb',
                'itemListElement' => collect($breadcrumbs)->values()
                    ->map(fn (array $remah, int $i) => array_filter([
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $remah['name'],
                        'item' => $remah['url'] ?? null,
                    ]))->all(),
            ];
        }
    @endphp
    <script type="application/ld+json">
    {!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            var isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        })();
    </script>
    <style>
        [x-cloak] { display: none !important; }
        html.dark { color-scheme: dark; background-color: #09090b; }
        html.dark body { background-color: #09090b; color: #fafafa; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-white dark:bg-[#09090b] text-slate-800 dark:text-zinc-100 antialiased flex flex-col min-h-screen selection:bg-[#4A6FA5] selection:text-white"
      x-data="{ isScrolled: false, mobileMenu: false, mobileProduk: false, mobileVps: false, mobileAi: false, mobileDb: false }"
      x-init="isScrolled = (window.pageYOffset > 20)"
      @scroll.window="isScrolled = (window.pageYOffset > 20)">
    @include('partials.impersonation-banner')

    @php
        $isHome = request()->routeIs('home');
        $navVpsSpecs = $vpsSpecs ?? \App\Models\VpsSpec::where('category', 'vps')->orderBy('sell_price', 'asc')->get();
        $navAiSpecs = $aiSpecs ?? \App\Models\VpsSpec::where('category', 'ai_combo')->orderBy('sell_price', 'asc')->get();
        $navDbSpecs = $dbSpecs ?? \App\Models\VpsSpec::where('category', 'managed_db')->orderBy('sell_price', 'asc')->get();
    @endphp

    <!-- Enterprise Navbar -->
    <header :class="{
                'bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md border-b border-slate-200 dark:border-zinc-800 shadow-xs': isScrolled || mobileMenu || !{{ $isHome ? 'true' : 'false' }},
                'bg-transparent border-transparent shadow-none': !isScrolled && !mobileMenu && {{ $isHome ? 'true' : 'false' }}
            }"
            class="sticky top-0 z-50 transition-all duration-200">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Brand Logo (Left) -->
                <a href="{{ route('home') }}" class="flex items-center space-x-3 shrink-0">
                    <img src="{{ asset('images/logo.png') }}" alt="VexaHost" class="h-9 w-auto object-contain">
                    <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Vexa<span class="text-[#4A6FA5]">Host</span></span>
                </a>

                <!-- Right Group: Nav Links pepet ke Button Masuk -->
                <div class="hidden md:flex items-center space-x-6">
                    <nav class="flex items-center space-x-5 text-sm font-medium text-slate-700 dark:text-zinc-200">

                        <!-- Beranda -->
                        <a href="{{ route('home') }}" class="group flex items-center py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0 {{ request()->routeIs('home') ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200' }}">
                            <span class="relative pb-0.5">
                                Beranda
                                <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full {{ request()->routeIs('home') ? 'w-full' : 'w-0' }}"></span>
                            </span>
                        </a>

                        <!-- Dropdown Produk -->
                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @mouseenter="open = true" @mouseleave="open = false">
                            <button @click="open = !open"
                                    class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0"
                                    :class="open ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200'">
                                <span class="relative pb-0.5">
                                    Produk
                                    <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full"
                                          :class="open ? 'w-full' : 'w-0'"></span>
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4A6FA5] transition-transform duration-200" :class="open ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 mt-2 w-72 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-zinc-800 py-2 z-50 text-slate-800 dark:text-zinc-200"
                                 style="display: none;">
                                
                                <a href="{{ route('home') }}#pricing" @click="open = false" class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-[#4A6FA5] group-hover:text-white transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-[#4A6FA5] transition-colors">Cloud VPS</p>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 leading-snug">Compute KVM andal, NVMe SSD &amp; root access.</p>
                                    </div>
                                </a>

                                <a href="{{ route('home') }}#ai-packages" @click="open = false" class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-[#4A6FA5] group-hover:text-white transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-[#4A6FA5] transition-colors">AI Combo Packages</p>
                                            <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Baru</span>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 leading-snug">Zero setup coding agent &amp; private workstation.</p>
                                    </div>
                                </a>

                                <a href="{{ route('home') }}#database-packages" @click="open = false" class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-[#4A6FA5] group-hover:text-white transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-[#4A6FA5] transition-colors">Managed Database</p>
                                            <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Baru</span>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 leading-snug">Server database khusus, terpisah dari aplikasi.</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Dropdown Paket VPS -->
                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @mouseenter="open = true" @mouseleave="open = false">
                            <button @click="open = !open"
                                    class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0"
                                    :class="open ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200'">
                                <span class="relative pb-0.5">
                                    Paket VPS
                                    <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full"
                                          :class="open ? 'w-full' : 'w-0'"></span>
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4A6FA5] transition-transform duration-200" :class="open ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 mt-2 w-72 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-zinc-800 py-2 z-50 text-slate-800 dark:text-zinc-200"
                                 style="display: none;">
                                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-zinc-800 mb-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Pilihan Paket VPS</p>
                                </div>
                                @foreach($navVpsSpecs as $spec)
                                    <a href="{{ route('checkout', $spec->id) }}" @click="open = false" class="flex items-center justify-between px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-xs font-semibold text-slate-800 dark:text-zinc-200 group-hover:text-[#4A6FA5] transition-colors">{{ $spec->name }}</p>
                                                @if($spec->name === 'Standard')
                                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Populer</span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-zinc-500">{{ $spec->cpu }} Core • {{ $spec->ram }} GB RAM • {{ $spec->disk }} GB SSD</p>
                                        </div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white font-mono-code shrink-0">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                                    </a>
                                @endforeach
                                <div class="border-t border-slate-100 dark:border-zinc-800 mt-1 pt-1.5 px-3.5">
                                    <a href="{{ route('home') }}#pricing" @click="open = false" class="block text-center text-xs font-bold text-[#4A6FA5] hover:text-[#3D5E8C] py-1">
                                        Lihat Semua Paket VPS &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown AI Agent -->
                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @mouseenter="open = true" @mouseleave="open = false">
                            <button @click="open = !open"
                                    class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0"
                                    :class="open ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200'">
                                <span class="relative pb-0.5">
                                    AI Agent
                                    <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full"
                                          :class="open ? 'w-full' : 'w-0'"></span>
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4A6FA5] transition-transform duration-200" :class="open ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 mt-2 w-80 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-zinc-800 py-2 z-50 text-slate-800 dark:text-zinc-200"
                                 style="display: none;">
                                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-zinc-800 mb-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Paket AI Coding &amp; Automation</p>
                                </div>
                                @foreach($navAiSpecs as $spec)
                                    <a href="{{ route('checkout', $spec->id) }}" @click="open = false" class="flex items-center justify-between px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                        <div class="pr-2">
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-xs font-semibold text-slate-800 dark:text-zinc-200 group-hover:text-[#4A6FA5] transition-colors">{{ $spec->name }}</p>
                                                @if($spec->id == 8)
                                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Populer</span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 line-clamp-1">{{ $spec->cpu }} Core • {{ $spec->ram }} GB RAM • {{ $spec->disk }} GB SSD</p>
                                        </div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white font-mono-code shrink-0">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                                    </a>
                                @endforeach
                                <div class="border-t border-slate-100 dark:border-zinc-800 mt-1 pt-1.5 px-3.5">
                                    <a href="{{ route('home') }}#ai-packages" @click="open = false" class="block text-center text-xs font-bold text-[#4A6FA5] hover:text-[#3D5E8C] py-1">
                                        Lihat Semua Fitur AI &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Database -->
                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @mouseenter="open = true" @mouseleave="open = false">
                            <button @click="open = !open"
                                    class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0"
                                    :class="open ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200'">
                                <span class="relative pb-0.5">
                                    Database
                                    <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full"
                                          :class="open ? 'w-full' : 'w-0'"></span>
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4A6FA5] transition-transform duration-200" :class="open ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 mt-2 w-80 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-zinc-800 py-2 z-50 text-slate-800 dark:text-zinc-200"
                                 style="display: none;">
                                <div class="px-3.5 py-1.5 border-b border-slate-100 dark:border-zinc-800 mb-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Dedicated Database VPS</p>
                                </div>
                                @foreach($navDbSpecs as $spec)
                                    <a href="{{ route('checkout', $spec->id) }}" @click="open = false" class="flex items-center justify-between px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-zinc-800/60 transition-colors group">
                                        <div class="pr-2">
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-xs font-semibold text-slate-800 dark:text-zinc-200 group-hover:text-[#4A6FA5] transition-colors">{{ $spec->name }}</p>
                                                @if($spec->id == 12)
                                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Populer</span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-zinc-500">{{ $spec->cpu }} Core • {{ $spec->ram }} GB RAM • {{ $spec->disk }} GB NVMe</p>
                                        </div>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white font-mono-code shrink-0">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                                    </a>
                                @endforeach
                                <div class="border-t border-slate-100 dark:border-zinc-800 mt-1 pt-1.5 px-3.5">
                                    <a href="{{ route('home') }}#database-packages" @click="open = false" class="block text-center text-xs font-bold text-[#4A6FA5] hover:text-[#3D5E8C] py-1">
                                        Lihat Semua Fitur Database &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Docs -->
                        <a href="{{ route('docs') }}" class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0 {{ request()->routeIs('docs') ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200' }}">
                            <span class="relative pb-0.5">
                                Docs
                                <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full {{ request()->routeIs('docs') ? 'w-full' : 'w-0' }}"></span>
                            </span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4A6FA5] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                            </svg>
                        </a>

                        <!-- WA Gateway -->
                        <a href="https://wa.vexahostcloud.my.id" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0 text-slate-700 dark:text-zinc-200">
                            <span class="relative pb-0.5">
                                WA Gateway
                                <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full w-0"></span>
                            </span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4A6FA5] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                            </svg>
                        </a>

                        <!-- Jasa Web -->
                        <a href="https://build.vexahostcloud.my.id" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-1 py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0 text-slate-700 dark:text-zinc-200">
                            <span class="relative pb-0.5">
                                Jasa Web
                                <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full w-0"></span>
                            </span>
                            <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4A6FA5] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                            </svg>
                        </a>

                        <!-- Status -->
                        <a href="{{ route('status') }}" class="group flex items-center py-1 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors whitespace-nowrap shrink-0 {{ request()->routeIs('status') ? 'text-[#4A6FA5] font-semibold' : 'text-slate-700 dark:text-zinc-200' }}">
                            <span class="relative pb-0.5">
                                Status
                                <span class="absolute bottom-0 left-0 h-[2px] bg-[#4A6FA5] transition-all duration-200 group-hover:w-full {{ request()->routeIs('status') ? 'w-full' : 'w-0' }}"></span>
                            </span>
                        </a>
                    </nav>

                    <!-- Divider tipis -->
                    <div class="h-4 w-px bg-slate-200 dark:bg-zinc-800"></div>

                    <!-- Theme toggle button -->
                    @include('partials.theme-toggle')

                    <!-- Tombol Aksi (Masuk & Pesan) rapat dengan menu -->
                    <div class="flex items-center space-x-3">
                        @auth
                            @if(auth()->user()->is_admin)
                                <a href="{{ route('admin.index') }}" class="px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-400 text-xs font-semibold border border-amber-200 dark:border-amber-800/50 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors">
                                    Admin Area
                                </a>
                            @endif
                            <a href="{{ route('dashboard.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors">
                                Dashboard
                            </a>
                            <form action="{{ route('logout') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-sm font-medium text-slate-500 dark:text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors px-2 py-2">
                                    Keluar
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#4A6FA5] hover:bg-[#3D5E8C] transition-colors shadow-xs">
                                Masuk
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Mobile Header Right Actions -->
                <div class="flex md:hidden items-center gap-1.5">
                    @include('partials.theme-toggle')
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-[#4A6FA5] hover:bg-[#3D5E8C]">
                            Masuk
                        </a>
                    @else
                        <a href="{{ route('dashboard.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-800 dark:text-zinc-200 bg-slate-100 dark:bg-zinc-800">
                            Dashboard
                        </a>
                    @endguest
                    <button @click="mobileMenu = !mobileMenu" class="p-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Menu Mobile">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    </header>
    {{-- ===================== Mobile Drawer (Muncul dari Bawah / Bottom Sheet) ===================== --}}
    <div x-show="mobileMenu" x-cloak
         x-effect="document.body.style.overflow = mobileMenu ? 'hidden' : ''"
         x-transition:enter="transition-transform duration-300 ease-out"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition-transform duration-250 ease-in"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed inset-0 z-50 flex flex-col bg-white dark:bg-zinc-950 md:hidden">

        {{-- Drawer Top Header Bar --}}
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 px-5 py-4 shrink-0 bg-white dark:bg-zinc-950">
            <a href="{{ route('home') }}" @click="mobileMenu = false" class="flex items-center space-x-3 shrink-0">
                <img src="{{ asset('images/logo.png') }}" alt="VexaHost" class="h-8 w-auto object-contain">
                <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Vexa<span class="text-[#4A6FA5]">Host</span></span>
            </a>
            <button @click="mobileMenu = false" class="rounded-lg p-2 text-slate-500 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors" aria-label="Tutup Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Drawer Scrollable Content --}}
        <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4 text-slate-800 dark:text-zinc-200">
            <!-- Beranda -->
            <a href="{{ route('home') }}" @click="mobileMenu = false" class="block px-3 py-2 text-sm font-semibold {{ request()->routeIs('home') ? 'text-[#4A6FA5] bg-blue-50/50 dark:bg-zinc-900' : 'text-slate-800 dark:text-zinc-200' }} rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                Beranda
            </a>

            <!-- Accordion Produk -->
            <div>
                <button @click="mobileProduk = !mobileProduk" class="w-full flex justify-between items-center px-3 py-2 text-sm font-semibold text-slate-800 dark:text-zinc-200 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>Produk Cloud</span>
                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileProduk ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="mobileProduk" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-slate-100 dark:border-zinc-800 ml-3">
                    <a href="{{ route('home') }}#pricing" @click="mobileMenu = false" class="block px-3 py-2 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5]">Cloud VPS KVM</a>
                    <a href="{{ route('home') }}#ai-packages" @click="mobileMenu = false" class="block px-3 py-2 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5] flex items-center justify-between">
                        <span>AI Combo Packages</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Baru</span>
                    </a>
                    <a href="{{ route('home') }}#database-packages" @click="mobileMenu = false" class="block px-3 py-2 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5] flex items-center justify-between">
                        <span>Managed Database</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-zinc-200">Baru</span>
                    </a>
                </div>
            </div>

            <!-- Accordion Paket VPS -->
            <div>
                <button @click="mobileVps = !mobileVps" class="w-full flex justify-between items-center px-3 py-2 text-sm font-semibold text-slate-800 dark:text-zinc-200 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>Paket VPS</span>
                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileVps ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="mobileVps" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-slate-100 dark:border-zinc-800 ml-3">
                    @foreach($navVpsSpecs as $spec)
                        <a href="{{ route('checkout', $spec->id) }}" @click="mobileMenu = false" class="flex justify-between items-center px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5]">
                            <span>{{ $spec->name }}</span>
                            <span class="text-[11px] font-bold font-mono-code text-slate-500 dark:text-zinc-400">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                        </a>
                    @endforeach
                    <a href="{{ route('home') }}#pricing" @click="mobileMenu = false" class="block px-3 py-1.5 text-xs font-bold text-[#4A6FA5]">Lihat Semua Paket VPS &rarr;</a>
                </div>
            </div>

            <!-- Accordion AI Agent -->
            <div>
                <button @click="mobileAi = !mobileAi" class="w-full flex justify-between items-center px-3 py-2 text-sm font-semibold text-slate-800 dark:text-zinc-200 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>AI Agent</span>
                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileAi ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="mobileAi" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-slate-100 dark:border-zinc-800 ml-3">
                    @foreach($navAiSpecs as $spec)
                        <a href="{{ route('checkout', $spec->id) }}" @click="mobileMenu = false" class="flex justify-between items-center px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5]">
                            <span>{{ $spec->name }}</span>
                            <span class="text-[11px] font-bold font-mono-code text-slate-500 dark:text-zinc-400">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                        </a>
                    @endforeach
                    <a href="{{ route('home') }}#ai-packages" @click="mobileMenu = false" class="block px-3 py-1.5 text-xs font-bold text-[#4A6FA5]">Lihat Semua Fitur AI &rarr;</a>
                </div>
            </div>

            <!-- Accordion Database -->
            <div>
                <button @click="mobileDb = !mobileDb" class="w-full flex justify-between items-center px-3 py-2 text-sm font-semibold text-slate-800 dark:text-zinc-200 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>Database (DB)</span>
                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileDb ? 'rotate-180 text-[#4A6FA5]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="mobileDb" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-slate-100 dark:border-zinc-800 ml-3">
                    @foreach($navDbSpecs as $spec)
                        <a href="{{ route('checkout', $spec->id) }}" @click="mobileMenu = false" class="flex justify-between items-center px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-zinc-300 hover:text-[#4A6FA5]">
                            <span>{{ $spec->name }}</span>
                            <span class="text-[11px] font-bold font-mono-code text-slate-500 dark:text-zinc-400">Rp {{ number_format($spec->sell_price / 1000, 0) }}rb</span>
                        </a>
                    @endforeach
                    <a href="{{ route('home') }}#database-packages" @click="mobileMenu = false" class="block px-3 py-1.5 text-xs font-bold text-[#4A6FA5]">Lihat Semua Fitur Database &rarr;</a>
                </div>
            </div>

            {{-- Direct Links --}}
            <div class="border-t border-slate-100 dark:border-zinc-800 pt-3 mt-2 space-y-1">
                <a href="{{ route('docs') }}" @click="mobileMenu = false" class="flex items-center justify-between px-3 py-2 text-sm font-medium text-slate-700 dark:text-zinc-300 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>Docs</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                    </svg>
                </a>
                <a href="https://wa.vexahostcloud.my.id" target="_blank" rel="noopener noreferrer" @click="mobileMenu = false" class="flex items-center justify-between px-3 py-2 text-sm font-medium text-slate-700 dark:text-zinc-300 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>WA Gateway</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                    </svg>
                </a>
                <a href="https://build.vexahostcloud.my.id" target="_blank" rel="noopener noreferrer" @click="mobileMenu = false" class="flex items-center justify-between px-3 py-2 text-sm font-medium text-slate-700 dark:text-zinc-300 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    <span>Jasa Web</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7H7M17 7V17"/>
                    </svg>
                </a>
                <a href="{{ route('status') }}" @click="mobileMenu = false" class="block px-3 py-2 text-sm font-medium text-slate-700 dark:text-zinc-300 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-900">
                    Status Sistem
                </a>
            </div>
        </div>

        {{-- Bottom Bar: Fixed / Pinned Auth Actions (Persis Seperti VexaHost WA Gateway) --}}
        <div class="shrink-0 border-t border-slate-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
            @auth
                <div class="space-y-2">
                    <a href="{{ route('dashboard.index') }}" @click="mobileMenu = false" class="block w-full text-center rounded-xl bg-[#4A6FA5] hover:bg-[#3D5E8C] px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-all">
                        Dashboard
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="block w-full text-center px-4 py-1.5 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700">
                            Keluar dari Akun
                        </button>
                    </form>
                </div>
            @else
                <div class="grid grid-cols-2 gap-2.5">
                    <a href="{{ route('login') }}" @click="mobileMenu = false" class="flex items-center justify-center rounded-xl border border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-all text-center">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" @click="mobileMenu = false" class="flex items-center justify-center rounded-xl bg-[#4A6FA5] hover:bg-[#3D5E8C] px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-all text-center">
                        Buat Akun
                    </a>
                </div>
            @endauth
        </div>
    </div>

    <!-- Flash Messages via SweetAlert (Overlay alert, avoids pushing down Hero section) -->
    @if(session('success'))
        <script>
            (function() {
                function showSuccessAlert() {
                    if (window.showSuccess) {
                        window.showSuccess(@json(session('success')));
                    } else if (window.Swal) {
                        window.Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: @json(session('success')),
                            confirmButtonColor: '#111827',
                            confirmButtonText: 'Mengerti',
                            timer: 3500,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'rounded-2xl',
                                confirmButton: 'rounded-lg px-5 py-2.5 font-semibold',
                            }
                        });
                    } else {
                        setTimeout(showSuccessAlert, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showSuccessAlert);
                } else {
                    showSuccessAlert();
                }
            })();
        </script>
    @endif

    @if(session('error'))
        <script>
            (function() {
                function showErrorAlert() {
                    if (window.showError) {
                        window.showError(@json(session('error')));
                    } else if (window.Swal) {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: @json(session('error')),
                            confirmButtonColor: '#111827',
                            confirmButtonText: 'Mengerti',
                            customClass: {
                                popup: 'rounded-2xl',
                                confirmButton: 'rounded-lg px-5 py-2.5 font-semibold',
                            }
                        });
                    } else {
                        setTimeout(showErrorAlert, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showErrorAlert);
                } else {
                    showErrorAlert();
                }
            })();
        </script>
    @endif

    @if($errors->any())
        <script>
            (function() {
                function showValidationErrors() {
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Perhatian',
                            html: `<ul class="text-left text-sm text-slate-600 list-disc list-inside space-y-1 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>`,
                            confirmButtonColor: '#111827',
                            confirmButtonText: 'Mengerti',
                            customClass: {
                                popup: 'rounded-2xl',
                                confirmButton: 'rounded-lg px-5 py-2.5 font-semibold',
                            }
                        });
                    } else {
                        setTimeout(showValidationErrors, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showValidationErrors);
                } else {
                    showValidationErrors();
                }
            })();
        </script>
    @endif

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Enterprise 4-Column Footer -->
    @unless(request()->routeIs('checkout'))
    <footer class="relative overflow-hidden bg-gradient-to-br from-[#F5F8FD] via-[#EBF2FA] to-[#DFEAF7] dark:from-[#09090b] dark:via-[#09090b] dark:to-zinc-950 text-slate-600 dark:text-zinc-400 pt-16 pb-12 border-t border-slate-200/70 dark:border-zinc-800">

        <!-- Ambient 3D Floating Orbs for Footer -->
        <div class="pointer-events-none absolute -top-16 -right-16 w-80 h-80 rounded-full bg-gradient-to-br from-[#CADFF8] via-[#8BB5E8] to-[#4A6FA5] opacity-25 dark:opacity-5 filter blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-16 w-80 h-80 rounded-full bg-gradient-to-tr from-[#7EA7DB] via-[#A8C7F0] to-[#E3EFFF] opacity-30 dark:opacity-5 filter blur-3xl"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 mb-12">

                <!-- Column 1: Brand & Identity -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="flex items-center space-x-3">
                        <img src="{{ asset('images/logo.png') }}" alt="VexaHost" class="h-9 w-auto object-contain">
                        <span class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Vexa<span class="text-[#4A6FA5]">Host</span></span>
                    </div>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-zinc-400 max-w-sm">
                        Platform cloud VPS KVM di Indonesia dengan akses root penuh, storage NVMe SSD, dan kemudahan 1-click control panel.
                    </p>
                    <div class="space-y-1 text-xs text-slate-500 dark:text-zinc-400">
                        <p class="text-slate-500 dark:text-zinc-400 font-normal">Created by <span class="text-slate-800 dark:text-zinc-200 font-medium">vexahostcloud</span></p>
                        <p>Infrastruktur: Tencent Cloud &amp; Lintasarta Cloudeka (Jakarta &amp; Singapore)</p>
                    </div>
                </div>

                <!-- Column 2: PRODUK -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white mb-4">Produk Cloud</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="{{ route('home') }}#pricing" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Cloud VPS KVM</a></li>
                        <li><a href="{{ route('home') }}#pricing" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Student Basic (Rp 80rb)</a></li>
                        <li><a href="{{ route('home') }}#pricing" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Mahasiswa Basic (Rp 90rb)</a></li>
                        <li><a href="{{ route('home') }}#pricing" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Standard Production</a></li>
                        <li><a href="{{ route('home') }}#pricing" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">AI &amp; Automation Stack</a></li>
                        <li><span class="text-slate-400 dark:text-zinc-600">Managed VPS (Segera)</span></li>
                        <li><span class="text-slate-400 dark:text-zinc-600">Bare Metal Server (Segera)</span></li>
                    </ul>
                </div>

                <!-- Column 3: DUKUNGAN & DOKUMENTASI -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white mb-4">Dukungan</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="{{ route('docs') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Dokumentasi Hub</a></li>
                        <li><a href="{{ route('status') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors flex items-center gap-1.5">
                            <span>Status Sistem</span>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        </a></li>
                        <li><a href="{{ route('docs.kelompok', 'keamanan') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Panduan SSH &amp; Security</a></li>
                        <li><a href="{{ route('docs.kelompok', 'control-panel') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Tutorial Coolify &amp; Dokploy</a></li>
                        <li><a href="{{ route('home') }}#faq" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Pertanyaan Umum (FAQ)</a></li>
                        <li><a href="{{ route('home') }}#kontak" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Kontak Dukungan</a></li>
                    </ul>
                </div>

                <!-- Column 4: LEGAL & KEBIJAKAN -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white mb-4">Legal &amp; Kebijakan</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="{{ route('terms') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Ketentuan Layanan (ToS)</a></li>
                        <li><a href="{{ route('privacy') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Kebijakan Privasi</a></li>
                        <li><a href="{{ route('refund') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Kebijakan Refund</a></li>
                        <li><a href="{{ route('terms') }}#aup" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Acceptable Use (AUP)</a></li>
                    </ul>
                </div>
            </div>

            <!-- Payment Methods Banner -->
            <div class="pt-8 pb-8 border-t border-slate-200/80 dark:border-zinc-800">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-800 dark:text-zinc-200">Metode Pembayaran Resmi</p>
                        <p class="text-[11px] text-slate-500 dark:text-zinc-400">Mendukung otomatisasi instan QRIS, Virtual Account Bank, &amp; Toko Shopee</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                        @php
                            $footerPayments = [
                                ['label' => 'QRIS', 'file' => 'qris.svg'],
                                ['label' => 'BCA', 'file' => 'bca.svg'],
                                ['label' => 'Mandiri', 'file' => 'mandiri.svg'],
                                ['label' => 'BNI', 'file' => 'bni.svg'],
                                ['label' => 'BRI', 'file' => 'bri.svg'],
                                ['label' => 'GoPay', 'file' => 'gopay.svg'],
                                ['label' => 'OVO', 'file' => 'ovo.svg'],
                                ['label' => 'DANA', 'file' => 'dana.svg'],
                                ['label' => 'ShopeePay', 'file' => 'shopeepay.svg'],
                                ['label' => 'LinkAja', 'file' => 'linkaja.svg'],
                            ];
                        @endphp
                        @foreach ($footerPayments as $pay)
                            <div class="payment-badge-light flex h-7 shrink-0 items-center justify-center rounded-lg border border-white/80 dark:border-zinc-700 bg-white/90 dark:bg-white/95 backdrop-blur-xs px-2 shadow-2xs hover:bg-white transition-all"
                                 title="{{ $pay['label'] }}">
                                <img src="{{ asset('images/payments/' . $pay['file']) }}"
                                     alt="{{ $pay['label'] }}"
                                     loading="lazy"
                                     class="h-3.5 w-auto max-w-[50px] object-contain">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="pt-6 border-t border-slate-200/80 dark:border-zinc-800 text-[11px] text-slate-500 dark:text-zinc-400 flex flex-col sm:flex-row justify-between items-center gap-3">
                <p>&copy; {{ date('Y') }} VexaHost. All rights reserved. Created by vexahostcloud.</p>
                <div class="flex items-center space-x-5 text-[11px]">
                    <a href="{{ route('terms') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Terms of Service</a>
                    <span>&bull;</span>
                    <a href="{{ route('privacy') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Privacy Policy</a>
                    <span>&bull;</span>
                    <a href="{{ route('refund') }}" class="text-slate-600 dark:text-zinc-400 hover:text-[#4A6FA5] dark:hover:text-[#6588BC] transition-colors">Refund Policy</a>
                </div>
            </div>
        </div>
    </footer>
    @endunless

    <!-- Floating Live Chat / WhatsApp Widget -->
    <div class="fixed bottom-6 right-6 z-40">
        <a href="https://wa.me/{{ \App\Support\NomorWhatsApp::internasional() }}?text=Halo%20VexaHost,%20saya%20butuh%20bantuan%20seputar%20server%20VPS"
           target="_blank"
           rel="noopener"
           title="Chat WhatsApp CS ({{ \App\Support\NomorWhatsApp::lokal() }})"
           class="group flex items-center gap-2 px-4 py-2.5 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-full shadow-lg hover:shadow-xl transition-all hover:scale-105">
            <svg class="w-5 h-5 fill-current animate-pulse" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            <span class="text-xs font-bold tracking-wide">CS</span>
        </a>
    </div>

    @include('partials.invoice-modal')

    @stack('scripts')
    <script>
        // Smooth and accurate scroll to hash on page load (e.g. from /dashboard or direct route)
        window.addEventListener('load', function() {
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    setTimeout(function() {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                }
            }
        });
    </script>
</body>
</html>
