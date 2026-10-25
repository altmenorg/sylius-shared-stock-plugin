<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Exception;

/** A stock movement was refused by the database: not enough free stock, or nothing to move. */
final class InsufficientSharedStockException extends \RuntimeException
{
    public function __construct(public readonly int|string $productId, public readonly int $requested)
    {
        parent::__construct(sprintf('Not enough shared stock for product %s (%d requested).', $productId, $requested));
    }
}
