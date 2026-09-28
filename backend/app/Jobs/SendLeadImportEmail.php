<?php

namespace App\Jobs;

use App\Mail\LeadImportCompleted;
use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendLeadImportEmail implements ShouldQueue
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
            'email-notification',
        ];
    }

    public function handle(): void
    {
        $import = Import::with('user')->find($this->importId);
        if (!$import) {
            return;
        }

        $recipientEmail = $import->notification_email ?? $import->user?->email;
        if (!$recipientEmail) {
            return;
        }

        Mail::to($recipientEmail)->send(new LeadImportCompleted($import));
    }
}
