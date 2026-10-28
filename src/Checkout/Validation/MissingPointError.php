<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Checkout\Validation;

use Shopware\Core\Checkout\Cart\Error\Error;

final class MissingPointError extends Error
{
    public function __construct()
    {
        parent::__construct('An InPost pickup point must be selected.');
    }

    public function getId(): string
    {
        return 'clueware-inpost-point-required';
    }

    public function getMessageKey(): string
    {
        return 'cluewareInpostShipping.checkout.pointRequired';
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function blockResubmit(): bool
    {
        return false;
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [];
    }
}
