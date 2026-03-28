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
        if (!preg_match('#^/t/([a-z0-9-]+)(?:/|$)#', $request->getPathInfo(), $matches)) {
            $this->tenantContext->setTenantSlug(null);
            $this->databaseSwitcher->resetToBaseDatabase();

            return;
        }

        $tenantSlug = $matches[1];
        $request->attributes->set('tenantSlug', $tenantSlug);
        $this->tenantContext->setTenantSlug($tenantSlug);
        $this->databaseSwitcher->switchToTenant($tenantSlug);
    }
}
