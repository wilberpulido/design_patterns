/**
 * DECORATOR PATTERN - Java
 * Scenario: Cloud file storage service — decorators add virus scanning,
 * compression, and upload metrics on top of a core S3-compatible storage.
 *
 * To compile and run:
 *   javac example.java && java example
 */
public class example {

    interface FileStorage {
        void upload(String filename, byte[] content);
        byte[] download(String filename);
    }

    // Core storage: persists files in memory (simulates S3, GCS, Azure Blob, etc.)
    // It has no awareness of compression, scanning, or metrics — single responsibility.
    static class S3Storage implements FileStorage {
        private final java.util.Map<String, byte[]> store = new java.util.HashMap<>();

        @Override
        public void upload(String filename, byte[] content) {
            store.put(filename, content);
            System.out.println("[S3Storage] Stored '" + filename + "' (" + content.length + " bytes)");
        }

        @Override
        public byte[] download(String filename) {
            byte[] content = store.getOrDefault(filename, new byte[0]);
            System.out.println("[S3Storage] Retrieved '" + filename + "' (" + content.length + " bytes)");
            return content;
        }
    }

    // Base decorator: implements FileStorage and delegates to the wrapped instance.
    // This is the key: it IS a FileStorage so it can replace the original anywhere.
    static abstract class StorageDecorator implements FileStorage {
        protected final FileStorage wrapped;
        StorageDecorator(FileStorage wrapped) { this.wrapped = wrapped; }
    }

    static class VirusScanDecorator extends StorageDecorator {
        VirusScanDecorator(FileStorage wrapped) { super(wrapped); }

        @Override
        public void upload(String filename, byte[] content) {
            System.out.println("[VirusScanDecorator] Scanning '" + filename + "' for malware...");
            // Simulated scan — production would call ClamAV or a cloud scanning API
            if (filename.endsWith(".exe") || filename.contains("malware")) {
                System.out.println("[VirusScanDecorator] Threat detected — upload blocked.");
                return; // stops the chain — nothing below runs
            }
            System.out.println("[VirusScanDecorator] File is clean — proceeding.");
            wrapped.upload(filename, content);
        }

        @Override
        public byte[] download(String filename) {
            // No scanning needed on download — the file was already verified on upload
            return wrapped.download(filename);
        }
    }

    static class CompressionDecorator extends StorageDecorator {
        CompressionDecorator(FileStorage wrapped) { super(wrapped); }

        @Override
        public void upload(String filename, byte[] content) {
            System.out.println("[CompressionDecorator] Compressing '" + filename + "'...");
            byte[] compressed = compress(content);
            System.out.println("[CompressionDecorator] " + content.length + " → " + compressed.length + " bytes (simulated)");
            wrapped.upload(filename, compressed);
        }

        @Override
        public byte[] download(String filename) {
            byte[] compressed = wrapped.download(filename);
            System.out.println("[CompressionDecorator] Decompressing file...");
            return decompress(compressed);
        }

        private byte[] compress(byte[] data) {
            // Simulated — in production use java.util.zip.GZIPOutputStream
            return java.util.Arrays.copyOf(data, Math.max(1, data.length / 2));
        }

        private byte[] decompress(byte[] data) {
            byte[] result = new byte[data.length * 2];
            System.arraycopy(data, 0, result, 0, data.length);
            return result;
        }
    }

    static class MetricsDecorator extends StorageDecorator {
        private int uploadCount = 0;
        private int downloadCount = 0;

        MetricsDecorator(FileStorage wrapped) { super(wrapped); }

        @Override
        public void upload(String filename, byte[] content) {
            long start = System.currentTimeMillis();
            wrapped.upload(filename, content);
            uploadCount++;
            System.out.println("[MetricsDecorator] Upload #" + uploadCount + " done in " + (System.currentTimeMillis() - start) + "ms");
        }

        @Override
        public byte[] download(String filename) {
            long start = System.currentTimeMillis();
            byte[] result = wrapped.download(filename);
            downloadCount++;
            System.out.println("[MetricsDecorator] Download #" + downloadCount + " done in " + (System.currentTimeMillis() - start) + "ms");
            return result;
        }

        // matiz: decorators can expose additional methods not in the base interface.
        // This breaks the fully transparent substitution, but is acceptable when this
        // decorator is only accessed directly at the composition root (main/bootstrap),
        // never passed around as a generic FileStorage.
        public void printStats() {
            System.out.println("[MetricsDecorator] Stats — uploads: " + uploadCount + ", downloads: " + downloadCount);
        }
    }

    public static void main(String[] args) {
        System.out.println("=== Decorator Pattern Demo — Cloud File Storage (Java) ===\n");

        MetricsDecorator storage = new MetricsDecorator(
            new VirusScanDecorator(
                new CompressionDecorator(
                    new S3Storage()
                )
            )
        );

        byte[] report  = "Q4 Financial Report — CONFIDENTIAL".getBytes();
        byte[] invoice = "Invoice #4521 — $15,000".getBytes();

        System.out.println("-- Uploading clean files --");
        storage.upload("q4_report.pdf", report);
        System.out.println();
        storage.upload("invoice_4521.pdf", invoice);

        System.out.println("\n-- Attempting to upload a flagged file --");
        storage.upload("malware_payload.exe", new byte[]{0x4d, 0x5a});

        System.out.println("\n-- Downloading a file --");
        storage.download("q4_report.pdf");

        System.out.println();
        storage.printStats();
    }
}
