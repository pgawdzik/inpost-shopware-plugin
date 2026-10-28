<?php declare(strict_types=1);

namespace Clueware\InpostShipping\Checkout\Validation;

use Shopware\Core\Checkout\Cart\Error\Error;

final class MissingPhoneNumberError extends Error
{
    public function __construct()
    {
        parent::__construct('A recipient phone number is required for InPost delivery.');
    }

    public function getId(): string
    {
        return 'clueware-inpost-phone-number-required';
    }

    public function getMessageKey(): string
    {
        return 'cluewareInpostShipping.checkout.phoneNumberRequired';
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
