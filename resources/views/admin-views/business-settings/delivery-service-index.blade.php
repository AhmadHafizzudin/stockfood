@extends('layouts.admin.app')

@section('title', translate('Delivery Service Settings'))

@section('content')
<div class="content container-fluid">
    {{-- Removed top nav menu include --}}

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ translate('Delivery Service Integration') }}</h5>
                    <p class="card-text">{{ translate('Configure third-party delivery services like Grab and Lalamove. After payment and marking food as ready for pickup, delivery will be handled via the selected service.') }}</p>
                </div>
                <div class="card-body">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card shadow--card-2 h-100">
                                <div class="card-body">
                            <h6 class="mb-3">{{ translate('Grab') }}</h6>
                            @php($grab = $data_values->where('key_name','grab')->first())
                            @php($grab_values = $grab ? ($grab->mode == 'live' ? $grab->live_values : $grab->test_values) : [])
                            @php($grab_active = $grab ? $grab->is_active : 0)
                            <form action="{{ route('admin.business-settings.delivery-service-update') }}" method="post" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="gateway" value="grab">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Status') }}</label>
                                        <select class="form-select" name="status">
                                            <option value="1" {{ $grab_active == 1 ? 'selected' : '' }}>{{ translate('Enabled') }}</option>
                                            <option value="0" {{ $grab_active == 0 ? 'selected' : '' }}>{{ translate('Disabled') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Mode') }}</label>
                                        <select class="form-select" name="mode">
                                            <option value="live" {{ $grab && $grab->mode == 'live' ? 'selected' : '' }}>{{ translate('Live') }}</option>
                                            <option value="test" {{ $grab && $grab->mode == 'test' ? 'selected' : '' }}>{{ translate('Test') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Secret Key') }}</label>
                                        <input type="text" class="form-control" name="secret_key" value="{{ $grab_values['secret_key'] ?? '' }}" placeholder="{{ translate('Enter Secret Key') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Callback URL') }}</label>
                                        <input type="text" class="form-control" name="callback_url" value="{{ $grab_values['callback_url'] ?? '' }}" placeholder="{{ translate('Enter Callback URL') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Merchant ID (optional)') }}</label>
                                        <input type="text" class="form-control" name="merchant_id" value="{{ $grab_values['merchant_id'] ?? '' }}" placeholder="{{ translate('Merchant ID') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Gateway Title') }}</label>
                                        <input type="text" class="form-control" name="gateway_title" value="{{ $grab && $grab->additional_data ? json_decode($grab->additional_data)->gateway_title : 'Grab' }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Gateway Image') }}</label>
                                        <input type="file" class="form-control" name="gateway_image" accept="image/png" />
                                        @if($grab && $grab->additional_data && json_decode($grab->additional_data)->gateway_image)
                                            <small class="text-muted d-block mt-1">{{ translate('Current Image:') }} {{ json_decode($grab->additional_data)->gateway_image }}</small>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{ translate('Save Grab Settings') }}</button>
                                    </div>
                                </div>
                            </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card shadow--card-2 h-100">
                                <div class="card-body">
                            <h6 class="mb-3">{{ translate('Lalamove') }}</h6>
                            @php($lalamove = $data_values->where('key_name','lalamove')->first())
                            @php($lalamove_values = $lalamove ? ($lalamove->mode == 'live' ? $lalamove->live_values : $lalamove->test_values) : [])
                            @php($lalamove_active = $lalamove ? $lalamove->is_active : 0)
                            <form action="{{ route('admin.business-settings.delivery-service-update') }}" method="post" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="gateway" value="lalamove">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Status') }}</label>
                                        <select class="form-select" name="status">
                                            <option value="1" {{ $lalamove_active == 1 ? 'selected' : '' }}>{{ translate('Enabled') }}</option>
                                            <option value="0" {{ $lalamove_active == 0 ? 'selected' : '' }}>{{ translate('Disabled') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Mode') }}</label>
                                        <select class="form-select" name="mode">
                                            <option value="live" {{ $lalamove && $lalamove->mode == 'live' ? 'selected' : '' }}>{{ translate('Live') }}</option>
                                            <option value="test" {{ $lalamove && $lalamove->mode == 'test' ? 'selected' : '' }}>{{ translate('Test') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Secret Key') }}</label>
                                        <input type="text" class="form-control" name="secret_key" value="{{ $lalamove_values['secret_key'] ?? '' }}" placeholder="{{ translate('Enter Secret Key') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Callback URL') }}</label>
                                        <input type="text" class="form-control" name="callback_url" value="{{ $lalamove_values['callback_url'] ?? '' }}" placeholder="{{ translate('Enter Callback URL') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Merchant ID (optional)') }}</label>
                                        <input type="text" class="form-control" name="merchant_id" value="{{ $lalamove_values['merchant_id'] ?? '' }}" placeholder="{{ translate('Merchant ID') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Gateway Title') }}</label>
                                        <input type="text" class="form-control" name="gateway_title" value="{{ $lalamove && $lalamove->additional_data ? json_decode($lalamove->additional_data)->gateway_title : 'Lalamove' }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ translate('Gateway Image') }}</label>
                                        <input type="file" class="form-control" name="gateway_image" accept="image/png" />
                                        @if($lalamove && $lalamove->additional_data && json_decode($lalamove->additional_data)->gateway_image)
                                            <small class="text-muted d-block mt-1">{{ translate('Current Image:') }} {{ json_decode($lalamove->additional_data)->gateway_image }}</small>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{ translate('Save Lalamove Settings') }}</button>
                                    </div>
                                </div>
                            </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" />
                    <div>
                        <h6>{{ translate('How it works') }}</h6>
                        <ol>
                            <li>{{ translate('Customer completes payment.') }}</li>
                            <li>{{ translate('Admin updates the order status to Ready for Pickup.') }}</li>
                            <li>{{ translate('The configured Delivery Service (Grab/Lalamove) is triggered to handle pickup and delivery via callback URL and secret key.') }}</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection