<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SiteScript;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ScriptManager
{
    public const CACHE_KEY = 'cms.site_scripts.v1';

    public function __construct(
        private readonly KavloStorage $storage,
    ) {}

    public function placementOptions(): array
    {
        return [
            ['value' => 'head', 'label' => 'Head', 'description' => 'Inject before </head>.'],
            ['value' => 'body_start', 'label' => 'Body Start', 'description' => 'Inject right after <body>.'],
            ['value' => 'body_end', 'label' => 'Body End', 'description' => 'Inject before </body>.'],
        ];
    }

    public function sourceTypeOptions(): array
    {
        return [
            ['value' => 'inline', 'label' => 'Inline code', 'description' => 'Paste JavaScript or a full embed snippet.'],
            ['value' => 'url', 'label' => 'External URL', 'description' => 'Load a remote script by URL.'],
            ['value' => 'upload', 'label' => 'Uploaded file', 'description' => 'Upload a local .js or .mjs file.'],
        ];
    }

    public function loadStrategyOptions(): array
    {
        return [
            ['value' => 'blocking', 'label' => 'Blocking', 'description' => 'Load immediately in document order.'],
            ['value' => 'defer', 'label' => 'Defer', 'description' => 'Load after parsing the document.'],
            ['value' => 'async', 'label' => 'Async', 'description' => 'Load independently as soon as it is available.'],
        ];
    }

    public function all(): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        return SiteScript::query()
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function enabled(): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        $cached = $this->cachedEnabledRows();

        if (is_array($cached) && $this->cachePayloadIsValid($cached)) {
            return SiteScript::hydrate($cached);
        }

        $this->forget();

        $rows = SiteScript::query()
            ->where('is_enabled', true)
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteScript $script) => $script->attributesToArray())
            ->all();

        Cache::forever(self::CACHE_KEY, $rows);

        return SiteScript::hydrate($rows);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function render(string $placement): string
    {
        $managed = $this->enabled()
            ->where('placement', $placement)
            ->map(fn (SiteScript $script) => $this->renderScript($script))
            ->filter()
            ->implode(PHP_EOL);

        $legacy = $this->legacyScripts($placement);

        return trim(implode(PHP_EOL, array_filter([$managed, $legacy])));
    }

    private function renderScript(SiteScript $script): string
    {
        return match ($script->source_type) {
            'inline' => $this->renderInline($script),
            'url' => $this->renderExternal($script->source_url, $script->load_strategy),
            'upload' => $this->renderExternal(
                $script->file_path ? $this->storage->publicUrl($script->file_path) : null,
                $script->load_strategy,
            ),
            default => '',
        };
    }

    private function renderInline(SiteScript $script): string
    {
        $content = trim((string) $script->inline_content);

        if ($content === '') {
            return '';
        }

        if (preg_match('/<\s*(script|noscript)\b/i', $content) === 1) {
            return $content;
        }

        return "<script>\n{$content}\n</script>";
    }

    private function renderExternal(?string $url, string $strategy): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $attributes = [
            'src="'.e($url).'"',
        ];

        if ($strategy === 'async') {
            $attributes[] = 'async';
        } elseif ($strategy === 'defer') {
            $attributes[] = 'defer';
        }

        return '<script '.implode(' ', $attributes).'></script>';
    }

    private function legacyScripts(string $placement): string
    {
        try {
            return match ($placement) {
                'head' => trim((string) Setting::get('head_scripts', '')),
                'body_end' => trim((string) Setting::get('body_scripts', '')),
                default => '',
            };
        } catch (\Throwable) {
            return '';
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('site_scripts');
        } catch (\Throwable) {
            return false;
        }
    }

    private function cachedEnabledRows(): mixed
    {
        try {
            return Cache::get(self::CACHE_KEY);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cachePayloadIsValid(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }
        }

        return true;
    }
}
