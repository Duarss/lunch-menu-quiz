<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LunchPickupWindow extends Model
{
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function getDateForInputAttribute(): ?string
    {
        return $this->date?->format('Y-m-d');
    }

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

        $formats = ['H:i:s', 'H:i'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $time)->format('H:i');
            } catch (\Throwable $e) {
                // continue trying other formats
            }
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getRouteKeyName(): string
    {
        return 'date';
    }
}
