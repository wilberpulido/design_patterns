// DECORATOR PATTERN - Go
// Scenario: HTTP client for a third-party API — decorators add retry logic,
// response caching, and request logging on top of a core HTTP client.
//
// To run: go run example.go

package main

import (
	"fmt"
	"time"
)

// HttpClient is the interface every decorator and the real client must satisfy.
// Defining behavior through interfaces is idiomatic Go — no base class needed.
type HttpClient interface {
	Get(url string) (string, error)
}

// RealHttpClient performs the actual network call (simulated here).
// It knows nothing about retries, caching, or logging.
type RealHttpClient struct {
	callCount int
}

func (c *RealHttpClient) Get(url string) (string, error) {
	c.callCount++
	fmt.Printf("[RealHttpClient] Fetching %s (attempt #%d)...\n", url, c.callCount)
	// Simulate a flaky upstream service that fails the first 2 attempts
	if c.callCount <= 2 {
		return "", fmt.Errorf("upstream timeout")
	}
	return fmt.Sprintf(`{"url":"%s","data":"product catalog loaded"}`, url), nil
}

// LoggingDecorator wraps any HttpClient and logs every call and its outcome.
type LoggingDecorator struct {
	wrapped HttpClient
}

func (d *LoggingDecorator) Get(url string) (string, error) {
	start := time.Now()
	fmt.Printf("[LoggingDecorator] → GET %s\n", url)
	resp, err := d.wrapped.Get(url)
	elapsed := time.Since(start).Round(time.Millisecond)
	if err != nil {
		fmt.Printf("[LoggingDecorator] ← Failed in %v: %v\n", elapsed, err)
	} else {
		fmt.Printf("[LoggingDecorator] ← Success in %v\n", elapsed)
	}
	return resp, err
}

// RetryDecorator wraps any HttpClient and retries on transient failures.
type RetryDecorator struct {
	wrapped    HttpClient
	maxRetries int
}

func (d *RetryDecorator) Get(url string) (string, error) {
	var lastErr error
	for attempt := 1; attempt <= d.maxRetries; attempt++ {
		fmt.Printf("[RetryDecorator] Attempt %d/%d\n", attempt, d.maxRetries)
		resp, err := d.wrapped.Get(url)
		if err == nil {
			fmt.Printf("[RetryDecorator] Succeeded on attempt %d.\n", attempt)
			return resp, nil
		}
		lastErr = err
		fmt.Printf("[RetryDecorator] Attempt %d failed: %v\n", attempt, err)
		if attempt < d.maxRetries {
			// Exponential backoff would go here in production
			time.Sleep(5 * time.Millisecond)
		}
	}
	return "", fmt.Errorf("all %d attempts failed: %w", d.maxRetries, lastErr)
}

// CachingDecorator wraps any HttpClient and avoids redundant network calls.
type CachingDecorator struct {
	wrapped HttpClient
	cache   map[string]string
}

func NewCachingDecorator(wrapped HttpClient) *CachingDecorator {
	return &CachingDecorator{wrapped: wrapped, cache: make(map[string]string)}
}

func (d *CachingDecorator) Get(url string) (string, error) {
	if cached, ok := d.cache[url]; ok {
		fmt.Printf("[CachingDecorator] Cache HIT for %s — skipping network call.\n", url)
		return cached, nil
	}
	fmt.Printf("[CachingDecorator] Cache MISS for %s — delegating.\n", url)
	resp, err := d.wrapped.Get(url)
	if err == nil {
		d.cache[url] = resp
		fmt.Printf("[CachingDecorator] Response cached for future calls.\n")
	}
	return resp, err
}

// matiz: in Go, decorators are naturally expressed via interface composition.
// Any struct implementing HttpClient can wrap any other HttpClient — no inheritance,
// no base class. This is idiomatic Go: "accept interfaces, return structs."
// The compiler enforces the contract at the wrapping point, not at a class hierarchy.

func main() {
	fmt.Println("=== Decorator Pattern Demo — HTTP Client with Retry & Cache (Go) ===\n")

	core := &RealHttpClient{}

	// Stack (outer to inner): Caching → Retry → Logging → RealHttpClient
	// Caching is outermost: on a cache hit, the retry and real client never run.
	client := NewCachingDecorator(
		&RetryDecorator{
			wrapped:    &LoggingDecorator{wrapped: core},
			maxRetries: 4,
		},
	)

	url := "https://api.example.com/products"

	fmt.Println("-- First call: cache miss, service is flaky --")
	resp, err := client.Get(url)
	if err != nil {
		fmt.Printf("Final error: %v\n", err)
	} else {
		fmt.Printf("Response: %s\n", resp)
	}

	fmt.Println("\n-- Second call: same URL, should hit cache --")
	resp, err = client.Get(url)
	if err != nil {
		fmt.Printf("Final error: %v\n", err)
	} else {
		fmt.Printf("Response: %s\n", resp)
	}
}
