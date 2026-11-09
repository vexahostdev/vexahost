@extends('layouts.admin', ['title' => 'Kasus Pelanggaran', 'headerTitle' => 'Kasus Pelanggaran Ketentuan Penggunaan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    notifyModalOpen: false,
    resolveModalOpen: false,
    target: { id: '', type_label: '', customer: '' },
    openNotify(c) { this.target = c; this.notifyModalOpen = true; },
    openResolve(c) { this.target = c; this.resolveModalOpen = true; }
}">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

    @if($errors->any())
        <div class="bg-white p-5 rounded-lg border border-red-200">
            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Peringatan risiko akun -->
    <div class="bg-white p-5 rounded-lg border border-amber-200">
        <div class="flex items-start gap-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                Penting
            </span>
            <div>
                <div class="text-sm font-bold text-slate-900">Pelanggaran Satu Pelanggan Berdampak ke Semua</div>
                <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                    Acceptable Use Policy Supplier menyasar <strong>akun</strong>, bukan hanya layanan.
                    Satu pelanggan yang menjalankan VPN, proxy, scraping, torrent, atau penambangan kripto
                    dapat menyebabkan seluruh akun VexaHost disuspend dan saldo hangus.
                </p>
            </div>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Belum Selesai</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $openCount > 0 ? 'text-amber-600' : 'text-slate-900' }} font-mono-code">{{ $openCount }}</span>
                <span class="text-xs text-slate-500">Perlu Ditangani</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Tingkat Kritis</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $criticalCount > 0 ? 'text-red-600' : 'text-slate-900' }} font-mono-code">{{ $criticalCount }}</span>
                <span class="text-xs text-slate-500">Segera</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Diterminasi</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $terminatedCount }}</span>
                <span class="text-xs text-slate-500">Layanan Dihentikan</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Total Kasus</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $totalCount }}</span>
                <span class="text-xs text-slate-500">Sepanjang Waktu</span>
            </div>
        </div>
    </div>

    <!-- Tabel Kasus -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Daftar Kasus</h3>
                <p class="text-xs text-slate-500">Catatan ini menjadi bukti saat pelanggan menyanggah di kemudian hari.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('admin.abuse.index') }}" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()"
                            class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                        <option value="">Semua Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ $filterStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="severity" onchange="this.form.submit()"
                            class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                        <option value="">Semua Tingkat</option>
                        @foreach($severities as $severity)
                            <option value="{{ $severity }}" {{ $filterSeverity === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                        @endforeach
                    </select>
                </form>
                <button @click="createModalOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shrink-0">
                    Catat Kasus
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Kasus</th>
                        <th class="px-5 py-3.5">Pelanggan</th>
                        <th class="px-5 py-3.5">Layanan</th>
                        <th class="px-5 py-3.5 text-center">Tingkat</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($cases as $case)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 text-sm block">{{ $case->type_label }}</span>
                                <span class="text-[11px] text-slate-400 font-mono-code">
                                    ID: #AB-{{ str_pad($case->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="text-[11px] text-slate-500 block">
                                    {{ $case->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">
                                <div class="font-semibold text-slate-900">{{ $case->user?->name ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $case->user?->email ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-600">
                                {{ $case->vpsInstance?->hostname ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($case->severity === 'critical')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-800 border border-red-200">{{ $case->severity_label }}</span>
                                @elseif($case->severity === 'high')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">{{ $case->severity_label }}</span>
                                @elseif($case->severity === 'medium')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-100 text-sky-800 border border-sky-200">{{ $case->severity_label }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">{{ $case->severity_label }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if(in_array($case->status, ['resolved'], true))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>{{ $case->status_label }}
                                    </span>
                                @elseif($case->status === 'terminated')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ $case->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>{{ $case->status_label }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1 flex-wrap">
                                    @if(!in_array($case->status, ['resolved', 'terminated'], true))
                                        <button @click='openNotify(@json(["id" => $case->id, "type_label" => $case->type_label, "customer" => $case->user?->email]))'
                                                class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                            Beritahu
                                        </button>
                                        <button @click='openResolve(@json(["id" => $case->id, "type_label" => $case->type_label, "customer" => $case->user?->email]))'
                                                class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                            Tutup
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-400">
                                            {{ $case->resolved_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">
                                Belum ada kasus pelanggaran yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $cases->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Catat Kasus -->
    <div x-show="createModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Catat Kasus Pelanggaran</h3>
                    <p class="text-xs text-slate-500">Sertakan bukti selengkap mungkin untuk keperluan sengketa</p>
                </div>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.abuse.store') }}" method="POST" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="{{ $labelClass }}">Layanan Terkait</label>
                    <select name="vps_instance_id" class="{{ $inputClass }}">
                        <option value="">— Tidak terkait layanan tertentu —</option>
                        @foreach($instances as $instance)
                            <option value="{{ $instance->id }}" data-user="{{ $instance->customer_id }}">
                                {{ $instance->hostname ?? 'Instance #' . $instance->id }}
                                — {{ $instance->customer?->name ?? 'Tanpa pemilik' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Pemilik Layanan</label>
                    <select name="user_id" required class="{{ $inputClass }}">
                        <option value="">— Pilih pelanggan —</option>
                        @foreach($instances->pluck('customer')->filter()->unique('id') as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Jenis Pelanggaran</label>
                        <select name="type" required class="{{ $inputClass }}">
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Tingkat Keparahan</label>
                        <select name="severity" required class="{{ $inputClass }}">
                            @foreach($severities as $severity)
                                <option value="{{ $severity }}" {{ $severity === 'medium' ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Keterangan & Bukti</label>
                    <textarea name="evidence" rows="4" maxlength="2000"
                              placeholder="Contoh: trafik keluar 900 Mbps berkelanjutan ke port 1080, terdeteksi 18 Sep 03:12 WIB"
                              class="{{ $inputClass }}"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="createModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Simpan Kasus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Beritahu Pelanggan -->
    <div x-show="notifyModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="notifyModalOpen = false">

        <div @click.away="notifyModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Kirim Pemberitahuan Pelanggaran</h3>
                <p class="text-xs text-slate-500">
                    Dikirim hanya ke <span class="font-semibold text-slate-800" x-text="target.customer"></span>
                </p>
            </div>

            <form :action="'/admin/abuse/' + target.id + '/notify'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Batas Waktu Tanggapan (hari)</label>
                    <input type="number" name="deadline_days" required min="1" max="30" value="3"
                           class="{{ $inputClass }} font-mono-code">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Untuk VPS, sebaiknya maksimal 2 hari agar masih tersisa waktu sebelum Supplier menghapus data.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="notifyModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Kirim Pemberitahuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tutup Kasus -->
    <div x-show="resolveModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="resolveModalOpen = false">

        <div @click.away="resolveModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Tutup Kasus</h3>
                <p class="text-xs text-slate-500">
                    Kasus <span class="font-semibold text-slate-800" x-text="target.type_label"></span>
                </p>
            </div>

            <form :action="'/admin/abuse/' + target.id + '/resolve'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Hasil Akhir</label>
                    <select name="status" required class="{{ $inputClass }}">
                        <option value="resolved">Selesai — pelanggan memperbaiki</option>
                        <option value="terminated">Diterminasi — layanan dihentikan</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Catatan Penyelesaian</label>
                    <textarea name="resolution" rows="3" required maxlength="1000" class="{{ $inputClass }}"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="resolveModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Tutup Kasus
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
