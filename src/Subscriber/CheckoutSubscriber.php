<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Subscriber;

use Clueware\InpostShipping\Checkout\PointSelection\PointSelection;
use Clueware\InpostShipping\Configuration\PluginConfig;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PluginConfig $config)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmLoaded',
            CartConvertedEvent::class => 'onCartConverted',
        ];
    }

    public function onConfirmLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        if (!$this->config->isActive($salesChannelId)) {
            return;
        }

        $countries = $this->config->geowidgetCountries($salesChannelId);
        $selection = $event->getPage()->getCart()->getExtension(PointSelection::EXTENSION_KEY);
        $event->getPage()->addExtension('cluewareInpostShipping', new ArrayStruct([
            'lockerShippingMethodId' => $this->config->lockerShippingMethodId($salesChannelId),
            'courierShippingMethodId' => $this->config->courierShippingMethodId($salesChannelId),
            'geowidgetToken' => $this->config->geowidgetToken($salesChannelId),
            'assetsUrl' => $this->config->geowidgetAssetsUrl($salesChannelId),
            'pointsApiUrl' => $this->config->geowidgetPointsApiUrl($salesChannelId),
            'countries' => $countries,
            'language' => $this->geowidgetLanguage($salesChannelContext),
            'shippingAddressLocation' => $this->shippingAddressLocation($salesChannelContext, $countries),
            'selection' => $selection instanceof PointSelection ? $selection->toArray() : null,
        ], 'clueware_inpost_shipping_config'));
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->config->isActive($salesChannelId)) {
            return;
        }

        $shippingMethodId = $event->getSalesChannelContext()->getShippingMethod()->getId();
        $lockerMethodId = $this->config->lockerShippingMethodId($salesChannelId);
        $courierMethodId = $this->config->courierShippingMethodId($salesChannelId);
        if ($shippingMethodId !== $lockerMethodId && $shippingMethodId !== $courierMethodId) {
            return;
        }

        $converted = $event->getConvertedCart();
        $customFields = \is_array($converted['customFields'] ?? null) ? $converted['customFields'] : [];
        $customFields['cluewareInpostDeliveryType'] = $shippingMethodId === $lockerMethodId ? 'locker' : 'courier';

        $selection = $event->getCart()->getExtension(PointSelection::EXTENSION_KEY);
        if ($selection instanceof PointSelection && $shippingMethodId === $lockerMethodId) {
            $customFields['cluewareInpostPoint'] = $selection->toArray();
        }

        $converted['customFields'] = $customFields;
        $event->setConvertedCart($converted);
    }

    /**
     * @param list<string> $countries
     *
     * @return array{street: string, postalCode: string, city: string, countryCode: string}|null
     */
    private function shippingAddressLocation(
        SalesChannelContext $context,
        array $countries,
    ): ?array {
        $location = $context->getShippingLocation();
        $address = $location->getAddress();
        $countryCode = strtoupper((string) $location->getCountry()->getIso());

        if ($address === null || !\in_array($countryCode, $countries, true)) {
            return null;
        }

        $street = $this->searchText($address->getStreet(), 120);
        $postcode = $this->searchText($address->getZipcode(), 16);
        $city = $this->searchText($address->getCity(), 100);

        if (mb_strlen($street) < 3 || mb_strlen($postcode) < 3 || mb_strlen($city) < 2) {
            return null;
        }

        return [
            'street' => $street,
            'postalCode' => $postcode,
            'city' => $city,
            'countryCode' => $countryCode,
        ];
    }

    private function searchText(?string $value, int $limit): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $value)) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $limit);
    }

    private function geowidgetLanguage(SalesChannelContext $context): string
    {
        $language = strtolower(explode('-', $context->getLanguageInfo()->localeCode, 2)[0]);

        return \in_array($language, ['de', 'en', 'es', 'fr', 'it', 'nl', 'pl', 'pt', 'uk'], true)
            ? $language
            : 'en';
    }
}
