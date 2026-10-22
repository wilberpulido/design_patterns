<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// APPLICATION LAYER — use cases, DTOs, orchestration
// ============================================================

class OrderApplicationService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway $payments,
        private StockService $stock
    ) {}

    public function placeOrder(PlaceOrderCommand $cmd): PlaceOrderResult
    {
        echo "\n[OrderApplicationService] Starting PlaceOrder use case...\n";

        $order = new Order(uniqid('ord_'), $cmd->customerId);

        foreach ($cmd->items as $item) {
            // Application coordinates infrastructure checks, domain enforces rules
            if (! $this->stock->isAvailable($item['productId'], $item['quantity'])) {
                throw new \RuntimeException("Product '{$item['name']}' is out of stock.");
            }
            $order->addItem(new OrderItem(
                $item['productId'],
                $item['name'],
                $item['quantity'],
                new Money($item['unitPrice'])
            ));
        }

        $order->place(); // domain enforces: no empty orders, immutable once placed

        $txId = $this->payments->charge($cmd->customerId, $order->total());
        $this->orders->save($order);

        echo "[OrderApplicationService] PlaceOrder use case completed.\n";

        return new PlaceOrderResult($order->id, $order->total()->amount, $txId);
    }
}
