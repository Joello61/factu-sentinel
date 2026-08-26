<?php
declare(strict_types=1);
namespace App\Shared\DependencyInjection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class GesdinetReuseDetectionCachePass implements CompilerPassInterface
{
    private const SERVICE_ID = "gesdinet_jwt_refresh_token.spent_refresh_token_registry";
    private const CACHE_PARAMETER = "gesdinet_jwt_refresh_token.reuse_detection.cache";

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::SERVICE_ID) || !$container->hasParameter(self::CACHE_PARAMETER)) {
            return;
        }

        $cacheServiceId = $container->getParameter(self::CACHE_PARAMETER);
        \assert(\is_string($cacheServiceId));

        $def = $container->getDefinition(self::SERVICE_ID);
        $def->replaceArgument(0, new Reference($cacheServiceId));
    }
}
