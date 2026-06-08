"""
Clean Architecture — Patient Intake System (Python)

Robert C. Martin's four concentric circles:
  1. Entities       — enterprise-wide business rules (innermost, most stable)
  2. Use Cases      — application-specific logic (Interactors + boundary data)
  3. Interface Adapters — Controllers, Presenters, Gateways (convert data between rings)
  4. Frameworks & Drivers — outermost (DB drivers, web frameworks, CLI tools)

The Dependency Rule: source code dependencies can ONLY point inward.
"""

from __future__ import annotations
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import date
from typing import Optional
import uuid


# ─────────────────────────────────────────────
# RING 1: ENTITIES — enterprise-wide business rules
# ─────────────────────────────────────────────

@dataclass
class Patient:
    id:         str
    full_name:  str
    date_of_birth: date
    allergies:  list[str] = field(default_factory=list)

    def age(self) -> int:
        today = date.today()
        return today.year - self.date_of_birth.year - (
            (today.month, today.day) < (self.date_of_birth.month, self.date_of_birth.day)
        )

    # Business rule: a minor (<18) cannot be admitted without a guardian flag
    def assert_adult_or_with_guardian(self, has_guardian: bool) -> None:
        if self.age() < 18 and not has_guardian:
            raise ValueError(
                f"Patient '{self.id}' is {self.age()} — a guardian must be present for minors."
            )


@dataclass
class Prescription:
    id:         str
    patient_id: str
    drug:       str
    dosage:     str
    # Business rule: a prescription cannot be issued for a drug in the patient's allergy list
    # (checked by the entity, not the use case — it's an enterprise-wide rule)

    def assert_not_allergic(self, allergies: list[str]) -> None:
        if self.drug.lower() in [a.lower() for a in allergies]:
            raise ValueError(
                f"Drug '{self.drug}' is in the patient's allergy list. Prescription rejected."
            )


# ─────────────────────────────────────────────
# RING 2: USE CASES — application-specific business rules
# ─────────────────────────────────────────────

# Boundary data structures (InputData / OutputData)
@dataclass(frozen=True)
class RegisterPatientInput:
    full_name:      str
    date_of_birth:  date
    allergies:      list[str]

@dataclass(frozen=True)
class RegisterPatientOutput:
    patient_id:  str
    full_name:   str
    age:         int

@dataclass(frozen=True)
class IssuePrescriptionInput:
    patient_id:   str
    drug:         str
    dosage:       str

@dataclass(frozen=True)
class IssuePrescriptionOutput:
    prescription_id: str
    patient_name:    str
    drug:            str
    dosage:          str


# Output ports — implemented by Presenters (ring 3); called by use cases
class RegisterPatientOutputPort(ABC):
    @abstractmethod
    def present_registered(self, output: RegisterPatientOutput) -> None: ...
    @abstractmethod
    def present_error(self, message: str) -> None: ...

class IssuePrescriptionOutputPort(ABC):
    @abstractmethod
    def present_issued(self, output: IssuePrescriptionOutput) -> None: ...
    @abstractmethod
    def present_error(self, message: str) -> None: ...

# Input ports — implemented by Interactors; called by Controllers
class RegisterPatientInputPort(ABC):
    @abstractmethod
    def execute(self, input_data: RegisterPatientInput) -> None: ...

class IssuePrescriptionInputPort(ABC):
    @abstractmethod
    def execute(self, input_data: IssuePrescriptionInput) -> None: ...

# Gateways — defined in ring 2, implemented in ring 3/4
class PatientGateway(ABC):
    @abstractmethod
    def next_id(self) -> str: ...
    @abstractmethod
    def save(self, patient: Patient) -> None: ...
    @abstractmethod
    def find_by_id(self, id: str) -> Optional[Patient]: ...

class PrescriptionGateway(ABC):
    @abstractmethod
    def next_id(self) -> str: ...
    @abstractmethod
    def save(self, prescription: Prescription) -> None: ...


# Interactors
class RegisterPatientInteractor(RegisterPatientInputPort):
    # matiz: the Interactor pushes output to the presenter via OutputPort.
    # It never returns a value. The controller reads from the presenter afterwards.
    # This is the strict Clean Architecture approach — use case and presentation are fully decoupled.
    def __init__(
        self,
        gateway:   PatientGateway,
        presenter: RegisterPatientOutputPort,
    ) -> None:
        self._gateway   = gateway
        self._presenter = presenter

    def execute(self, inp: RegisterPatientInput) -> None:
        print(f"[RegisterPatientInteractor] Registering '{inp.full_name}'")
        patient = Patient(
            id             = self._gateway.next_id(),
            full_name      = inp.full_name,
            date_of_birth  = inp.date_of_birth,
            allergies      = list(inp.allergies),
        )
        self._gateway.save(patient)
        self._presenter.present_registered(RegisterPatientOutput(
            patient_id = patient.id,
            full_name  = patient.full_name,
            age        = patient.age(),
        ))


class IssuePrescriptionInteractor(IssuePrescriptionInputPort):
    def __init__(
        self,
        patients:      PatientGateway,
        prescriptions: PrescriptionGateway,
        presenter:     IssuePrescriptionOutputPort,
    ) -> None:
        self._patients      = patients
        self._prescriptions = prescriptions
        self._presenter     = presenter

    def execute(self, inp: IssuePrescriptionInput) -> None:
        print(f"[IssuePrescriptionInteractor] Issuing '{inp.drug}' for patient '{inp.patient_id}'")

        patient = self._patients.find_by_id(inp.patient_id)
        if patient is None:
            self._presenter.present_error(f"Patient '{inp.patient_id}' not found.")
            return

        prescription = Prescription(
            id         = self._prescriptions.next_id(),
            patient_id = inp.patient_id,
            drug       = inp.drug,
            dosage     = inp.dosage,
        )

        try:
            # Entity enforces the allergy rule — the interactor just orchestrates
            prescription.assert_not_allergic(patient.allergies)
        except ValueError as e:
            self._presenter.present_error(str(e))
            return

        self._prescriptions.save(prescription)
        self._presenter.present_issued(IssuePrescriptionOutput(
            prescription_id = prescription.id,
            patient_name    = patient.full_name,
            drug            = prescription.drug,
            dosage          = prescription.dosage,
        ))


# ─────────────────────────────────────────────
# RING 3: INTERFACE ADAPTERS — Gateways, Controllers, Presenters
# ─────────────────────────────────────────────

# Gateways (infrastructure implementations)
class InMemoryPatientGateway(PatientGateway):
    def __init__(self) -> None:
        self._store: dict[str, Patient] = {}
        self._seq = 1

    def next_id(self) -> str:
        id_ = f"pat-{self._seq:03d}"
        self._seq += 1
        return id_

    def save(self, patient: Patient) -> None:
        print(f"[InMemoryPatientGateway] Saved '{patient.id}' ({patient.full_name})")
        self._store[patient.id] = patient

    def find_by_id(self, id: str) -> Optional[Patient]:
        return self._store.get(id)


class InMemoryPrescriptionGateway(PrescriptionGateway):
    def __init__(self) -> None:
        self._store: dict[str, Prescription] = {}
        self._seq = 1

    def next_id(self) -> str:
        id_ = f"rx-{self._seq:03d}"
        self._seq += 1
        return id_

    def save(self, p: Prescription) -> None:
        print(f"[InMemoryPrescriptionGateway] Saved prescription '{p.id}' — {p.drug} {p.dosage}")
        self._store[p.id] = p


# Presenters — convert OutputData into view models
class CliRegistrationPresenter(RegisterPatientOutputPort):
    def __init__(self) -> None:
        self.view_model: Optional[dict] = None

    def present_registered(self, output: RegisterPatientOutput) -> None:
        self.view_model = {
            "patient_id": output.patient_id,
            "name": output.full_name,
            "age": output.age,
        }
        print(f"[CliRegistrationPresenter] ✓ Registered: {self.view_model}")

    def present_error(self, message: str) -> None:
        self.view_model = {"error": message}
        print(f"[CliRegistrationPresenter] ✗ Error: {message}")


class CliPrescriptionPresenter(IssuePrescriptionOutputPort):
    def __init__(self) -> None:
        self.view_model: Optional[dict] = None

    def present_issued(self, output: IssuePrescriptionOutput) -> None:
        self.view_model = {
            "rx":      output.prescription_id,
            "patient": output.patient_name,
            "drug":    output.drug,
            "dosage":  output.dosage,
        }
        print(f"[CliPrescriptionPresenter] ✓ Prescription issued: {self.view_model}")

    def present_error(self, message: str) -> None:
        self.view_model = {"error": message}
        print(f"[CliPrescriptionPresenter] ✗ Rejected: {message}")


# Controllers — translate external input into InputData and call the use case
class PatientController:
    def __init__(self, register_port: RegisterPatientInputPort) -> None:
        self._register = register_port

    def post_register(self, body: dict) -> None:
        print(f"[PatientController] POST /patients  name='{body['full_name']}'")
        self._register.execute(RegisterPatientInput(
            full_name      = body["full_name"],
            date_of_birth  = body["date_of_birth"],
            allergies      = body.get("allergies", []),
        ))


class PrescriptionController:
    def __init__(self, issue_port: IssuePrescriptionInputPort) -> None:
        self._issue = issue_port

    def post_prescription(self, body: dict) -> None:
        print(f"[PrescriptionController] POST /prescriptions  drug='{body['drug']}'")
        self._issue.execute(IssuePrescriptionInput(
            patient_id = body["patient_id"],
            drug       = body["drug"],
            dosage     = body["dosage"],
        ))


# ─────────────────────────────────────────────
# RING 4: FRAMEWORKS & DRIVERS — Composition Root
# ─────────────────────────────────────────────

if __name__ == "__main__":
    print("=== Clean Architecture — Patient Intake System (Python) ===\n")

    patient_gw      = InMemoryPatientGateway()
    prescription_gw = InMemoryPrescriptionGateway()

    reg_presenter  = CliRegistrationPresenter()
    rx_presenter   = CliPrescriptionPresenter()

    register_interactor = RegisterPatientInteractor(patient_gw, reg_presenter)
    issue_interactor    = IssuePrescriptionInteractor(patient_gw, prescription_gw, rx_presenter)

    patient_ctrl      = PatientController(register_interactor)
    prescription_ctrl = PrescriptionController(issue_interactor)

    print("--- Register a patient with a penicillin allergy ---")
    patient_ctrl.post_register({
        "full_name":      "Maria López",
        "date_of_birth":  date(1985, 3, 15),
        "allergies":      ["penicillin", "aspirin"],
    })

    patient_id = reg_presenter.view_model["patient_id"]  # type: ignore

    print("\n--- Doctor issues a safe prescription ---")
    prescription_ctrl.post_prescription({
        "patient_id": patient_id,
        "drug":       "Amoxicillin",
        "dosage":     "500mg every 8h for 7 days",
    })

    print("\n--- Doctor accidentally prescribes a drug the patient is allergic to ---")
    prescription_ctrl.post_prescription({
        "patient_id": patient_id,
        "drug":       "Penicillin",
        "dosage":     "250mg every 6h",
    })
