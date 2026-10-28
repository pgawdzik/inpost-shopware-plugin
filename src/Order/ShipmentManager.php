<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Order;

use Clueware\InpostShipping\Api\Exception\InPostApiException;
use Clueware\InpostShipping\Api\InPostApiClientInterface;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Clueware\InpostShipping\Content\Shipment\ShipmentEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\LockFactory;

final class ShipmentManager
{
    private const ACTIVE_STATUSES = ['creating', 'created'];

    /**
     * @param EntityRepository<\Shopware\Core\Checkout\Order\OrderCollection> $orderRepository
     * @param EntityRepository<\Clueware\InpostShipping\Content\Shipment\ShipmentCollection> $shipmentRepository
     * @param EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $shipmentRepository,
        private readonly EntityRepository $orderDeliveryRepository,
        private readonly PluginConfig $config,
        private readonly InPostApiClientInterface $apiClient,
        private readonly WeightAndSizeCalculator $calculator,
        private readonly ShipmentStatusMapper $statusMapper,
        private readonly LockFactory $lockFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function state(string $orderId, Context $context): array
    {
        $order = $this->loadOrder($orderId, $context);
        $shipment = $this->findLatestShipment($orderId, $context);
        $defaults = $this->calculator->calculate($order, $context);
        $point = $this->pointSnapshot($order);

        return [
            'orderId' => $order->getId(),
            'deliveryType' => $this->deliveryType($order),
            'point' => $point,
            'apiConfigured' => $this->config->isApiConfigured($order->getSalesChannelId()),
            'codEnabled' => $this->config->codEnabled($order->getSalesChannelId()),
            'defaults' => [
                'weight' => $defaults['weight'],
                'size' => $defaults['size'],
                'length' => 400,
                'width' => 300,
                'height' => 200,
                'codAmount' => 0.0,
                'labelFormat' => $this->config->labelFormat($order->getSalesChannelId()),
            ],
            'shipment' => $shipment === null ? null : $this->serializeShipment($shipment),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create(string $orderId, array $input, Context $context): array
    {
        if (!Uuid::isValid($orderId)) {
            throw new \InvalidArgumentException('Invalid order ID.');
        }

        $lock = $this->lockFactory->createLock('clueware-inpost-shipment-' . $orderId, 45.0);
        if (!$lock->acquire()) {
            throw new \RuntimeException('A shipment operation is already in progress.');
        }

        try {
            $order = $this->loadOrder($orderId, $context);
            $existing = $this->findActiveShipment($orderId, $context);
            if ($existing !== null) {
                throw new \RuntimeException('An active shipment already exists for this order.');
            }

            $salesChannelId = $order->getSalesChannelId();
            if (!$this->config->isApiConfigured($salesChannelId)) {
                throw new InPostApiException('Complete the InPost API configuration first.', 'configuration_missing', 422);
            }

            $deliveryType = $this->deliveryType($order);
            if (!\in_array($deliveryType, ['locker', 'courier'], true)) {
                throw new \InvalidArgumentException('This order does not use a configured InPost shipping method.');
            }

            $point = $this->pointSnapshot($order);
            if ($deliveryType === 'locker' && $point === null) {
                throw new \InvalidArgumentException('The order does not contain an InPost pickup point.');
            }

            if (!is_numeric($input['weight'] ?? null) || (float) $input['weight'] <= 0.0) {
                throw new \InvalidArgumentException('Shipment weight is required.');
            }
            $weight = max(0.001, min(50.0, (float) $input['weight']));
            $size = \in_array($input['size'] ?? null, ['small', 'medium', 'large'], true)
                ? (string) $input['size']
                : $this->config->defaultSize($salesChannelId);
            $dimensions = $deliveryType === 'courier' ? $this->courierDimensions($input) : null;
            $codAmount = $this->config->codEnabled($salesChannelId)
                ? max(0.0, min((float) $order->getAmountTotal(), (float) ($input['codAmount'] ?? 0)))
                : 0.0;
            $labelFormat = ($input['labelFormat'] ?? null) === 'A4' ? 'A4' : 'A6';
            $service = $this->config->serviceFor($deliveryType, $salesChannelId);
            $shipmentId = Uuid::randomHex();
            $log = [$this->logEntry('create_started', 'creating')];

            $this->shipmentRepository->create([[
                'id' => $shipmentId,
                'orderId' => $order->getId(),
                'orderVersionId' => Defaults::LIVE_VERSION,
                'salesChannelId' => $salesChannelId,
                'currencyId' => $order->getCurrencyId(),
                'deliveryType' => $deliveryType,
                'pointId' => $point['name'] ?? null,
                'pointSnapshot' => $point,
                'service' => $service,
                'status' => 'creating',
                'labelFormat' => $labelFormat,
                'weight' => $weight,
                'size' => $size,
                'codAmount' => $codAmount > 0 ? $codAmount : null,
                'operationLog' => $log,
            ]], $context);

            try {
                $response = $this->apiClient->createShipment(
                    $this->buildPayload($order, $deliveryType, $point, $service, $weight, $size, $dimensions, $codAmount),
                    $salesChannelId
                );
                $externalId = $this->identifier($response['id'] ?? null);
                if ($externalId === null) {
                    throw new InPostApiException('InPost did not return a shipment identifier.', 'invalid_response', 502);
                }
                $externalStatus = $this->shortText($response['status'] ?? 'created', 64);
                $tracking = $this->shortText($response['tracking_number'] ?? null, 64) ?: null;
                $log[] = $this->logEntry('create_succeeded', $externalStatus);

                $this->shipmentRepository->update([[
                    'id' => $shipmentId,
                    'externalShipmentId' => $externalId,
                    'trackingNumber' => $tracking,
                    'externalStatus' => $externalStatus,
                    'status' => $this->statusMapper->map($externalStatus),
                    'operationLog' => $log,
                    'lastErrorCode' => null,
                    'lastErrorMessage' => null,
                ]], $context);
                $this->writeTrackingCode($order, $tracking, $context);
            } catch (InPostApiException $exception) {
                $log[] = $this->logEntry('create_failed', 'error', $exception->getMessage());
                $this->shipmentRepository->update([[
                    'id' => $shipmentId,
                    'status' => 'error',
                    'lastErrorCode' => $exception->getErrorCode(),
                    'lastErrorMessage' => $this->shortText($exception->getMessage(), 1000),
                    'operationLog' => $log,
                ]], $context);
                throw $exception;
            }

            return $this->state($orderId, $context);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(string $orderId, Context $context): array
    {
        $order = $this->loadOrder($orderId, $context);
        $shipment = $this->requireCurrentShipment($orderId, $context);
        $externalId = $shipment->getExternalShipmentId();
        if ($externalId === null) {
            throw new \RuntimeException('This shipment has no InPost identifier.');
        }

        $response = $this->apiClient->getShipment($externalId, $order->getSalesChannelId());
        $externalStatus = $this->shortText($response['status'] ?? $shipment->getExternalStatus(), 64);
        $tracking = $this->shortText($response['tracking_number'] ?? $shipment->getTrackingNumber(), 64) ?: null;
        $log = $shipment->getOperationLog() ?? [];
        $log[] = $this->logEntry('status_refreshed', $externalStatus);
        $this->shipmentRepository->update([[
            'id' => $shipment->getId(),
            'externalStatus' => $externalStatus,
            'status' => $this->statusMapper->map($externalStatus),
            'trackingNumber' => $tracking,
            'operationLog' => array_slice($log, -100),
            'lastErrorCode' => null,
            'lastErrorMessage' => null,
        ]], $context);
        $this->writeTrackingCode($order, $tracking, $context);

        return $this->state($orderId, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $orderId, Context $context): array
    {
        $order = $this->loadOrder($orderId, $context);
        $shipment = $this->requireCurrentShipment($orderId, $context);
        if (
            $shipment->getExternalShipmentId() === null
            || !\in_array($shipment->getExternalStatus(), ['created', 'offers_prepared', 'offer_selected'], true)
        ) {
            throw new \RuntimeException('This shipment cannot be cancelled in its current state.');
        }

        $this->apiClient->cancelShipment($shipment->getExternalShipmentId(), $order->getSalesChannelId());
        $log = $shipment->getOperationLog() ?? [];
        $log[] = $this->logEntry('shipment_cancelled', 'cancelled');
        $this->shipmentRepository->update([[
            'id' => $shipment->getId(),
            'status' => 'cancelled',
            'externalStatus' => 'cancelled',
            'operationLog' => array_slice($log, -100),
        ]], $context);

        return $this->state($orderId, $context);
    }

    /**
     * @return array{content: string, contentType: string, extension: string, trackingNumber: string}
     */
    public function label(string $orderId, Context $context): array
    {
        $order = $this->loadOrder($orderId, $context);
        $shipment = $this->requireCurrentShipment($orderId, $context);
        if ($shipment->getExternalShipmentId() === null) {
            throw new \RuntimeException('This shipment has no InPost identifier.');
        }

        $label = $this->apiClient->getLabel(
            $shipment->getExternalShipmentId(),
            $shipment->getLabelFormat(),
            $order->getSalesChannelId()
        );
        $label['trackingNumber'] = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $shipment->getTrackingNumber()) ?: $shipment->getExternalShipmentId();

        return $label;
    }

    private function loadOrder(string $orderId, Context $context): OrderEntity
    {
        if (!Uuid::isValid($orderId)) {
            throw new \InvalidArgumentException('Invalid order ID.');
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            throw new \InvalidArgumentException('Order not found.');
        }

        return $order;
    }

    private function findLatestShipment(string $orderId, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('createdAt', 'DESC'))
            ->setLimit(1);
        $shipment = $this->shipmentRepository->search($criteria, $context)->first();

        return $shipment instanceof ShipmentEntity ? $shipment : null;
    }

    private function findActiveShipment(string $orderId, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter('status', self::ACTIVE_STATUSES))
            ->setLimit(1);
        $shipment = $this->shipmentRepository->search($criteria, $context)->first();

        return $shipment instanceof ShipmentEntity ? $shipment : null;
    }

    private function requireCurrentShipment(string $orderId, Context $context): ShipmentEntity
    {
        $shipment = $this->findLatestShipment($orderId, $context);
        if ($shipment === null) {
            throw new \RuntimeException('No InPost shipment exists for this order.');
        }

        return $shipment;
    }

    /**
     * @param array<string, mixed>|null $point
     * @param array{length: int, width: int, height: int}|null $dimensions
     *
     * @return array<string, mixed>
     */
    private function buildPayload(
        OrderEntity $order,
        string $deliveryType,
        ?array $point,
        string $service,
        float $weight,
        string $size,
        ?array $dimensions,
        float $codAmount
    ): array {
        $address = $this->shippingAddress($order);
        $customer = $order->getOrderCustomer();
        $email = $this->shortText($customer?->getEmail(), 254);
        $phone = preg_replace('/[^0-9+]/', '', (string) $address?->getPhoneNumber()) ?? '';
        if ($email === '' || $phone === '') {
            throw new \InvalidArgumentException('The order requires a recipient e-mail and phone number.');
        }

        $receiver = [
            'company_name' => $this->shortText($address?->getCompany(), 100),
            'first_name' => $this->shortText($address?->getFirstName() ?: $customer?->getFirstName(), 100),
            'last_name' => $this->shortText($address?->getLastName() ?: $customer?->getLastName(), 100),
            'email' => $email,
            'phone' => $phone,
        ];

        if ($deliveryType === 'courier') {
            if ($address === null) {
                throw new \InvalidArgumentException('The order does not contain a shipping address.');
            }
            [$street, $building] = $this->splitStreet($address->getStreet());
            $receiver['address'] = [
                'street' => $street,
                'building_number' => $building,
                'city' => $this->shortText($address->getCity(), 100),
                'post_code' => $this->shortText($address->getZipcode(), 16),
                'country_code' => $address->getCountry()?->getIso() ?: 'PL',
            ];
        }

        $payload = [
            'receiver' => array_filter($receiver, static fn (mixed $value): bool => $value !== '' && $value !== null),
            'parcels' => $deliveryType === 'locker'
                ? ['template' => $size]
                : [
                    'dimensions' => [...($dimensions ?? []), 'unit' => 'mm'],
                    'weight' => ['amount' => $weight, 'unit' => 'kg'],
                ],
            'service' => $service,
            'reference' => $this->shortText('SW-' . $order->getOrderNumber(), 64),
        ];

        if ($deliveryType === 'locker' && $point !== null) {
            $payload['custom_attributes'] = [
                'sending_method' => 'dispatch_order',
                'target_point' => $point['name'],
            ];
        }
        if ($codAmount > 0.0) {
            $payload['cod'] = [
                'amount' => round($codAmount, 2),
                'currency' => $order->getCurrency()?->getIsoCode() ?: 'PLN',
            ];
            if ($deliveryType === 'courier') {
                $payload['insurance'] = $payload['cod'];
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{length: int, width: int, height: int}
     */
    private function courierDimensions(array $input): array
    {
        $limits = ['length' => 3500, 'width' => 2400, 'height' => 2400];
        $defaults = ['length' => 400, 'width' => 300, 'height' => 200];
        $dimensions = [];

        foreach ($limits as $field => $maximum) {
            $value = $input[$field] ?? $defaults[$field];
            if (!is_numeric($value) || (float) $value < 1 || (float) $value > $maximum) {
                throw new \InvalidArgumentException(sprintf(
                    'Courier %s must be between 1 and %d mm.',
                    $field,
                    $maximum
                ));
            }
            $dimensions[$field] = (int) round((float) $value);
        }

        return $dimensions;
    }

    private function shippingAddress(OrderEntity $order): ?OrderAddressEntity
    {
        $delivery = $order->getDeliveries()?->first();

        return $delivery?->getShippingOrderAddress() ?? $order->getBillingAddress();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pointSnapshot(OrderEntity $order): ?array
    {
        $customFields = $order->getCustomFields() ?? [];
        $point = $customFields['cluewareInpostPoint'] ?? null;

        return \is_array($point) ? $point : null;
    }

    private function deliveryType(OrderEntity $order): string
    {
        $customFields = $order->getCustomFields() ?? [];
        $type = $customFields['cluewareInpostDeliveryType'] ?? null;
        if (\in_array($type, ['locker', 'courier'], true)) {
            return $type;
        }

        $shippingMethodId = $order->getDeliveries()?->first()?->getShippingMethodId();
        if ($shippingMethodId === $this->config->lockerShippingMethodId($order->getSalesChannelId())) {
            return 'locker';
        }
        if ($shippingMethodId === $this->config->courierShippingMethodId($order->getSalesChannelId())) {
            return 'courier';
        }

        return 'other';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment(ShipmentEntity $shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'deliveryType' => $shipment->getDeliveryType(),
            'point' => $shipment->getPointSnapshot(),
            'externalShipmentId' => $shipment->getExternalShipmentId(),
            'trackingNumber' => $shipment->getTrackingNumber(),
            'service' => $shipment->getService(),
            'status' => $shipment->getStatus(),
            'externalStatus' => $shipment->getExternalStatus(),
            'labelFormat' => $shipment->getLabelFormat(),
            'weight' => $shipment->getWeight(),
            'size' => $shipment->getSize(),
            'codAmount' => $shipment->getCodAmount(),
            'lastErrorCode' => $shipment->getLastErrorCode(),
            'lastErrorMessage' => $shipment->getLastErrorMessage(),
            'operationLog' => $shipment->getOperationLog() ?? [],
        ];
    }

    private function writeTrackingCode(OrderEntity $order, ?string $tracking, Context $context): void
    {
        if ($tracking === null || $tracking === '') {
            return;
        }

        $delivery = $order->getDeliveries()?->first();
        if ($delivery === null) {
            return;
        }

        $codes = $delivery->getTrackingCodes();
        if (!\in_array($tracking, $codes, true)) {
            $codes[] = $tracking;
            $this->orderDeliveryRepository->update([[
                'id' => $delivery->getId(),
                'versionId' => Defaults::LIVE_VERSION,
                'trackingCodes' => $codes,
            ]], $context);
        }
    }

    /**
     * @return array{action: string, status: string, message: string|null, at: string}
     */
    private function logEntry(string $action, string $status, ?string $message = null): array
    {
        return [
            'action' => $action,
            'status' => $status,
            'message' => $message === null ? null : $this->shortText($message, 500),
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
    }

    private function identifier(mixed $value): ?string
    {
        if (!\is_int($value) && !\is_string($value)) {
            return null;
        }
        $value = (string) $value;

        return preg_match('/^\d{1,32}$/', $value) === 1 ? $value : null;
    }

    private function shortText(mixed $value, int $limit): string
    {
        if (!\is_scalar($value)) {
            return '';
        }

        return mb_substr(trim(strip_tags((string) $value)), 0, $limit);
    }

    /**
     * @return array{string, string}
     */
    private function splitStreet(string $value): array
    {
        $value = $this->shortText($value, 160);
        if (preg_match('/^(.+?)\s+([0-9][A-Za-z0-9\/-]*)$/u', $value, $matches) === 1) {
            return [$this->shortText($matches[1], 100), $this->shortText($matches[2], 32)];
        }

        return [$value, '1'];
    }
}
