@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<div class="login-wrap"><div class="eyebrow mb-2">Account recovery</div><h1 class="page-title mb-2">Choose a new password</h1><p class="text-muted mb-4">Use at least 12 characters.</p>
<div class="card"><div class="card-body p-4"><form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email',$email) }}" autocomplete="email" required></div><div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div><div class="mb-3"><label class="form-label" for="password_confirmation">Confirm new password</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div><button class="btn btn-primary w-100">Update password</button></form></div></div>
</div>
@endsection
