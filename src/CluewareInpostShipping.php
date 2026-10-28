<?php declare(strict_types=1);

namespace Clueware\InpostShipping;

use Clueware\InpostShipping\Configuration\PhoneFieldVisibilitySynchronizer;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class CluewareInpostShipping extends Plugin
{
    public function activate(ActivateContext $context): void
    {
        // Availability is reconciled by the post-activation subscriber. Keeping
        // the methods disabled here prevents an unconfigured fresh install from
        // exposing a checkout option that cannot be completed.
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setInpostShippingMethodsActive(false);
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $mediaIds = $connection->fetchFirstColumn(
            <<<'SQL'
SELECT `id`
FROM `media`
WHERE JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.cluewareInpostShippingAsset')) = 'true'
SQL
        );

        $connection->executeStatement(
            'DELETE FROM `sales_channel_shipping_method` WHERE `shipping_method_id` = :lockerShippingMethodId OR `shipping_method_id` = :courierShippingMethodId',
            [
                'lockerShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::LOCKER_SHIPPING_METHOD_ID),
                'courierShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::COURIER_SHIPPING_METHOD_ID),
            ]
        );
        $connection->executeStatement(
            'UPDATE `shipping_method` SET `media_id` = NULL WHERE `id` = :lockerShippingMethodId OR `id` = :courierShippingMethodId',
            [
                'lockerShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::LOCKER_SHIPPING_METHOD_ID),
                'courierShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::COURIER_SHIPPING_METHOD_ID),
            ]
        );
        $connection->executeStatement(
            'DROP TABLE IF EXISTS `clueware_inpost_shipment`'
        );

        if ($mediaIds !== []) {
            /** @var EntityRepository<MediaCollection> $mediaRepository */
            $mediaRepository = $this->container->get('media.repository');
            $mediaRepository->delete(
                array_map(
                    static fn (string $mediaId): array => ['id' => Uuid::fromBytesToHex($mediaId)],
                    $mediaIds
                ),
                $context->getContext()
            );
        }
    }

    public function postUpdate(UpdateContext $context): void
    {
        $this->container->get(\Clueware\InpostShipping\Shipping\ShippingMethodAvailabilitySynchronizer::class)
            ->synchronize();

        // Build this service explicitly so updates also work with the container
        // compiled for the previous plugin version.
        $systemConfigService = $this->container->get(SystemConfigService::class);
        (new PhoneFieldVisibilitySynchronizer(
            $this->container->get(Connection::class),
            new PluginConfig($systemConfigService),
            $systemConfigService,
        ))->synchronize();
    }

    private function setInpostShippingMethodsActive(bool $active): void
    {
        $this->container->get(Connection::class)->executeStatement(
            'UPDATE `shipping_method` SET `active` = :active WHERE `id` = :lockerShippingMethodId OR `id` = :courierShippingMethodId',
            [
                'active' => (int) $active,
                'lockerShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::LOCKER_SHIPPING_METHOD_ID),
                'courierShippingMethodId' => Uuid::fromHexToBytes(PluginConfig::COURIER_SHIPPING_METHOD_ID),
            ]
        );
    }
}
