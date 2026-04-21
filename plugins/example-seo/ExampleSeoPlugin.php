<?php

namespace Plugins\ExampleSeo;

use App\Contracts\PluginBase;
use App\Models\Form;
use App\Plugins\PluginContext;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ExampleSeoPlugin implements PluginBase
{
    public function boot(PluginContext $context): void
    {
        $storage = $context->storage();
        $http = $context->http()
            ->acceptJson()
            ->withHeaders([
                'X-Plugin-Slug' => $context->slug(),
            ]);

        $context->addFilter('blocks.available', function (array $blocks) {
            $blocks[] = [
                'type' => 'seo-audit-summary',
                'label' => 'SEO Audit Summary',
                'description' => 'Example plugin page-builder block for highlighting an SEO audit result or CTA.',
                'group' => 'components',
                'icon' => 'Search',
                'fields' => [
                    ['key' => 'label', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'summary', 'label' => 'Summary', 'type' => 'textarea'],
                    [
                        'key' => 'severity',
                        'label' => 'Severity',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Info', 'value' => 'info'],
                            ['label' => 'Warning', 'value' => 'warning'],
                            ['label' => 'Critical', 'value' => 'critical'],
                        ],
                    ],
                    ['key' => 'cta_label', 'label' => 'CTA Label', 'type' => 'text'],
                    ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url'],
                ],
                'defaultData' => [
                    'label' => 'SEO health check',
                    'summary' => 'Fix metadata and internal linking to improve crawlability.',
                    'severity' => 'warning',
                    'cta_label' => 'Request audit',
                    'cta_url' => '/contact',
                ],
                'source' => 'example-seo',
            ];

            return $blocks;
        });

        $context->addFilter('blocks.render', function ($rendered, array $block, array $data) {
            if ($rendered !== null || ($block['type'] ?? null) !== 'seo-audit-summary') {
                return $rendered;
            }

            $severity = (string) ($data['severity'] ?? 'info');
            $severityClasses = [
                'info' => 'border-sky-200 bg-sky-50 text-sky-900',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
                'critical' => 'border-rose-200 bg-rose-50 text-rose-900',
            ];
            $severityLabel = [
                'info' => 'Info',
                'warning' => 'Warning',
                'critical' => 'Critical',
            ][$severity] ?? 'Info';
            $cardClass = $severityClasses[$severity] ?? $severityClasses['info'];

            $label = e((string) ($data['label'] ?? 'SEO health check'));
            $summary = e((string) ($data['summary'] ?? ''));
            $ctaLabel = trim((string) ($data['cta_label'] ?? ''));
            $ctaUrl = trim((string) ($data['cta_url'] ?? ''));
            $ctaHtml = '';

            if ($ctaLabel !== '' && $ctaUrl !== '') {
                $ctaText = e($ctaLabel);
                $ctaHref = e($ctaUrl);
                $ctaHtml = <<<HTML
<div class="mt-4">
    <a href="{$ctaHref}" class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">{$ctaText}</a>
</div>
HTML;
            }

            return new HtmlString(<<<HTML
<section class="example-seo-audit-summary rounded-2xl border p-6 shadow-sm {$cardClass}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-semibold">{$label}</h2>
        <span class="rounded-full border border-current/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]">{$severityLabel}</span>
    </div>
    <p class="mt-3 max-w-3xl text-sm/6">{$summary}</p>
    {$ctaHtml}
</section>
HTML);
        });

        $context->addFilter('page_types', function (array $types) {
            $types[] = [
                'type' => 'seo-landing-page',
                'label' => 'SEO Landing Page',
                'view' => 'pages.show',
                'description' => 'Example plugin page type for SEO campaign landing pages.',
                'source' => 'example-seo',
            ];

            return $types;
        });

        $context->registerFormBlock([
            'type' => 'seo-consent',
            'label' => 'SEO Consent',
            'description' => 'Example plugin block that captures whether the submitter wants an SEO follow-up.',
            'group' => 'form',
            'icon' => 'Search',
            'fields' => [
                ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
            ],
            'defaultData' => [
                'label' => 'I want an SEO follow-up',
                'key' => 'seo_consent',
                'required' => false,
                'options' => [
                    ['label' => 'Yes, contact me about SEO improvements', 'value' => 'yes'],
                ],
            ],
        ]);

        $context->registerFormAction([
            'key' => 'example-seo.audit-webhook',
            'label' => 'SEO Audit Webhook',
            'description' => 'Stores a submission snapshot in plugin storage and can optionally POST it to an external SEO endpoint.',
            'fields' => [
                [
                    'key' => 'endpoint',
                    'label' => 'Webhook URL',
                    'type' => 'url',
                    'placeholder' => 'https://example.com/api/seo-leads',
                ],
                [
                    'key' => 'success_message',
                    'label' => 'Success Message',
                    'type' => 'textarea',
                    'placeholder' => 'Thanks, we will review your SEO request.',
                ],
            ],
            'handler' => function (Form $form, array $data, Request $request, array $config) use ($storage, $http): array {
                $payload = [
                    'form' => [
                        'id' => $form->id,
                        'name' => $form->name,
                        'slug' => $form->slug,
                    ],
                    'submission' => $data,
                    'request' => [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                ];

                $storage->put(
                    'submissions/'.Str::uuid().'.json',
                    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
                );

                $endpoint = trim((string) ($config['endpoint'] ?? ''));

                if ($endpoint !== '') {
                    $http->post($endpoint, $payload);
                }

                return [
                    'message' => $config['success_message'] ?? 'Thanks, we will review your SEO request.',
                ];
            },
        ]);

        $context->registerEmbeddableForm([
            'key' => 'example-seo-lead-capture',
            'label' => 'SEO Lead Capture',
            'description' => 'Example embeddable form registration exposed by the SEO plugin.',
            'preview_html' => <<<'HTML'
<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/80 p-5 text-sm text-slate-700">
    <div class="font-semibold text-slate-900">SEO lead capture</div>
    <p class="mt-2">Plugin-managed CTA block with a custom renderer and delivery workflow.</p>
</div>
HTML,
            'render' => function (array $payload): string {
                $registration = is_array($payload['registration'] ?? null) ? $payload['registration'] : [];
                $label = e((string) ($registration['label'] ?? 'SEO Lead Capture'));

                return <<<HTML
<section class="example-seo-lead-capture rounded-2xl border border-slate-200 bg-slate-50 px-6 py-8 shadow-sm">
    <div class="mx-auto max-w-2xl space-y-3 text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Plugin demo</p>
        <h2 class="text-2xl font-semibold text-slate-900">{$label}</h2>
        <p class="text-sm text-slate-600">This embeddable form is rendered directly by the Example SEO plugin so themes and plugins can ship custom lead-capture experiences.</p>
        <div class="flex justify-center">
            <a href="/contact" class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Request an audit</a>
        </div>
    </div>
</section>
HTML;
            },
        ]);

        $context->registerDataHubChannel([
            'key' => 'example-seo-webhook',
            'label' => 'SEO Webhook',
            'type' => 'webhook',
            'endpoint' => '/plugins/example-seo/webhook',
            'path' => '/plugins/example-seo/webhook',
            'description' => 'Example outbound integration channel for SEO lead delivery.',
            'visibility' => 'Plugin-managed',
        ]);

        $context->registerDataHubResource([
            'key' => 'seo-metadata',
            'label' => 'SEO Metadata',
            'description' => 'Example plugin resource describing SEO fields a plugin could expose to the DataHub.',
            'supports' => ['graphql'],
            'fields' => ['meta_title', 'meta_description', 'canonical_url', 'structured_data'],
        ]);

        $context->registerMediaUsage(function ($media) {
            $fileName = Str::lower((string) $media->file_name);

            if (! str_contains($fileName, 'seo') && ! str_contains($fileName, 'og')) {
                return [];
            }

            return [[
                'type' => 'plugin',
                'label' => 'Example SEO Plugin',
                'href' => '/admin/plugins',
                'context' => 'Referenced by plugin SEO assets',
            ]];
        });

        $context->addFilter('plugins.example-seo.capabilities', function (array $capabilities) use ($storage, $http) {
            $capabilities['admin_nav'] = [
                'group' => 'Content',
                'title' => 'SEO',
                'href' => '/admin/plugins',
            ];

            $capabilities['page_builder_block'] = [
                'type' => 'seo-audit-summary',
                'group' => 'components',
            ];

            $capabilities['page_type'] = [
                'type' => 'seo-landing-page',
                'view' => 'pages.show',
            ];

            $capabilities['storage'] = [
                'private_root' => $storage->root(),
                'public_root' => $storage->root('public'),
                'public_example_url' => $storage->url('examples/og-image.jpg'),
            ];

            $capabilities['http'] = [
                'user_agent' => $http->defaultHeaders()['User-Agent'],
                'headers' => $http->defaultHeaders(),
                'timeout' => $http->defaultTimeout(),
            ];

            return $capabilities;
        });
    }
}
