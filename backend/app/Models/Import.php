<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_email',
        'original_filename',
        'file_path',
        'file_size_bytes',
        'total_records',
        'processed_records',
        'success_count',
        'failed_count',
        'status',
        'failed_csv_path',
        'field_mapping',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'field_mapping' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class, 'import_id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_records <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->processed_records / $this->total_records) * 100));
    }
}
