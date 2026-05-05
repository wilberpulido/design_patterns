import java.util.LinkedHashMap;
import java.util.Map;

// The target interface — the modern reporting contract our application depends on.
// Accepts flexible Object values; returns a String in whatever format the implementation chooses.
interface ReportGenerator {
    String generate(String title, Map<String, Object> data);
}

// The adaptee — a legacy XML report engine shared across multiple internal systems.
// We cannot change it: other teams depend on its current API.
// Incompatibilities: only handles String values, reversed parameter order, XML-specific return type.
class XmlReportEngine {
    public String buildXmlReport(String reportTitle, Map<String, String> stringFields) {
        System.out.println("[XmlReportEngine] Building XML report: \"" + reportTitle + "\"");
        StringBuilder xml = new StringBuilder("<?xml version=\"1.0\"?>\n")
            .append("<report title=\"").append(reportTitle).append("\">\n");

        for (Map.Entry<String, String> entry : stringFields.entrySet()) {
            xml.append("  <field name=\"").append(entry.getKey()).append("\">")
               .append(entry.getValue())
               .append("</field>\n");
        }
        xml.append("</report>");
        return xml.toString();
    }
}

// The Adapter — implements the modern ReportGenerator interface
// and delegates to XmlReportEngine after translating the data format.
class XmlReportAdapter implements ReportGenerator {
    private final XmlReportEngine xmlEngine;

    public XmlReportAdapter(XmlReportEngine xmlEngine) {
        this.xmlEngine = xmlEngine;
    }

    @Override
    public String generate(String title, Map<String, Object> data) {
        System.out.println("[XmlReportAdapter] generate() called — translating Object values to String...");

        // matiz: The adapter's job is not always just renaming methods.
        // Here it performs type coercion (Object → String) — a data shape translation.
        // Adapters are the right place for this: they isolate the transformation
        // so neither the client nor the adaptee needs to know about the other's type system.
        Map<String, String> stringFields = new LinkedHashMap<>();
        for (Map.Entry<String, Object> entry : data.entrySet()) {
            stringFields.put(entry.getKey(), String.valueOf(entry.getValue()));
        }

        String xml = xmlEngine.buildXmlReport(title, stringFields);
        System.out.println("[XmlReportAdapter] Translation complete. Returning XML report.");
        return xml;
    }
}

// Client code — uses ReportGenerator exclusively.
// It can produce XML, JSON, or PDF reports by swapping the adapter, with no changes here.
class AuditReportService {
    private final ReportGenerator reporter;

    public AuditReportService(ReportGenerator reporter) {
        this.reporter = reporter;
    }

    public void generateUserAuditReport(String userId, int actionsCount, boolean flagged) {
        System.out.println("\n[AuditReportService] Generating audit report for user: " + userId);

        Map<String, Object> data = new LinkedHashMap<>();
        data.put("user_id", userId);
        data.put("actions_count", actionsCount);
        data.put("flagged", flagged);
        data.put("generated_at", "2024-06-01T10:30:00Z");

        String report = reporter.generate("User Audit Report", data);

        System.out.println("[AuditReportService] Report generated:\n" + report);
    }
}

class Main {
    public static void main(String[] args) {
        XmlReportEngine legacyEngine = new XmlReportEngine();
        ReportGenerator adapter = new XmlReportAdapter(legacyEngine);
        AuditReportService auditService = new AuditReportService(adapter);

        auditService.generateUserAuditReport("usr_9182", 47, true);
    }
}
