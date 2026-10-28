<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Order;

final class ShipmentStatusMapper
{
    public function map(?string $externalStatus): string
    {
        $status = strtolower(trim((string) $externalStatus));

        return match ($status) {
            'cancelled' => 'cancelled',
            'delivered' => 'delivered',
            'returned_to_sender', 'avizo', 'claimed' => 'returned',
            'created', 'offers_prepared', 'offer_selected', 'confirmed',
            'dispatched_by_sender', 'collected_from_sender', 'taken_by_courier',
            'adopted_at_source_branch', 'sent_from_source_branch',
            'ready_to_pickup', 'out_for_delivery', 'stored' => 'created',
            default => $status === '' ? 'created' : 'created',
        };
    }
}
