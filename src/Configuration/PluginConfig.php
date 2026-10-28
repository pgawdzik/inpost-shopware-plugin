<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Configuration;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PluginConfig
{
    private const PREFIX = 'CluewareInpostShipping.config.';

    /** @var list<string> */
    private const DEFAULT_GEOWIDGET_COUNTRIES = ['PL', 'BE', 'GB', 'ES', 'FR', 'IT', 'LU', 'NL', 'PT', 'AT', 'HU'];

    /**
     * IDs of the shipping methods created by the plugin migration.
     *
     * Keeping the IDs stable means the storefront and order handling always use
     * the two InPost methods, without requiring a manual assignment in config.
     */
    public const LOCKER_SHIPPING_METHOD_ID = 'e8a0f0c9bd4b3b8d73053e1c9771d32d';
    public const COURIER_SHIPPING_METHOD_ID = '4b249c3e7f35de4a8172b7ab9d09b411';

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function isActive(?string $salesChannelId): bool
    {
        return (bool) $this->get('active', $salesChannelId, false);
    }

    public function environment(?string $salesChannelId): string
    {
        return $this->get('environment', $salesChannelId, 'sandbox') === 'production' ? 'production' : 'sandbox';
    }

    public function apiBaseUrl(?string $salesChannelId): string
    {
        return $this->environment($salesChannelId) === 'production'
            ? 'https://api-shipx-pl.easypack24.net'
            : 'https://sandbox-api-shipx-pl.easypack24.net';
    }

    public function geowidgetAssetsUrl(?string $salesChannelId): string
    {
        return $this->environment($salesChannelId) === 'production'
            ? 'https://geowidget.inpost-group.com'
            : 'https://sandbox-global-geowidget-sdk.easypack24.net';
    }

    public function geowidgetPointsApiUrl(?string $salesChannelId): string
    {
        return $this->environment($salesChannelId) === 'production'
            ? 'https://api.inpost-group.com/geowidget/v1/points'
            : 'https://sandbox-api.inpost-group.com/geowidget/v1/points';
    }

    public function apiToken(?string $salesChannelId): string
    {
        return trim((string) $this->get('apiToken', $salesChannelId, ''));
    }

    public function organizationId(?string $salesChannelId): string
    {
        $value = trim((string) $this->get('organizationId', $salesChannelId, ''));

        return preg_match('/^\d{1,20}$/', $value) === 1 ? $value : '';
    }

    public function geowidgetToken(?string $salesChannelId): string
    {
        return trim((string) $this->get('geowidgetToken', $salesChannelId, ''));
    }

    public function showNativePhoneField(?string $salesChannelId): bool
    {
        return (bool) $this->get('showNativePhoneField', $salesChannelId, true);
    }

    /**
     * @return non-empty-list<string>
     */
    public function geowidgetCountries(?string $salesChannelId): array
    {
        $configured = $this->get('geowidgetCountries', $salesChannelId, self::DEFAULT_GEOWIDGET_COUNTRIES);
        if (\is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (!\is_array($configured)) {
            return self::DEFAULT_GEOWIDGET_COUNTRIES;
        }

        $countries = [];
        foreach ($configured as $country) {
            if (!\is_string($country)) {
                continue;
            }

            $country = strtoupper(trim($country));
            if (\in_array($country, self::DEFAULT_GEOWIDGET_COUNTRIES, true) && !\in_array($country, $countries, true)) {
                $countries[] = $country;
            }
        }

        return $countries !== [] ? $countries : self::DEFAULT_GEOWIDGET_COUNTRIES;
    }

    public function lockerShippingMethodId(?string $salesChannelId): string
    {
        return self::LOCKER_SHIPPING_METHOD_ID;
    }

    public function courierShippingMethodId(?string $salesChannelId): string
    {
        return self::COURIER_SHIPPING_METHOD_ID;
    }

    public function serviceFor(string $deliveryType, ?string $salesChannelId): string
    {
        if ($deliveryType === 'locker') {
            $service = (string) $this->get('lockerService', $salesChannelId, 'inpost_locker_standard');

            return \in_array($service, ['inpost_locker_standard', 'inpost_locker_pass_thru'], true)
                ? $service
                : 'inpost_locker_standard';
        }

        $service = (string) $this->get('courierService', $salesChannelId, 'inpost_courier_standard');

        return \in_array($service, ['inpost_courier_standard', 'inpost_courier_c2c'], true)
            ? $service
            : 'inpost_courier_standard';
    }

    public function defaultSize(?string $salesChannelId): string
    {
        $size = (string) $this->get('defaultSize', $salesChannelId, 'small');

        return \in_array($size, ['small', 'medium', 'large'], true) ? $size : 'small';
    }

    public function defaultWeight(?string $salesChannelId): float
    {
        return max(0.001, min(50.0, (float) $this->get('defaultWeight', $salesChannelId, 1.0)));
    }

    public function labelFormat(?string $salesChannelId): string
    {
        return $this->get('labelFormat', $salesChannelId, 'A6') === 'A4' ? 'A4' : 'A6';
    }

    public function codEnabled(?string $salesChannelId): bool
    {
        return (bool) $this->get('codEnabled', $salesChannelId, true);
    }

    public function isApiConfigured(?string $salesChannelId): bool
    {
        return $this->apiToken($salesChannelId) !== '' && $this->organizationId($salesChannelId) !== '';
    }

    public function isCourierEnabled(?string $salesChannelId): bool
    {
        return $this->isActive($salesChannelId) && $this->isApiConfigured($salesChannelId);
    }

    public function isLockerEnabled(?string $salesChannelId): bool
    {
        return $this->isCourierEnabled($salesChannelId) && $this->geowidgetToken($salesChannelId) !== '';
    }

    private function get(string $key, ?string $salesChannelId, mixed $default): mixed
    {
        $value = $this->systemConfigService->get(self::PREFIX . $key, $salesChannelId);

        return $value ?? $default;
    }
}
