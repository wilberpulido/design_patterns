"""
DECORATOR PATTERN - Python
Scenario: Data Export Pipeline — a CSV exporter wrapped with compression,
encryption, and audit logging for a compliance-heavy SaaS application.
"""

from abc import ABC, abstractmethod
import time


class DataExporter(ABC):
    @abstractmethod
    def export(self, data: list[dict]) -> bytes:
        pass


# The core exporter — converts records to CSV bytes and nothing else.
class CsvExporter(DataExporter):
    def export(self, data: list[dict]) -> bytes:
        print("[CsvExporter] Serializing records to CSV format...")
        lines = [",".join(data[0].keys())]
        for row in data:
            lines.append(",".join(str(v) for v in row.values()))
        result = "\n".join(lines).encode()
        print(f"[CsvExporter] {len(data)} records serialized ({len(result)} bytes)")
        return result


# Base decorator: wraps another DataExporter.
# All decorators extend this to share the same constructor.
class ExporterDecorator(DataExporter):
    def __init__(self, wrapped: DataExporter):
        self._wrapped = wrapped


class CompressionDecorator(ExporterDecorator):
    def export(self, data: list[dict]) -> bytes:
        raw = self._wrapped.export(data)
        print(f"[CompressionDecorator] Compressing {len(raw)} bytes...")
        # Simulated compression — in production use gzip or zstd
        compressed = raw[::2]  # placeholder: take every other byte to shrink size
        print(f"[CompressionDecorator] {len(raw)} → {len(compressed)} bytes (simulated)")
        return compressed


class EncryptionDecorator(ExporterDecorator):
    def __init__(self, wrapped: DataExporter, key: str):
        super().__init__(wrapped)
        self._key = key

    def export(self, data: list[dict]) -> bytes:
        payload = self._wrapped.export(data)
        print(f"[EncryptionDecorator] Encrypting payload with key '{self._key}'...")
        # Simulated encryption — in production use cryptography.fernet or AES-GCM
        encrypted = bytes(b ^ 0xFF for b in payload)
        print(f"[EncryptionDecorator] Payload encrypted ({len(encrypted)} bytes)")
        return encrypted


class AuditLogDecorator(ExporterDecorator):
    def export(self, data: list[dict]) -> bytes:
        print(f"[AuditLogDecorator] Export started at {time.strftime('%H:%M:%S')}")
        result = self._wrapped.export(data)
        print(f"[AuditLogDecorator] Export completed — {len(data)} records exported, audit trail saved.")
        return result


# matiz: Python has built-in @decorator syntax for wrapping functions,
# but class-based decorators are the right choice when the decorated object
# has multiple methods or carries state across calls — they preserve the full
# interface instead of wrapping a single function call.


records = [
    {"id": 1, "email": "alice@corp.com", "plan": "enterprise"},
    {"id": 2, "email": "bob@corp.com",   "plan": "starter"},
    {"id": 3, "email": "carol@corp.com", "plan": "enterprise"},
]

# Build the pipeline: AuditLog → Encryption → Compression → Csv
# Audit wraps everything so it captures the total time including encryption.
pipeline = AuditLogDecorator(
    EncryptionDecorator(
        CompressionDecorator(
            CsvExporter()
        ),
        key="aes-secret-key-42"
    )
)

print("=== Decorator Pattern Demo — Data Export Pipeline (Python) ===\n")
output = pipeline.export(records)
print(f"\nFinal payload: {len(output)} bytes (compressed + encrypted, ready to send)")
