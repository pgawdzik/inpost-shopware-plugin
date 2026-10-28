<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Migration;

use Clueware\InpostShipping\Configuration\PluginConfig;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1750100000AddInpostShippingMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750100000;
    }

    public function update(Connection $connection): void
    {
        $deliveryTimeId = $connection->fetchOne(
            'SELECT `delivery_time_id` FROM `shipping_method` WHERE `delivery_time_id` IS NOT NULL ORDER BY `created_at` LIMIT 1'
        );

        if (!\is_string($deliveryTimeId) || $deliveryTimeId === '') {
            throw new \RuntimeException('An existing delivery time is required to create the InPost shipping methods.');
        }

        $createdAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->createShippingMethod(
            $connection,
            PluginConfig::LOCKER_SHIPPING_METHOD_ID,
            'clueware_inpost_locker',
            1,
            $deliveryTimeId,
            $createdAt,
            [
                'en-GB' => 'InPost Parcel Locker',
                'de-DE' => 'InPost Paczkomat',
                'pl-PL' => 'InPost Paczkomat',
            ]
        );

        $this->createShippingMethod(
            $connection,
            PluginConfig::COURIER_SHIPPING_METHOD_ID,
            'clueware_inpost_courier',
            1,
            $deliveryTimeId,
            $createdAt,
            [
                'en-GB' => 'InPost Courier',
                'de-DE' => 'InPost Kurier',
                'pl-PL' => 'InPost Kurier',
            ]
        );

        $this->assignToSalesChannels($connection, PluginConfig::LOCKER_SHIPPING_METHOD_ID);
        $this->assignToSalesChannels($connection, PluginConfig::COURIER_SHIPPING_METHOD_ID);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @param array<string, string> $translations
     */
    private function createShippingMethod(
        Connection $connection,
        string $shippingMethodId,
        string $technicalName,
        int $position,
        string $deliveryTimeId,
        string $createdAt,
        array $translations,
    ): void {
        $id = Uuid::fromHexToBytes($shippingMethodId);

        if ($connection->fetchOne('SELECT 1 FROM `shipping_method` WHERE `id` = :id', ['id' => $id]) === false) {
            $connection->insert('shipping_method', [
                'id' => $id,
                'technical_name' => $technicalName,
                'active' => 1,
                'position' => $position,
                'delivery_time_id' => $deliveryTimeId,
                'tax_type' => 'auto',
                'created_at' => $createdAt,
            ]);
        }

        foreach ($translations as $localeCode => $name) {
            $languageId = $connection->fetchOne(
                'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id` WHERE `locale`.`code` = :localeCode LIMIT 1',
                ['localeCode' => $localeCode]
            );

            if (!\is_string($languageId) || $languageId === '') {
                continue;
            }

            $connection->executeStatement(
                'INSERT IGNORE INTO `shipping_method_translation` (`shipping_method_id`, `language_id`, `name`, `created_at`) VALUES (:shippingMethodId, :languageId, :name, :createdAt)',
                [
                    'shippingMethodId' => $id,
                    'languageId' => $languageId,
                    'name' => $name,
                    'createdAt' => $createdAt,
                ]
            );
        }

        if ($connection->fetchOne('SELECT 1 FROM `shipping_method_price` WHERE `shipping_method_id` = :shippingMethodId LIMIT 1', ['shippingMethodId' => $id]) === false) {
            $connection->insert('shipping_method_price', [
                'id' => Uuid::randomBytes(),
                'shipping_method_id' => $id,
                'calculation' => 1,
                'currency_price' => json_encode([[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 0.0,
                    'net' => 0.0,
                    'linked' => false,
                ]], \JSON_THROW_ON_ERROR),
                'quantity_start' => 0,
                'created_at' => $createdAt,
            ]);
        }
    }

    private function assignToSalesChannels(Connection $connection, string $shippingMethodId): void
    {
        $connection->executeStatement(
            'INSERT IGNORE INTO `sales_channel_shipping_method` (`sales_channel_id`, `shipping_method_id`) SELECT `id`, :shippingMethodId FROM `sales_channel`',
            ['shippingMethodId' => Uuid::fromHexToBytes($shippingMethodId)]
        );
    }
}
