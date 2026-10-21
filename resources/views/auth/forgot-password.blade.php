@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<div class="login-wrap"><div class="eyebrow mb-2">Account recovery</div><h1 class="page-title mb-2">Reset your password</h1><p class="text-muted mb-4">Enter your account email. If it exists, we’ll send you a reset link.</p>
<div class="card"><div class="card-body p-4"><form method="post" action="{{ route('password.email') }}">@csrf<div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control form-control-lg" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div><button class="btn btn-primary w-100">Send reset link</button></form><a class="d-block text-center mt-3" href="{{ route('login') }}">Back to sign in</a></div></div>
</div>
@endsection
