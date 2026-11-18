<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StampingPosition;
use App\Exceptions\DocumentStampingException;
use App\Models\Document;
use App\Models\DocumentFile;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DocumentStampingService
{
    public function __construct(
        protected DocumentNamingService $namingService
    ) {}

    /**
     * Membubuhi stempel pengesahan resmi dan stempel QR-Code ke berkas fisik dokumen.
     * Mengembalikan model DocumentFile yang telah diperbarui dengan kolom stamped_file_path.
     */
    public function stamp(Document $document, ?DocumentFile $file = null): ?DocumentFile
    {
        $targetFile = $file ?? $document->primaryFile ?? $document->files()->where('is_active', true)->first();

        if (! $targetFile || empty($targetFile->original_file_path)) {
            Log::info("Stamping dilewati: Dokumen #{$document->id} belum memiliki berkas fisik.");

            return null;
        }

        $extension = strtolower(pathinfo($targetFile->original_file_path, PATHINFO_EXTENSION));

        try {
            $stampedPath = match ($extension) {
                'pdf' => $this->stampPdf($document, $targetFile),
                'docx' => $this->stampWord($document, $targetFile),
                default => null,
            };

            if ($stampedPath) {
                $diskName = $targetFile->disk?->value ?? 'private';
                $stampedHash = $this->computeStorageFileSha256($diskName, $stampedPath);

                $targetFile->update([
                    'stamped_file_path' => $stampedPath,
                    'stamped_file_hash_sha256' => $stampedHash,
                ]);

                if (auth()->check()) {
                    activity('document_audit')
                        ->performedOn($document)
                        ->causedBy(auth()->user())
                        ->event('stamped')
                        ->withProperties([
                            'document_number' => $document->document_number,
                            'file_name' => $targetFile->getEffectiveDownloadFileName(),
                            'stamped_file_path' => $stampedPath,
                            'original_file_hash_sha256' => $targetFile->file_hash_sha256,
                            'stamped_file_hash_sha256' => $stampedHash,
                            'stamp_position' => 'footer_bottom_right',
                        ])
                        ->log('Membubuhi stempel resmi naskah dinas dan QR-Code verifikasi');
                }
            }
        } catch (DocumentStampingException $e) {
            Log::warning("Stamping berkas #{$targetFile->id} untuk dokumen #{$document->id} dilewati: {$e->getMessage()}");
        }

        return $targetFile->fresh();
    }

    /**
     * Membubuhi stempel resmi universitas dan stempel QR-Code ke lembar berkas PDF.
     * Menggunakan engine FPDI & FPDF.
     *
     * @throws DocumentStampingException
     */
    public function stampPdf(Document $document, DocumentFile $file): string
    {
        $diskName = $file->disk?->value ?? 'private';
        $storage = Storage::disk($diskName);

        if (! $storage->exists($file->original_file_path)) {
            throw new DocumentStampingException("Berkas PDF asli tidak ditemukan pada storage disk [{$diskName}]: {$file->original_file_path}");
        }

        $tempOriginal = tempnam(sys_get_temp_dir(), 'pdf_orig_').'.pdf';
        $tempStamped = tempnam(sys_get_temp_dir(), 'pdf_stamp_').'.pdf';

        try {
            file_put_contents($tempOriginal, $storage->get($file->original_file_path));

            $verifyUrl = $document->getVerificationUrl();
            $docNumber = $document->document_number ?: 'DRAF';
            $numberLabel = str_starts_with(strtolower($docNumber), 'no')
                ? $docNumber
                : "No. {$docNumber}";
            $dateLine = "Disahkan: {$this->formatIssuedDate($document)} | e-Arsip UNIVBI";

            $this->stampPdfFooterOverlay(
                sourceLocalPath: $tempOriginal,
                targetLocalPath: $tempStamped,
                numberLabel: $numberLabel,
                dateLine: $dateLine,
                verifyUrl: $verifyUrl
            );

            $stampedRelativePath = $this->generateStampedPath($file);
            $storage->put($stampedRelativePath, (string) file_get_contents($tempStamped));

            return $stampedRelativePath;
        } catch (Throwable $e) {
            Log::error('Gagal membubuhi stempel pada berkas PDF: '.$e->getMessage(), [
                'document_id' => $document->id,
                'file_id' => $file->id,
                'exception' => $e,
            ]);

            throw new DocumentStampingException('Gagal membubuhi stempel berkas PDF: '.$e->getMessage(), 0, $e);
        } finally {
            if (file_exists($tempOriginal)) {
                @unlink($tempOriginal);
            }
            if (file_exists($tempStamped)) {
                @unlink($tempStamped);
            }
        }
    }

    /**
     * Membubuhi QR Code berukuran ringkas serta baris nomor & tanggal resmi pada footer pojok kanan bawah
     * seluruh halaman berkas PDF lokal menggunakan FPDI & FPDF.
     */
    public function stampPdfFooterOverlay(
        string $sourceLocalPath,
        string $targetLocalPath,
        string $numberLabel,
        string $dateLine,
        string $verifyUrl
    ): void {
        $qrPath = null;

        try {
            $qrPath = $this->generateQrCodePng($verifyUrl);

            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($sourceLocalPath);

            $qrSize = 11.5;
            $numberText = $this->sanitizePdfText($numberLabel);
            $dateText = $this->sanitizePdfText($dateLine);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $orientation = $size['orientation'];
                $pageWidth = (float) $size['width'];
                $pageHeight = (float) $size['height'];

                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($templateId);

                $coords = $this->calculateStampCoordinates(
                    pageWidth: $pageWidth,
                    pageHeight: $pageHeight,
                    boxWidth: $qrSize,
                    boxHeight: $qrSize,
                    position: StampingPosition::Footer,
                );

                $qrX = $coords['x'];
                $qrY = $coords['y'];

                $pdf->SetFont('Helvetica', 'B', 6.5);
                $wNumber = $pdf->GetStringWidth($numberText);
                $pdf->SetFont('Helvetica', '', 5.8);
                $wDate = $pdf->GetStringWidth($dateText);

                $textWidth = min(max($wNumber, $wDate, 32.0), max(35.0, $qrX - 10.0));
                $textLeftX = max(6.0, $qrX - $textWidth - 2.2);
                $blockLeftX = $textLeftX - 1.5;
                $blockWidth = ($qrX + $qrSize + 0.8) - $blockLeftX;

                // Latar putih bersih di area footer paling bawah pojok kanan agar keterangan pengesahan tidak bertabrakan dengan garis tepi
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($blockLeftX, $qrY - 0.5, $blockWidth, $qrSize + 1.0, 'F');

                $pdf->SetDrawColor(203, 213, 225);
                $pdf->SetLineWidth(0.2);
                $pdf->Line($blockLeftX, $qrY - 0.5, $qrX + $qrSize, $qrY - 0.5);

                // Baris 1: Nomor Surat Resmi (rata kanan di sebelah kiri QR Code)
                $pdf->SetFont('Helvetica', 'B', 6.5);
                $pdf->SetTextColor(30, 41, 59);
                $pdf->SetXY($textLeftX, $qrY + 2.4);
                $pdf->Cell($textWidth, 3.2, $numberText, 0, 0, 'R');

                // Baris 2: Tanggal Pengesahan & Identitas e-Arsip
                $pdf->SetFont('Helvetica', '', 5.8);
                $pdf->SetTextColor(71, 85, 105);
                $pdf->SetXY($textLeftX, $qrY + 5.9);
                $pdf->Cell($textWidth, 3.0, $dateText, 0, 0, 'R');

                // QR Code verifikasi berukuran ringkas di pojok kanan bawah footer
                $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);
            }

            $pdf->Output('F', $targetLocalPath);
        } finally {
            if ($qrPath && file_exists($qrPath)) {
                @unlink($qrPath);
            }
        }
    }

    /**
     * Membubuhi stempel nomor sah dan QR Code ke dokumen Word (.docx).
     * Mengganti tag teks template (${NOMOR_SURAT}, {seq}/..., {tanggal_sah}, {pejabat}) serta
     * selalu menyematkan QR Code verifikasi berukuran ringkas di footer pojok kanan bawah semua halaman.
     *
     * @throws DocumentStampingException
     */
    public function stampWord(Document $document, DocumentFile $file): ?string
    {
        $diskName = $file->disk?->value ?? 'private';
        $storage = Storage::disk($diskName);

        if (! $storage->exists($file->original_file_path)) {
            throw new DocumentStampingException("Berkas Word asli tidak ditemukan pada storage disk [{$diskName}]: {$file->original_file_path}");
        }

        $tempOriginal = tempnam(sys_get_temp_dir(), 'docx_orig_').'.docx';
        $tempStamped = tempnam(sys_get_temp_dir(), 'docx_stamp_').'.docx';
        $tempConvertedPdfStamped = tempnam(sys_get_temp_dir(), 'docx_pdf_stamp_').'.pdf';
        $pdfConvertDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'docx_pdf_'.uniqid('', true);
        $qrPath = null;

        try {
            file_put_contents($tempOriginal, $storage->get($file->original_file_path));

            $verifyUrl = $document->getVerificationUrl();
            // Margin 0 pada PNG Word agar modul QR mengisi 100% kotak tanpa padding putih internal berlebih
            $qrPath = $this->generateQrCodePng($verifyUrl, 360, 0);

            $docNumber = $document->document_number ?: 'NOMOR: [DRAF]';
            $dateStr = $this->formatIssuedDate($document);
            $signer = $this->resolveSignerName($document);

            // 1. Ganti placeholder variabel TemplateProcessor (${...}) tanpa merusak struktur XML asli dokumen
            $templateProcessor = new TemplateProcessor($tempOriginal);
            $variables = $templateProcessor->getVariables();

            $hasPlaceholders = false;
            foreach ($variables as $v) {
                $upper = strtoupper($v);
                if (in_array($upper, ['NOMOR_SURAT', 'NOMOR', 'NO_SURAT', 'DOCUMENT_NUMBER', 'NO'], true)) {
                    $templateProcessor->setValue($v, $docNumber);
                    $hasPlaceholders = true;
                } elseif (in_array($upper, ['TANGGAL_SAH', 'TANGGAL', 'ISSUED_DATE', 'DATE'], true)) {
                    $templateProcessor->setValue($v, $dateStr);
                    $hasPlaceholders = true;
                } elseif (in_array($upper, ['PEJABAT', 'SIGNER', 'PENGESAH', 'PENANDATANGAN'], true)) {
                    $templateProcessor->setValue($v, $signer);
                    $hasPlaceholders = true;
                } elseif ($upper === 'UUID') {
                    $templateProcessor->setValue($v, $document->uuid);
                    $hasPlaceholders = true;
                } elseif ($upper === 'VERIFY_URL') {
                    $templateProcessor->setValue($v, $verifyUrl);
                    $hasPlaceholders = true;
                }
            }

            if ($hasPlaceholders) {
                $templateProcessor->saveAs($tempStamped);
            } else {
                copy($tempOriginal, $tempStamped);
            }

            // 2. Gabungkan run XML terpecah & ganti seluruh pola placeholder/nomor sebelum konversi/stempel final
            $zip = new \ZipArchive;
            if ($zip->open($tempStamped) === true) {
                $categoryPattern = $document->category?->number_format;
                $searchPatterns = array_filter([
                    $categoryPattern,
                    '{seq}/UNIVBI.{unit}/{cat}/{roman_month}/{year}',
                    '{seq}/UNIVBI.{unit}/{cat}/{month}/{year}',
                ]);

                $escapedDocNumber = htmlspecialchars($docNumber, ENT_XML1, 'UTF-8');
                $escapedDateStr = htmlspecialchars($dateStr, ENT_XML1, 'UTF-8');
                $escapedSigner = htmlspecialchars($signer, ENT_XML1, 'UTF-8');
                $escapedUuid = htmlspecialchars((string) $document->uuid, ENT_XML1, 'UTF-8');
                $escapedVerifyUrl = htmlspecialchars($verifyUrl, ENT_XML1, 'UTF-8');

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_ends_with($entryName, '.xml')) {
                        $entryXml = $zip->getFromIndex($i);
                        if ($entryXml) {
                            $isDocumentOrHeader = ($entryName === 'word/document.xml') || preg_match('#word/(header|footer)\d+\.xml#', $entryName);
                            if ($isDocumentOrHeader) {
                                $entryXml = $this->coalesceWordPlaceholderRuns($entryXml);

                                foreach ($searchPatterns as $sp) {
                                    if (str_contains($entryXml, $sp)) {
                                        $entryXml = str_replace($sp, $escapedDocNumber, $entryXml);
                                    }
                                }

                                // Regex untuk pola dinamis {seq}/...
                                $entryXml = (string) preg_replace_callback('/\{seq\}\/[^\s<>]+(?=\s|<|\")/i', function () use ($escapedDocNumber): string {
                                    return $escapedDocNumber;
                                }, $entryXml);

                                $entryXml = (string) preg_replace_callback('/\{\s*(nomor_surat|no_surat|document_number|nomor|no)\s*\}|\$\{\s*(nomor_surat|no_surat|document_number|nomor|no)\s*\}/i', function () use ($escapedDocNumber): string {
                                    return $escapedDocNumber;
                                }, $entryXml);

                                $entryXml = (string) preg_replace_callback('/\{\s*(tanggal_sah|tgl_sah|issued_date|tanggal|date)\s*\}|\$\{\s*(tanggal_sah|tgl_sah|issued_date|tanggal|date)\s*\}/i', function () use ($escapedDateStr): string {
                                    return $escapedDateStr;
                                }, $entryXml);

                                $entryXml = (string) preg_replace_callback('/\{\s*(pejabat|signer|pengesah|penandatangan|tt_pengirim|ttd)\s*\}|\$\{\s*(pejabat|signer|pengesah|penandatangan|tt_pengirim|ttd)\s*\}/i', function () use ($escapedSigner): string {
                                    return $escapedSigner;
                                }, $entryXml);

                                $entryXml = (string) preg_replace('/\$?\{\s*uuid\s*\}/i', $escapedUuid, $entryXml);
                                $entryXml = (string) preg_replace('/\$?\{\s*verify_url\s*\}/i', $escapedVerifyUrl, $entryXml);
                            }

                            // Sanitasi global: pastikan tidak ada karakter & telanjang yang merusak XML (contoh: Kepala Sistem Informasi & E-Arsip)
                            $sanitizedXml = (string) preg_replace('/&(?!(amp|lt|gt|quot|apos);)/', '&amp;', $entryXml);
                            $zip->addFromString($entryName, $sanitizedXml);
                        }
                    }
                }
                $zip->close();
            }

            // 3. Jika LibreOffice tersedia (mis. di server production), konversi DOCX -> PDF lalu tempel QR/Nomor pada PDF final
            $convertedPdfPath = $this->convertDocxToPdf($tempStamped, $pdfConvertDir);
            if ($convertedPdfPath !== null && is_file($convertedPdfPath)) {
                $numberLabel = str_starts_with(strtolower($docNumber), 'no')
                    ? $docNumber
                    : "No. {$docNumber}";
                $dateLine = "Disahkan: {$dateStr} | e-Arsip UNIVBI";

                $this->stampPdfFooterOverlay(
                    sourceLocalPath: $convertedPdfPath,
                    targetLocalPath: $tempConvertedPdfStamped,
                    numberLabel: $numberLabel,
                    dateLine: $dateLine,
                    verifyUrl: $verifyUrl
                );

                $stampedRelativePath = $this->generateStampedPath($file, 'pdf');
                $storage->put($stampedRelativePath, (string) file_get_contents($tempConvertedPdfStamped));

                return $stampedRelativePath;
            }

            // 4. Fallback aman jika LibreOffice belum terpasang (mis. mesin lokal): sematkan QR langsung di footer .docx
            if ($zip->open($tempStamped) === true) {
                $this->injectWordFooterQrDrawing($zip, $qrPath, 52.0, 8.0);
                $zip->close();
            }

            $stampedRelativePath = $this->generateStampedPath($file, 'docx');
            $storage->put($stampedRelativePath, (string) file_get_contents($tempStamped));

            return $stampedRelativePath;
        } catch (Throwable $e) {
            Log::error('Gagal membubuhi stempel pada berkas Word (.docx): '.$e->getMessage(), [
                'document_id' => $document->id,
                'file_id' => $file->id,
                'exception' => $e,
            ]);

            throw new DocumentStampingException('Gagal membubuhi stempel berkas Word: '.$e->getMessage(), 0, $e);
        } finally {
            foreach ([$tempOriginal, $tempStamped, $tempConvertedPdfStamped, $qrPath] as $tempPath) {
                if ($tempPath && file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }

            if (is_dir($pdfConvertDir)) {
                File::deleteDirectory($pdfConvertDir);
            }
        }
    }

    /**
     * Menyematkan QR Code verifikasi ke seluruh footer section dokumen Word (.docx)
     * menggunakan DrawingML <wp:anchor relativeFrom="page"> sehingga menempel persis di pojok kanan bawah kertas
     * tanpa terhalang padding margin dokumen maupun margin internal footer.
     */
    public function injectWordFooterQrDrawing(\ZipArchive $zip, string $qrPath, float $qrSizePt = 52.0, float $edgeGapPt = 8.0): void
    {
        $documentXml = $zip->getFromName('word/document.xml');
        $docRelsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');

        if (! is_string($documentXml) || ! is_string($docRelsXml) || ! is_string($contentTypesXml)) {
            return;
        }

        $mediaFileName = 'univbi_qr_'.substr(md5((string) microtime(true).$qrPath), 0, 8).'.png';
        $zip->addFromString("word/media/{$mediaFileName}", (string) file_get_contents($qrPath));

        if (! preg_match('/Extension=["\']png["\']/i', $contentTypesXml)) {
            $contentTypesXml = str_replace(
                '</Types>',
                '<Default Extension="png" ContentType="image/png"/></Types>',
                $contentTypesXml
            );
        }

        // Petakan relasi rId -> Target pada word/_rels/document.xml.rels
        $docRelTargets = [];
        $maxDocRelId = 0;
        if (preg_match_all('/<Relationship\b([^>]+)\/?>/i', $docRelsXml, $relMatches)) {
            foreach ($relMatches[1] as $attrs) {
                if (preg_match('/Id=["\']([^"\']+)["\']/i', $attrs, $idM) && preg_match('/Target=["\']([^"\']+)["\']/i', $attrs, $targetM)) {
                    $docRelTargets[$idM[1]] = $targetM[1];
                    if (preg_match('/^rId(\d+)$/i', $idM[1], $numM)) {
                        $maxDocRelId = max($maxDocRelId, (int) $numM[1]);
                    }
                }
            }
        }

        // Cari nomor urut tertinggi footer yang sudah ada di arsip docx
        $maxFooterNum = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/footer(\d+)\.xml$#i', $name, $fnM)) {
                $maxFooterNum = max($maxFooterNum, (int) $fnM[1]);
            }
        }

        $qrSizeEmu = (int) round($qrSizePt * 12700);
        $edgeGapEmu = (int) round($edgeGapPt * 12700);
        $footersToStamp = [];
        $pendingFooterXml = [];

        $allocateDefaultFooter = function (int $posXEmu, int $posYEmu) use (
            &$maxFooterNum,
            &$maxDocRelId,
            &$docRelsXml,
            &$contentTypesXml,
            &$footersToStamp,
            &$pendingFooterXml
        ): string {
            $maxFooterNum++;
            $footerFileName = "footer{$maxFooterNum}.xml";
            $footerZipPath = "word/{$footerFileName}";

            $maxDocRelId++;
            $newRelId = "rId{$maxDocRelId}";

            $relNode = "<Relationship Id=\"{$newRelId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer\" Target=\"{$footerFileName}\"/>";
            $docRelsXml = str_replace('</Relationships>', $relNode.'</Relationships>', $docRelsXml);

            if (! str_contains($contentTypesXml, "/{$footerZipPath}")) {
                $overrideNode = "<Override PartName=\"/{$footerZipPath}\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml\"/>";
                $contentTypesXml = str_replace('</Types>', $overrideNode.'</Types>', $contentTypesXml);
            }

            $pendingFooterXml[$footerZipPath] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"></w:ftr>';

            $footersToStamp[$footerZipPath] = [
                'posX' => $posXEmu,
                'posY' => $posYEmu,
            ];

            return $newRelId;
        };

        $sectMatches = 0;
        $documentXml = (string) preg_replace_callback(
            '#<w:sectPr\b([^>]*)(?:\/>|>(.*?)<\/w:sectPr>)#s',
            function (array $m) use (&$sectMatches, &$footersToStamp, $docRelTargets, $allocateDefaultFooter, $qrSizeEmu, $edgeGapEmu): string {
                $sectMatches++;
                $sectAttrs = $m[1];
                $sectInner = $m[2] ?? '';

                $wTwips = 11906;
                $hTwips = 16838;
                if (preg_match('/<w:pgSz\b([^>]+)\/?>/i', $sectInner, $pgSzM)) {
                    $pgAttrs = $pgSzM[1];
                    if (preg_match('/w:w=["\'](\d+)["\']/i', $pgAttrs, $wM)) {
                        $wTwips = (int) $wM[1];
                    }
                    if (preg_match('/w:h=["\'](\d+)["\']/i', $pgAttrs, $hM)) {
                        $hTwips = (int) $hM[1];
                    }
                    if (preg_match('/w:orient=["\']landscape["\']/i', $pgAttrs) && $wTwips < $hTwips) {
                        [$wTwips, $hTwips] = [$hTwips, $wTwips];
                    }
                }

                $posXEmu = max(0, (int) round($wTwips * 635) - $qrSizeEmu - $edgeGapEmu);
                $posYEmu = max(0, (int) round($hTwips * 635) - $qrSizeEmu - $edgeGapEmu);

                $hasDefaultFooter = false;
                if (preg_match_all('/<w:footerReference\b([^>]+)\/?>/i', $sectInner, $fRefs)) {
                    foreach ($fRefs[1] as $fAttrs) {
                        $fType = '';
                        $fRelId = '';
                        if (preg_match('/w:type=["\']([^"\']+)["\']/i', $fAttrs, $tM)) {
                            $fType = strtolower($tM[1]);
                        }
                        if (preg_match('/r:id=["\']([^"\']+)["\']/i', $fAttrs, $idM)) {
                            $fRelId = $idM[1];
                        }
                        if ($fType === 'default') {
                            $hasDefaultFooter = true;
                        }
                        if ($fRelId !== '' && isset($docRelTargets[$fRelId])) {
                            $target = ltrim($docRelTargets[$fRelId], '/');
                            $footerZipPath = str_starts_with($target, 'word/') ? $target : "word/{$target}";
                            $footersToStamp[$footerZipPath] = [
                                'posX' => $posXEmu,
                                'posY' => $posYEmu,
                            ];
                        }
                    }
                }

                if (! $hasDefaultFooter) {
                    $newRelId = $allocateDefaultFooter($posXEmu, $posYEmu);
                    $sectInner = "<w:footerReference w:type=\"default\" r:id=\"{$newRelId}\"/>".$sectInner;
                }

                return "<w:sectPr{$sectAttrs}>{$sectInner}</w:sectPr>";
            },
            $documentXml
        );

        // Fallback jika dokumen tidak memiliki node <w:sectPr>
        if ($sectMatches === 0) {
            $posXEmu = max(0, (int) round(11906 * 635) - $qrSizeEmu - $edgeGapEmu);
            $posYEmu = max(0, (int) round(16838 * 635) - $qrSizeEmu - $edgeGapEmu);
            $newRelId = $allocateDefaultFooter($posXEmu, $posYEmu);
            $documentXml = str_replace(
                '</w:body>',
                "<w:sectPr><w:footerReference w:type=\"default\" r:id=\"{$newRelId}\"/><w:pgSz w:w=\"11906\" w:h=\"16838\"/></w:sectPr></w:body>",
                $documentXml
            );
        }

        // Sematkan DrawingML anchor ke tiap file footer yang aktif
        $docPrCounter = 9100;
        foreach ($footersToStamp as $footerZipPath => $coords) {
            $footerXml = $pendingFooterXml[$footerZipPath] ?? $zip->getFromName($footerZipPath);
            if (! is_string($footerXml)) {
                continue;
            }

            $footerBase = basename($footerZipPath);
            $footerRelsPath = "word/_rels/{$footerBase}.rels";
            $footerRelsXml = $zip->getFromName($footerRelsPath);
            if (! is_string($footerRelsXml)) {
                $footerRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
            }

            $maxImgRelId = 0;
            if (preg_match_all('/Id=["\']rId(\d+)["\']/i', $footerRelsXml, $imgRelM)) {
                $maxImgRelId = max(array_map('intval', $imgRelM[1])) ?: 0;
            }
            $nextImgRelId = 'rId'.($maxImgRelId + 1);

            $imgRelNode = "<Relationship Id=\"{$nextImgRelId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"media/{$mediaFileName}\"/>";
            $footerRelsXml = str_replace('</Relationships>', $imgRelNode.'</Relationships>', $footerRelsXml);
            $zip->addFromString($footerRelsPath, $footerRelsXml);

            if (! preg_match('/<w:ftr\b[^>]*xmlns:r=/i', $footerXml)) {
                $footerXml = (string) preg_replace(
                    '/<w:ftr\b/',
                    '<w:ftr xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"',
                    $footerXml,
                    1
                );
            }

            $docPrId = ++$docPrCounter;
            $posX = $coords['posX'];
            $posY = $coords['posY'];

            $drawingParagraph = '<w:p>'
                .'<w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>'
                .'<w:r>'
                .'<w:rPr><w:noProof/></w:rPr>'
                .'<w:drawing>'
                .'<wp:anchor xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251658240" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
                .'<wp:simplePos x="0" y="0"/>'
                ."<wp:positionH relativeFrom=\"page\"><wp:posOffset>{$posX}</wp:posOffset></wp:positionH>"
                ."<wp:positionV relativeFrom=\"page\"><wp:posOffset>{$posY}</wp:posOffset></wp:positionV>"
                ."<wp:extent cx=\"{$qrSizeEmu}\" cy=\"{$qrSizeEmu}\"/>"
                .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
                .'<wp:wrapNone/>'
                ."<wp:docPr id=\"{$docPrId}\" name=\"QR Verifikasi UNIVBI\"/>"
                .'<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
                .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                ."<pic:nvPicPr><pic:cNvPr id=\"{$docPrId}\" name=\"{$mediaFileName}\"/><pic:cNvPicPr/></pic:nvPicPr>"
                ."<pic:blipFill><a:blip r:embed=\"{$nextImgRelId}\"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>"
                ."<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$qrSizeEmu}\" cy=\"{$qrSizeEmu}\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></pic:spPr>"
                .'</pic:pic>'
                .'</a:graphicData>'
                .'</a:graphic>'
                .'</wp:anchor>'
                .'</w:drawing>'
                .'</w:r>'
                .'</w:p>';

            if (preg_match('#<w:ftr\b([^>]*)/>#', $footerXml)) {
                $footerXml = (string) preg_replace('#<w:ftr\b([^>]*)/>#', '<w:ftr$1>'.$drawingParagraph.'</w:ftr>', $footerXml, 1);
            } else {
                $footerXml = str_replace('</w:ftr>', $drawingParagraph.'</w:ftr>', $footerXml);
            }

            $zip->addFromString($footerZipPath, $footerXml);
        }

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $docRelsXml);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    }

    /**
     * Menggabungkan run teks Word (<w:r><w:t>) yang terpecah di dalam satu paragraf
     * (misalnya akibat tag spell-check <w:proofErr/> atau batas run) agar token seperti
     * {tanggal_sah} dan {seq}/UNIVBI.{unit}/... tetap terbaca utuh.
     */
    public function coalesceWordPlaceholderRuns(string $xml): string
    {
        $cleanXml = (string) preg_replace('#<w:proofErr\b[^>]*/>#', '', $xml);

        return (string) preg_replace_callback('#(<w:p\b[^>]*>)(.*?)(</w:p>)#s', function (array $pMatch): string {
            $inner = $pMatch[2];
            if (! str_contains($inner, '{')) {
                return $pMatch[0];
            }

            $parts = preg_split('#(<w:r\b[^>]*>.*?</w:r>)#s', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                return $pMatch[0];
            }

            $output = '';
            $buffered = null;
            $pendingGap = '';

            $flushBuffer = static function () use (&$output, &$buffered, &$pendingGap): void {
                if ($buffered === null) {
                    $output .= $pendingGap;
                    $pendingGap = '';

                    return;
                }

                if ($buffered['changed']) {
                    $updatedRun = preg_replace(
                        '#(<w:t)(?:\s+[^>]*)?(>)(.*?)(</w:t>)#s',
                        '$1 xml:space="preserve"$2'.$buffered['text'].'$4',
                        $buffered['raw'],
                        1
                    );
                    $output .= ($updatedRun ?? $buffered['raw']).$pendingGap;
                } else {
                    $output .= $buffered['raw'].$pendingGap;
                }

                $buffered = null;
                $pendingGap = '';
            };

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (trim($part) === '') {
                    $pendingGap .= $part;

                    continue;
                }

                $isTextRun = preg_match(
                    '#^<w:r\b[^>]*>(?:\s*<w:rPr\b[^>]*>.*?</w:rPr>|\s*<w:rPr\b[^>]*/>)?\s*<w:t(?:\s+[^>]*)?>(.*?)</w:t>\s*</w:r>$#s',
                    $part,
                    $runMatch
                ) === 1;

                if (! $isTextRun) {
                    $flushBuffer();
                    $output .= $part;

                    continue;
                }

                $runTextXml = $runMatch[1];
                $runTextDecoded = html_entity_decode($runTextXml, ENT_QUOTES | ENT_XML1, 'UTF-8');

                if ($buffered !== null) {
                    $bufDecoded = html_entity_decode($buffered['text'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $hasOpenBrace = substr_count($bufDecoded, '{') > substr_count($bufDecoded, '}');
                    $endsWithDollar = str_ends_with($bufDecoded, '$') && str_starts_with($runTextDecoded, '{');
                    $continuesSeqPattern = str_contains($bufDecoded, '{seq}')
                        && $runTextDecoded !== ''
                        && ! preg_match('/^\s/u', $runTextDecoded);

                    if ($hasOpenBrace || $endsWithDollar || $continuesSeqPattern) {
                        $buffered['text'] .= $runTextXml;
                        $buffered['changed'] = true;
                        $pendingGap = '';

                        continue;
                    }

                    $flushBuffer();
                }

                if (str_contains($runTextDecoded, '{') || str_ends_with($runTextDecoded, '$')) {
                    $output .= $pendingGap;
                    $pendingGap = '';
                    $buffered = [
                        'raw' => $part,
                        'text' => $runTextXml,
                        'changed' => false,
                    ];
                } else {
                    $output .= $pendingGap.$part;
                    $pendingGap = '';
                }
            }

            $flushBuffer();

            return $pMatch[1].$output.$pMatch[3];
        }, $cleanXml);
    }

    /**
     * Menghitung koordinat X dan Y kotak stempel berdasarkan orientasi halaman dan posisi enum.
     *
     * @return array{x: float, y: float}
     */
    public function calculateStampCoordinates(
        float $pageWidth,
        float $pageHeight,
        float $boxWidth,
        float $boxHeight,
        ?StampingPosition $position,
        float $xOffset = 0,
        float $yOffset = 0
    ): array {
        $position ??= StampingPosition::TopRight;

        switch ($position) {
            case StampingPosition::TopLeft:
                $x = 10.0 + $xOffset;
                $y = 10.0 + $yOffset;
                break;

            case StampingPosition::BottomCenter:
                $x = (($pageWidth - $boxWidth) / 2.0) + $xOffset;
                $y = $pageHeight - $boxHeight - 15.0 + $yOffset;
                break;

            case StampingPosition::TopRight:
                $x = $pageWidth - $boxWidth - 10.0 + $xOffset;
                $y = 8.0 + $yOffset;
                break;

            case StampingPosition::Footer:
            default:
                $x = $pageWidth - $boxWidth - 7.0 + $xOffset;
                $y = $pageHeight - $boxHeight - 3.5 + $yOffset;
                break;
        }

        // Clamp agar tidak keluar dari batas tepi halaman
        $clampedX = max(0.0, min($x, $pageWidth - $boxWidth));
        $clampedY = max(0.0, min($y, $pageHeight - $boxHeight));

        return [
            'x' => round($clampedX, 2),
            'y' => round($clampedY, 2),
        ];
    }

    /**
     * Menghasilkan file gambar PNG QR Code sementara menggunakan GD.
     * Menggunakan BaconQrCode Encoder matrix tanpa ketergantungan imagick sehingga dapat berjalan di semua lingkungan.
     */
    public function generateQrCodePng(string $content, int $pixelSize = 300, int $margin = 2): string
    {
        $qr = Encoder::encode(
            $content,
            ErrorCorrectionLevel::M()
        );
        $matrix = $qr->getMatrix();
        $moduleCount = $matrix->getWidth();

        $totalModules = $moduleCount + (2 * $margin);
        $scale = max(1, (int) round($pixelSize / $totalModules));
        $imageWidth = $totalModules * $scale;
        $imageHeight = $totalModules * $scale;

        $image = imagecreatetruecolor($imageWidth, $imageHeight);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $image,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        ($x + $margin + 1) * $scale - 1,
                        ($y + $margin + 1) * $scale - 1,
                        $black
                    );
                }
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'qr_').'.png';
        imagepng($image, $tempPath);
        imagedestroy($image);

        return $tempPath;
    }

    /**
     * Menghasilkan path penyimpanan hasil stempel relatif di storage disk.
     */
    protected function generateStampedPath(DocumentFile $file, ?string $targetExtension = null): string
    {
        $directory = dirname($file->original_file_path);
        $baseName = $file->standardized_file_name ?: basename($file->original_file_path);

        if (is_string($targetExtension) && $targetExtension !== '') {
            $nameWithoutExt = pathinfo($baseName, PATHINFO_FILENAME);
            $baseName = "{$nameWithoutExt}.".ltrim($targetExtension, '.');
        }

        return "{$directory}/stamped-{$baseName}";
    }

    /**
     * Hitung checksum SHA-256 secara streaming dari berkas yang tersimpan di disk storage.
     */
    protected function computeStorageFileSha256(string $diskName, string $path): ?string
    {
        $storage = Storage::disk($diskName);
        if ($path === '' || ! $storage->exists($path)) {
            return null;
        }

        $stream = $storage->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Konversi berkas Word (.docx) menjadi PDF memakai LibreOffice Headless jika tersedia di server.
     */
    protected function convertDocxToPdf(string $localDocxPath, string $outputDir): ?string
    {
        $binary = $this->resolveLibreOfficeBinary();
        if ($binary === null) {
            return null;
        }

        if (! is_dir($outputDir) && ! @mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            return null;
        }

        $profileDir = $outputDir.DIRECTORY_SEPARATOR.'lo_profile';
        if (! is_dir($profileDir)) {
            @mkdir($profileDir, 0755, true);
        }

        $userInstallArg = '-env:UserInstallation=file:///'.ltrim(str_replace('\\', '/', $profileDir), '/');
        $timeout = max(15, (int) config('services.libreoffice.timeout', 60));

        try {
            $process = new Process([
                $binary,
                $userInstallArg,
                '--headless',
                '--norestore',
                '--nologo',
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDir,
                $localDocxPath,
            ]);
            $process->setTimeout($timeout);
            $process->run();

            $expectedPdfPath = $outputDir.DIRECTORY_SEPARATOR.pathinfo($localDocxPath, PATHINFO_FILENAME).'.pdf';

            if (! $process->isSuccessful() || ! is_file($expectedPdfPath) || filesize($expectedPdfPath) === 0) {
                Log::warning('Konversi DOCX ke PDF via LibreOffice tidak menghasilkan berkas valid; fallback ke .docx.', [
                    'exit_code' => $process->getExitCode(),
                    'error_output' => trim($process->getErrorOutput()),
                ]);

                return null;
            }

            return $expectedPdfPath;
        } catch (Throwable $exception) {
            Log::warning('Gagal menjalankan proses LibreOffice untuk konversi DOCX ke PDF.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Temukan path binary LibreOffice (`soffice` / `libreoffice`) dari konfigurasi atau sistem PATH.
     */
    protected function resolveLibreOfficeBinary(): ?string
    {
        $configured = config('services.libreoffice.binary');
        if (is_string($configured) && trim($configured) !== '') {
            $trimmed = trim($configured);
            if (is_file($trimmed)) {
                return $trimmed;
            }
        }

        $finder = new ExecutableFinder;
        foreach (['soffice', 'libreoffice'] as $candidate) {
            $found = $finder->find($candidate);
            if (is_string($found) && $found !== '') {
                return $found;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ] as $windowsPath) {
                if (is_file($windowsPath)) {
                    return $windowsPath;
                }
            }
        }

        return null;
    }

    /**
     * Format tanggal pengesahan/terbit dokumen.
     */
    protected function formatIssuedDate(Document $document): string
    {
        if ($document->issued_date) {
            return $document->issued_date->translatedFormat('d M Y');
        }

        if ($document->approved_at) {
            return $document->approved_at->translatedFormat('d M Y');
        }

        return date('d M Y');
    }

    /**
     * Resolusi nama dan jabatan pejabat penandatangan.
     */
    protected function resolveSignerName(Document $document): string
    {
        if (filled($document->signer_name_snapshot)) {
            $name = $document->signer_name_snapshot;
            if (filled($document->signer_position_snapshot)) {
                $name .= ' ('.$document->signer_position_snapshot.')';
            }

            return $name;
        }

        if ($document->finalApprover) {
            return $document->finalApprover->name;
        }

        return 'Pimpinan Universitas Bina Insan';
    }

    /**
     * Sanitasi teks string Latin-1 agar aman dirender oleh FPDF tanpa kesalahan encoding.
     */
    protected function sanitizePdfText(string $text): string
    {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }
}
