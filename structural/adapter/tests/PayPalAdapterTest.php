<?php

declare(strict_types=1);

namespace DesignPatterns\Tests\Structural\Adapter;

use DesignPatterns\Structural\Adapter\LegacyPayPalClient;
use DesignPatterns\Structural\Adapter\PaymentGateway;
use DesignPatterns\Structural\Adapter\PayPalAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayPalAdapter::class)]
#[CoversClass(LegacyPayPalClient::class)]
final class PayPalAdapterTest extends TestCase
{
    public function test_adapter_translates_dollars_into_the_legacy_cents_api(): void
    {
        // The adaptee only understands integer cents and uppercase currency codes.
        // A spy subclass records what the adapter actually sends to it.
        $legacy = new class extends LegacyPayPalClient {
            public array $received = [];
            public function submitPaymentRequest(int $amountInCents, string $currencyCode, string $cardToken, string $description): string
            {
                $this->received = [$amountInCents, $currencyCode];
                return 'PPL-TXN-FAKE';
            }
        };
        $this->expectOutputRegex('/\[PayPalAdapter\]/');

        $result = (new PayPalAdapter($legacy))->charge(99.99, 'usd', 'tok_test');

        $this->assertSame([9999, 'USD'], $legacy->received);
        $this->assertSame('success', $result['status']);
    }

    public function test_refund_maps_the_internal_id_back_to_the_paypal_id(): void
    {
        $this->expectOutputRegex('/Cancelling transaction PPL-TXN-/');

        // Client code only sees the target interface, never the legacy class.
        $gateway = new PayPalAdapter(new LegacyPayPalClient());
        $this->assertInstanceOf(PaymentGateway::class, $gateway);

        $charge = $gateway->charge(10.0, 'USD', 'tok_test');

        $this->assertTrue($gateway->refund($charge['transaction_id']));
    }
}
