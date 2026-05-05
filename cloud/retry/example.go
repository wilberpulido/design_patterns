package main

import (
	"errors"
	"fmt"
	"math"
	"time"
)

/**
 * Scenario: Database Connection Pool
 *
 * The app retries connecting to the primary DB during a brief failover.
 * It aborts immediately on authentication errors.
 */

// ─── Error types ──────────────────────────────────────────────────────────────

// TransientError signals a temporary failure — safe to retry.
type TransientError struct{ msg string }

func (e *TransientError) Error() string { return e.msg }

// PermanentError signals a permanent failure — do not retry.
type PermanentError struct{ msg string }

func (e *PermanentError) Error() string { return e.msg }

// isTransient checks if an error is worth retrying.
func isTransient(err error) bool {
	var t *TransientError
	return errors.As(err, &t)
}

// ─── Retry Policy ─────────────────────────────────────────────────────────────

type RetryPolicy struct {
	MaxAttempts       int
	BaseDelay         time.Duration
	BackoffMultiplier float64
}

func (p RetryPolicy) delay(attempt int) time.Duration {
	// 100ms → 200ms → 400ms
	ms := float64(p.BaseDelay.Milliseconds()) * math.Pow(p.BackoffMultiplier, float64(attempt-1))
	return time.Duration(ms) * time.Millisecond
}

// ─── Retry Executor ───────────────────────────────────────────────────────────
// matiz: in Go, retry is often implemented as a plain function rather than a struct,
// since there is no state to hold between calls. A struct is used here only to
// carry the policy — if you find yourself passing the policy everywhere, a
// function closure like `newRetrier(policy)` returning a func is equally idiomatic.

type RetryExecutor struct {
	policy RetryPolicy
}

func (r RetryExecutor) Execute(operation func() (any, error)) (any, error) {
	for attempt := 1; ; attempt++ {
		fmt.Printf("[RetryExecutor] Attempt %d/%d...\n", attempt, r.policy.MaxAttempts)

		result, err := operation()
		if err == nil {
			fmt.Printf("[RetryExecutor] Success on attempt %d.\n", attempt)
			return result, nil
		}

		if !isTransient(err) {
			fmt.Printf("[RetryExecutor] Permanent failure: %q. Aborting immediately.\n", err)
			return nil, err
		}

		if attempt >= r.policy.MaxAttempts {
			fmt.Printf("[RetryExecutor] All %d attempts exhausted. Giving up.\n", r.policy.MaxAttempts)
			return nil, err
		}

		delay := r.policy.delay(attempt)
		fmt.Printf("[RetryExecutor] Transient failure: %q. Retrying in %s...\n", err, delay)
		time.Sleep(delay)
	}
}

// ─── DB Connector ─────────────────────────────────────────────────────────────

type DBConnector struct {
	host             string
	remainingFailures int
}

func NewDBConnector(host string, simulatedFailures int) *DBConnector {
	return &DBConnector{host: host, remainingFailures: simulatedFailures}
}

func (db *DBConnector) Connect(user, password string) (string, error) {
	fmt.Printf("[DBConnector] Connecting to %s as %s...\n", db.host, user)

	if db.remainingFailures > 0 {
		db.remainingFailures--
		return "", &TransientError{msg: fmt.Sprintf("connection refused: %s is in failover", db.host)}
	}

	connID := fmt.Sprintf("conn_%s_%d", user, time.Now().UnixMilli()%10000)
	fmt.Printf("[DBConnector] Connected. Connection ID: %s\n", connID)
	return connID, nil
}

func (db *DBConnector) ConnectBadCredentials(user, password string) (string, error) {
	fmt.Printf("[DBConnector] Connecting to %s as %s...\n", db.host, user)
	return "", &PermanentError{msg: fmt.Sprintf("authentication failed for user %q (do not retry)", user)}
}

// ─── Entry point ──────────────────────────────────────────────────────────────

func main() {
	fmt.Println("=== Database Connection Pool — Retry Pattern ===\n")

	policy := RetryPolicy{
		MaxAttempts:       3,
		BaseDelay:         100 * time.Millisecond,
		BackoffMultiplier: 2.0,
	}
	executor := RetryExecutor{policy: policy}

	// Case 1: success on first attempt
	fmt.Println("--- Case 1: No failures ---")
	db := NewDBConnector("db-primary.prod", 0)
	result, _ := executor.Execute(func() (any, error) { return db.Connect("app_user", "secret") })
	fmt.Printf("Result: %s\n\n", result)

	// Case 2: one transient failure
	fmt.Println("--- Case 2: One transient failure ---")
	db = NewDBConnector("db-primary.prod", 1)
	result, _ = executor.Execute(func() (any, error) { return db.Connect("app_user", "secret") })
	fmt.Printf("Result: %s\n\n", result)

	// Case 3: two transient failures
	fmt.Println("--- Case 3: Two transient failures ---")
	db = NewDBConnector("db-primary.prod", 2)
	result, _ = executor.Execute(func() (any, error) { return db.Connect("app_user", "secret") })
	fmt.Printf("Result: %s\n\n", result)

	// Case 4: all attempts exhausted
	fmt.Println("--- Case 4: All attempts exhausted ---")
	db = NewDBConnector("db-primary.prod", 99)
	_, err := executor.Execute(func() (any, error) { return db.Connect("app_user", "secret") })
	fmt.Printf("Final error: %s\n\n", err)

	// Case 5: permanent error — aborts immediately
	fmt.Println("--- Case 5: Permanent error (bad credentials) ---")
	db = NewDBConnector("db-primary.prod", 0)
	_, err = executor.Execute(func() (any, error) { return db.ConnectBadCredentials("hacker", "wrong") })
	fmt.Printf("Final error: %s\n", err)
}
