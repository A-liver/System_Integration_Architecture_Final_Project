<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | University Registration and Payment</title>
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('theme'); } catch (e) {}
            var dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip" href="#main">Skip to main content</a>
    <header class="top">
        <div class="wrap">
            <a class="wordmark" href="{{ route('home') }}">University Registration and Payment</a>
            <nav class="nav" aria-label="Main">
                @auth
                    @if (auth()->user()->isStaff())
                        <a href="{{ route('staff.reconciliation') }}" @if (request()->routeIs('staff.reconciliation')) aria-current="page" @endif>Reconciliation</a>
                        <a href="{{ route('staff.logs') }}" @if (request()->routeIs('staff.logs')) aria-current="page" @endif>Integration log</a>
                    @else
                        <a href="{{ route('courses.index') }}" @if (request()->routeIs('courses.*')) aria-current="page" @endif>Courses</a>
                        <a href="{{ route('registrations.index') }}" @if (request()->routeIs('registrations.index', 'registrations.show')) aria-current="page" @endif>My registrations</a>
                    @endif
                @endauth
            </nav>
            <div class="who">
                @auth
                    <span>{{ auth()->user()->name }}</span>
                @endauth
                <button type="button" class="link-btn" data-theme-toggle>Switch theme</button>
                @auth
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="link-btn">Sign out</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main id="main">
        <div class="wrap">
            @if (session('status'))
                <div class="msg" role="status">{{ session('status') }}</div>
            @endif
            @if (session('problems'))
                <div class="msg bad" role="alert">
                    <ul>
                        @foreach (session('problems') as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @isset($errors)
                @if ($errors->any())
                    <div class="msg bad" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endisset

            @yield('content')
        </div>
    </main>

    <footer class="foot">
        <div class="wrap">Sample data for the System Integration and Architecture midterm. This is a proposed design, not a live university system.</div>
    </footer>
</body>
</html>
