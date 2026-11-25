<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = [
        'user_id', 'title', 'original_filename', 'file_type', 'status',
        'similarity_score', 'matched_words', 'total_words', 'extracted_text',
        'no_repository', 'report_data', 'completed_at',
    ];

    protected $casts = [
        'no_repository' => 'boolean',
        'report_data' => 'array',
        'completed_at' => 'datetime',
        'similarity_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
