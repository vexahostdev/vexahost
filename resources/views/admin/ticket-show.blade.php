@extends('layouts.admin', ['title' => 'Tanggapi Tiket #' . $ticket->id, 'headerTitle' => 'Respon Tiket Bantuan', 'backUrl' => route('admin.tickets'), 'backLabel' => 'Kembali ke Daftar Tiket'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <!-- Ticket Summary Card (No line under title) -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2">
            <div>
                <span class="font-mono-code text-xs text-slate-400 font-semibold">#TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}</span>
                <h1 class="text-base font-bold text-slate-900 mt-0.5">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-2.5 py-1 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                    {{ str_replace('_', ' ', $ticket->status) }}
                </span>
                <span class="text-xs text-slate-400">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="text-slate-500">SLA: <span class="font-semibold {{ $ticket->sla_due_at && $ticket->sla_due_at->isPast() && !$ticket->first_response_at ? 'text-red-600' : 'text-slate-900' }}">{{ $ticket->first_response_at ? 'Sudah direspons ' . $ticket->first_response_at->diffForHumans() : ($ticket->sla_due_at?->diffForHumans() ?? '-') }}</span></div>
            <form method="POST" action="{{ route('admin.tickets.assign', $ticket->id) }}" class="flex items-center gap-2">@csrf<select name="assigned_to" onchange="this.form.submit()" class="px-2 py-1.5 rounded border border-slate-300 bg-white"><option value="">Belum ditugaskan</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" {{ $ticket->assigned_to === $admin->id ? 'selected' : '' }}>{{ $admin->full_name }}</option>@endforeach</select></form>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-3 text-xs text-slate-600 border-t border-slate-100">
            <div>
                <span class="text-slate-400 block text-[11px]">Pelapor:</span>
                <span class="font-semibold text-slate-900">{{ $ticket->customer->full_name }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Email:</span>
                <span class="font-mono-code text-slate-700">{{ $ticket->customer->email }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Terkait VPS:</span>
                <span class="font-medium text-slate-900">{{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[11px]">Prioritas:</span>
                <span class="font-semibold uppercase text-slate-900">{{ $ticket->priority }}</span>
            </div>
        </div>
    </div>

    @if($ticket->isServiceRequest())
        @php
            $requestVps = $ticket->vpsInstance;
            $requestData = $ticket->request_data ?? [];
            $requestIp = $requestVps?->public_ip ?? '-';
        @endphp
        <!-- Panel Permintaan Layanan Server -->
        <div class="bg-white rounded-lg border {{ $ticket->isOpen() ? 'border-blue-300' : 'border-slate-200' }} p-5 space-y-4 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $ticket->type === 'reinstall' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800' }}">
                        Permintaan Server
                    </span>
                    <h3 class="font-bold text-slate-900 text-sm mt-1">{{ $ticket->type_label }}</h3>
                </div>
                @if($ticket->isOpen())
                    <span class="text-slate-500">
                        Diajukan {{ $ticket->created_at->locale('id')->diffForHumans() }} &bull; janji ke pelanggan maks. {{ \App\Models\SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS }} jam kerja
                    </span>
                @else
                    <span class="font-semibold text-emerald-700">Selesai {{ $ticket->resolved_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 rounded-lg bg-slate-50 border border-slate-200">
                <div>
                    <span class="text-slate-400 block text-[11px]">Server:</span>
                    <span class="font-semibold text-slate-900">{{ $requestVps?->hostname ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px]">IP Publik:</span>
                    <span class="font-mono-code font-semibold text-slate-900">{{ $requestIp }}</span>
                </div>
                @if($ticket->type === 'reinstall')
                    <div>
                        <span class="text-slate-400 block text-[11px]">OS diminta:</span>
                        <span class="font-semibold text-slate-900">{{ \App\Models\Order::osLabels()[$requestData['os'] ?? ''] ?? ($requestData['os'] ?? '-') }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Stack diminta:</span>
                        <span class="font-semibold text-slate-900">{{ \App\Models\Order::stackLabels()[$requestData['control_panel'] ?? ''] ?? ($requestData['control_panel'] ?? '-') }}</span>
                    </div>
                @else
                    <div>
                        <span class="text-slate-400 block text-[11px]">Status di panel:</span>
                        <span class="font-semibold text-slate-900">{{ $requestVps ? ucfirst($requestVps->status) : '-' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Paket:</span>
                        <span class="font-semibold text-slate-900">{{ $requestVps ? ($requestVps->cpu . ' vCPU / ' . $requestVps->ram . ' GB') : '-' }}</span>
                    </div>
                @endif
            </div>

            @if($ticket->isOpen() && $requestVps)
                @if($ticket->type === 'reinstall')
                    <ol class="list-decimal pl-5 space-y-1 text-slate-700">
                        <li>Buka dashboard supplier, cari VPS dengan IP <strong class="font-mono-code">{{ $requestIp }}</strong>.</li>
                        <li>Jalankan reinstall/rebuild dengan OS yang diminta. Catat password root baru.</li>
                        <li>Jika stack bukan "Tanpa Control Panel", pasang stack yang diminta.</li>
                        <li>Isi form di bawah. Pelanggan otomatis menerima email. Password hanya tampil di dashboard pelanggan, tidak ikut email.</li>
                    </ol>

                    @php
                        $adminOsOptions = $requestVps->reinstallOsOptions();
                        $adminStackOptions = $requestVps->reinstallStackOptions();
                        $defaultOs = old('os', $requestData['os'] ?? $requestVps->os);
                        $defaultStack = old('control_panel', $requestData['control_panel'] ?? $requestVps->control_panel);
                    @endphp
                    <form action="{{ route('admin.tickets.complete-reinstall', $ticket->id) }}" method="POST" class="space-y-3 pt-3 border-t border-slate-100"
                          data-confirm="Tandai reinstall selesai? Password root baru akan tersimpan dan pelanggan menerima email.">
                        @csrf
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">OS terpasang</label>
                                <select name="os" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white focus:outline-none">
                                    @foreach($adminOsOptions as $osKey => $osName)
                                        <option value="{{ $osKey }}" @selected((string) $defaultOs === (string) $osKey)>{{ $osName }}</option>
                                    @endforeach
                                </select>
                                @error('os') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Stack terpasang</label>
                                @if($requestVps->hasFixedStack())
                                    <p class="px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-700">{{ $requestVps->control_panel_label }} (bawaan paket)</p>
                                @else
                                    <select name="control_panel" required class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white focus:outline-none">
                                        @foreach($adminStackOptions as $stackKey => $stackName)
                                            <option value="{{ $stackKey }}" @selected((string) $defaultStack === (string) $stackKey)>{{ $stackName }}</option>
                                        @endforeach
                                    </select>
                                    @error('control_panel') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Username SSH</label>
                            <input type="text" name="ssh_username" value="{{ old('ssh_username', $ticket->vpsInstance?->ssh_user ?? 'root') }}" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-mono-code focus:outline-none">
                            <p class="mt-1 text-slate-400">Biarkan bila username tidak berubah setelah reinstall.</p>
                            @error('ssh_username') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Password SSH baru</label>
                            <input type="text" name="root_password" required minlength="8" maxlength="128" autocomplete="off" spellcheck="false"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-mono-code focus:outline-none"
                                   placeholder="Password root dari dashboard supplier">
                            <p class="mt-1 text-slate-400">Disimpan terenkripsi. Pelanggan membukanya lewat ikon mata di tab Akses.</p>
                            @error('root_password') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Pesan ke pelanggan (opsional)</label>
                            <textarea name="message" rows="3" maxlength="2000" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none"
                                      placeholder="Kosongkan untuk memakai pesan standar: reinstall selesai, password baru ada di tab Akses.">{{ old('message') }}</textarea>
                            @error('message') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors">
                                Tandai Reinstall Selesai
                            </button>
                        </div>
                    </form>
                @else
                    <ol class="list-decimal pl-5 space-y-1 text-slate-700">
                        <li>Buka dashboard supplier, cek status VPS dengan IP <strong class="font-mono-code">{{ $requestIp }}</strong>.</li>
                        <li>Jika server hang atau mati, jalankan reboot (atau force reboot) dari dashboard supplier.</li>
                        <li>Tunggu 1-2 menit, lalu pastikan port SSH ({{ $requestVps->ssh_port ?: 22 }}) bisa diakses kembali.</li>
                        <li>Balas tiket di bawah dengan status "Resolved". Pelanggan otomatis menerima email.</li>
                    </ol>
                @endif
            @endif
        </div>
    @endif

    <!-- Messages Timeline -->
    <div class="space-y-3">
        @foreach($ticket->messages as $msg)
            <div class="rounded-lg p-4 border {{ $msg->is_admin_reply ? 'bg-slate-100 border-slate-300' : 'bg-white border-slate-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs {{ $msg->is_admin_reply ? 'bg-black text-white' : 'bg-slate-300 text-slate-900' }}">
                            {{ strtoupper(substr($msg->user->full_name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-xs text-slate-900">{{ $msg->user->full_name }}</span>
                        @if($msg->is_admin_reply)
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black text-white">
                                Admin (Ryan)
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500">
                        {{ $msg->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                    </span>
                </div>
                <div class="text-xs text-slate-800 leading-relaxed whitespace-pre-line pl-8">
                    {{ $msg->message }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reply Box (No lines under title, black button) -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <h3 class="font-semibold text-slate-900 text-sm mb-3">Balas Sebagai Admin VexaHost</h3>
        <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST" class="space-y-3 text-xs">
            @csrf
            <div>
                <textarea name="message" rows="4" required placeholder="Tuliskan petunjuk atau balasan teknis..."
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none"></textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-slate-600"><input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300"> Catatan internal (tidak terlihat pelanggan)</label>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-2">
                    <label class="font-medium text-slate-700">Status Tiket:</label>
                    <select name="status" class="px-2.5 py-1.5 rounded-lg border border-slate-300 font-medium focus:outline-none bg-white">
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved (Selesai)</option>
                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed (Tutup)</option>
                        <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                    </select>
                </div>

                <button type="submit" class="px-5 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors">
                    Kirim Balasan Admin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
