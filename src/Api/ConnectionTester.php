<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Api;

use Clueware\InpostShipping\Api\Exception\InPostApiException;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

final class ConnectionTester
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PluginConfig $config,
        private readonly InPostApiClientInterface $apiClient,
    ) {
    }

    /**
     * @return array{testedSalesChannels: int}
     */
    public function testConfiguredSalesChannels(): array
    {
        $testedSalesChannels = 0;
        foreach ($this->connection->fetchFirstColumn('SELECT `id` FROM `sales_channel`') as $salesChannelId) {
            if (!is_string($salesChannelId)) {
                continue;
            }

            $salesChannelId = Uuid::fromBytesToHex($salesChannelId);
            if (!$this->config->isApiConfigured($salesChannelId)) {
                continue;
            }

            $this->apiClient->testConnection($salesChannelId);
            ++$testedSalesChannels;
        }

        if ($testedSalesChannels === 0) {
            throw new InPostApiException(
                'Save a ShipX API token and organization ID before testing the connection.',
                'configuration_missing',
                422
            );
        }

        return ['testedSalesChannels' => $testedSalesChannels];
    }
}
