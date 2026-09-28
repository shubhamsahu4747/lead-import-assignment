<?php

namespace App\Services;

use App\Jobs\ProcessLeadImport;
use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader;

class LeadImportService
{
    /**
     * Required CSV columns for lead import.
     */
    protected const REQUIRED_COLUMNS = ['name', 'email', 'phone', 'company'];

    /**
     * Store the uploaded CSV, validate headers, count rows efficiently, and dispatch async job.
     *
     * @param UploadedFile $file
     * @param string|null $notificationEmail
     * @param int|null $userId
     * @return Import
     * @throws ValidationException
     */
    public function storeAndDispatch(UploadedFile $file, ?string $notificationEmail = null, ?int $userId = null): Import
    {
        $originalFilename = $file->getClientOriginalName();
        $fileSizeBytes = $file->getSize();

        // 1. Store the uploaded file in private storage
        $storedPath = $file->store('imports', 'local');
        if (!$storedPath) {
            throw ValidationException::withMessages([
                'file' => 'Failed to store the uploaded CSV file.',
            ]);
        }

        $fullPath = Storage::disk('local')->path($storedPath);

        // 2. Validate CSV structure and headers using league/csv
        try {
            $reader = Reader::createFromPath($fullPath, 'r');
            $reader->setHeaderOffset(0);

            $headers = $reader->getHeader();
            if (empty($headers)) {
                Storage::disk('local')->delete($storedPath);
                throw ValidationException::withMessages([
                    'file' => 'The uploaded CSV file has no header row.',
                ]);
            }

            // Lowercase and trim headers for validation
            $normalizedHeaders = array_map(fn($h) => strtolower(trim((string) $h)), $headers);
            $missingColumns = [];

            foreach (self::REQUIRED_COLUMNS as $required) {
                if (!in_array($required, $normalizedHeaders, true)) {
                    $missingColumns[] = $required;
                }
            }

            if (!empty($missingColumns)) {
                Storage::disk('local')->delete($storedPath);
                throw ValidationException::withMessages([
                    'file' => 'CSV is missing required header(s): ' . implode(', ', $missingColumns) . '. Required columns are: name, email, phone, company.',
                ]);
            }

            // 3. Fast streaming row count without loading all records into memory
            $totalRecords = 0;
            $records = $reader->getRecords();
            foreach ($records as $record) {
                $totalRecords++;
            }

            if ($totalRecords === 0) {
                Storage::disk('local')->delete($storedPath);
                throw ValidationException::withMessages([
                    'file' => 'The uploaded CSV file contains no data rows.',
                ]);
            }

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);
            throw ValidationException::withMessages([
                'file' => 'Invalid CSV format: ' . $e->getMessage(),
            ]);
        }

        // 4. Create Import record
        $import = Import::create([
            'user_id' => $userId,
            'notification_email' => $notificationEmail,
            'original_filename' => $originalFilename,
            'file_path' => $storedPath,
            'file_size_bytes' => $fileSizeBytes,
            'total_records' => $totalRecords,
            'processed_records' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'status' => 'pending',
            'field_mapping' => [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'company' => 'company',
            ],
        ]);

        // 5. Dispatch async queue job
        ProcessLeadImport::dispatch($import->id);

        return $import;
    }

    /**
     * Get detailed status of an import including live progress percentage.
     *
     * @param Import $import
     * @return array
     */
    public function getStatusData(Import $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'original_filename' => $import->original_filename,
            'total_records' => $import->total_records,
            'processed_records' => $import->processed_records,
            'success_count' => $import->success_count,
            'failed_count' => $import->failed_count,
            'progress' => $import->progress,
            'has_failed_csv' => !empty($import->failed_csv_path) && Storage::disk('local')->exists($import->failed_csv_path),
            'started_at' => $import->started_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
            'error_message' => $import->error_message,
        ];
    }
}
