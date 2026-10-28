<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Administration\Controller;

use Clueware\InpostShipping\Api\Exception\InPostApiException;
use Clueware\InpostShipping\Api\ConnectionTester;
use Clueware\InpostShipping\Order\ShipmentManager;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/clueware/inpost', defaults: ['_routeScope' => ['api']])]
final class ShipmentController extends AbstractController
{
    public function __construct(
        private readonly ShipmentManager $shipmentManager,
        private readonly ConnectionTester $connectionTester,
    ) {
    }

    #[Route(
        path: '/connection-test',
        name: 'api.action.clueware.inpost.connection_test',
        methods: ['GET'],
        defaults: ['_acl' => ['system.plugin_maintain']]
    )]
    public function testConnection(): JsonResponse
    {
        return $this->run(fn (): array => $this->connectionTester->testConfiguredSalesChannels());
    }

    #[Route(
        path: '/order/{orderId}',
        name: 'api.action.clueware.inpost.order.state',
        methods: ['GET'],
        defaults: ['_acl' => ['clueware_inpost_shipping.viewer']]
    )]
    public function state(string $orderId, Context $context): JsonResponse
    {
        return new JsonResponse($this->shipmentManager->state($orderId, $context));
    }

    #[Route(
        path: '/order/{orderId}/shipment',
        name: 'api.action.clueware.inpost.order.create',
        methods: ['POST'],
        defaults: ['_acl' => ['clueware_inpost_shipping.creator']]
    )]
    public function create(string $orderId, Request $request, Context $context): JsonResponse
    {
        return $this->run(function () use ($orderId, $request, $context): array {
            $payload = json_decode($request->getContent(), true, 32, \JSON_THROW_ON_ERROR);
            if (!\is_array($payload)) {
                throw new \InvalidArgumentException('Request payload must be an object.');
            }

            return $this->shipmentManager->create($orderId, $payload, $context);
        });
    }

    #[Route(
        path: '/order/{orderId}/shipment/refresh',
        name: 'api.action.clueware.inpost.order.refresh',
        methods: ['POST'],
        defaults: ['_acl' => ['clueware_inpost_shipping.editor']]
    )]
    public function refresh(string $orderId, Context $context): JsonResponse
    {
        return $this->run(fn (): array => $this->shipmentManager->refresh($orderId, $context));
    }

    #[Route(
        path: '/order/{orderId}/shipment',
        name: 'api.action.clueware.inpost.order.cancel',
        methods: ['DELETE'],
        defaults: ['_acl' => ['clueware_inpost_shipping.canceller']]
    )]
    public function cancel(string $orderId, Context $context): JsonResponse
    {
        return $this->run(fn (): array => $this->shipmentManager->cancel($orderId, $context));
    }

    #[Route(
        path: '/order/{orderId}/shipment/label',
        name: 'api.action.clueware.inpost.order.label',
        methods: ['GET'],
        defaults: ['_acl' => ['clueware_inpost_shipping.label']]
    )]
    public function label(string $orderId, Context $context): Response
    {
        try {
            $label = $this->shipmentManager->label($orderId, $context);
            $fileName = 'inpost-' . $label['trackingNumber'] . '.' . $label['extension'];
            $response = new Response($label['content']);
            $response->headers->set('Content-Type', $label['contentType']);
            $response->headers->set(
                'Content-Disposition',
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName)
            );
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * @param callable(): array<string, mixed> $operation
     */
    private function run(callable $operation): JsonResponse
    {
        try {
            return new JsonResponse($operation());
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], 400);
        } catch (InPostApiException $exception) {
            return new JsonResponse([
                'success' => false,
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ], $exception->getHttpStatus());
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], 409);
        }
    }
}
