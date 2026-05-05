import java.util.*;

/**
 * Scenario: E-commerce Shopping Cart
 *
 * Users browse a product catalog, add items to a cart, and checkout.
 * The controller validates input before reaching the model.
 */

// ─── Main ─────────────────────────────────────────────────────────────────────
// matiz: placing the entry-point class first allows running without pre-compiling:
//   java example.java
// Java 11+ executes single-file programs by launching the first class it finds.
// If main() is not in the first class, the runtime fails with "can't find main()".
class example {
    public static void main(String[] args) {
        System.out.println("=== E-commerce Shop — MVC Pattern ===\n");

        ProductCatalog catalog    = new ProductCatalog();
        Cart           cart       = new Cart();
        ShopView       view       = new ShopView();
        ShopController controller = new ShopController(catalog, cart, view);

        catalog.add(new Product(1, "Mechanical Keyboard", 129.99, "peripherals", 10));
        catalog.add(new Product(2, "USB-C Hub",            45.00, "peripherals",  5));
        catalog.add(new Product(3, "4K Monitor",          349.00, "displays",     3));
        catalog.add(new Product(4, "Laptop Stand",         29.99, "accessories",  8));

        controller.browseCatalog();
        controller.addToCart(1, 2);   // 2x keyboard
        controller.addToCart(3, 1);   // 1x monitor
        controller.addToCart(99, 1);  // product not found
        controller.addToCart(2, 10);  // exceeds available stock
        controller.viewCart();
        controller.checkout();
        controller.viewCart();        // cart should be empty after checkout
    }
}

// ─── Model ────────────────────────────────────────────────────────────────────

record Product(int id, String name, double price, String category, int stock) {
    public boolean hasStock(int qty) {
        return stock >= qty;
    }
}

// matiz: CartItem is a separate concept from Product.
// It captures "a product + quantity in the context of a specific order".
// Keeping them separate means a Product can exist without being in a cart,
// and a CartItem can carry order-specific data (e.g. applied discount) without
// polluting the Product model.
record CartItem(Product product, int quantity) {
    public double subtotal() {
        return product.price() * quantity;
    }
}

class ProductCatalog {
    private final Map<Integer, Product> products = new LinkedHashMap<>();

    public void add(Product p)                { products.put(p.id(), p); }
    public Optional<Product> find(int id)     { return Optional.ofNullable(products.get(id)); }
    public List<Product> findAll()            { return new ArrayList<>(products.values()); }
}

class Cart {
    private final List<CartItem> items = new ArrayList<>();

    public void add(Product product, int quantity) { items.add(new CartItem(product, quantity)); }
    public List<CartItem> getItems()               { return Collections.unmodifiableList(items); }
    public boolean isEmpty()                       { return items.isEmpty(); }
    public double total()                          { return items.stream().mapToDouble(CartItem::subtotal).sum(); }
    public void clear()                            { items.clear(); }
}

// ─── View ─────────────────────────────────────────────────────────────────────

class ShopView {
    public void renderCatalog(List<Product> products) {
        System.out.println("\n┌─ Product Catalog ──────────────────────────────────────");
        for (Product p : products) {
            System.out.printf("│  #%-3d %-24s $%7.2f  [%-12s] stock: %d%n",
                    p.id(), p.name(), p.price(), p.category(), p.stock());
        }
        System.out.println("└────────────────────────────────────────────────────────");
    }

    public void renderCart(List<CartItem> items, double total) {
        System.out.println("\n┌─ Shopping Cart ─────────────────────────────────────────");
        if (items.isEmpty()) {
            System.out.println("│  (empty)");
        }
        for (CartItem item : items) {
            System.out.printf("│  %-24s x%d  =  $%.2f%n",
                    item.product().name(), item.quantity(), item.subtotal());
        }
        System.out.printf("│  TOTAL: $%.2f%n", total);
        System.out.println("└─────────────────────────────────────────────────────────");
    }

    public void renderReceipt(List<CartItem> items, double total) {
        System.out.println("\n[ShopView] Order placed successfully!");
        System.out.printf("[ShopView] %d item(s) — Total charged: $%.2f%n", items.size(), total);
    }

    public void renderAddedToCart(Product product, int qty) {
        System.out.printf("[ShopView] Added: %s x%d%n", product.name(), qty);
    }

    public void renderError(String message) {
        System.out.println("[ShopView] ERROR: " + message);
    }
}

// ─── Controller ───────────────────────────────────────────────────────────────
// The controller handles user actions. It validates input, delegates business
// rules to the model, and chooses which view method to call.
// It contains no formatting and no business logic of its own.

class ShopController {
    private final ProductCatalog catalog;
    private final Cart           cart;
    private final ShopView       view;

    public ShopController(ProductCatalog catalog, Cart cart, ShopView view) {
        this.catalog = catalog;
        this.cart    = cart;
        this.view    = view;
    }

    public void browseCatalog() {
        System.out.println("[ShopController] Handling: browse catalog");
        view.renderCatalog(catalog.findAll());
    }

    public void addToCart(int productId, int quantity) {
        System.out.printf("[ShopController] Handling: add product #%d x%d%n", productId, quantity);
        Optional<Product> found = catalog.find(productId);
        if (found.isEmpty()) {
            view.renderError("Product #" + productId + " not found.");
            return;
        }
        Product product = found.get();
        if (!product.hasStock(quantity)) {
            view.renderError(String.format(
                    "Not enough stock for '%s' (requested: %d, available: %d)",
                    product.name(), quantity, product.stock()));
            return;
        }
        cart.add(product, quantity);
        view.renderAddedToCart(product, quantity);
    }

    public void viewCart() {
        System.out.println("[ShopController] Handling: view cart");
        view.renderCart(cart.getItems(), cart.total());
    }

    public void checkout() {
        System.out.println("[ShopController] Handling: checkout");
        if (cart.isEmpty()) {
            view.renderError("Cannot checkout with an empty cart.");
            return;
        }
        List<CartItem> snapshot = new ArrayList<>(cart.getItems());
        double total = cart.total();
        cart.clear();
        view.renderReceipt(snapshot, total);
    }
}
