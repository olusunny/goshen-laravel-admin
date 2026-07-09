<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fundraising</title>
</head>
<body>
    <main style="max-width: 760px; margin: 48px auto; font-family: Arial, sans-serif; line-height: 1.5;">
        @if ($campaign)
            <h1>{{ $campaign->title }}</h1>
            <p>{{ $campaign->short_description ?: $campaign->cause }}</p>
            <p>
                Raised {{ $campaign->currency }} {{ number_format((float) $campaign->raised_amount, 2) }}
                of {{ $campaign->currency }} {{ number_format((float) $campaign->goal_amount, 2) }}
            </p>
            <p><a href="{{ route('fundraising.show', $campaign->slug) }}">View campaign</a></p>
        @else
            <h1>Fundraising</h1>
            <p>No active campaign is available right now.</p>
        @endif
    </main>
</body>
</html>
