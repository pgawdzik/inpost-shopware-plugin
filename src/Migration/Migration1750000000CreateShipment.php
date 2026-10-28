<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1750000000CreateShipment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `clueware_inpost_shipment` (
    `id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NOT NULL,
    `order_version_id` BINARY(16) NOT NULL,
    `sales_channel_id` BINARY(16) NOT NULL,
    `currency_id` BINARY(16) NULL,
    `delivery_type` VARCHAR(32) NOT NULL,
    `point_id` VARCHAR(64) NULL,
    `point_snapshot` JSON NULL,
    `external_shipment_id` VARCHAR(64) NULL,
    `tracking_number` VARCHAR(64) NULL,
    `service` VARCHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `external_status` VARCHAR(64) NULL,
    `label_format` VARCHAR(16) NOT NULL,
    `weight` DECIMAL(10,3) NOT NULL,
    `size` VARCHAR(16) NULL,
    `cod_amount` DECIMAL(20,3) NULL,
    `last_error_code` VARCHAR(128) NULL,
    `last_error_message` VARCHAR(1000) NULL,
    `operation_log` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.clueware_inpost_shipment.order` (`order_id`, `order_version_id`),
    KEY `idx.clueware_inpost_shipment.tracking` (`tracking_number`),
    CONSTRAINT `fk.clueware_inpost_shipment.order`
        FOREIGN KEY (`order_id`, `order_version_id`)
        REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.clueware_inpost_shipment.sales_channel`
        FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.clueware_inpost_shipment.currency`
        FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `json.clueware_inpost_shipment.point_snapshot` CHECK (JSON_VALID(`point_snapshot`)),
    CONSTRAINT `json.clueware_inpost_shipment.operation_log` CHECK (JSON_VALID(`operation_log`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
