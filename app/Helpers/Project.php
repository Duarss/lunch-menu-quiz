<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Project
{
    protected const SELECTION_WINDOW_CACHE_PREFIX = 'selection_window_ready:';

    /**
     * Parse various date inputs into a Carbon instance in the given timezone.
     */
    protected static function toCarbon(DateTimeInterface|string|null $date = null, ?string $tz = null): Carbon
    {
        $tz = $tz ?: config('app.timezone', 'UTC');
        if ($date instanceof DateTimeInterface) {
            return Carbon::instance((new Carbon($date))->setTimezone($tz));
        }
        if (is_string($date)) {
            return Carbon::parse($date, $tz);
        }
        return Carbon::now($tz);
    }

    /**
     * Generate ISO week code (e.g., 2025-W47) from a given date.
     */
    public static function isoWeekCode(DateTimeInterface|string|null $date = null, ?string $tz = null): string
    {
        $c = self::toCarbon($date, $tz);
        $year = $c->isoWeekYear;
        $week = str_pad((string) $c->isoWeek, 2, '0', STR_PAD_LEFT);
        return sprintf('%d-W%s', $year, $week);
    }

    /**
     * Generate month-based week code (YYYY-MM-Wn) where n resets each month.
     */
    public static function monthWeekCode(DateTimeInterface|string|null $date = null, ?string $tz = null): string
    {
        $c = self::toCarbon($date, $tz);
        $year = $c->year;
        $month = str_pad((string) $c->month, 2, '0', STR_PAD_LEFT);
        // Carbon's weekOfMonth uses weeks starting on Monday by default (respecting startOfWeek setting)
        $weekInMonth = $c->weekOfMonth;
        return sprintf('%d-%s-W%d', $year, $month, $weekInMonth);
    }

    /**
     * Parse a month-based week code back to Monday date of that week.
     */
    public static function mondayFromMonthWeekCode(string $code, ?string $tz = null): Carbon
    {
        $tz = $tz ?: config('app.timezone', 'Asia/Jakarta');
        if (!preg_match('/^(\\d{4})-(\\d{2})-W(\\d{1,2})$/', $code, $m)) {
            throw new \InvalidArgumentException('Invalid month week code: ' . $code);
        }
        // $m indices: 0=full, 1=year, 2=month, 3=weekInMonth
        $year = (int) $m[1];
        $month = (int) $m[2];
        $weekInMonth = (int) $m[3];
        // Start at first Monday of month then advance (weekInMonth-1) weeks.
        $firstDay = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
        $firstMonday = $firstDay->copy()->next(Carbon::MONDAY);
        // If month starts on Monday, next(MONDAY) moves a week ahead; adjust:
        if ($firstDay->dayOfWeekIso === Carbon::MONDAY) {
            $firstMonday = $firstDay->copy();
        }
        $targetMonday = $firstMonday->copy()->addWeeks($weekInMonth - 1);
        // Ensure still in same month; if spills over, clamp to last Monday inside month.
        if ($targetMonday->month !== $month) {
            // Move back 1 week until month matches.
            while ($targetMonday->month !== $month) {
                $targetMonday->subWeek();
            }
        }
        return $targetMonday->startOfDay();
    }

    /**
     * Current selectable month-week code (next week) if window open; else null.
     */
    public static function currentSelectableMonthWeekCode(DateTimeInterface|string|null $now = null, ?string $tz = null): ?string
    {
        $c = self::toCarbon($now, $tz);
        if (!self::isSelectionWindowOpen($c)) {
            return null;
        }
        $nextMonday = $c->copy()->next(Carbon::MONDAY);
        return self::monthWeekCode($nextMonday);
    }

    /**
     * Get the Monday (date) of the ISO week for a given date.
     */
    public static function isoWeekMonday(DateTimeInterface|string|null $date = null, ?string $tz = null): Carbon
    {
        $c = self::toCarbon($date, $tz);
        return $c->copy()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Convert ISO week code (YYYY-Www) back to the Monday Carbon date.
     */
    public static function mondayFromIsoWeekCode(string $code, ?string $tz = null): Carbon
    {
        $tz = $tz ?: config('app.timezone', 'UTC');
        // Accept formats like 2025-W47 or 2025W47
        $normalized = str_replace('W', '', strtoupper($code));
        if (!preg_match('/^(\d{4})-?(\d{2})$/', $normalized, $m)) {
            throw new \InvalidArgumentException('Invalid ISO week code: ' . $code);
        }
        [$year, $week] = $m;
        $year = (int) $year;
        $week = (int) $week;
        $c = Carbon::now($tz);
        $c->setISODate($year, $week, 1); // 1 = Monday
        return $c->startOfDay();
    }

    /**
     * Whether selection window is open at a given moment (Wed–Fri local time).
     * Users can choose menus for the upcoming (next) week during this window.
     */
    public static function isSelectionWindowOpen(DateTimeInterface|string|null $now = null, ?string $tz = null): bool
    {
        $c = self::toCarbon($now, $tz);
        $dow = (int) $c->dayOfWeekIso; // 1=Mon .. 7=Sun
        // if (!in_array($dow, [Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY], true)) {
        //     return false;
        // }
        if (!in_array($dow, [Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY], true)) {
            return false;
        }

        $weekCode = self::monthWeekCode($c->copy()->next(Carbon::MONDAY), $tz);

        return self::isSelectionWindowReady($weekCode);
    }

    /**
     * The target (next) week's ISO week code for selection, if window is open; otherwise null.
     */
    public static function currentSelectableWeekCode(DateTimeInterface|string|null $now = null, ?string $tz = null): ?string
    {
        $c = self::toCarbon($now, $tz);
        if (!self::isSelectionWindowOpen($c)) {
            return null;
        }
        $nextMonday = $c->copy()->next(Carbon::MONDAY);
        return self::isoWeekCode($nextMonday);
    }

    protected static function selectionWindowCacheKey(string $weekCode): string
    {
        return self::SELECTION_WINDOW_CACHE_PREFIX . strtoupper($weekCode);
    }

    /**
     * Returns whether the admin has flagged the selection window as ready for a given week code.
     */
    public static function isSelectionWindowReady(string $weekCode): bool
    {
        return Cache::get(self::selectionWindowCacheKey($weekCode), false) === true;
    }

    /**
     * Mark the selection window as ready (karyawan can submit once Wed–Fri window opens).
     * Automatically expires at the end of the upcoming Friday unless closed sooner.
     */
    public static function openSelectionWindow(string $weekCode, DateTimeInterface|string|null $weekMonday = null, ?string $tz = null): void
    {
        $monday = $weekMonday
            ? self::toCarbon($weekMonday, $tz)->startOfDay()
            : self::mondayFromMonthWeekCode($weekCode, $tz);
        $expiry = $monday->copy()->addDays(4)->endOfDay();
        Cache::put(self::selectionWindowCacheKey($weekCode), true, $expiry);
    }

    /**
     * Remove the ready flag so that no selections can be made until reopened.
     */
    public static function closeSelectionWindow(string $weekCode): void
    {
        Cache::forget(self::selectionWindowCacheKey($weekCode));
    }

    /**
     * Convenience: generate report code for a given Monday date (or any date in that week).
     */
    public static function reportCodeForWeek(DateTimeInterface|string|null $monday = null, ?string $tz = null): string
    {
        return self::isoWeekCode(self::isoWeekMonday($monday, $tz), $tz);
    }
}
