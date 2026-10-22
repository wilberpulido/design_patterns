<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\Layered;

// ============================================================
// PRESENTATION LAYER — HTTP controller
// ============================================================

class OrderController
{
    public function __construct(private OrderApplicationService $service) {}

    // Simulates handling POST /orders
    public function store(array $httpRequest): void
    {
        echo "\n[OrderController] POST /orders received\n";

        // Presentation only: map HTTP input → application command
        $result = $this->service->placeOrder(new PlaceOrderCommand(
            customerId: $httpRequest['customer_id'],
            items: $httpRequest['items']
        ));

        // matiz: the controller formats the response — it never decides business outcomes.
        // Discount logic, order limits, fraud checks — those live in Domain or Application.
        echo "[OrderController] 201 Created — order {$result->orderId} | total \${$result->total} | tx: {$result->transactionId}\n";
    }
}
