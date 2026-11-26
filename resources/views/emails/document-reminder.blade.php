<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{{ $headlineLead }} {{ $headlineAccent }} - {{ $universityName }}</title>
    <style>
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f7fb;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            border-collapse: collapse;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
        }
        @media only screen and (max-width: 620px) {
            .email-shell {
                padding: 16px 10px 24px 10px !important;
            }
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .email-hero-pad {
                padding: 24px 20px 22px 20px !important;
            }
            .email-body-pad {
                padding: 24px 20px !important;
            }
            .email-hero-title {
                font-size: 24px !important;
                line-height: 30px !important;
            }
            .email-kv-cell {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .email-kv-val-cell {
                padding-top: 4px !important;
            }
            .email-cta-cell {
                display: block !important;
                width: 100% !important;
                padding-right: 0 !important;
                padding-bottom: 10px !important;
            }
            .email-cta-link {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
                text-align: center !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a;">
    {{-- Preheader ringkas untuk inbox email client --}}
    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">
        {{ $documentNumber }} &bull; {{ $documentTitle }} ({{ $statusText }})
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; background-color: #f4f7fb; border-collapse: collapse;">
        <tr>
            <td align="center" class="email-shell" style="padding: 32px 16px 40px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-container" style="width: 100%; max-width: 600px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; border-collapse: collapse;">
                    {{-- Header Ice-Blue sesuai mockup referensi --}}
                    <tr>
                        <td class="email-hero-pad" style="background-color: #eaf2ff; padding: 32px 36px 28px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td valign="middle" style="padding: 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                            <tr>
                                                <td valign="middle" style="padding: 0 10px 0 0;">
                                                    @if ($logoUrl)
                                                        <img src="{{ $logoUrl }}" alt="{{ $universityName }}" height="28" style="display: block; height: 28px; width: auto; max-width: 120px; border: 0; outline: none;">
                                                    @else
                                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                                            <tr>
                                                                <td align="center" valign="middle" height="28" style="height: 28px; min-width: 28px; padding: 0 8px; background-color: #2563eb; border-radius: 6px; color: #ffffff; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; line-height: 28px; text-align: center; white-space: nowrap;">
                                                                    {{ $logoBadgeText }}
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endif
                                                </td>
                                                <td valign="middle" style="padding: 0; font-size: 14px; font-weight: 700; color: #334155; line-height: 20px;">
                                                    {{ $universityName }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="middle" align="right" style="padding: 0;">
                                        <span style="display: inline-block; padding: 4px 10px; background-color: {{ $badgeBg }}; border: 1px solid {{ $badgeBorder }}; border-radius: 999px; font-size: 11px; font-weight: 700; color: {{ $badgeText }}; letter-spacing: 0.03em; line-height: 16px; white-space: nowrap;">
                                            {{ $badgeLabel }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <h1 class="email-hero-title" style="margin: 24px 0 0 0; font-size: 28px; line-height: 35px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                                {{ $headlineLead }}<br>
                                <span style="color: {{ $accentColor }};">{{ $headlineAccent }}</span>
                            </h1>
                        </td>
                    </tr>

                    {{-- Body Putih Utama --}}
                    <tr>
                        <td class="email-body-pad" style="background-color: #ffffff; padding: 32px 36px;">
                            <p style="margin: 0 0 8px 0; font-size: 14px; line-height: 22px; color: #334155;">
                                <strong style="color: #2563eb; font-weight: 700;">Yth. {{ $recipientName }},</strong>
                            </p>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 22px; color: #475569;">
                                {{ $introText }}
                            </p>

                            {{-- Daftar Detail Dokumen (Baris bersih dengan garis tipis horizontal) --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; border-top: 1px solid #e2e8f0;">
                                <tr>
                                    <td class="email-kv-cell" valign="top" width="38%" style="padding: 13px 12px 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 700; color: #0f172a;">
                                        Nomor Naskah
                                    </td>
                                    <td class="email-kv-cell email-kv-val-cell" valign="top" align="right" style="padding: 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; color: #334155; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                                        {{ $documentNumber }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-kv-cell" valign="top" width="38%" style="padding: 13px 12px 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 700; color: #0f172a;">
                                        Judul Dokumen
                                    </td>
                                    <td class="email-kv-cell email-kv-val-cell" valign="top" align="right" style="padding: 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 600; color: #0f172a;">
                                        {{ $documentTitle }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-kv-cell" valign="top" width="38%" style="padding: 13px 12px 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 700; color: #0f172a;">
                                        Unit Pengelola
                                    </td>
                                    <td class="email-kv-cell email-kv-val-cell" valign="top" align="right" style="padding: 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; color: #475569;">
                                        {{ $unitName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-kv-cell" valign="top" width="38%" style="padding: 13px 12px 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 700; color: #0f172a;">
                                        Tanggal Kedaluwarsa
                                    </td>
                                    <td class="email-kv-cell email-kv-val-cell" valign="top" align="right" style="padding: 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 600; color: #0f172a;">
                                        {{ $expiredDateStr }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-kv-cell" valign="middle" width="38%" style="padding: 13px 12px 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px; font-weight: 700; color: #0f172a;">
                                        Status Masa Berlaku
                                    </td>
                                    <td class="email-kv-cell email-kv-val-cell" valign="middle" align="right" style="padding: 13px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; line-height: 20px;">
                                        <span style="display: inline-block; padding: 3px 10px; background-color: {{ $badgeBg }}; border: 1px solid {{ $badgeBorder }}; border-radius: 999px; font-size: 12px; font-weight: 700; color: {{ $badgeText }};">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            {{-- Blok CTA & Tautan Portal Admin --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 28px 0 6px 0; font-size: 16px; line-height: 22px; font-weight: 700; color: #0f172a;">
                                        {{ $ctaHeading }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 18px 0; font-size: 14px; line-height: 21px; color: #475569;">
                                        {{ $ctaDescription }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 28px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                            <tr>
                                                <td class="email-cta-cell" valign="middle" style="padding: 0 10px 0 0;">
                                                    <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="email-cta-link" style="display: inline-block; background-color: #2563eb; border: 1px solid #2563eb; border-radius: 6px; padding: 11px 22px; font-size: 14px; line-height: 20px; font-weight: 600; color: #ffffff; text-decoration: none;">
                                                        Buka Dokumen
                                                    </a>
                                                </td>
                                                <td class="email-cta-cell" valign="middle" style="padding: 0;">
                                                    <a href="{{ $adminPortalUrl }}" target="_blank" rel="noopener" class="email-cta-link" style="display: inline-block; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 11px 18px; font-size: 14px; line-height: 20px; font-weight: 600; color: #1e293b; text-decoration: none;">
                                                        Masuk ke Portal Admin
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Blok Bantuan Kontak Kampus --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; border-top: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 24px 0 6px 0; font-size: 15px; line-height: 22px; font-weight: 700; color: #0f172a;">
                                        Butuh bantuan lebih lanjut?
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 0 12px 0; font-size: 13px; line-height: 20px; color: #475569;">
                                        Jika ada pertanyaan atau kendala terkait pembaruan arsip, silakan hubungi pengelola melalui
                                        @if ($supportEmail)
                                            <a href="mailto:{{ $supportEmail }}" style="color: #2563eb; text-decoration: underline;">{{ $supportEmail }}</a>
                                        @else
                                            administrator portal
                                        @endif
                                        atau akses langsung <a href="{{ $adminPortalUrl }}" target="_blank" rel="noopener" style="color: #2563eb; text-decoration: underline;">Portal Admin</a>.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0; font-size: 13px; line-height: 20px; color: #64748b;">
                                        &mdash; Tim {{ $systemName }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Footer Minimalis di luar card putih --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-container" style="width: 100%; max-width: 600px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 24px 8px 8px 8px; font-size: 12px; line-height: 18px; color: #94a3b8;">
                            {{ $copyrightText }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 8px 16px 8px; font-size: 12px; line-height: 18px; color: #94a3b8;">
                            <a href="{{ $publicPortalUrl }}" target="_blank" rel="noopener" style="color: #94a3b8; text-decoration: underline;">Portal Publik</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ $adminPortalUrl }}" target="_blank" rel="noopener" style="color: #94a3b8; text-decoration: underline;">Portal Admin</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ $documentUrl }}" target="_blank" rel="noopener" style="color: #94a3b8; text-decoration: underline;">Detail Dokumen</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                <tr>
                                    <td valign="middle" style="padding: 0 8px 0 0;">
                                        @if ($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $universityName }}" height="18" style="display: block; height: 18px; width: auto; max-width: 80px; opacity: 0.7; border: 0; outline: none;">
                                        @else
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                                <tr>
                                                    <td align="center" valign="middle" height="20" style="height: 20px; min-width: 20px; padding: 0 6px; background-color: #94a3b8; border-radius: 4px; color: #ffffff; font-size: 9px; font-weight: 800; letter-spacing: 0.03em; line-height: 20px; text-align: center; white-space: nowrap;">
                                                        {{ $logoBadgeText }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                    <td valign="middle" style="padding: 0; font-size: 12px; font-weight: 700; color: #94a3b8; line-height: 18px;">
                                        {{ $universityName }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
