<?php

namespace Tests\Feature;

use App\Jobs\ProcessLeadImport;
use App\Models\Import;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_can_upload_valid_csv_and_queue_import(): void
    {
        $csvContent = "name,email,phone,company\n"
                    . "John Doe,john@example.com,+1-555-0100,Acme Inc\n"
                    . "Jane Smith,jane@example.com,+1-555-0101,Beta LLC\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csvContent);

        $response = $this->postJson('/api/imports', [
            'file' => $file,
            'notification_email' => 'user@example.com',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'CSV file accepted. Background processing has started.')
            ->assertJsonStructure([
                'message',
                'import' => [
                    'id',
                    'status',
                    'original_filename',
                    'total_records',
                    'processed_records',
                    'success_count',
                    'failed_count',
                    'progress',
                ],
            ]);

        $importId = $response->json('import.id');
        $this->assertDatabaseHas('imports', [
            'id' => $importId,
            'original_filename' => 'leads.csv',
            'total_records' => 2,
            'notification_email' => 'user@example.com',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessLeadImport::class, function ($job) use ($importId) {
            return $job->importId === $importId;
        });
    }

    public function test_rejects_csv_with_missing_required_headers(): void
    {
        $csvContent = "name,email\nJohn Doe,john@example.com\n";
        $file = UploadedFile::fake()->createWithContent('invalid_headers.csv', $csvContent);

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_non_csv_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_can_retrieve_import_status_and_progress(): void
    {
        $import = Import::create([
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'total_records' => 1000,
            'processed_records' => 450,
            'success_count' => 400,
            'failed_count' => 50,
            'status' => 'processing',
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $import->id,
                'status' => 'processing',
                'total_records' => 1000,
                'processed_records' => 450,
                'success_count' => 400,
                'failed_count' => 50,
                'progress' => 45,
            ]);
    }

    public function test_can_list_import_history(): void
    {
        Import::create([
            'original_filename' => 'history1.csv',
            'file_path' => 'imports/h1.csv',
            'total_records' => 100,
            'processed_records' => 100,
            'success_count' => 95,
            'failed_count' => 5,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/imports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'original_filename',
                        'total_records',
                        'processed_records',
                        'success_count',
                        'failed_count',
                        'status',
                    ],
                ],
                'meta',
            ]);
    }
}
