<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLeadChunk;
use App\Models\Import;
use App\Models\Lead;
use App\Services\FailedRecordService;
use App\Services\LeadValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use League\Csv\Reader;

class BenchmarkLeadImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:benchmark {count=10000 : Number of records (e.g. 10000, 100000)} {--batch-size=5000 : Batch size for processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an actual end-to-end performance benchmark on a generated CSV file';

    /**
     * Execute the console command.
     */
    public function handle(LeadValidationService $validator, FailedRecordService $failedRecordService): int
    {
        $count = (int) $this->argument('count');
        $batchSize = (int) $this->option('batch-size');

        $this->info("========================================================");
        $this->info(" Large CSV Lead Import - Performance Benchmark");
        $this->info("========================================================");
        $this->info(" Target records : " . number_format($count));
        $this->info(" Batch size     : " . number_format($batchSize));

        // 1. Ensure test CSV exists, or generate it
        $csvPath = base_path("storage/testing/leads-benchmark-{$count}.csv");
        if (!file_exists($csvPath)) {
            $this->info(" Generating test CSV with 5% duplicates and 5% errors...");
            $scriptPath = base_path('../scripts/generate-leads.php');
            passthru(sprintf('php %s %d --output=%s', escapeshellarg($scriptPath), $count, escapeshellarg($csvPath)));
        }

        if (!file_exists($csvPath)) {
            $this->error("Failed to locate or generate CSV at {$csvPath}");
            return 1;
        }

        $fileSizeMb = round(filesize($csvPath) / (1024 * 1024), 2);
        $this->line(" CSV File Size  : {$fileSizeMb} MB");

        // 2. Initialize Import record
        $import = Import::create([
            'original_filename' => basename($csvPath),
            'file_path' => "testing/" . basename($csvPath),
            'file_size_bytes' => filesize($csvPath),
            'total_records' => $count,
            'processed_records' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            Redis::del("import_emails_{$import->id}");
        } catch (\Throwable $e) {}

        // Reset memory counter
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        $timeStart = microtime(true);

        $this->info(" Processing streamed CSV chunks...");

        $reader = Reader::createFromPath($csvPath, 'r');
        $reader->setHeaderOffset(0);

        $records = $reader->getRecords();
        $chunk = [];
        $chunkIndex = 1;
        $startRowNumber = 2;
        $totalChunks = 0;

        foreach ($records as $record) {
            $chunk[] = $record;
            if (count($chunk) >= $batchSize) {
                $job = new ProcessLeadChunk($import->id, $chunkIndex, $startRowNumber, $chunk);
                $job->handle($validator);

                $startRowNumber += count($chunk);
                $chunkIndex++;
                $totalChunks++;
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $job = new ProcessLeadChunk($import->id, $chunkIndex, $startRowNumber, $chunk);
            $job->handle($validator);
            $totalChunks++;
        }

        $import->refresh();

        // Generate failed records CSV
        $failedRecordService->generateFailedCsv($import);

        $import->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $elapsed = microtime(true) - $timeStart;
        $peakMem = memory_get_peak_usage(true) - $memStart;
        $peakMemMb = round(max(0, $peakMem) / (1024 * 1024), 2);
        $totalSystemPeakMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        $throughput = round($import->processed_records / max(0.001, $elapsed));

        $this->info("========================================================");
        $this->info(" Benchmark Results");
        $this->info("========================================================");
        $this->line(sprintf(" Total Time        : %s seconds", number_format($elapsed, 3)));
        $this->line(sprintf(" Throughput        : %s records/sec", number_format($throughput)));
        $this->line(sprintf(" Peak PHP Memory   : %s MB", $totalSystemPeakMb));
        $this->line(sprintf(" Total Records     : %s", number_format($import->total_records)));
        $this->line(sprintf(" Processed Records : %s", number_format($import->processed_records)));
        $this->line(sprintf(" Successful Leads  : %s", number_format($import->success_count)));
        $this->line(sprintf(" Failed Records    : %s", number_format($import->failed_count)));
        $this->line(sprintf(" Chunks Executed   : %d", $totalChunks));
        $this->line(sprintf(" Failed CSV Path   : %s", $import->failed_csv_path ?? 'None'));
        $this->info("========================================================");

        return 0;
    }
}
