<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Order;

use Clueware\InpostShipping\Configuration\PluginConfig;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class WeightAndSizeCalculator
{
    /**
     * @param EntityRepository<\Shopware\Core\Content\Product\ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly PluginConfig $config
    ) {
    }

    /**
     * @return array{weight: float, size: string}
     */
    public function calculate(OrderEntity $order, Context $context): array
    {
        $salesChannelId = $order->getSalesChannelId();
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return [
                'weight' => $this->config->defaultWeight($salesChannelId),
                'size' => $this->config->defaultSize($salesChannelId),
            ];
        }

        $ids = [];
        $quantities = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== 'product' || $lineItem->getReferencedId() === null) {
                continue;
            }
            $ids[] = $lineItem->getReferencedId();
            $quantities[$lineItem->getReferencedId()] = ($quantities[$lineItem->getReferencedId()] ?? 0) + $lineItem->getQuantity();
        }

        if ($ids === []) {
            return [
                'weight' => $this->config->defaultWeight($salesChannelId),
                'size' => $this->config->defaultSize($salesChannelId),
            ];
        }

        $products = $this->productRepository->search(new Criteria(array_values(array_unique($ids))), $context);
        $weight = 0.0;
        $volume = 0.0;
        $maxHeight = 0.0;
        foreach ($products as $product) {
            if (!$product instanceof ProductEntity) {
                continue;
            }
            $quantity = $quantities[$product->getId()] ?? 1;
            $weight += max(0.0, (float) $product->getWeight()) * $quantity;
            $height = max(0.0, (float) $product->getHeight());
            $width = max(0.0, (float) $product->getWidth());
            $length = max(0.0, (float) $product->getLength());
            $volume += $height * $width * $length * $quantity;
            $maxHeight = max($maxHeight, min($height, $width, $length));
        }

        if ($weight <= 0.0) {
            $weight = $this->config->defaultWeight($salesChannelId);
        }

        $size = $this->config->defaultSize($salesChannelId);
        if ($volume > 0.0) {
            if ($maxHeight <= 80.0 && $volume <= 80.0 * 380.0 * 640.0) {
                $size = 'small';
            } elseif ($maxHeight <= 190.0 && $volume <= 190.0 * 380.0 * 640.0) {
                $size = 'medium';
            } else {
                $size = 'large';
            }
        }

        return ['weight' => round(min(50.0, $weight), 3), 'size' => $size];
    }
}
