<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ButtonHint
{
    /**
     * Membuat Hint Action dengan modal panduan yang rapi, terstruktur, dan nyaman dibaca.
     */
    public static function make(string|array $message, ?string $title = null, ?Width $width = null): Action
    {
        $rawMessage = is_array($message) ? implode("\n\n", $message) : $message;
        $name = $title ? 'hint_'.Str::slug($title, '_') : 'hint_'.substr(md5($rawMessage), 0, 8);
        $parsedData = static::parseContent($rawMessage);

        $isComplex = $parsedData['is_complex'] ?? false;
        $modalWidth = $width ?? ($isComplex ? Width::Large : Width::Medium);

        return Action::make($name)
            ->label($title ?? 'Petunjuk')
            ->hiddenLabel(true)
            ->tooltip($title ?? 'Lihat Keterangan & Panduan')
            ->icon('heroicon-m-information-circle')
            ->color('info')
            ->modalHeading($title ?? 'Panduan & Keterangan Resmi')
            ->modalDescription(null)
            ->modalContent(fn (): View => view('filament.components.hint-modal', [
                'title' => $title ?? 'Panduan Resmi',
                'rawMessage' => $rawMessage,
                'data' => $parsedData,
            ]))
            ->modalWidth($modalWidth)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup Panduan');
    }

    /**
     * Mem-parsing teks keterangan menjadi struktur data yang rapi dan terorganisir.
     */
    public static function parseContent(string $text): array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $text));

        $hasNumberedSections = (bool) preg_match('/(?:^|\n)\s*\d+\.\s+/m', $normalized);
        $hasTokens = (bool) preg_match('/(\\\\?\$(\{[A-Za-z0-9_]+\}|[A-Za-z0-9_]+)|\{[a-zA-Z0-9_\.\/\{\}]+\})/', $normalized);

        if ($hasNumberedSections) {
            $parts = preg_split('/(?:^|\n)\s*(\d+)\.\s+/m', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE);
            $intro = trim($parts[0] ?? '');
            $sections = [];

            for ($i = 1; $i < count($parts); $i += 2) {
                $num = $parts[$i];
                $body = trim($parts[$i + 1] ?? '');

                $lines = explode("\n", $body);
                $titleLine = array_shift($lines);
                $sectionTitle = trim(rtrim(trim($titleLine), ':'));

                $introLines = [];
                $items = [];

                foreach ($lines as $line) {
                    $trimmedLine = trim($line);
                    if ($trimmedLine === '') {
                        continue;
                    }

                    if (str_starts_with($trimmedLine, '•') || str_starts_with($trimmedLine, '-') || str_starts_with($trimmedLine, '*')) {
                        $cleanedItem = trim(ltrim($trimmedLine, "•-* \t"));
                        $items[] = [
                            'raw' => $cleanedItem,
                            'html' => static::formatTokens($cleanedItem),
                        ];
                    } else {
                        $introLines[] = $trimmedLine;
                    }
                }

                $sections[] = [
                    'number' => $num,
                    'title' => $sectionTitle,
                    'intro' => implode(' ', $introLines),
                    'intro_formatted' => static::formatTokens(implode(' ', $introLines)),
                    'items' => $items,
                ];
            }

            return [
                'type' => 'numbered_sections',
                'is_complex' => true,
                'intro' => $intro,
                'sections' => $sections,
                'has_tokens' => $hasTokens,
            ];
        }

        // Jika teks memiliki poin-poin bullet (• atau -)
        if (str_contains($normalized, '•') || preg_match('/(?:^|\n)\s*[-*]\s+/m', $normalized)) {
            $lines = explode("\n", $normalized);
            $introLines = [];
            $bullets = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                if (str_starts_with($trimmed, '•') || str_starts_with($trimmed, '-') || str_starts_with($trimmed, '*')) {
                    $cleaned = trim(ltrim($trimmed, "•-* \t"));
                    $bullets[] = [
                        'raw' => $cleaned,
                        'html' => static::formatTokens($cleaned),
                    ];
                } else {
                    $introLines[] = $trimmed;
                }
            }

            return [
                'type' => 'bullets',
                'is_complex' => count($bullets) > 4,
                'intro' => implode("\n", $introLines),
                'bullets' => $bullets,
                'has_tokens' => $hasTokens,
            ];
        }

        // Teks narasi paragraf sederhana
        $rawParagraphs = array_filter(array_map('trim', explode("\n\n", $normalized)));
        $paragraphs = array_map(fn (string $p) => nl2br(static::formatTokens($p)), $rawParagraphs);

        return [
            'type' => 'simple',
            'is_complex' => false,
            'intro' => null,
            'paragraphs' => $paragraphs,
            'has_tokens' => $hasTokens,
        ];
    }

    /**
     * Menghighlight token kode seperti {seq}, {nomor_surat}, ${QR_CODE} menjadi chip yang interaktif.
     */
    public static function formatTokens(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold text-gray-900 dark:text-gray-100">$1</strong>', $escaped);

        $pattern = '/(\\\\?\$(\{[A-Za-z0-9_]+\}|[A-Za-z0-9_]+)|\{[a-zA-Z0-9_\.\/\{\}]+\})/';

        return (string) preg_replace_callback($pattern, function (array $matches): string {
            $clean = ltrim($matches[0], '\\');
            $raw = htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
            $jsSafe = addcslashes($clean, "'\\");

            return sprintf(
                '<button type="button" data-token="%s" @click.stop.prevent="copyToken($el.getAttribute(\'data-token\'), $el)" class="hint-chip" :class="{ \'hint-chip-active\': copiedToken === \'%s\' }" title="Klik untuk menyalin %s"><span class="hint-chip-label">%s</span><span x-show="copiedToken === \'%s\'" x-cloak class="hint-chip-copied">✓ Tersalin</span><span x-show="copiedToken !== \'%s\'" class="hint-chip-icon"><svg class="hint-icon-svg" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M11.986 3H12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h.014A2.25 2.25 0 0 1 10.25 1.5h-1.5A2.25 2.25 0 0 1 6.5 3H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-.014ZM10.5 4v-.75a.75.75 0 0 0-.75-.75h-1.5a.75.75 0 0 0-.75.75V4h3Z" clip-rule="evenodd" /></svg></span></button>',
                $raw,
                $jsSafe,
                $raw,
                $raw,
                $jsSafe,
                $jsSafe
            );
        }, $escaped);
    }
}
