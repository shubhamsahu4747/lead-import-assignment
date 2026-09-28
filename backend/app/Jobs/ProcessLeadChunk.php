<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\Lead;
use App\Services\LeadValidationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProcessLeadChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public int $importId;
    public int $batchNumber;
    public int $startRowNumber;
    public array $rows;

    /**
     * Create a new job instance.
     *
     * @param int $importId
     * @param int $batchNumber
     * @param int $startRowNumber
     * @param array $rows
     */
    public function __construct(int $importId, int $batchNumber, int $startRowNumber, array $rows)
    {
        $this->importId = $importId;
        $this->batchNumber = $batchNumber;
        $this->startRowNumber = $startRowNumber;
        $this->rows = $rows;
    }

    /**
     * Tags for Laravel Horizon monitoring.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'import:' . $this->importId,
            'chunk:' . $this->batchNumber,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(LeadValidationService $validator): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        // 1. Idempotency Check: Prevent duplicate batch execution
        $idempotencyKey = "import_{$this->importId}_batch_{$this->batchNumber}_completed";
        if (Cache::has($idempotencyKey)) {
            return;
        }

        $now = now();
        $failuresToInsert = [];
        $candidates = [];
        $chunkEmails = [];
        $redisKey = "import_emails_{$this->importId}";

        // 2. Validate and detect intra-batch duplicates
        foreach ($this->rows as $index => $rawRow) {
            $rowNumber = $this->startRowNumber + $index;
            $normalized = $validator->normalize($rawRow);
            $validation = $validator->validate($normalized);

            if (!$validation['is_valid']) {
                $failuresToInsert[] = [
                    'import_id' => $this->importId,
                    'row_number' => $rowNumber,
                    'name' => $normalized['name'] ?: null,
                    'email' => $normalized['email'] ?: null,
                    'phone' => $normalized['phone'] ?: null,
                    'company' => $normalized['company'] ?: null,
                    'reason' => $validation['reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                continue;
            }

            $email = $normalized['email'];

            // Check duplicate within the same chunk
            if (isset($chunkEmails[$email])) {
                $failuresToInsert[] = [
                    'import_id' => $this->importId,
                    'row_number' => $rowNumber,
                    'name' => $normalized['name'],
                    'email' => $normalized['email'],
                    'phone' => $normalized['phone'],
                    'company' => $normalized['company'],
                    'reason' => 'Duplicate email in current import',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                continue;
            }

            // Check duplicate across previous chunks in the same import using Redis Set
            $isDuplicateInImport = false;
            try {
                // Redis SADD returns 1 if added, 0 if member already exists
                $added = Redis::sadd($redisKey, $email);
                if ($added === 0) {
                    $isDuplicateInImport = true;
                }
            } catch (\Throwable $e) {
                // If Redis is unreachable, fallback to in-memory check
                $isDuplicateInImport = false;
            }

            if ($isDuplicateInImport) {
                $failuresToInsert[] = [
                    'import_id' => $this->importId,
                    'row_number' => $rowNumber,
                    'name' => $normalized['name'],
                    'email' => $normalized['email'],
                    'phone' => $normalized['phone'],
                    'company' => $normalized['company'],
                    'reason' => 'Duplicate email in current import',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                continue;
            }

            $chunkEmails[$email] = true;
            $candidates[] = [
                'row_number' => $rowNumber,
                'data' => $normalized,
            ];
        }

        // 3. Check duplicates against existing database records using a SINGLE WHERE IN query
        $leadsToInsert = [];
        if (!empty($candidates)) {
            $candidateEmails = array_column(array_column($candidates, 'data'), 'email');

            $existingEmails = DB::table('leads')
                ->whereIn('email', $candidateEmails)
                ->pluck('email')
                ->flip()
                ->toArray();

            foreach ($candidates as $candidate) {
                $email = $candidate['data']['email'];

                if (isset($existingEmails[$email])) {
                    $failuresToInsert[] = [
                        'import_id' => $this->importId,
                        'row_number' => $candidate['row_number'],
                        'name' => $candidate['data']['name'],
                        'email' => $candidate['data']['email'],
                        'phone' => $candidate['data']['phone'],
                        'company' => $candidate['data']['company'],
                        'reason' => 'Duplicate email already exists',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } else {
                    $leadsToInsert[] = [
                        'name' => $candidate['data']['name'],
                        'email' => $candidate['data']['email'],
                        'phone' => $candidate['data']['phone'],
                        'company' => $candidate['data']['company'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // 4. Perform bulk database operations inside a transaction
        DB::transaction(function () use ($leadsToInsert, $failuresToInsert) {
            // Bulk insert valid leads in sub-batches of 1,000 to respect max_allowed_packet
            if (!empty($leadsToInsert)) {
                foreach (array_chunk($leadsToInsert, 1000) as $leadSubChunk) {
                    Lead::insertOrIgnore($leadSubChunk);
                }
            }

            // Bulk insert failures in sub-batches of 1,000
            if (!empty($failuresToInsert)) {
                foreach (array_chunk($failuresToInsert, 1000) as $failureSubChunk) {
                    ImportFailure::insert($failureSubChunk);
                }
            }

            // 5. Update atomic progress counters on imports table
            $processedCount = count($this->rows);
            $successCount = count($leadsToInsert);
            $failedCount = count($failuresToInsert);

            DB::table('imports')
                ->where('id', $this->importId)
                ->incrementEach([
                    'processed_records' => $processedCount,
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                ]);
        });

        // 6. Record idempotency key for 48 hours
        Cache::put($idempotencyKey, true, now()->addDays(2));
    }
}
