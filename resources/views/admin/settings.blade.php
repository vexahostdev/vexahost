@extends('layouts.admin', ['title' => 'Pengaturan Sistem', 'headerTitle' => 'Pengaturan Sistem & Branding', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ $tab }}' }">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
        $tabBase = 'px-4 py-2 rounded-lg text-xs font-bold transition-colors whitespace-nowrap';
        $globalOn = $settings->bool('maintenance_global_enabled', false);
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

    @if($globalOn)
        <div class="bg-white p-5 rounded-lg border border-amber-200">
            <div class="flex items-start gap-3">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                    Aktif
                </span>
                <div>
                    <div class="text-sm font-bold text-slate-900">Maintenance Global Sedang Menyala</div>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Seluruh situs tertutup untuk pengunjung. Admin dan rute webhook pembayaran tetap dapat diakses.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Navigasi Tab -->
    <div class="bg-white p-2 rounded-lg border border-slate-200 flex items-center gap-1 overflow-x-auto">
        <button type="button" @click="tab = 'brand'"
                :class="tab === 'brand' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Identitas Merek</button>
        <button type="button" @click="tab = 'company'"
                :class="tab === 'company' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Profil Perusahaan</button>
        <button type="button" @click="tab = 'support'"
                :class="tab === 'support' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Kontak & Dukungan</button>
        <button type="button" @click="tab = 'notification'"
                :class="tab === 'notification' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Notifikasi & Tenggang</button>
        <button type="button" @click="tab = 'mail'"
                :class="tab === 'mail' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Email & Pengirim</button>
        <button type="button" @click="tab = 'maintenance'"
                :class="tab === 'maintenance' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                class="{{ $tabBase }}">Maintenance</button>
    </div>

    <!-- ============ TAB: IDENTITAS MEREK ============ -->
    <div x-show="tab === 'brand'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.brand') }}" method="POST" enctype="multipart/form-data"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Identitas Merek</h3>
                <p class="text-xs text-slate-500">Nama, logo, dan warna yang dipakai di seluruh halaman serta surel.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Merek</label>
                        <input type="text" name="brand_name" required maxlength="60"
                               value="{{ old('brand_name', $settings->get('brand_name', 'VexaHost')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Warna Aksen</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="brand_accent_color"
                                   value="{{ old('brand_accent_color', $settings->get('brand_accent_color', '#4A6FA5')) }}"
                                   class="h-10 w-14 rounded-lg border border-slate-300 cursor-pointer">
                            <span class="font-mono-code text-xs text-slate-500">
                                {{ $settings->get('brand_accent_color', '#4A6FA5') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Tagline</label>
                    <input type="text" name="brand_tagline" maxlength="160"
                           value="{{ old('brand_tagline', $settings->get('brand_tagline', '')) }}"
                           class="{{ $inputClass }}">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Logo (maks. 1 MB)</label>
                        @if($settings->get('brand_logo_path'))
                            <img src="{{ asset('storage/' . $settings->get('brand_logo_path')) }}"
                                 alt="Logo" class="h-10 w-auto object-contain mb-2">
                        @endif
                        <input type="file" name="brand_logo" accept="image/*"
                               class="w-full text-xs text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-slate-100 file:text-xs file:font-bold file:text-slate-700">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Favicon (maks. 256 KB)</label>
                        @if($settings->get('brand_favicon_path'))
                            <img src="{{ asset('storage/' . $settings->get('brand_favicon_path')) }}"
                                 alt="Favicon" class="h-8 w-8 object-contain mb-2">
                        @endif
                        <input type="file" name="brand_favicon" accept="image/*"
                               class="w-full text-xs text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-slate-100 file:text-xs file:font-bold file:text-slate-700">
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Simpan Identitas Merek
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: PROFIL PERUSAHAAN ============ -->
    <div x-show="tab === 'company'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.company') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Profil Perusahaan</h3>
                <p class="text-xs text-slate-500">Dipakai ulang oleh faktur PDF, faktur cetak, dan surel sistem.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Badan Hukum</label>
                        <input type="text" name="company_legal_name" required maxlength="120"
                               value="{{ old('company_legal_name', $settings->get('company_legal_name', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">NPWP</label>
                        <input type="text" name="company_npwp" maxlength="30"
                               value="{{ old('company_npwp', $settings->get('company_npwp', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Alamat</label>
                    <textarea name="company_address" rows="2" maxlength="500"
                              class="{{ $inputClass }}">{{ old('company_address', $settings->get('company_address', '')) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Kota</label>
                        <input type="text" name="company_city" maxlength="80"
                               value="{{ old('company_city', $settings->get('company_city', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Kode Pos</label>
                        <input type="text" name="company_postal_code" maxlength="10"
                               value="{{ old('company_postal_code', $settings->get('company_postal_code', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Telepon</label>
                        <input type="text" name="company_phone" maxlength="30"
                               value="{{ old('company_phone', $settings->get('company_phone', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Perusahaan</label>
                        <input type="email" name="company_email" maxlength="120"
                               value="{{ old('company_email', $settings->get('company_email', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Situs Web</label>
                        <input type="url" name="company_website" maxlength="120"
                               value="{{ old('company_website', $settings->get('company_website', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-3">Penanda Tangan Faktur</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">Nama</label>
                            <input type="text" name="invoice_signature_name" maxlength="80"
                                   value="{{ old('invoice_signature_name', $settings->get('invoice_signature_name', '')) }}"
                                   class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Jabatan</label>
                            <input type="text" name="invoice_signature_title" maxlength="80"
                                   value="{{ old('invoice_signature_title', $settings->get('invoice_signature_title', '')) }}"
                                   class="{{ $inputClass }}">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="{{ $labelClass }}">Catatan Kaki Faktur</label>
                        <textarea name="invoice_footer_note" rows="2" maxlength="300"
                                  class="{{ $inputClass }}">{{ old('invoice_footer_note', $settings->get('invoice_footer_note', '')) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Simpan Profil Perusahaan
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: KONTAK & DUKUNGAN ============ -->
    <div x-show="tab === 'support'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.support') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Kontak & Dukungan</h3>
                <p class="text-xs text-slate-500">Tampil di halaman publik, surel, dan halaman maintenance.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Dukungan</label>
                        <input type="email" name="support_email" required maxlength="120"
                               value="{{ old('support_email', $settings->get('support_email', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Nomor WhatsApp</label>
                        <input type="text" name="support_whatsapp" maxlength="30" placeholder="+62..."
                               value="{{ old('support_whatsapp', $settings->get('support_whatsapp', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Jam Operasional</label>
                    <input type="text" name="support_hours" maxlength="120"
                           value="{{ old('support_hours', $settings->get('support_hours', '')) }}"
                           class="{{ $inputClass }}">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label class="{{ $labelClass }}">Instagram</label>
                        <input type="url" name="social_instagram" maxlength="160"
                               value="{{ old('social_instagram', $settings->get('social_instagram', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">X / Twitter</label>
                        <input type="url" name="social_twitter" maxlength="160"
                               value="{{ old('social_twitter', $settings->get('social_twitter', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">LinkedIn</label>
                        <input type="url" name="social_linkedin" maxlength="160"
                               value="{{ old('social_linkedin', $settings->get('social_linkedin', '')) }}"
                               class="{{ $inputClass }}">
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Simpan Kontak Dukungan
                </button>
            </div>
        </form>
    </div>

    <!-- ============ TAB: NOTIFIKASI & TENGGANG ============ -->
    <div x-show="tab === 'notification'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.notification') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Notifikasi & Masa Tenggang</h3>
                <p class="text-xs text-slate-500">Penerima digest harian dan batas tenggang per jenis produk.</p>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Surel Penerima Digest Admin</label>
                        <input type="email" name="admin_notification_email" required maxlength="120"
                               value="{{ old('admin_notification_email', $settings->get('admin_notification_email', '')) }}"
                               class="{{ $inputClass }}">
                        <p class="text-[11px] text-slate-500 mt-1">Satu surel berisi seluruh layanan jatuh tempo, bukan satu surel per layanan.</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Ambang Waktu Tanggap Pemenuhan (menit)</label>
                        <input type="number" name="fulfillment_sla_minutes" required min="5" max="1440"
                               value="{{ old('fulfillment_sla_minutes', $settings->int('fulfillment_sla_minutes', 120)) }}"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Nomor WhatsApp Admin (Order Lunas &amp; Pengingat Setup)</label>
                    <input type="text" name="admin_notification_whatsapp" maxlength="200" placeholder="6281234567890, 6289876543210"
                           value="{{ old('admin_notification_whatsapp', $settings->get('admin_notification_whatsapp', '')) }}"
                           class="{{ $inputClass }} font-mono-code">
                    @error('admin_notification_whatsapp') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-slate-500 mt-1">
                        Boleh lebih dari satu, pisahkan dengan koma. Setiap order lunas langsung dikabarkan ke WA &amp; surel di atas,
                        lalu diingatkan 30 menit, 1 jam, dan tiap 2 jam sampai layanan diserahkan.
                        Gunakan nomor yang <strong>berbeda</strong> dari nomor pengirim WA Gateway.
                    </p>
                    @if (trim((string) $settings->get('admin_notification_whatsapp', '')) === '')
                        <p class="text-[11px] text-amber-700 mt-1 font-semibold">⚠ Belum diisi — admin tidak akan menerima notifikasi WhatsApp.</p>
                    @endif
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="digest_enabled" value="1"
                           {{ old('digest_enabled', $settings->bool('digest_enabled', true)) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                    <span class="text-sm text-slate-700">Aktifkan digest harian ke admin</span>
                </label>

                <div class="pt-4 border-t border-slate-100">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Masa Tenggang per Jenis Produk</span>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 mb-4">
                        <p class="text-[11px] text-amber-900 leading-relaxed">
                            Tenggang VexaHost wajib <strong>lebih pendek</strong> daripada batas Supplier.
                            Supplier menghapus VPS setelah <strong>7 hari</strong> suspensi, sedangkan database
                            dan aplikasi setelah <strong>30 hari</strong>. Selisihnya adalah ruang Anda untuk bertindak.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $labelClass }}">Tenggang VPS (hari)</label>
                            <input type="number" name="renewal_grace_days_vps" required min="1" max="6"
                                   value="{{ old('renewal_grace_days_vps', $settings->int('renewal_grace_days_vps', 5)) }}"
                                   class="{{ $inputClass }} font-mono-code">
                            <p class="text-[11px] text-slate-500 mt-1">Batas Supplier: 7 hari</p>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Tenggang Database (hari)</label>
                            <input type="number" name="renewal_grace_days_database" required min="1" max="29"
                                   value="{{ old('renewal_grace_days_database', $settings->int('renewal_grace_days_database', 25)) }}"
                                   class="{{ $inputClass }} font-mono-code">
                            <p class="text-[11px] text-slate-500 mt-1">Batas Supplier: 30 hari</p>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Tenggang Aplikasi & AI (hari)</label>
                            <input type="number" name="renewal_grace_days_app" required min="1" max="29"
                                   value="{{ old('renewal_grace_days_app', $settings->int('renewal_grace_days_app', 25)) }}"
                                   class="{{ $inputClass }} font-mono-code">
                            <p class="text-[11px] text-slate-500 mt-1">Batas Supplier: 30 hari</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Simpan Pengaturan Notifikasi
                </button>
            </div>
        </form>

        <form action="{{ route('admin.settings.notification.test') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            @csrf
            <div>
                <h3 class="font-bold text-slate-900 text-sm">Tes Notifikasi Admin</h3>
                <p class="text-xs text-slate-500">Kirim pesan uji ke surel &amp; nomor WA admin yang sudah disimpan.</p>
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-900 text-xs font-bold transition-colors">
                Kirim Tes
            </button>
        </form>
    </div>

    <!-- ============ TAB: EMAIL & PENGIRIM ============ -->
    <div x-show="tab === 'mail'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.mail') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Identitas Email &amp; Pengirim</h3>
                <p class="text-xs text-slate-500">
                    Konfigurasi alamat pengirim (noreply &amp; support), Reply-To, dan penerima notifikasi admin.
                </p>
            </div>

            <div class="p-5 space-y-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-[11px] text-blue-900 leading-relaxed">
                        Domain terverifikasi pengirim: <strong>{{ implode(', ', (array) config('mail.allowed_sender_domains', ['vexahostcloud.my.id'])) }}</strong>.<br>
                        Field yang dibiarkan kosong akan otomatis menggunakan nilai default dari konfigurasi lingkungan (<code>.env</code>).
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Pengirim Noreply</label>
                        <input type="text" name="mail_noreply_name" maxlength="120"
                               placeholder="{{ config('mail.from.name', 'VexaHost Cloud') }}"
                               value="{{ old('mail_noreply_name', $settings->get('mail_noreply_name', '')) }}"
                               class="{{ $inputClass }}">
                        <p class="text-[11px] text-slate-500 mt-1">Dipakai untuk email otomatis (tagihan, peringatan, sistem).</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Alamat Pengirim Noreply</label>
                        <input type="email" name="mail_noreply_address" maxlength="120"
                               placeholder="{{ config('mail.from.address', 'noreply@vexahostcloud.my.id') }}"
                               value="{{ old('mail_noreply_address', $settings->get('mail_noreply_address', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                        <p class="text-[11px] text-slate-500 mt-1">Wajib menggunakan domain terverifikasi.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Nama Pengirim Support</label>
                        <input type="text" name="mail_support_name" maxlength="120"
                               placeholder="{{ config('mail.support.name', 'VexaHost Support') }}"
                               value="{{ old('mail_support_name', $settings->get('mail_support_name', '')) }}"
                               class="{{ $inputClass }}">
                        <p class="text-[11px] text-slate-500 mt-1">Dipakai untuk tiket bantuan dan penolakan bayar.</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Alamat Pengirim Support</label>
                        <input type="email" name="mail_support_address" maxlength="120"
                               placeholder="{{ config('mail.support.address', 'support@vexahostcloud.my.id') }}"
                               value="{{ old('mail_support_address', $settings->get('mail_support_address', '')) }}"
                               class="{{ $inputClass }} font-mono-code">
                        <p class="text-[11px] text-slate-500 mt-1">Wajib menggunakan domain terverifikasi.</p>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Alamat Reply-To</label>
                    <input type="email" name="mail_reply_to_address" maxlength="120"
                           placeholder="{{ config('mail.reply_to.address', 'support@vexahostcloud.my.id') }}"
                           value="{{ old('mail_reply_to_address', $settings->get('mail_reply_to_address', '')) }}"
                           class="{{ $inputClass }} font-mono-code">
                    <p class="text-[11px] text-slate-500 mt-1">Semua email (noreply maupun support) akan diarahkan ke alamat ini saat penerima membalas.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Daftar Email Penerima Admin</label>
                    <textarea name="mail_admin_addresses" rows="3"
                              placeholder="{{ implode(', ', (array) config('mail.admin_addresses', ['support@vexahostcloud.my.id', 'vexahostcloudtech@gmail.com'])) }}"
                              class="{{ $inputClass }} font-mono-code">{{ old('mail_admin_addresses', $settings->get('mail_admin_addresses', '')) }}</textarea>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Bebas domain. Pisahkan dengan koma atau baris baru. Alert order lunas, provisioning, dan SLA dikirim ke seluruh alamat ini.
                    </p>
                </div>
            </div>

            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-black hover:bg-slate-800 text-white text-xs font-bold transition-colors">
                    Simpan Pengaturan Email
                </button>
            </div>
        </form>

        <!-- Formulir Tes Kirim Email -->
        <form action="{{ route('admin.settings.mail.test') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 p-5 flex flex-col sm:flex-row items-stretch sm:items-end justify-between gap-4">
            @csrf
            <div class="space-y-1 flex-1">
                <h3 class="font-bold text-slate-900 text-sm">Kirim Email Tes Identitas</h3>
                <p class="text-xs text-slate-500">Uji pengiriman sinkron melalui Brevo SMTP untuk memastikan identitas From dan Reply-To berjalan semestinya.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tipe Identitas</label>
                        <select name="type" class="{{ $inputClass }}">
                            <option value="noreply">Noreply ({{ \App\Support\MailIdentity::noreply()['address'] }})</option>
                            <option value="support">Support ({{ \App\Support\MailIdentity::support()['address'] }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Email Tujuan</label>
                        <input type="email" name="to" required placeholder="admin@domain.com" class="{{ $inputClass }} font-mono-code">
                    </div>
                </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-900 text-xs font-bold transition-colors shrink-0">
                Kirim Email Tes
            </button>
        </form>
    </div>

    <!-- ============ TAB: MAINTENANCE ============ -->
    <div x-show="tab === 'maintenance'" x-cloak class="space-y-6">
        <form action="{{ route('admin.settings.maintenance') }}" method="POST"
              class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Maintenance Mode</h3>
                <p class="text-xs text-slate-500">
                    Rute webhook pembayaran selalu dikecualikan agar notifikasi Lynk tidak pernah tertolak.
                </p>
            </div>

            <div class="p-5 space-y-5">
                <!-- Tingkat 1 -->
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-bold text-slate-900">Tingkat 1 — Maintenance Global</div>
                            <p class="text-xs text-slate-500 mt-0.5">Seluruh situs tertutup kecuali admin dan rute yang dikecualikan.</p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer shrink-0">
                            <input type="checkbox" name="maintenance_global_enabled" value="1"
                                   {{ $globalOn ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                            <span class="text-xs font-bold text-slate-700">Aktifkan</span>
                        </label>
                    </div>
                </div>

                <!-- Tingkat 2 -->
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="text-sm font-bold text-slate-900 mb-1">Tingkat 2 — Per Cakupan</div>
                    <p class="text-xs text-slate-500 mb-3">Tutup sebagian sistem tanpa menutup seluruhnya.</p>

                    <div class="space-y-2">
                        @foreach($scopeStates as $scope => $state)
                            <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="scope_{{ $scope }}" value="1"
                                           {{ $state['manual'] ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                    <span class="text-sm text-slate-700">{{ $state['label'] }}</span>
                                </div>
                                @if($state['window'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-100 text-sky-800 border border-sky-200">
                                        Jendela Aktif
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Pesan Maintenance</label>
                    <textarea name="maintenance_message" rows="2" maxlength="300"
                              class="{{ $inputClass }}">{{ old('maintenance_message', $settings->get('maintenance_message', '')) }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Daftar IP yang Diizinkan</label>
                    <input type="text" name="maintenance_allowed_ips" maxlength="300"
                           placeholder="103.10.1.5, 103.10.1.6"
                           value="{{ old('maintenance_allowed_ips', $settings->get('maintenance_allowed_ips', '')) }}"
                           class="{{ $inputClass }} font-mono-code">
                    <p class="text-[11px] text-slate-500 mt-1">Pisahkan dengan koma. IP ini selalu dapat mengakses situs saat maintenance.</p>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Simpan Pengaturan Maintenance
                </button>
            </div>
        </form>

        <!-- Token jalur pintas -->
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Token Jalur Pintas</h3>
                <p class="text-xs text-slate-500">Untuk menguji situs dalam mode maintenance tanpa membukanya ke publik.</p>
            </div>
            <div class="p-5">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 mb-3">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Tautan Jalur Pintas</span>
                    <code class="font-mono-code text-xs text-slate-700 break-all">
                        {{ url('/') }}?bypass={{ $settings->get('maintenance_bypass_token', '(belum dibuat)') }}
                    </code>
                </div>
                <form action="{{ route('admin.settings.bypass-token') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Buat Ulang Token
                    </button>
                </form>
            </div>
        </div>

        <!-- Status komponen -->
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Status Komponen</h3>
                <p class="text-xs text-slate-500">Status ini tampil di halaman status publik.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Komponen</th>
                            <th class="px-5 py-3.5">Status Saat Ini</th>
                            <th class="px-5 py-3.5">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($components as $component)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 text-sm block">{{ $component->name }}</span>
                                    <span class="text-[11px] text-slate-500">{{ $component->description }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold border {{ $component->status_classes }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $component->status_dot_class }}"></span>
                                        {{ $component->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <form action="{{ route('admin.settings.components.update', $component->id) }}"
                                          method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        @csrf
                                        <select name="status"
                                                class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                            @foreach($componentStatuses as $status)
                                                <option value="{{ $status }}" {{ $component->status === $status ? 'selected' : '' }}>
                                                    {{ ucfirst($status) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="status_note" maxlength="160" placeholder="Catatan singkat"
                                               value="{{ $component->status_note }}"
                                               class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shrink-0">
                                            Simpan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
