<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Storefront\Controller;

use Clueware\InpostShipping\Checkout\PointSelection\PointSelection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class PointSelectionController extends StorefrontController
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    #[Route(
        path: '/inpost/point-selection',
        name: 'frontend.clueware.inpost.point.save',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function save(Request $request, SalesChannelContext $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 32, \JSON_THROW_ON_ERROR);
            if (!\is_array($payload)) {
                throw new \InvalidArgumentException('Payload must be an object.');
            }

            $selection = PointSelection::fromPayload($payload);
        } catch (\JsonException|\InvalidArgumentException) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid pickup point.'], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $cart->addExtension(PointSelection::EXTENSION_KEY, $selection);
        $this->cartService->recalculate($cart, $context);

        return new JsonResponse(['success' => true, 'selection' => $selection->toArray()]);
    }

    #[Route(
        path: '/inpost/point-selection',
        name: 'frontend.clueware.inpost.point.clear',
        defaults: ['XmlHttpRequest' => true],
        methods: ['DELETE']
    )]
    public function clear(SalesChannelContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $cart->removeExtension(PointSelection::EXTENSION_KEY);
        $this->cartService->recalculate($cart, $context);

        return new JsonResponse(['success' => true]);
    }
}
