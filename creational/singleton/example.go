package main

import (
	"fmt"
	"sync"
	"time"
)

// SINGLETON PATTERN - Go
// Scenario: MetricsCollector — gathers application telemetry (request counts, error rates,
// latency) from multiple goroutines and reports them to a monitoring backend.
//
// Why Singleton here?
// - Metrics must be aggregated in ONE place — multiple collectors would produce split counts.
// - In Go, services spawn many goroutines; all must report to the same collector.
// - sync.Once is the idiomatic Go way to guarantee safe single initialization.

type MetricsCollector struct {
	requestCount int64
	errorCount   int64
	mu           sync.Mutex // protects concurrent writes to the counters
}

var (
	collectorInstance *MetricsCollector
	once              sync.Once
)

// GetCollector is the only entry point to the MetricsCollector.
// sync.Once guarantees the initialization runs exactly once — even across concurrent goroutines.
func GetCollector() *MetricsCollector {
	once.Do(func() {
		fmt.Println("[MetricsCollector] No instance found — initializing collector...")
		collectorInstance = &MetricsCollector{}
		collectorInstance.connectToMonitoringBackend()
	})
	return collectorInstance
}

func (m *MetricsCollector) connectToMonitoringBackend() {
	fmt.Println("[MetricsCollector] Connected to monitoring backend.")
}

func (m *MetricsCollector) RecordRequest(route string) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.requestCount++
	fmt.Printf("[MetricsCollector] Request recorded: %s (total: %d)\n", route, m.requestCount)
}

func (m *MetricsCollector) RecordError(route string, statusCode int) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.errorCount++
	fmt.Printf("[MetricsCollector] Error recorded: %s → %d (total errors: %d)\n", route, statusCode, m.errorCount)
}

func (m *MetricsCollector) Report() {
	m.mu.Lock()
	defer m.mu.Unlock()
	fmt.Printf("[MetricsCollector] Report → requests: %d | errors: %d | error rate: %.1f%%\n",
		m.requestCount,
		m.errorCount,
		float64(m.errorCount)/float64(m.requestCount)*100,
	)
}

// matiz: without sync.Once, concurrent goroutines calling GetCollector() simultaneously
// could each create their own MetricsCollector instance, splitting the metric counts.
// The naive version below would be UNSAFE under concurrency:
//
//   func GetCollectorUnsafe() *MetricsCollector {
//       if collectorInstance == nil {             // goroutine A and B both see nil here
//           collectorInstance = &MetricsCollector{} // both create separate instances
//       }
//       return collectorInstance
//   }
//
// sync.Once solves this with an internal mutex — but only incurs locking cost once.
// After the first call, it's effectively a lock-free read.
func demonstrateSyncOnceProtection() {
	fmt.Println("[Matiz] Demonstrating that concurrent calls return the same instance...")
	var wg sync.WaitGroup
	results := make([]*MetricsCollector, 5)

	for i := 0; i < 5; i++ {
		wg.Add(1)
		go func(idx int) {
			defer wg.Done()
			results[idx] = GetCollector()
		}(i)
	}

	wg.Wait()

	allSame := true
	for _, r := range results {
		if r != results[0] {
			allSame = false
		}
	}
	fmt.Printf("[Matiz] All 5 concurrent goroutines received the same instance: %v\n", allSame)
}

func main() {
	fmt.Println("=== Singleton Pattern Demo — MetricsCollector (Go) ===\n")

	fmt.Println("-- HTTP handler goroutine requests the collector --")
	collectorFromHTTP := GetCollector()

	fmt.Println("\n-- Background job goroutine requests the collector --")
	collectorFromJob := GetCollector()

	fmt.Println("\n-- Are both goroutines sharing the same collector? --")
	fmt.Printf("Same object: %v\n", collectorFromHTTP == collectorFromJob)

	fmt.Println("\n-- HTTP handler records requests --")
	collectorFromHTTP.RecordRequest("GET /products")
	collectorFromHTTP.RecordRequest("POST /checkout")
	collectorFromHTTP.RecordError("POST /checkout", 500)

	fmt.Println("\n-- Background job records its own metrics --")
	collectorFromJob.RecordRequest("CRON /send-emails")

	fmt.Println("\n-- Reporting aggregated metrics (from collectorFromHTTP reference) --")
	time.Sleep(10 * time.Millisecond) // let goroutines settle
	collectorFromHTTP.Report()

	fmt.Println()
	demonstrateSyncOnceProtection()
}
