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
    public function __construct(
        private TenantContext $tenantContext,
        private TenantDatabaseSwitcher $databaseSwitcher,
        private string $defaultUri,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
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
            return null;
        }

        return preg_match('/^[a-z0-9-]+$/', $subdomain) ? $subdomain : null;
    }
}
