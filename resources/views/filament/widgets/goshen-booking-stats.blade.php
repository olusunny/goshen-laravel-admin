<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Goshen booking performance
        </x-slot>

        <x-slot name="description">
            Ticket sales, paid revenue, edition performance, and the most recent Goshen purchases.
        </x-slot>

        @php
            $overview = $this->getOverview();
            $dailySales = $this->getDailySales();
            $weeklySales = $this->getWeeklySales();
            $monthlySales = $this->getMonthlySales();
            $editions = $this->getEditionBreakdown();
            $recentPurchases = $this->getRecentPurchases();

            $cards = [
                ['label' => 'Total tickets sold', 'value' => number_format($overview['tickets_sold']), 'meta' => number_format($overview['paid_bookings']).' paid booking(s)', 'tone' => 'navy'],
                ['label' => 'Paid revenue', 'value' => $overview['revenue'], 'meta' => 'Settled Goshen booking value', 'tone' => 'gold'],
                ['label' => 'Sold today', 'value' => number_format($overview['today_tickets']), 'meta' => $overview['today_revenue'], 'tone' => 'green'],
                ['label' => 'Sold this week', 'value' => number_format($overview['week_tickets']), 'meta' => $overview['week_revenue'], 'tone' => 'blue'],
                ['label' => 'Sold this month', 'value' => number_format($overview['month_tickets']), 'meta' => $overview['month_revenue'], 'tone' => 'purple'],
                ['label' => 'Check-in status', 'value' => number_format($overview['checked_in']).' checked in', 'meta' => number_format($overview['awaiting_check_in']).' awaiting check-in', 'tone' => 'slate'],
            ];
        @endphp

        <div class="goshen-booking-widget">
            <div class="goshen-booking-cards">
                @foreach ($cards as $card)
                    <div class="goshen-booking-card goshen-booking-card-{{ $card['tone'] }}">
                        <div class="goshen-booking-card-label">{{ $card['label'] }}</div>
                        <div class="goshen-booking-card-value">{{ $card['value'] }}</div>
                        <div class="goshen-booking-card-meta">{{ $card['meta'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="goshen-booking-grid">
                <div class="goshen-booking-panel goshen-booking-panel-wide">
                    <div class="goshen-booking-panel-heading">
                        <div>
                            <h3>Sales timeline</h3>
                            <p>Paid revenue and issued ticket count by day, week, and month.</p>
                        </div>
                    </div>

                    <div class="goshen-booking-timeline-grid">
                        @foreach ([['Daily', $dailySales], ['Weekly', $weeklySales], ['Monthly', $monthlySales]] as [$title, $rows])
                            <div class="goshen-booking-timeline">
                                <div class="goshen-booking-subtitle">{{ $title }}</div>

                                @forelse ($rows as $row)
                                    <div class="goshen-booking-row">
                                        <div class="goshen-booking-row-top">
                                            <span>{{ $row['label'] }}</span>
                                            <strong>{{ number_format($row['tickets']) }} ticket(s)</strong>
                                        </div>
                                        <div class="goshen-booking-row-meta">{{ $row['amount'] }}</div>
                                        <div class="goshen-booking-bar">
                                            <span style="width: {{ max(4, $row['bar']) }}%;"></span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="goshen-booking-empty">No sales yet.</div>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="goshen-booking-panel">
                    <div class="goshen-booking-panel-heading">
                        <div>
                            <h3>Recent purchases</h3>
                            <p>Latest settled Goshen transactions.</p>
                        </div>
                    </div>

                    <div class="goshen-booking-list">
                        @forelse ($recentPurchases as $purchase)
                            <div class="goshen-booking-purchase">
                                <div>
                                    <strong>{{ $purchase['customer'] }}</strong>
                                    <span>{{ $purchase['email'] }}</span>
                                    <small>{{ $purchase['event'] }}</small>
                                </div>
                                <div class="goshen-booking-purchase-side">
                                    <strong>{{ $purchase['amount'] }}</strong>
                                    <span>{{ $purchase['method'] }} · {{ number_format($purchase['tickets']) }} ticket(s)</span>
                                    <small>{{ $purchase['paid_at'] }}</small>
                                </div>
                            </div>
                        @empty
                            <div class="goshen-booking-empty">No paid Goshen purchases found yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="goshen-booking-panel">
                <div class="goshen-booking-panel-heading">
                    <div>
                        <h3>Performance by Goshen edition</h3>
                        <p>Compare issued tickets, paid bookings, revenue, check-in progress, and latest sale per edition.</p>
                    </div>
                </div>

                <div class="goshen-booking-editions">
                    @forelse ($editions as $edition)
                        <div class="goshen-booking-edition">
                            <div class="goshen-booking-edition-main">
                                <strong>{{ $edition['name'] }}</strong>
                                @if ($edition['venue'])
                                    <span>{{ $edition['venue'] }}</span>
                                @endif
                            </div>

                            <div class="goshen-booking-edition-stat">
                                <span>Tickets</span>
                                <strong>{{ number_format($edition['tickets_sold']) }}</strong>
                            </div>
                            <div class="goshen-booking-edition-stat">
                                <span>Paid bookings</span>
                                <strong>{{ number_format($edition['paid_bookings']) }}</strong>
                            </div>
                            <div class="goshen-booking-edition-stat">
                                <span>Revenue</span>
                                <strong>{{ $edition['revenue'] }}</strong>
                            </div>
                            <div class="goshen-booking-edition-stat">
                                <span>Checked in</span>
                                <strong>{{ number_format($edition['checked_in']) }}</strong>
                            </div>
                            <div class="goshen-booking-edition-stat">
                                <span>Latest sale</span>
                                <strong>{{ $edition['latest_sale'] }}</strong>
                            </div>
                        </div>
                    @empty
                        <div class="goshen-booking-empty">No Goshen Retreat edition has been configured yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <style>
            .goshen-booking-widget {
                display: grid;
                gap: 18px;
            }

            .goshen-booking-cards {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 14px;
            }

            .goshen-booking-card {
                min-height: 140px;
                border-radius: 22px;
                padding: 18px;
                overflow: hidden;
                position: relative;
                border: 1px solid rgba(148, 163, 184, .22);
                box-shadow: 0 18px 36px rgba(15, 23, 42, .08);
            }

            .goshen-booking-card::after {
                content: "";
                position: absolute;
                right: -32px;
                top: -42px;
                width: 110px;
                height: 110px;
                border-radius: 999px;
                background: rgba(255, 255, 255, .16);
            }

            .goshen-booking-card-label {
                font-size: 12px;
                font-weight: 900;
                letter-spacing: .08em;
                text-transform: uppercase;
                opacity: .75;
            }

            .goshen-booking-card-value {
                margin-top: 16px;
                font-size: 26px;
                line-height: 1.08;
                font-weight: 950;
            }

            .goshen-booking-card-meta {
                margin-top: 10px;
                font-size: 12px;
                line-height: 1.45;
                font-weight: 800;
                opacity: .78;
            }

            .goshen-booking-card-navy { background: linear-gradient(135deg, #082f49, #0f766e); color: #fff; }
            .goshen-booking-card-gold { background: linear-gradient(135deg, #f59e0b, #fde68a); color: #111827; }
            .goshen-booking-card-green { background: linear-gradient(135deg, #064e3b, #10b981); color: #fff; }
            .goshen-booking-card-blue { background: linear-gradient(135deg, #0f172a, #2563eb); color: #fff; }
            .goshen-booking-card-purple { background: linear-gradient(135deg, #312e81, #a855f7); color: #fff; }
            .goshen-booking-card-slate { background: linear-gradient(135deg, #111827, #475569); color: #fff; }

            .goshen-booking-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
                gap: 18px;
            }

            .goshen-booking-panel {
                border-radius: 24px;
                border: 1px solid rgba(148, 163, 184, .24);
                background: rgba(248, 250, 252, .74);
                padding: 20px;
                box-shadow: 0 18px 42px rgba(15, 23, 42, .06);
            }

            .goshen-booking-panel-heading {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 16px;
            }

            .goshen-booking-panel h3 {
                margin: 0;
                color: #0f172a;
                font-size: 18px;
                font-weight: 950;
            }

            .goshen-booking-panel p {
                margin: 5px 0 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.5;
            }

            .goshen-booking-timeline-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            .goshen-booking-timeline,
            .goshen-booking-purchase,
            .goshen-booking-edition {
                border-radius: 18px;
                border: 1px solid rgba(148, 163, 184, .2);
                background: #fff;
            }

            .goshen-booking-timeline {
                padding: 14px;
            }

            .goshen-booking-subtitle {
                margin-bottom: 10px;
                color: #0c2230;
                font-size: 13px;
                font-weight: 950;
                text-transform: uppercase;
                letter-spacing: .08em;
            }

            .goshen-booking-row {
                padding: 10px 0;
                border-top: 1px solid rgba(148, 163, 184, .18);
            }

            .goshen-booking-row:first-of-type {
                border-top: 0;
            }

            .goshen-booking-row-top {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                color: #334155;
                font-size: 12px;
            }

            .goshen-booking-row-top strong,
            .goshen-booking-purchase strong,
            .goshen-booking-edition strong {
                color: #0f172a;
                font-weight: 950;
            }

            .goshen-booking-row-meta {
                margin-top: 4px;
                color: #a16207;
                font-size: 12px;
                font-weight: 900;
            }

            .goshen-booking-bar {
                height: 8px;
                margin-top: 8px;
                border-radius: 999px;
                overflow: hidden;
                background: #e2e8f0;
            }

            .goshen-booking-bar span {
                display: block;
                height: 100%;
                border-radius: 999px;
                background: linear-gradient(90deg, #f59e0b, #0f766e);
            }

            .goshen-booking-list {
                display: grid;
                gap: 10px;
            }

            .goshen-booking-purchase {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 14px;
                padding: 14px;
            }

            .goshen-booking-purchase span,
            .goshen-booking-purchase small,
            .goshen-booking-edition span {
                display: block;
                margin-top: 3px;
                color: #64748b;
                font-size: 12px;
                line-height: 1.4;
            }

            .goshen-booking-purchase-side {
                text-align: right;
                white-space: nowrap;
            }

            .goshen-booking-editions {
                display: grid;
                gap: 10px;
            }

            .goshen-booking-edition {
                display: grid;
                grid-template-columns: minmax(240px, 1.5fr) repeat(5, minmax(120px, 1fr));
                gap: 14px;
                align-items: center;
                padding: 16px;
            }

            .goshen-booking-edition-main strong {
                font-size: 15px;
            }

            .goshen-booking-edition-stat span {
                color: #94a3b8;
                font-size: 11px;
                font-weight: 900;
                letter-spacing: .06em;
                text-transform: uppercase;
            }

            .goshen-booking-edition-stat strong {
                display: block;
                margin-top: 4px;
                font-size: 13px;
            }

            .goshen-booking-empty {
                border-radius: 18px;
                border: 1px dashed rgba(148, 163, 184, .4);
                padding: 18px;
                color: #64748b;
                background: rgba(255, 255, 255, .7);
                font-weight: 800;
            }

            @media (max-width: 1500px) {
                .goshen-booking-cards {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .goshen-booking-grid,
                .goshen-booking-timeline-grid {
                    grid-template-columns: 1fr;
                }

                .goshen-booking-edition {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 820px) {
                .goshen-booking-cards,
                .goshen-booking-edition,
                .goshen-booking-purchase {
                    grid-template-columns: 1fr;
                }

                .goshen-booking-purchase-side {
                    text-align: left;
                    white-space: normal;
                }
            }

            .dark .goshen-booking-panel,
            .dark .goshen-booking-timeline,
            .dark .goshen-booking-purchase,
            .dark .goshen-booking-edition {
                background: #111827;
                border-color: rgba(255, 255, 255, .08);
            }

            .dark .goshen-booking-panel h3,
            .dark .goshen-booking-subtitle,
            .dark .goshen-booking-row-top strong,
            .dark .goshen-booking-purchase strong,
            .dark .goshen-booking-edition strong {
                color: #f8fafc;
            }

            .dark .goshen-booking-panel p,
            .dark .goshen-booking-row-top,
            .dark .goshen-booking-purchase span,
            .dark .goshen-booking-purchase small,
            .dark .goshen-booking-edition span {
                color: #cbd5e1;
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
