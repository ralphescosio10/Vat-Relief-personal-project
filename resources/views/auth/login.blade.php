@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="login-wrap">
    <div class="eyebrow mb-2">Accounting workspace</div>
    <h1 class="page-title mb-2">Welcome back</h1>
    <p class="text-muted mb-4">Sign in to manage client VAT records and reporting.</p>
    <div class="card"><div class="card-body p-4 p-md-5">
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control form-control-lg" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="mb-2"><label class="form-label" for="password">Password</label><input class="form-control form-control-lg" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <div class="text-end mb-3"><a href="{{ route('password.request') }}" class="small">Forgot password?</a></div>
            <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Remember me</label></div>
            <button class="btn btn-primary btn-lg w-100">Sign in</button>
        </form>
    </div></div>
    <p class="text-muted small mt-3">New to the workspace? <a href="{{ route('register') }}">Create an accountant account</a></p>
</div>
@endsection
