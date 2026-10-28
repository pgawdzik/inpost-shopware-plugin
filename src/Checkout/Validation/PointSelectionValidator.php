<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Checkout\Validation;

use Clueware\InpostShipping\Checkout\PointSelection\PointSelection;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PointSelectionValidator implements CartValidatorInterface
{
    public function __construct(
        private readonly PluginConfig $config,
        private readonly RequestStack $requestStack
    ) {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $salesChannelId = $context->getSalesChannelId();
        if (!$this->config->isActive($salesChannelId)) {
            return;
        }

        if ($context->getShippingMethod()->getId() !== $this->config->lockerShippingMethodId($salesChannelId)) {
            return;
        }

        if (!$this->isOrderSubmission()) {
            return;
        }

        if (!$cart->getExtension(PointSelection::EXTENSION_KEY) instanceof PointSelection) {
            $errors->add(new MissingPointError());
        }
    }

    private function isOrderSubmission(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return true;
        }

        if (!$request->isMethod(Request::METHOD_POST)) {
            return false;
        }

        return \in_array($request->attributes->get('_route'), [
            'frontend.checkout.finish.order',
            'store-api.checkout.cart.order',
        ], true);
    }
}
