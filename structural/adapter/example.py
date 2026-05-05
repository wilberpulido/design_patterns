from abc import ABC, abstractmethod
from dataclasses import dataclass


# Value objects representing our domain language.
@dataclass
class Package:
    weight_kg: float
    destination: str
    sender: str

@dataclass
class TrackingInfo:
    tracking_number: str
    carrier: str
    estimated_days: int


# The target interface — what our fulfillment module expects from any shipping carrier.
# The application depends on this abstraction, not on any specific carrier SDK.
class ShippingCarrier(ABC):
    @abstractmethod
    def ship(self, package: Package) -> TrackingInfo:
        pass

    @abstractmethod
    def get_rate(self, package: Package) -> float:
        pass


# The adaptee — FedEx's legacy SDK with a completely different API.
# We cannot modify this (it's a vendor library installed via pip).
# Problems: works in pounds (not kg), returns raw dicts, uses a different parameter order.
class FedExLegacySDK:
    def create_shipment(self, weight_lbs: float, dest_address: str, origin: str) -> dict:
        print(f"[FedExLegacySDK] Creating shipment: {weight_lbs} lbs → {dest_address} from {origin}")
        tracking = f"FDX{abs(hash(dest_address + str(weight_lbs))) % 100000:05d}"
        return {"tracking_id": tracking, "transit_days": 3, "success": True}

    def get_shipping_cost(self, weight_lbs: float, zone: str) -> float:
        print(f"[FedExLegacySDK] Calculating cost: {weight_lbs} lbs, zone '{zone}'")
        return round(weight_lbs * 2.5 + 5.0, 2)


# The Adapter — implements ShippingCarrier using FedExLegacySDK under the hood.
# Responsibilities: unit conversion (kg → lbs), zone mapping, and response normalization.
class FedExAdapter(ShippingCarrier):
    KG_TO_LBS = 2.20462

    def __init__(self, sdk: FedExLegacySDK):
        # matiz: Inject the adaptee via constructor rather than instantiating it internally.
        # This makes the adapter unit-testable — you can pass a fake FedExLegacySDK
        # to verify the adapter's translation logic without making real HTTP calls.
        self._sdk = sdk

    def ship(self, package: Package) -> TrackingInfo:
        print(f"[FedExAdapter] ship() — converting {package.weight_kg} kg → lbs...")
        weight_lbs = round(package.weight_kg * self.KG_TO_LBS, 3)

        result = self._sdk.create_shipment(weight_lbs, package.destination, package.sender)

        # Normalize the raw dict into our domain object (TrackingInfo).
        return TrackingInfo(
            tracking_number=result["tracking_id"],
            carrier="FedEx",
            estimated_days=result["transit_days"]
        )

    def get_rate(self, package: Package) -> float:
        print(f"[FedExAdapter] get_rate() — converting kg and mapping destination to FedEx zone...")
        weight_lbs = round(package.weight_kg * self.KG_TO_LBS, 3)

        # Zone mapping: our interface works with destination strings; FedEx expects zone codes.
        zone = "A" if "US" in package.destination else "B"
        return self._sdk.get_shipping_cost(weight_lbs, zone)


# Client code — works only with ShippingCarrier.
# Switching from FedEx to UPS means creating a UPSAdapter, nothing else changes here.
class FulfillmentService:
    def __init__(self, carrier: ShippingCarrier):
        self._carrier = carrier

    def fulfill(self, package: Package) -> None:
        print(f"\n[FulfillmentService] Requesting shipping rate for package to {package.destination}...")
        rate = self._carrier.get_rate(package)
        print(f"[FulfillmentService] Estimated shipping cost: ${rate}")

        print(f"\n[FulfillmentService] Dispatching package from {package.sender}...")
        tracking = self._carrier.ship(package)
        print(
            f"[FulfillmentService] Shipped! Tracking: {tracking.tracking_number} "
            f"via {tracking.carrier} (~{tracking.estimated_days} business days)"
        )


if __name__ == "__main__":
    pkg = Package(weight_kg=2.5, destination="New York, US", sender="Warehouse Central")
    sdk = FedExLegacySDK()
    adapter = FedExAdapter(sdk)
    service = FulfillmentService(adapter)

    service.fulfill(pkg)
