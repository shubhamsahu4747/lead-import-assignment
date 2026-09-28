<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\FailedRecordService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFailedRecords implements ShouldQueue
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
            'generate-failed-csv',
        ];
    }

    public function handle(FailedRecordService $failedRecordService): void
    {
        $import = Import::find($this->importId);
        if (!$import) {
            return;
        }

        $failedRecordService->generateFailedCsv($import);
    }
}
