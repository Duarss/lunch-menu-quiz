<?php

namespace App\Actions\Dashboard;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use App\Models\User;
use Carbon\Carbon;

class BuildKaryawanDashboardData
{
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu'];

    // Note: __invoke is what gets called when using app(BuildKaryawanDashboardData::class)(...)
    public function __invoke(User $user, string $rangeStart, string $rangeEnd, string $weekCode, Carbon $targetMonday, Carbon $now): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $windowOpen = Project::isSelectionWindowOpen($now);
        $windowReady = Project::isSelectionWindowReady($weekCode);

        $rangeStartCarbon = Carbon::parse($rangeStart, $tz);
        $rangeEndCarbon = Carbon::parse($rangeEnd, $tz);
        $windowStart = $targetMonday->copy()->subDays(5)->startOfDay();
        $windowEnd = $targetMonday->copy()->subDays(3)->endOfDay();

        $selections = ChosenMenu::with('menu')
            ->where('chosen_by', $user->id)
            ->whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->get()
            ->keyBy(function ($item) use ($tz) {
                return Carbon::parse($item->chosen_for_day, $tz)->toDateString();
            });

        $days = [];
        $dayCursor = $rangeStartCarbon->copy();
        $completed = 0;
        $pendingSubtitle = 'Terakhir diisi pada ' . $windowEnd->copy()->format('D, d M H:i');

        for ($i = 0; $i < count(self::DAY_LABELS); $i++) {
            $dateKey = $dayCursor->toDateString();
            $dayLabel = $dayCursor->format('D');
            $entry = $selections->get($dateKey);

            if ($entry) {
                $completed++;
            }

            $timestampString = $entry->updated_at ?? $entry->chosen_at ?? null;
            $timestamp = $timestampString ? Carbon::parse($timestampString, $tz) : null;

            $status = 'pending';
            $subtitle = $pendingSubtitle;
            if ($entry) {
                $status = $entry->is_locked ? 'locked' : 'saved';
                $subtitlePrefix = $entry->is_locked ? 'Locked' : 'Saved';
                $subtitle = $timestamp ? $subtitlePrefix . ' ' . $timestamp->format('D, d M H:i') : $subtitlePrefix;
            }

            $colour = match ($status) {
                'pending' => 'warning',
                'locked' => 'secondary',
                default => 'primary',
            };

            $days[$dayLabel] = [
                'date' => $dateKey,
                'value' => $entry?->menu?->name ?? 'Pending',
                'status' => $status,
                'subtitle' => $subtitle,
                'colour' => $colour,
            ];

            $dayCursor->addDay();
        }

        $remaining = max(0, count(self::DAY_LABELS) - $completed);

        if ($windowOpen) {
            $windowStatusLabel = 'Buka';
            $windowSubtitle = 'Submit before ' . $windowEnd->copy()->format('D, d M H:i');
            $windowBadgeClass = 'bg-success';
            $ctaLabel = $remaining > 0 ? 'Lengkapi Pilihan Saya' : 'Tinjau Pilihan Saya';
        } elseif (!$windowReady) {
            $windowStatusLabel = 'Pending';
            $windowSubtitle = 'Waiting for admin to release menus';
            $windowBadgeClass = 'bg-warning text-white';
            $ctaLabel = 'Lihat Menu Minggu Depan';
        } else {
            $windowStatusLabel = 'Tutup';
            $windowSubtitle = 'Opens ' . $windowStart->copy()->format('D, d M H:i');
            $windowBadgeClass = 'bg-warning text-white';
            $ctaLabel = 'Lihat Menu Minggu Depan';
        }

        $remainingBadgeClass = $remaining > 0 ? 'bg-warning text-white' : 'bg-success';
        $remainingBadgeLabel = $remaining > 0 ? 'Needs Attention' : 'All Set';

        $summary = [
            'week_code' => $weekCode,
            'week_subtitle' => sprintf('%s – %s', $rangeStartCarbon->format('d M'), $rangeEndCarbon->format('d M')),
            'window_status_label' => $windowStatusLabel,
            'window_subtitle' => $windowSubtitle,
            'window_badge_class' => $windowBadgeClass,
            'completed_days' => $completed,
            'remaining_days' => $remaining,
            'remaining_badge_class' => $remainingBadgeClass,
            'remaining_badge_label' => $remainingBadgeLabel,
            'cta_label' => $ctaLabel,
            'pending_days' => collect($days)
                ->filter(fn($day) => $day['status'] === 'pending')
                ->keys()
                ->values()
                ->all(),
            'window_ready' => $windowReady,
        ];

        $dayCards = $this->buildDayCards($days);

        $recentSelections = ChosenMenu::with('menu')
            ->where('chosen_by', $user->id)
            ->orderByDesc('chosen_at')
            ->limit(5)
            ->get()
            ->map(function ($item) use ($tz) {
                $chosenFor = Carbon::parse($item->chosen_for_day, $tz);
                $chosenAt = $item->chosen_at ? Carbon::parse($item->chosen_at, $tz) : null;

                return [
                    'date_label' => $chosenFor->format('D, d M Y'),
                    'menu_name' => optional($item->menu)->name ?? '—',
                    'status' => $item->is_locked ? 'Locked' : 'Saved',
                    'timestamp_label' => $chosenAt ? $chosenAt->format('d M Y H:i') : '—',
                ];
            });

        return [
            'summary' => $summary,
            'dayCards' => $dayCards,
            'recentSelections' => $recentSelections,
        ];
    }

    // Note: buildDayCards creates dashboard cards for each selection day
    private function buildDayCards(array $days): array
    {
        $statusBadgeMap = [
            'pending' => ['class' => 'bg-warning text-white', 'label' => 'Pending'],
            'saved' => ['class' => 'bg-success', 'label' => 'Saved'],
            'locked' => ['class' => 'bg-secondary', 'label' => 'Locked'],
        ];

        $iconMap = [
            'locked' => 'bx-lock',
            'saved' => 'bx-check-square',
            'pending' => 'bx-time-five',
        ];

        $cards = [];

        foreach (self::DAY_LABELS as $label) {
            $day = $days[$label] ?? null;
            if ($day === null) {
                continue;
            }

            $status = $day['status'];
            $badge = $statusBadgeMap[$status] ?? ['class' => 'bg-secondary', 'label' => 'Status'];
            $icon = $iconMap[$status] ?? 'bx-bowl-hot';
            $accent = $day['colour'] ?? 'primary';

            $cards[] = [
                'label' => $label,
                'value' => $day['value'],
                'subtitle' => $day['subtitle'],
                'badge_class' => $badge['class'],
                'badge_label' => $badge['label'],
                'icon' => $icon,
                'accent' => $accent,
                'card_class' => 'card-border-shadow-' . $accent,
            ];
        }

        return $cards;
    }
}
