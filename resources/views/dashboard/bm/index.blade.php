@extends('layouts.app', ['title' => 'Dashboard BM'])

@section('content')
<div class="space-y-6">
	<h5 class="mt-3 mb-3">Summary</h5>
	<div class="row g-3">
		<x-card-data id="upcoming-ready" title="Menus Ready (Upcoming)" subtitle="Days prepared" icon="bx-calendar" unit="" colour="primary" :value="$stats['upcoming_ready_days'] ?? '0 / 4'" />
		<x-card-data id="locked-percent" title="Selections Locked" subtitle="Across all picks" icon="bx-lock" unit="%" colour="warning" :value="$stats['locked_percent'] ?? 0" />
		<x-card-data id="pending-users" title="Pending Selections" subtitle="Users to remind" icon="bx-bell" unit="" colour="primary" :value="$stats['pending_users'] ?? 0" />
		<x-card-data id="next-export" title="Next Export" subtitle="Scheduled Friday" icon="bx-download" unit="" colour="primary" :value="$stats['next_export_date'] ?? 'Friday'" />
	</div>

	@php
		$exportOptions = collect($exportOptions ?? [])->filter(fn($opt) => !empty($opt['report']))->values();
	@endphp
	<h5 class="mt-4 mb-3">Export & Reports</h5>
	<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
		@foreach($exportOptions as $option)
			@php
				$report = $option['report'] ?? null;
				$label = $option['label'] ?? 'Export';
				$key = $option['key'] ?? 'export';
			@endphp
			@continue(!$report)
			@if(!$report->exported_at)
				<x-button :url="route('bm.reports.export', $report->code)" :label="$label" icon="bx-download" />
			@else
				<x-card-notification :id="'exported-'.$key" title="Exported" :message="'Week '.$report->code.' exported'" :time="$report->exported_at->format('Y-m-d H:i')" />
			@endif
		@endforeach
		<x-button :url="route('bm.reports.index')" label="All Reports" icon="bx-file" />
	</div>
	@if($exportOptions->isNotEmpty())
		<div class="d-flex flex-column gap-1 mb-2">
			@foreach($exportOptions as $option)
				@php
					$description = $option['description'] ?? null;
					$note = $option['note'] ?? null;
				@endphp
				@if($description)
					<div class="small text-muted">{{ $description }}</div>
				@endif
				@if($note)
					<div class="small text-muted fst-italic">{{ $note }}</div>
				@endif
			@endforeach
		</div>
	@endif

	<h5 class="mt-4 mb-3">Daily Selection Status</h5>
	<div class="row g-3">
		@foreach(($selection['days'] ?? []) as $day => $data)
			<x-card-data :id="'sel-'.$day" :title="$day" :value="$data['completed'] . ' / ' . $data['total']" :subtitle="($data['percent'] ?? 0) . '%'" icon="bx-check-square" unit="" colour="primary" />
		@endforeach
	</div>

	{{-- <h5 class="mt-4 mb-3">Users Needing Attention</h5>
	<x-table id="bm-pending-users" caption="Pending Users">
		<thead>
			<tr>
				<th class="text-left">Username</th>
				<th class="text-left">Incomplete Days</th>
				<th class="text-left">Actions</th>
			</tr>
		</thead>
		<tbody>
			@forelse(($pendingUsers ?? []) as $pu)
				<tr>
					<td>{{ $pu['username'] }}</td>
					<td>{{ implode(', ', $pu['missing_days']) }}</td>
					<td>
						<x-button :url="route('bm.users.show', $pu['username'])" label="View" icon="bx-show" />
					</td>
				</tr>
			@empty
				<tr><td colspan="3" class="text-center">All users complete.</td></tr>
			@endforelse
		</tbody>
	</x-table> --}}

	<h5 class="mt-4 mb-3">Upcoming Week Prep</h5>
	<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
		<x-card-data id="upcoming-code" title="Upcoming Week Code" :value="$upcomingWeek['code'] ?? '—'" subtitle="Identifier" icon="bx-hash" unit="" colour="primary" />
		<x-card-data id="upcoming-days-ready" title="Days Ready" :value="(($upcomingWeek['days_ready'] ?? 0).' / 4')" subtitle="Full 4 menus" icon="bx-calendar" unit="" colour="primary" />
	</div>
	<div class="card p-3 mb-3">
		<h6 class="mb-3">Upcoming Week Menus</h6>
		@php($dayOrder = ['Mon','Tue','Wed','Thu'])
		<div class="row g-3">
			@foreach($dayOrder as $label)
				@php($menus = ($upcomingWeek['days'][$label] ?? []))
				<div class="col-md-3">
					<div class="small text-muted fw-semibold mb-2 d-flex justify-content-between align-items-center">
						<span>{{ $label }}</span>
						<span class="badge bg-{{ count($menus)===4 ? 'success' : 'secondary' }}">{{ count($menus) }}/4</span>
					</div>
					<ul class="list-unstyled mb-0 small">
						@forelse($menus as $m)
							<li class="mb-1" title="{{ $m['name'] ?? '' }}">{{ Str::limit($m['name'] ?? '',38) }}</li>
						@empty
							<li class="text-muted">No menus</li>
						@endforelse
					</ul>
				</div>
			@endforeach
		</div>
	</div>

	{{-- <h5 class="mt-4 mb-3">Recent Reports</h5>
	<x-table id="bm-reports" caption="">
		<thead>
			<tr>
				<th class="text-left">Code</th>
				<th class="text-left">Exported At</th>
				<th class="text-left">Exporter</th>
				<th class="text-left">Action</th>
			</tr>
		</thead>
		<tbody>
			@forelse(($recentReports ?? []) as $report)
				<tr>
					<td>{{ $report->code }}</td>
					<td>{{ $report->exported_at?->format('Y-m-d H:i') ?? 'Pending' }}</td>
					<td>{{ $report->exported_by ?? '—' }}</td>
					<td>
						<x-button :url="route('bm.reports.show', $report->code)" label="View" icon="bx-show" />
						@if(!$report->exported_at)
							<x-button :url="route('bm.reports.export', $report->code)" label="Export" icon="bx-download" />
						@endif
					</td>
				</tr>
			@empty
				<tr><td colspan="4" class="text-center">No recent reports.</td></tr>
			@endforelse
		</tbody>
	</x-table> --}}
</div>
@endsection
