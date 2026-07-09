<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->title }}</title>
</head>
<body>
    <main style="max-width: 760px; margin: 48px auto; font-family: Arial, sans-serif; line-height: 1.5;">
        <p><a href="{{ route('fundraising.index') }}">Back to fundraising</a></p>
        <h1>{{ $campaign->title }}</h1>
        @if ($campaign->cause)
            <p><strong>{{ $campaign->cause }}</strong></p>
        @endif
        <p>{{ $campaign->description ?: $campaign->short_description }}</p>
        <p>
            Raised {{ $campaign->currency }} {{ number_format((float) $campaign->raised_amount, 2) }}
            of {{ $campaign->currency }} {{ number_format((float) $campaign->goal_amount, 2) }}
            ({{ $campaign->progressPercentage() }}%)
        </p>
        <p>Status: {{ ucfirst($campaign->status) }}</p>
    </main>
</body>
</html>
