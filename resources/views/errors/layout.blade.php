@extends('layouts.app')
@section('title', $heading)

@section('content')
    <div class="error-page">
        <h1>{{ $heading }}</h1>
        <p>{{ $detail }}</p>
        <a class="btn quiet" href="{{ route('home') }}">Go to the portal home</a>
    </div>
@endsection
