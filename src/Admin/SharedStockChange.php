<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Admin;

/** A change of a product's shared stock asked for in the admin product form, applied once the product is saved. */
final class SharedStockChange
{
    public const ENABLE = 'enable';
    public const DISABLE = 'disable';
    public const CORRECT = 'correct';

    private function __construct(
        public readonly string $type,
        public readonly ?int $from,
        public readonly ?int $to,
    ) {
    }

    public static function enable(int $onHand): self
    {
        return new self(self::ENABLE, null, $onHand);
    }

    public static function disable(): self
    {
        return new self(self::DISABLE, null, null);
    }

    /** The admin saw $from on hand when the form was opened and asks for $to: a correction of $to - $from. */
    public static function correct(int $from, int $to): self
    {
        return new self(self::CORRECT, $from, $to);
    }

    public function delta(): int
    {
        return (int) $this->to - (int) $this->from;
    }
}
