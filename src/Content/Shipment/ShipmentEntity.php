<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Content\Shipment;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class ShipmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;
    protected string $orderVersionId;
    protected string $salesChannelId;
    protected ?string $currencyId = null;
    protected string $deliveryType;
    protected ?string $pointId = null;
    protected ?array $pointSnapshot = null;
    protected ?string $externalShipmentId = null;
    protected ?string $trackingNumber = null;
    protected string $service;
    protected string $status;
    protected ?string $externalStatus = null;
    protected string $labelFormat;
    protected float $weight;
    protected ?string $size = null;
    protected ?float $codAmount = null;
    protected ?string $lastErrorCode = null;
    protected ?string $lastErrorMessage = null;
    protected ?array $operationLog = null;
    protected ?OrderEntity $order = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?CurrencyEntity $currency = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getDeliveryType(): string
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(string $deliveryType): void
    {
        $this->deliveryType = $deliveryType;
    }

    public function getPointId(): ?string
    {
        return $this->pointId;
    }

    public function setPointId(?string $pointId): void
    {
        $this->pointId = $pointId;
    }

    public function getPointSnapshot(): ?array
    {
        return $this->pointSnapshot;
    }

    public function setPointSnapshot(?array $pointSnapshot): void
    {
        $this->pointSnapshot = $pointSnapshot;
    }

    public function getExternalShipmentId(): ?string
    {
        return $this->externalShipmentId;
    }

    public function setExternalShipmentId(?string $externalShipmentId): void
    {
        $this->externalShipmentId = $externalShipmentId;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): void
    {
        $this->service = $service;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getExternalStatus(): ?string
    {
        return $this->externalStatus;
    }

    public function setExternalStatus(?string $externalStatus): void
    {
        $this->externalStatus = $externalStatus;
    }

    public function getLabelFormat(): string
    {
        return $this->labelFormat;
    }

    public function setLabelFormat(string $labelFormat): void
    {
        $this->labelFormat = $labelFormat;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): void
    {
        $this->weight = $weight;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): void
    {
        $this->size = $size;
    }

    public function getCodAmount(): ?float
    {
        return $this->codAmount;
    }

    public function setCodAmount(?float $codAmount): void
    {
        $this->codAmount = $codAmount;
    }

    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    public function setLastErrorCode(?string $lastErrorCode): void
    {
        $this->lastErrorCode = $lastErrorCode;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): void
    {
        $this->lastErrorMessage = $lastErrorMessage;
    }

    public function getOperationLog(): ?array
    {
        return $this->operationLog;
    }

    public function setOperationLog(?array $operationLog): void
    {
        $this->operationLog = $operationLog;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }
}
