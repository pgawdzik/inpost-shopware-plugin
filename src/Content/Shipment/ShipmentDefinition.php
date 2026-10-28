<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Content\Shipment;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class ShipmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'clueware_inpost_shipment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShipmentCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShipmentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required(), new ApiAware()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware()),
            (new StringField('delivery_type', 'deliveryType', 32))->addFlags(new Required(), new ApiAware()),
            (new StringField('point_id', 'pointId', 64))->addFlags(new ApiAware()),
            (new JsonField('point_snapshot', 'pointSnapshot'))->addFlags(new ApiAware()),
            (new StringField('external_shipment_id', 'externalShipmentId', 64))->addFlags(new ApiAware()),
            (new StringField('tracking_number', 'trackingNumber', 64))->addFlags(new ApiAware()),
            (new StringField('service', 'service', 64))->addFlags(new Required(), new ApiAware()),
            (new StringField('status', 'status', 32))->addFlags(new Required(), new ApiAware()),
            (new StringField('external_status', 'externalStatus', 64))->addFlags(new ApiAware()),
            (new StringField('label_format', 'labelFormat', 16))->addFlags(new Required(), new ApiAware()),
            (new FloatField('weight', 'weight'))->addFlags(new Required(), new ApiAware()),
            (new StringField('size', 'size', 16))->addFlags(new ApiAware()),
            (new FloatField('cod_amount', 'codAmount'))->addFlags(new ApiAware()),
            (new StringField('last_error_code', 'lastErrorCode', 128))->addFlags(new ApiAware()),
            (new StringField('last_error_message', 'lastErrorMessage', 1000))->addFlags(new ApiAware()),
            (new JsonField('operation_log', 'operationLog'))->addFlags(new ApiAware()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class),
        ]);
    }
}
