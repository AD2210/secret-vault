<?php

declare(strict_types=1);

namespace App\Routing;

use App\Tenancy\TenantContext;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final readonly class TenantAwareRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    public function __construct(
        private RouterInterface $inner,
        private RequestStack $requestStack,
        private TenantContext $tenantContext,
    ) {
    }

    public function setContext(RequestContext $context): void
    {
        if ($this->inner instanceof RequestContextAwareInterface) {
            $this->inner->setContext($context);
        }
    }

    public function getContext(): RequestContext
    {
        if ($this->inner instanceof RequestContextAwareInterface) {
            return $this->inner->getContext();
        }

        throw new \LogicException('Wrapped router does not expose a request context.');
    }

    public function generate(string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $route = $this->inner->getRouteCollection()->get($name);
        if (null === $route) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
        }

        if (in_array('tenantSlug', $route->compile()->getPathVariables(), true) && !array_key_exists('tenantSlug', $parameters)) {
            $tenantSlug = $this->requestStack->getCurrentRequest()?->attributes->get('tenantSlug');
            if (!is_string($tenantSlug) || '' === $tenantSlug) {
                $tenantSlug = $this->tenantContext->getTenantSlug();
            }

            if (is_string($tenantSlug) && '' !== $tenantSlug) {
                $parameters['tenantSlug'] = $tenantSlug;
            }
        }

        return $this->inner->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        return $this->inner->match($pathinfo);
    }

    public function matchRequest(Request $request): array
    {
        if (!$this->inner instanceof RequestMatcherInterface) {
            throw new \LogicException('Wrapped router does not implement RequestMatcherInterface.');
        }

        return $this->inner->matchRequest($request);
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->inner instanceof WarmableInterface) {
            return $this->inner->warmUp($cacheDir, $buildDir);
        }

        return [];
    }
}
