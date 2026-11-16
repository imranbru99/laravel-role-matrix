<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('role-matrix::ui.matrix') }}</title>
</head>
<body style="margin:0;background:#f6f7fb">
    @if (session('status'))
        <p style="padding:12px 16px;background:#ecfdf5">{{ session('status') }}</p>
    @endif
    @yield('content')
</body>
</html>
