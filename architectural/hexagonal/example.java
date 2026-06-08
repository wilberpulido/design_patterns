// Hexagonal Architecture — Online Auction Platform (Java)
//
// Compile: javac example.java
// Run:     java AuctionMain
//
// The application core (AuctionLot + use cases) has zero dependencies on
// HTTP frameworks, databases, or messaging systems.
// Ports and adapters isolate every external concern.

import java.util.*;
import java.time.Instant;

// ─────────────────────────────────────────────
// DOMAIN — pure business objects
// ─────────────────────────────────────────────

class AuctionLot {
    private final String id;
    private final String title;
    private final double reservePrice;
    private Bid   highestBid = null;
    private boolean closed = false;

    AuctionLot(String id, String title, double reservePrice) {
        this.id           = id;
        this.title        = title;
        this.reservePrice = reservePrice;
    }

    // Domain rule: a bid must exceed the current highest bid (or the reserve if no bids yet)
    void placeBid(Bid bid) {
        if (closed) {
            throw new IllegalStateException("Auction lot '" + id + "' is closed.");
        }
        double floor = (highestBid != null) ? highestBid.amount() : reservePrice;
        if (bid.amount() <= floor) {
            throw new IllegalArgumentException(
                "Bid of " + bid.amount() + " does not exceed current floor of " + floor);
        }
        this.highestBid = bid;
        System.out.println("[AuctionLot] '" + id + "' new highest bid: "
            + bid.amount() + " by '" + bid.bidderId() + "'");
    }

    // matiz: closing the lot is a domain event — downstream systems (payment, shipping)
    // are triggered by it, but the domain object itself only changes its own state.
    void close() {
        if (closed) throw new IllegalStateException("Already closed.");
        this.closed = true;
        System.out.println("[AuctionLot] '" + id + "' closed. Winner: "
            + (highestBid != null ? highestBid.bidderId() + " at " + highestBid.amount() : "no bids"));
    }

    String   getId()          { return id; }
    String   getTitle()       { return title; }
    Bid      getHighestBid()  { return highestBid; }
    boolean  isClosed()       { return closed; }
}

record Bid(String bidderId, double amount, Instant placedAt) {}

// ─────────────────────────────────────────────
// PORTS — interfaces owned by the application core
// ─────────────────────────────────────────────

// Primary ports (use-case interfaces external actors call)
interface PlaceBidPort {
    void placeBid(String lotId, String bidderId, double amount);
}

interface CloseLotPort {
    void closeLot(String lotId);
}

// Secondary ports (what the core needs from infrastructure)
interface AuctionRepository {
    void save(AuctionLot lot);
    Optional<AuctionLot> findById(String id);
}

interface BidEventPublisher {
    void publishBidPlaced(String lotId, String bidderId, double amount);
    void publishLotClosed(String lotId, String winnerId, double winningAmount);
}

// ─────────────────────────────────────────────
// APPLICATION SERVICE — implements primary ports
// ─────────────────────────────────────────────

class AuctionService implements PlaceBidPort, CloseLotPort {

    private final AuctionRepository  repository;
    private final BidEventPublisher  publisher;

    // matiz: the service receives PORT interfaces at construction time.
    // The same service runs against InMemoryAuctionRepository in tests
    // and PostgresAuctionRepository in production — with zero code changes.
    AuctionService(AuctionRepository repository, BidEventPublisher publisher) {
        this.repository = repository;
        this.publisher  = publisher;
    }

    @Override
    public void placeBid(String lotId, String bidderId, double amount) {
        System.out.println("[AuctionService] Processing bid — lot='" + lotId
            + "' bidder='" + bidderId + "' amount=" + amount);

        AuctionLot lot = repository.findById(lotId)
            .orElseThrow(() -> new NoSuchElementException("Lot '" + lotId + "' not found."));

        lot.placeBid(new Bid(bidderId, amount, Instant.now()));
        repository.save(lot);
        publisher.publishBidPlaced(lotId, bidderId, amount);
    }

    @Override
    public void closeLot(String lotId) {
        System.out.println("[AuctionService] Closing lot '" + lotId + "'");

        AuctionLot lot = repository.findById(lotId)
            .orElseThrow(() -> new NoSuchElementException("Lot '" + lotId + "' not found."));

        lot.close();
        repository.save(lot);

        if (lot.getHighestBid() != null) {
            Bid winner = lot.getHighestBid();
            publisher.publishLotClosed(lotId, winner.bidderId(), winner.amount());
        }
    }
}

// ─────────────────────────────────────────────
// SECONDARY ADAPTERS — driven side
// ─────────────────────────────────────────────

class InMemoryAuctionRepository implements AuctionRepository {
    private final Map<String, AuctionLot> store = new HashMap<>();

    @Override
    public void save(AuctionLot lot) {
        System.out.println("[InMemoryAuctionRepository] Saved lot '" + lot.getId()
            + "' — closed: " + lot.isClosed());
        store.put(lot.getId(), lot);
    }

    @Override
    public Optional<AuctionLot> findById(String id) {
        return Optional.ofNullable(store.get(id));
    }

    void seed(AuctionLot lot) { store.put(lot.getId(), lot); }
}

class WebSocketBidPublisher implements BidEventPublisher {
    @Override
    public void publishBidPlaced(String lotId, String bidderId, double amount) {
        System.out.println("[WebSocketBidPublisher] Broadcast → lot '" + lotId
            + "': new bid " + amount + " by '" + bidderId + "'");
    }

    @Override
    public void publishLotClosed(String lotId, String winnerId, double winningAmount) {
        System.out.println("[WebSocketBidPublisher] Broadcast → lot '" + lotId
            + "': closed. Winner '" + winnerId + "' at " + winningAmount);
    }
}

// ─────────────────────────────────────────────
// PRIMARY ADAPTER — driving side
// ─────────────────────────────────────────────

class AuctionApiController {
    // Depends on PORT interfaces — the adapter can be swapped for a CLI adapter
    // or a Kafka consumer adapter with zero changes to AuctionService.
    private final PlaceBidPort  placeBid;
    private final CloseLotPort  closeLot;

    AuctionApiController(PlaceBidPort placeBid, CloseLotPort closeLot) {
        this.placeBid = placeBid;
        this.closeLot = closeLot;
    }

    void postBid(String lotId, String bidderId, double amount) {
        System.out.println("[AuctionApiController] POST /lots/" + lotId
            + "/bids  bidder='" + bidderId + "' amount=" + amount);
        placeBid.placeBid(lotId, bidderId, amount);
        System.out.println("[AuctionApiController] 201 Created → Bid accepted");
    }

    void postClose(String lotId) {
        System.out.println("[AuctionApiController] POST /lots/" + lotId + "/close");
        closeLot.closeLot(lotId);
        System.out.println("[AuctionApiController] 200 OK → Lot closed");
    }
}

// ─────────────────────────────────────────────
// COMPOSITION ROOT
// ─────────────────────────────────────────────

class AuctionMain {
    public static void main(String[] args) {
        System.out.println("=== Hexagonal Architecture — Online Auction Platform (Java) ===\n");

        // Seed a lot
        InMemoryAuctionRepository repository = new InMemoryAuctionRepository();
        repository.seed(new AuctionLot("lot-101", "Vintage Rolex Submariner", 2000.00));

        WebSocketBidPublisher publisher = new WebSocketBidPublisher();
        AuctionService        service   = new AuctionService(repository, publisher);
        AuctionApiController  controller = new AuctionApiController(service, service);

        System.out.println("--- Bidder A opens with 2500 ---");
        controller.postBid("lot-101", "bidder-alice", 2500.00);

        System.out.println("\n--- Bidder B raises to 3100 ---");
        controller.postBid("lot-101", "bidder-bob", 3100.00);

        System.out.println("\n--- Bidder A retaliates with 3500 ---");
        controller.postBid("lot-101", "bidder-alice", 3500.00);

        System.out.println("\n--- Auctioneer closes the lot ---");
        controller.postClose("lot-101");
    }
}
