<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1020"><title>FamilyCall Control</title>
@viteReactRefresh
@vite(['resources/css/admin.css','resources/js/admin.jsx'])
@inertiaHead
</head><body>@inertia</body></html>
