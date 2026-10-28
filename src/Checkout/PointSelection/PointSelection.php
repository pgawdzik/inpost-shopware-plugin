<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Checkout\PointSelection;

use Shopware\Core\Framework\Struct\Struct;

final class PointSelection extends Struct
{
    public const EXTENSION_KEY = 'cluewareInpostPoint';

    /**
     * @param list<string> $types
     */
    public function __construct(
        protected string $name,
        protected array $types,
        protected string $street,
        protected string $buildingNumber,
        protected string $postCode,
        protected string $city,
        protected ?float $latitude,
        protected ?float $longitude
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $name = self::text($payload['name'] ?? null, 64);
        if ($name === '' || preg_match('/^[A-Za-z0-9_-]{2,64}$/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid InPost point identifier.');
        }

        $details = \is_array($payload['address_details'] ?? null) ? $payload['address_details'] : [];
        $address = \is_array($payload['address'] ?? null) ? $payload['address'] : [];
        $location = \is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $types = \is_array($payload['type'] ?? null) ? $payload['type'] : [];
        $types = array_values(array_filter(array_map(
            static fn (mixed $type): string => self::text($type, 48),
            $types
        )));

        $street = self::text($details['street'] ?? $address['line1'] ?? '', 120);
        $building = self::text($details['building_number'] ?? '', 32);
        $postCode = self::text($details['post_code'] ?? '', 16);
        $city = self::text($details['city'] ?? $address['line2'] ?? '', 100);

        $latitude = self::coordinate($location['latitude'] ?? null, -90.0, 90.0);
        $longitude = self::coordinate($location['longitude'] ?? null, -180.0, 180.0);

        return new self($name, array_slice($types, 0, 8), $street, $building, $postCode, $city, $latitude, $longitude);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'types' => $this->types,
            'street' => $this->street,
            'buildingNumber' => $this->buildingNumber,
            'postCode' => $this->postCode,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    private static function text(mixed $value, int $limit): string
    {
        if (!\is_scalar($value)) {
            return '';
        }

        $value = trim(strip_tags((string) $value));

        return mb_substr($value, 0, $limit);
    }

    private static function coordinate(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        return $coordinate >= $min && $coordinate <= $max ? $coordinate : null;
    }
}
