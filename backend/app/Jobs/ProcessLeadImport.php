<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Throwable;

class ProcessLeadImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function tags(): array
    {
        return [
            'import:' . $this->importId,
            'orchestrator',
        ];
    }

    public function handle(): void
    {
        $import = Import::find($this->importId);
        if (!$import) {
            return;
        }

        try {
            $import->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $fullPath = Storage::disk('local')->path($import->file_path);
            if (!file_exists($fullPath)) {
                throw new \RuntimeException("CSV file does not exist at path: {$fullPath}");
            }

            // Stream CSV without loading whole file into memory
            $reader = Reader::createFromPath($fullPath, 'r');
            $reader->setHeaderOffset(0);

            $batchSize = (int) env('CSV_BATCH_SIZE', 5000);
            if ($batchSize < 100) {
                $batchSize = 5000;
            }

            $records = $reader->getRecords();
            $chunk = [];
            $chunkIndex = 1;
            $startRowNumber = 2; // Row 1 is header, data starts at line 2
            $chunkJobs = [];

            foreach ($records as $record) {
                $chunk[] = $record;

                if (count($chunk) >= $batchSize) {
                    $chunkJobs[] = new ProcessLeadChunk(
                        $import->id,
                        $chunkIndex,
                        $startRowNumber,
                        $chunk
                    );

                    $startRowNumber += count($chunk);
                    $chunkIndex++;
                    $chunk = [];
                }
            }

            // Flush remaining rows
            if (!empty($chunk)) {
                $chunkJobs[] = new ProcessLeadChunk(
                    $import->id,
                    $chunkIndex,
                    $startRowNumber,
                    $chunk
                );
            }

            if (empty($chunkJobs)) {
                $import->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                return;
            }

            $importId = $this->importId;

            // Dispatch as a resilient, monitored Laravel job batch
            Bus::batch($chunkJobs)
                ->name("lead-import-{$import->id}")
                ->allowFailures()
                ->then(function (Batch $batch) use ($importId) {
                    Import::where('id', $importId)->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    // Generate failed records CSV
                    dispatch(new GenerateFailedRecords($importId));

                    // Send completion notification email
                    dispatch(new SendLeadImportEmail($importId));
                })
                ->catch(function (Batch $batch, Throwable $e) use ($importId) {
                    Log::error("Batch import #{$importId} encountered catastrophic error: " . $e->getMessage());

                    Import::where('id', $importId)->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => now(),
                    ]);
                })
                ->dispatch();

        } catch (Throwable $e) {
            Log::error("ProcessLeadImport failed for import #{$this->importId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
