<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Content\Shipment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ShipmentEntity>
 */
final class ShipmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShipmentEntity::class;
    }
}
