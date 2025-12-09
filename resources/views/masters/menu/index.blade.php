@extends('layouts.app', ['title' => $title])

@section('content')
<div class="space-y-6">
	@php
		$role = auth()->user()->role ?? 'guest';

		$selectionWindowReady = $selectionWindowReady ?? false;
		$selectionWindowOpen  = $selectionWindowOpen ?? false;
		$windowOpen           = $windowOpen ?? $selectionWindowOpen ?? false;

		$creationWeekCode         = $creationWeekCode ?? $weekCode ?? null;
		$creationRangeStart       = $creationRangeStart ?? null;
		$creationRangeEnd         = $creationRangeEnd ?? null;
		$creationAutoAdvanceWeeks = $creationAutoAdvanceWeeks ?? 0;
		$creationSkippedWeeks     = $creationSkippedWeeks ?? [];
		$optionsDetailedByDay     = $optionsDetailedByDay ?? [];
		$optionsByDay             = $optionsByDay ?? [];
		$days                     = $days ?? [];
		$vendorDayOrder           = $vendorDayOrder ?? [];
		$vendorDays               = $vendorDays ?? [];
		$vendorLabels             = $vendorLabels ?? ['vendorA' => 'Vendor A', 'vendorB' => 'Vendor B'];

		$windowReady  = $windowReady ?? $selectionWindowReady ?? false;
		$dayOrder     = ['Mon','Tue','Wed','Thu'];

		$tz = config('app.timezone', 'Asia/Jakarta');
		$todayDowIso = \Carbon\Carbon::now($tz)->dayOfWeekIso;
		$windowEligible = in_array(
			$todayDowIso,
			[\Carbon\Carbon::MONDAY, \Carbon\Carbon::TUESDAY, \Carbon\Carbon::WEDNESDAY, \Carbon\Carbon::THURSDAY, \Carbon\Carbon::FRIDAY],
			true
		);
	@endphp

	@if(in_array($role, ['admin','bm']))
		{{-- ================= ADMIN / BM LAYOUT ================= --}}
		<h5 class="mt-3 mb-3 d-flex justify-content-between align-items-center">
			Manage Menus
			<x-button id="btn-open-add-menu-modal" label="Add Next Week Menu" icon="bx-plus" />
		</h5>

		{{-- Selection Window Info --}}
		<div class="alert @if($selectionWindowReady) alert-success @else alert-warning @endif d-flex flex-wrap justify-content-between align-items-center gap-2">
			<div>
				<strong>Selection Window:</strong>
				{{ $selectionWindowReady ? 'Ready' : 'Pending' }} for week
				<code>{{ $weekCode ?? '—' }}</code>.
				@if($selectionWindowReady && $selectionWindowOpen)
					<span class="badge bg-success ms-1">Currently Open</span>
				@elseif($selectionWindowReady)
					<span class="badge bg-secondary ms-1">Awaiting Wed–Fri window</span>
				@else
					<span class="badge bg-secondary ms-1">Hidden from karyawan</span>
				@endif
				<div class="small text-muted mt-1">
					Toggle this once menus are final so karyawan can start choosing during Wed–Fri.
				</div>
			</div>
			<form id="toggle-window-form" method="POST" action="{{ route('masterMenu.toggleWindow') }}" class="mb-0">
				@csrf
				<input type="hidden" name="status" value="{{ $selectionWindowReady ? 'close' : 'open' }}">
				<button
					type="submit"
					class="btn btn-sm @if($selectionWindowReady) btn-outline-danger @else btn-primary @endif d-inline-flex align-items-center gap-1"
				>
					<i class="bx @if($selectionWindowReady) bx-lock-open-alt @else bx-check-circle @endif"></i>
					{{ $selectionWindowReady ? 'Close Window' : 'Open Selection Window' }}
				</button>
			</form>
		</div>

		@if(!$windowEligible && !$selectionWindowReady)
			<div class="alert alert-light border-start border-3 border-warning text-muted small mt-2 mb-0">
				Heads up: even after opening, karyawan can only submit choices on Wednesday through Friday.
			</div>
		@endif

		{{-- Creation Target Info --}}
		@if($creationWeekCode)
			@php
				$creationStartLabel = $creationRangeStart ? \Carbon\Carbon::parse($creationRangeStart)->format('D, d M Y') : null;
				$creationEndLabel   = $creationRangeEnd   ? \Carbon\Carbon::parse($creationRangeEnd)->format('D, d M Y')   : null;
			@endphp
			<div class="alert alert-info small d-flex flex-column gap-1 mt-3">
				<div>
					<strong>Next menu additions target:</strong>
					Week <code>{{ $creationWeekCode }}</code>
					@if($creationStartLabel && $creationEndLabel)
						<span class="text-muted">({{ $creationStartLabel }} – {{ $creationEndLabel }})</span>
					@endif
				</div>
				@if(!empty($creationSkippedWeeks))
					<div>
						Previous upcoming weeks already have full menus:
						@foreach($creationSkippedWeeks as $skip)
							<span class="badge bg-secondary me-1">
								{{ $skip['code'] }} ({{ $skip['existing_count'] }} items)
							</span>
					@endforeach
				</div>
				@endif
			</div>
		@endif

		{{-- Upcoming Week Menus Preview --}}

		<div class="d-flex align-items-center justify-content-between mb-3">
			<div>
				@foreach($dayOrder as $idx => $label)
					<button
						type="button"
						class="btn btn-sm me-1 admin-day-tab-btn @if($idx===0) btn-primary @else btn-outline-primary @endif"
						data-day-label="{{ $label }}"
					>
						{{ $label }}
					</button>
				@endforeach
			</div>
			<div class="btn-group btn-group-sm" role="group">
				<button type="button" class="btn btn-outline-secondary" id="admin-day-prev-btn">Prev</button>
				<button type="button" class="btn btn-outline-secondary" id="admin-day-next-btn">Next</button>
			</div>
		</div>

		<div class="position-relative" style="min-height: 520px;">
			@foreach($dayOrder as $idx => $label)
				@php
					$groups = $optionsDetailedByDay[$label] ?? [];
				@endphp
				<div
					class="admin-day-slide @if($idx!==0) d-none @endif"
					data-day-label="{{ $label }}"
				>
					<div class="small fw-semibold mb-2">
						{{ $label }}
						@if(!empty($days[$label]['date_label']))
							<span class="text-muted ms-2">{{ $days[$label]['date_label'] }}</span>
						@endif
					</div>

					@if($groups)
						<div class="row g-3">
							@foreach($groups as $group)
								<div class="col-md-6">
									<div class="border rounded p-3 bg-light position-relative" style="height:100%;">
										<button
											type="button"
											class="btn btn-sm btn-outline-primary position-absolute edit-menu-image-btn"
											style="top:12px; right:12px;"
											data-week="{{ $weekCode ?? $creationWeekCode ?? '' }}"
											data-day="{{ strtoupper(substr($label, 0, 3)) }}"
											data-catering="{{ $group['catering'] ?? '' }}"
											data-label="{{ $group['catering_label'] ?? '' }}"
											data-image="{{ $group['image_url'] ?? '' }}"
										>
											<i class="bx bx-edit"></i>
											<span class="ms-1">Edit Image</span>
										</button>

										@if(!empty($group['image_url']))
											<img
												src="{{ $group['image_url'] }}"
												alt="{{ $group['catering_label'] ?? 'Vendor' }}"
												class="rounded mb-2"
												style="width:100%;height:350px;object-fit:cover"
											>
										@else
											<div
												class="d-flex align-items-center justify-content-center bg-secondary text-white rounded mb-2"
												style="width:100%;height:350px;font-size:14px"
											>
												No Img
											</div>
										@endif

										<div class="w-100">
											<div class="fw-semibold mb-1">
												{{ $group['catering_label'] ?? ($group['catering'] ? Str::title(str_replace(['_', '-'], ' ', $group['catering'])) : 'Vendor') }}
											</div>
											<div class="small text-muted mb-2">
												2 menus from this catering:
											</div>
											<ul class="ps-3 small mb-0">
												@foreach($group['menus'] as $menu)
													<li class="mb-1" title="{{ $menu['name'] }}">
														{{ Str::limit($menu['name'], 60) }}
														<span class="text-muted">
															<code>{{ $menu['code'] }}</code>
														</span>
													</li>
												@endforeach
											</ul>
										</div>
									</div>
								</div>
							@endforeach
						</div>
					@else
						<div
							class="empty-menu-placeholder text-center text-muted px-3 py-5 rounded"
							data-day-name="{{ $label }}"
						>
							<i class="bx bx-restaurant bx-sm text-primary chef-icon mb-2"></i>
							<p class="fw-semibold mb-1">Menus are still cooking for {{ $label }}.</p>
							<p class="small mb-0 placeholder-tip">Fresh picks land soon.</p>
						</div>
					@endif
				</div>
				@endforeach
				</div>

	@elseif($role === 'vendor')
		{{-- ================= VENDOR LAYOUT ================= --}}
		@php
			$vendorLabel = isset($vendorCatering) ? ucfirst($vendorCatering) : 'Vendor';
			$vendorWeekLabel = $vendorWeekCode ?? $weekCode ?? '—';
			$vendorRangeLabelStart = $vendorRangeStart ? \Carbon\Carbon::parse($vendorRangeStart)->format('D, d M Y') : null;
			$vendorRangeLabelEnd   = $vendorRangeEnd   ? \Carbon\Carbon::parse($vendorRangeEnd)->format('D, d M Y')   : null;
		@endphp

		<h5 class="mt-3 mb-3">Upload Menu - {{ $vendorLabel }}</h5>

		<div class="alert alert-info small">
			<div>
				<strong>Target Week:</strong>
				<code>{{ $vendorWeekLabel }}</code>
				@if($vendorRangeLabelStart && $vendorRangeLabelEnd)
					<span class="ms-1">({{ $vendorRangeLabelStart }} – {{ $vendorRangeLabelEnd }})</span>
				@endif
			</div>
			<div class="mt-1">
				Isi nama menu dan unggah gambar untuk setiap opsi (A &amp; B) per hari.
				Gunakan tombol panah untuk berpindah hari dan perhatikan ikon status pada setiap opsi.
			</div>
		</div>

		@if(empty($vendorDayOrder) || empty($vendorDays))
			<div class="alert alert-warning small">Tidak ada minggu yang memerlukan menu baru saat ini.</div>
		@else
			<div class="vendor-slider-toolbar d-flex flex-wrap justify-content-between align-items-center mb-3">
				<div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
					<span class="badge bg-light text-dark text-uppercase">Week {{ $vendorWeekLabel }}</span>
					@if($vendorRangeLabelStart && $vendorRangeLabelEnd)
						<span class="text-muted small">{{ $vendorRangeLabelStart }} – {{ $vendorRangeLabelEnd }}</span>
					@endif
				</div>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary" id="vendor-slider-prev">
						<i class="bx bx-chevron-left"></i>
						<span class="d-none d-sm-inline ms-1">Prev</span>
					</button>
					<button type="button" class="btn btn-outline-secondary" id="vendor-slider-next">
						<span class="d-none d-sm-inline me-1">Next</span>
						<i class="bx bx-chevron-right"></i>
					</button>
				</div>
			</div>

			<div class="vendor-slider-wrapper">
				<div class="vendor-slider-track">
					@foreach($vendorDayOrder as $vendorDayLabel)
						@php
							$vendorDay = $vendorDays[$vendorDayLabel] ?? null;
						@endphp
						@if(!$vendorDay)
							@continue
						@endif
						@php
							$options = $vendorDay['options'] ?? [];
							$totalOptions = count($options);
							$completedOptions = 0;
							foreach ($options as $tempOption) {
								if (!empty($tempOption['has_menu'])) {
									$completedOptions++;
								}
							}
							$dayComplete = $totalOptions > 0 && $completedOptions === $totalOptions;
							$dayBadgeClass = $dayComplete ? 'bg-success' : 'bg-warning text-dark';
							$dayBadgeText = $dayComplete ? 'Complete' : 'Needs menu';
						@endphp
						<div class="vendor-slide" data-day-index="{{ $loop->index }}">
							<div class="card shadow-sm border-0 vendor-day-card h-100" data-day="{{ $vendorDay['day_code'] ?? '' }}">
								<div class="card-body d-flex flex-column">
									<div class="d-flex justify-content-between align-items-start mb-3">
										<div>
											<div class="fw-semibold">{{ $vendorDay['label'] ?? $vendorDayLabel }}</div>
											@if(!empty($vendorDay['date_label']))
												<div class="small text-muted">{{ $vendorDay['date_label'] }}</div>
											@endif
											<div class="small text-muted mt-1 vendor-day-summary">
												@if($totalOptions)
													{{ $completedOptions }} dari {{ $totalOptions }} opsi siap
												@else
													Belum ada opsi untuk hari ini.
												@endif
											</div>
										</div>
										<span class="badge vendor-day-status {{ $dayBadgeClass }}">{{ $dayBadgeText }}</span>
									</div>
									<div class="flex-grow-1">
										@php
											$optionA = $options['A'] ?? null;
										@endphp
										<div class="mb-3">
											<label class="form-label small mb-1">Gambar Menu (untuk Opsi A &amp; B)</label>
											<input type="file" class="form-control form-control-sm vendor-menu-image-day" accept="image/*">
											<div class="mt-2 vendor-preview-wrapper">
												@if(!empty($optionA['image_url']))
													<img src="{{ $optionA['image_url'] }}" alt="{{ $optionA['name'] ?? 'Menu image' }}" class="img-fluid rounded vendor-menu-preview" style="max-height:200px;object-fit:cover;">
												@else
													<div class="text-muted small fst-italic">Belum ada gambar.</div>
												@endif
											</div>
										</div>
										<div class="row g-3">
											<div class="col-12 col-md-6">
												@php
													$optionAComplete = $optionA && !empty($optionA['has_menu']);
													$optionAIcon = $optionAComplete ? 'bx-check-circle' : 'bx-time-five';
													$optionAIconClass = $optionAComplete ? 'text-success' : 'text-warning';
													$optionAStateClass = $optionAComplete ? 'is-complete' : 'is-pending';
												@endphp
												<div
													class="vendor-option-card vendor-menu-card {{ $optionAStateClass }}"
													data-day="{{ $vendorDay['day_code'] ?? '' }}"
													data-option="A"
												>
													<div class="d-flex justify-content-between align-items-center mb-2">
														<div class="d-flex align-items-center">
															<i class="bx {{ $optionAIcon }} vendor-option-status-icon {{ $optionAIconClass }} me-2"></i>
															<span class="fw-semibold">Opsi A</span>
														</div>
														<span class="badge vendor-option-status {{ $optionAComplete ? 'bg-success' : 'bg-secondary' }}">
															{{ $optionAComplete ? 'Sudah ada' : 'Belum ada' }}
														</span>
													</div>
													<div class="mb-2">
														<label class="form-label small mb-1">Nama Menu A</label>
														<input type="text" class="form-control form-control-sm vendor-menu-name" value="{{ $optionA['name'] ?? '' }}" placeholder="Masukkan nama menu A">
													</div>
												</div>
											</div>
											<div class="col-12 col-md-6">
												@php
													$optionB = $options['B'] ?? null;
													$optionBComplete = $optionB && !empty($optionB['has_menu']);
													$optionBIcon = $optionBComplete ? 'bx-check-circle' : 'bx-time-five';
													$optionBIconClass = $optionBComplete ? 'text-success' : 'text-warning';
													$optionBStateClass = $optionBComplete ? 'is-complete' : 'is-pending';
												@endphp
												<div
													class="vendor-option-card vendor-menu-card {{ $optionBStateClass }}"
													data-day="{{ $vendorDay['day_code'] ?? '' }}"
													data-option="B"
												>
													<div class="d-flex justify-content-between align-items-center mb-2">
														<div class="d-flex align-items-center">
															<i class="bx {{ $optionBIcon }} vendor-option-status-icon {{ $optionBIconClass }} me-2"></i>
															<span class="fw-semibold">Opsi B</span>
														</div>
														<span class="badge vendor-option-status {{ $optionBComplete ? 'bg-success' : 'bg-secondary' }}">
															{{ $optionBComplete ? 'Sudah ada' : 'Belum ada' }}
														</span>
													</div>
													<div class="mb-2">
														<label class="form-label small mb-1">Nama Menu B</label>
														<input type="text" class="form-control form-control-sm vendor-menu-name" value="{{ $optionB['name'] ?? '' }}" placeholder="Masukkan nama menu B">
													</div>
												</div>
											</div>
										</div>
										<div class="d-flex justify-content-end mt-3">
											<button type="button" class="btn btn-primary btn-sm vendor-save-day-button">
												<i class="bx bx-save"></i>
												<span class="ms-1">Simpan</span>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					@endforeach
				</div>
			</div>
		@endif

	@else
		{{-- ================= KARYAWAN LAYOUT ================= --}}
		@php
			$totalDays     = 0;
			$selectedCount = 0;
			$pendingCount  = 0;

			foreach ($dayOrder as $orderLabel) {
				$dayEntry = $days[$orderLabel] ?? null;
				if (!$dayEntry) {
					continue;
				}

				$totalDays += 1;
				if (empty($dayEntry['selected'])) {
					$pendingCount += 1;
				} else {
					$selectedCount += 1;
				}
			}

			$windowStatusLabel = $windowOpen
				? 'Open'
				: ($windowReady ? 'Ready Soon' : 'Closed');

			$windowStatusBadgeClass = $windowOpen
				? 'bg-success'
				: ($windowReady ? 'bg-success text-white' : 'bg-danger');
			$pendingBadgeClass  = $pendingCount ? 'bg-warning text-white' : 'bg-success';
			$pendingCardClass   = $pendingCount ? 'card-border-shadow-warning' : 'card-border-shadow-primary';
			$selectionPercent   = $totalDays ? (int) round(($selectedCount / max(1, $totalDays)) * 100) : 0;
		@endphp

		<div class="row g-3 mt-4 mb-3">
			<div class="col-md-6 col-lg-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body py-3">
						<div class="small text-muted mb-1">Selection Window</div>
						<div class="d-flex align-items-center justify-content-between">
							<span class="fw-semibold">Week <code>{{ $weekCode ?? '—' }}</code></span>
							<span class="badge {{ $windowStatusBadgeClass }}">{{ $windowStatusLabel }}</span>
						</div>
						<div class="small text-muted mt-2">
							@if($windowOpen)
								Window is open; you can adjust selections until Friday.
							@elseif($windowReady)
								Window opens Wed-Fri. Check back to submit choices.
							@else
								Admin is still preparing menus. Your last saved choices stay on hold.
							@endif
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-lg-4">
				<div class="card border-0 shadow-sm h-100 {{ $pendingCardClass }}">
					<div class="card-body py-3">
						<div class="small text-muted mb-1">Pending Days</div>
						<div class="d-flex align-items-center justify-content-between">
							<span class="display-6 fw-semibold" id="label-pending">{{ $pendingCount }}</span>
							<span class="badge {{ $pendingBadgeClass }}">{{ $pendingCount ? 'Butuh Dipilih' : 'Semua Dipilih' }}</span>
						</div>
						<div class="small text-muted mt-2">
							@if($pendingCount)
								Select at least one menu for each pending day to finish.
							@else
								Every day has a saved choice. You can still make changes while the window is open.
							@endif
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 col-lg-4">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-body py-3">
						<div class="small text-muted mb-1">Saved Choices</div>
						<div class="d-flex align-items-center justify-content-between">
							<span class="fw-semibold">{{ $selectedCount }} / {{ $totalDays }}</span>
							<span class="badge bg-primary">{{ $selectionPercent }}%</span>
						</div>
						<div class="small text-muted mt-2">
							@if($totalDays === 0)
								Menus will appear once admin opens the window for this week.
							@elseif($selectedCount === $totalDays)
								All lunches covered. Feel free to tweak while the window stays open.
							@else
								{{ $pendingCount }} {{ Str::plural('day', $pendingCount) }} still need a choice before Friday.
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="card p-3 mb-3">
			<h6 class="mb-2">
				Select Menus For Week <code>{{ $weekCode }}</code>
			</h6>

			@if(isset($rangeStart, $rangeEnd))
				<p class="small text-muted mb-2">
					For lunches from
					<strong>{{ \Carbon\Carbon::parse($rangeStart)->format('D, d M Y') }}</strong>
					to
					<strong>{{ \Carbon\Carbon::parse($rangeEnd)->format('D, d M Y') }}</strong>.
				</p>
			@endif

			{{-- Day carousel controls --}}
			<div class="d-flex align-items-center justify-content-between mb-2">
				<div>
					@foreach($dayOrder as $idx => $label)
						@php
							$d = $days[$label];
						@endphp
						<button
							type="button"
							class="btn btn-sm me-1 day-tab-btn @if($idx===0) btn-primary @else btn-outline-primary @endif"
							data-day-label="{{ $label }}"
						>
							{{ $label }}
							@if(!empty($d['locked']))
								<span class="badge bg-secondary ms-1">Locked</span>
							@endif
						</button>
					@endforeach
				</div>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary" id="day-prev-btn">Prev</button>
					<button type="button" class="btn btn-outline-secondary" id="day-next-btn">Next</button>
				</div>
			</div>

			<div class="position-relative" style="min-height: 520px;">
				@foreach($dayOrder as $idx => $label)
					@php
						$d      = $days[$label];
						$groups = $optionsDetailedByDay[$label] ?? [];
					@endphp

					<div
						class="day-slide @if($idx!==0) d-none @endif"
						data-day-label="{{ $label }}"
					>
						<div class="small fw-semibold mb-2">
							{{ $label }}
							@if(!empty($d['date_label']))
								<span class="text-muted ms-2">{{ $d['date_label'] }}</span>
							@endif
							@if(!empty($d['is_holiday']))
								<span class="badge bg-danger ms-1">Libur</span>
							@elseif(!empty($d['locked']))
								<span class="badge bg-secondary ms-1">Locked</span>
							@endif
						</div>

						<div
							class="menu-day-column"
							data-day-label="{{ $label }}"
							data-date="{{ $d['date'] }}"
						>
							@if(!empty($d['is_holiday']))
								<div class="alert alert-warning small mb-0">
									Hari ini adalah hari libur. Tidak perlu memilih menu.
								</div>
							@elseif($groups)
								<div class="row g-3">
									@foreach($groups as $group)
										@php
											$isGroupSelected = isset($d['selected'])
												&& collect($group['menus'])->contains(function($m) use ($d) {
													return $m['code'] === $d['selected'];
												});
										@endphp
										<div class="col-md-6">
											<div class="menu-select-card border rounded p-3 mb-3 bg-light position-relative @if($isGroupSelected) selected @endif">
												@if(!empty($group['image_url']))
													<img
														src="{{ $group['image_url'] }}"
														alt="{{ $group['catering_label'] ?? 'Vendor' }}"
														style="width:100%;height:350px;object-fit:cover"
														class="rounded mb-2"
													>
												@else
													<div
														class="d-flex align-items-center justify-content-center bg-secondary text-white rounded mb-2"
														style="width:100%;height:350px;font-size:14px"
													>
														No Img
													</div>
												@endif

												<div class="w-100">
													<div class="fw-semibold mb-1">
														{{ $group['catering_label'] ?? ($group['catering'] ? Str::title(str_replace(['_', '-'], ' ', $group['catering'])) : 'Vendor') }}
													</div>
													<div class="small text-muted mb-2">
														Choose one of the two menus:
													</div>
													<ul class="ps-3 small list-unstyled mb-0">
														@foreach($group['menus'] as $menu)
															<li class="form-check mb-1" title="{{ $menu['name'] }}">
																<input
																	class="form-check-input menu-option-radio"
																	type="radio"
																	name="choice-{{ $label }}"
																	id="choice-{{ $label }}-{{ $menu['code'] }}"
																	value="{{ $menu['code'] }}"
																	@checked(isset($d['selected']) && $d['selected'] === $menu['code'])
																>
																<label class="form-check-label" for="choice-{{ $label }}-{{ $menu['code'] }}">
																	{{ Str::limit($menu['name'], 60) }}
																	<span class="text-muted">
																		<code>{{ $menu['code'] }}</code>
																	</span>
																</label>
															</li>
														@endforeach
													</ul>
												</div>

												@if($isGroupSelected)
													<span
														class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-primary"
														style="font-size:9px"
													>
														Chosen
													</span>
												@endif
											</div>
										</div>
									@endforeach
								</div>
							@else
								<div
									class="empty-menu-placeholder text-center text-muted px-3 py-5 rounded"
									data-day-name="{{ $label }}"
								>
									<i class="bx bx-restaurant bx-sm text-primary chef-icon mb-2"></i>
									<p class="fw-semibold mb-1">Hang tight, {{ $label }}'s menu is brewing.</p>
									<p class="small mb-0 placeholder-tip">Check back soon for the tastiest picks.</p>
								</div>
							@endif

							@if(empty($d['locked']) && $windowOpen && $groups)
								<div class="small text-muted mt-1">
									Choose one menu; use the tabs or Prev/Next to switch days.
								</div>
							@endif
						</div>
					</div>
				@endforeach
			</div>

			@if($windowOpen)
				<div class="mt-3 d-flex justify-content-end">
					<x-button id="btn-save-weekly-choices" label="Save Choices" icon="bx-save" />
				</div>
			@else
				<div class="alert alert-info p-2 mt-3 small">
					@if($windowReady)
						Selection window closed. Choices shown (if any) for reference.
					@else
						Waiting for admin to release the selection window.
					@endif
				</div>
			@endif
		</div>
	@endif
</div>
@endsection

@section('modal')
@parent
<div id="add-menu-modal-template" class="d-none">
		<div class="text-start">
		<p class="mb-2 small text-muted">
			Create weekly menus: {{ $vendorLabels['vendorA'] ?? 'Vendor A' }} and {{ $vendorLabels['vendorB'] ?? 'Vendor B' }} each receive <strong>Opsi A</strong> &amp; <strong>Opsi B</strong> automatically.
		</p>

		<div class="mb-2">
			<label class="form-label mb-0">Day</label>
			<select id="add-menu-day" class="form-select form-select-sm" required>
				<option value="MON">MON</option>
				<option value="TUE">TUE</option>
				<option value="WED">WED</option>
				<option value="THU">THU</option>
			</select>
		</div>

		<div class="mb-3">
			<h6 class="small fw-semibold mb-1">{{ $vendorLabels['vendorA'] ?? 'Vendor A' }}</h6>
			<div class="mb-1">
				<label class="form-label mb-0">Image (covers {{ $vendorLabels['vendorA'] ?? 'Vendor A' }} Opsi A &amp; Opsi B)</label>
				<input id="vendor-a-image-file" type="file" accept="image/*" class="form-control form-control-sm" required />
				<small class="text-muted">Required. Max 2MB. JPG/PNG/WEBP.</small>
				<div class="mt-2">
					<img
						id="vendor-a-image-preview"
						src=""
						alt="{{ ($vendorLabels['vendorA'] ?? 'Vendor A') . ' preview' }}"
						class="img-fluid rounded shadow-sm d-none"
						style="max-height:200px;object-fit:cover;"
					/>
				</div>
			</div>
		</div>

		<div class="mb-1 border-top pt-2">
			<h6 class="small fw-semibold mb-1">{{ $vendorLabels['vendorB'] ?? 'Vendor B' }}</h6>
			<div class="mb-1">
				<label class="form-label mb-0">Image (covers {{ $vendorLabels['vendorB'] ?? 'Vendor B' }} Opsi A &amp; Opsi B)</label>
				<input id="vendor-b-image-file" type="file" accept="image/*" class="form-control form-control-sm" required />
				<small class="text-muted">Required. Max 2MB. JPG/PNG/WEBP.</small>
				<div class="mt-2">
					<img
						id="vendor-b-image-preview"
						src=""
						alt="{{ ($vendorLabels['vendorB'] ?? 'Vendor B') . ' preview' }}"
						class="img-fluid rounded shadow-sm d-none"
						style="max-height:200px;object-fit:cover;"
					/>
				</div>
			</div>
		</div>

		<div class="alert alert-info p-2 mb-0 small">
			Names are fixed to <strong>Opsi A</strong> and <strong>Opsi B</strong>.
			Codes will be generated as <code>{{ $creationWeekCode ?? $weekCode }}-DAY-N</code> for N = 1..4 (2 per catering).
		</div>
	</div>
</div>

<div id="edit-menu-modal-template" class="d-none">
	<div class="text-start">
		<p class="small mb-2">Upload a new image for <strong>__LABEL__</strong> (__DAY__) — applies to both Opsi A and Opsi B.</p>
		<input id="edit-menu-image-file" type="file" accept="image/*" class="form-control form-control-sm" required />
		<small class="text-muted">Max 2MB. JPG/PNG/WEBP.</small>
		<div class="mt-2">
			<img
				id="edit-menu-image-preview"
				src=""
				alt="Updated image preview"
				class="img-fluid rounded shadow-sm d-none"
				style="max-height:200px;object-fit:cover;"
			/>
		</div>
	</div>
</div>
@endsection

@push('css')
<style>
.empty-menu-placeholder {
	background-color: #f8fafc;
	border: 1px dashed rgba(13, 110, 253, 0.35);
}

.empty-menu-placeholder .chef-icon {
	font-size: 2.5rem;
	animation: menu-float 3.2s ease-in-out infinite;
}

@keyframes menu-float {
	0% { transform: translateY(0); }
	50% { transform: translateY(-6px); }
	100% { transform: translateY(0); }
}

.vendor-slider-toolbar {
	gap: 0.75rem;
}

.vendor-slider-toolbar .btn[disabled] {
	opacity: 0.6;
	pointer-events: none;
}

.vendor-slider-wrapper {
	position: relative;
	overflow: hidden;
	margin: 0 -0.25rem;
	--vendor-slides-per-view: 1;
}

.vendor-slider-track {
	display: flex;
	transition: transform 0.45s ease;
}

.vendor-slide {
	flex: 0 0 calc(100% / var(--vendor-slides-per-view));
	max-width: calc(100% / var(--vendor-slides-per-view));
	padding: 0 0.25rem;
	box-sizing: border-box;
}

.vendor-slide > .card {
	height: 100%;
}

.vendor-day-status {
	font-size: 0.75rem;
}

.vendor-option-card {
	border: 1px solid rgba(13, 110, 253, 0.15);
	border-radius: 0.75rem;
	padding: 1rem;
	background-color: #f8fafc;
}

.vendor-option-card.is-complete {
	border-color: rgba(25, 135, 84, 0.55);
	background-color: #edf7f1;
}

.vendor-option-card.is-pending {
	border-color: rgba(253, 126, 20, 0.3);
	background-color: #fff7ec;
}

.vendor-option-card + .vendor-option-card {
	margin-top: 1rem;
}

.vendor-option-status-icon {
	font-size: 1.25rem;
}

.vendor-slider-wrapper.is-stacked {
	overflow: visible;
	margin: 0;
}

.vendor-slider-wrapper.is-stacked .vendor-slider-track {
	flex-direction: column;
	transform: none !important;
}

.vendor-slider-wrapper.is-stacked .vendor-slide {
	max-width: 100%;
	flex: 1 0 auto;
	padding: 0;
}

@media (min-width: 768px) {
	.vendor-slider-wrapper {
		margin: 0 -0.5rem;
	}

	.vendor-slide {
		padding: 0 0.5rem;
	}
}

@media (min-width: 992px) {
	.vendor-slider-wrapper {
		--vendor-slides-per-view: 2;
	}
}
</style>
@endpush

@push('js')
<script>
const WEEK_CODE         = @json($weekCode ?? null);
const CREATE_WEEK_CODE  = @json($creationWeekCode ?? $weekCode ?? null);
const MENU_DETAILS      = @json($menuDetails ?? []);
const VENDOR_DAY_DATA   = @json($vendorDays ?? []);
const VENDOR_DAY_ORDER  = @json($vendorDayOrder ?? []);
const VENDOR_CATERING   = @json($vendorCatering ?? null);
const VENDOR_LABELS     = @json($vendorLabels ?? ['vendorA' => 'Vendor A', 'vendorB' => 'Vendor B']);
const VENDOR_MENU_ENDPOINT = @json(route('vendorMenu.store'));
const WINDOW_OPEN       = @json($windowOpen ?? false);
const WINDOW_READY      = @json($selectionWindowReady ?? false);
const WINDOW_ELIGIBLE   = @json($windowEligible ?? false);
const WINDOW_STATUS_URL = @json(route('masterMenu.windowStatus'));
const USER_ROLE         = @json($role ?? null);

const windowStatusState = {
	ready: !!WINDOW_READY,
	open: !!WINDOW_OPEN,
	week: WEEK_CODE,
};

const setupFilePreview = (input, preview, fallbackSrc = '') => {
	if (!input || !preview) return;

	const togglePreview = (src) => {
		if (src) {
			preview.src = src;
			preview.classList.remove('d-none');
		} else {
			preview.removeAttribute('src');
			preview.classList.add('d-none');
		}
	};

	if (fallbackSrc) {
		togglePreview(fallbackSrc);
	}

	input.addEventListener('change', () => {
		const file = input.files && input.files[0];
		if (!file) {
			togglePreview(fallbackSrc);
			return;
		}

		const reader = new FileReader();
		reader.onload = (event) => {
			const result = event.target && typeof event.target.result === 'string'
				? event.target.result
				: '';
			togglePreview(result || fallbackSrc);
		};
		reader.onerror = () => togglePreview(fallbackSrc);
		reader.readAsDataURL(file);
	});
};

const notifyWindowChange = (icon, title, text) => {
	if (typeof window.Swal !== 'undefined') {
		Swal.fire({ icon, title, text });
		return;
	}

	if (window.console && console.info) {
		console.info(`[${String(icon || 'info').toUpperCase()}] ${title}: ${text}`);
	}
};

const refreshChoiceInteractivity = () => {
	const disableAll = !windowStatusState.open;
	const columns = document.querySelectorAll('.menu-day-column');

	columns.forEach(col => {
		const slide = col.closest('.day-slide');
		const lockedBadge = slide ? slide.querySelector('.badge.bg-secondary') : null;
		const locked = !!lockedBadge;
		const disable = disableAll || locked;

		col.querySelectorAll('.menu-option-radio').forEach(radio => {
			radio.disabled = disable;
		});
	});

	const saveBtn = document.getElementById('btn-save-weekly-choices');
	if (saveBtn) {
		if (disableAll) {
			saveBtn.setAttribute('disabled', 'disabled');
			saveBtn.classList.add('disabled');
		} else {
			saveBtn.removeAttribute('disabled');
			saveBtn.classList.remove('disabled');
		}
	}
};

const handleWindowStatusChange = (status) => {
	if (!status || typeof status !== 'object') return;

	const ready = !!status.ready;
	const open = !!status.open;
	const week = status.week_code || windowStatusState.week;
	const stateChanged =
		ready !== windowStatusState.ready
		|| open !== windowStatusState.open
		|| week !== windowStatusState.week;

	if (ready && !windowStatusState.ready) {
		const text = open
			? 'Selection window is open again. Submit or adjust your choices before Friday.'
			: 'Window is ready again. You can submit once the Wed-Fri window opens.';
		notifyWindowChange('success', 'Selection window reopened', text);
	} else if (!ready && windowStatusState.ready) {
		notifyWindowChange(
			'info',
			'Selection window paused',
			'Admin is updating menus. Your saved choices remain intact.'
		);
	} else if (open !== windowStatusState.open) {
		if (open) {
			notifyWindowChange(
				'success',
				'Selection window open',
				'You can now submit or update your lunch choices until Friday.'
			);
		} else {
			notifyWindowChange(
				'info',
				'Selection window closed',
				'The window is temporarily closed. Please try again once it reopens.'
			);
		}
	}

	windowStatusState.ready = ready;
	windowStatusState.open = open;
	windowStatusState.week = week;

	refreshChoiceInteractivity();

	if (stateChanged) {
		setTimeout(() => {
			window.location.reload();
		}, 1200);
	}
};

// --- Rotate fun tips for empty menu states ---
(() => {
	const tips = [
		'Chef update: %DAY% options arrive soon once the kitchen signs off.',
		'Pro tip: check your lunch history if you need inspo while menus load.',
		'Good things take time. Fresh menus drop as soon as admin opens the window.',
		'Heads up: selection usually opens Wed-Fri, so swing back then for choices.'
	];

	const placeholders = document.querySelectorAll('.empty-menu-placeholder .placeholder-tip');
	if (!placeholders.length) return;

	placeholders.forEach((el, idx) => {
		const wrapper = el.closest('.empty-menu-placeholder');
		const dayName = wrapper ? wrapper.getAttribute('data-day-name') : null;
		const baseTip = tips[(idx + Math.floor(Math.random() * tips.length)) % tips.length];
		el.textContent = baseTip.replace('%DAY%', dayName || 'the day');
	});
})();

// --- Build interactive selection for karyawan view ---
(() => {
	if (USER_ROLE !== 'karyawan') return;

	document.querySelectorAll('.menu-day-column').forEach(col => {
		const slide = col.closest('.day-slide');
		const locked = !!(slide && slide.querySelector('.badge.bg-secondary'));
		if (locked) return;

		col.querySelectorAll('.menu-select-card').forEach(card => {
			const cardRadios = card.querySelectorAll('.menu-option-radio');
			cardRadios.forEach(radio => {
				radio.addEventListener('change', () => {
					if (!document.getElementById('btn-save-weekly-choices')) return;

					const anyChecked = Array.from(cardRadios).some(rr => rr.checked);
					if (anyChecked) {
						card.classList.add('selected');

						col.querySelectorAll('.menu-select-card').forEach(otherCard => {
							if (otherCard !== card) {
								otherCard.classList.remove('selected');
								otherCard.querySelectorAll('.badge.bg-primary').forEach(badge => badge.remove());
							}
						});

						card.querySelectorAll('.badge.bg-primary').forEach(badge => badge.remove());
						const badge = document.createElement('span');
						badge.className = 'position-absolute top-0 end-0 translate-middle badge rounded-pill bg-primary';
						badge.style.fontSize = '9px';
						badge.textContent = 'Chosen';
						card.appendChild(badge);
					} else {
						card.classList.remove('selected');
						card.querySelectorAll('.badge.bg-primary').forEach(badge => badge.remove());
					}
				});
			});
		});
	});
})();

// --- Day carousel controls (karyawan) ---
(() => {
	const dayOrder   = ['Mon','Tue','Wed','Thu'];
	let currentIndex = 0;
	const slides     = Array.from(document.querySelectorAll('.day-slide'));
	const tabButtons = Array.from(document.querySelectorAll('.day-tab-btn'));
	const prevBtn    = document.getElementById('day-prev-btn');
	const nextBtn    = document.getElementById('day-next-btn');

	const showSlide = (index) => {
		if (!slides.length) return;
		currentIndex = (index + slides.length) % slides.length;
		const label = dayOrder[currentIndex];

		slides.forEach(slide => {
			slide.classList.toggle('d-none', slide.getAttribute('data-day-label') !== label);
		});

		tabButtons.forEach(btn => {
			if (btn.getAttribute('data-day-label') === label) {
				btn.classList.remove('btn-outline-primary');
				btn.classList.add('btn-primary');
			} else {
				btn.classList.remove('btn-primary');
				btn.classList.add('btn-outline-primary');
			}
		});
	};

	tabButtons.forEach((btn, idx) => {
		btn.addEventListener('click', () => showSlide(idx));
	});

	if (prevBtn) prevBtn.addEventListener('click', () => showSlide(currentIndex - 1));
	if (nextBtn) nextBtn.addEventListener('click', () => showSlide(currentIndex + 1));

	showSlide(0);
})();

// --- Admin/BM day carousel controls (preview only) ---
(() => {
	const dayOrder   = ['Mon','Tue','Wed','Thu'];
	let currentIndex = 0;
	const slides     = Array.from(document.querySelectorAll('.admin-day-slide'));
	const tabButtons = Array.from(document.querySelectorAll('.admin-day-tab-btn'));
	const prevBtn    = document.getElementById('admin-day-prev-btn');
	const nextBtn    = document.getElementById('admin-day-next-btn');

	const showSlide = (index) => {
		if (!slides.length) return;
		currentIndex = (index + slides.length) % slides.length;
		const label = dayOrder[currentIndex];

		slides.forEach(slide => {
			slide.classList.toggle('d-none', slide.getAttribute('data-day-label') !== label);
		});

		tabButtons.forEach(btn => {
			if (btn.getAttribute('data-day-label') === label) {
				btn.classList.remove('btn-outline-primary');
				btn.classList.add('btn-primary');
			} else {
				btn.classList.remove('btn-primary');
				btn.classList.add('btn-outline-primary');
			}
		});
	};

	tabButtons.forEach((btn, idx) => {
		btn.addEventListener('click', () => showSlide(idx));
	});

	if (prevBtn) prevBtn.addEventListener('click', () => showSlide(currentIndex - 1));
	if (nextBtn) nextBtn.addEventListener('click', () => showSlide(currentIndex + 1));

	showSlide(0);
})();

// --- Save weekly choices (karyawan) ---
(() => {
	const saveBtn = document.getElementById('btn-save-weekly-choices');
	if (!saveBtn) return;

	saveBtn.addEventListener('click', () => {
		const dayCols  = document.querySelectorAll('.menu-day-column');
		const formData = {};
		const missing  = [];

		dayCols.forEach(col => {
			const date    = col.getAttribute('data-date');
			const checked = col.querySelector('.menu-option-radio:checked');
			if (checked) {
				formData['choices[' + date + ']'] = checked.value;
			} else {
				missing.push(col.getAttribute('data-day-label'));
			}
		});

		const selectedCount = Object.keys(formData).length;
		const performSave = () => {
			ajaxPost({
				url: '{{ route('karyawan.selections.save', $weekCode) }}',
				formData: formData,
				successCallback: (response) => {
					const pendingLabel = document.getElementById('label-pending');
					if (pendingLabel) {
						const remaining = Array
							.from(document.querySelectorAll('.menu-day-column'))
							.reduce((count, column) => count + (column.querySelector('.menu-option-radio:checked') ? 0 : 1), 0);

						pendingLabel.textContent = remaining.toString();
						pendingLabel.classList.toggle('text-warning', remaining > 0);
						pendingLabel.classList.toggle('text-primary', remaining === 0);

						const pendingCard = pendingLabel.closest('.card');
						if (pendingCard) {
							pendingCard.classList.toggle('card-border-shadow-warning', remaining > 0);
							pendingCard.classList.toggle('card-border-shadow-primary', remaining === 0);
						}
					}

					if (response && Array.isArray(response.saved)) {
						response.saved.forEach(savedDate => {
							const column = document.querySelector('.menu-day-column[data-date="' + savedDate + '"]');
							if (!column) return;
							column.setAttribute('data-saved', 'true');
						});
					}

					saveBtn.dataset.originalHtml  = saveBtn.dataset.originalHtml  || saveBtn.innerHTML;
					saveBtn.dataset.originalClass = saveBtn.dataset.originalClass || saveBtn.className;
					saveBtn.classList.add('btn-success');
					saveBtn.innerHTML = '<i class="bx bx-check"></i><span class="ms-1">Saved</span>';
					setTimeout(() => {
						saveBtn.className = saveBtn.dataset.originalClass || saveBtn.className;
						saveBtn.innerHTML  = saveBtn.dataset.originalHtml  || saveBtn.innerHTML;
					}, 2000);
				},
				errorCallback: (response) => {
					if (typeof Swal !== 'undefined') {
						Swal.fire({
							icon: 'error',
							title: 'Error',
							text: response.message || 'Failed to save choices.'
						});
					} else {
						alert(response.message || 'Failed to save choices.');
					}
				}
			});
		};

		if (!selectedCount) {
			if (typeof Swal !== 'undefined') {
				Swal.fire({
					icon: 'warning',
					title: 'No selections yet',
					text: 'Pilih minimal satu menu sebelum menyimpan.'
				});
			} else {
				alert('Pilih minimal satu menu sebelum menyimpan.');
			}
			return;
		}

		if (missing.length) {
			const missingList = missing.join(', ');
			if (typeof Swal !== 'undefined') {
				Swal.fire({
					icon: 'warning',
					title: 'Pilihan belum lengkap',
					html: 'Anda belum memilih menu untuk:<br><strong>' + missingList + '</strong><br><br>Simpan pilihan yang sudah ada sekarang?',
					showCancelButton: true,
					confirmButtonText: 'Ya, simpan',
					cancelButtonText: 'Batal'
				}).then(result => {
					if (result.isConfirmed) {
						performSave();
					}
				});
			} else {
				const proceed = window.confirm('Anda belum memilih menu untuk: ' + missingList + '. Simpan pilihan yang sudah ada?');
				if (proceed) {
					performSave();
				}
			}
			return;
		}

		performSave();
	});
})();

// --- Poll selection window status for karyawan ---
(() => {
	if (USER_ROLE !== 'karyawan' || !WINDOW_STATUS_URL) return;

	let pollTimer = null;
	const POLL_INTERVAL = 45000;

	const poll = () => {
		fetch(WINDOW_STATUS_URL, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
			},
			credentials: 'same-origin',
		})
			.then(response => {
				if (!response.ok) throw new Error('Status check failed');
				return response.json();
			})
			.then(handleWindowStatusChange)
			.catch(() => {
				// Silent retry on next interval.
			});
	};

	const startPolling = () => {
		if (pollTimer) return;
		poll();
		pollTimer = setInterval(poll, POLL_INTERVAL);
	};

	if (document.visibilityState === 'visible') {
		startPolling();
	}

	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible') {
			startPolling();
		} else if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	});
})();

// --- Real-time window updates via Echo ---
(() => {
	if (USER_ROLE !== 'karyawan') return;

	const MAX_ATTEMPTS = 20;
	let attempt = 0;

	const attachListener = () => {
		if (typeof window === 'undefined') return;
		const echo = window.Echo;
		if (!echo) {
			attempt += 1;
			if (attempt <= MAX_ATTEMPTS) {
				setTimeout(attachListener, 500);
			} else {
				console.warn('Selection window listener not attached: Echo unavailable.');
			}
			return;
		}

		try {
			echo.channel('selection-window')
				.listen('.selection.window.toggled', event => {
					if (!event) return;
					handleWindowStatusChange({
						ready: event.ready,
						open: event.open,
						week_code: event.week_code,
					});
				});
		} catch (error) {
			console.warn('Failed to initialize selection window listener', error);
		}
	};

	attachListener();
})();

// --- Admin toggle window guard ---
(() => {
	const form = document.getElementById('toggle-window-form');
	if (!form) return;

	const statusInput = form.querySelector('input[name="status"]');

	form.addEventListener('submit', (event) => {
		const opening = statusInput && statusInput.value === 'open';
		if (!opening || WINDOW_ELIGIBLE) {
			return;
		}

		if (typeof window.Swal === 'undefined') {
			return;
		}

		event.preventDefault();

		Swal.fire({
			icon: 'info',
			title: 'Heads up',
			text: 'Karyawan can only submit their choices between Wednesday and Friday, even after you open the window.',
			showCancelButton: true,
			confirmButtonText: 'Open anyway',
			cancelButtonText: 'Cancel',
		}).then(result => {
			if (result.isConfirmed) {
				form.submit();
			}
		});
	});
})();

// --- Admin Add Menu Modal ---
(() => {
	const addMenuBtn = document.getElementById('btn-open-add-menu-modal');
	if (!addMenuBtn) return;

	addMenuBtn.addEventListener('click', () => {
		const template = document.getElementById('add-menu-modal-template');
		if (!template) return;

		Swal.fire({
			title: 'Add Weekly Menu',
			html: template.innerHTML,
			width: 600,
			showCancelButton: true,
			confirmButtonText: 'Save',
			cancelButtonText: 'Cancel',
			didOpen: () => {
				const popup = Swal.getPopup();
				if (!popup) return;

				const vendorAInput   = popup.querySelector('#vendor-a-image-file');
				const vendorAPreview = popup.querySelector('#vendor-a-image-preview');
				const vendorBInput   = popup.querySelector('#vendor-b-image-file');
				const vendorBPreview = popup.querySelector('#vendor-b-image-preview');

				setupFilePreview(vendorAInput, vendorAPreview);
				setupFilePreview(vendorBInput, vendorBPreview);
			},
			preConfirm: () => {
				const popup = Swal.getPopup();
				if (!popup) {
					Swal.showValidationMessage('Unable to locate the menu form. Please try again.');
					return false;
				}

				const daySelect     = popup.querySelector('#add-menu-day');
				const vendorAFileEl = popup.querySelector('#vendor-a-image-file');
				const vendorBFileEl = popup.querySelector('#vendor-b-image-file');

				const day       = daySelect ? daySelect.value : '';
				const vendorAFile = vendorAFileEl && vendorAFileEl.files ? vendorAFileEl.files[0] : undefined;
				const vendorBFile = vendorBFileEl && vendorBFileEl.files ? vendorBFileEl.files[0] : undefined;

				if (!day) {
					Swal.showValidationMessage('Please choose a day.');
					return false;
				}

				if (!vendorAFile || !vendorBFile) {
					const vendorAName = VENDOR_LABELS.vendorA ?? 'Vendor A';
					const vendorBName = VENDOR_LABELS.vendorB ?? 'Vendor B';
					Swal.showValidationMessage(`Both ${vendorAName} and ${vendorBName} images are required.`);
					return false;
				}

				const formData = new FormData();
				formData.append('week_code', CREATE_WEEK_CODE || WEEK_CODE);
				formData.append('day', day);
				formData.append('vendor_a_image', vendorAFile);
				formData.append('vendor_b_image', vendorBFile);

				return formData;
			}
		}).then(result => {
			if (result.isConfirmed) {
				ajaxPost({
					url: '{{ route('masterMenu.store') }}',
					formData: result.value,
					contentType: false,
					processData: false,
					successCallback: () => { location.reload(); }
				});
			}
		});
	});
})();

// --- Admin update image ---
(() => {
	const resolveWeek = (btn) => btn.getAttribute('data-week') || CREATE_WEEK_CODE || WEEK_CODE || '';

	document.addEventListener('click', (event) => {
		const btn = event.target.closest('.edit-menu-image-btn');
		if (!btn) return;
		event.preventDefault();

		if (typeof window.Swal === 'undefined') {
			console.error('SweetAlert2 is not available.');
			return;
		}

		const catering = btn.getAttribute('data-catering') || '';
		const labelAttr = btn.getAttribute('data-label') || '';
		const day = btn.getAttribute('data-day') || '';
		const week = resolveWeek(btn);
		const label = labelAttr || (catering ? catering.charAt(0).toUpperCase() + catering.slice(1) : 'Vendor');

		const template = document.getElementById('edit-menu-modal-template');
		if (!template) {
			console.error('Edit menu modal template is missing.');
			return;
		}

		const modalHtml = template.innerHTML
			.replace(/__LABEL__/g, label)
			.replace(/__DAY__/g, day);

		Swal.fire({
			title: 'Update Menu Image',
			html: modalHtml,
			showCancelButton: true,
			confirmButtonText: 'Update',
			cancelButtonText: 'Cancel',
			width: 500,
			didOpen: () => {
				const popup = Swal.getPopup();
				if (!popup) return;

				const fileInput = popup.querySelector('#edit-menu-image-file');
				const preview   = popup.querySelector('#edit-menu-image-preview');
				const fallback  = btn.getAttribute('data-image') || '';

				setupFilePreview(fileInput, preview, fallback);
			},
			preConfirm: () => {
				const popup = Swal.getPopup();
				if (!popup) {
					Swal.showValidationMessage('Unable to load the form.');
					return false;
				}

				const fileInput = popup.querySelector('#edit-menu-image-file');
				const file = fileInput && fileInput.files ? fileInput.files[0] : undefined;

				if (!file) {
					Swal.showValidationMessage('Please choose an image.');
					return false;
				}

				const formData = new FormData();
				formData.append('week_code', week);
				formData.append('day', day);
				formData.append('catering', catering);
				formData.append('image', file);

				return formData;
			}
		}).then(result => {
			if (result.isConfirmed) {
				ajaxPost({
					url: '{{ route('masterMenu.updateImage') }}',
					formData: result.value,
					contentType: false,
					processData: false,
					successCallback: () => { location.reload(); }
				});
			}
		});
	});
})();

// --- Vendor menu upload ---
(() => {
	if (USER_ROLE !== 'vendor' || !VENDOR_MENU_ENDPOINT) return;

	const sliderWrapper = document.querySelector('.vendor-slider-wrapper');
	const sliderTrack = sliderWrapper ? sliderWrapper.querySelector('.vendor-slider-track') : null;
	const sliderSlides = sliderTrack ? Array.from(sliderTrack.querySelectorAll('.vendor-slide')) : [];
	const prevBtn = document.getElementById('vendor-slider-prev');
	const nextBtn = document.getElementById('vendor-slider-next');

	let sliderIndex = 0;
	let slidesPerView = 1;
	let resizeTimer = null;

	const getSlidesPerView = () => {
		if (!sliderWrapper) return 1;
		const raw = getComputedStyle(sliderWrapper).getPropertyValue('--vendor-slides-per-view');
		const parsed = parseInt(raw, 10);
		return Number.isNaN(parsed) || parsed <= 0 ? 1 : parsed;
	};

	const applySlideSizing = () => {
		sliderSlides.forEach(slide => {
			const basis = `calc(100% / ${slidesPerView})`;
			slide.style.flexBasis = basis;
			slide.style.maxWidth = basis;
		});
	};

	const updateTransform = () => {
		if (!sliderTrack) return;
		const offsetPercent = sliderIndex * (100 / slidesPerView);
		sliderTrack.style.transform = `translateX(-${offsetPercent}%)`;
	};

	const updateControls = () => {
		const maxIndex = Math.max(0, sliderSlides.length - slidesPerView);
		if (prevBtn) {
			prevBtn.disabled = sliderIndex === 0;
		}
		if (nextBtn) {
			nextBtn.disabled = sliderIndex >= maxIndex;
		}
	};

	const applyLayout = () => {
		if (!sliderWrapper || !sliderTrack) {
			if (prevBtn) prevBtn.disabled = true;
			if (nextBtn) nextBtn.disabled = true;
			return;
		}

		slidesPerView = getSlidesPerView();
		const maxIndex = Math.max(0, sliderSlides.length - slidesPerView);
		sliderIndex = Math.min(sliderIndex, maxIndex);

		applySlideSizing();
		updateTransform();
		updateControls();
	};

	if (sliderWrapper && sliderTrack) {
		applyLayout();
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(applyLayout, 150);
		});

		if (prevBtn) {
			prevBtn.addEventListener('click', () => {
				if (sliderIndex === 0) return;
				sliderIndex = Math.max(0, sliderIndex - 1);
				updateTransform();
				updateControls();
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', () => {
				const maxIndex = Math.max(0, sliderSlides.length - slidesPerView);
				if (sliderIndex >= maxIndex) return;
				sliderIndex = Math.min(maxIndex, sliderIndex + 1);
				updateTransform();
				updateControls();
			});
		}
	} else {
		if (prevBtn) prevBtn.disabled = true;
		if (nextBtn) nextBtn.disabled = true;
	}

	const refreshDayBadge = (dayCard) => {
		if (!dayCard) return;
		const optionCards = dayCard.querySelectorAll('.vendor-menu-card');
		const badge = dayCard.querySelector('.vendor-day-status');
		const summary = dayCard.querySelector('.vendor-day-summary');

		const total = optionCards.length;
		let completed = 0;
		optionCards.forEach(optionCard => {
			if (optionCard.classList.contains('is-complete')) {
				completed += 1;
			}
		});

		if (badge) {
			badge.classList.remove('bg-success', 'bg-warning', 'text-dark');
			if (total && completed === total) {
				badge.classList.add('bg-success');
				badge.textContent = 'Complete';
			} else {
				badge.classList.add('bg-warning', 'text-white');
				badge.textContent = 'Butuh Perhatian';
			}
		}

		if (summary) {
			summary.textContent = total
				? `${completed} dari ${total} opsi siap`
				: 'Belum ada opsi untuk hari ini.';
		}
	};

	const dayCards = document.querySelectorAll('.vendor-day-card');
	dayCards.forEach(refreshDayBadge);

	const renderPreviewPlaceholder = (wrapper) => {
		if (!wrapper) return;
		wrapper.innerHTML = '<div class="text-muted small fst-italic">Belum ada gambar.</div>';
	};

	const restoreOriginalPreview = (wrapper) => {
		if (!wrapper) return;
		const original = wrapper.dataset.originalContent || '';
		if (original.trim().length) {
			wrapper.innerHTML = original;
		} else {
			renderPreviewPlaceholder(wrapper);
		}
	};

	const showFilePreview = (file, wrapper) => {
		if (!wrapper) return;
		if (!file) {
			restoreOriginalPreview(wrapper);
			return;
		}

		const reader = new FileReader();
		reader.onload = ({ target }) => {
			const dataUrl = target && typeof target.result === 'string' ? target.result : '';
			if (!dataUrl) {
				restoreOriginalPreview(wrapper);
				return;
			}

			const preview = document.createElement('img');
			preview.className = 'img-fluid rounded vendor-menu-preview';
			preview.style.maxHeight = '200px';
			preview.style.objectFit = 'cover';
			preview.src = dataUrl;
			preview.alt = 'Menu preview';

			wrapper.innerHTML = '';
			wrapper.appendChild(preview);
		};
		reader.onerror = () => restoreOriginalPreview(wrapper);
		reader.readAsDataURL(file);
	};

	// attach preview behavior for each day
	dayCards.forEach(dayCard => {
		const previewWrapper = dayCard.querySelector('.vendor-preview-wrapper');
		if (previewWrapper && typeof previewWrapper.dataset.originalContent === 'undefined') {
			previewWrapper.dataset.originalContent = previewWrapper.innerHTML;
		}

		const imageInput = dayCard.querySelector('.vendor-menu-image-day');
		if (imageInput) {
			imageInput.addEventListener('change', () => {
				const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
				showFilePreview(file, previewWrapper);
			});
		}
	});

	// single save per day
	dayCards.forEach(dayCard => {
		const saveButton = dayCard.querySelector('.vendor-save-day-button');
		if (!saveButton) return;

		saveButton.addEventListener('click', () => {
			const day = dayCard.getAttribute('data-day');
			if (!day) return;

			const optionACard = dayCard.querySelector('.vendor-menu-card[data-option="A"]');
			const optionBCard = dayCard.querySelector('.vendor-menu-card[data-option="B"]');
			const nameAInput = optionACard ? optionACard.querySelector('.vendor-menu-name') : null;
			const nameBInput = optionBCard ? optionBCard.querySelector('.vendor-menu-name') : null;
			const imageInput = dayCard.querySelector('.vendor-menu-image-day');
			const file = imageInput && imageInput.files ? imageInput.files[0] : null;

			const nameA = nameAInput ? nameAInput.value.trim() : '';
			const nameB = nameBInput ? nameBInput.value.trim() : '';

			if (!nameA || !nameB) {
				const message = 'Nama menu A dan B wajib diisi.';
				if (typeof Swal !== 'undefined') {
					Swal.fire('Perhatian', message, 'warning');
				} else {
					alert(message);
				}
				return;
			}

			// require image only if there is no existing preview
			const existingPreview = dayCard.querySelector('.vendor-menu-preview');
			const requiresImage = !existingPreview;
			if (requiresImage && !file) {
				const message = 'Unggah 1 gambar yang berisi kedua menu sebelum menyimpan.';
				if (typeof Swal !== 'undefined') {
					Swal.fire('Perhatian', message, 'warning');
				} else {
					alert(message);
				}
				return;
			}

			const formData = new FormData();
			formData.append('day', day);
			formData.append('name_a', nameA);
			formData.append('name_b', nameB);
			if (file) {
				formData.append('image', file);
			}

			saveButton.disabled = true;
			saveButton.classList.add('disabled');

			ajaxPost({
				url: VENDOR_MENU_ENDPOINT,
				formData: formData,
				contentType: false,
				processData: false,
				toast: false,
				successCallback: (response) => {
					const menus = response?.menus || [];
					if (Array.isArray(menus)) {
						menus.forEach(menu => {
							const option = menu.option || menu.slot_label || '';
							const card = option === 'A' ? optionACard : (option === 'B' ? optionBCard : null);
							if (!card) return;

							const nameInput = card.querySelector('.vendor-menu-name');
							const badge = card.querySelector('.vendor-option-status');
							const statusIcon = card.querySelector('.vendor-option-status-icon');

							if (nameInput) {
								nameInput.value = menu.name || (option === 'A' ? nameA : nameB);
							}

							if (badge) {
								badge.classList.remove('bg-secondary');
								badge.classList.add('bg-success');
								badge.textContent = 'Sudah ada';
							}

							if (statusIcon) {
								statusIcon.classList.remove('bx-time-five', 'text-warning');
								statusIcon.classList.add('bx-check-circle', 'text-success');
							}

							card.classList.remove('is-pending');
							card.classList.add('is-complete');
						});
					}

					// refresh preview original content snapshot
					const previewWrapperSaved = dayCard.querySelector('.vendor-preview-wrapper');
					if (previewWrapperSaved) {
						previewWrapperSaved.dataset.originalContent = previewWrapperSaved.innerHTML;
					}

					if (imageInput) {
						imageInput.value = '';
					}

					refreshDayBadge(dayCard);

					saveButton.disabled = false;
					saveButton.classList.remove('disabled');

					if (typeof Swal !== 'undefined') {
						Swal.fire('Berhasil', response?.message || 'Menu hari ini berhasil disimpan.', 'success');
					} else {
						alert(response?.message || 'Menu hari ini berhasil disimpan.');
					}
				},
				errorCallback: (response) => {
					saveButton.disabled = false;
					saveButton.classList.remove('disabled');

					const message = response?.responseJSON?.message || response?.message || 'Gagal menyimpan menu.';
					if (typeof Swal !== 'undefined') {
						Swal.fire('Gagal', message, 'error');
					} else {
						alert(message);
					}
				}
			});
		});
	});
})();

refreshChoiceInteractivity();
</script>
@endpush
