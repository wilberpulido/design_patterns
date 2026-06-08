from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import datetime
from enum import Enum
from typing import Optional
import uuid

# Scenario: customer support ticket system.
# A customer submits a ticket — the system creates it, assigns an agent,
# and notifies them. Each layer has a single responsibility.

# ============================================================
# DOMAIN LAYER — business rules, entities, no external deps
# ============================================================

class Priority(Enum):
    LOW      = "low"
    MEDIUM   = "medium"
    HIGH     = "high"
    CRITICAL = "critical"

class TicketStatus(Enum):
    OPEN        = "open"
    IN_PROGRESS = "in_progress"
    RESOLVED    = "resolved"

@dataclass
class Ticket:
    id:              str
    customer_id:     str
    subject:         str
    priority:        Priority
    status:          TicketStatus        = TicketStatus.OPEN
    assigned_to:     Optional[str]       = None
    created_at:      datetime            = field(default_factory=datetime.now)

    def assign(self, agent_id: str) -> None:
        # Domain rule: resolved tickets cannot be reassigned
        if self.status == TicketStatus.RESOLVED:
            raise ValueError("Cannot assign a resolved ticket.")
        self.assigned_to = agent_id
        self.status = TicketStatus.IN_PROGRESS
        print(f"[Ticket] #{self.id[:8]} assigned to '{agent_id}' — status: in_progress")

    def resolve(self) -> None:
        # Domain rule: unassigned tickets cannot be resolved
        if self.assigned_to is None:
            raise ValueError("Cannot resolve an unassigned ticket.")
        self.status = TicketStatus.RESOLVED
        print(f"[Ticket] #{self.id[:8]} resolved by '{self.assigned_to}'")

    def is_critical(self) -> bool:
        return self.priority == Priority.CRITICAL


# ============================================================
# INFRASTRUCTURE LAYER — DB and notification services
# ============================================================

class TicketRepository(ABC):
    @abstractmethod
    def save(self, ticket: Ticket) -> None: pass

    @abstractmethod
    def find_by_id(self, ticket_id: str) -> Optional[Ticket]: pass

class AgentNotifier(ABC):
    @abstractmethod
    def notify_new_assignment(self, agent_id: str, ticket: Ticket) -> None: pass

class PostgresTicketRepository(TicketRepository):
    def save(self, ticket: Ticket) -> None:
        print(f"[PostgresTicketRepository] INSERT/UPDATE ticket '{ticket.id[:8]}' status='{ticket.status.value}'")

    def find_by_id(self, ticket_id: str) -> Optional[Ticket]:
        print(f"[PostgresTicketRepository] SELECT * FROM tickets WHERE id = '{ticket_id[:8]}'")
        # Simulate a found ticket
        return Ticket(ticket_id, "cust_88", "Login broken", Priority.HIGH)

class SlackAgentNotifier(AgentNotifier):
    def notify_new_assignment(self, agent_id: str, ticket: Ticket) -> None:
        print(f"[SlackAgentNotifier] DM to '{agent_id}': new ticket '{ticket.subject}' [{ticket.priority.value}]")


# ============================================================
# APPLICATION LAYER — use cases, DTOs, orchestration
# ============================================================

@dataclass
class OpenTicketCommand:
    customer_id: str
    subject:     str
    priority:    str  # raw string from HTTP input

@dataclass
class AssignTicketCommand:
    ticket_id: str
    agent_id:  str

class TicketApplicationService:
    def __init__(self, tickets: TicketRepository, notifier: AgentNotifier) -> None:
        self._tickets  = tickets
        self._notifier = notifier

    def open_ticket(self, cmd: OpenTicketCommand) -> str:
        print(f"\n[TicketApplicationService] Opening ticket for customer '{cmd.customer_id}'...")
        priority = Priority(cmd.priority)  # maps raw input to domain type
        ticket = Ticket(
            id=str(uuid.uuid4()),
            customer_id=cmd.customer_id,
            subject=cmd.subject,
            priority=priority,
        )
        self._tickets.save(ticket)
        print(f"[TicketApplicationService] Ticket '{ticket.id[:8]}' created — priority: {priority.value}")
        return ticket.id

    def assign_ticket(self, cmd: AssignTicketCommand) -> None:
        print(f"\n[TicketApplicationService] Assigning ticket '{cmd.ticket_id[:8]}' to '{cmd.agent_id}'...")

        ticket = self._tickets.find_by_id(cmd.ticket_id)
        if ticket is None:
            raise ValueError(f"Ticket '{cmd.ticket_id}' not found.")

        ticket.assign(cmd.agent_id)        # domain enforces assignment rules
        self._tickets.save(ticket)
        self._notifier.notify_new_assignment(cmd.agent_id, ticket)

        # matiz: the application layer decides WHAT to do with domain information,
        # but not the rule itself. "Is critical?" is a domain question (ticket.is_critical()).
        # "Who else to notify if critical?" is an application-level coordination decision.
        if ticket.is_critical():
            print(f"[TicketApplicationService] CRITICAL — escalating to on-call manager...")
            self._notifier.notify_new_assignment("manager_oncall", ticket)


# ============================================================
# PRESENTATION LAYER — HTTP handlers (controllers)
# ============================================================

class TicketController:
    def __init__(self, service: TicketApplicationService) -> None:
        self._service = service

    def create(self, http_body: dict) -> None:
        print(f"\n[TicketController] POST /tickets")
        ticket_id = self._service.open_ticket(OpenTicketCommand(
            customer_id=http_body["customer_id"],
            subject=http_body["subject"],
            priority=http_body["priority"],
        ))
        print(f"[TicketController] 201 Created — ticket {ticket_id[:8]}")

    def assign(self, ticket_id: str, http_body: dict) -> None:
        print(f"\n[TicketController] PUT /tickets/{ticket_id[:8]}/assign")
        self._service.assign_ticket(AssignTicketCommand(
            ticket_id=ticket_id,
            agent_id=http_body["agent_id"],
        ))
        print(f"[TicketController] 200 OK")


# ============================================================
# COMPOSITION ROOT — wire dependencies, run
# ============================================================

if __name__ == "__main__":
    controller = TicketController(
        TicketApplicationService(
            PostgresTicketRepository(),
            SlackAgentNotifier(),
        )
    )

    ticket_id = None

    # Simulate: customer opens a ticket
    controller.create({
        "customer_id": "cust_88",
        "subject":     "Cannot export reports",
        "priority":    "high",
    })

    # Simulate: support team assigns an agent
    controller.assign(str(uuid.uuid4()), {"agent_id": "agent_42"})
