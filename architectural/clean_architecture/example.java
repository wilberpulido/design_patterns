// Clean Architecture — Real Estate Property Listing Platform (Java)
//
// Compile: javac example.java
// Run:     java PropertyMain
//
// The Dependency Rule: source code dependencies point ONLY inward.
// Entities know nothing about use cases; use cases know nothing about adapters.

import java.util.*;

// ─────────────────────────────────────────────
// RING 1: ENTITIES — enterprise-wide business rules (most stable)
// ─────────────────────────────────────────────

class Property {
    private final String id;
    private final String address;
    private final double priceUsd;
    private final int    sqMeters;
    private boolean      published = false;

    Property(String id, String address, double priceUsd, int sqMeters) {
        this.id        = id;
        this.address   = address;
        this.priceUsd  = priceUsd;
        this.sqMeters  = sqMeters;
    }

    // Business rule: a property must have a valid price and area to be listed
    void publish() {
        if (priceUsd <= 0) {
            throw new IllegalStateException("Property '" + id + "' must have a positive price.");
        }
        if (sqMeters <= 0) {
            throw new IllegalStateException("Property '" + id + "' must have a positive area.");
        }
        this.published = true;
        System.out.println("[Property] '" + id + "' published — " + address + " at $" + priceUsd);
    }

    // matiz: price-per-sqmeter is an entity computation, not a use-case computation.
    // Calculations that depend only on entity state belong in the entity.
    double pricePerSqMeter() { return priceUsd / sqMeters; }

    String   getId()       { return id; }
    String   getAddress()  { return address; }
    double   getPriceUsd() { return priceUsd; }
    int      getSqMeters() { return sqMeters; }
    boolean  isPublished() { return published; }
}

class ViewingRequest {
    private final String id;
    private final String propertyId;
    private final String buyerEmail;
    private       String status = "pending"; // pending → confirmed → completed

    ViewingRequest(String id, String propertyId, String buyerEmail) {
        this.id         = id;
        this.propertyId = propertyId;
        this.buyerEmail = buyerEmail;
    }

    // Business rule: only a pending request can be confirmed
    void confirm() {
        if (!status.equals("pending")) {
            throw new IllegalStateException("Viewing '" + id + "' is already '" + status + "'.");
        }
        this.status = "confirmed";
        System.out.println("[ViewingRequest] '" + id + "' confirmed for '" + buyerEmail + "'");
    }

    String getId()         { return id; }
    String getPropertyId() { return propertyId; }
    String getBuyerEmail() { return buyerEmail; }
    String getStatus()     { return status; }
}

// ─────────────────────────────────────────────
// RING 2: USE CASES — application-specific rules
// ─────────────────────────────────────────────

// Boundary data structures
record PublishPropertyInput(String propertyId) {}
record PublishPropertyOutput(String propertyId, String address, double pricePerSqm, boolean published) {}

record RequestViewingInput(String propertyId, String buyerEmail) {}
record RequestViewingOutput(String viewingId, String propertyAddress, String buyerEmail, String status) {}

// Output ports (implemented by Presenters in ring 3)
interface PublishPropertyOutputPort {
    void presentSuccess(PublishPropertyOutput output);
    void presentError(String message);
}

interface RequestViewingOutputPort {
    void presentSuccess(RequestViewingOutput output);
    void presentError(String message);
}

// Input ports (called by Controllers in ring 3)
interface PublishPropertyInputPort {
    void execute(PublishPropertyInput input);
}

interface RequestViewingInputPort {
    void execute(RequestViewingInput input);
}

// Gateways (defined in ring 2, implemented in ring 3/4)
interface PropertyGateway {
    Optional<Property> findById(String id);
    void save(Property property);
}

interface ViewingGateway {
    String nextId();
    void save(ViewingRequest viewing);
}

interface ViewingNotifier {
    void sendConfirmation(String buyerEmail, String propertyAddress, String viewingId);
}

// Interactors
class PublishPropertyInteractor implements PublishPropertyInputPort {
    private final PropertyGateway          properties;
    private final PublishPropertyOutputPort presenter;

    PublishPropertyInteractor(PropertyGateway properties, PublishPropertyOutputPort presenter) {
        this.properties = properties;
        this.presenter  = presenter;
    }

    @Override
    public void execute(PublishPropertyInput input) {
        System.out.println("[PublishPropertyInteractor] Publishing property '" + input.propertyId() + "'");

        Optional<Property> opt = properties.findById(input.propertyId());
        if (opt.isEmpty()) {
            presenter.presentError("Property '" + input.propertyId() + "' not found.");
            return;
        }

        Property property = opt.get();
        try {
            property.publish(); // entity enforces its own invariants
        } catch (IllegalStateException e) {
            presenter.presentError(e.getMessage());
            return;
        }

        properties.save(property);
        System.out.println("[PublishPropertyInteractor] Pushing output to presenter");

        // matiz: the interactor calls presenter.presentSuccess() — it never returns a value.
        // The controller will read the view model from the presenter after execute() returns.
        presenter.presentSuccess(new PublishPropertyOutput(
            property.getId(),
            property.getAddress(),
            property.pricePerSqMeter(),
            property.isPublished()
        ));
    }
}

class RequestViewingInteractor implements RequestViewingInputPort {
    private final PropertyGateway       properties;
    private final ViewingGateway        viewings;
    private final ViewingNotifier       notifier;
    private final RequestViewingOutputPort presenter;

    RequestViewingInteractor(
        PropertyGateway properties,
        ViewingGateway viewing,
        ViewingNotifier notifier,
        RequestViewingOutputPort presenter
    ) {
        this.properties = properties;
        this.viewings   = viewing;
        this.notifier   = notifier;
        this.presenter  = presenter;
    }

    @Override
    public void execute(RequestViewingInput input) {
        System.out.println("[RequestViewingInteractor] Viewing request for '"
            + input.propertyId() + "' by '" + input.buyerEmail() + "'");

        Optional<Property> opt = properties.findById(input.propertyId());
        if (opt.isEmpty()) {
            presenter.presentError("Property '" + input.propertyId() + "' not found.");
            return;
        }

        Property property = opt.get();
        if (!property.isPublished()) {
            presenter.presentError("Property '" + input.propertyId() + "' is not published.");
            return;
        }

        ViewingRequest viewing = new ViewingRequest(
            viewings.nextId(), input.propertyId(), input.buyerEmail()
        );
        viewing.confirm();
        viewings.save(viewing);

        notifier.sendConfirmation(input.buyerEmail(), property.getAddress(), viewing.getId());

        presenter.presentSuccess(new RequestViewingOutput(
            viewing.getId(), property.getAddress(), viewing.getBuyerEmail(), viewing.getStatus()
        ));
    }
}

// ─────────────────────────────────────────────
// RING 3: INTERFACE ADAPTERS — Gateways, Presenters, Controllers
// ─────────────────────────────────────────────

class InMemoryPropertyGateway implements PropertyGateway {
    private final Map<String, Property> store = new HashMap<>();

    void seed(Property p) { store.put(p.getId(), p); }

    @Override
    public Optional<Property> findById(String id) { return Optional.ofNullable(store.get(id)); }

    @Override
    public void save(Property p) {
        System.out.println("[InMemoryPropertyGateway] Saved '" + p.getId()
            + "' — published: " + p.isPublished());
        store.put(p.getId(), p);
    }
}

class InMemoryViewingGateway implements ViewingGateway {
    private final Map<String, ViewingRequest> store = new HashMap<>();
    private int seq = 1;

    @Override
    public String nextId() { return "view-" + seq++; }

    @Override
    public void save(ViewingRequest v) {
        System.out.println("[InMemoryViewingGateway] Saved viewing '" + v.getId()
            + "' — status: " + v.getStatus());
        store.put(v.getId(), v);
    }
}

class EmailViewingNotifier implements ViewingNotifier {
    @Override
    public void sendConfirmation(String buyerEmail, String address, String viewingId) {
        System.out.println("[EmailViewingNotifier] Email → '" + buyerEmail
            + "': Viewing '" + viewingId + "' confirmed at " + address);
    }
}

class JsonPublishPresenter implements PublishPropertyOutputPort {
    private Map<String, Object> viewModel;

    @Override
    public void presentSuccess(PublishPropertyOutput output) {
        viewModel = Map.of(
            "property_id",      output.propertyId(),
            "address",          output.address(),
            "price_per_sqm",    String.format("%.2f", output.pricePerSqm()),
            "published",        output.published()
        );
        System.out.println("[JsonPublishPresenter] ViewModel: " + viewModel);
    }

    @Override
    public void presentError(String message) {
        viewModel = Map.of("error", message);
        System.out.println("[JsonPublishPresenter] Error ViewModel: " + viewModel);
    }

    Map<String, Object> getViewModel() { return viewModel; }
}

class JsonViewingPresenter implements RequestViewingOutputPort {
    private Map<String, Object> viewModel;

    @Override
    public void presentSuccess(RequestViewingOutput output) {
        viewModel = Map.of(
            "viewing_id", output.viewingId(),
            "property",   output.propertyAddress(),
            "buyer",      output.buyerEmail(),
            "status",     output.status()
        );
        System.out.println("[JsonViewingPresenter] ViewModel: " + viewModel);
    }

    @Override
    public void presentError(String message) {
        viewModel = Map.of("error", message);
        System.out.println("[JsonViewingPresenter] Error ViewModel: " + viewModel);
    }
}

class PropertyController {
    private final PublishPropertyInputPort publishPort;
    private final JsonPublishPresenter     publishPresenter;

    PropertyController(PublishPropertyInputPort port, JsonPublishPresenter presenter) {
        this.publishPort      = port;
        this.publishPresenter = presenter;
    }

    void postPublish(String propertyId) {
        System.out.println("[PropertyController] POST /properties/" + propertyId + "/publish");
        publishPort.execute(new PublishPropertyInput(propertyId));
        System.out.println("[PropertyController] 200 OK → " + publishPresenter.getViewModel());
    }
}

class ViewingController {
    private final RequestViewingInputPort viewingPort;
    private final JsonViewingPresenter    viewingPresenter;

    ViewingController(RequestViewingInputPort port, JsonViewingPresenter presenter) {
        this.viewingPort      = port;
        this.viewingPresenter = presenter;
    }

    void postViewing(String propertyId, String buyerEmail) {
        System.out.println("[ViewingController] POST /viewings  property='" + propertyId + "'");
        viewingPort.execute(new RequestViewingInput(propertyId, buyerEmail));
    }
}

// ─────────────────────────────────────────────
// RING 4: FRAMEWORKS & DRIVERS — Composition Root
// ─────────────────────────────────────────────

class PropertyMain {
    public static void main(String[] args) {
        System.out.println("=== Clean Architecture — Real Estate Platform (Java) ===\n");

        InMemoryPropertyGateway propertyGateway = new InMemoryPropertyGateway();
        InMemoryViewingGateway  viewingGateway  = new InMemoryViewingGateway();
        EmailViewingNotifier    notifier        = new EmailViewingNotifier();

        propertyGateway.seed(new Property("prop-001", "Calle Gran Vía 45, Madrid", 450000, 90));
        propertyGateway.seed(new Property("prop-002", "Passeig de Gràcia 88, Barcelona", 0, 120));

        JsonPublishPresenter publishPresenter = new JsonPublishPresenter();
        JsonViewingPresenter viewingPresenter = new JsonViewingPresenter();

        PublishPropertyInteractor publishInteractor = new PublishPropertyInteractor(
            propertyGateway, publishPresenter
        );
        RequestViewingInteractor viewingInteractor = new RequestViewingInteractor(
            propertyGateway, viewingGateway, notifier, viewingPresenter
        );

        PropertyController propertyController = new PropertyController(publishInteractor, publishPresenter);
        ViewingController  viewingController  = new ViewingController(viewingInteractor, viewingPresenter);

        System.out.println("--- Agent publishes a valid property ---");
        propertyController.postPublish("prop-001");

        System.out.println("\n--- Agent tries to publish a property with invalid price (entity rule) ---");
        propertyController.postPublish("prop-002");

        System.out.println("\n--- Buyer requests a viewing of the published property ---");
        viewingController.postViewing("prop-001", "buyer@email.com");

        System.out.println("\n--- Buyer tries to view an unpublished property ---");
        viewingController.postViewing("prop-002", "otherbuy@email.com");
    }
}
