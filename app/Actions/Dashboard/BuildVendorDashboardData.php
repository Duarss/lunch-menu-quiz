<?php

namespace App\Actions\Dashboard;

use App\Actions\Menu\BuildVendorIndexData;
use App\Actions\Menu\MenuAction;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;

class BuildVendorDashboardData extends MenuAction
{
    private const SLIDE_CHUNK = 2;

    public function __construct(private BuildVendorIndexData $buildVendorIndexData) {}

    public function __invoke(User $vendor, string $fallbackWeekCode): array
    {
        $vendorData = ($this->buildVendorIndexData)($vendor);

        $dayOrder = $vendorData['vendorDayOrder'] ?? [];
        $days = $vendorData['vendorDays'] ?? [];

        $slotStats = $this->calculateSlotStats($dayOrder, $days);

        $rangeStartLabel = $this->formatDateLabel($vendorData['vendorRangeStart'] ?? null);
        $rangeEndLabel = $this->formatDateLabel($vendorData['vendorRangeEnd'] ?? null);

        $rangeLabel = $rangeStartLabel && $rangeEndLabel
            ? $rangeStartLabel . ' – ' . $rangeEndLabel
            : ($rangeStartLabel ?? '—');

        $weekCode = $vendorData['vendorWeekCode'] ?? $vendorData['weekCode'] ?? $fallbackWeekCode;

        $windowReady = (bool) ($vendorData['selectionWindowReady'] ?? false);
        $windowOpen = (bool) ($vendorData['selectionWindowOpen'] ?? false);

        $windowStatus = $windowOpen ? 'Open' : ($windowReady ? 'Ready' : 'Pending');
        $windowSubtitle = $windowOpen
            ? 'Karyawan dapat memilih menu minggu ini.'
            : ($windowReady ? 'Menunggu jadwal Wed–Fri untuk pemilihan karyawan.' : 'Menunggu admin membuka window.');

        $summaryCards = $this->buildSummaryCards(
            weekCode: $weekCode,
            rangeLabel: $rangeLabel,
            filledSlots: $slotStats['filled'],
            totalSlots: $slotStats['total'],
            progressPercent: $slotStats['percent'],
            remainingSlots: $slotStats['remaining'],
            windowStatus: $windowStatus,
            windowSubtitle: $windowSubtitle
        );

        $daySummaries = $this->buildDaySummaries($dayOrder, $days);
        $slides = $this->chunkSlides($daySummaries);

        return [
            'summaryCards' => $summaryCards,
            'progressSlides' => $slides,
            'hasSlides' => !empty($slides),
            'uploadUrl' => route('masterMenu.index'),
            'vendorName' => $vendorData['vendorCateringLabel'] ?? $this->resolveVendorLabel($vendorData['vendorCatering'] ?? null),
        ];
    }

    private function buildSummaryCards(string $weekCode, string $rangeLabel, int $filledSlots, int $totalSlots, int $progressPercent, int $remainingSlots, string $windowStatus, string $windowSubtitle): array
    {
        $remainingBadgeClass = $remainingSlots === 0 ? 'badge bg-success' : 'badge bg-warning text-dark';
        $remainingBadgeLabel = $remainingSlots === 0 ? 'Sudah lengkap' : 'Perlu dilengkapi';
        $windowBadgeClass = $this->windowBadgeClass($windowStatus);

        return [
            [
                'title' => 'Minggu yang sedang diunggah',
                'value' => $weekCode,
                'subtitle' => $rangeLabel,
            ],
            [
                'title' => 'Slot terisi',
                'value' => $filledSlots,
                'suffix' => '/ ' . $totalSlots,
                'progress' => [
                    'percent' => $progressPercent,
                    'label' => $progressPercent . '% dari target minggu ini',
                ],
            ],
            [
                'title' => 'Sisa slot',
                'value' => $remainingSlots,
                'badge' => [
                    'class' => $remainingBadgeClass,
                    'label' => $remainingBadgeLabel,
                ],
                'subtitle' => 'Selesaikan semua slot sebelum window ditutup.',
            ],
            [
                'title' => 'Status window unggah',
                'value' => 'Upload Window',
                'badge' => [
                    'class' => $windowBadgeClass,
                    'label' => ucfirst($windowStatus),
                ],
                'subtitle' => $windowSubtitle ?: 'Ikuti jadwal yang sudah ditentukan oleh admin.',
            ],
        ];
    }

    private function buildDaySummaries(array $dayOrder, array $days): array
    {
        $summaries = [];

        foreach ($dayOrder as $label) {
            $day = $days[$label] ?? null;
            if ($day === null) {
                continue;
            }

            $options = $day['options'] ?? [];
            $optionA = $options['A'] ?? null;
            $optionB = $options['B'] ?? null;

            $expectedParts = 3;
            $completedParts = 0;

            $optionCards = [];

            foreach (['A' => $optionA, 'B' => $optionB] as $optionKey => $optionData) {
                $optionFilled = $optionData && !empty($optionData['has_menu']);
                if ($optionFilled) {
                    $completedParts++;
                }

                $optionCards[] = [
                    'label' => 'Opsi ' . $optionKey,
                    'description' => $optionFilled
                        ? ($optionData['name'] ?: 'Nama belum tercatat')
                        : 'Segera lengkapi nama menu.',
                    'code' => $optionData['code'] ?? null,
                    'badge' => [
                        'class' => $optionFilled ? 'badge bg-success' : 'badge bg-secondary',
                        'label' => $optionFilled ? 'Nama terisi' : 'Nama belum diisi',
                    ],
                ];
            }

            $hasImage = $optionA && (!empty($optionA['image']) || !empty($optionA['image_url']));
            if ($hasImage) {
                $completedParts++;
            }

            $imageCard = [
                'label' => 'Gambar Menu',
                'description' => $hasImage ? 'Sudah ada gambar yang terunggah.' : 'Belum ada gambar untuk menu hari ini.',
                'badge' => [
                    'class' => $hasImage ? 'badge bg-success' : 'badge bg-secondary',
                    'label' => $hasImage ? 'Gambar ada' : 'Gambar belum ada',
                ],
            ];

            $badgeClass = $completedParts === $expectedParts ? 'bg-success' : 'bg-warning';
            $badgeLabel = sprintf('%d / %d Terpenuhi', $completedParts, $expectedParts);

            $summaries[] = [
                'label' => $day['label'] ?? $label,
                'date_label' => $day['date_label'] ?? null,
                'badge_class' => $badgeClass,
                'badge_label' => $badgeLabel,
                'has_options' => !empty($options),
                'option_cards' => $optionCards,
                'image_card' => $imageCard,
            ];
        }

        return $summaries;
    }

    private function chunkSlides(array $daySummaries): array
    {
        if (empty($daySummaries)) {
            return [];
        }

        $chunks = array_chunk($daySummaries, self::SLIDE_CHUNK);

        return array_map(
            static fn(array $chunk) => ['days' => $chunk],
            $chunks
        );
    }

    private function calculateSlotStats(array $dayOrder, array $days): array
    {
        $filled = 0;
        $total = 0;

        foreach ($dayOrder as $label) {
            $day = $days[$label] ?? null;
            if ($day === null) {
                continue;
            }

            foreach (($day['options'] ?? []) as $option) {
                $total++;
                if (!empty($option['has_menu'])) {
                    $filled++;
                }
            }
        }

        $remaining = max($total - $filled, 0);
        $percent = $total > 0 ? (int) round(($filled / max($total, 1)) * 100) : 0;

        return [
            'filled' => $filled,
            'total' => $total,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }

    private function formatDateLabel(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('D, d M Y');
    }

    private function windowBadgeClass(string $status): string
    {
        return match (strtolower($status)) {
            'open' => 'badge bg-success',
            'ready', 'ready soon' => 'badge bg-info text-dark',
            'pending' => 'badge bg-warning text-dark',
            'closed' => 'badge bg-secondary',
            default => 'badge bg-secondary',
        };
    }

    private function resolveVendorLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'Vendor';
        }

        $sanitized = strtolower(trim($code));
        $sanitized = str_replace([' ', '-', '_'], '', $sanitized);

        $map = [
            'vendora' => 'vendorA',
            'vendorb' => 'vendorB',
        ];

        $normalized = $map[$sanitized] ?? null;
        if ($normalized === null) {
            return ucfirst($code);
        }

        return Company::where('code', $normalized)->value('name')
            ?? match ($normalized) {
                'vendorA' => 'Vendor A',
                'vendorB' => 'Vendor B',
                default => ucfirst($code),
            };
    }
}
