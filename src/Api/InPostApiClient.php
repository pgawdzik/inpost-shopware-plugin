<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Api;

use Clueware\InpostShipping\Api\Exception\InPostApiException;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class InPostApiClient implements InPostApiClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PluginConfig $config
    ) {
    }

    public function createShipment(array $payload, string $salesChannelId): array
    {
        $organizationId = $this->requireOrganizationId($salesChannelId);
        $response = $this->request('POST', "/v1/organizations/{$organizationId}/shipments", $salesChannelId, [
            'json' => $payload,
        ]);

        return $this->decode($response);
    }

    public function testConnection(string $salesChannelId): array
    {
        $organizationId = $this->requireOrganizationId($salesChannelId);

        return $this->decode($this->request('GET', "/v1/organizations/{$organizationId}", $salesChannelId));
    }

    public function getShipment(string $shipmentId, string $salesChannelId): array
    {
        $this->assertIdentifier($shipmentId);

        return $this->decode($this->request('GET', "/v1/shipments/{$shipmentId}", $salesChannelId));
    }

    public function cancelShipment(string $shipmentId, string $salesChannelId): void
    {
        $this->assertIdentifier($shipmentId);
        $this->request('DELETE', "/v1/shipments/{$shipmentId}", $salesChannelId)->getContent();
    }

    public function getLabel(string $shipmentId, string $format, string $salesChannelId): array
    {
        $this->assertIdentifier($shipmentId);
        $organizationId = $this->requireOrganizationId($salesChannelId);
        $format = $format === 'A4' ? 'A4' : 'A6';
        $response = $this->request(
            'POST',
            "/v1/organizations/{$organizationId}/shipments/labels",
            $salesChannelId,
            [
                'json' => [
                    'type' => $format,
                    'format' => 'pdf',
                    'shipment_ids' => [(int) $shipmentId],
                ],
            ]
        );

        try {
            $content = $response->getContent();
        } catch (ExceptionInterface $exception) {
            throw new InPostApiException('The InPost label could not be downloaded.', 'label_download_failed', 502);
        }

        if ($content === '' || \strlen($content) > 20 * 1024 * 1024) {
            throw new InPostApiException('The InPost label response is invalid.', 'invalid_label', 502);
        }

        return ['content' => $content, 'contentType' => 'application/pdf', 'extension' => 'pdf'];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, string $salesChannelId, array $options = []): ResponseInterface
    {
        $token = $this->config->apiToken($salesChannelId);
        if ($token === '') {
            throw new InPostApiException('The InPost API token is not configured.', 'configuration_missing', 422);
        }

        $options['headers'] = array_merge(
            \is_array($options['headers'] ?? null) ? $options['headers'] : [],
            [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json, application/pdf',
            ]
        );
        $options['timeout'] = 20.0;
        $options['max_duration'] = 30.0;

        try {
            $response = $this->httpClient->request(
                $method,
                $this->config->apiBaseUrl($salesChannelId) . $path,
                $options
            );
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            throw new InPostApiException('InPost is temporarily unavailable.', 'transport_error', 502);
        }

        if ($status >= 400) {
            $message = 'InPost rejected the request.';
            $code = $status === 401 || $status === 403 ? 'authentication_error' : 'api_error';

            try {
                $error = $response->toArray(false);
                $candidate = $error['message'] ?? $error['error'] ?? $error['details'] ?? null;
                if (\is_string($candidate) && $candidate !== '') {
                    $message = $this->sanitizeMessage($candidate);
                }
            } catch (ExceptionInterface) {
            }

            throw new InPostApiException($message, $code, $status >= 500 ? 502 : 422);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        try {
            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new InPostApiException('InPost returned an invalid response.', 'invalid_response', 502);
        }

        if (!\is_array($data)) {
            throw new InPostApiException('InPost returned an invalid response.', 'invalid_response', 502);
        }

        return $data;
    }

    private function requireOrganizationId(string $salesChannelId): string
    {
        $organizationId = $this->config->organizationId($salesChannelId);
        if ($organizationId === '') {
            throw new InPostApiException('The InPost organization ID is not configured.', 'configuration_missing', 422);
        }

        return $organizationId;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^\d{1,32}$/', $identifier) !== 1) {
            throw new InPostApiException('The InPost shipment identifier is invalid.', 'invalid_identifier', 422);
        }
    }

    private function sanitizeMessage(string $message): string
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._-]+/i', 'Bearer ***', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
