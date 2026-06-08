package main

import (
	"fmt"
	"time"
)

// Scenario: public library book lending system.
// A member borrows a book — the system checks eligibility,
// records the loan, marks the book unavailable, and sends a confirmation.

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

type Book struct {
	ID        string
	Title     string
	Author    string
	Available bool
}

type Member struct {
	ID          string
	Name        string
	Email       string
	ActiveLoans int
}

type Loan struct {
	ID         string
	BookID     string
	MemberID   string
	BorrowedAt time.Time
	DueAt      time.Time
}

func NewLoan(bookID, memberID string) *Loan {
	now := time.Now()
	return &Loan{
		ID:         fmt.Sprintf("loan_%d", now.UnixMilli()),
		BookID:     bookID,
		MemberID:   memberID,
		BorrowedAt: now,
		DueAt:      now.AddDate(0, 0, 14), // domain rule: 14-day lending period
	}
}

// Domain service — eligibility logic spans both Member and Book
func CanBorrow(member *Member, book *Book) (bool, string) {
	if !book.Available {
		return false, "book is not available"
	}
	if member.ActiveLoans >= 5 { // domain rule: 5-loan limit per member
		return false, "member has reached the maximum loan limit (5)"
	}
	return true, ""
}

// ============================================================
// INFRASTRUCTURE LAYER — DB and email gateway
// ============================================================

type BookRepository interface {
	FindByID(id string) (*Book, error)
	Save(book *Book) error
}

type MemberRepository interface {
	FindByID(id string) (*Member, error)
}

type LoanRepository interface {
	Save(loan *Loan) error
}

type EmailGateway interface {
	SendLoanConfirmation(email string, loan *Loan, bookTitle string) error
}

type SqlBookRepository struct{}

func (r *SqlBookRepository) FindByID(id string) (*Book, error) {
	fmt.Printf("[SqlBookRepository] SELECT * FROM books WHERE id = '%s'\n", id)
	return &Book{ID: id, Title: "The Pragmatic Programmer", Author: "Hunt & Thomas", Available: true}, nil
}

func (r *SqlBookRepository) Save(book *Book) error {
	fmt.Printf("[SqlBookRepository] UPDATE books SET available = %v WHERE id = '%s'\n", book.Available, book.ID)
	return nil
}

type SqlMemberRepository struct{}

func (r *SqlMemberRepository) FindByID(id string) (*Member, error) {
	fmt.Printf("[SqlMemberRepository] SELECT * FROM members WHERE id = '%s'\n", id)
	return &Member{ID: id, Name: "Elena Ruiz", Email: "elena@library.org", ActiveLoans: 2}, nil
}

type SqlLoanRepository struct{}

func (r *SqlLoanRepository) Save(loan *Loan) error {
	fmt.Printf("[SqlLoanRepository] INSERT INTO loans (id, book_id, member_id, due_at) VALUES ('%s', '%s', '%s', '%s')\n",
		loan.ID, loan.BookID, loan.MemberID, loan.DueAt.Format("2006-01-02"))
	return nil
}

type SmtpEmailGateway struct{}

func (g *SmtpEmailGateway) SendLoanConfirmation(email string, loan *Loan, bookTitle string) error {
	fmt.Printf("[SmtpEmailGateway] Sending confirmation to %s — \"%s\" due %s\n",
		email, bookTitle, loan.DueAt.Format("2006-01-02"))
	return nil
}

// ============================================================
// APPLICATION LAYER — use case orchestration
// ============================================================

type BorrowBookCommand struct {
	BookID   string
	MemberID string
}

type LendingApplicationService struct {
	books   BookRepository
	members MemberRepository
	loans   LoanRepository
	email   EmailGateway
}

func NewLendingApplicationService(
	b BookRepository, m MemberRepository, l LoanRepository, e EmailGateway,
) *LendingApplicationService {
	return &LendingApplicationService{b, m, l, e}
}

func (s *LendingApplicationService) BorrowBook(cmd BorrowBookCommand) (*Loan, error) {
	fmt.Println("\n[LendingApplicationService] Starting BorrowBook use case...")

	book, err := s.books.FindByID(cmd.BookID)
	if err != nil {
		return nil, err
	}

	member, err := s.members.FindByID(cmd.MemberID)
	if err != nil {
		return nil, err
	}

	// matiz: business eligibility lives in the domain (CanBorrow).
	// The application layer asks the domain whether the action is allowed,
	// then coordinates the infrastructure side effects if it is.
	// No business rule is written here — only orchestration.
	if ok, reason := CanBorrow(member, book); !ok {
		return nil, fmt.Errorf("cannot borrow: %s", reason)
	}

	loan := NewLoan(book.ID, member.ID)
	fmt.Printf("[LendingApplicationService] Loan '%s' created — '%s' borrowed by %s, due %s\n",
		loan.ID, book.Title, member.Name, loan.DueAt.Format("2006-01-02"))

	book.Available = false
	s.books.Save(book)
	s.loans.Save(loan)
	s.email.SendLoanConfirmation(member.Email, loan, book.Title)

	fmt.Println("[LendingApplicationService] BorrowBook use case completed.")
	return loan, nil
}

// ============================================================
// PRESENTATION LAYER — HTTP handler
// ============================================================

type LendingHandler struct {
	service *LendingApplicationService
}

func NewLendingHandler(s *LendingApplicationService) *LendingHandler {
	return &LendingHandler{s}
}

func (h *LendingHandler) HandleBorrowRequest(bookID, memberID string) {
	fmt.Printf("\n[LendingHandler] POST /loans {book_id: %s, member_id: %s}\n", bookID, memberID)

	loan, err := h.service.BorrowBook(BorrowBookCommand{bookID, memberID})
	if err != nil {
		fmt.Printf("[LendingHandler] 422 Unprocessable Entity — %s\n", err)
		return
	}

	fmt.Printf("[LendingHandler] 201 Created — loan %s | due %s\n",
		loan.ID, loan.DueAt.Format("2006-01-02"))
}

// ============================================================
// COMPOSITION ROOT
// ============================================================

func main() {
	handler := NewLendingHandler(
		NewLendingApplicationService(
			&SqlBookRepository{},
			&SqlMemberRepository{},
			&SqlLoanRepository{},
			&SmtpEmailGateway{},
		),
	)

	handler.HandleBorrowRequest("book_42", "member_7")
}
