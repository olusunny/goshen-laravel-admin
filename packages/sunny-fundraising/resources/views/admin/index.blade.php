<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fundraising Admin</title>
</head>
<body>
    <main style="max-width: 1080px; margin: 40px auto; font-family: Arial, sans-serif; line-height: 1.5;">
        <h1>Fundraising Admin</h1>
        <h2>Campaigns</h2>
        <table border="1" cellpadding="8" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th align="left">Title</th>
                    <th align="left">Status</th>
                    <th align="right">Raised</th>
                    <th align="right">Goal</th>
                    <th align="right">Contributions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    <tr>
                        <td>{{ $campaign->title }}</td>
                        <td>{{ $campaign->status }}</td>
                        <td align="right">{{ $campaign->currency }} {{ number_format((float) $campaign->raised_amount, 2) }}</td>
                        <td align="right">{{ $campaign->currency }} {{ number_format((float) $campaign->goal_amount, 2) }}</td>
                        <td align="right">{{ $campaign->contributions_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Recent Contributions</h2>
        <table border="1" cellpadding="8" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th align="left">Campaign</th>
                    <th align="left">Donor</th>
                    <th align="right">Amount</th>
                    <th align="left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentContributions as $contribution)
                    <tr>
                        <td>{{ $contribution->campaign?->title ?: 'Campaign removed' }}</td>
                        <td>{{ $contribution->is_anonymous ? 'Anonymous supporter' : ($contribution->display_name ?: 'Supporter') }}</td>
                        <td align="right">{{ $contribution->currency }} {{ number_format((float) $contribution->amount, 2) }}</td>
                        <td>{{ $contribution->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No contributions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
