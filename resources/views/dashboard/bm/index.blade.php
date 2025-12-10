@extends('layouts.app', ['title' => 'Dashboard BM'])

@section('content')
<div class="space-y-6">
	<h5 class="mt-3 mb-3">Summary</h5>
	<div class="row g-3">
		@foreach($statCards as $card)
			<x-card-data
				:id="$card['id']"
				title="{{ $card['title'] }}"
				:subtitle="$card['subtitle']"
				icon="{{ $card['icon'] }}"
				unit="{{ $card['unit'] }}"
				colour="{{ $card['colour'] }}"
				:value="$card['value']"
			/>
		@endforeach
	</div>

	<h5 class="mt-4 mb-3">Export & Reports</h5>
	<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
		@foreach($exportOptions as $option)
			@if(!empty($option['button']))
				<x-button :url="$option['button']['url']" :label="$option['button']['label']" :icon="$option['button']['icon']" />
			@elseif(!empty($option['notification']))
				<x-card-notification
					:id="$option['notification']['id']"
					title="{{ $option['notification']['title'] }}"
					:message="{{ $option['notification']['message'] }}"
					:time="{{ $option['notification']['time'] }}"
				/>
			@endif
		@endforeach
		<x-button :url="$allReportsUrl" label="All Reports" icon="bx-file" />
	</div>
	@if(!empty($exportOptions))
		<div class="d-flex flex-column gap-1 mb-2">
			@foreach($exportOptions as $option)
				@if(!empty($option['description']))
					<div class="small text-muted">{{ $option['description'] }}</div>
				@endif
				@if(!empty($option['note']))
					<div class="small text-muted fst-italic">{{ $option['note'] }}</div>
				@endif
			@endforeach
		</div>
	@endif

	<h5 class="mt-4 mb-3">Daily Selection Status</h5>
	<div class="row g-3">
		@foreach($selectionCards as $card)
			<x-card-data
				:id="$card['id']"
				title="{{ $card['title'] }}"
				:value="$card['value']"
				:subtitle="$card['subtitle']"
				icon="{{ $card['icon'] }}"
				unit="{{ $card['unit'] }}"
				colour="{{ $card['colour'] }}"
			/>
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
		@foreach($upcomingSummaryCards as $card)
			<x-card-data
				:id="$card['id']"
				title="{{ $card['title'] }}"
				:value="$card['value']"
				:subtitle="$card['subtitle']"
				icon="{{ $card['icon'] }}"
				unit="{{ $card['unit'] }}"
				colour="{{ $card['colour'] }}"
			/>
		@endforeach
	</div>
	<div class="card p-3 mb-3">
		<h6 class="mb-3">Upcoming Week Menus</h6>
		<div class="row g-3">
			@foreach($upcomingDayBlocks as $day)
				<div class="col-md-3">
					<div class="small text-muted fw-semibold mb-2 d-flex justify-content-between align-items-center">
						<span>{{ $day['label'] }}</span>
						<span class="badge {{ $day['badge_class'] }}">{{ $day['badge_label'] }}</span>
					</div>
					<ul class="list-unstyled mb-0 small">
						@forelse($day['menus'] as $menu)
							<li class="mb-1" title="{{ $menu['name'] }}">{{ $menu['short_name'] }}</li>
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
