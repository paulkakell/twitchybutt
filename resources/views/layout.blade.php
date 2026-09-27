<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Creator studio') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="/">{{ config('app.name') }}<span>Independent creator publishing</span></a>
    <nav aria-label="Main navigation">
        <a href="/">Explore</a>
        @auth
            <a href="/account">My account</a>
            @can('manage-content')<a href="/studio">Studio</a>@endcan
            <form action="/logout" method="post">@csrf<button class="text-button">Sign out</button></form>
        @else
            <a href="/login">Sign in</a><a class="button small" href="/register">Join this site</a>
        @endauth
    </nav>
</header>
<div class="development">Development preview {{ config('cms.version') }}. TEST invoices only. No funds accepted.</div>
<main id="main" class="container">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="errors" role="alert"><strong>Check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="container footer"><span>Creator-owned site. No centralized content hosting.</span><a href="/report">Report content</a></footer>
</body>
</html>
