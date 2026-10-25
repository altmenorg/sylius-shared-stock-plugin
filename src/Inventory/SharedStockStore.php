<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Inventory;

use Altmenorg\SyliusSharedStockPlugin\Exception\InsufficientSharedStockException;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Every movement of a shared stock is one SQL UPDATE whose WHERE clause carries the check
 * ("hold 200 if 200 are free"): two concurrent checkouts can never both take the last 200 g.
 * Never read-compute-write. After each update the managed entity is refreshed (value and Doctrine
 * original data), so that a later flush neither overwrites nor reverts the stored figures.
 */
final class SharedStockStore implements SharedStockStoreInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly bool $allowNegative = false,
    ) {
    }

    public function hold(SharedStockProductInterface $product, int $quantity): void
    {
        $this->update($product, 'shared_stock_on_hold = shared_stock_on_hold + :q',
            $this->allowNegative ? '' : 'AND shared_stock_on_hand - shared_stock_on_hold >= :q', $quantity);
    }

    public function release(SharedStockProductInterface $product, int $quantity): void
    {
        $this->update($product, 'shared_stock_on_hold = shared_stock_on_hold - :q', 'AND shared_stock_on_hold >= :q', $quantity);
    }

    public function sell(SharedStockProductInterface $product, int $quantity): void
    {
        $this->update($product, 'shared_stock_on_hold = shared_stock_on_hold - :q, shared_stock_on_hand = shared_stock_on_hand - :q',
            'AND shared_stock_on_hold >= :q', $quantity);
    }

    public function giveBack(SharedStockProductInterface $product, int $quantity): void
    {
        $this->update($product, 'shared_stock_on_hand = shared_stock_on_hand + :q', '', $quantity);
    }

    public function adjust(SharedStockProductInterface $product, string $method, int $quantity, bool $allowNegative = false): void
    {
        match ($method) {
            'replace' => $this->update($product, 'shared_stock_on_hand = :q', '', $quantity),
            'increase' => $this->update($product, 'shared_stock_on_hand = shared_stock_on_hand + :q', '', $quantity),
            'decrease' => $this->update($product, 'shared_stock_on_hand = shared_stock_on_hand - :q',
                $allowNegative ? '' : 'AND shared_stock_on_hand >= :q', $quantity),
            default => throw new \InvalidArgumentException('Unknown stock method ' . $method),
        };
    }

    public function enable(SharedStockProductInterface $product, int $onHand): void
    {
        $this->switch($product, 'shared_stock_on_hand = :q', 'shared_stock_on_hand IS NULL', ['q' => $onHand], $onHand);
    }

    public function disable(SharedStockProductInterface $product): void
    {
        $this->switch($product, 'shared_stock_on_hand = NULL', 'shared_stock_on_hand IS NOT NULL AND shared_stock_on_hold = 0', [], 0);
    }

    public function available(SharedStockProductInterface $product): int
    {
        $this->refresh($product);

        return (int) $product->getSharedStockOnHand() - $product->getSharedStockOnHold();
    }

    private function update(SharedStockProductInterface $product, string $set, string $check, int $quantity): void
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }
        $table = $this->em->getClassMetadata($product::class)->getTableName();
        $rows = $this->em->getConnection()->executeStatement(
            "UPDATE $table SET $set, shared_stock_version = shared_stock_version + 1 WHERE id = :id AND shared_stock_on_hand IS NOT NULL $check",
            ['q' => $quantity, 'id' => $product->getId()],
        );
        if ($rows !== 1) {
            $this->refresh($product);

            throw new InsufficientSharedStockException($product->getId(), $quantity);
        }
        $this->refresh($product);
    }

    /** Turning the shared stock on or off: same single checked UPDATE, other conditions. */
    private function switch(SharedStockProductInterface $product, string $set, string $condition, array $params, int $quantity): void
    {
        $table = $this->em->getClassMetadata($product::class)->getTableName();
        $rows = $this->em->getConnection()->executeStatement(
            "UPDATE $table SET $set, shared_stock_version = shared_stock_version + 1 WHERE id = :id AND $condition",
            $params + ['id' => $product->getId()],
        );
        $this->refresh($product);
        if ($rows !== 1) {
            throw new InsufficientSharedStockException($product->getId(), $quantity);
        }
    }

    private function refresh(SharedStockProductInterface $product): void
    {
        $table = $this->em->getClassMetadata($product::class)->getTableName();
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT shared_stock_on_hand, shared_stock_on_hold FROM $table WHERE id = ?", [$product->getId()]);
        if ($row === false) {
            return;
        }
        $values = ['sharedStockOnHand' => $row['shared_stock_on_hand'] === null ? null : (int) $row['shared_stock_on_hand'],
                   'sharedStockOnHold' => (int) $row['shared_stock_on_hold']];
        $uow = $this->em->getUnitOfWork();
        $meta = $this->em->getClassMetadata($product::class);
        foreach ($values as $field => $value) {
            $meta->setFieldValue($product, $field, $value);
            if ($uow->isInIdentityMap($product)) {
                $uow->setOriginalEntityProperty(spl_object_id($product), $field, $value);
            }
        }
    }
}
