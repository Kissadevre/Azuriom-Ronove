@extends('admin.layouts.admin')

@section('title', trans('ronove::admin.nav.'.$section))

@section('content')
    <div class="card">
        <div class="card-body">
            <h1>{{ trans('ronove::admin.nav.'.$section) }}</h1>
        </div>
    </div>
@endsection
