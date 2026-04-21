<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    /**
     * Routes already covered well enough by model-level activity logging.
     *
     * @var array<int, string>
     */
    private array $ignoredRoutes = [
        'admin.pages.store',
        'admin.pages.destroy',
        'admin.forms.store',
        'admin.forms.destroy',
        'admin.email-templates.store',
        'admin.email-templates.destroy',
        'admin.account.profile',
        'admin.account.password',
        'admin.users.store',
        'admin.users.destroy',
    ];

    /**
     * @var array<int, string>
     */
    private array $sensitiveFields = [
        '_token',
        '_method',
        'current_password',
        'password',
        'password_confirmation',
        'token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request, $response)) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || ! str_starts_with($routeName, 'admin.') || in_array($routeName, $this->ignoredRoutes, true)) {
            return $response;
        }

        $subject = $this->resolveSubject($request);
        $action = $this->resolveAction($routeName, $request);
        $target = $this->resolveTarget($routeName, $subject, $request);

        $logger = activity()
            ->useLog('admin')
            ->causedBy($request->user())
            ->withProperties(array_filter([
                'route_name' => $routeName,
                'method' => $request->method(),
                'target' => $target,
                'subject_label' => $subject ? $this->subjectLabel($subject) : null,
                'changed_fields' => $this->changedFields($request),
            ], fn ($value) => ! ($value === null || $value === [] || $value === '')));

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->log(trim($action.' '.$target));

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->user()) {
            return false;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $errors = $request->session()->get('errors');

        if ($errors && method_exists($errors, 'any') && $errors->any()) {
            return false;
        }

        return true;
    }

    private function resolveSubject(Request $request): ?Model
    {
        foreach (($request->route()?->parameters() ?? []) as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    private function resolveAction(string $routeName, Request $request): string
    {
        return match ($routeName) {
            'admin.users.update-roles' => 'updated roles for',
            'admin.users.update-permissions' => 'updated permissions for',
            'admin.account.api-keys.store' => 'created',
            'admin.account.api-keys.rotate' => 'rotated',
            'admin.account.api-keys.destroy' => 'revoked',
            'admin.settings.email.test' => 'sent',
            default => match (Str::afterLast($routeName, '.')) {
                'store' => 'created',
                'update' => 'updated',
                'destroy' => 'deleted',
                'restore' => 'restored',
                'force-delete' => 'permanently deleted',
                'duplicate' => 'duplicated',
                'reorder' => 'reordered',
                'bulk' => 'bulk updated',
                'toggle' => 'toggled',
                'activate' => 'activated',
                'clear' => 'cleared',
                'enable' => 'enabled',
                'disable' => 'disabled',
                'test' => 'tested',
                'profile' => 'updated',
                'password' => 'changed',
                default => match ($request->method()) {
                    'POST' => 'ran',
                    'PUT', 'PATCH' => 'updated',
                    'DELETE' => 'deleted',
                    default => 'updated',
                },
            },
        };
    }

    private function resolveTarget(string $routeName, ?Model $subject, Request $request): string
    {
        if ($subject) {
            return $this->subjectLabel($subject);
        }

        $target = match ($routeName) {
            'admin.settings.update' => 'general settings',
            'admin.settings.email.update' => 'email settings',
            'admin.settings.email.test' => 'test email',
            'admin.cache.clear' => 'cache',
            'admin.maintenance.enable', 'admin.maintenance.disable' => 'maintenance mode',
            'admin.users.update-roles' => 'user access roles',
            'admin.users.update-permissions' => 'user direct permissions',
            default => $this->fallbackTarget($routeName),
        };

        $label = $request->string('name')->trim()->value()
            ?: $request->string('title')->trim()->value()
            ?: $request->string('slug')->trim()->value();

        if ($label && ! str_contains($target, 'settings')) {
            return sprintf('%s "%s"', $target, $label);
        }

        $id = $request->route()?->parameter('id');

        if ($id) {
            return "{$target} #{$id}";
        }

        return $target;
    }

    private function fallbackTarget(string $routeName): string
    {
        $segments = explode('.', Str::after($routeName, 'admin.'));
        array_pop($segments);

        if ($segments === ['account', 'api-keys']) {
            return 'API key';
        }

        return (string) Str::of(implode(' ', $segments))
            ->replace('-', ' ')
            ->singular()
            ->lower();
    }

    private function subjectLabel(Model $subject): string
    {
        $type = Str::headline(class_basename($subject));

        foreach (['title', 'name', 'slug', 'email'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (filled($value)) {
                return sprintf('%s "%s"', $type, $value);
            }
        }

        return sprintf('%s #%s', $type, $subject->getKey());
    }

    /**
     * @return array<int, string>
     */
    private function changedFields(Request $request): array
    {
        return collect($request->except($this->sensitiveFields))
            ->keys()
            ->map(fn ($key) => (string) $key)
            ->take(6)
            ->values()
            ->all();
    }
}
