<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageView;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PageAnalytics
{
    public function track(Page $page, Request $request): void
    {
        if (! $this->tableExists()) {
            return;
        }

        PageView::query()->create([
            'page_id' => $page->id,
            'path' => $this->normalizePath($request),
            'viewed_on' => now()->toDateString(),
            'visitor_hash' => $this->visitorHash($request),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'referrer_host' => $this->externalReferrerHost($request),
            'utm_source' => $this->nullableString($request->query('utm_source')),
            'utm_medium' => $this->nullableString($request->query('utm_medium')),
            'utm_campaign' => $this->nullableString($request->query('utm_campaign')),
            'utm_term' => $this->nullableString($request->query('utm_term')),
            'utm_content' => $this->nullableString($request->query('utm_content')),
            'device_type' => $this->deviceType($request->userAgent()),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'created_at' => now(),
        ]);
    }

    public function report(int $days = 14): array
    {
        if (! $this->tableExists()) {
            return [
                'summary' => [
                    'total_views' => 0,
                    'unique_visitors' => 0,
                    'views_today' => 0,
                    'unique_today' => 0,
                ],
                'trend' => [],
                'top_pages' => [],
                'top_referrers' => [],
            ];
        }

        $today = now()->startOfDay();
        $rangeStart = $today->copy()->subDays(max($days - 1, 0));

        $summary = [
            'total_views' => (int) PageView::query()->count(),
            'unique_visitors' => (int) PageView::query()->distinct('visitor_hash')->count('visitor_hash'),
            'views_today' => (int) PageView::query()->whereDate('created_at', $today)->count(),
            'unique_today' => (int) PageView::query()
                ->whereDate('created_at', $today)
                ->distinct('visitor_hash')
                ->count('visitor_hash'),
        ];

        $trendRows = PageView::query()
            ->selectRaw('viewed_on, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->whereBetween('viewed_on', [$rangeStart->toDateString(), $today->toDateString()])
            ->groupBy('viewed_on')
            ->orderBy('viewed_on')
            ->get()
            ->keyBy(fn (PageView $view) => $view->viewed_on->toDateString());

        $trend = collect(range(0, $days - 1))
            ->map(function (int $offset) use ($rangeStart, $trendRows) {
                $date = $rangeStart->copy()->addDays($offset)->toDateString();
                $row = $trendRows->get($date);

                return [
                    'date' => $date,
                    'label' => CarbonImmutable::parse($date)->format('M j'),
                    'views' => (int) ($row?->getAttribute('views') ?? 0),
                    'unique_visitors' => (int) ($row?->getAttribute('unique_visitors') ?? 0),
                ];
            })
            ->values()
            ->all();

        $topPages = PageView::query()
            ->select('page_id')
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT visitor_hash) as unique_visitors, MAX(created_at) as last_viewed_at')
            ->with('page:id,title,slug')
            ->groupBy('page_id')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn (PageView $view) => [
                'page_id' => $view->page_id,
                'title' => $view->page?->title ?? 'Deleted page',
                'slug' => $view->page?->slug ?? null,
                'views' => (int) $view->getAttribute('views'),
                'unique_visitors' => (int) $view->getAttribute('unique_visitors'),
                'last_viewed_at' => $view->getAttribute('last_viewed_at'),
            ])
            ->values()
            ->all();

        $topReferrers = PageView::query()
            ->select('referrer_host')
            ->selectRaw('COUNT(*) as visits')
            ->whereNotNull('referrer_host')
            ->groupBy('referrer_host')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(fn (PageView $view) => [
                'host' => (string) $view->referrer_host,
                'visits' => (int) $view->getAttribute('visits'),
            ])
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'trend' => $trend,
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
        ];
    }

    private function normalizePath(Request $request): string
    {
        return $request->getPathInfo() ?: '/';
    }

    private function visitorHash(Request $request): string
    {
        $parts = [
            (string) config('app.key'),
            (string) $request->ip(),
            Str::limit((string) $request->userAgent(), 255, ''),
            (string) $request->cookie(config('session.cookie')),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function externalReferrerHost(Request $request): ?string
    {
        $referrer = trim((string) $request->headers->get('referer'));

        if ($referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        $currentHost = parse_url($request->fullUrl(), PHP_URL_HOST);

        if (! is_string($host) || $host === '' || $host === $currentHost) {
            return null;
        }

        return Str::lower($host);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function deviceType(?string $userAgent): string
    {
        $agent = Str::lower((string) $userAgent);

        if ($agent === '') {
            return 'unknown';
        }

        if (Str::contains($agent, ['ipad', 'tablet'])) {
            return 'tablet';
        }

        if (Str::contains($agent, ['mobile', 'iphone', 'android'])) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('page_views');
        } catch (\Throwable) {
            return false;
        }
    }
}
