@extends('layouts.app')

@section('title', trans('ronove::messages.language'))

@section('content')
    <div class="card">
        <div class="card-body">
            <h1>{{ trans('ronove::messages.language') }}</h1>
            <p>{{ trans('ronove::messages.description') }}</p>

            @if($locales->isEmpty())
                <div class="alert alert-info mb-0">{{ trans('ronove::messages.no_languages') }}</div>
            @else
                <div class="row g-3">
                    @foreach($locales as $locale)
                        <div class="col-sm-6 col-lg-4">
                            <form action="{{ route('ronove.locale.update') }}" method="POST">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $locale->code }}">
                                <button type="submit" class="btn w-100 {{ $currentLocale === $locale->code ? 'btn-primary' : 'btn-outline-primary' }}">
                                    {{ $locale->native_name }}
                                    @if($currentLocale === $locale->code)
                                        <i class="bi bi-check-lg ms-1" aria-hidden="true"></i>
                                    @endif
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
