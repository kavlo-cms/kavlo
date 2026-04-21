<?php

namespace App\Plugins;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PluginHttpClient
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private readonly PluginManifest $manifest,
        private readonly array $headers = [],
        private readonly ?string $baseUrl = null,
        private readonly int $timeout = 10,
        private readonly int $connectTimeout = 5,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            manifest: $this->manifest,
            headers: [...$this->headers, ...$headers],
            baseUrl: $this->baseUrl,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
        );
    }

    public function acceptJson(): self
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
        ]);
    }

    public function asJson(): self
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    public function baseUrl(string $baseUrl): self
    {
        $baseUrl = trim($baseUrl);
        $this->assertUrlAllowed($baseUrl);

        return new self(
            manifest: $this->manifest,
            headers: $this->headers,
            baseUrl: rtrim($baseUrl, '/'),
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
        );
    }

    public function timeout(int $seconds): self
    {
        return new self(
            manifest: $this->manifest,
            headers: $this->headers,
            baseUrl: $this->baseUrl,
            timeout: max(1, $seconds),
            connectTimeout: $this->connectTimeout,
        );
    }

    public function connectTimeout(int $seconds): self
    {
        return new self(
            manifest: $this->manifest,
            headers: $this->headers,
            baseUrl: $this->baseUrl,
            timeout: $this->timeout,
            connectTimeout: max(1, $seconds),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $url, array $options = []): Response
    {
        $this->requireAccessForMethod($method);
        $normalizedUrl = trim($url);

        if ($normalizedUrl === '') {
            throw new RuntimeException('Plugin HTTP requests require a URL.');
        }

        if ($this->baseUrl === null && parse_url($normalizedUrl, PHP_URL_SCHEME) === null) {
            throw new RuntimeException('Plugin HTTP requests require an absolute URL or a configured base URL.');
        }

        $this->assertUrlAllowed($normalizedUrl);

        return $this->pendingRequest()->send(strtoupper($method), $normalizedUrl, $options);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $url, array $query = []): Response
    {
        return $this->request('GET', $url, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $url, array $data = []): Response
    {
        return $this->request('POST', $url, ['json' => $data]);
    }

    /**
     * @return array<string, string>
     */
    public function defaultHeaders(): array
    {
        $this->requireReadAccess();

        return [
            'User-Agent' => 'cms-plugin/'.$this->manifest->slug,
            ...$this->headers,
        ];
    }

    public function defaultTimeout(): int
    {
        $this->requireReadAccess();

        return $this->timeout;
    }

    private function pendingRequest(): PendingRequest
    {
        $request = Http::withHeaders($this->defaultHeaders())
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);

        return $this->baseUrl !== null ? $request->baseUrl($this->baseUrl) : $request;
    }

    private function assertUrlAllowed(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null) {
            return;
        }

        if (! in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new RuntimeException('Plugin HTTP requests may only use http or https URLs.');
        }
    }

    private function requireAccessForMethod(string $method): void
    {
        $normalizedMethod = strtoupper(trim($method));

        if (in_array($normalizedMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->requireReadAccess();

            return;
        }

        $this->requireWriteAccess();
    }

    private function requireReadAccess(): void
    {
        if (! $this->manifest->hasScope('http:read')) {
            throw new RuntimeException("Plugin [{$this->manifest->slug}] requires the [http:read] scope.");
        }
    }

    private function requireWriteAccess(): void
    {
        if (! $this->manifest->hasScope('http:write')) {
            throw new RuntimeException("Plugin [{$this->manifest->slug}] requires the [http:write] scope.");
        }
    }
}
