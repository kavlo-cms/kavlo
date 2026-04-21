<?php

namespace App\Plugins;

use App\Services\HookManager;
use App\Services\KavloStorage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PluginContext
{
    private ?PluginStorage $storage = null;

    private ?PluginHttpClient $http = null;

    public function __construct(
        private readonly PluginManifest $manifest,
        private readonly HookManager $hooks,
    ) {}

    public function slug(): string
    {
        return $this->manifest->slug;
    }

    public function tablePrefix(): string
    {
        return $this->manifest->tablePrefix();
    }

    public function path(string $relative = ''): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        return $relative === ''
            ? $this->manifest->directory
            : $this->manifest->directory.'/'.$relative;
    }

    public function hasScope(string $scope): bool
    {
        return $this->manifest->hasScope($scope);
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->requireScope('hooks:write');

        $this->hooks->addFilter($hook, $callback, $priority);
    }

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->requireScope('hooks:write');

        $this->hooks->addAction($hook, $callback, $priority);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function registerAdminNav(array $items): void
    {
        $this->requireScope('admin_nav:write');

        $normalized = array_map(
            fn (array $item) => $this->normalizeAdminNavItem($item),
            $items,
        );

        $this->hooks->addFilter('admin.nav', fn (array $current) => [...$current, ...$normalized]);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public function registerFormBlock(array $block): void
    {
        $this->requireScope('forms:write');
        $normalized = $this->normalizeLabeledRegistration($block, 'form block', ['type', 'label']);
        $normalized['source'] = $normalized['source'] ?? $this->slug();

        $this->hooks->addFilter('form_builder.blocks', fn (array $current) => [...$current, $normalized]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    public function registerFormAction(array $action): void
    {
        $this->requireScope('forms:write');
        $normalized = $this->normalizeLabeledRegistration($action, 'form action', ['key', 'label']);

        if (! isset($action['handler']) || ! is_callable($action['handler'])) {
            throw new RuntimeException("Plugin [{$this->slug()}] registered a form action without a callable handler.");
        }

        $normalized['description'] = (string) ($action['description'] ?? '');
        $normalized['source'] = (string) ($action['source'] ?? $this->slug());
        $normalized['fields'] = is_array($action['fields'] ?? null) ? array_values($action['fields']) : [];
        $normalized['handler'] = $action['handler'];

        $this->hooks->addFilter('form_actions', fn (array $current) => [...$current, $normalized]);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public function registerEmbeddableForm(array $form): void
    {
        $this->requireScope('forms:write');
        $normalized = $this->normalizeLabeledRegistration($form, 'embeddable form', ['key', 'label']);
        $normalized['source'] = (string) ($form['source'] ?? $this->slug());
        $normalized['description'] = (string) ($form['description'] ?? '');
        $normalized['view'] = $form['view'] ?? null;
        $normalized['render'] = $form['render'] ?? null;
        $normalized['preview_blocks'] = $form['preview_blocks'] ?? null;
        $normalized['preview_html'] = $form['preview_html'] ?? null;

        $this->hooks->addFilter('embeddable_forms', fn (array $current) => [...$current, $normalized]);
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    public function registerDataHubChannel(array $channel): void
    {
        $this->requireScope('datahub:write');
        $normalized = $this->normalizeLabeledRegistration($channel, 'DataHub channel', ['key', 'label', 'type']);
        $normalized['source'] = (string) ($channel['source'] ?? $this->slug());
        $this->hooks->addFilter('datahub.channels', fn (array $current) => [...$current, $normalized]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    public function registerDataHubResource(array $resource): void
    {
        $this->requireScope('datahub:write');
        $normalized = $this->normalizeLabeledRegistration($resource, 'DataHub resource', ['key', 'label']);
        $normalized['source'] = (string) ($resource['source'] ?? $this->slug());
        $normalized['fields'] = is_array($resource['fields'] ?? null) ? array_values($resource['fields']) : [];
        $normalized['supports'] = is_array($resource['supports'] ?? null) ? array_values($resource['supports']) : [];
        $normalized['description'] = (string) ($resource['description'] ?? '');
        $normalized['model'] = $resource['model'] ?? null;
        $normalized['graphql_type'] = $resource['graphql_type'] ?? null;
        $normalized['record_count'] = $resource['record_count'] ?? null;
        $normalized['generated_routes'] = $resource['generated_routes'] ?? null;

        $this->hooks->addFilter('datahub.resources', fn (array $current) => [...$current, $normalized]);
    }

    public function registerMediaUsage(callable $resolver): void
    {
        $this->requireScope('media:read');

        $this->hooks->addFilter('media.usage.references', function (array $current, Media $media) use ($resolver) {
            $references = $resolver($media);

            if (! is_array($references)) {
                return $current;
            }

            $normalized = array_values(array_filter(array_map(
                fn (mixed $reference) => is_array($reference)
                    ? $this->normalizeMediaReference($reference)
                    : null,
                $references,
            )));

            return [...$current, ...$normalized];
        });
    }

    public function storage(): PluginStorage
    {
        return $this->storage ??= new PluginStorage($this->manifest, app(KavloStorage::class));
    }

    public function http(): PluginHttpClient
    {
        return $this->http ??= new PluginHttpClient($this->manifest);
    }

    private function requireScope(string $scope): void
    {
        if (! $this->hasScope($scope)) {
            throw new RuntimeException("Plugin [{$this->slug()}] requires the [{$scope}] scope.");
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $requiredKeys
     * @return array<string, mixed>
     */
    private function normalizeLabeledRegistration(array $item, string $label, array $requiredKeys): array
    {
        foreach ($requiredKeys as $key) {
            $value = trim((string) ($item[$key] ?? ''));

            if ($value === '') {
                throw new RuntimeException("Plugin [{$this->slug()}] registered an invalid {$label}.");
            }

            $item[$key] = $value;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function normalizeAdminNavItem(array $item): array
    {
        $group = trim((string) ($item['group'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $href = trim((string) ($item['href'] ?? ''));
        $icon = trim((string) ($item['icon'] ?? ''));
        $permission = trim((string) ($item['permission'] ?? ''));

        if ($group === '' || $title === '' || $href === '') {
            throw new RuntimeException("Plugin [{$this->slug()}] registered an invalid admin navigation item.");
        }

        $normalized = [
            'group' => $group,
            'title' => $title,
            'href' => $href,
        ];

        if ($icon !== '') {
            $normalized['icon'] = $icon;
        }

        if ($permission !== '') {
            $normalized['permission'] = $permission;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, string>
     */
    private function normalizeMediaReference(array $reference): array
    {
        $type = trim((string) ($reference['type'] ?? 'plugin'));
        $label = trim((string) ($reference['label'] ?? ''));
        $href = trim((string) ($reference['href'] ?? ''));
        $context = trim((string) ($reference['context'] ?? ''));

        if ($label === '' || $href === '' || $context === '') {
            throw new RuntimeException("Plugin [{$this->slug()}] registered an invalid media usage reference.");
        }

        return [
            'type' => $type !== '' ? $type : 'plugin',
            'label' => $label,
            'href' => $href,
            'context' => $context,
        ];
    }
}
