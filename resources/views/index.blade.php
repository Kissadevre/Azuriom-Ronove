@extends('layouts.app')

@section('title', trans('ronove::messages.language'))

@section('content')
    <div class="card">
        <div class="card-body">
            <h1>{{ trans('ronove::messages.language') }}</h1>
            <p>{{ trans('ronove::messages.description') }}</p>

            @include('ronove::language-buttons', ['languageOptions' => $languageOptions])
        </div>
    </div>
@endsection
