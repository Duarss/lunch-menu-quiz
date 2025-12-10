@extends('layouts.app', ['title' => 'Dashboard Vendor'])

@section('content')
<div class="space-y-6 mt-3">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
		<div>
			<h5 class="mt-3 mb-3 mb-lg-0">Vendor Overview</h5>
			@if(!empty($vendorName))
				<div class="small text-muted">{{ $vendorName }}</div>
			@endif
		</div>
		<x-button :url="$uploadUrl" label="Kelola & Unggah Menu" icon="bx-edit" />
	</div>

	{{-- SUMMARY CARD ROW --}}
	<div class="row g-3 mt-3 mb-3">
		@foreach($summaryCards as $card)
			<div class="col-12 col-md-6 col-xl-3">
				<div class="card border-0 shadow-sm h-100 vendor-summary-card">
					<div class="card-body">
						<div class="small text-muted mb-1">{{ $card['title'] }}</div>
						@if(isset($card['progress']))
							<div class="d-flex align-items-baseline gap-1 mb-2">
								<div class="fw-semibold">{{ $card['value'] }}</div>
								@if(!empty($card['suffix']))
									<div class="small text-muted">{{ $card['suffix'] }}</div>
								@endif
							</div>
							<div class="progress">
								<div class="progress-bar bg-primary" role="progressbar"
									 style="width: {{ $card['progress']['percent'] }}%;"
									 aria-valuenow="{{ $card['progress']['percent'] }}" aria-valuemin="0" aria-valuemax="100">
								</div>
							</div>
							@if(!empty($card['progress']['label']))
								<div class="small text-muted mt-1">{{ $card['progress']['label'] }}</div>
							@endif
						@elseif(isset($card['badge']))
							<div class="d-flex align-items-center justify-content-between mb-1">
								<div class="fw-semibold">{{ $card['value'] }}</div>
								<span class="{{ $card['badge']['class'] }}">{{ $card['badge']['label'] }}</span>
							</div>
						@else
							<div class="fw-semibold mb-1">{{ $card['value'] }}</div>
						@endif

						@if(isset($card['suffix']) && !isset($card['progress']))
							<div class="small text-muted">{{ $card['suffix'] }}</div>
						@endif

						@if(!empty($card['subtitle']))
							<div class="small text-muted mt-1">{{ $card['subtitle'] }}</div>
						@endif
					</div>
				</div>
			</div>
		@endforeach
	</div>

	{{-- SLIDER HARI --}}
	@if(empty($progressSlides))
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
					@foreach($progressSlides as $slide)
						<div class="vendor-progress-slide">
							<div class="d-flex flex-column gap-3">
								@foreach($slide['days'] as $day)
									<div class="card border-0 shadow-sm vendor-day-card">
										<div class="card-body">
											<div class="d-flex justify-content-between align-items-start mb-3">
												<div>
													<div class="fw-semibold">{{ $day['label'] }}</div>
													@if(!empty($day['date_label']))
														<div class="small text-muted">{{ $day['date_label'] }}</div>
													@endif
												</div>
												<span class="badge {{ $day['badge_class'] }}">{{ $day['badge_label'] }}</span>
											</div>

											@if(empty($day['has_options']))
												<div class="text-muted small fst-italic">Belum ada slot menu untuk hari ini.</div>
											@else
												<div class="vendor-option-stack">
													@foreach($day['option_cards'] as $option)
														<div class="vendor-option-card border rounded-3 p-3">
															<div class="d-flex justify-content-between align-items-start gap-3">
																<div>
																	<div class="fw-semibold">{{ $option['label'] }}</div>
																	<div class="small text-muted">{{ $option['description'] }}</div>
																	@if(!empty($option['code']))
																		<div class="text-muted small"><code>{{ $option['code'] }}</code></div>
																	@endif
																</div>
																<span class="badge {{ $option['badge']['class'] }} align-self-center">{{ $option['badge']['label'] }}</span>
															</div>
														</div>
													@endforeach
													<div class="vendor-option-card border rounded-3 p-3">
														<div class="d-flex justify-content-between align-items-start gap-3">
															<div>
																<div class="fw-semibold">{{ $day['image_card']['label'] }}</div>
																<div class="small text-muted">{{ $day['image_card']['description'] }}</div>
															</div>
															<span class="badge {{ $day['image_card']['badge']['class'] }} align-self-center">{{ $day['image_card']['badge']['label'] }}</span>
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
