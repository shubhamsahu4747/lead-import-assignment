<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lead Import Completed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; padding: 24px; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { border-bottom: 2px solid #3b82f6; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
        .meta { color: #64748b; font-size: 14px; margin-top: 4px; }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 24px 0; }
        .stat-card { background: #f1f5f9; padding: 16px; border-radius: 6px; text-align: center; }
        .stat-val { font-size: 22px; font-weight: 700; color: #0f172a; }
        .stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; }
        .stat-success .stat-val { color: #16a34a; }
        .stat-failed .stat-val { color: #dc2626; }
        .footer { font-size: 12px; color: #94a3b8; margin-top: 32px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 16px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #15803d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lead Import Completed</h1>
            <div class="meta">File: <strong>{{ $import->original_filename }}</strong> (Import #{{ $import->id }})</div>
        </div>

        <p>Hello,</p>
        <p>Your asynchronous lead import has finished processing. Here is the operational summary:</p>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-val">{{ number_format($import->total_records) }}</div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-val">{{ number_format($import->success_count) }}</div>
                <div class="stat-label">Successful</div>
            </div>
            <div class="stat-card stat-failed">
                <div class="stat-val">{{ number_format($import->failed_count) }}</div>
                <div class="stat-label">Failed</div>
            </div>
        </div>

        @if($import->failed_count > 0)
            <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0; color: #991b1b; font-size: 14px;">
                    <strong>{{ number_format($import->failed_count) }} records</strong> could not be imported due to validation errors or duplicate emails.
                    @if($hasAttachment)
                        The detailed <code>failed_records.csv</code> error report is attached to this email.
                    @else
                        The error report exceeds the email attachment size threshold and can be downloaded directly from your web dashboard.
                    @endif
                </p>
            </div>
        @else
            <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px 16px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0; color: #166534; font-size: 14px;">
                    All records passed validation and were successfully stored!
                </p>
            </div>
        @endif

        <p style="font-size: 13px; color: #64748b;">
            Started: {{ $import->started_at?->format('Y-m-d H:i:s T') ?? 'N/A' }}<br>
            Completed: {{ $import->completed_at?->format('Y-m-d H:i:s T') ?? 'N/A' }}
        </p>

        <div class="footer">
            Automated notification from Lead Import Processing System.
        </div>
    </div>
</body>
</html>
