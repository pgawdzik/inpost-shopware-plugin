<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Configuration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PhoneFieldVisibilitySynchronizer
{
    private const SHOPWARE_PHONE_FIELD_SETTING = 'core.loginRegistration.showPhoneNumberField';

    public function __construct(
        private readonly Connection $connection,
        private readonly PluginConfig $config,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function synchronize(?string $salesChannelId = null): void
    {
        $salesChannelIds = $salesChannelId === null
            ? $this->allSalesChannelIds()
            : [$salesChannelId];

        foreach ($salesChannelIds as $currentSalesChannelId) {
            if (!$this->config->isActive($currentSalesChannelId)
                || !$this->config->showNativePhoneField($currentSalesChannelId)
                || (bool) $this->systemConfigService->get(self::SHOPWARE_PHONE_FIELD_SETTING, $currentSalesChannelId)) {
                continue;
            }

            // This is intentionally one-way. Never hide a core field which may
            // have been enabled by the merchant or another extension.
            $this->systemConfigService->set(self::SHOPWARE_PHONE_FIELD_SETTING, true, $currentSalesChannelId);
        }
    }

    /** @return list<string> */
    private function allSalesChannelIds(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $id): ?string => \is_string($id) ? Uuid::fromBytesToHex($id) : null,
            $this->connection->fetchFirstColumn('SELECT `id` FROM `sales_channel`')
        )));
    }
}
