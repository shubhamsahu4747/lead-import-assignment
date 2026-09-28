<?php

use App\Http\Controllers\LeadImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Lead Import API Endpoints
Route::prefix('imports')->group(function () {
    Route::post('/', [LeadImportController::class, 'store']);
    Route::get('/', [LeadImportController::class, 'index']);
    Route::get('/{id}', [LeadImportController::class, 'show']);
    Route::get('/{id}/failed-csv', [LeadImportController::class, 'downloadFailedCsv']);
    Route::get('/{id}/failures', [LeadImportController::class, 'failures']);
});

// Stored Leads Explorer
Route::get('/leads', [LeadImportController::class, 'leads']);

// Direct Sample CSV Generator for UI convenience
Route::get('/sample-csv', function (Request $request) {
    $count = min(10000, max(10, (int) $request->input('count', 100)));
    $filename = "sample-leads-{$count}.csv";

    return new StreamedResponse(function () use ($count) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['name', 'email', 'phone', 'company'], ',', '"', '\\');

        // First add a known duplicate pair
        fputcsv($handle, ['Alice Smith', 'alice.smith@samplecorp.com', '+1-555-0100', 'SampleCorp'], ',', '"', '\\');
        fputcsv($handle, ['Alice Smith Duplicate', 'alice.smith@samplecorp.com', '+1-555-0100', 'SampleCorp'], ',', '"', '\\');

        // Add some deliberate invalid records
        fputcsv($handle, ['', 'missing.name@test.com', '+1-555-0101', 'Acme Inc'], ',', '"', '\\');
        fputcsv($handle, ['Bad Email User', 'not-a-valid-email', '+1-555-0102', 'Beta LLC'], ',', '"', '\\');
        fputcsv($handle, ['Missing Phone User', 'no.phone@test.com', '', 'Gamma Co'], ',', '"', '\\');
        fputcsv($handle, ['Missing Company User', 'no.company@test.com', '+1-555-0104', ''], ',', '"', '\\');

        // Fill remaining with valid records
        for ($i = 7; $i <= $count; $i++) {
            fputcsv($handle, [
                "Lead Person {$i}",
                "lead.person.{$i}@domain{$i}.com",
                "+1-555-" . sprintf("%04d", $i % 9999),
                "Enterprise Org " . ($i % 50),
            ], ',', '"', '\\');
        }

        fclose($handle);
    }, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
});
