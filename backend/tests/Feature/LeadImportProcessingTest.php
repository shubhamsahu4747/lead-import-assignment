<?php

namespace Tests\Feature;

use App\Jobs\GenerateFailedRecords;
use App\Jobs\ProcessLeadChunk;
use App\Jobs\SendLeadImportEmail;
use App\Mail\LeadImportCompleted;
use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\Lead;
use App\Services\FailedRecordService;
use App\Services\LeadValidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportProcessingTest extends TestCase
{
    use DatabaseTransactions;

    protected Import $import;
    protected LeadValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new LeadValidationService();

        $this->import = Import::create([
            'original_filename' => 'leads_test.csv',
            'file_path' => 'imports/leads_test.csv',
            'notification_email' => 'notify@test.com',
            'total_records' => 5,
            'processed_records' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'status' => 'processing',
        ]);

        try {
            Redis::del("import_emails_{$this->import->id}");
        } catch (\Throwable $e) {
            // Redis fallback
        }
    }

    public function test_chunk_job_inserts_valid_leads_and_records_failures(): void
    {
        // Pre-insert an existing lead to test existing duplicate detection
        Lead::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'phone' => '+1-555-0001',
            'company' => 'Old Corp',
        ]);

        $rows = [
            // Row 1: Valid new lead
            [
                'name' => 'Alice Valid',
                'email' => 'alice@example.com',
                'phone' => '+1-555-1111',
                'company' => 'Acme Corp',
            ],
            // Row 2: Duplicate email in current import
            [
                'name' => 'Alice Duplicate',
                'email' => 'alice@example.com',
                'phone' => '+1-555-2222',
                'company' => 'Beta Corp',
            ],
            // Row 3: Duplicate already existing in MySQL
            [
                'name' => 'Existing Copy',
                'email' => 'existing@example.com',
                'phone' => '+1-555-3333',
                'company' => 'Gamma Corp',
            ],
            // Row 4: Invalid email format
            [
                'name' => 'Invalid Email Guy',
                'email' => 'bad-email-format',
                'phone' => '+1-555-4444',
                'company' => 'Delta Corp',
            ],
            // Row 5: Missing company
            [
                'name' => 'Missing Company Guy',
                'email' => 'valid.email@example.com',
                'phone' => '+1-555-5555',
                'company' => '',
            ],
        ];

        $job = new ProcessLeadChunk($this->import->id, batchNumber: 1, startRowNumber: 2, rows: $rows);
        $job->handle($this->validator);

        // Assert 1 valid lead inserted
        $this->assertDatabaseHas('leads', [
            'name' => 'Alice Valid',
            'email' => 'alice@example.com',
        ]);

        // Assert import counters atomically updated
        $this->import->refresh();
        $this->assertSame(5, $this->import->processed_records);
        $this->assertSame(1, $this->import->success_count);
        $this->assertSame(4, $this->import->failed_count);

        // Assert failure records with exact failure reasons
        $failures = ImportFailure::where('import_id', $this->import->id)
            ->orderBy('row_number')
            ->get();

        $this->assertCount(4, $failures);

        // Row 3 (2nd in CSV): Duplicate in current import
        $this->assertSame(3, $failures[0]->row_number);
        $this->assertSame('Duplicate email in current import', $failures[0]->reason);

        // Row 4 (3rd in CSV): Duplicate already exists in DB
        $this->assertSame(4, $failures[1]->row_number);
        $this->assertSame('Duplicate email already exists', $failures[1]->reason);

        // Row 5 (4th in CSV): Invalid email format
        $this->assertSame(5, $failures[2]->row_number);
        $this->assertSame('Invalid email format', $failures[2]->reason);

        // Row 6 (5th in CSV): Missing company
        $this->assertSame(6, $failures[3]->row_number);
        $this->assertSame('Missing company', $failures[3]->reason);
    }

    public function test_batch_idempotency_prevents_duplicate_processing(): void
    {
        $rows = [
            [
                'name' => 'Idempotent Test',
                'email' => 'idempotent@example.com',
                'phone' => '+1-555-9999',
                'company' => 'Safe Corp',
            ],
        ];

        $job = new ProcessLeadChunk($this->import->id, batchNumber: 99, startRowNumber: 2, rows: $rows);

        // First execution
        $job->handle($this->validator);
        $this->import->refresh();
        $this->assertSame(1, $this->import->processed_records);
        $this->assertSame(1, $this->import->success_count);

        // Second duplicate execution (simulating network retry of same batch)
        $job->handle($this->validator);
        $this->import->refresh();
        // Numbers must not be double incremented
        $this->assertSame(1, $this->import->processed_records);
        $this->assertSame(1, $this->import->success_count);
    }

    public function test_generates_failed_records_csv_and_sends_email(): void
    {
        Mail::fake();

        // Create failure records
        ImportFailure::create([
            'import_id' => $this->import->id,
            'row_number' => 2,
            'name' => 'Fail Lead',
            'email' => 'fail@example.com',
            'phone' => '+1-555-0000',
            'company' => 'Fail Corp',
            'reason' => 'Invalid email format',
        ]);

        $this->import->update([
            'failed_count' => 1,
            'status' => 'completed',
        ]);

        // Generate failed CSV
        $generatorJob = new GenerateFailedRecords($this->import->id);
        $generatorJob->handle(new FailedRecordService());

        $this->import->refresh();
        $this->assertNotNull($this->import->failed_csv_path);
        $this->assertTrue(Storage::disk('local')->exists($this->import->failed_csv_path));

        // Send email
        $emailJob = new SendLeadImportEmail($this->import->id);
        $emailJob->handle();

        Mail::assertSent(LeadImportCompleted::class, function ($mail) {
            return $mail->hasTo('notify@test.com') && $mail->import->id === $this->import->id;
        });
    }
}
