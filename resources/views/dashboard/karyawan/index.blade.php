@extends('layouts.app', ['title' => 'Dashboard Karyawan'])

@section('content')
@php
	$dayOrder = ['Mon','Tue','Wed','Thu'];
	$iconMap = ['locked' => 'bx-lock', 'saved' => 'bx-check-square', 'pending' => 'bx-time-five'];
	$pendingDays = $summary['pending_days'] ?? [];
	$statusBadgeMap = [
		'pending' => ['class' => 'bg-warning text-white', 'label' => 'Pending'],
		'saved' => ['class' => 'bg-success', 'label' => 'Saved'],
		'locked' => ['class' => 'bg-secondary', 'label' => 'Locked'],
	];
	$defaultStatusBadge = ['class' => 'bg-secondary', 'label' => 'Status'];
	$windowColour = $summary['window_colour'] ?? 'secondary';
	$windowBadgeClass = $windowColour === 'warning'
		? 'bg-warning text-white'
		: 'bg-' . $windowColour;
	$remainingColour = $summary['remaining_colour'] ?? 'secondary';
	$remainingBadgeClass = $remainingColour === 'warning'
		? 'bg-warning text-white'
		: 'bg-' . $remainingColour;
	$remainingBadgeLabel = ($summary['remaining_days'] ?? 0) > 0 ? 'Needs Attention' : 'All Set';
	$weekSubtitle = $summary['week_subtitle'] ?? '—';
@endphp
<div class="space-y-6">
	<h5 class="mt-3">Ringkasan</h5>
	<div class="row g-3 mb-3">
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-body py-3">
					<div class="small text-muted mb-1">Selection Window</div>
					<div class="d-flex align-items-center justify-content-between gap-3">
						<span class="fw-semibold" id="label-window-status">{{ $summary['window_status_label'] }}</span>
						<span class="badge {{ $windowBadgeClass }}">{{ $summary['window_status_label'] }}</span>
					</div>
					<div class="small text-muted mt-2">{{ $summary['window_subtitle'] }}</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-body py-3">
					<div class="small text-muted mb-1">Week Code</div>
					<div class="d-flex align-items-center justify-content-between gap-3">
						<span class="fw-semibold">Week <code>{{ $summary['week_code'] ?? '—' }}</code></span>
						<span class="badge bg-light text-dark text-wrap">{{ $weekSubtitle }}</span>
					</div>
					<div class="small text-muted mt-2">Lunch range: {{ $weekSubtitle }}</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-body py-3">
					<div class="small text-muted mb-1">Completed Days</div>
					<div class="d-flex align-items-center justify-content-between gap-3">
						<span class="display-6 fw-semibold" id="label-completed-days">{{ $summary['completed_days'] }}</span>
						<span class="badge bg-primary">of 4</span>
					</div>
					<div class="small text-muted mt-2">Menus saved</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-body py-3">
					<div class="small text-muted mb-1">Remaining Days</div>
					<div class="d-flex align-items-center justify-content-between gap-3">
						<span class="display-6 fw-semibold" id="label-remaining-days">{{ $summary['remaining_days'] }}</span>
						<span class="badge {{ $remainingBadgeClass }}">{{ $remainingBadgeLabel }}</span>
					</div>
					<div class="small text-muted mt-2">Still to choose</div>
				</div>
			</div>
		</div>
	</div>

	<h5 class="mt-4 mb-3">Aksi</h5>
	<div class="d-flex flex-wrap gap-2 mb-2">
		<x-button :url="route('masterMenu.index')" :label="$summary['cta_label']" icon="bx-edit" />
		<x-button :url="route('karyawan.history.index', auth()->user()->username)" label="Lihat Riwayat" icon="bx-time-five" />
	</div>

	<h5 class="mt-4 mb-3">Pilihan Menu Minggu Depan</h5>
	<div class="row g-3 mb-3">
		@foreach($dayOrder as $label)
			@php($day = $days[$label] ?? null)
			@continue(!$day)
			@php($icon = $iconMap[$day['status']] ?? 'bx-bowl-hot')
			@php($badge = $statusBadgeMap[$day['status']] ?? $defaultStatusBadge)
			@php($accentColour = $day['colour'] ?? 'primary')
			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm h-100 {{ 'card-border-shadow-' . $accentColour }}">
					<div class="card-body py-3">
						<div class="small text-muted mb-1 d-flex align-items-center gap-2">
							<i class="bx {{ $icon }} text-{{ $accentColour }}"></i>
							<span>{{ $label }}</span>
						</div>
						<div class="d-flex align-items-center justify-content-between gap-3">
							<span class="fw-semibold" id="label-day-{{ strtolower($label) }}">{{ $day['value'] }}</span>
							<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
						</div>
						<div class="small text-muted mt-2">{{ $day['subtitle'] }}</div>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<h5 class="mt-4 mb-3">Recent Activity</h5>
	<x-table id="karyawan-recent" caption="Recent Selections">
		<thead>
			<tr>
				<th class="text-left">Day</th>
				<th class="text-left">Menu</th>
				<th class="text-left">Status</th>
				<th class="text-left">Updated</th>
			</tr>
		</thead>
		<tbody>
			@forelse($recentSelections as $item)
				<tr>
					<td>{{ $item['date_label'] }}</td>
					<td>{{ $item['menu_name'] }}</td>
					<td>{{ $item['status'] }}</td>
					<td>{{ $item['timestamp_label'] }}</td>
				</tr>
			@empty
				<tr><td colspan="4" class="text-center">No selections yet.</td></tr>
			@endforelse
		</tbody>
	</x-table>

	{{-- @if(!empty($pendingDays))
		<ul class="list-group">
			<x-card-notification id="pending-reminder" title="Pending Days" :message="'You still need to choose for: '.implode(', ', $pendingDays)" :time="now()->format('d M Y H:i')" />
		</ul>
	@endif --}}
</div>
@endsection
