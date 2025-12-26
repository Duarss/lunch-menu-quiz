@extends('layouts.app', ['title' => 'Dashboard Admin'])

@section('content')
<div class="space-y-6">
	<h5 class="mt-4 mb-3">Summary</h5>
	{{-- Summary Cards Row --}}
	<div class="row g-3">
		{{-- Total Users --}}
		<x-card-data id="total-users" title="Total Users" subtitle="All karyawan" icon="bx-user" unit="" colour="primary" :value="$stats['total_users'] ?? 0" />
		{{-- Total Menus (This Week) --}}
		<x-card-data id="menus-week" title="Menus This Week" subtitle="Distinct options chosen" icon="bx-restaurant" unit="" colour="primary" :value="$stats['menus_this_week'] ?? 0" />
		{{-- Completion % --}}
		<x-card-data id="completion" title="Completion" subtitle="Users finished Mon–Thu" icon="bx-pie-chart-alt-2" unit="%" colour="warning" :value="$stats['completion_percent'] ?? 0" />
		{{-- Pending Reports --}}
		<x-card-data id="pending-reports" title="Pending Export" subtitle="Unexported weeks" icon="bx-file" unit="" colour="primary" :value="$stats['pending_export_weeks'] ?? 0" />
	</div>

	<h5 class="mt-4 mb-3">Quick Actions</h5>
	{{-- Actions --}}
	<div class="d-flex flex-wrap gap-2 mb-2">
		<x-button :url="route('admin.users.index')" label="Manage Users" icon="bx-user" />
		<x-button :url="route('admin.menus.index')" label="Manage Menus" icon="bx-restaurant" />
		<x-button :url="route('admin.reports.index')" label="View Reports" icon="bx-line-chart" />
		{{-- <x-button :url="route('admin.analytics.index')" icon="bx-line-chart" /> --}}
	</div>

	<h5 class="mt-4 mb-3">Selection Progress</h5>
	{{-- Selection Progress Breakdown --}}
	<div class="row g-3">
		<x-card-data id="progress-total" title="Selection Progress" :value="($stats['fully_completed_users'] ?? 0).' / '.($stats['total_users'] ?? 0)" subtitle="Users completed all days" icon="bx-list-check" unit="" colour="primary" />
		@foreach(($stats['daily_breakdown'] ?? []) as $day => $data)
			<x-card-data :id="'day-'.$day" :title="$day" :value="$data['count']" :subtitle="($data['percent'] ?? 0).'%'" icon="bx-calendar" unit="" colour="primary" />
		@endforeach
	</div>

	<h5 class="mt-4 mb-3">Recent Reports</h5>
	{{-- Recent Reports Table --}}
	<x-table id="admin-reports" caption="">
		<thead>
			<tr>
				<th class="text-left">Week Code</th>
				<th class="text-left">Exported By</th>
				<th class="text-left">Exported At</th>
				<th class="text-left">Actions</th>
			</tr>
		</thead>
		<tbody>
			@forelse(($recentReports ?? []) as $report)
				<tr>
					<td>{{ $report->code }}</td>
					<td>{{ $report->exported_by ?? '—' }}</td>
					<td>{{ $report->exported_at?->format('Y-m-d H:i') ?? 'Pending' }}</td>
					<td>
						<x-button :url="route('admin.reports.show', $report->code)" label="View" icon="bx-show" />
						@if(!$report->exported_at)
							<x-button :url="route('admin.reports.export', $report->code)" label="Export" icon="bx-download" />
						@endif
					</td>
				</tr>
			@empty
				<tr><td colspan="4" class="text-center">No reports yet.</td></tr>
			@endforelse
		</tbody>
	</x-table>

	<h5 class="mt-4 mb-3">Upcoming Week Prep</h5>
	<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
		<x-card-data id="upcoming-prep" title="Days Ready" :value="(($prep['days_ready'] ?? 0).' / 4')" subtitle="Full days" icon="bx-calendar" unit="" colour="primary" />
		<x-card-data id="upcoming-week-code" title="Upcoming Code" :value="($prep['code'] ?? '—')" subtitle="Identifier" icon="bx-hash" unit="" colour="primary" />
	</div>
	<div class="card p-3 mb-3">
		<h6 class="mb-3">Upcoming Week Menus</h6>
		<div class="row g-3">
			@foreach(($prep['days'] ?? []) as $day)
				<div class="col-md-3">
					<div class="small text-muted fw-semibold mb-2 d-flex justify-content-between align-items-center">
						<span>{{ $day['label'] ?? '—' }}</span>
						<span class="badge bg-{{ !empty($day['is_full']) ? 'success' : 'secondary' }}">{{ $day['count'] ?? 0 }}/4</span>
					</div>
					<ul class="list-unstyled mb-0 small">
						@forelse(($day['menus'] ?? []) as $menu)
							<li class="mb-1" title="{{ $menu['name'] ?? '' }}">{{ Str::limit($menu['name'] ?? '', 38) }}</li>
						@empty
							<li class="text-muted">No menus</li>
						@endforelse
					</ul>
				</div>
			@endforeach
		</div>
	</div>
</div>
@endsection

