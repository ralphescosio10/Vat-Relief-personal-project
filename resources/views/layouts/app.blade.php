<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'VAT Relief') · Ledger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{--ink:#15231f;--muted:#66766f;--green:#1c6b55;--pale:#eff5f1;--line:#e2e9e5}
        body{background:#f6f8f6;color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
        .navbar{background:#102a23!important}.navbar-brand{font-weight:750;letter-spacing:-.03em}.navbar-brand span{color:#a9dec6}
        .page{max-width:1220px;margin:auto;padding:34px 22px 64px}.eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.72rem;color:var(--green);font-weight:700}
        h1,h2,h3{letter-spacing:-.035em;font-weight:700}.page-title{font-size:clamp(1.7rem,3vw,2.4rem)}
        .card{border:1px solid var(--line);border-radius:14px;box-shadow:0 3px 16px #132e2010}.card-header{background:#fff;border-bottom:1px solid var(--line);font-weight:650;padding:16px 20px}
        .btn-primary{background:var(--green);border-color:var(--green)}.btn-primary:hover{background:#155440;border-color:#155440}
        .text-muted{color:var(--muted)!important}.table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;white-space:nowrap}.table td{vertical-align:middle}
        .stat{font-size:2rem;font-weight:750;letter-spacing:-.06em}.badge{font-weight:600}.form-label{font-weight:600;font-size:.9rem}
        .side-links a{color:#d8e8e1;text-decoration:none}.side-links a:hover{color:#fff}.login-wrap{max-width:460px;margin:8vh auto}
        .table-responsive{border-radius:10px}.alert{border-radius:10px}
        @media(max-width:600px){.page{padding:24px 14px 46px}.table{font-size:.88rem}}
    </style>
    @stack('head')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid px-lg-5">
        <a class="navbar-brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">vat<span>desk</span></a>
        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <div class="navbar-nav me-auto side-links">
                    <a class="nav-link" href="{{ route('dashboard') }}">Overview</a>
                    <a class="nav-link" href="{{ route('companies.index') }}">Clients</a>
                </div>
                <span class="navbar-text me-3">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light">Sign out</button></form>
            </div>
        @endauth
    </div>
</nav>
<main class="page">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Please check the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
