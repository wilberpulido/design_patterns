from abc import ABC, abstractmethod
from typing import List
from dataclasses import dataclass

# ─── Event data ───────────────────────────────────────────────────────────────
# A dataclass groups all relevant information about a price change.
# Passing a structured object (not raw args) makes adding fields easy
# without breaking every observer's method signature.
@dataclass
class PriceChangedEvent:
    ticker: str
    old_price: float
    new_price: float

    @property
    def change_pct(self) -> float:
        return ((self.new_price - self.old_price) / self.old_price) * 100


# ─── Observer interface ───────────────────────────────────────────────────────
class PriceObserver(ABC):
    @abstractmethod
    def on_price_changed(self, event: PriceChangedEvent) -> None:
        pass


# ─── Subject ──────────────────────────────────────────────────────────────────
# StockTicker is the subject. It tracks price and notifies observers on change.
# It knows nothing about what observers do with the information.
class StockTicker:
    def __init__(self, ticker: str, initial_price: float):
        self.ticker = ticker
        self._price = initial_price
        self._observers: List[PriceObserver] = []

    def subscribe(self, observer: PriceObserver) -> None:
        self._observers.append(observer)
        name = type(observer).__name__
        print(f"[StockTicker:{self.ticker}] Subscribed: {name}")

    def unsubscribe(self, observer: PriceObserver) -> None:
        self._observers.remove(observer)
        name = type(observer).__name__
        print(f"[StockTicker:{self.ticker}] Unsubscribed: {name}")

    def set_price(self, new_price: float) -> None:
        if new_price == self._price:
            return  # No state change → no notification needed.

        event = PriceChangedEvent(
            ticker=self.ticker,
            old_price=self._price,
            new_price=new_price,
        )
        self._price = new_price

        direction = "▲" if new_price > event.old_price else "▼"
        print(f"\n[StockTicker:{self.ticker}] Price updated: "
              f"${event.old_price:.2f} → ${new_price:.2f} {direction} "
              f"({event.change_pct:+.2f}%)")

        self._notify(event)

    def _notify(self, event: PriceChangedEvent) -> None:
        print(f"[StockTicker:{self.ticker}] Notifying {len(self._observers)} observer(s)...")
        for observer in self._observers:
            observer.on_price_changed(event)


# ─── Concrete Observers ───────────────────────────────────────────────────────

class AlertObserver(PriceObserver):
    """Fires an alert when price moves more than a given threshold."""

    def __init__(self, threshold_pct: float):
        self.threshold_pct = threshold_pct

    def on_price_changed(self, event: PriceChangedEvent) -> None:
        abs_change = abs(event.change_pct)
        if abs_change >= self.threshold_pct:
            direction = "surged" if event.change_pct > 0 else "dropped"
            print(f"[AlertSystem] ⚠ {event.ticker} {direction} {abs_change:.2f}%! "
                  f"Sending push notification...")
        else:
            print(f"[AlertSystem] {event.ticker} change ({event.change_pct:+.2f}%) "
                  f"below threshold. No alert.")


class PortfolioObserver(PriceObserver):
    """Recalculates portfolio value whenever a held stock changes price."""

    def __init__(self, shares_held: int):
        self.shares_held = shares_held
        self._portfolio_value = 0.0

    def on_price_changed(self, event: PriceChangedEvent) -> None:
        self._portfolio_value = event.new_price * self.shares_held
        gain_loss = (event.new_price - event.old_price) * self.shares_held
        symbol = "+" if gain_loss >= 0 else ""
        print(f"[Portfolio] {event.ticker} x{self.shares_held} shares → "
              f"value: ${self._portfolio_value:,.2f} "
              f"(P&L: {symbol}${gain_loss:,.2f})")


class AuditLogObserver(PriceObserver):
    """Records every price tick for regulatory compliance."""

    # matiz: this observer has no business logic — it only records.
    # This is a cross-cutting concern (auditing) added without touching the subject.
    # In production this would write to an immutable log store.

    def __init__(self):
        self._log: list = []

    def on_price_changed(self, event: PriceChangedEvent) -> None:
        entry = {
            "ticker": event.ticker,
            "from": event.old_price,
            "to": event.new_price,
            "change_pct": round(event.change_pct, 4),
        }
        self._log.append(entry)
        print(f"[AuditLog] Tick recorded for {event.ticker}. "
              f"Total records: {len(self._log)}")


# ─── Main ─────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    print("=== Stock Market Price Alert System — Observer Pattern ===\n")

    aapl = StockTicker("AAPL", initial_price=182.50)

    alert_observer     = AlertObserver(threshold_pct=2.0)
    portfolio_observer = PortfolioObserver(shares_held=100)
    audit_observer     = AuditLogObserver()

    aapl.subscribe(alert_observer)
    aapl.subscribe(portfolio_observer)
    aapl.subscribe(audit_observer)

    # Minor price move — alert won't fire, but portfolio and audit will react.
    aapl.set_price(183.00)

    # Significant drop — alert triggers.
    aapl.set_price(178.00)

    # matiz: setting the same price produces no notification.
    # Prevents unnecessary downstream processing when nothing actually changed.
    print("\n--- Setting same price (should produce no notification) ---")
    aapl.set_price(178.00)

    # Strong recovery — alert fires again.
    aapl.set_price(186.75)
