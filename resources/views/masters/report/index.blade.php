@extends('layouts.app', ['title' => $title ?? 'Master Report'])

@section('content')
<div class="space-y-6">
	<h5 class="mt-3 mb-3">Weekly Report Export</h5>

	@if(session('status'))
		<div id="status-alert" class="alert alert-success small">{{ session('status') }}</div>
	@endif
	@if($errors->has('export'))
		<div id="error-alert" class="alert alert-danger small">{{ $errors->first('export') }}</div>
	@endif

	<div class="card p-3 p-md-4 mb-4">
		<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
			<div class="flex-grow-1 text-center text-md-start">
				<div class="fw-semibold">Week <code>{{ $week['code'] }}</code></div>
				<div class="small text-muted">Lunch range: {{ $week['range_label'] }}</div>
				<div class="small text-muted">Export deadline: {{ $week['deadline_label'] }}</div>
				<div class="mt-2 d-flex flex-column flex-sm-row flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
					<span class="badge {{ $week['window']['badge_class'] }}">{{ $week['window']['status'] }}</span>
					<small class="text-muted text-center text-md-start">{{ $week['window']['help'] }}</small>
				</div>
			</div>
			<div class="text-center text-md-end flex-grow-1 flex-md-grow-0">
				@if(!$week['export_pending'])
					<div class="text-success d-inline-flex align-items-center gap-1 fw-semibold">
						<i class="bx bx-check-circle"></i>
						<span>Exported {{ $week['exported_at_label'] }}</span>
					</div>
					<div class="small text-muted mt-1">By {{ $week['exported_by_label'] ?? '—' }}</div>
					@if($role === 'admin')
						<form class="export-form mt-2" method="POST"
							action="{{ route('masterReport.export', ['masterReport' => $week['report']->code]) }}"
							data-week="{{ $week['code'] }}"
							data-available="{{ $week['export_available_label'] }}"
							data-finalizes="0"
							@if($week['export_repeat_message']) data-repeat-warning="{{ e($week['export_repeat_message']) }}" @endif>
							@csrf
							<button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
								<i class="bx bx-download"></i>
								<span>Download Export</span>
							</button>
						</form>
					@endif
				@else
					<form class="export-form" method="POST"
						action="{{ route('masterReport.export', ['masterReport' => $week['report']->code]) }}"
						data-week="{{ $week['code'] }}"
						data-available="{{ $week['export_available_label'] }}"
						data-finalizes="{{ $role === 'bm' ? '1' : '0' }}"
						data-pending-warning="{{ e($week['pending_warning'] ?? '') }}">
						@csrf
						<button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
							<i class="bx bx-download"></i>
							<span>Export Next Week</span>
						</button>
					</form>
					@if(!$week['can_export'] && $week['export_disabled_reason'])
						<small class="text-muted d-block mt-1">{{ $week['export_disabled_reason'] }}</small>
					@endif
					@if(!empty($week['pending_warning']))
						<small class="text-danger d-block mt-1">{{ $week['pending_warning'] }}</small>
					@endif
					@if($week['export_future_label'])
						<small class="text-muted d-block mt-1">Previously scheduled for {{ $week['export_future_label'] }}.</small>
					@endif
				@endif
			</div>
		</div>
		<div class="mt-3 small text-center text-md-start">
			<div>Selections captured: <strong>{{ $week['totals']['selections'] }}</strong></div>
			<div>Pending users: <strong>{{ $week['totals']['pending_count'] }}</strong> of {{ $week['totals']['karyawan'] }} karyawan.</div>
		</div>
	</div>

	@if($week['export_pending'] && $week['totals']['pending_count'] > 0)
		<div class="alert alert-warning small text-center text-md-start">
			{{ $week['totals']['pending_count'] }} karyawan masih belum melengkapi pilihan untuk minggu ini. Pertimbangkan untuk mengingatkan mereka sebelum menutup window.
		</div>
	@endif

	@if(!empty($current_week))
		<div class="card p-3 p-md-4 mb-4">
			<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
				<div class="flex-grow-1 text-center text-md-start">
					<div class="fw-semibold">Current Week <code>{{ $current_week['code'] }}</code></div>
					<div class="small text-muted">Lunch range: {{ $current_week['range_label'] }}</div>
					<div class="small text-muted">Export available since: {{ $current_week['export_available_label'] }}</div>
					<div class="mt-2 d-flex flex-column flex-sm-row flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
						<span class="badge {{ $current_week['window']['badge_class'] }}">{{ $current_week['window']['status'] }}</span>
						<small class="text-muted text-center text-md-start">{{ $current_week['window']['help'] }}</small>
					</div>
				</div>
				<div class="text-center text-md-end flex-grow-1 flex-md-grow-0">
					@if(!$current_week['export_pending'])
						<div class="text-success d-inline-flex align-items-center gap-1 fw-semibold">
							<i class="bx bx-check-circle"></i>
							<span>Exported {{ $current_week['exported_at_label'] }}</span>
						</div>
						<div class="small text-muted mt-1">By {{ $current_week['exported_by_label'] ?? '—' }}</div>
						@if($role === 'admin')
							<form class="export-form mt-2" method="POST"
								action="{{ route('masterReport.export', ['masterReport' => $current_week['report']->code]) }}"
								data-week="{{ $current_week['code'] }}"
								data-available="{{ $current_week['export_available_label'] }}"
								data-finalizes="0"
								@if($current_week['export_repeat_message']) data-repeat-warning="{{ e($current_week['export_repeat_message']) }}" @endif>
								@csrf
								<button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
									<i class="bx bx-download"></i>
									<span>Download Export</span>
								</button>
							</form>
						@endif
					@else
						<form class="export-form" method="POST"
							action="{{ route('masterReport.export', ['masterReport' => $current_week['report']->code]) }}"
							data-week="{{ $current_week['code'] }}"
							data-available="{{ $current_week['export_available_label'] }}"
							data-finalizes="{{ $role === 'bm' ? '1' : '0' }}"
							data-pending-warning="{{ e($current_week['pending_warning'] ?? '') }}"
							@if($current_week['export_repeat_message']) data-repeat-warning="{{ e($current_week['export_repeat_message']) }}" @endif>
							@csrf
							<button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
								<i class="bx bx-download"></i>
								<span>Export This Week</span>
							</button>
						</form>
						@if(!$current_week['can_export'] && $current_week['export_disabled_reason'])
							<small class="text-muted d-block mt-1">{{ $current_week['export_disabled_reason'] }}</small>
						@endif
						@if(!empty($current_week['pending_warning']))
							<small class="text-danger d-block mt-1">{{ $current_week['pending_warning'] }}</small>
						@endif
					@endif
				</div>
			</div>
			<div class="mt-3 small text-center text-md-start">
				<div>Selections captured: <strong>{{ $current_week['totals']['selections'] }}</strong></div>
				<div>Pending users: <strong>{{ $current_week['totals']['pending_count'] }}</strong> of {{ $current_week['totals']['karyawan'] }} karyawan.</div>
			</div>
			<div class="small text-muted mt-2 text-center text-md-start">Use this export when distributing the menus being served this week.</div>
		</div>
	@endif

	<div class="row g-3 mb-4">
		<x-card-data id="total-karyawan" title="Total Karyawan" subtitle="Eligible users" icon="bx-group" unit="" colour="primary" :value="$week['totals']['karyawan']" />
		<x-card-data id="completed-karyawan" title="Completed" :value="$week['totals']['completed'].' / '.$week['totals']['karyawan']" subtitle="Finished selections" icon="bx-task" unit="" colour="primary" />
		<x-card-data id="completion-percent" title="Completion" :value="$week['totals']['completion_percent']" subtitle="Percent complete" icon="bx-pie-chart-alt" unit="%" colour="warning" />
		<x-card-data id="pending-count" title="Pending" :value="$week['totals']['pending_count']" subtitle="Need follow-up" icon="bx-bell" unit="" colour="warning" />
	</div>

	<div class="card p-3 p-md-4 mb-4">
		<h6 class="mb-3 text-center text-md-start">Daily Progress</h6>
		<x-table id="report-daily" caption="Mon–Thu completion status">
			<thead>
				<tr>
					<th class="text-left">Day</th>
					<th class="text-left">Date</th>
					<th class="text-left">Completed Users</th>
					<th class="text-left">Completion %</th>
				</tr>
			</thead>
			<tbody>
				@forelse($week['daily'] as $day)
					<tr>
						<td>{{ $day['label'] }}</td>
						<td>{{ $day['date'] }}</td>
						<td>{{ $day['count'] }} / {{ $week['totals']['karyawan'] }}</td>
						<td>{{ $day['percent'] }}%</td>
					</tr>
				@empty
					<tr><td colspan="4" class="text-center">No selections recorded yet.</td></tr>
				@endforelse
			</tbody>
		</x-table>
	</div>

	<div class="card p-3 p-md-4 mb-4">
		<h6 class="mb-3 text-center text-md-start">Pending Karyawan</h6>
		@if(count($week['pending_users']) === 0)
			<div class="small text-success text-center text-md-start">All karyawan have submitted selections.</div>
		@else
			<x-table id="report-pending-users" caption="Users needing action" use-datatable>
				<thead>
					<tr>
						<th class="text-left">Name</th>
						<th class="text-left">Username</th>
						<th class="text-left">Missing Days</th>
					</tr>
				</thead>
				<tbody>
					@foreach($week['pending_users'] as $pending)
						<tr>
							<td>{{ $pending['name'] }}</td>
							<td>{{ $pending['username'] }}</td>
							<td>{{ implode(', ', $pending['missing_labels']) }}</td>
						</tr>
					@endforeach
				</tbody>
			</x-table>
		@endif
	</div>

	<div class="card p-3 p-md-4 mb-4">
		<h6 class="mb-3 text-center text-md-start">Pending Weekly Exports</h6>
		<x-table id="report-pending-weeks" caption="Unexported weeks">
			<thead>
				<tr>
					<th class="text-left">Week Code</th>
					<th class="text-left">Created</th>
					<th class="text-left">Status</th>
					<th class="text-left">Actions</th>
				</tr>
			</thead>
			<tbody>
				@forelse($pending_reports as $pending)
					<tr>
						<td>{{ $pending['code'] }}</td>
						<td>{{ $pending['created_at_label'] }}</td>
						<td>
							<span class="badge {{ $pending['status_badge_class'] }}">{{ $pending['status_label'] }}</span>
							@if(!empty($pending['status_note']))
								<div class="small text-muted mt-1">{{ $pending['status_note'] }}</div>
							@endif
						</td>
						<td>
							<form class="export-form d-inline" method="POST"
								action="{{ $pending['export_route'] }}"
								data-week="{{ $pending['code'] }}"
								data-available="{{ $pending['available_label'] }}"
								data-finalizes="{{ $role === 'bm' ? '1' : '0' }}"
								data-pending-warning="{{ $pending['pending_warning'] }}"
								@if(!empty($pending['repeat_warning'])) data-repeat-warning="{{ e($pending['repeat_warning']) }}" @endif>
								@csrf
								<button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
									<i class="bx bx-download"></i>
									<span>Export</span>
								</button>
							</form>
							@if(!$pending['can_export'])
								<small class="text-muted d-block">Available on {{ $pending['available_label'] }}</small>
							@endif
							@if(!empty($pending['incomplete_summary']))
								<small class="text-muted d-block">{{ $pending['incomplete_summary'] }}</small>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="4" class="text-center">No pending weeks.</td></tr>
				@endforelse
			</tbody>
		</x-table>
	</div>

	<div class="card p-3 p-md-4 mb-5">
		<h6 class="mb-3 text-center text-md-start">Export History</h6>
		<x-table id="report-history" caption="Export log">
			<thead>
				<tr>
					<th class="text-left">Week Code</th>
					<th class="text-left">Exported At</th>
					<th class="text-left">Exported By</th>
				</tr>
			</thead>
			<tbody>
				@forelse($history_reports as $history)
					<tr>
						<td>{{ $history['code'] }}</td>
						<td>{{ $history['exported_at_label'] }}</td>
						<td>{{ $history['exported_by_label'] }}</td>
					</tr>
				@empty
					<tr><td colspan="3" class="text-center">No exports recorded yet.</td></tr>
				@endforelse
			</tbody>
		</x-table>
	</div>
</div>
@endsection

@push('js')
<script>
	$(function () {
		const pendingTable = $('#report-pending-users');
		if (pendingTable.length && typeof pendingTable.DataTable === 'function') {
			const dtOptions = {
				language: typeof dataTableTranslate === 'function' ? dataTableTranslate('Pending Karyawan') : undefined,
				autoWidth: false,
				scrollX: true,
				order: [],
				pageLength: 10,
				lengthChange: false,
				columnDefs: [
					{ targets: 2, orderable: false },
				],
			};

			if ($.fn.dataTable && $.fn.dataTable.Responsive) {
				dtOptions.responsive = true;
			}

			pendingTable.DataTable(dtOptions);
		}
	});

	document.querySelectorAll('.export-form').forEach(form => {
		form.addEventListener('submit', function (event) {
			if (form.dataset.confirmed === '1') {
				return;
			}

			event.preventDefault();
			const week = form.dataset.week || 'selected week';
			const available = form.dataset.available;
	        const finalizes = form.dataset.finalizes === '1';
	        const title = (finalizes ? 'Export ' : 'Download ') + week + '?';
			const baseMessage = finalizes
			    ? 'Exporting will download the weekly report and close the selection window for this week. This action can only be done once.'
			    : 'Download the weekly report without changing the selection window status.';
			const pendingWarning = form.dataset.pendingWarning;
			const repeatWarning = form.dataset.repeatWarning;
			const warnings = [pendingWarning, repeatWarning].filter(message => !!message);
			const confirmLabel = finalizes ? 'Export' : 'Download';

			const swalConfig = {
				icon: 'warning',
				title,
				showCancelButton: true,
				confirmButtonText: confirmLabel,
				cancelButtonText: 'Cancel',
				customClass: {
					confirmButton: 'btn btn-primary',
					cancelButton: 'btn btn-secondary'
				},
				buttonsStyling: false,
				footer: available ? 'Available since ' + available : ''
			};

			if (warnings.length > 0) {
				const warningHtml = warnings
					.map(message => `<div class="text-danger mt-2 small">${message}</div>`)
					.join('');
				swalConfig.html = `<div>${baseMessage}</div>${warningHtml}`;
			} else {
				swalConfig.text = baseMessage;
			}

			Swal.fire(swalConfig).then(result => {
				if (result.isConfirmed) {
					form.dataset.confirmed = '1';

					const formData = new FormData(form);
					const action = form.getAttribute('action');
					const method = form.getAttribute('method') || 'POST';

					Swal.fire({
						title: 'Preparing download…',
						allowOutsideClick: false,
						allowEscapeKey: false,
						didOpen: () => {
							Swal.showLoading();
						},
					});

					fetch(action, {
						method,
						body: formData,
						headers: {
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
					})
						.then(response => {
							if (!response.ok) {
								throw new Error('Server responded with status ' + response.status);
							}

							const disposition = response.headers.get('Content-Disposition') || '';
							return response.blob().then(blob => ({ blob, disposition }));
						})
						.then(({ blob, disposition }) => {
							const filenameMatch = /filename="?([^";]+)"?/i.exec(disposition);
							const fallbackName = `${form.dataset.week || 'weekly-report'}.xlsx`;
							const filename = filenameMatch ? filenameMatch[1] : fallbackName;

							const url = window.URL.createObjectURL(blob);
							const link = document.createElement('a');
							link.href = url;
							link.download = filename;
							document.body.appendChild(link);
							link.click();
							document.body.removeChild(link);
							window.URL.revokeObjectURL(url);

							Swal.close();
							window.location.reload();
						})
						.catch(error => {
							Swal.fire({
								icon: 'error',
								title: 'Export failed',
								text: error.message || 'Unable to download the report. Please try again.',
							});
							form.dataset.confirmed = '0';
						});
				}
			});
		});
	});
</script>
@endpush
