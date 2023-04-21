@extends('master.back')

@section('content')

<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title"><b>{{ __('Update Box') }}</b> </h3>
                <a class="btn btn-primary btn-sm" href="{{route('back.box.index')}}"><i class="fas fa-chevron-left"></i> {{ __('Back') }}</a>
            </div>
        </div>
    </div>

	<!-- Form -->
	<div class="row">
		<div class="col-xl-12 col-lg-12 col-md-12">
			<div class="card o-hidden border-0 shadow-lg">
				<div class="card-body ">
					<!-- Nested Row within Card Body -->
					<div class="row justify-content-center">
						<div class="col-lg-12">
							<form class="admin-form" action="{{ route('back.box.update',$box->id) }}" method="POST">
								@csrf
								@method('PUT')
								@include('alerts.alerts')

								<div class="form-group">
									<label for="name">{{ __('Name') }} *</label>
									<input type="text" name="name" class="form-control item-name" id="name"
										placeholder="{{ __('Enter Name') }}" value="{{ $box->name }}" >
								</div>

								<div class="form-group">
									<label for="min_items">{{ __('Min Items') }} *</label>
									<input type="number" name="min_items" class="form-control" id="min_items"
										placeholder="{{ __('Enter Number of Min Items') }}" value="{{ $box->min_items }}" >
								</div>

								<div class="form-group">
									<label for="max_items">{{ __('Max Items') }} *</label>
									<input type="number" name="max_items" class="form-control" id="max_items"
										placeholder="{{ __('Enter Number of Max Items') }}" value="{{ $box->max_items }}" >
								</div>

								<div class="form-group">
									<label for="height">{{ __('Box Height') }} *</label>
									<input type="text" name="height" class="form-control" id="height"
										placeholder="{{ __('Enter Box Height') }}" value="{{ $box->height }}" >
								</div>

								<div class="form-group">
									<label for="width">{{ __('Box Width') }} *</label>
									<input type="text" name="width" class="form-control" id="width"
										placeholder="{{ __('Enter Box Width') }}" value="{{ $box->width }}" >
								</div>

								<div class="form-group">
									<label for="length">{{ __('Box Length') }} *</label>
									<input type="text" name="length" class="form-control" id="length"
										placeholder="{{ __('Enter Box Length') }}" value="{{ $box->length }}" >
								</div>

								<div class="form-group">
									<label for="weight">{{ __('Box Weight (LBS)') }} *</label>
									<input type="text" name="weight" class="form-control" id="weight"
										placeholder="{{ __('Enter Box Weight in LBS') }}" value="{{ $box->weight }}" >
								</div>

								<div class="form-group">
									<button type="submit"
										class="btn btn-secondary ">{{ __('Submit') }}
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

		</div>

	</div>

</div>

@endsection
