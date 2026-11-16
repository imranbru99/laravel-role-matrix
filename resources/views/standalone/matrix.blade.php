@extends('role-matrix::standalone.layout')

@section('content')
    @include('role-matrix::matrix', [
        'payload' => $payload,
        'assetCss' => $assetCss,
        'assetJs' => $assetJs,
        'saveUrl' => $saveUrl,
    ])
@endsection
