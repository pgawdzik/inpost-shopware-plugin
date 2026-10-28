<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Shipping;

use Clueware\InpostShipping\Configuration\PluginConfig;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

final class ShippingMethodAvailabilitySynchronizer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PluginConfig $config,
    ) {
    }

    public function synchronize(?string $salesChannelId = null): void
    {
        $salesChannelIds = $salesChannelId === null
            ? $this->connection->fetchFirstColumn('SELECT `id` FROM `sales_channel`')
            : [Uuid::fromHexToBytes($salesChannelId)];

        $hasLocker = false;
        $hasCourier = false;
        foreach ($salesChannelIds as $salesChannelIdBytes) {
            if (!is_string($salesChannelIdBytes)) {
                continue;
            }

            $salesChannelIdHex = Uuid::fromBytesToHex($salesChannelIdBytes);
            $lockerEnabled = $this->config->isLockerEnabled($salesChannelIdHex);
            $courierEnabled = $this->config->isCourierEnabled($salesChannelIdHex);
            $this->setAssociation($salesChannelIdBytes, PluginConfig::LOCKER_SHIPPING_METHOD_ID, $lockerEnabled);
            $this->setAssociation($salesChannelIdBytes, PluginConfig::COURIER_SHIPPING_METHOD_ID, $courierEnabled);
            $hasLocker = $hasLocker || $lockerEnabled;
            $hasCourier = $hasCourier || $courierEnabled;
        }

        if ($salesChannelId !== null) {
            $hasLocker = $this->hasAssociation(PluginConfig::LOCKER_SHIPPING_METHOD_ID);
            $hasCourier = $this->hasAssociation(PluginConfig::COURIER_SHIPPING_METHOD_ID);
        }

        $this->setMethodActive(PluginConfig::LOCKER_SHIPPING_METHOD_ID, $hasLocker);
        $this->setMethodActive(PluginConfig::COURIER_SHIPPING_METHOD_ID, $hasCourier);
    }

    private function setAssociation(string $salesChannelId, string $shippingMethodId, bool $enabled): void
    {
        $shippingMethodIdBytes = Uuid::fromHexToBytes($shippingMethodId);
        if ($enabled) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `sales_channel_shipping_method` (`sales_channel_id`, `shipping_method_id`) VALUES (:salesChannelId, :shippingMethodId)',
                ['salesChannelId' => $salesChannelId, 'shippingMethodId' => $shippingMethodIdBytes]
            );

            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM `sales_channel_shipping_method` WHERE `sales_channel_id` = :salesChannelId AND `shipping_method_id` = :shippingMethodId',
            ['salesChannelId' => $salesChannelId, 'shippingMethodId' => $shippingMethodIdBytes]
        );
    }

    private function hasAssociation(string $shippingMethodId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM `sales_channel_shipping_method` WHERE `shipping_method_id` = :shippingMethodId LIMIT 1',
            ['shippingMethodId' => Uuid::fromHexToBytes($shippingMethodId)]
        ) !== false;
    }

    private function setMethodActive(string $shippingMethodId, bool $active): void
    {
        $this->connection->update(
            'shipping_method',
            ['active' => (int) $active],
            ['id' => Uuid::fromHexToBytes($shippingMethodId)]
        );
    }
}
