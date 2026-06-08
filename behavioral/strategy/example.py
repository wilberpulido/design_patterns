from abc import ABC, abstractmethod
from typing import Any

# Scenario: report exporter — same data, different output formats.
# Adding a new format (e.g., PDF) should not require modifying the exporter or existing strategies.

# The Strategy interface
class ExportStrategy(ABC):
    @abstractmethod
    def export(self, data: list[dict[str, Any]], filename: str) -> str:
        pass

    @abstractmethod
    def extension(self) -> str:
        pass


# Concrete Strategy: CSV — simple tabular format
class CsvExportStrategy(ExportStrategy):
    def export(self, data: list[dict[str, Any]], filename: str) -> str:
        print(f"[CsvExportStrategy] Serializing {len(data)} rows to CSV...")
        header = ",".join(data[0].keys())
        rows = "\n".join(",".join(str(v) for v in row.values()) for row in data)
        content = f"{header}\n{rows}"
        output = f"{filename}.csv"
        print(f"[CsvExportStrategy] Written to {output}")
        return output

    def extension(self) -> str:
        return "csv"


# Concrete Strategy: JSON — structured, good for APIs and downstream systems
class JsonExportStrategy(ExportStrategy):
    def export(self, data: list[dict[str, Any]], filename: str) -> str:
        import json
        print(f"[JsonExportStrategy] Serializing {len(data)} records to JSON...")
        content = json.dumps(data, indent=2)
        output = f"{filename}.json"
        print(f"[JsonExportStrategy] Written to {output} ({len(content)} bytes)")
        return output

    def extension(self) -> str:
        return "json"


# Concrete Strategy: Markdown — human-readable, useful for documentation or Slack
class MarkdownExportStrategy(ExportStrategy):
    def export(self, data: list[dict[str, Any]], filename: str) -> str:
        print(f"[MarkdownExportStrategy] Rendering {len(data)} rows as Markdown table...")
        headers = list(data[0].keys())
        header_row = "| " + " | ".join(headers) + " |"
        separator  = "| " + " | ".join(["---"] * len(headers)) + " |"
        rows = "\n".join("| " + " | ".join(str(row[h]) for h in headers) + " |" for row in data)
        output = f"{filename}.md"
        print(f"[MarkdownExportStrategy] Written to {output}")
        return output

    def extension(self) -> str:
        return "md"


# The Context — runs whichever export strategy is injected. Knows nothing about formats.
class ReportExporter:
    def __init__(self, strategy: ExportStrategy) -> None:
        self._strategy = strategy

    # matiz: strategies can be composed externally — e.g., export to JSON then compress,
    # or export to CSV then encrypt. The context stays the same; the caller decides
    # whether to chain a Decorator around the strategy output.
    # Strategy defines *how* to export; additional behavior wraps the result, not the strategy.
    def set_strategy(self, strategy: ExportStrategy) -> None:
        print(f"[ReportExporter] Switching export format to: {strategy.extension().upper()}")
        self._strategy = strategy

    def export(self, data: list[dict[str, Any]], filename: str) -> str:
        print(f"\n[ReportExporter] Exporting report '{filename}'...")
        output = self._strategy.export(data, filename)
        print(f"[ReportExporter] Export complete → {output}")
        return output


# Client — a reporting dashboard that lets users choose the export format
class ReportingDashboard:
    def __init__(self, exporter: ReportExporter) -> None:
        self._exporter = exporter

    def download_report(self, format: str) -> None:
        data = [
            {"month": "January",  "revenue": 42000, "orders": 310},
            {"month": "February", "revenue": 38500, "orders": 280},
            {"month": "March",    "revenue": 51200, "orders": 405},
        ]

        strategies = {
            "csv":      CsvExportStrategy(),
            "json":     JsonExportStrategy(),
            "markdown": MarkdownExportStrategy(),
        }

        print(f"[ReportingDashboard] User requested export format: {format.upper()}")
        self._exporter.set_strategy(strategies[format])
        self._exporter.export(data, "quarterly_report")


if __name__ == "__main__":
    exporter  = ReportExporter(CsvExportStrategy())
    dashboard = ReportingDashboard(exporter)

    dashboard.download_report("csv")
    dashboard.download_report("json")
    dashboard.download_report("markdown")
