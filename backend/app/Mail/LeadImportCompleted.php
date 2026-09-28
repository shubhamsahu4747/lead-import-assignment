<?php

namespace App\Mail;

use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class LeadImportCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public Import $import;
    public bool $hasAttachment = false;

    /**
     * Create a new message instance.
     */
    public function __construct(Import $import)
    {
        $this->import = $import;

        if ($import->failed_csv_path && Storage::disk('local')->exists($import->failed_csv_path)) {
            $maxBytes = config('app.failed_csv_attachment_max_bytes', 5242880);
            $size = Storage::disk('local')->size($import->failed_csv_path);
            $this->hasAttachment = ($size <= $maxBytes);
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Lead Import #{$this->import->id} Completed: {$this->import->original_filename}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.import_completed',
            with: [
                'import' => $this->import,
                'hasAttachment' => $this->hasAttachment,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if ($this->hasAttachment && $this->import->failed_csv_path) {
            $path = Storage::disk('local')->path($this->import->failed_csv_path);
            if (file_exists($path)) {
                return [
                    Attachment::fromPath($path)
                        ->as('failed_records.csv')
                        ->withMime('text/csv'),
                ];
            }
        }

        return [];
    }
}
