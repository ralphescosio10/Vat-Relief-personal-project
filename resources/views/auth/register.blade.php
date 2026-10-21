@extends('layouts.app')
@section('title', 'Create accountant account')
@section('content')
<div class="login-wrap">
    <div class="eyebrow mb-2">Accounting workspace</div>
    <h1 class="page-title mb-2">Create an account</h1>
    <p class="text-muted mb-4">Your accountant account will open the shared company portfolio. Only register staff who should access all client companies.</p>
    <div class="card"><div class="card-body p-4 p-md-5">
        <form method="post" action="{{ route('register') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus></div>
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
            <div class="mb-3"><label class="form-label" for="password">Password (12+ characters)</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
            <div class="mb-4"><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
            <button class="btn btn-primary btn-lg w-100">Register as accountant</button>
        </form>
    </div></div>
    <p class="text-muted small mt-3">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
</div>
@endsection
