@extends('layouts.app')

{{-- The OAuth authorization prompt (Passport 13 no longer ships its own view). --}}
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Authorization Request') }}</div>

                <div class="card-body">
                    <p><strong>{{ $client->name }}</strong> is requesting permission to access your account.</p>

                    @if (count($scopes) > 0)
                        <div class="mb-3">
                            <p class="mb-1"><strong>This application will be able to:</strong></p>

                            <ul>
                                @foreach ($scopes as $scope)
                                    <li>{{ $scope->description }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="d-flex gap-2">
                        <form method="post" action="{{ route('passport.authorizations.approve') }}">
                            @csrf

                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="btn btn-success">{{ __('Authorize') }}</button>
                        </form>

                        <form method="post" action="{{ route('passport.authorizations.deny') }}">
                            @csrf
                            @method('DELETE')

                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button class="btn btn-danger">{{ __('Cancel') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
