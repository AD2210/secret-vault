<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabaseSwitcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class TenantRequestSubscriber implements EventSubscriberInterface
{
    private const string TENANT_SESSION_KEY = '_vault_tenant_slug';

    public function __construct(
        private TenantContext $tenantContext,
        private TenantDatabaseSwitcher $databaseSwitcher,
        private string $defaultUri,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tenantSlug = $this->resolveTenantSlug($request);
        if (null === $tenantSlug) {
            $this->tenantContext->setTenantSlug(null);
            $this->databaseSwitcher->resetToBaseDatabase();

            return;
        }

        $request->attributes->set('tenantSlug', $tenantSlug);
        $this->tenantContext->setTenantSlug($tenantSlug);
        if ($request->hasSession()) {
            $request->getSession()->set(self::TENANT_SESSION_KEY, $tenantSlug);
        }

        if ($this->shouldUseBaseDatabase($request)) {
            $this->databaseSwitcher->resetToBaseDatabase();

            return;
        }

        $this->databaseSwitcher->switchToTenant($tenantSlug);
    }

    private function resolveTenantSlug(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        if (preg_match('#^/t/([a-z0-9-]+)(?:/|$)#', $request->getPathInfo(), $matches)) {
            return $matches[1];
        }

        $baseHost = parse_url($this->defaultUri, PHP_URL_HOST);
        if (!is_string($baseHost) || '' === $baseHost) {
            return null;
        }

        $requestHost = mb_strtolower($request->getHost());
        $baseHost = mb_strtolower($baseHost);
        $suffix = '.'.$baseHost;
        if (!str_ends_with($requestHost, $suffix)) {
            return null;
        }

        $subdomain = substr($requestHost, 0, -strlen($suffix));
        if (false === $subdomain || '' === $subdomain || str_contains($subdomain, '.')) {
            return $this->resolveTenantSlugFromSession($request);
        }

        return preg_match('/^[a-z0-9-]+$/', $subdomain) ? $subdomain : $this->resolveTenantSlugFromSession($request);
    }

    private function shouldUseBaseDatabase(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        $path = $request->getPathInfo();

        return in_array($path, ['/login', '/logout', '/2fa', '/2fa_check'], true)
            || (bool) preg_match('#^/t/[a-z0-9-]+/(login|logout|2fa|2fa_check|security/2fa/setup)$#', $path);
    }

    private function resolveTenantSlugFromSession(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        if (!$this->shouldUseBaseDatabase($request) || !$request->hasSession()) {
            return null;
        }

        $tenantSlug = $request->getSession()->get(self::TENANT_SESSION_KEY);

        return is_string($tenantSlug) && '' !== trim($tenantSlug) ? trim($tenantSlug) : null;
    }
}
