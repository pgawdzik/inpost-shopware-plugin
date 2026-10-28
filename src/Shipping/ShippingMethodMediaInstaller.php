<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Shipping;

use Clueware\InpostShipping\Configuration\PluginConfig;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

final class ShippingMethodMediaInstaller
{
    private const ASSET_MARKER = 'cluewareInpostShippingAsset';

    public function __construct(
        private readonly Connection $connection,
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function assign(Context $context, string $pluginPath): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($pluginPath): void {
            $mediaUpdates = [];

            foreach ([
                PluginConfig::LOCKER_SHIPPING_METHOD_ID => 'Inpost_logo_parcelocker.svg',
                PluginConfig::COURIER_SHIPPING_METHOD_ID => 'Inpost_logo_courier.svg',
            ] as $shippingMethodId => $imageName) {
                $shippingMethodIdBytes = Uuid::fromHexToBytes($shippingMethodId);
                $mediaId = $this->connection->fetchOne(
                    'SELECT `media_id` FROM `shipping_method` WHERE `id` = :id',
                    ['id' => $shippingMethodIdBytes]
                );

                if (\is_string($mediaId) && $mediaId !== '') {
                    continue;
                }

                $mediaId = $this->connection->fetchOne(
                    <<<'SQL'
SELECT `id`
FROM `media`
WHERE `file_name` = :fileName
  AND `file_extension` = :fileExtension
  AND JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.cluewareInpostShippingAsset')) = 'true'
ORDER BY `created_at` DESC
LIMIT 1
SQL,
                    [
                        'fileName' => pathinfo($imageName, \PATHINFO_FILENAME),
                        'fileExtension' => 'svg',
                    ]
                );

                if (!\is_string($mediaId) || $mediaId === '') {
                    $image = file_get_contents($pluginPath . '/Resources/public/shipping-methods/' . $imageName);
                    if (!\is_string($image)) {
                        continue;
                    }

                    $mediaIdHex = $this->mediaService->saveFile(
                        $image,
                        'svg',
                        'image/svg+xml',
                        pathinfo($imageName, \PATHINFO_FILENAME),
                        $context,
                        'shipping_method',
                        null,
                        false
                    );
                    $mediaUpdates[] = [
                        'id' => $mediaIdHex,
                        'customFields' => [self::ASSET_MARKER => true],
                    ];
                    $mediaId = Uuid::fromHexToBytes($mediaIdHex);
                }

                $this->connection->update(
                    'shipping_method',
                    ['media_id' => $mediaId],
                    ['id' => $shippingMethodIdBytes]
                );
            }

            if ($mediaUpdates !== []) {
                $this->mediaRepository->update($mediaUpdates, $context);
            }
        });
    }
}
