<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page?->title ?? ucfirst($type) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-3xl px-6 py-12">
        <h1 class="text-3xl font-semibold">{{ $page?->title ?? ucfirst($type) }}</h1>
        <article class="prose mt-6 max-w-none">
            {!! $page?->body ?: '<p>This page is being prepared.</p>' !!}
        </article>
    </main>
</body>
</html>
