<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BorangPublicViewerController extends Controller
{
    /**
     * Display the public assessor document viewer or locked notice.
     */
    public function show(Request $request, string $uuid): Response|RedirectResponse
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->where('is_borang', true)
            ->where('status', DocumentStatus::Approved)
            ->with(['unit', 'category', 'academicYear', 'accreditationCriteria', 'primaryFile'])
            ->firstOrFail();

        $unit = $document->unit;

        // 1. Check if assessor access is open for this unit/prodi
        if (! $unit || ! $unit->isAssessorAccessOpen()) {
            return response()->view('public.borang-locked', [
                'document' => $document,
                'unit' => $unit,
                'status' => $unit?->getAssessorAccessStatus() ?? 'inactive',
            ], 403);
        }

        // 2. Check if unit requires a passcode
        if ($unit->assessor_passcode) {
            $sessionKey = 'assessor_auth_unit_'.$unit->id;

            if ($request->isMethod('post')) {
                $enteredPasscode = (string) $request->input('passcode');
                if (hash_equals($unit->assessor_passcode, $enteredPasscode)) {
                    session([$sessionKey => true]);

                    return redirect()->route('borang.public.show', ['uuid' => $document->uuid]);
                }

                return back()->withErrors(['passcode' => 'PIN / Sandi akses salah. Silakan periksa kembali lembar pengantar LKPS Anda.']);
            }

            if (! session($sessionKey)) {
                return response()->view('public.borang-passcode', [
                    'document' => $document,
                    'unit' => $unit,
                ]);
            }
        }

        // Increment view counter for high-scale analytics
        $document->increment('views_count');

        return response()->view('public.borang-viewer', [
            'document' => $document,
            'unit' => $unit,
        ]);
    }

    /**
     * Display the comprehensive assessor portal for an entire study program / unit.
     */
    public function showUnitPortal(Request $request, string $unitCode): Response|RedirectResponse
    {
        $unit = Unit::query()
            ->where('code', $unitCode)
            ->firstOrFail();

        // 1. Check if assessor access is open for this unit/prodi
        if (! $unit->isAssessorAccessOpen()) {
            return response()->view('public.borang-locked', [
                'unit' => $unit,
                'status' => $unit->getAssessorAccessStatus(),
            ], 403);
        }

        // 2. Check if unit requires a passcode
        if ($unit->assessor_passcode) {
            $sessionKey = 'assessor_auth_unit_'.$unit->id;

            if ($request->isMethod('post')) {
                $enteredPasscode = (string) $request->input('passcode');
                if (hash_equals($unit->assessor_passcode, $enteredPasscode)) {
                    session([$sessionKey => true]);

                    return redirect()->route('borang.public.unit', ['unitCode' => $unit->code]);
                }

                return back()->withErrors(['passcode' => 'PIN / Sandi akses salah. Silakan periksa kembali lembar pengantar LKPS Anda.']);
            }

            if (! session($sessionKey)) {
                return response()->view('public.borang-passcode', [
                    'unit' => $unit,
                    'postAction' => route('borang.public.unit', ['unitCode' => $unit->code]),
                ]);
            }
        }

        // 3. Aggregate totals for criteria pills and academic year filter across the entire unit
        $baseUnitBorangQuery = Document::query()
            ->where('documents.unit_id', $unit->id)
            ->where('documents.is_borang', true)
            ->where('documents.status', DocumentStatus::Approved);

        $totalUnitDocuments = (clone $baseUnitBorangQuery)->count();

        $search = trim((string) $request->query('q', ''));
        $selectedCriterion = trim((string) $request->query('criterion', ''));
        $selectedYearId = $request->filled('year') ? (int) $request->query('year') : null;
        $activeDocUuid = trim((string) $request->query('doc', ''));
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $criteriaSummary = DB::table('criterion_document')
            ->join('accreditation_criteria', 'accreditation_criteria.id', '=', 'criterion_document.accreditation_criterion_id')
            ->join('documents', 'documents.id', '=', 'criterion_document.document_id')
            ->where('documents.unit_id', $unit->id)
            ->where('documents.is_borang', true)
            ->where('documents.status', DocumentStatus::Approved->value)
            ->whereNull('documents.deleted_at')
            ->when($selectedYearId !== null && $selectedYearId > 0, fn ($q) => $q->where('documents.academic_year_id', $selectedYearId))
            ->groupBy('accreditation_criteria.id', 'accreditation_criteria.code', 'accreditation_criteria.name', 'accreditation_criteria.order_index')
            ->orderBy('accreditation_criteria.order_index')
            ->select([
                'accreditation_criteria.code',
                'accreditation_criteria.name',
                DB::raw('COUNT(DISTINCT criterion_document.document_id) as doc_count'),
            ])
            ->get();

        $totalFilteredYearDocuments = ($selectedYearId !== null && $selectedYearId > 0)
            ? (clone $baseUnitBorangQuery)->where('documents.academic_year_id', $selectedYearId)->count()
            : $totalUnitDocuments;

        $availableYears = AcademicYear::query()
            ->whereIn('id', function ($sub) use ($unit): void {
                $sub->select('academic_year_id')
                    ->from('documents')
                    ->where('unit_id', $unit->id)
                    ->where('is_borang', true)
                    ->where('status', DocumentStatus::Approved->value)
                    ->whereNull('deleted_at')
                    ->whereNotNull('academic_year_id')
                    ->distinct();
            })
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        // 4. Paginate sidebar list cleanly (25 items/page) using lean column projection
        $filteredQuery = (clone $baseUnitBorangQuery)
            ->when($selectedCriterion !== '' && $selectedCriterion !== 'all', function (Builder $query) use ($selectedCriterion): void {
                $query->whereHas('accreditationCriteria', function (Builder $criterionQuery) use ($selectedCriterion): void {
                    $criterionQuery->where('code', $selectedCriterion);
                });
            })
            ->when($selectedYearId !== null && $selectedYearId > 0, fn (Builder $query): Builder => $query->where('documents.academic_year_id', $selectedYearId))
            ->when($search !== '', function (Builder $query) use ($search, $likeOperator): void {
                $query->where(function (Builder $sub) use ($search, $likeOperator): void {
                    $sub->where('documents.title', $likeOperator, "%{$search}%")
                        ->orWhere('documents.document_number', $likeOperator, "%{$search}%");
                });
            });

        $documents = (clone $filteredQuery)
            ->select([
                'documents.id',
                'documents.uuid',
                'documents.title',
                'documents.document_number',
                'documents.signer_name_snapshot',
                'documents.issued_date',
                'documents.academic_year_id',
            ])
            ->with([
                'accreditationCriteria:id,code,name',
                'academicYear:id,name',
            ])
            ->orderBy('documents.title')
            ->simplePaginate(25)
            ->withQueryString();

        // 5. Fetch the single active document separately with its primaryFile
        $activeDocument = null;
        if ($activeDocUuid !== '') {
            $activeDocument = (clone $baseUnitBorangQuery)
                ->where('documents.uuid', $activeDocUuid)
                ->with(['accreditationCriteria:id,code,name', 'academicYear:id,name', 'primaryFile'])
                ->first();
        }

        if (! $activeDocument && $documents->isNotEmpty()) {
            $firstDoc = $documents->first();
            if ($firstDoc) {
                $activeDocument = Document::query()
                    ->where('id', $firstDoc->id)
                    ->with(['accreditationCriteria:id,code,name', 'academicYear:id,name', 'primaryFile'])
                    ->first();
            }
        }

        if ($activeDocument) {
            $activeDocument->increment('views_count');
        }

        return response()->view('public.borang-unit-portal', [
            'unit' => $unit,
            'documents' => $documents,
            'totalUnitDocuments' => $totalUnitDocuments,
            'totalFilteredYearDocuments' => $totalFilteredYearDocuments,
            'criteriaSummary' => $criteriaSummary,
            'availableYears' => $availableYears,
            'selectedCriterion' => $selectedCriterion,
            'selectedYearId' => $selectedYearId,
            'search' => $search,
            'activeDocument' => $activeDocument,
        ]);
    }

    /**
     * Increment view counter asynchronously when switching active documents client-side.
     */
    public function trackUnitView(Request $request, string $unitCode, string $uuid): JsonResponse
    {
        $unit = Unit::query()
            ->where('code', $unitCode)
            ->firstOrFail();

        if (! $unit->isAssessorAccessOpen()) {
            abort(403, 'Masa akses penilaian akreditasi tidak aktif.');
        }

        if ($unit->assessor_passcode && ! session('assessor_auth_unit_'.$unit->id)) {
            abort(403, 'Otentikasi sandi asesor diperlukan.');
        }

        Document::query()
            ->where('unit_id', $unit->id)
            ->where('is_borang', true)
            ->where('status', DocumentStatus::Approved)
            ->where('uuid', $uuid)
            ->increment('views_count');

        return response()->json(['ok' => true]);
    }

    /**
     * Stream the physical PDF document file to the assessor browser.
     */
    public function streamFile(Request $request, string $uuid): BinaryFileResponse|Response
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->where('is_borang', true)
            ->where('status', DocumentStatus::Approved)
            ->with(['unit', 'primaryFile'])
            ->firstOrFail();

        $unit = $document->unit;

        if (! $unit || ! $unit->isAssessorAccessOpen()) {
            abort(403, 'Masa akses penilaian akreditasi tidak aktif.');
        }

        if ($unit->assessor_passcode && ! session('assessor_auth_unit_'.$unit->id)) {
            abort(403, 'Otentikasi sandi asesor diperlukan.');
        }

        $fileRecord = $document->primaryFile;
        if (! $fileRecord) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan.');
        }

        $relativeFilePath = $fileRecord->stamped_file_path ?? $fileRecord->original_file_path;
        $disk = Storage::disk($fileRecord->disk->value);

        if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
            abort(404, 'File fisik tidak ditemukan pada media penyimpanan.');
        }

        $absolutePath = $disk->path($relativeFilePath);
        $mimeType = mime_content_type($absolutePath) ?: 'application/pdf';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($absolutePath).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Download the physical PDF document file directly.
     */
    public function downloadFile(Request $request, string $uuid): BinaryFileResponse|Response
    {
        $document = Document::query()
            ->where('uuid', $uuid)
            ->where('is_borang', true)
            ->where('status', DocumentStatus::Approved)
            ->with(['unit', 'primaryFile'])
            ->firstOrFail();

        $unit = $document->unit;

        if (! $unit || ! $unit->isAssessorAccessOpen()) {
            abort(403, 'Masa akses penilaian akreditasi tidak aktif.');
        }

        if ($unit->assessor_passcode && ! session('assessor_auth_unit_'.$unit->id)) {
            abort(403, 'Otentikasi sandi asesor diperlukan.');
        }

        $fileRecord = $document->primaryFile;
        if (! $fileRecord) {
            abort(404, 'Berkas fisik naskah dinas tidak ditemukan.');
        }

        $relativeFilePath = $fileRecord->stamped_file_path ?? $fileRecord->original_file_path;
        $disk = Storage::disk($fileRecord->disk->value);

        if (! $relativeFilePath || ! $disk->exists($relativeFilePath)) {
            abort(404, 'File fisik tidak ditemukan pada media penyimpanan.');
        }

        $absolutePath = $disk->path($relativeFilePath);
        $document->increment('downloads_count');

        $downloadFilename = str_replace(['/', '\\'], '-', $document->document_number ?? 'DOK').'.pdf';

        return response()->download($absolutePath, $downloadFilename, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
