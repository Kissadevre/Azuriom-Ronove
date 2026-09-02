@extends('layouts.app')

@section('title', trans('ronove::messages.language'))

@section('content')
    <div class="card">
        <div class="card-body">
            <h1>{{ trans('ronove::messages.language') }}</h1>
            <p class="mb-0">{{ trans('ronove::messages.description') }}</p>
        </div>
    </div>
@endsection
