package main

import (
	"fmt"
	"time"
)

// Scenario: HR employee management system.
// The HRService needs to find and update employee records — it should not
// know whether data comes from a database, an HR SaaS API, or a test fixture.

// Domain object — pure Go struct, no ORM tags
type Employee struct {
	ID         int
	Name       string
	Department string
	Role       string
	Active     bool
	HiredAt    time.Time
}

// The Repository interface — defined in the service's package, not the storage package.
// matiz: in Go, interfaces belong to the consumer, not the implementor.
// The HRService defines what it needs — any type that satisfies these methods works.
// This is the Go idiom: small, consumer-defined interfaces instead of large, provider-defined ones.
type EmployeeRepository interface {
	FindByID(id int) (*Employee, error)
	FindByDepartment(dept string) ([]*Employee, error)
	FindAllActive() ([]*Employee, error)
	Save(e *Employee) error
}

// Concrete implementation: PostgreSQL — simulates database queries
type PostgresEmployeeRepository struct{}

func (r *PostgresEmployeeRepository) FindByID(id int) (*Employee, error) {
	fmt.Printf("[PostgresEmployeeRepository] SELECT * FROM employees WHERE id = %d\n", id)
	return &Employee{
		ID: id, Name: "María García", Department: "Engineering",
		Role: "Senior Engineer", Active: true, HiredAt: time.Date(2021, 4, 1, 0, 0, 0, 0, time.UTC),
	}, nil
}

func (r *PostgresEmployeeRepository) FindByDepartment(dept string) ([]*Employee, error) {
	fmt.Printf("[PostgresEmployeeRepository] SELECT * FROM employees WHERE department = '%s' AND active = true\n", dept)
	return []*Employee{
		{ID: 1, Name: "María García",  Department: dept, Role: "Senior Engineer", Active: true},
		{ID: 2, Name: "Carlos López",  Department: dept, Role: "Tech Lead",        Active: true},
	}, nil
}

func (r *PostgresEmployeeRepository) FindAllActive() ([]*Employee, error) {
	fmt.Println("[PostgresEmployeeRepository] SELECT * FROM employees WHERE active = true")
	return []*Employee{
		{ID: 1, Name: "María García",  Department: "Engineering", Active: true},
		{ID: 2, Name: "Carlos López",  Department: "Engineering", Active: true},
		{ID: 3, Name: "Ana Martínez",  Department: "Design",      Active: true},
	}, nil
}

func (r *PostgresEmployeeRepository) Save(e *Employee) error {
	fmt.Printf("[PostgresEmployeeRepository] INSERT/UPDATE employee '%s'\n", e.Name)
	return nil
}

// InMemory implementation — used in tests, no database needed
type InMemoryEmployeeRepository struct {
	data map[int]*Employee
}

func NewInMemoryEmployeeRepository() *InMemoryEmployeeRepository {
	return &InMemoryEmployeeRepository{data: make(map[int]*Employee)}
}

func (r *InMemoryEmployeeRepository) FindByID(id int) (*Employee, error) {
	fmt.Printf("[InMemoryEmployeeRepository] Looking up employee #%d...\n", id)
	e, ok := r.data[id]
	if !ok {
		return nil, fmt.Errorf("employee %d not found", id)
	}
	return e, nil
}

func (r *InMemoryEmployeeRepository) FindByDepartment(dept string) ([]*Employee, error) {
	fmt.Printf("[InMemoryEmployeeRepository] Filtering by department '%s'...\n", dept)
	var result []*Employee
	for _, e := range r.data {
		if e.Department == dept && e.Active {
			result = append(result, e)
		}
	}
	return result, nil
}

func (r *InMemoryEmployeeRepository) FindAllActive() ([]*Employee, error) {
	var result []*Employee
	for _, e := range r.data {
		if e.Active {
			result = append(result, e)
		}
	}
	return result, nil
}

func (r *InMemoryEmployeeRepository) Save(e *Employee) error {
	r.data[e.ID] = e
	fmt.Printf("[InMemoryEmployeeRepository] Saved employee '%s'\n", e.Name)
	return nil
}

// The Service — depends only on the EmployeeRepository interface
type HRService struct {
	employees EmployeeRepository
}

func NewHRService(repo EmployeeRepository) *HRService {
	return &HRService{employees: repo}
}

func (s *HRService) GetEmployeeProfile(id int) {
	fmt.Printf("\n[HRService] Fetching profile for employee #%d...\n", id)
	e, err := s.employees.FindByID(id)
	if err != nil {
		fmt.Printf("[HRService] Employee not found: %v\n", err)
		return
	}
	fmt.Printf("[HRService] %s | %s | %s\n", e.Name, e.Role, e.Department)
}

func (s *HRService) ListDepartment(dept string) {
	fmt.Printf("\n[HRService] Listing employees in '%s'...\n", dept)
	employees, _ := s.employees.FindByDepartment(dept)
	for _, e := range employees {
		fmt.Printf("[HRService]   - %s (%s)\n", e.Name, e.Role)
	}
}

func main() {
	fmt.Println("=== Production (PostgreSQL) ===")
	service := NewHRService(&PostgresEmployeeRepository{})
	service.GetEmployeeProfile(1)
	service.ListDepartment("Engineering")

	fmt.Println("\n=== Tests (InMemory) ===")
	repo := NewInMemoryEmployeeRepository()
	repo.Save(&Employee{ID: 1, Name: "Test Engineer", Department: "Engineering", Role: "Junior", Active: true})
	repo.Save(&Employee{ID: 2, Name: "Test Designer", Department: "Design",      Role: "Senior", Active: true})
	service = NewHRService(repo)
	service.ListDepartment("Engineering")
	service.GetEmployeeProfile(99) // not found
}
