// Scenario: API authentication — different clients authenticate via different mechanisms.
// The API gateway should not contain if/else chains for each auth method.

// The Strategy interface — every auth method must implement this contract
interface AuthStrategy {
    AuthResult authenticate(HttpRequest request);
    String schemeName();
}

// Supporting types
class HttpRequest {
    public final String path;
    public final String authHeader;
    public final String apiKey;

    HttpRequest(String path, String authHeader, String apiKey) {
        this.path       = path;
        this.authHeader = authHeader;
        this.apiKey     = apiKey;
    }
}

class AuthResult {
    public final boolean success;
    public final String  principalId;
    public final String  reason;

    AuthResult(boolean success, String principalId, String reason) {
        this.success     = success;
        this.principalId = principalId;
        this.reason      = reason;
    }
}

// Concrete Strategy: JWT — validates a signed token from the Authorization header
class JwtAuthStrategy implements AuthStrategy {
    public AuthResult authenticate(HttpRequest request) {
        System.out.println("[JwtAuthStrategy] Verifying JWT signature...");
        if (request.authHeader != null && request.authHeader.startsWith("Bearer ")) {
            System.out.println("[JwtAuthStrategy] Token valid. Extracting claims...");
            return new AuthResult(true, "user_jwt_42", "JWT verified");
        }
        return new AuthResult(false, null, "Missing or malformed Bearer token");
    }

    public String schemeName() { return "JWT"; }
}

// Concrete Strategy: API Key — validates a static key from a header or query param
class ApiKeyAuthStrategy implements AuthStrategy {
    private static final String VALID_KEY = "secret-api-key-123";

    public AuthResult authenticate(HttpRequest request) {
        System.out.println("[ApiKeyAuthStrategy] Looking up API key in registry...");
        if (VALID_KEY.equals(request.apiKey)) {
            System.out.println("[ApiKeyAuthStrategy] API key recognized. Resolving client identity...");
            return new AuthResult(true, "client_service_7", "API key valid");
        }
        return new AuthResult(false, null, "Unknown or revoked API key");
    }

    public String schemeName() { return "API Key"; }
}

// Concrete Strategy: Basic Auth — username:password encoded in the Authorization header
class BasicAuthStrategy implements AuthStrategy {
    public AuthResult authenticate(HttpRequest request) {
        System.out.println("[BasicAuthStrategy] Decoding Basic credentials...");
        if (request.authHeader != null && request.authHeader.startsWith("Basic ")) {
            System.out.println("[BasicAuthStrategy] Credentials valid. Checking account status...");
            return new AuthResult(true, "admin_user", "Basic auth accepted");
        }
        return new AuthResult(false, null, "Basic auth header missing");
    }

    public String schemeName() { return "Basic Auth"; }
}

// The Context — delegates authentication to whatever strategy is injected.
// matiz: Strategy is the pattern to reach for when you want to favor composition
// over inheritance. Without it, you'd create JwtApiGateway, ApiKeyApiGateway, etc.
// With it, you have one ApiGateway that works with any auth method — past and future.
class ApiGateway {
    private AuthStrategy strategy;

    ApiGateway(AuthStrategy strategy) {
        this.strategy = strategy;
    }

    public void setStrategy(AuthStrategy strategy) {
        System.out.println("[ApiGateway] Switching auth scheme to: " + strategy.schemeName());
        this.strategy = strategy;
    }

    public boolean handleRequest(HttpRequest request) {
        System.out.println("\n[ApiGateway] Authenticating request to " + request.path
            + " using " + strategy.schemeName() + "...");

        AuthResult result = strategy.authenticate(request);

        if (result.success) {
            System.out.println("[ApiGateway] Access granted to principal: " + result.principalId);
        } else {
            System.out.println("[ApiGateway] Access denied: " + result.reason);
        }
        return result.success;
    }
}

// Client — routes each incoming request to the gateway with the appropriate strategy
class RequestRouter {
    private final ApiGateway gateway;

    RequestRouter(ApiGateway gateway) {
        this.gateway = gateway;
    }

    public void route(HttpRequest request, AuthStrategy strategy) {
        gateway.setStrategy(strategy);
        boolean allowed = gateway.handleRequest(request);
        System.out.println("[RequestRouter] Request " + (allowed ? "forwarded" : "rejected") + ".\n");
    }
}

class Main {
    public static void main(String[] args) {
        ApiGateway    gateway = new ApiGateway(new JwtAuthStrategy());
        RequestRouter router  = new RequestRouter(gateway);

        // JWT client
        router.route(
            new HttpRequest("/api/orders", "Bearer eyJhbGciOiJIUzI1NiJ9...", null),
            new JwtAuthStrategy()
        );

        // Service-to-service with API key
        router.route(
            new HttpRequest("/api/inventory", null, "secret-api-key-123"),
            new ApiKeyAuthStrategy()
        );

        // Legacy client using Basic Auth
        router.route(
            new HttpRequest("/api/reports", "Basic YWRtaW46cGFzcw==", null),
            new BasicAuthStrategy()
        );
    }
}
