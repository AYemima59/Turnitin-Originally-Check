<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonSource extends Model
{
    protected $fillable = ['title', 'source_type', 'url', 'content', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
