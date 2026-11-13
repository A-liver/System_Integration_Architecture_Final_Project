@extends('layouts.app')
@section('title', 'Integration log')

@section('content')
    <div class="page-head">
        <h1>Integration log</h1>
        <form class="search" method="get" action="{{ route('staff.logs') }}">
            <label class="field" style="flex:1;margin:0">
                Component
                <select name="component">
                    <option value="">All components</option>
                    @foreach ($components as $option)
                        <option value="{{ $option }}" @selected($component === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn quiet">Filter</button>
        </form>
    </div>

    @if ($logs->isEmpty())
        <div class="state-empty"><p>No integration activity recorded{{ $component ? ' for this component' : '' }}.</p></div>
    @else
        <table class="ledger">
            <thead>
                <tr>
                    <th scope="col">Time</th>
                    <th scope="col">Component</th>
                    <th scope="col">Action</th>
                    <th scope="col">Outcome</th>
                    <th scope="col">Detail</th>
                    <th scope="col" class="num">ms</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td data-label="Time">{{ $log->created_at->format('M j, g:i:s A') }}</td>
                        <td data-label="Component">{{ str_replace('_', ' ', $log->component) }}</td>
                        <td data-label="Action">{{ $log->action }}</td>
                        <td data-label="Outcome">{{ $log->outcome }}</td>
                        <td data-label="Detail" style="overflow-wrap:anywhere">{{ $log->message }}@if ($log->reference) <div class="why">{{ $log->reference }}</div>@endif</td>
                        <td class="num" data-label="ms">{{ $log->duration_ms }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <nav class="pager" aria-label="Pages">
            @if ($logs->previousPageUrl())<a href="{{ $logs->previousPageUrl() }}">Newer entries</a>@endif
            @if ($logs->nextPageUrl())<a href="{{ $logs->nextPageUrl() }}">Older entries</a>@endif
        </nav>
    @endif
@endsection
