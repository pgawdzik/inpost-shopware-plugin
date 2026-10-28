<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Subscriber;

use Clueware\InpostShipping\Configuration\PhoneFieldVisibilitySynchronizer;
use Clueware\InpostShipping\Shipping\ShippingMethodMediaInstaller;
use Clueware\InpostShipping\Shipping\ShippingMethodAvailabilitySynchronizer;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ShippingMethodMediaInstaller $mediaInstaller,
        private readonly ShippingMethodAvailabilitySynchronizer $availabilitySynchronizer,
        private readonly ?PhoneFieldVisibilitySynchronizer $phoneFieldVisibilitySynchronizer = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'initializePluginConfiguration',
            SystemConfigChangedEvent::class => 'synchronizeConfiguration',
        ];
    }

    public function initializePluginConfiguration(PluginPostActivateEvent $event): void
    {
        if ($event->getPlugin()->getName() !== 'CluewareInpostShipping') {
            return;
        }

        $this->mediaInstaller->assign($event->getContext()->getContext(), \dirname(__DIR__, 2));
        $this->availabilitySynchronizer->synchronize();
        $this->phoneFieldVisibilitySynchronizer?->synchronize();
    }

    /**
     * Kept for containers compiled before 2.0.8.
     */
    public function assignShippingMethodMedia(PluginPostActivateEvent $event): void
    {
        $this->initializePluginConfiguration($event);
    }

    public function synchronizeConfiguration(SystemConfigChangedEvent $event): void
    {
        if (!str_starts_with($event->getKey(), 'CluewareInpostShipping.config.')) {
            return;
        }

        $this->availabilitySynchronizer->synchronize($event->getSalesChannelId());
        $this->phoneFieldVisibilitySynchronizer?->synchronize($event->getSalesChannelId());
    }

    /**
     * Kept for containers compiled before 2.0.8.
     */
    public function synchronizeAvailability(SystemConfigChangedEvent $event): void
    {
        $this->synchronizeConfiguration($event);
    }
}
