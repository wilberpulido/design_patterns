"""
Scenario: Student Grade Tracker

Teachers record grades for students. The system generates
individual reports and a class summary.
"""

from dataclasses import dataclass, field
from typing import List, Optional

# ─── Model ────────────────────────────────────────────────────────────────────
# The model owns data and the rules that govern it.
# It knows nothing about how results will be displayed.

@dataclass
class Grade:
    subject: str
    score: float  # 0–100

    def letter(self) -> str:
        # Business rule: letter grade mapping lives in the model.
        if self.score >= 90: return 'A'
        if self.score >= 80: return 'B'
        if self.score >= 70: return 'C'
        if self.score >= 60: return 'D'
        return 'F'

@dataclass
class Student:
    name:       str
    student_id: str
    grades:     List[Grade] = field(default_factory=list)

    def add_grade(self, subject: str, score: float) -> None:
        # Business rule: score must be valid. The controller delegates here.
        if not (0 <= score <= 100):
            raise ValueError(f"Score must be 0–100, got {score}")
        self.grades.append(Grade(subject, score))

    def average(self) -> Optional[float]:
        if not self.grades:
            return None
        return sum(g.score for g in self.grades) / len(self.grades)

    def is_passing(self) -> bool:
        avg = self.average()
        return avg is not None and avg >= 60

class GradeBook:
    def __init__(self):
        self._students: dict[str, Student] = {}

    def enroll(self, student: Student) -> None:
        self._students[student.student_id] = student

    def find(self, student_id: str) -> Optional[Student]:
        return self._students.get(student_id)

    def all_students(self) -> List[Student]:
        return list(self._students.values())

# ─── View ─────────────────────────────────────────────────────────────────────
# matiz: the view is completely stateless — it receives data and renders it.
# This makes it trivial to swap for a different format (HTML, JSON, PDF)
# without changing any model or controller code.
# A JsonReportView could implement the same methods and produce API responses.

class ReportView:
    def render_student_report(self, student: Student) -> None:
        avg    = student.average()
        status = "PASSING" if student.is_passing() else "FAILING"
        print(f"\n┌─ Student Report: {student.name} ({student.student_id})")
        print(f"│  Status:  {status}")
        print(f"│  Average: {avg:.1f}" if avg is not None else "│  Average: N/A")
        print("│  Grades:")
        for g in student.grades:
            print(f"│    {g.subject:<22} {g.score:5.1f}  ({g.letter()})")
        if not student.grades:
            print("│    (no grades recorded)")
        print("└─")

    def render_class_summary(self, students: List[Student]) -> None:
        print(f"\n┌─ Class Summary ({len(students)} students)")
        for s in students:
            avg     = s.average()
            avg_str = f"{avg:.1f}" if avg is not None else " N/A"
            mark    = "✓" if s.is_passing() else "✗"
            print(f"│  {mark} {s.name:<22} avg={avg_str}")
        print("└─")

    def render_not_found(self, student_id: str) -> None:
        print(f"[ReportView] ERROR: Student '{student_id}' not found.")

    def render_error(self, message: str) -> None:
        print(f"[ReportView] ERROR: {message}")

# ─── Controller ───────────────────────────────────────────────────────────────
# The controller handles the incoming action (a button click, a form submit,
# a CLI command). It calls the model, then picks which view method to invoke.
# It does not format output and it does not contain grade-calculation logic.

class GradeController:
    def __init__(self, grade_book: GradeBook, view: ReportView):
        self._grade_book = grade_book
        self._view       = view

    def record_grade(self, student_id: str, subject: str, score: float) -> None:
        print(f"[GradeController] Handling: record grade — {student_id} / {subject} / {score}")
        student = self._grade_book.find(student_id)
        if student is None:
            self._view.render_not_found(student_id)
            return
        try:
            student.add_grade(subject, score)
            print(f"[GradeController] Grade recorded.")
        except ValueError as e:
            self._view.render_error(str(e))

    def show_report(self, student_id: str) -> None:
        print(f"[GradeController] Handling: report for {student_id}")
        student = self._grade_book.find(student_id)
        if student is None:
            self._view.render_not_found(student_id)
            return
        self._view.render_student_report(student)

    def show_class_summary(self) -> None:
        print("[GradeController] Handling: class summary")
        self._view.render_class_summary(self._grade_book.all_students())

# ─── Entry point ──────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=== Student Grade Tracker — MVC Pattern ===\n")

    grade_book = GradeBook()
    view       = ReportView()
    controller = GradeController(grade_book, view)

    grade_book.enroll(Student("Alice Torres", "STU-001"))
    grade_book.enroll(Student("Bob Nguyen",   "STU-002"))
    grade_book.enroll(Student("Carol Smith",  "STU-003"))

    controller.record_grade("STU-001", "Mathematics", 92.0)
    controller.record_grade("STU-001", "Physics",     88.0)
    controller.record_grade("STU-001", "Literature",  75.0)

    controller.record_grade("STU-002", "Mathematics", 55.0)
    controller.record_grade("STU-002", "Physics",     61.0)
    controller.record_grade("STU-002", "Literature",  48.0)

    controller.record_grade("STU-003", "Mathematics", 79.0)
    controller.record_grade("STU-003", "Physics",     83.0)
    controller.record_grade("STU-003", "Literature",  91.0)

    controller.record_grade("STU-001", "Chemistry", 110.0)  # invalid — model rejects it
    controller.show_report("STU-999")                       # student not found

    controller.show_report("STU-001")
    controller.show_report("STU-002")
    controller.show_class_summary()
