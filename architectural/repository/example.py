from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Optional
from datetime import date

# Scenario: user account management system.
# The AuthService needs to find and persist users — it should not know
# whether they come from PostgreSQL, an external API, or a test fixture.

# Domain object — no ORM dependency, no database knowledge
@dataclass(frozen=True)
class User:
    id: int
    email: str
    username: str
    plan: str          # "free" | "pro" | "enterprise"
    active: bool
    joined: date


# The Repository interface — speaks in domain terms, not SQL terms
class UserRepository(ABC):
    @abstractmethod
    def find_by_id(self, user_id: int) -> Optional[User]:
        pass

    @abstractmethod
    def find_by_email(self, email: str) -> Optional[User]:
        pass

    @abstractmethod
    def find_all_by_plan(self, plan: str) -> list[User]:
        pass

    @abstractmethod
    def save(self, user: User) -> None:
        pass


# Concrete implementation: PostgreSQL — simulates psycopg2 or SQLAlchemy queries
class PostgresUserRepository(UserRepository):
    def find_by_id(self, user_id: int) -> Optional[User]:
        print(f"[PostgresUserRepository] SELECT * FROM users WHERE id = {user_id}")
        return User(user_id, "alice@example.com", "alice", "pro", True, date(2023, 3, 15))

    def find_by_email(self, email: str) -> Optional[User]:
        print(f"[PostgresUserRepository] SELECT * FROM users WHERE email = '{email}'")
        return User(1, email, "alice", "pro", True, date(2023, 3, 15))

    def find_all_by_plan(self, plan: str) -> list[User]:
        print(f"[PostgresUserRepository] SELECT * FROM users WHERE plan = '{plan}' AND active = true")
        return [
            User(1, "alice@example.com", "alice", plan, True, date(2023, 3, 15)),
            User(2, "bob@example.com",   "bob",   plan, True, date(2023, 6, 20)),
        ]

    def save(self, user: User) -> None:
        print(f"[PostgresUserRepository] INSERT/UPDATE user '{user.username}' in database")


# matiz: multiple concrete implementations can coexist behind the same interface.
# The LdapUserRepository fetches users from a corporate directory instead of a database —
# the AuthService works identically either way. Swapping data sources is a one-line change
# at the composition root (where dependencies are wired).
class LdapUserRepository(UserRepository):
    def find_by_id(self, user_id: int) -> Optional[User]:
        print(f"[LdapUserRepository] Querying LDAP directory for uid={user_id}...")
        return User(user_id, "corp_user@company.com", "corp_user", "enterprise", True, date(2020, 1, 1))

    def find_by_email(self, email: str) -> Optional[User]:
        print(f"[LdapUserRepository] Querying LDAP directory for mail={email}...")
        return User(42, email, "corp_user", "enterprise", True, date(2020, 1, 1))

    def find_all_by_plan(self, plan: str) -> list[User]:
        print(f"[LdapUserRepository] LDAP does not support plan filtering — returning empty.")
        return []

    def save(self, user: User) -> None:
        print(f"[LdapUserRepository] LDAP is read-only — cannot persist user '{user.username}'.")


# The Service — depends only on the UserRepository interface
class AuthService:
    def __init__(self, users: UserRepository) -> None:
        self._users = users

    def login(self, email: str) -> None:
        print(f"\n[AuthService] Attempting login for {email}...")
        user = self._users.find_by_email(email)
        if user and user.active:
            print(f"[AuthService] Login successful — welcome, {user.username} ({user.plan} plan)")
        else:
            print(f"[AuthService] Login failed — user not found or inactive.")

    def list_pro_users(self) -> None:
        print(f"\n[AuthService] Fetching all pro users...")
        users = self._users.find_all_by_plan("pro")
        for u in users:
            print(f"[AuthService]   @{u.username} ({u.email})")


if __name__ == "__main__":
    print("=== PostgreSQL ===")
    service = AuthService(PostgresUserRepository())
    service.login("alice@example.com")
    service.list_pro_users()

    print("\n=== LDAP (corporate directory) ===")
    service = AuthService(LdapUserRepository())
    service.login("corp_user@company.com")
    service.list_pro_users()
