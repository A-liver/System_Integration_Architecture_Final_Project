@extends('layouts.app')
@section('title', 'Sign in')

@section('content')
    <div class="narrow">
        <h1>Sign in to register</h1>
        <p class="sub">Students use their student number. Registrar staff use their staff ID.</p>

        <form method="post" action="{{ route('login') }}" data-busy-form>
            @csrf
            <label class="field">
                Student number or staff ID
                <input type="text" name="login_id" value="{{ old('login_id') }}" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                Password
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="btn wide" data-busy-label="Signing in...">Sign in</button>
        </form>
    </div>
@endsection
