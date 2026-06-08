// Scenario: pricing strategies for an e-commerce platform.
// Different user types and conditions get different discount calculations.
// Adding a new pricing rule should not require modifying the cart or existing strategies.

interface PriceBreakdown {
  originalPrice: number;
  discount: number;
  finalPrice: number;
  label: string;
}

// The Strategy interface — every pricing rule implements this contract
interface PricingStrategy {
  calculate(unitPrice: number, quantity: number): PriceBreakdown;
  strategyName(): string;
}

// Concrete Strategy: regular pricing — no discount
class RegularPricing implements PricingStrategy {
  calculate(unitPrice: number, quantity: number): PriceBreakdown {
    const original = unitPrice * quantity;
    console.log(`[RegularPricing] Applying standard pricing for ${quantity} units...`);
    return { originalPrice: original, discount: 0, finalPrice: original, label: "Regular price" };
  }
  strategyName() { return "Regular"; }
}

// Concrete Strategy: member pricing — flat 15% discount for subscribed users
class MemberPricing implements PricingStrategy {
  private readonly discountRate = 0.15;

  calculate(unitPrice: number, quantity: number): PriceBreakdown {
    const original = unitPrice * quantity;
    const discount = original * this.discountRate;
    console.log(`[MemberPricing] Applying ${this.discountRate * 100}% member discount...`);
    return { originalPrice: original, discount, finalPrice: original - discount, label: "Member price (15% off)" };
  }
  strategyName() { return "Member"; }
}

// Concrete Strategy: bulk pricing — tiered discount based on quantity
class BulkPricing implements PricingStrategy {
  calculate(unitPrice: number, quantity: number): PriceBreakdown {
    const original = unitPrice * quantity;
    // Tiered discount: 10% for 10+, 20% for 50+, 30% for 100+
    const rate = quantity >= 100 ? 0.30 : quantity >= 50 ? 0.20 : quantity >= 10 ? 0.10 : 0;
    const discount = original * rate;
    console.log(`[BulkPricing] Quantity: ${quantity} → discount tier: ${rate * 100}%`);
    return { originalPrice: original, discount, finalPrice: original - discount, label: `Bulk price (${rate * 100}% off)` };
  }
  strategyName() { return "Bulk"; }
}

// Concrete Strategy: seasonal sale — fixed percentage configured externally
class SeasonalPricing implements PricingStrategy {
  constructor(private readonly salePercent: number, private readonly campaignName: string) {}

  calculate(unitPrice: number, quantity: number): PriceBreakdown {
    const original = unitPrice * quantity;
    const discount = original * (this.salePercent / 100);
    console.log(`[SeasonalPricing] Applying ${this.campaignName} sale: ${this.salePercent}% off...`);
    return { originalPrice: original, discount, finalPrice: original - discount, label: `${this.campaignName} (${this.salePercent}% off)` };
  }
  strategyName() { return `Seasonal (${this.campaignName})`; }
}

// The Context — calculates price using whatever strategy is active.
class PriceCalculator {
  constructor(private strategy: PricingStrategy) {}

  // matiz: the strategy can be swapped mid-lifecycle. This is key when user state changes
  // during a session — e.g., a user logs in while browsing and instantly gets member pricing
  // applied to their existing cart without rebuilding the calculator.
  setStrategy(strategy: PricingStrategy): void {
    console.log(`[PriceCalculator] Switching pricing strategy to: ${strategy.strategyName()}`);
    this.strategy = strategy;
  }

  quote(unitPrice: number, quantity: number): PriceBreakdown {
    return this.strategy.calculate(unitPrice, quantity);
  }
}

// Client — a shopping cart that applies the correct pricing based on user type
class ShoppingCart {
  constructor(private readonly calculator: PriceCalculator) {}

  addItem(productName: string, unitPrice: number, quantity: number, strategy: PricingStrategy): void {
    console.log(`\n[ShoppingCart] Adding ${quantity}x "${productName}" @ $${unitPrice} each...`);
    this.calculator.setStrategy(strategy);
    const breakdown = this.calculator.quote(unitPrice, quantity);

    console.log(`[ShoppingCart] ${breakdown.label}`);
    console.log(`[ShoppingCart]   Original: $${breakdown.originalPrice.toFixed(2)}`);
    console.log(`[ShoppingCart]   Discount: -$${breakdown.discount.toFixed(2)}`);
    console.log(`[ShoppingCart]   Final:    $${breakdown.finalPrice.toFixed(2)}`);
  }
}

// Bootstrap
const calculator = new PriceCalculator(new RegularPricing());
const cart = new ShoppingCart(calculator);

cart.addItem("Wireless Headphones", 89.99, 1,   new RegularPricing());
cart.addItem("Wireless Headphones", 89.99, 1,   new MemberPricing());
cart.addItem("USB-C Cable",          9.99, 75,  new BulkPricing());
cart.addItem("Mechanical Keyboard", 149.99, 2,  new SeasonalPricing(25, "Black Friday"));
