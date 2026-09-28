<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadImportRequest;
use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\Lead;
use App\Services\FailedRecordService;
use App\Services\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LeadImportController extends Controller
{
    protected LeadImportService $importService;
    protected FailedRecordService $failedRecordService;

    public function __construct(LeadImportService $importService, FailedRecordService $failedRecordService)
    {
        $this->importService = $importService;
        $this->failedRecordService = $failedRecordService;
    }

    /**
     * Upload and initiate asynchronous CSV import.
     * Returns HTTP 202 Accepted immediately.
     */
    public function store(LeadImportRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $notificationEmail = $request->input('notification_email');
            $userId = $request->user()?->id;

            $import = $this->importService->storeAndDispatch(
                $file,
                $notificationEmail,
                $userId
            );

            return response()->json([
                'message' => 'CSV file accepted. Background processing has started.',
                'import' => $this->importService->getStatusData($import)], 202);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LeadImportController@store error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Import could not be started. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time status and progress of an import.
     */
    public function show(int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json([
                'message' => 'Import record not found.',
            ], 404);
        }

        return response()->json($this->importService->getStatusData($import));
    }

    /**
     * List previous imports with metrics and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $imports = Import::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $imports->items(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    /**
     * Download the generated failed_records.csv for an import.
     */
    public function downloadFailedCsv(int $id): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Import record not found.'], 404);
        }

        if ($import->failed_count === 0) {
            return response()->json(['message' => 'This import has zero failed records.'], 404);
        }

        // If file not yet generated or missing, generate it on demand
        if (!$import->failed_csv_path || !Storage::disk('local')->exists($import->failed_csv_path)) {
            $this->failedRecordService->generateFailedCsv($import);
            $import->refresh();
        }

        if (!$import->failed_csv_path || !Storage::disk('local')->exists($import->failed_csv_path)) {
            return response()->json(['message' => 'Failed records CSV file is not available.'], 404);
        }

        $fullPath = Storage::disk('local')->path($import->failed_csv_path);
        $downloadFilename = "failed_records_import_{$import->id}.csv";

        return response()->download($fullPath, $downloadFilename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$downloadFilename}\"",
        ]);
    }

    /**
     * Preview recent failures for an import (paginated).
     */
    public function failures(Request $request, int $id): JsonResponse
    {
        $import = Import::find($id);

        if (!$import) {
            return response()->json(['message' => 'Import not found.'], 404);
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));

        $failures = ImportFailure::where('import_id', $import->id)
            ->orderBy('row_number')
            ->paginate($perPage);

        return response()->json($failures);
    }

    /**
     * Explorer: List stored leads with search and pagination.
     */
    public function leads(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));

        $query = Lead::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $leads->items(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }
}
