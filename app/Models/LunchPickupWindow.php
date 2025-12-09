<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LunchPickupWindow extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday'];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function getStartTimeForInputAttribute(): ?string
    {
        return $this->formatTime($this->start_time);
    }

    public function getEndTimeForInputAttribute(): ?string
    {
        return $this->formatTime($this->end_time);
    }

    private function formatTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
