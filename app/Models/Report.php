<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'year',
        'month',
        'week_in_month',
        'exported_at',
        'exported_by',
    ];

    protected $casts = [
        'exported_at' => 'datetime',
    ];

    public function exporter()
    {
        return $this->belongsTo(User::class, 'exported_by', 'username');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
