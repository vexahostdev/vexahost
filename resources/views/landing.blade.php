@extends('layouts.app', ['title' => 'VexaHost — Cloud VPS NVMe KVM Indonesia Mulai Rp 80rb/bln'])

@section('content')
<!-- Promo Ad Popup Banner Modal (Center, Closable) -->
<div x-data="{
        showPromo: true,
        closePromo() {
            this.showPromo = false;
        }
     }"
     x-show="showPromo"
     x-cloak
     @keydown.escape.window="closePromo()"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
     style="display: none;">

    <!-- Dark Backdrop with Blur -->
    <div x-show="showPromo"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
         @click="closePromo()"></div>

    <!-- Modal Card (Pure Image Banner) -->
    <div x-show="showPromo"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-md z-10 flex flex-col items-end">

        <!-- Close 'X' Button (Outside Image) -->
        <button type="button"
                @click="closePromo()"
                class="mb-2.5 z-30 w-8 h-8 rounded-full bg-slate-900/80 hover:bg-[#4A6FA5] text-white flex items-center justify-center transition-all cursor-pointer border border-white/20 shadow-lg backdrop-blur-xs hover:scale-110 active:scale-95"
                aria-label="Tutup Iklan">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <!-- Pure Image Banner (Click to explore VPS pricing) -->
        <div class="w-full bg-[#0B0F19] rounded-2xl shadow-2xl overflow-hidden border-4 border-[#4A6FA5] shadow-[0_20px_50px_rgba(74,111,165,0.35)]">
            <a href="#pricing"
               @click="closePromo()"
               class="block relative w-full aspect-[800/560] overflow-hidden cursor-pointer"
               title="Deploy Cloud VPS KVM Cepat &amp; Andal — VexaHost">
                <picture>
                    <source srcset="{{ asset('images/promo-banner.webp') }}" type="image/webp">
                    <img src="{{ asset('images/promo-banner.jpg') }}"
                         alt="Deploy Cloud VPS KVM Cepat &amp; Andal — VexaHost"
                         width="800"
                         height="560"
                         loading="eager"
                         decoding="async"
                         class="w-full h-full object-cover">
                </picture>
            </a>
        </div>
    </div>
</div>

<!-- Hero Section (Seamless Full-Bleed Showcase Integrated with Header) -->
<section class="relative overflow-hidden bg-gradient-to-br from-[#F5F8FD] via-[#EBF2FA] to-[#DFEAF7] dark:from-[#09090b] dark:via-[#09090b] dark:to-zinc-950 -mt-16 pt-20 sm:pt-24 lg:pt-24 pb-4 sm:pb-5 border-b border-slate-200/50 dark:border-zinc-800 min-h-screen lg:min-h-dvh flex flex-col justify-between">

    <!-- Ambient 3D Floating Orbs (Bokeh & Clay Depth across Full Hero Background) -->
    <!-- Top-Left Large Orb (Extends behind Header) -->
    <div class="pointer-events-none absolute -top-8 -left-12 w-64 sm:w-80 h-64 sm:h-80 rounded-full bg-gradient-to-br from-[#CADFF8] via-[#8BB5E8] to-[#4A6FA5] opacity-40 dark:opacity-10 filter blur-2xl"></div>
    <!-- Top-Center Subtle Orb (Extends behind Header) -->
    <div class="pointer-events-none absolute -top-12 left-1/3 w-48 sm:w-60 h-48 sm:h-60 rounded-full bg-gradient-to-b from-[#A5C6EE] to-transparent opacity-35 dark:opacity-10 filter blur-3xl"></div>
    <!-- Bottom-Left Orb -->
    <div class="pointer-events-none absolute -bottom-16 left-10 w-60 sm:w-72 h-60 sm:h-72 rounded-full bg-gradient-to-tr from-[#7EA7DB] via-[#A8C7F0] to-[#E3EFFF] opacity-45 dark:opacity-10 filter blur-2xl"></div>
    <!-- Right Orb Behind Organic Blob -->
    <div class="pointer-events-none absolute top-1/2 -right-16 -translate-y-1/2 w-80 sm:w-96 h-80 sm:h-96 rounded-full bg-gradient-to-bl from-[#9BBFE6] via-[#658EC4] to-[#3B5F90] opacity-35 dark:opacity-15 filter blur-3xl"></div>
    <!-- Bottom-Center Orb -->
    <div class="pointer-events-none absolute -bottom-10 right-1/3 w-52 sm:w-64 h-52 sm:h-64 rounded-full bg-gradient-to-tl from-[#4A6FA5] to-[#C0D9F7] opacity-30 dark:opacity-10 filter blur-2xl"></div>

    <!-- Subtle Decorative Speed Slashes (Slanted Lines in Background) -->
    <div class="pointer-events-none absolute top-20 left-1/4 w-16 sm:w-24 h-0.5 bg-white/70 dark:bg-zinc-800/60 rotate-[-40deg] rounded-full"></div>
    <div class="pointer-events-none absolute top-1/2 left-8 w-12 sm:w-16 h-0.5 bg-white/60 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>
    <div class="pointer-events-none absolute top-28 right-1/3 w-20 sm:w-28 h-0.5 bg-white/50 dark:bg-zinc-800/40 rotate-[-40deg] rounded-full"></div>
    <div class="pointer-events-none absolute bottom-32 right-1/4 w-14 sm:w-20 h-0.5 bg-white/60 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full my-auto py-4 sm:py-6">

        <!-- 2-Column Responsive Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-8 items-center">

            <!-- Left Column: Typography & Action (Cols 1-6) -->
            <div class="lg:col-span-6 flex flex-col items-start text-left">

                <!-- Main Display Headline (Bold, All Caps, Tight Tracking) -->
                <h1 class="text-5xl sm:text-6xl lg:text-7xl xl:text-[4.75rem] font-black text-slate-900 tracking-tight leading-none uppercase">
                    CLOUD VPS
                </h1>

                <!-- Highlight Pill Badge (Without Dash) -->
                <div class="mt-3 sm:mt-4">
                    <span class="inline-flex items-center px-4 py-1.5 rounded-lg bg-[#4A6FA5] text-white font-extrabold text-sm sm:text-base tracking-wider uppercase shadow-xs">
                        LAYANAN TERBAIK
                    </span>
                </div>

                <!-- Dynamic Editorial Subheadline (Italic) -->
                <p class="mt-4 sm:mt-5 text-2xl sm:text-3xl lg:text-[2.05rem] xl:text-4xl italic font-semibold text-slate-800 leading-snug">
                    Menghadirkan Solusi Kreatif &amp; Andal
                </p>

                <!-- Short Modern Body Copy -->
                <p class="mt-3.5 sm:mt-4 text-sm sm:text-base text-slate-600 max-w-lg leading-relaxed font-normal">
                    Solusi Cloud VPS KVM NVMe andal dengan infrastruktur enterprise kelas dunia, aktivasi cepat, dan performa tinggi untuk mendukung pertumbuhan bisnis digital Anda.
                </p>

                <!-- Single CTA Button ('Lihat Paket' with Standard Rounded-lg Corner) -->
                <div class="mt-7 sm:mt-9">
                    <a href="#pricing"
                       class="inline-flex items-center justify-center px-8 sm:px-10 py-3.5 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-sm sm:text-base font-bold shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-95 cursor-pointer">
                        Lihat Paket
                    </a>
                </div>

                <!-- Bottom-Left URL Link -->
                <div class="mt-8 sm:mt-12">
                    <a href="{{ route('home') }}"
                       class="text-xs sm:text-sm font-bold text-slate-700 hover:text-[#4A6FA5] transition-colors tracking-tight">
                        www.vexahostcloud.my.id
                    </a>
                </div>
            </div>

            <!-- Right Column: Organic Fluid Blob Wave + Vector Rocket Centerpiece (Cols 7-12) -->
            <div class="lg:col-span-6 relative flex flex-col items-center justify-center">

                <!-- High-Resolution Vector Visual Art (Pure Scalable SVG) -->
                <svg viewBox="0 0 500 560"
                     class="w-full h-auto max-w-[400px] sm:max-w-[500px] lg:max-w-[560px] xl:max-w-[620px] mx-auto filter drop-shadow-[0_25px_40px_rgba(74,111,165,0.28)]"
                     fill="none"
                     xmlns="http://www.w3.org/2000/svg"
                     role="img"
                     aria-label="VexaHost High-Performance Cloud Rocket Launch">
                    <defs>
                        <!-- Organic Wave Blob Gradient -->
                        <linearGradient id="vhBlobGrad" x1="150" y1="50" x2="450" y2="520" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#5E85BC" />
                            <stop offset="50%" stop-color="#4A6FA5" />
                            <stop offset="100%" stop-color="#345480" />
                        </linearGradient>

                        <!-- Rocket Fuselage Body Gradient -->
                        <linearGradient id="vhRocketBodyGrad" x1="210" y1="150" x2="290" y2="350" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#FFFFFF" />
                            <stop offset="35%" stop-color="#EBF1FA" />
                            <stop offset="70%" stop-color="#D4E2F3" />
                            <stop offset="100%" stop-color="#B8CEE8" />
                        </linearGradient>

                        <!-- Rocket 3D Right Shadow -->
                        <linearGradient id="vhRocketShadow" x1="250" y1="150" x2="290" y2="150" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="rgba(0,0,0,0)" />
                            <stop offset="100%" stop-color="rgba(30,50,80,0.22)" />
                        </linearGradient>

                        <!-- Left Fin Gradient -->
                        <linearGradient id="vhFinGradLeft" x1="180" y1="280" x2="225" y2="360" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#3A5B8A" />
                            <stop offset="100%" stop-color="#243D61" />
                        </linearGradient>

                        <!-- Right Fin Gradient -->
                        <linearGradient id="vhFinGradRight" x1="320" y1="280" x2="275" y2="360" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#3A5B8A" />
                            <stop offset="100%" stop-color="#243D61" />
                        </linearGradient>

                        <!-- Window Radial Glow -->
                        <radialGradient id="vhWindowGlow" cx="50%" cy="40%" r="60%">
                            <stop offset="0%" stop-color="#2D466E" />
                            <stop offset="70%" stop-color="#14243B" />
                            <stop offset="100%" stop-color="#0A1422" />
                        </radialGradient>

                        <!-- Smoke Plume Gradient -->
                        <linearGradient id="vhSmokeGrad" x1="250" y1="360" x2="250" y2="530" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#3B5F90" stop-opacity="0.8" />
                            <stop offset="40%" stop-color="#345480" stop-opacity="0.95" />
                            <stop offset="100%" stop-color="#29446B" />
                        </linearGradient>

                        <!-- Thrust Engine Flame Gradient -->
                        <linearGradient id="vhFlameGrad" x1="250" y1="355" x2="250" y2="385" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#FFFFFF" />
                            <stop offset="35%" stop-color="#93C5FD" />
                            <stop offset="75%" stop-color="#3B82F6" stop-opacity="0.85" />
                            <stop offset="100%" stop-color="#1D4ED8" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <!-- 1. Background Organic Blob (Breathing / Counter-Float Parallax Animation) -->
                    <g class="vh-blob-group">
                        <!-- Dynamic Fluid Organic Wave Blob Backdrop (Matching Reference Shape) -->
                        <path d="M 230,80 
                                 C 340,65 460,110 470,220 
                                 C 480,310 440,360 455,440 
                                 C 468,505 400,535 340,530 
                                 C 270,525 220,535 150,520 
                                 C 80,505 105,430 140,370 
                                 C 180,300 130,220 150,150 
                                 C 168,88 190,85 230,80 Z" 
                              fill="url(#vhBlobGrad)" />

                        <!-- Inner Organic Highlight Curve for 3D Surface Depth -->
                        <path d="M 250,95 
                                 C 335,82 435,125 448,220 
                                 C 458,300 422,345 435,420 
                                 C 438,435 432,450 420,465 
                                 C 380,400 405,300 395,225 
                                 C 382,130 305,98 250,95 Z" 
                              fill="white" opacity="0.12" />
                    </g>

                    <!-- 2. Ambient Flying Sparkles / Stars -->
                    <g class="vh-sparkle pointer-events-none" style="animation-delay: 0s;">
                        <circle cx="170" cy="220" r="2.5" fill="#FFFFFF" opacity="0.75" />
                    </g>
                    <g class="vh-sparkle pointer-events-none" style="animation-delay: 1.2s;">
                        <circle cx="335" cy="175" r="3" fill="#FFFFFF" opacity="0.85" />
                    </g>
                    <g class="vh-sparkle pointer-events-none" style="animation-delay: 0.6s;">
                        <circle cx="315" cy="385" r="2.2" fill="#93C5FD" opacity="0.75" />
                    </g>

                    <!-- 3. Rocket Flight Unit (Smooth Floating Up & Down + Hover Lift) -->
                    <g class="vh-rocket-group">
                        <g transform="translate(-37.5, -42) scale(1.15)">

                        <!-- Rocket Exhaust Smoke Plume (Pulsing Thrust) -->
                        <g class="vh-smoke-group">
                            <path d="M 235,370 
                                     C 230,400 215,420 220,450 
                                     C 225,480 200,510 240,525 
                                     C 255,530 265,515 275,510 
                                     C 290,525 310,490 290,460 
                                     C 275,435 270,400 265,370 Z" 
                                  fill="url(#vhSmokeGrad)" />
                        </g>

                        <!-- Thrust Engine Flame Pulse / Energy Glow -->
                        <g class="vh-flame-group">
                            <ellipse cx="250" cy="365" rx="13" ry="18" fill="url(#vhFlameGrad)" filter="drop-shadow(0 0 8px rgba(96,165,250,0.85))" />
                            <ellipse cx="250" cy="362" rx="6" ry="10" fill="#FFFFFF" opacity="0.95" />
                        </g>

                        <!-- Rocket Left Fin -->
                        <path d="M 218,295 
                                 C 195,310 180,345 178,368 
                                 C 195,365 210,350 220,338 Z" 
                              fill="url(#vhFinGradLeft)" />
                        <path d="M 218,295 C 195,310 180,345 178,368 C 188,366 200,358 208,348 Z" fill="black" opacity="0.15" />

                        <!-- Rocket Right Fin -->
                        <path d="M 282,295 
                                 C 305,310 320,345 322,368 
                                 C 305,365 290,350 280,338 Z" 
                              fill="url(#vhFinGradRight)" />
                        <path d="M 282,295 C 305,310 320,345 322,368 C 312,366 300,358 292,348 Z" fill="black" opacity="0.2" />

                        <!-- Rocket Main Fuselage Body -->
                        <path d="M 250,150 
                                 C 230,195 218,255 218,340 
                                 C 232,348 268,348 282,340 
                                 C 282,255 270,195 250,150 Z" 
                              fill="url(#vhRocketBodyGrad)" 
                              filter="drop-shadow(0 6px 12px rgba(0,0,0,0.18))" />

                        <!-- Fuselage 3D Curvature Shadow (Right Half) -->
                        <path d="M 250,150 
                                 C 260,195 275,255 282,340 
                                 C 265,345 250,345 250,345 
                                 C 250,255 250,195 250,150 Z" 
                              fill="url(#vhRocketShadow)" />

                        <!-- Nose Cone Specular Highlight -->
                        <path d="M 250,150 
                                 C 246,162 242,180 240,200 
                                 C 244,198 252,198 256,200 
                                 C 255,180 252,162 250,150 Z" 
                                  fill="white" opacity="0.55" />

                        <!-- Upper Horizontal White Band / Stripe -->
                        <path d="M 221,245 
                                 C 236,252 264,252 279,245 
                                 L 278,258 
                                 C 263,265 237,265 222,258 Z" 
                              fill="white" />

                        <!-- Lower Horizontal White Band / Stripe -->
                        <path d="M 218,285 
                                 C 235,293 265,293 282,285 
                                 L 282,298 
                                 C 265,306 235,306 218,298 Z" 
                              fill="white" />

                        <!-- Porthole 1 (Top Circular Window) -->
                        <circle cx="250" cy="222" r="16" fill="white" />
                        <circle cx="250" cy="222" r="12" fill="url(#vhWindowGlow)" />
                        <path d="M 243,215 A 8 8 0 0 1 257 215 A 8 8 0 0 0 243 215 Z" fill="white" opacity="0.45" />

                        <!-- Porthole 2 (Bottom Circular Window) -->
                        <circle cx="250" cy="268" r="16" fill="white" />
                        <circle cx="250" cy="268" r="12" fill="url(#vhWindowGlow)" />
                        <path d="M 243,261 A 8 8 0 0 1 257 261 A 8 8 0 0 0 243 261 Z" fill="white" opacity="0.45" />

                        <!-- Rocket Bottom Engine Exhaust Nozzle -->
                        <path d="M 238,344 
                                 L 235,358 
                                 C 245,362 255,362 265,358 
                                 L 262,344 Z" 
                              fill="#233B5E" />
                        </g>
                    </g>
                </svg>
            </div>

        </div>

    </div>

    <!-- Integrated Running Stack & Ecosystem Marquee (Full Screen Width / Edge-to-Edge) -->
    <div class="w-full relative z-10 mt-auto pt-5 sm:pt-6 border-t border-slate-200/50 dark:border-zinc-800 overflow-hidden">
        <div class="flustra-stack-marquee relative w-full overflow-hidden">
            <div class="pointer-events-none absolute left-0 top-0 bottom-0 z-10 w-12 sm:w-24 bg-gradient-to-r from-[#F5F8FD] via-[#F5F8FD]/80 dark:from-[#09090b] dark:via-[#09090b]/80 to-transparent"></div>
            <div class="pointer-events-none absolute right-0 top-0 bottom-0 z-10 w-12 sm:w-24 bg-gradient-to-l from-[#DFEAF7] via-[#DFEAF7]/80 dark:from-[#09090b] dark:via-[#09090b]/80 to-transparent"></div>

            <div class="flustra-stack-track flex w-max items-center gap-3 sm:gap-4">
                    @php
                        $ecosystemLogos = [
                            ['label' => 'Tencent Cloud', 'file' => 'images/logos/providers/tencent.svg'],
                            ['label' => 'Lintasarta Cloudeka', 'file' => 'images/logos/providers/cloudeka.svg'],
                            ['label' => 'Coolify', 'file' => 'images/logos/panels/coolify.svg'],
                            ['label' => 'Dokploy', 'file' => 'images/logos/panels/dokploy.svg'],
                            ['label' => 'Docker', 'file' => 'images/logos/panels/docker.svg'],
                            ['label' => 'aaPanel', 'file' => 'images/logos/panels/aapanel.svg'],
                            ['label' => 'CloudPanel', 'file' => 'images/logos/panels/cloudpanel.svg'],
                            ['label' => 'CyberPanel', 'file' => 'images/logos/panels/cyberpanel.svg'],
                            ['label' => 'HestiaCP', 'file' => 'images/logos/panels/hestiacp.svg'],
                            ['label' => 'Ollama AI', 'file' => 'images/logos/apps/ollama.svg'],
                            ['label' => 'AnythingLLM', 'file' => 'images/logos/apps/anythingllm.svg'],
                            ['label' => 'LibreChat', 'file' => 'images/logos/apps/librechat.svg'],
                            ['label' => 'n8n Automation', 'file' => 'images/logos/apps/n8n.svg'],
                            ['label' => 'VS Code Server', 'file' => 'images/logos/apps/vscode_server.svg'],
                            ['label' => 'Gitea', 'file' => 'images/logos/apps/gitea_forgejo.svg'],
                            ['label' => 'Uptime Kuma', 'file' => 'images/logos/apps/uptime_kuma.svg'],
                            ['label' => 'Netdata', 'file' => 'images/logos/apps/netdata_beszel.svg'],
                            ['label' => 'WordPress', 'file' => 'images/logos/apps/wordpress.svg'],
                            ['label' => 'Ghost CMS', 'file' => 'images/logos/apps/ghost.svg'],
                            ['label' => 'Strapi', 'file' => 'images/logos/apps/strapi_directus.svg'],
                            ['label' => 'PrestaShop', 'file' => 'images/logos/apps/prestashop_bagisto.svg'],
                            ['label' => 'PostgreSQL', 'file' => 'images/logos/databases/postgres.svg'],
                            ['label' => 'MySQL', 'file' => 'images/logos/databases/mysql.svg'],
                            ['label' => 'Redis', 'file' => 'images/logos/databases/redis.svg'],
                            ['label' => 'MongoDB', 'file' => 'images/logos/databases/mongodb.svg'],
                            ['label' => 'Qdrant Vector DB', 'file' => 'images/logos/databases/qdrant.svg'],
                            ['label' => 'CloudBeaver', 'file' => 'images/logos/databases/cloudbeaver.svg'],
                            ['label' => 'OpenClaw', 'file' => 'images/logos/apps/openclaw.svg'],
                            ['label' => 'Hermes Agent', 'file' => 'images/logos/apps/hermes_agent.svg'],
                            ['label' => 'Agent Zero', 'file' => 'images/logos/apps/agent_zero.svg'],
                            ['label' => '9Router', 'file' => 'images/logos/apps/9router.svg'],
                            ['label' => 'OmniRoute', 'file' => 'images/logos/apps/omniroute.svg'],
                        ];
                    @endphp

                    @for ($i = 0; $i < 2; $i++)
                        @foreach ($ecosystemLogos as $logo)
                            <div class="flex h-9 sm:h-10 shrink-0 items-center justify-center gap-2 sm:gap-2.5 rounded-xl border border-white/80 dark:border-zinc-800 bg-white/75 dark:bg-zinc-900/90 backdrop-blur-xs hover:bg-white dark:hover:bg-zinc-800 px-3 sm:px-3.5 shadow-2xs transition-all duration-200 hover:scale-105 select-none"
                                 title="{{ $logo['label'] }}">
                                <img src="{{ asset($logo['file']) }}"
                                     alt="{{ $logo['label'] }}"
                                     class="h-4 w-4 sm:h-5 sm:w-5 shrink-0 object-contain">
                                <span class="text-xs font-bold text-slate-800 dark:text-zinc-200 tracking-tight whitespace-nowrap">{{ $logo['label'] }}</span>
                            </div>
                        @endforeach
                    @endfor
                </div>
            </div>
        </div>
</section>

<!-- Pricing Section (3 cards prominent + 4th card peeking with side slide buttons) -->
<section id="pricing" class="scroll-mt-16 sm:scroll-mt-20 pt-16 pb-3 bg-slate-50" x-data="{
    scrollLeft() {
        const c = this.$refs.slider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: -step, behavior: 'smooth' });
    },
    scrollRight() {
        const c = this.$refs.slider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: step, behavior: 'smooth' });
    }
}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Centered Header -->
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Paket Cloud VPS</h2>
            <p class="text-sm text-slate-600 mt-2">Dapatkan dedicated compute resource dan full root access tanpa biaya tersembunyi.</p>
        </div>

        <!-- Relative Carousel Container with Left & Right Side Buttons -->
        <div class="relative">
            <!-- Left Side Slide Button -->
            <button @click="scrollLeft()" 
                    title="Geser sebelumnya"
                    class="hidden sm:flex absolute -left-4 sm:-left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Carousel Track: 3 full cards + 4th card peeking on right -->
            <div x-ref="slider" class="flex gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 pt-1 no-scrollbar px-1">
                @foreach(($vpsSpecs ?? $specs->where('category', 'vps')) as $spec)
                    @php
                        $isPopular = ($spec->name === 'Standard');
                        $isBusiness = ($spec->name === 'Business' || $spec->name === 'AI Production Pro');
                        $isStartup = ($spec->name === 'Startup' || $spec->name === 'AI Production');
                        $isMahasiswa = ($spec->name === 'Mahasiswa Basic');
                        $isStudent = ($spec->name === 'Student Basic');
                    @endphp
                    <div class="pricing-card snap-start shrink-0 w-[84%] sm:w-[calc(48%-12px)] lg:w-[calc((100%-48px)/3.2)] bg-white rounded-xl border {{ $isPopular ? 'border-[#4A6FA5] shadow-sm ring-1 ring-[#4A6FA5]' : 'border-slate-200' }} p-7 sm:p-8 flex flex-col justify-between">
                        <div>
                            <!-- Header badge -->
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    @if($spec->badge)
                                        <span class="{{ $isPopular ? 'text-[#4A6FA5]' : 'text-slate-700' }} font-bold">{{ $spec->badge }}</span>
                                    @elseif($isPopular)
                                        <span class="text-[#4A6FA5] font-bold">Terpopuler</span>
                                    @elseif($isBusiness)
                                        <span class="text-slate-700 font-bold">Business Tier</span>
                                    @elseif($isStartup)
                                        <span class="text-slate-700 font-bold">Scale Up</span>
                                    @elseif($isMahasiswa)
                                        <span class="text-slate-700 font-bold">Mahasiswa</span>
                                    @elseif($isStudent)
                                        <span class="text-slate-700 font-bold">Entry Level</span>
                                    @else
                                        <span>Paket Standar</span>
                                    @endif
                                </span>
                                @if(!$spec->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-900 border border-amber-300">
                                        Stok Habis
                                    </span>
                                @endif
                            </div>

                            <!-- Title -->
                            <h3 class="text-xl font-bold text-slate-900">{{ $spec->name }}</h3>
                            <p class="text-xs text-slate-500 mt-1 mb-5">
                                @if($spec->tagline)
                                    {{ $spec->tagline }}
                                @elseif($spec->name === 'Student Basic')
                                    Cocok untuk belajar Linux &amp; portfolio web dasar
                                @elseif($spec->name === 'Mahasiswa Basic')
                                    Resource 2 vCPU lega untuk praktikum, mini server &amp; bot
                                @elseif($spec->name === 'Standard')
                                    Pilihan ideal website bisnis, REST API, &amp; bot yang berjalan 24 jam
                                @elseif($spec->name === 'Premium')
                                    Multi-aplikasi Docker &amp; production klien beban tinggi
                                @elseif($spec->name === 'Business' || $spec->name === 'AI Production Pro')
                                    Resource 8 core masif untuk database besar, multi-service &amp; enterprise
                                @elseif($spec->name === 'Startup' || $spec->name === 'AI Production')
                                    Solusi tangguh 4 core untuk aplikasi startup, backend API &amp; traffic bertumbuh
                                @else
                                    Solusi komputasi cloud handal dengan performa terisolasi
                                @endif
                            </p>

                            <!-- Price -->
                            <div class="mb-6 pb-5 border-b border-slate-100">
                                <div class="flex items-baseline gap-1 mt-0.5">
                                    <span class="text-3xl sm:text-4xl font-black text-slate-900 font-mono-code">Rp {{ number_format($spec->sell_price, 0, ',', '.') }}</span>
                                    <span class="text-xs text-slate-500">/bln</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Tarif flat perpanjangan, batalkan kapan saja.</p>
                            </div>

                            <!-- CTA Button -->
                            @if($spec->is_active)
                                <a href="{{ route('checkout', $spec->id) }}"
                                   class="w-full block text-center py-3 px-4 rounded-lg text-sm font-bold transition-colors mb-6 {{ $isPopular ? 'bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white' : 'bg-slate-900 hover:bg-slate-800 text-white' }}">
                                    Pilih Paket Ini &rarr;
                                </a>
                            @else
                                <button type="button" disabled
                                   class="w-full block text-center py-3 px-4 rounded-lg text-sm font-semibold mb-6 bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed">
                                    Stok Habis / Tidak Tersedia
                                </button>
                            @endif

                            <!-- Features list -->
                            <ul class="space-y-3.5 text-xs text-slate-700">
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $spec->cpu }} Core</strong> vCPU High Performance</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $spec->ram }} GB</strong> RAM DDR4/DDR5</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $spec->disk }} GB</strong> NVMe SSD</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $spec->bandwidth }} Mbps</strong> unmetered bandwidth</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Dedicated IP Publik IPv4 static</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Full root access SSH (port 22)</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Pre-install Coolify / Dokploy / cPanel</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span>Tencent Cloud &amp; Cloudeka Lintasarta</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Right Side Slide Button -->
            <button @click="scrollRight()" 
                    title="Geser berikutnya"
                    class="hidden sm:flex absolute -right-4 sm:-right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Mobile-only slide buttons -->
        <div class="flex sm:hidden justify-center items-center gap-3 mt-4">
            <button @click="scrollLeft()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="scrollRight()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</section>

<!-- VEXAHOST AI COMBO PACKAGES SECTION -->
<section id="ai-packages" class="scroll-mt-16 sm:scroll-mt-20 pt-8 pb-3 bg-slate-50" x-data="{
    scrollAiLeft() {
        const c = this.$refs.aiSlider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: -step, behavior: 'smooth' });
    },
    scrollAiRight() {
        const c = this.$refs.aiSlider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: step, behavior: 'smooth' });
    }
}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Centered Header -->
        <div class="text-center max-w-2xl mx-auto mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">VexaHost AI Combo Packages</h2>
            <p class="text-sm text-slate-600 mt-2">Mesin coding otomatis dan workstation bertenaga AI siap pakai dalam hitungan menit.</p>
        </div>

        @php
            $aiPackages = $aiSpecs ?? \App\Models\VpsSpec::where('category', 'ai_combo')->orderBy('sell_price', 'asc')->get();
        @endphp

        <!-- Relative Carousel Container with Left & Right Side Buttons -->
        <div class="relative">
            <!-- Left Side Slide Button -->
            <button @click="scrollAiLeft()" 
                    title="Geser sebelumnya"
                    class="hidden sm:flex absolute -left-4 sm:-left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Carousel Track: 3 full cards + 4th card peeking on right -->
            <div x-ref="aiSlider" class="flex gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-4 pt-1 no-scrollbar px-1">
                @foreach($aiPackages as $package)
                    @php
                        $isPopular = str_contains(strtolower($package->name), 'workstation') || ($package->id == 8);
                        $features = is_array($package->features) ? $package->features : (json_decode($package->features, true) ?: []);
                    @endphp
                    <div class="pricing-card snap-start shrink-0 w-[84%] sm:w-[calc(48%-12px)] lg:w-[calc((100%-48px)/3.2)] bg-white rounded-xl border {{ $isPopular ? 'border-[#4A6FA5] shadow-sm ring-1 ring-[#4A6FA5]' : 'border-slate-200' }} p-7 sm:p-8 flex flex-col justify-between"
                         x-data="{ openAi: false }">
                        <div>
                            <!-- Header badge -->
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    @if($package->badge)
                                        <span class="{{ $isPopular ? 'text-[#4A6FA5]' : 'text-slate-700' }} font-bold">{{ $package->badge }}</span>
                                    @elseif($isPopular)
                                        <span class="text-[#4A6FA5] font-bold">Terpopuler</span>
                                    @elseif(str_contains(strtolower($package->name), 'terminal'))
                                        <span class="text-slate-700 font-bold">Developer Stack</span>
                                    @elseif(str_contains(strtolower($package->name), 'hermes'))
                                        <span class="text-slate-700 font-bold">Autonomous Hub</span>
                                    @else
                                        <span class="text-slate-700 font-bold">Private RAG</span>
                                    @endif
                                </span>
                                @if(!$package->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-900 border border-amber-300">
                                        Stok Habis
                                    </span>
                                @endif
                            </div>

                            <!-- Title -->
                            <h3 class="text-xl font-bold text-slate-900">{{ $package->name }}</h3>
                            <p class="text-xs text-slate-500 mt-1 mb-5">
                                {{ $package->tagline }}
                            </p>

                            <!-- Price -->
                            <div class="mb-6 pb-5 border-b border-slate-100">
                                <div class="flex items-baseline gap-1 mt-0.5">
                                    <span class="text-3xl sm:text-4xl font-black text-slate-900 font-mono-code">Rp {{ number_format($package->sell_price, 0, ',', '.') }}</span>
                                    <span class="text-xs text-slate-500">/bln</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">Tarif flat perpanjangan, batalkan kapan saja.</p>
                            </div>

                            <!-- CTA Button -->
                            @if($package->is_active)
                                <a href="{{ route('checkout', $package->id) }}"
                                   class="w-full block text-center py-3 px-4 rounded-lg text-sm font-bold transition-colors mb-6 {{ $isPopular ? 'bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white' : 'bg-slate-900 hover:bg-slate-800 text-white' }}">
                                    Pilih Paket Ini &rarr;
                                </a>
                            @else
                                <button type="button" disabled
                                   class="w-full block text-center py-3 px-4 rounded-lg text-sm font-semibold mb-6 bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed">
                                    Stok Habis / Tidak Tersedia
                                </button>
                            @endif

                            <!-- Features list (Fitur Inti) -->
                            <ul class="space-y-3.5 text-xs text-slate-700">
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $package->cpu }} Core</strong> vCPU High Performance</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $package->ram }} GB</strong> RAM DDR4/DDR5</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $package->disk }} GB</strong> NVMe SSD</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span><strong class="text-slate-900 font-bold text-sm">{{ $package->bandwidth }} Mbps</strong> unmetered bandwidth</span>
                                </li>
                                @if(count($features) > 0)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 text-[#4A6FA5] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>{{ $features[0] }}</span>
                                    </li>
                                @endif
                            </ul>

                            <!-- Expandable remaining features -->
                            <div x-show="openAi" x-transition class="mt-3.5 space-y-3.5">
                                <ul class="space-y-3.5 text-xs text-slate-700">
                                    @foreach(array_slice($features, 1) as $feat)
                                        <li class="flex items-start gap-2.5">
                                            <svg class="w-4 h-4 text-[#4A6FA5] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>{{ $feat }}</span>
                                        </li>
                                    @endforeach
                                    <li class="flex items-center gap-2.5">
                                        <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Dedicated IP Publik IPv4 static</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <svg class="w-4 h-4 text-[#4A6FA5] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span>Tencent Cloud &amp; Cloudeka Lintasarta</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Toggle Button -->
                            <button type="button" @click="openAi = !openAi"
                                    class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-slate-900 hover:text-[#4A6FA5] transition-colors focus:outline-none">
                                <span x-text="openAi ? 'Tutup rincian ↑' : '+ Buka rincian lengkap ↓'"></span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Right Side Slide Button -->
            <button @click="scrollAiRight()" 
                    title="Geser berikutnya"
                    class="hidden sm:flex absolute -right-4 sm:-right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Mobile-only slide buttons -->
        <div class="flex sm:hidden justify-center items-center gap-3 mt-6">
            <button @click="scrollAiLeft()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="scrollAiRight()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</section>

<!-- PAKET MANAGED DATABASE (Dedicated DB VPS) SECTION -->
<section id="database-packages" class="scroll-mt-16 sm:scroll-mt-20 pt-8 pb-16 bg-slate-50 border-b border-slate-200" x-data="{
    scrollDbLeft() {
        const c = this.$refs.dbSlider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: -step, behavior: 'smooth' });
    },
    scrollDbRight() {
        const c = this.$refs.dbSlider;
        const card = c.querySelector('.pricing-card');
        const step = card ? (card.offsetWidth + 24) : 320;
        c.scrollBy({ left: step, behavior: 'smooth' });
    }
}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Centered Header -->
        <div class="text-center max-w-2xl mx-auto mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Paket Managed Database</h2>
            <p class="text-sm text-slate-600 mt-2">Server database khusus yang terpisah dari server aplikasi, sehingga beban aplikasi tidak mengganggu database Anda.</p>
        </div>

        @php
            $dbPackages = $dbSpecs ?? \App\Models\VpsSpec::where('category', 'managed_db')->orderBy('sell_price', 'asc')->get();
        @endphp

        <!-- Relative Carousel Container with Slide Buttons -->
        <div class="relative">
            <!-- Left Side Slide Button (tablet only, hidden on md+ because all 3 cards fit) -->
            <button @click="scrollDbLeft()" 
                    title="Geser sebelumnya"
                    class="hidden sm:flex md:hidden absolute -left-4 sm:-left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Carousel Track: horizontal scroll on mobile (< md), 3-column grid on desktop (md+) -->
            <div x-ref="dbSlider" class="flex md:grid md:grid-cols-3 gap-6 overflow-x-auto md:overflow-visible scroll-smooth snap-x snap-mandatory pb-4 pt-1 no-scrollbar px-1">
            @foreach($dbPackages as $package)
                @php
                    $isPopular = str_contains(strtolower($package->name), 'standard') || ($package->id == 12);
                    $isEnterprise = str_contains(strtolower($package->name), 'enterprise') || ($package->id == 13);
                    $isMicro = str_contains(strtolower($package->name), 'micro') || ($package->id == 11);
                    $features = is_array($package->features) ? $package->features : (json_decode($package->features, true) ?: []);

                    $badgeLabel = match($package->id) {
                        11 => 'Entry Level',
                        12 => 'Terpopuler / Best Value',
                        13 => 'Enterprise & AI Vector',
                        default => 'Dedicated DB'
                    };

                    $businessFeatures = [];
                    $techFeatures = [];
                    foreach($features as $feat) {
                        $parts = explode(':', $feat, 2);
                        $title = count($parts) === 2 ? trim($parts[0]) : null;
                        if($title && in_array($title, ['Spesifikasi Mesin', 'Pilihan Engine Siap Pakai'])) {
                            $techFeatures[] = ['title' => $title, 'desc' => trim($parts[1])];
                        } else {
                            $businessFeatures[] = [
                                'title' => $title,
                                'desc' => count($parts) === 2 ? trim($parts[1]) : $feat
                            ];
                        }
                    }
                    $topFeatures = array_slice($businessFeatures, 0, 3);
                    $moreFeatures = array_slice($businessFeatures, 3);
                @endphp
                <div class="pricing-card snap-start shrink-0 w-[84%] sm:w-[calc(48%-12px)] md:w-auto md:shrink bg-white rounded-xl border {{ $isPopular ? 'border-[#4A6FA5] shadow-sm ring-1 ring-[#4A6FA5]' : 'border-slate-200' }} p-7 sm:p-8 flex flex-col justify-between"
                     x-data="{ openDb: false }">
                    <div>
                        <!-- Header badge -->
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                @if($package->badge)
                                    <span class="{{ $isPopular ? 'text-[#4A6FA5]' : 'text-slate-700' }} font-bold">{{ $package->badge }}</span>
                                @elseif($isPopular)
                                    <span class="text-[#4A6FA5] font-bold">{{ $badgeLabel }}</span>
                                @elseif($isEnterprise)
                                    <span class="text-slate-700 font-bold">{{ $badgeLabel }}</span>
                                @elseif($isMicro)
                                    <span class="text-slate-700 font-bold">{{ $badgeLabel }}</span>
                                @else
                                    <span class="text-slate-700 font-bold">{{ $badgeLabel }}</span>
                                @endif
                            </span>
                            @if(!$package->is_active)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-900 border border-amber-300">
                                    Stok Habis
                                </span>
                            @endif
                        </div>

                        <!-- Title -->
                        <h3 class="text-xl font-bold text-slate-900">{{ $package->name }}</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-5">
                            {{ $package->tagline }}
                        </p>

                        <!-- Price -->
                        <div class="mb-6 pb-5 border-b border-slate-100">
                            <div class="flex items-baseline gap-1 mt-0.5">
                                <span class="text-3xl sm:text-4xl font-black text-slate-900 font-mono-code">Rp {{ number_format($package->sell_price, 0, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">/bln</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Tarif flat perpanjangan, batalkan kapan saja.</p>
                        </div>

                        <!-- CTA Button -->
                        @if($package->is_active)
                            <a href="{{ route('checkout', $package->id) }}"
                               class="w-full block text-center py-3 px-4 rounded-lg text-sm font-bold transition-colors mb-6 {{ $isPopular ? 'bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white' : 'bg-slate-900 hover:bg-slate-800 text-white' }}">
                                Pilih Paket Ini &rarr;
                            </a>
                        @else
                            <button type="button" disabled
                               class="w-full block text-center py-3 px-4 rounded-lg text-sm font-semibold mb-6 bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed">
                                Stok Habis / Tidak Tersedia
                            </button>
                        @endif

                        <!-- Features list: Poin Utama Terlihat -->
                        <ul class="space-y-3 text-xs text-slate-700">
                            @foreach($topFeatures as $item)
                                <li class="flex items-start gap-2.5">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span class="leading-relaxed">
                                        @if($item['title'])
                                            <strong class="text-slate-900 font-bold">{{ $item['title'] }}:</strong>
                                        @endif
                                        <span class="text-slate-600">{{ $item['desc'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <!-- Expandable remaining features & specs -->
                        <div x-show="openDb" x-transition class="mt-3 space-y-3">
                            @if(count($moreFeatures) > 0)
                                <ul class="space-y-3 text-xs text-slate-700">
                                    @foreach($moreFeatures as $item)
                                        <li class="flex items-start gap-2.5">
                                            <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span class="leading-relaxed">
                                                @if($item['title'])
                                                    <strong class="text-slate-900 font-bold">{{ $item['title'] }}:</strong>
                                                @endif
                                                <span class="text-slate-600">{{ $item['desc'] }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if(count($techFeatures) > 0)
                                <!-- Developer & Technical Specs Sub-Card (Poin Bawah) -->
                                <div class="pt-3.5 border-t border-slate-100 bg-slate-50/90 -mx-3 p-3.5 rounded-lg border border-slate-200/80 space-y-2.5">
                                    @foreach($techFeatures as $tech)
                                        <div class="text-[11px] leading-relaxed">
                                            <span class="text-slate-500 font-bold block text-[10px] uppercase tracking-wider">{{ $tech['title'] }}:</span>
                                            <span class="font-medium text-slate-900">{{ $tech['desc'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Toggle Button -->
                        <button type="button" @click="openDb = !openDb"
                                class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-slate-900 hover:text-[#4A6FA5] transition-colors focus:outline-none">
                            <span x-text="openDb ? 'Tutup rincian ↑' : '+ Buka rincian lengkap ↓'"></span>
                        </button>
                    </div>
                </div>
            @endforeach
            </div>

            <!-- Right Side Slide Button (tablet only) -->
            <button @click="scrollDbRight()" 
                    title="Geser berikutnya"
                    class="hidden sm:flex md:hidden absolute -right-4 sm:-right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full border border-slate-300 bg-white hover:bg-slate-50 items-center justify-center text-slate-900 shadow-md transition-all focus:outline-none hover:scale-105">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- Mobile-only slide buttons -->
        <div class="flex sm:hidden justify-center items-center gap-3 mt-4">
            <button @click="scrollDbLeft()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900 shadow-xs active:scale-95 transition-transform" aria-label="Geser ke kiri">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="scrollDbRight()" class="p-2 rounded-lg border border-slate-300 bg-white text-slate-900 shadow-xs active:scale-95 transition-transform" aria-label="Geser ke kanan">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</section>

<!-- RUNNING PAYMENT METHOD TICKER (Pindah ke Bawah Database Pricing) -->
<section aria-label="Metode Pembayaran yang Didukung"
         class="relative overflow-hidden border-y border-slate-200/70 dark:border-zinc-800 bg-gradient-to-r from-[#F5F8FD] via-[#EBF2FA] to-[#DFEAF7] dark:from-[#09090b] dark:via-zinc-950 dark:to-[#09090b] py-4 sm:py-5">
    <div class="w-full flex items-center px-4 sm:px-6 lg:px-8">
        <!-- Track Marquee -->
        <div class="flustra-pay-marquee relative flex-1 overflow-hidden">
            <div class="pointer-events-none absolute left-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-r from-[#F5F8FD] via-[#F5F8FD]/80 dark:from-[#09090b] dark:via-[#09090b]/80 to-transparent"></div>
            <div class="pointer-events-none absolute right-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-l from-[#DFEAF7] via-[#DFEAF7]/80 dark:from-[#09090b] dark:via-[#09090b]/80 to-transparent"></div>

            <div class="flustra-pay-track flex w-max items-center gap-3 sm:gap-4">
                @php
                    $marqueePayments = [
                        ['label' => 'QRIS', 'file' => 'qris.svg'],
                        ['label' => 'Bank BCA', 'file' => 'bca.svg'],
                        ['label' => 'Bank Mandiri', 'file' => 'mandiri.svg'],
                        ['label' => 'Bank BNI', 'file' => 'bni.svg'],
                        ['label' => 'Bank BRI', 'file' => 'bri.svg'],
                        ['label' => 'Bank BSI', 'file' => 'bsi.svg'],
                        ['label' => 'Bank Permata', 'file' => 'permata.svg'],
                        ['label' => 'CIMB Niaga', 'file' => 'cimb.svg'],
                        ['label' => 'GoPay', 'file' => 'gopay.svg'],
                        ['label' => 'OVO', 'file' => 'ovo.svg'],
                        ['label' => 'DANA', 'file' => 'dana.svg'],
                        ['label' => 'ShopeePay', 'file' => 'shopeepay.svg'],
                        ['label' => 'LinkAja', 'file' => 'linkaja.svg'],
                        ['label' => 'Indomaret', 'file' => 'indomaret.svg'],
                        ['label' => 'Alfamart', 'file' => 'alfamart.svg'],
                        ['label' => 'Akulaku PayLater', 'file' => 'akulaku.svg'],
                    ];
                @endphp

                @for ($i = 0; $i < 2; $i++)
                    @foreach ($marqueePayments as $payment)
                        <div class="light-isolated flex h-9 sm:h-10 shrink-0 items-center justify-center rounded-xl border border-white/80 dark:border-zinc-700 bg-white/95 dark:bg-zinc-100 backdrop-blur-xs hover:bg-white px-3 sm:px-4 shadow-2xs transition-all duration-200 hover:scale-105 select-none"
                             title="{{ $payment['label'] }}">
                            <img src="{{ asset('images/payments/' . $payment['file']) }}"
                                 alt="{{ $payment['label'] }}"
                                 class="h-4 sm:h-5 w-auto max-w-[65px] sm:max-w-[78px] object-contain">
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    </div>
</section>

<!-- COMPARISON TABLE SECTION (VexaHost vs Alternatif Lain) -->
<section class="py-20 bg-white dark:bg-[#09090b] border-b border-slate-200 dark:border-zinc-800">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-zinc-100">Kenapa VexaHost vs Alternatif Lain?</h2>
            <p class="text-sm text-slate-600 dark:text-zinc-400 mt-2">Bandingkan transparansi biaya, fleksibilitas root, dan kecepatan setup kami.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse border border-slate-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <thead>
                    <tr class="bg-slate-900 dark:bg-zinc-900 text-white text-xs uppercase tracking-wider">
                        <th class="p-4 sm:p-5 font-bold">Karakteristik / Fitur</th>
                        <th class="p-4 sm:p-5 font-bold bg-[#4A6FA5] text-white">
                            <div class="flex items-center gap-2">
                                <span>VexaHost Cloud VPS</span>
                                <span class="px-2 py-0.5 rounded bg-white/20 text-[10px] font-mono-code">Rekomendasi</span>
                            </div>
                        </th>
                        <th class="p-4 sm:p-5 font-bold text-slate-300 dark:text-zinc-300">Shared Hosting Biasa</th>
                        <th class="p-4 sm:p-5 font-bold text-slate-300 dark:text-zinc-300">Hyperscaler (AWS / GCP)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-zinc-800 text-xs sm:text-sm text-slate-700 dark:text-zinc-300">
                    <!-- Row 1 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Harga / Biaya Bulanan</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-[#4A6FA5] dark:text-[#7da2d8] font-mono-code">Rp 80rb – 590rb (Flat)</td>
                        <td class="p-4 sm:p-5 text-slate-600 dark:text-zinc-400">Rp 50rb – 150rb (Banyak limitasi)</td>
                        <td class="p-4 sm:p-5 text-slate-500 dark:text-zinc-400 font-mono-code">$10 – $100+ (Tagihan membengkak)</td>
                    </tr>
                    <!-- Row 2 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Akses Root (SSH Port 22)</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-[#4A6FA5] dark:text-[#7da2d8] flex items-center gap-1.5">
                            <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-zinc-800 text-[#4A6FA5] dark:text-[#7da2d8] flex items-center justify-center font-bold text-xs">✓</span> Full Root Access
                        </td>
                        <td class="p-4 sm:p-5 text-slate-400 dark:text-zinc-500 font-medium">❌ Tidak ada (cPanel saja)</td>
                        <td class="p-4 sm:p-5 text-slate-700 dark:text-zinc-300 font-medium">✓ Full Root Access</td>
                    </tr>
                    <!-- Row 3 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Dedicated Compute Resource</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-slate-900 dark:text-zinc-100">
                            ✓ vCPU &amp; RAM KVM Terisolasi
                        </td>
                        <td class="p-4 sm:p-5 text-slate-500 dark:text-zinc-400">❌ Rebutan dengan ratusan akun</td>
                        <td class="p-4 sm:p-5 text-slate-700 dark:text-zinc-300">✓ Dedicated Resource</td>
                    </tr>
                    <!-- Row 4 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Modern Control Panel</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-[#4A6FA5] dark:text-[#7da2d8]">
                            ✓ 1-Click Coolify / Dokploy (Gratis)
                        </td>
                        <td class="p-4 sm:p-5 text-slate-600 dark:text-zinc-400">cPanel standar (tidak support Docker)</td>
                        <td class="p-4 sm:p-5 text-slate-400 dark:text-zinc-500">❌ Tanpa panel (setup manual rumit)</td>
                    </tr>
                    <!-- Row 5 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Dukungan Bahasa</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-slate-900 dark:text-zinc-100">
                            ✓ 100% Bahasa Indonesia (WhatsApp &amp; Web)
                        </td>
                        <td class="p-4 sm:p-5 text-slate-600 dark:text-zinc-400">✓ Bahasa Indonesia</td>
                        <td class="p-4 sm:p-5 text-slate-500 dark:text-zinc-400">❌ Bahasa Inggris / Bot otomatis</td>
                    </tr>
                    <!-- Row 6 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Setup &amp; Pembayaran</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-[#4A6FA5] dark:text-[#7da2d8]">
                            QRIS, VA Bank &amp; Shopee (&lt; 15 menit)
                        </td>
                        <td class="p-4 sm:p-5 text-slate-600 dark:text-zinc-400">Instan tapi fitur terkunci</td>
                        <td class="p-4 sm:p-5 text-slate-400 dark:text-zinc-500">Wajib Kartu Kredit Internasional</td>
                    </tr>
                    <!-- Row 7 -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="p-4 sm:p-5 font-semibold text-slate-900 dark:text-zinc-100">Biaya Transfer Egress / Kuota</td>
                        <td class="p-4 sm:p-5 bg-blue-50/40 dark:bg-zinc-900/80 font-bold text-slate-900 dark:text-zinc-100">
                            ✓ Bebas Biaya Kuota Tersembunyi
                        </td>
                        <td class="p-4 sm:p-5 text-slate-500 dark:text-zinc-400">Sering di-throttle jika traffic ramai</td>
                        <td class="p-4 sm:p-5 text-slate-500 dark:text-zinc-400 font-mono-code">Mahal ($0.09 / GB bandwidth keluar)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- USE CASES / SOLUTIONS SECTION (Solusi untuk berbagai kebutuhan) -->
<section class="py-20 bg-slate-50 border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Solusi Andal untuk Berbagai Kebutuhan</h2>
            <p class="text-sm text-slate-600 mt-2">Dukungan arsitektur fleksibel untuk mahasiswa, freelancer, hingga aplikasi komersial.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Case 1 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Web &amp; Application Hosting</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Deploy aplikasi Next.js, Node.js, Laravel, WordPress, atau Go dengan sertifikat SSL otomatis dan latensi koneksi rendah.
                </p>
            </div>

            <!-- Case 2 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Development &amp; Staging Sandbox</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Uji coba arsitektur sistem, simulasi microservices, dan lingkungan staging pengujian klien sebelum rilis ke publik.
                </p>
            </div>

            <!-- Case 3 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Dedicated Database Server</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Jalankan PostgreSQL, MySQL, Redis, atau MongoDB di server database khusus dengan penyimpanan NVMe SSD.
                </p>
            </div>

            <!-- Case 4 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">CI/CD &amp; Pipeline Automation</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Host self-hosted GitHub Actions runner, GitLab runner, atau instance workflow otomatisasi n8n tanpa batasan kuota menit.
                </p>
            </div>

            <!-- Case 5 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Bot &amp; Background Workers</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Jalankan bot Telegram, bot Discord, scheduled cron jobs, atau worker Python yang berjalan 24 jam di server Anda sendiri.
                </p>
            </div>

            <!-- Case 6 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:border-[#4A6FA5]/60 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#4A6FA5] flex items-center justify-center font-bold mb-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">AI Inference &amp; Agent Runners</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Eksperimen model embedding lokal, LLM inference ringan, dan framework agen mandiri di node komputasi dedicated VexaHost.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================= -->
<!-- SECTION: INFRASTRUKTUR CLOUD                                      -->
<!-- ================================================================= -->
{{-- Hanya fakta yang tercatat di sistem: penyedia, region, dan jenis virtualisasi.
     Tidak ada angka ping, bandwidth node, kapasitas DDoS, atau sertifikasi fasilitas,
     karena server dibeli dari penyedia dan tidak diukur sendiri oleh VexaHost. --}}
<section class="py-20 bg-white text-slate-800 border-b border-slate-200 relative overflow-hidden" x-data="{ activeRegion: 'jakarta' }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">

        <!-- Section Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">
                    Infrastruktur Cloud
                </h2>
                <p class="text-sm text-slate-600 mt-2 max-w-xl">
                    Server VexaHost berjalan di atas infrastruktur Tencent Cloud dan Lintasarta Cloudeka, dengan virtualisasi KVM dan akses root penuh.
                </p>
            </div>

            <!-- Region Switcher -->
            <div class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 shrink-0 self-start md:self-auto">
                <button @click="activeRegion = 'jakarta'"
                        :class="activeRegion === 'jakarta' ? 'bg-white text-slate-900 font-bold shadow-xs border border-slate-200' : 'text-slate-600 hover:text-slate-900'"
                        class="px-4 py-2 rounded-lg text-xs transition-all">
                    Jakarta
                </button>
                <button @click="activeRegion = 'singapore'"
                        :class="activeRegion === 'singapore' ? 'bg-white text-slate-900 font-bold shadow-xs border border-slate-200' : 'text-slate-600 hover:text-slate-900'"
                        class="px-4 py-2 rounded-lg text-xs transition-all">
                    Singapore
                </button>
            </div>
        </div>

        <!-- Detail Region -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 sm:p-8 mb-12 shadow-xs">
            <!-- Region 1: Jakarta -->
            <div x-show="activeRegion === 'jakarta'" x-transition class="space-y-6">
                <div class="flex items-center gap-3 pb-6 border-b border-slate-200">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 text-[#4A6FA5] flex items-center justify-center font-bold font-mono-code text-sm">
                        JKT
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Datacenter Jakarta</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Untuk aplikasi yang melayani pengguna di Indonesia</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2 text-xs">
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Penyedia Infrastruktur</span>
                        <p class="text-slate-900 font-bold text-sm">Tencent Cloud &amp; Lintasarta Cloudeka</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">Penyedia mengikuti paket yang Anda pesan dan tercantum di dashboard.</p>
                    </div>
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Cocok Untuk</span>
                        <p class="text-slate-900 font-bold text-sm">Layanan dengan Pengguna Lokal</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">E-commerce Indonesia, API pembayaran, web sekolah, dan aplikasi yang diakses dari jaringan domestik.</p>
                    </div>
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Akses Server</span>
                        <p class="text-slate-900 font-bold text-sm">IP Publik &amp; Root SSH</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">Setiap server mendapat IP publik sendiri dan akses root penuh.</p>
                    </div>
                </div>
            </div>

            <!-- Region 2: Singapore -->
            <div x-show="activeRegion === 'singapore'" x-transition class="space-y-6" style="display: none;">
                <div class="flex items-center gap-3 pb-6 border-b border-slate-200">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 text-[#4A6FA5] flex items-center justify-center font-bold font-mono-code text-sm">
                        SIN
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Datacenter Singapore</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Untuk rute internasional dan integrasi layanan global</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2 text-xs">
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Penyedia Infrastruktur</span>
                        <p class="text-slate-900 font-bold text-sm">Tencent Cloud</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">Tersedia untuk paket yang berjalan di Tencent Cloud.</p>
                    </div>
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Cocok Untuk</span>
                        <p class="text-slate-900 font-bold text-sm">Integrasi Layanan Global</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">Bot Discord, integrasi API GitHub dan OpenAI, serta server staging untuk pengguna lintas negara.</p>
                    </div>
                    <div>
                        <span class="text-slate-400 font-mono-code uppercase text-[11px] block mb-1">Akses Server</span>
                        <p class="text-slate-900 font-bold text-sm">IP Publik &amp; Root SSH</p>
                        <p class="text-slate-600 mt-1 leading-relaxed">Setiap server mendapat IP publik sendiri dan akses root penuh.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fondasi Server -->
        <div class="grid grid-cols-1 lg:grid-cols-3 border border-slate-200 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 rounded-2xl bg-white shadow-xs overflow-hidden">
            <div class="p-6 sm:p-7">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 rounded bg-blue-50 border border-blue-200 text-[#4A6FA5] font-mono-code font-bold text-xs">KERNEL 01</span>
                    <h4 class="text-base font-bold text-slate-900">Virtualisasi KVM</h4>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Setiap VPS berjalan sebagai mesin virtual KVM dengan kernel sendiri, dengan alokasi vCPU dan RAM sesuai paket yang Anda pilih.
                </p>
                <div class="mt-4 flex items-center gap-2 text-[11px] font-mono-code text-[#4A6FA5] font-semibold">
                    <span>✓</span> Akses root penuh
                </div>
            </div>

            <div class="p-6 sm:p-7">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 rounded bg-blue-50 border border-blue-200 text-[#4A6FA5] font-mono-code font-bold text-xs">STORAGE 02</span>
                    <h4 class="text-base font-bold text-slate-900">Penyimpanan NVMe SSD</h4>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Disk NVMe SSD dari penyedia infrastruktur dengan kapasitas sesuai paket. Pencadangan data tetap menjadi tanggung jawab pemilik server.
                </p>
                <div class="mt-4 flex items-center gap-2 text-[11px] font-mono-code text-[#4A6FA5] font-semibold">
                    <span>✓</span> Kapasitas tercantum di setiap paket
                </div>
            </div>

            <div class="p-6 sm:p-7">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 rounded bg-blue-50 border border-blue-200 text-[#4A6FA5] font-mono-code font-bold text-xs">SECURITY 03</span>
                    <h4 class="text-base font-bold text-slate-900">Keamanan di Tangan Anda</h4>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Atur firewall (UFW), login SSH key, dan Fail2ban sesuai kebutuhan aplikasi Anda. Panduan langkah demi langkah tersedia di dokumentasi.
                </p>
                <a href="{{ route('docs') }}" class="mt-4 inline-flex items-center gap-2 text-[11px] font-mono-code text-[#4A6FA5] font-semibold hover:underline">
                    <span>✓</span> Buka panduan hardening
                </a>
            </div>
        </div>

    </div>
</section>

<!-- ================================================================= -->
<!-- SECTION: DEVELOPER RUNTIME & 1-CLICK STACK CONSOLE               -->
<!-- ================================================================= -->
<section id="panels" class="py-24 bg-white border-b border-slate-200" x-data="{ activeStack: 'coolify' }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Runtime &amp; Control Panel Siap Pakai</h2>
            <p class="text-sm text-slate-600 mt-2">Pilih antarmuka manajemen server favorit Anda saat checkout. VPS Anda langsung aktif bersama stack pilihan.</p>
        </div>

        <!-- Terminal & Stack Interactive Split Showcase -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
            
            <!-- Left: Runtime Stack Selector List -->
            <div class="lg:col-span-5 space-y-3 flex flex-col justify-between">
                <!-- Stack 1: Coolify -->
                <button @click="activeStack = 'coolify'"
                        :class="activeStack === 'coolify' ? 'border-[#4A6FA5] dark:border-zinc-300 bg-blue-50/40 dark:bg-zinc-800 ring-1 ring-[#4A6FA5] dark:ring-zinc-300' : 'border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="w-full text-left p-4 rounded-xl border transition-all flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-[#4A6FA5] text-white font-bold text-sm flex items-center justify-center shrink-0 mt-0.5">
                        C
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900">Coolify PaaS</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">Self-hosted Vercel</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Deploy otomatis Next.js, Laravel, Node.js &amp; database langsung dari GitHub repo dengan free SSL Let's Encrypt.
                        </p>
                    </div>
                </button>

                <!-- Stack 2: Dokploy -->
                <button @click="activeStack = 'dokploy'"
                        :class="activeStack === 'dokploy' ? 'border-[#4A6FA5] dark:border-zinc-300 bg-blue-50/40 dark:bg-zinc-800 ring-1 ring-[#4A6FA5] dark:ring-zinc-300' : 'border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="w-full text-left p-4 rounded-xl border transition-all flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-[#4A6FA5] text-white font-bold text-sm flex items-center justify-center shrink-0 mt-0.5">
                        D
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900">Dokploy Docker Manager</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">Traefik Proxy</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Kelola multi-container Docker Compose, monitoring utilisasi RAM/CPU, dan rute domain dengan antarmuka web modern.
                        </p>
                    </div>
                </button>

                <!-- Stack 3: CloudPanel -->
                <button @click="activeStack = 'cloudpanel'"
                        :class="activeStack === 'cloudpanel' ? 'border-[#4A6FA5] dark:border-zinc-300 bg-blue-50/40 dark:bg-zinc-800 ring-1 ring-[#4A6FA5] dark:ring-zinc-300' : 'border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="w-full text-left p-4 rounded-xl border transition-all flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-[#4A6FA5] text-white font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                        CP
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900">CloudPanel / cPanel</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">PHP &amp; WordPress</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Web server Nginx super cepat, PHP-FPM multi-version, database MySQL/MariaDB, dan manajemen DNS domain instan.
                        </p>
                    </div>
                </button>

                <!-- Stack 4: AI Agent Runner -->
                <button @click="activeStack = 'ai'"
                        :class="activeStack === 'ai' ? 'border-[#4A6FA5] dark:border-zinc-300 bg-blue-50/40 dark:bg-zinc-800 ring-1 ring-[#4A6FA5] dark:ring-zinc-300' : 'border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="w-full text-left p-4 rounded-xl border transition-all flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-[#4A6FA5] text-white font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                        AI
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-bold text-slate-900">Hermes &amp; OpenClaw</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">AI Automation</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Eksekusi agen AI otonom, proxy LLM Omniroute, dan background workflow berdaya komputasi tinggi tanpa batas waktu.
                        </p>
                    </div>
                </button>
            </div>

            <!-- Right: Interactive Live Developer Terminal Preview -->
            <div class="lg:col-span-7 bg-[#09090b] dark:bg-[#111113] rounded-2xl border border-zinc-800 dark:border-zinc-700/80 p-6 flex flex-col justify-between shadow-xl font-mono-code text-xs text-zinc-300">
                <!-- Terminal Chrome Bar -->
                <div>
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-800">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-zinc-700"></span>
                            <span class="w-3 h-3 rounded-full bg-zinc-700"></span>
                            <span class="w-3 h-3 rounded-full bg-zinc-700"></span>
                            <span class="text-zinc-500 text-[11px] ml-2" x-text="'root@vexahost-vps ~ ' + activeStack"></span>
                        </div>
                        <span class="text-[10px] text-zinc-500 uppercase tracking-wider">KVM Shell (Port 22)</span>
                    </div>

                    <!-- Terminal Body Content -->
                    <div class="space-y-2.5 leading-relaxed">
                        <!-- Coolify terminal view -->
                        <div x-show="activeStack === 'coolify'" class="space-y-2">
                            <p class="text-zinc-400"># 1. Hubungkan repo GitHub ke Coolify Engine</p>
                            <p class="text-white"><span class="text-[#6588BC] font-bold">$</span> coolify deploy --repo=github.com/developer/saas-app --branch=main</p>
                            <p class="text-zinc-400">&gt; Building Next.js / Laravel container via Dockerfile...</p>
                            <p class="text-zinc-400">&gt; Generating Let's Encrypt Wildcard SSL certificate...</p>
                            <p class="text-white font-bold">✓ Deployment Selesai dalam 38 detik! Server aktif di https://app.domain.id</p>
                            <p class="text-zinc-500 pt-2">Port Web UI: <span class="text-zinc-300">http://103.150.xx.xx:8000</span> (Dashboard Admin Siap Pakai)</p>
                        </div>

                        <!-- Dokploy terminal view -->
                        <div x-show="activeStack === 'dokploy'" class="space-y-2" style="display: none;">
                            <p class="text-zinc-400"># 1. Jalankan Traefik Reverse Proxy &amp; Postgres Container</p>
                            <p class="text-white"><span class="text-[#6588BC] font-bold">$</span> dokploy compose up -d ./production-stack.yml</p>
                            <p class="text-zinc-400">&gt; [+] Running 4/4 [redis, postgres-16, api-gateway, frontend]</p>
                            <p class="text-zinc-400">&gt; Auto-routing incoming HTTPS traffic via Traefik v3</p>
                            <p class="text-white font-bold">✓ Cluster Docker aktif dan terisolasi pada dedicated RAM KVM!</p>
                            <p class="text-zinc-500 pt-2">Port Web UI: <span class="text-zinc-300">http://103.150.xx.xx:3000</span> (Dokploy Dashboard)</p>
                        </div>

                        <!-- CloudPanel terminal view -->
                        <div x-show="activeStack === 'cloudpanel'" class="space-y-2" style="display: none;">
                            <p class="text-zinc-400"># 1. Konfigurasi VHost Nginx &amp; Multi-PHP Pool</p>
                            <p class="text-white"><span class="text-[#6588BC] font-bold">$</span> clp-site add --domain=toko-online.com --php=8.3 --type=wordpress</p>
                            <p class="text-zinc-400">&gt; Setting up Nginx high-concurrency microcache...</p>
                            <p class="text-zinc-400">&gt; Initializing MySQL database &amp; user credentials...</p>
                            <p class="text-white font-bold">✓ Situs WordPress live dengan kecepatan loading sub-100ms!</p>
                            <p class="text-zinc-500 pt-2">Port Web UI: <span class="text-zinc-300">https://103.150.xx.xx:8443</span> (CloudPanel SSL)</p>
                        </div>

                        <!-- AI Agent terminal view -->
                        <div x-show="activeStack === 'ai'" class="space-y-2" style="display: none;">
                            <p class="text-zinc-400"># 1. Inisialisasi Autonomous Agent &amp; Omniroute Proxy</p>
                            <p class="text-white"><span class="text-[#6588BC] font-bold">$</span> hermes-agent start --workers=4 --proxy-port=8080</p>
                            <p class="text-zinc-400">&gt; Loading vector embeddings cache from NVMe SSD arrays...</p>
                            <p class="text-zinc-400">&gt; Sandbox process ready.</p>
                            <p class="text-white font-bold">✓ Worker AI agent aktif berjalan di background.</p>
                            <p class="text-zinc-500 pt-2">Status: <span class="text-zinc-300">Active Background Daemon (PID: 14022)</span></p>
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-6 border-t border-zinc-800 flex items-center justify-between text-[11px] text-zinc-500">
                    <span>Full root access SSH port 22 disertakan di semua paket.</span>
                    <a href="{{ route('docs') }}" class="text-[#6588BC] hover:underline font-semibold font-sans">Lihat Dokumentasi Lengkap &rarr;</a>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- ================================================================= -->
<!-- SECTION: UNIFIED ENTERPRISE COMPLIANCE & GOVERNANCE RIBBON        -->
<!-- ================================================================= -->
<section class="py-16 bg-white text-slate-800 border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header minimalis -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-8 mb-8 border-b border-slate-200">
            <div>
                <h3 class="text-lg sm:text-xl font-bold text-slate-900">Keamanan &amp; Layanan</h3>
            </div>
            <p class="text-xs text-slate-600 max-w-md">
                Hal-hal yang kami terapkan pada setiap layanan VexaHost.
            </p>
        </div>

        <!-- Unified 4-Column Horizontal Assurance Matrix -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Pillar 1: Penyedia infrastruktur -->
            <div class="space-y-2 border-l-2 border-[#4A6FA5] pl-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold font-mono-code text-[#4A6FA5] bg-blue-50 px-2 py-0.5 rounded border border-blue-100">Tencent &amp; Cloudeka</span>
                    <span class="text-[11px] text-slate-500 font-semibold">Penyedia Cloud</span>
                </div>
                <h4 class="text-sm font-bold text-slate-900">Infrastruktur Penyedia Besar</h4>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Server berjalan di infrastruktur Tencent Cloud dan Lintasarta Cloudeka, dengan pilihan lokasi Jakarta dan Singapore.
                </p>
            </div>

            <!-- Pillar 2: SSL/TLS -->
            <div class="space-y-2 border-l-2 border-[#4A6FA5] pl-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold font-mono-code text-[#4A6FA5] bg-blue-50 px-2 py-0.5 rounded border border-blue-100">HTTPS / AES-256</span>
                    <span class="text-[11px] text-slate-500 font-semibold">Terenkripsi</span>
                </div>
                <h4 class="text-sm font-bold text-slate-900">Kredensial Terenkripsi</h4>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Dashboard dan webhook diakses lewat HTTPS dengan HSTS, dan password server disimpan terenkripsi AES-256 dan hanya bisa dibuka oleh pemilik akun dari dashboard.
                </p>
            </div>

            <!-- Pillar 3: Permintaan server ditangani tim -->
            <div class="space-y-2 border-l-2 border-[#4A6FA5] pl-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold font-mono-code text-[#4A6FA5] bg-blue-50 px-2 py-0.5 rounded border border-blue-100">Maks. 6 Jam Kerja</span>
                    <span class="text-[11px] text-slate-500 font-semibold">Status Transparan</span>
                </div>
                <h4 class="text-sm font-bold text-slate-900">Permintaan Ditangani Tim</h4>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Reinstall OS atau server yang tidak bisa diakses diajukan dari dashboard dan dikerjakan langsung oleh tim kami. Perkembangannya bisa Anda pantau sampai selesai.
                </p>
            </div>

            <!-- Pillar 4: Tim Engineer VexaHost -->
            <div class="space-y-2 border-l-2 border-[#4A6FA5] pl-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold font-mono-code text-[#4A6FA5] bg-blue-50 px-2 py-0.5 rounded border border-blue-100">100% ENGINEER ID</span>
                    <span class="text-[11px] text-slate-500 font-semibold">Lokal Indonesia</span>
                </div>
                <h4 class="text-sm font-semibold text-slate-900">Dukungan <span class="font-normal">Tim Teknis VexaHost</span></h4>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Dukungan teknis responsif langsung dari tim developer dan system administrator berpengalaman di Indonesia tanpa perantara chatbot.
                </p>
            </div>
        </div>

    </div>
</section>

<!-- Testimonials (Clean flat cards) -->
<section class="py-20 bg-slate-50 border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Dipercaya Mahasiswa &amp; Praktisi Lokal</h2>
            <p class="text-sm text-slate-600 mt-2">Pengalaman nyata developer yang menjalankan proyek mereka di VexaHost.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Review 1 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Sebelumnya pusing cari hosting yang bisa jalanin Dokploy buat tugas akhir kampus. Di VexaHost paket 80rb langsung dapet akses root SSH port 22, deploy container microservices jalan mulus tanpa pernah kena suspend acak."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-[#4A6FA5] text-white flex items-center justify-center font-bold text-xs shrink-0">DA</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Daffa Alghifari</p>
                        <p class="text-[11px] text-slate-500">Mahasiswa Sistem Informasi &bull; Kontributor Dev</p>
                    </div>
                </div>
            </div>

            <!-- Review 2 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Buat handle beberapa web klien UMKM pakai Next.js SSR dan database PostgreSQL. Paket Standard 110rb sangat worth it, Let's Encrypt SSL otomatis jalan via Coolify dan bayar perpanjangan bulanan praktis pakai QRIS."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-teal-700 text-white flex items-center justify-center font-bold text-xs shrink-0">NL</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Nabila Larasati</p>
                        <p class="text-[11px] text-slate-500">Full-Stack Freelance Developer</p>
                    </div>
                </div>
            </div>

            <!-- Review 3 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Migrasi worker scraping Python dan cron job background dari hyperscaler luar ke node Jakarta VexaHost. Latensi direct peering ke IIX cuma 2-3ms, proses parsing data real-time jauh lebih kencang dan hemat biaya bulanan."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs shrink-0">FH</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Fajar Hidayatullah</p>
                        <p class="text-[11px] text-slate-500">Backend Engineer &bull; Surabaya</p>
                    </div>
                </div>
            </div>

            <!-- Review 4 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Agensi kami rutin setup staging server buat demo aplikasi ke klien. Pakai template CloudPanel di VexaHost hemat waktu setup berjam-jam, Nginx sama PHP 8.3 langsung siap pakai tanpa ribet konfigurasi manual."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-700 text-white flex items-center justify-center font-bold text-xs shrink-0">RK</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Reza Kurniawan Syahputra</p>
                        <p class="text-[11px] text-slate-500">DevOps Specialist &bull; Studio Digital Bandung</p>
                    </div>
                </div>
            </div>

            <!-- Review 5 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Saya jalanin 8 bot Telegram otomatisasi notifikasi dan workflow n8n non-stop. Ketersediaan resource KVM terbukti stabil, tidak ada isu memory ballooning atau throttle CPU waktu bot banjir request pesan masuk."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-amber-700 text-white flex items-center justify-center font-bold text-xs shrink-0">GP</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Ghani Pradana</p>
                        <p class="text-[11px] text-slate-500">Automation Engineer &bull; Tech Enthusiast</p>
                    </div>
                </div>
            </div>

            <!-- Review 6 -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between shadow-xs hover:border-[#4A6FA5]/50 transition-colors">
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">
                    "Isolasi KVM hardware-nya beneran kerasa, nggak ada CPU steal time waktu compile binary Go dan Docker build. Sempat ada kendala setting UFW awal, tapi admin support WA-nya fast response bantu sampai beres."
                </p>
                <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-rose-700 text-white flex items-center justify-center font-bold text-xs shrink-0">KS</div>
                    <div>
                        <p class="text-xs font-bold text-slate-900">Kevin Samuel Hutapea</p>
                        <p class="text-[11px] text-slate-500">Cloud Infrastructure Engineer</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Accordion (Clean Hostinger style) -->
<section id="faq" class="py-20 bg-white border-b border-slate-200" x-data="{ openFaq: 1 }">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Pertanyaan Umum (FAQ)</h2>
            <p class="text-sm text-slate-600 mt-2">Seputar aktivasi, kontrol server, dan metode pembayaran.</p>
        </div>

        <div class="divide-y divide-slate-200 border-y border-slate-200 bg-white rounded-xl shadow-xs">
            <!-- FAQ 1 -->
            <div class="p-5">
                <button @click="openFaq = (openFaq === 1 ? null : 1)" class="w-full flex justify-between items-center text-left text-sm font-semibold text-slate-900">
                    <span>Berapa lama proses aktivasi setelah pembayaran?</span>
                    <span class="text-slate-400 font-mono-code text-base" x-text="openFaq === 1 ? '−' : '+'">−</span>
                </button>
                <div x-show="openFaq === 1" class="mt-3 text-xs text-slate-600 leading-relaxed">
                    Setelah pembayaran melalui QRIS atau Virtual Account terkonfirmasi, tim teknis kami akan segera melakukan setup server VPS Anda. Proses provisioning membutuhkan waktu 1–2 jam kerja. Jika memesan via toko resmi Shopee, admin akan memproses dan mengirimkan kredensial akses melalui pesan Shopee.
                </div>
            </div>

            <!-- FAQ 2 -->
            <div class="p-5">
                <button @click="openFaq = (openFaq === 2 ? null : 2)" class="w-full flex justify-between items-center text-left text-sm font-semibold text-slate-900">
                    <span>Apakah saya mendapatkan akses root penuh?</span>
                    <span class="text-slate-400 font-mono-code text-base" x-text="openFaq === 2 ? '−' : '+'">+</span>
                </button>
                <div x-show="openFaq === 2" class="mt-3 text-xs text-slate-600 leading-relaxed" style="display: none;">
                    Ya, Anda mendapatkan full root access via SSH (port 22) dengan IP publik dedicated. Anda bebas menginstal software apapun, mengatur firewall UFW, atau menjalankan Docker containers.
                </div>
            </div>

            <!-- FAQ 3 -->
            <div class="p-5">
                <button @click="openFaq = (openFaq === 3 ? null : 3)" class="w-full flex justify-between items-center text-left text-sm font-semibold text-slate-900">
                    <span>Bagaimana alur jika saya membeli lewat Shopee?</span>
                    <span class="text-slate-400 font-mono-code text-base" x-text="openFaq === 3 ? '−' : '+'">+</span>
                </button>
                <div x-show="openFaq === 3" class="mt-3 text-xs text-slate-600 leading-relaxed" style="display: none;">
                    Checkout produk di toko Shopee resmi VexaHost, lalu cantumkan alamat email Anda di catatan pesanan. Admin akan membuatkan akun dashboard dengan format username <code class="font-mono-code">vx_xxxxxxx</code> dan mengirim kredensial login via email &amp; chat Shopee.
                </div>
            </div>

            <!-- FAQ 4 -->
            <div class="p-5">
                <button @click="openFaq = (openFaq === 4 ? null : 4)" class="w-full flex justify-between items-center text-left text-sm font-semibold text-slate-900">
                    <span>Di mana lokasi server VPS VexaHost?</span>
                    <span class="text-slate-400 font-mono-code text-base" x-text="openFaq === 4 ? '−' : '+'">+</span>
                </button>
                <div x-show="openFaq === 4" class="mt-3 text-xs text-slate-600 leading-relaxed" style="display: none;">
                    Tersedia dua opsi lokasi datacenter: Jakarta (Tencent Cloud dan Lintasarta Cloudeka) untuk pengguna di Indonesia, dan Singapore (Tencent Cloud) untuk rute internasional. Lokasi dipilih saat pemesanan dan tercantum di dashboard.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Bottom CTA (Showcase Style Inspired by Hero) -->
<section class="relative overflow-hidden py-16 sm:py-20 bg-gradient-to-br from-[#F5F8FD] via-[#EBF2FA] to-[#DFEAF7] dark:from-[#09090b] dark:via-zinc-950 dark:to-[#09090b] border-b border-slate-200/70 dark:border-zinc-800">

    <!-- Ambient 3D Floating Orbs -->
    <div class="pointer-events-none absolute -top-12 -left-12 w-64 h-64 rounded-full bg-gradient-to-br from-[#CADFF8] via-[#8BB5E8] to-[#4A6FA5] opacity-35 dark:opacity-10 filter blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-16 -right-12 w-72 h-72 rounded-full bg-gradient-to-tl from-[#4A6FA5] to-[#C0D9F7] opacity-30 dark:opacity-10 filter blur-2xl"></div>
    <div class="pointer-events-none absolute top-8 right-1/4 w-20 h-0.5 bg-white/70 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>
    <div class="pointer-events-none absolute bottom-8 left-1/4 w-16 h-0.5 bg-white/60 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 mb-3 tracking-tight">Siap Meluncurkan Server Cloud Anda?</h2>
        <p class="text-sm sm:text-base text-slate-600 max-w-lg mx-auto mb-8 leading-relaxed">
            Dapatkan performa server cloud andal dengan harga bersahabat. Tanpa setup fee, tanpa kontrak mengikat. Didukung penuh oleh tim VexaHost Cloud.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5">
            <a href="{{ route('checkout') }}" class="w-full sm:w-auto px-8 py-3.5 bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-all hover:scale-[1.02] active:scale-95 cursor-pointer">
                Pesan VPS Sekarang
            </a>
            <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-3.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 hover:border-slate-400 text-sm font-semibold rounded-lg shadow-2xs transition-all hover:scale-[1.02] active:scale-95 cursor-pointer">
                Masuk ke Dashboard
            </a>
        </div>
    </div>
</section>

<!-- KONTAK & DUKUNGAN SECTION (Paling Bawah Atas Footer) -->
<section id="kontak" class="py-20 bg-slate-50 border-b border-slate-200" x-data="{
    submitted: false,
    name: '',
    email: '',
    category: 'sales',
    message: '',
    submitForm() {
        if (!this.name || !this.email || !this.message) {
            showAlert('Mohon lengkapi semua isian formulir.');
            return;
        }
        this.submitted = true;
    }
}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Kontak &amp; Dukungan Pelanggan</h2>
            <p class="text-sm text-slate-600 mt-2">
                Punya pertanyaan teknis, konsultasi paket VPS, aktivasi pesanan Shopee, atau butuh bantuan deployment? Tim kami siap membantu Anda.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
            <!-- Left: Contact Form -->
            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
                <template x-if="!submitted">
                    <form @submit.prevent="submitForm()" class="space-y-4 text-xs sm:text-sm">
                        <div class="mb-2">
                            <h3 class="text-lg font-bold text-slate-900">Kirim Pesan ke Tim Dukungan</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Respon cepat dalam waktu 1–2 jam pada jam kerja operasional.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap *</label>
                            <input type="text"
                                   x-model="name"
                                   required
                                   placeholder="Contoh: Ryan Pratama"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all text-xs sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email Aktif *</label>
                            <input type="email"
                                   x-model="email"
                                   required
                                   placeholder="nama@email.com"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all text-xs sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kategori Pertanyaan</label>
                            <select x-model="category"
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all text-xs sm:text-sm">
                                <option value="sales">Tanya Paket &amp; Rekomendasi Spesifikasi</option>
                                <option value="technical">Bantuan Teknis / SSH &amp; Panel</option>
                                <option value="shopee">Konfirmasi &amp; Aktivasi Pesanan Shopee</option>
                                <option value="billing">Faktur Pembayaran &amp; Perpanjangan</option>
                                <option value="custom">Kebutuhan Custom Server / Enterprise</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pesan / Pertanyaan *</label>
                            <textarea x-model="message"
                                      rows="4"
                                      required
                                      placeholder="Tuliskan pertanyaan, kendala, atau spesifikasi yang Anda butuhkan secara jelas..."
                                      class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all text-xs sm:text-sm"></textarea>
                        </div>

                        <button type="submit"
                                class="w-full py-3.5 px-6 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-xs sm:text-sm font-bold transition-all shadow-md hover:shadow-lg hover:scale-[1.01] active:scale-95 cursor-pointer">
                            Kirim Pesan Sekarang &rarr;
                        </button>
                    </form>
                </template>

                <template x-if="submitted">
                    <div class="text-center py-10 space-y-4">
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-[#4A6FA5] mx-auto flex items-center justify-center font-bold text-xl">
                            ✓
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">Pesan Anda Berhasil Terkirim!</h3>
                        <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
                            Terima kasih telah menghubungi kami. Tim support VexaHost akan segera membalas pertanyaan Anda ke alamat email <span class="font-bold text-slate-800" x-text="email"></span>.
                        </p>
                        <div class="pt-4">
                            <button @click="submitted = false; name=''; email=''; message=''" class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                                Kirim Pesan Lain
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Right: Direct Channels -->
            <div class="lg:col-span-5 space-y-6">
                <!-- WhatsApp Official Box -->
                <div class="bg-[#128C7E]/10 border border-[#128C7E]/30 rounded-2xl p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-[#25D366] text-white flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Chat WhatsApp Customer Service</h3>
                            <span class="text-xs text-emerald-600 font-semibold">&bull; Respon Cepat Online</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed mb-3">
                        Konsultasikan kebutuhan server Anda secara langsung dengan customer service berbahasa Indonesia.
                    </p>
                    <div class="mb-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/80 border border-emerald-200 text-xs text-slate-800 font-mono-code font-bold">
                        <span>CS: {{ \App\Support\NomorWhatsApp::tampil() }}</span>
                    </div>
                    <a href="https://wa.me/{{ \App\Support\NomorWhatsApp::internasional() }}?text=Halo%20VexaHost,%20saya%20ingin%20konsultasi%20seputar%20server%20VPS"
                       target="_blank"
                       rel="noopener"
                       class="inline-flex items-center justify-center w-full py-2.5 px-4 rounded-lg bg-[#25D366] hover:bg-[#20bd5a] text-white text-xs font-bold transition-colors shadow-xs">
                        Buka WhatsApp CS &rarr;
                    </a>
                </div>

                <!-- Info Box -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4 shadow-xs text-xs">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Email Resmi</span>
                        <p class="text-sm font-bold text-slate-900 font-mono-code mt-0.5">{{ \App\Support\MailIdentity::contactEmail() }}</p>
                        <p class="text-slate-500 mt-0.5">Untuk pertanyaan umum, pengajuan tiket &amp; kerjasama</p>
                    </div>

                    <div class="pt-3 border-t border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Jam Layanan Operasional</span>
                        <p class="text-sm font-bold text-slate-900 mt-0.5">{{ app(\App\Services\SettingsService::class)->get('support_hours', 'Senin–Jumat, 09.00–17.00 WIB') }}</p>
                        <p class="text-slate-500 mt-0.5">Tiket dan pesan di luar jam ini dibalas pada jam kerja berikutnya.</p>
                    </div>

                    <div class="pt-3 border-t border-slate-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Datacenter</span>
                        <p class="text-slate-700 mt-1 leading-relaxed">
                            &bull; <strong>Jakarta:</strong> Tencent Cloud &amp; Lintasarta Cloudeka<br>
                            &bull; <strong>Singapore:</strong> Tencent Cloud
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-slate-500">
                        Dioperasikan dan dikelola secara profesional oleh <span class="text-slate-700 font-normal">VexaHost Cloud Indonesia</span>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
