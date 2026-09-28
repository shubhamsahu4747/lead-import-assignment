<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_number',
        'name',
        'email',
        'phone',
        'company',
        'reason',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'import_id' => 'integer',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_id');
    }
}
