"""
Hexagonal Architecture — Telemedicine Consultation Platform (Python)

The application core (domain + use cases) sits at the centre.
External actors and infrastructure connect to it through ports (interfaces).
The core never imports from HTTP frameworks, ORMs, or notification libraries.
"""

from __future__ import annotations
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from typing import Optional
import uuid


# ─────────────────────────────────────────────
# DOMAIN — pure business objects
# ─────────────────────────────────────────────

@dataclass
class Consultation:
    id:           str
    patient_id:   str
    doctor_id:    str
    scheduled_at: datetime
    status:       str = "scheduled"  # scheduled → started → completed | cancelled

    def start(self) -> None:
        """Domain rule: only a scheduled consultation can be started."""
        if self.status != "scheduled":
            raise ValueError(f"Consultation '{self.id}' is '{self.status}', cannot start.")
        self.status = "started"
        print(f"[Consultation] '{self.id}' status changed to 'started'")

    def complete(self) -> None:
        if self.status != "started":
            raise ValueError(f"Consultation '{self.id}' must be started before completing.")
        self.status = "completed"
        print(f"[Consultation] '{self.id}' status changed to 'completed'")

    def cancel(self) -> None:
        if self.status in ("completed", "cancelled"):
            raise ValueError(f"Cannot cancel a '{self.status}' consultation.")
        self.status = "cancelled"
        print(f"[Consultation] '{self.id}' cancelled")


# ─────────────────────────────────────────────
# PORTS — interfaces defined and owned by the application core
#
# Primary ports:  how external actors DRIVE the app   (use-case contracts)
# Secondary ports: how the app DRIVES external systems (repository/notifier)
# ─────────────────────────────────────────────

class BookConsultationPort(ABC):
    """Primary port: the use-case interface external actors (HTTP, CLI, tests) call."""
    @abstractmethod
    def book(self, patient_id: str, doctor_id: str, scheduled_at: datetime) -> dict: ...


class CancelConsultationPort(ABC):
    """Primary port."""
    @abstractmethod
    def cancel(self, consultation_id: str) -> None: ...


class ConsultationRepositoryPort(ABC):
    """Secondary port: the core defines what it needs from storage."""
    @abstractmethod
    def save(self, c: Consultation) -> None: ...
    @abstractmethod
    def find_by_id(self, id: str) -> Optional[Consultation]: ...


class PatientNotifierPort(ABC):
    """Secondary port: the core defines what it needs from the notification system."""
    @abstractmethod
    def notify_booking_confirmed(self, patient_id: str, consultation_id: str, scheduled_at: datetime) -> None: ...
    @abstractmethod
    def notify_cancelled(self, patient_id: str, consultation_id: str) -> None: ...


# ─────────────────────────────────────────────
# APPLICATION SERVICE — implements primary ports, depends only on secondary ports
# ─────────────────────────────────────────────

class ConsultationService(BookConsultationPort, CancelConsultationPort):
    # matiz: the service receives PORT interfaces, never concrete adapters.
    # This is Dependency Inversion at the architectural level: the core dictates the contract,
    # adapters conform to it.
    def __init__(
        self,
        repository: ConsultationRepositoryPort,
        notifier:   PatientNotifierPort,
    ) -> None:
        self._repo     = repository
        self._notifier = notifier

    def book(self, patient_id: str, doctor_id: str, scheduled_at: datetime) -> dict:
        consultation = Consultation(
            id           = str(uuid.uuid4())[:8],
            patient_id   = patient_id,
            doctor_id    = doctor_id,
            scheduled_at = scheduled_at,
        )
        print(f"[ConsultationService] Booking '{consultation.id}' — "
              f"patient '{patient_id}' + doctor '{doctor_id}'")

        self._repo.save(consultation)
        self._notifier.notify_booking_confirmed(patient_id, consultation.id, scheduled_at)

        return {"id": consultation.id, "status": consultation.status, "doctor": doctor_id}

    def cancel(self, consultation_id: str) -> None:
        c = self._repo.find_by_id(consultation_id)
        if c is None:
            raise ValueError(f"Consultation '{consultation_id}' not found.")

        c.cancel()
        self._repo.save(c)
        self._notifier.notify_cancelled(c.patient_id, consultation_id)
        print(f"[ConsultationService] Cancellation processed for '{consultation_id}'")


# ─────────────────────────────────────────────
# SECONDARY ADAPTERS — driven side, implement secondary ports
# ─────────────────────────────────────────────

class InMemoryConsultationRepository(ConsultationRepositoryPort):
    def __init__(self) -> None:
        self._store: dict[str, Consultation] = {}

    def save(self, c: Consultation) -> None:
        print(f"[InMemoryConsultationRepository] Persisted '{c.id}' — status: {c.status}")
        self._store[c.id] = c

    def find_by_id(self, id: str) -> Optional[Consultation]:
        return self._store.get(id)

    def all(self) -> list[Consultation]:
        return list(self._store.values())


class SmsPatientNotifier(PatientNotifierPort):
    def notify_booking_confirmed(
        self, patient_id: str, consultation_id: str, scheduled_at: datetime
    ) -> None:
        formatted = scheduled_at.strftime("%b %d at %H:%M")
        print(f"[SmsPatientNotifier] SMS → '{patient_id}': "
              f"Consultation '{consultation_id}' confirmed for {formatted}")

    def notify_cancelled(self, patient_id: str, consultation_id: str) -> None:
        print(f"[SmsPatientNotifier] SMS → '{patient_id}': "
              f"Consultation '{consultation_id}' has been cancelled")


# ─────────────────────────────────────────────
# PRIMARY ADAPTERS — driving side, translate external input into use-case calls
# ─────────────────────────────────────────────

class ConsultationHttpController:
    """
    A primary adapter. Knows about HTTP conventions (paths, body format)
    but delegates ALL domain decisions to use-case ports.
    """
    # matiz: depends on PRIMARY PORT ABCs, not on ConsultationService.
    # A CLI adapter or a message-queue consumer would look exactly the same here —
    # the core is completely unaware of which adapter is driving it.
    def __init__(
        self,
        book_port:   BookConsultationPort,
        cancel_port: CancelConsultationPort,
    ) -> None:
        self._book   = book_port
        self._cancel = cancel_port

    def post_consultation(self, body: dict) -> None:
        pid = body["patient_id"]
        print(f"[ConsultationHttpController] POST /consultations  patient='{pid}'")
        result = self._book.book(pid, body["doctor_id"], body["scheduled_at"])
        print(f"[ConsultationHttpController] 201 Created → {result}")
        return result

    def delete_consultation(self, consultation_id: str) -> None:
        print(f"[ConsultationHttpController] DELETE /consultations/{consultation_id}")
        self._cancel.cancel(consultation_id)
        print(f"[ConsultationHttpController] 204 No Content → Consultation cancelled")


# ─────────────────────────────────────────────
# COMPOSITION ROOT — the only place that wires ports to concrete adapters
# ─────────────────────────────────────────────

if __name__ == "__main__":
    print("=== Hexagonal Architecture — Telemedicine Platform (Python) ===\n")

    repository  = InMemoryConsultationRepository()
    notifier    = SmsPatientNotifier()
    service     = ConsultationService(repository, notifier)
    controller  = ConsultationHttpController(service, service)

    print("--- Patient books a video consultation ---")
    result = controller.post_consultation({
        "patient_id":   "patient-77",
        "doctor_id":    "dr-chen",
        "scheduled_at": datetime.now() + timedelta(hours=2),
    })

    print("\n--- Patient cancels before the appointment ---")
    controller.delete_consultation(result["id"])

    print("\n--- Second patient books successfully ---")
    controller.post_consultation({
        "patient_id":   "patient-33",
        "doctor_id":    "dr-patel",
        "scheduled_at": datetime.now() + timedelta(days=1),
    })
