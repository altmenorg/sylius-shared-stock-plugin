<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Tests\Unit;

use Altmenorg\SyliusSharedStockPlugin\Exception\InsufficientSharedStockException;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockOrderInventoryOperator;
use Altmenorg\SyliusSharedStockPlugin\Inventory\SharedStockStoreInterface;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\Product;
use Altmenorg\SyliusSharedStockPlugin\Tests\Fixtures\ProductVariant;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Inventory\Operator\OrderInventoryOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\OrderPaymentStates;

final class SharedStockOrderInventoryOperatorTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];

    private function store(?string $refuseHoldOf = null): SharedStockStoreInterface
    {
        $log = &$this->log;
        $store = $this->createMock(SharedStockStoreInterface::class);
        foreach (['hold', 'release', 'sell', 'giveBack'] as $method) {
            $store->method($method)->willReturnCallback(
                static function (SharedStockProductInterface $product, int $quantity) use (&$log, $method, $refuseHoldOf): void {
                    $code = $product instanceof Product ? $product->getCode() : '?';
                    if ($method === 'hold' && $code === $refuseHoldOf) {
                        throw new InsufficientSharedStockException((int) $product->getId(), $quantity);
                    }
                    $log[] = "$method $code $quantity";
                });
        }

        return $store;
    }

    private function product(string $code, ?int $onHand = 1000): Product
    {
        $product = new Product(crc32($code));
        $product->setCode($code);
        $product->setSharedStockOnHand($onHand);

        return $product;
    }

    /** @param list<array{0: ProductVariant, 1: int}> $lines */
    private function order(array $lines, string $paymentState = OrderPaymentStates::STATE_AWAITING_PAYMENT): OrderInterface
    {
        $items = [];
        foreach ($lines as [$variant, $quantity]) {
            $item = $this->createMock(OrderItemInterface::class);
            $item->method('getVariant')->willReturn($variant);
            $item->method('getQuantity')->willReturn($quantity);
            $items[] = $item;
        }
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection($items));
        $order->method('getPaymentState')->willReturn($paymentState);

        return $order;
    }

    private function inner(string ...$expected): OrderInventoryOperatorInterface
    {
        $inner = $this->createMock(OrderInventoryOperatorInterface::class);
        foreach (['hold', 'sell', 'cancel'] as $method) {
            $inner->expects(in_array($method, $expected, true) ? self::once() : self::never())->method($method);
        }

        return $inner;
    }

    public function testQuantitiesAreSummedPerProduct(): void
    {
        $product = $this->product('ZZ9A');
        $order = $this->order([[ProductVariant::of($product, 100), 2], [ProductVariant::of($product, 250), 1]]);

        (new SharedStockOrderInventoryOperator($this->inner('hold'), $this->store()))->hold($order);

        self::assertSame(['hold ZZ9A 450'], $this->log);
    }

    public function testOtherVariantsAreLeftToSylius(): void
    {
        $order = $this->order([
            [ProductVariant::of($this->product('NOSHARE', null), 100), 1],
            [ProductVariant::of($this->product('NOCONSUMPTION'), null), 1],
        ]);

        (new SharedStockOrderInventoryOperator($this->inner('sell'), $this->store()))->sell($order);

        self::assertSame([], $this->log);
    }

    public function testHoldIsAllOrNothing(): void
    {
        $order = $this->order([
            [ProductVariant::of($this->product('ZZ9A'), 100), 1],
            [ProductVariant::of($this->product('ZZ9B'), 250), 1],
        ]);

        try {
            (new SharedStockOrderInventoryOperator($this->inner(), $this->store('ZZ9B')))->hold($order);
            self::fail('The hold should have been refused.');
        } catch (InsufficientSharedStockException) {
        }

        self::assertSame(['hold ZZ9A 100', 'release ZZ9A 100'], $this->log);
    }

    public function testCancelReleasesUnpaidAndGivesBackPaid(): void
    {
        $product = $this->product('ZZ9A');
        $variant = ProductVariant::of($product, 100);

        (new SharedStockOrderInventoryOperator($this->inner('cancel'), $this->store()))->cancel($this->order([[$variant, 3]]));
        (new SharedStockOrderInventoryOperator($this->inner('cancel'), $this->store()))->cancel($this->order([[$variant, 3]], OrderPaymentStates::STATE_PAID));
        (new SharedStockOrderInventoryOperator($this->inner('cancel'), $this->store()))->cancel($this->order([[$variant, 3]], OrderPaymentStates::STATE_REFUNDED));

        self::assertSame(['release ZZ9A 300', 'giveBack ZZ9A 300', 'giveBack ZZ9A 300'], $this->log);
    }
}
