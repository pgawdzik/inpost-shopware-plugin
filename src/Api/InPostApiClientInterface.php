<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Api;

interface InPostApiClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function testConnection(string $salesChannelId): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createShipment(array $payload, string $salesChannelId): array;

    /**
     * @return array<string, mixed>
     */
    public function getShipment(string $shipmentId, string $salesChannelId): array;

    public function cancelShipment(string $shipmentId, string $salesChannelId): void;

    /**
     * @return array{content: string, contentType: string, extension: string}
     */
    public function getLabel(string $shipmentId, string $format, string $salesChannelId): array;
}
