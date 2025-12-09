@extends('layouts.app', ['title' => 'Dashboard Vendor'])

@section('content')
<div class="space-y-6 mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
		<h5 class="mt-3 mb-3 mb-lg-0">Vendor Overview</h5>
		<x-button :url="route('masterMenu.index')" label="Kelola & Unggah Menu" icon="bx-edit" />
	</div>

	@php
		$vendorWeekCode      = $summary['week_code'] ?? '—';
		$vendorRangeLabel    = $summary['range_label'] ?? '—';
		$filledSlots         = (int) ($summary['filled_slots'] ?? 0);
		$totalSlots          = (int) ($summary['total_slots'] ?? 0);
		$remainingSlots      = max(0, (int) ($summary['remaining_slots'] ?? 0));
		$windowStatus        = trim((string) ($summary['window_status'] ?? 'Pending')) ?: 'Pending';
		$windowSubtitle      = trim((string) ($summary['window_subtitle'] ?? ''));
		$progressPercent     = $totalSlots > 0 ? (int) round(($filledSlots / max(1, $totalSlots)) * 100) : 0;
		$remainingBadgeClass = $remainingSlots === 0 ? 'badge bg-success' : 'badge bg-warning text-dark';
		$windowStatusNormalized = strtolower($windowStatus);
		$windowBadgeClass = match ($windowStatusNormalized) {
			'open'                => 'badge bg-success',
			'ready', 'ready soon' => 'badge bg-info text-dark',
			'pending'             => 'badge bg-warning text-dark',
			'closed'              => 'badge bg-secondary',
			default               => 'badge bg-secondary',
		};

		$vendorSlides = collect($dayOrder)
			->map(fn($label) => [
				'label' => $label,
				'day'   => $days[$label] ?? null,
			])
			->filter(fn($entry) => !empty($entry['day']))
			->values();

		// bikin slide, tiap slide berisi max 2 hari
		$dayChunks = $vendorSlides->chunk(2);
	@endphp

	{{-- SUMMARY CARD ROW --}}
	<div class="row g-3 mt-3 mb-3">
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100 vendor-summary-card">
				<div class="card-body">
					<div class="small text-muted mb-1">Minggu yang sedang diunggah</div>
					<div class="fw-semibold mb-1">{{ $vendorWeekCode }}</div>
					<div class="small text-muted">{{ $vendorRangeLabel }}</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100 vendor-summary-card">
				<div class="card-body">
					<div class="small text-muted mb-1">Slot terisi</div>
					<div class="d-flex align-items-baseline gap-1 mb-2">
						<div class="fw-semibold">{{ $filledSlots }}</div>
						<div class="small text-muted">/ {{ $totalSlots }}</div>
					</div>
					<div class="progress">
						<div class="progress-bar bg-primary" role="progressbar"
							 style="width: {{ $progressPercent }}%;"
							 aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100">
						</div>
					</div>
					<div class="small text-muted mt-1">{{ $progressPercent }}% dari target minggu ini</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100 vendor-summary-card">
				<div class="card-body">
					<div class="small text-muted mb-1">Sisa slot</div>
					<div class="d-flex align-items-center justify-content-between">
						<div class="fw-semibold">{{ $remainingSlots }}</div>
						<span class="{{ $remainingBadgeClass }}">
							{{ $remainingSlots === 0 ? 'Sudah lengkap' : 'Perlu dilengkapi' }}
						</span>
					</div>
					<div class="small text-muted mt-1">
						Selesaikan semua slot sebelum window ditutup.
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6 col-xl-3">
			<div class="card border-0 shadow-sm h-100 vendor-summary-card">
				<div class="card-body">
					<div class="small text-muted mb-1">Status window unggah</div>
					<div class="d-flex align-items-center justify-content-between mb-1">
						<div class="fw-semibold">Upload Window</div>
						<span class="{{ $windowBadgeClass }}">{{ ucfirst($windowStatus) }}</span>
					</div>
					<div class="small text-muted">
						{{ $windowSubtitle ?: 'Ikuti jadwal yang sudah ditentukan oleh admin.' }}
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- SLIDER HARI --}}
	@if($vendorSlides->isEmpty())
		<div class="alert alert-warning small mb-0">Tidak ada data hari yang perlu diunggah saat ini.</div>
	@else
		<div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
			<div class="small text-muted">
				Gunakan tombol di kanan untuk melihat progress unggah per hari.
			</div>
			<div class="btn-group btn-group-sm" role="group" aria-label="Navigasi progress unggah">
				<button type="button" class="btn btn-outline-secondary" id="vendor-progress-prev">Sebelum</button>
				<button type="button" class="btn btn-outline-secondary" id="vendor-progress-next">Berikutnya</button>
			</div>
		</div>

		<div class="vendor-progress-carousel">
			<div class="vendor-progress-viewport">
				<div class="vendor-progress-track">
					@foreach($dayChunks as $chunk)
						<div class="vendor-progress-slide">
							<div class="d-flex flex-column gap-3">
								@foreach($chunk as $entry)
									@php
										$label    = $entry['label'];
										$day      = $entry['day'];
										$options  = $day['options'] ?? [];
										// hitung 3 komponen: nama A, nama B, dan gambar
										$expectedParts = 3;
										$completedParts = 0;
										$optionA = $options['A'] ?? null;
										$optionB = $options['B'] ?? null;
										if ($optionA && !empty($optionA['has_menu'])) {
											$completedParts++;
										}
										if ($optionB && !empty($optionB['has_menu'])) {
											$completedParts++;
										}
										// gambar dianggap sama dengan image opsi A
										$hasImage = $optionA && !empty($optionA['image']);
										if ($hasImage) {
											$completedParts++;
										}
										$badgeClass = $completedParts === $expectedParts ? 'bg-success' : 'bg-warning';
										$badgeLabel = sprintf('%d / %d Terpenuhi', $completedParts, $expectedParts);
									@endphp

									<div class="card border-0 shadow-sm vendor-day-card">
										<div class="card-body">
											<div class="d-flex justify-content-between align-items-start mb-3">
												<div>
													<div class="fw-semibold">{{ $day['label'] ?? $label }}</div>
													@if(!empty($day['date_label']))
														<div class="small text-muted">{{ $day['date_label'] }}</div>
													@endif
												</div>
												<span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
											</div>

											@if(empty($options))
												<div class="text-muted small fst-italic">Belum ada slot menu untuk hari ini.</div>
											@else
												<div class="vendor-option-stack">
													@php
														$optionList = collect($options)->values();
													@endphp
													@foreach($optionList as $option)
														@php
															$optionFilled = !empty($option['has_menu']);
															$statusClass  = $optionFilled ? 'bg-success' : 'bg-secondary';
															$statusLabel  = $optionFilled ? 'Nama terisi' : 'Nama belum diisi';
														@endphp

														<div class="vendor-option-card border rounded-3 p-3">
															<div class="d-flex justify-content-between align-items-start gap-3">
																<div>
																	<div class="fw-semibold">{{ $option['label'] ?? 'Opsi' }}</div>
																	<div class="small text-muted">
																		{{ $optionFilled ? ($option['name'] ?: 'Nama belum tercatat') : 'Segera lengkapi nama menu.' }}
																	</div>
																	@if(!empty($option['code']))
																		<div class="text-muted small"><code>{{ $option['code'] }}</code></div>
																	@endif
																</div>
																<span class="badge {{ $statusClass }} align-self-center">{{ $statusLabel }}</span>
															</div>
														</div>
													@endforeach
													<div class="vendor-option-card border rounded-3 p-3">
														@php
															$imageFilled = $hasImage;
															$imageStatusClass = $imageFilled ? 'bg-success' : 'bg-secondary';
															$imageStatusLabel = $imageFilled ? 'Gambar ada' : 'Gambar belum ada';
														@endphp
														<div class="d-flex justify-content-between align-items-start gap-3">
															<div>
																<div class="fw-semibold">Gambar Menu</div>
																<div class="small text-muted">
																	{{ $imageFilled ? 'Sudah ada gambar yang terunggah.' : 'Belum ada gambar untuk menu hari ini.' }}
																</div>
															</div>
															<span class="badge {{ $imageStatusClass }} align-self-center">{{ $imageStatusLabel }}</span>
														</div>
													</div>
												</div>
											@endif
										</div>
									</div>
								@endforeach
							</div>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	@endif
</div>
@endsection

@push('css')
<style>
	.vendor-summary-card {
		border-radius: 0.75rem;
	}
	.vendor-summary-card .progress {
		height: 6px;
		background-color: rgba(13, 110, 253, 0.12);
	}
	.vendor-summary-card .progress-bar {
		border-radius: 999px;
	}

	.vendor-day-card {
		border-radius: 0.75rem;
	}
	.vendor-option-stack {
		display: grid;
		gap: 0.75rem;
	}
	.vendor-option-card {
		background-color: #f8f9fa;
		border-color: rgba(0,0,0,0.06) !important;
	}
	.vendor-option-card .badge {
		min-width: 90px;
		text-align: center;
	}

	.vendor-progress-carousel {
		position: relative;
	}
	.vendor-progress-viewport {
		overflow: hidden;
		margin: 0 -0.25rem;
	}
	.vendor-progress-track {
		display: flex;
		transition: transform 0.35s ease;
		will-change: transform;
	}
	.vendor-progress-slide {
		flex: 0 0 100%;
		max-width: 100%;
		padding: 0 0.25rem;
		box-sizing: border-box;
	}
	.vendor-progress-slide > .card {
		height: 100%;
	}

	@media (min-width: 992px) {
		.vendor-progress-viewport {
			margin: 0 -0.5rem;
		}
		.vendor-progress-slide {
			padding: 0 0.5rem;
		}
	}

	@media (max-width: 575.98px) {
		.vendor-summary-card .display-6,
		.vendor-summary-card .fw-semibold {
			font-size: 1.5rem;
		}
	}
</style>
@endpush

@push('scripts')
<script>
	(() => {
		const wrapper = document.querySelector('.vendor-progress-carousel');
		const track = wrapper ? wrapper.querySelector('.vendor-progress-track') : null;
		const slides = track ? Array.from(track.querySelectorAll('.vendor-progress-slide')) : [];
		const prevBtn = document.getElementById('vendor-progress-prev');
		const nextBtn = document.getElementById('vendor-progress-next');

		if (!wrapper || !track || !slides.length) {
			if (prevBtn) prevBtn.disabled = true;
			if (nextBtn) nextBtn.disabled = true;
			return;
		}

		let sliderIndex = 0;
		const slidesPerView = 1;
		let resizeTimer = null;

		const updateControls = () => {
			const maxIndex = Math.max(0, slides.length - slidesPerView);
			if (prevBtn) prevBtn.disabled = sliderIndex === 0;
			if (nextBtn) nextBtn.disabled = sliderIndex >= maxIndex;
		};

		const updateTransform = () => {
			const viewport = wrapper.querySelector('.vendor-progress-viewport');
			const viewportWidth = viewport ? viewport.getBoundingClientRect().width : 0;
			const offset = sliderIndex * viewportWidth;
			track.style.transform = `translateX(-${offset}px)`;
		};

		const applyLayout = () => {
			const maxIndex = Math.max(0, slides.length - slidesPerView);
			sliderIndex = Math.min(sliderIndex, maxIndex);
			updateTransform();
			updateControls();
		};

		applyLayout();

		if (prevBtn) {
			prevBtn.addEventListener('click', () => {
				if (sliderIndex === 0) return;
				sliderIndex = Math.max(0, sliderIndex - slidesPerView);
				updateTransform();
				updateControls();
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', () => {
				const maxIndex = Math.max(0, slides.length - slidesPerView);
				if (sliderIndex >= maxIndex) return;
				sliderIndex = Math.min(maxIndex, sliderIndex + slidesPerView);
				updateTransform();
				updateControls();
			});
		}

		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(applyLayout, 150);
		});
	})();
</script>
@endpush
