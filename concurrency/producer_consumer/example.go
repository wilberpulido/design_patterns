package main

import (
	"fmt"
	"sync"
	"time"
)

/**
 * Scenario: Web Scraper
 *
 * A list of URLs is scraped concurrently by multiple workers.
 * A producer feeds URLs into a channel; workers drain it in parallel.
 */

// ─── Data ─────────────────────────────────────────────────────────────────────

type ScrapeJob struct {
	URL      string
	Priority int
}

// ─── Producer ─────────────────────────────────────────────────────────────────
// The producer sends jobs into the channel and closes it when done.
// Closing the channel is Go's idiomatic "poison pill" — all workers
// will exit their range loop automatically when the channel is drained.

func producer(jobs chan<- ScrapeJob, urls []string) {
	for i, url := range urls {
		job := ScrapeJob{URL: url, Priority: i + 1}
		fmt.Printf("[Producer] Queuing (#%d): %s  — buffer: %d/%d\n",
			job.Priority, job.URL, len(jobs), cap(jobs))
		jobs <- job         // blocks if channel buffer is full (backpressure)
		time.Sleep(40 * time.Millisecond)
	}
	// matiz: closing the channel — not a poison pill — is the idiomatic Go approach.
	// Workers using `for job := range jobs` exit automatically when the channel
	// is closed and empty. No sentinel value needed, no count to track.
	// This only works when there is a single producer; with multiple producers,
	// use sync.WaitGroup to close the channel only after all producers finish.
	close(jobs)
	fmt.Println("[Producer] All URLs queued. Channel closed.")
}

// ─── Consumer ─────────────────────────────────────────────────────────────────
// Each worker goroutine pulls jobs from the channel.
// `range jobs` blocks until a job is available and exits when the channel closes.

func worker(id int, jobs <-chan ScrapeJob, wg *sync.WaitGroup) {
	defer wg.Done()
	for job := range jobs {
		fmt.Printf("[Worker-%d] Scraping (#%d): %s\n", id, job.Priority, job.URL)
		time.Sleep(100 * time.Millisecond) // simulate HTTP fetch + parse
		fmt.Printf("[Worker-%d] Done    (#%d): %s\n", id, job.Priority, job.URL)
	}
	fmt.Printf("[Worker-%d] Channel drained. Shutting down.\n", id)
}

// ─── Entry point ──────────────────────────────────────────────────────────────

func main() {
	fmt.Println("=== Web Scraper — Producer-Consumer Pattern ===\n")

	urls := []string{
		"https://news.ycombinator.com",
		"https://lobste.rs",
		"https://reddit.com/r/golang",
		"https://go.dev/blog",
		"https://pkg.go.dev",
		"https://github.com/trending/go",
		"https://golangweekly.com",
		"https://awesome-go.com",
	}

	// A buffered channel IS the bounded buffer — no separate queue struct needed.
	// Buffer size = 3: producer can stay 3 jobs ahead of consumers.
	jobs := make(chan ScrapeJob, 3)

	var wg sync.WaitGroup

	numWorkers := 3
	for i := 1; i <= numWorkers; i++ {
		wg.Add(1)
		go worker(i, jobs, &wg)
	}

	// Producer runs in a goroutine so workers and producer run concurrently
	go producer(jobs, urls)

	wg.Wait()
	fmt.Println("\n[Main] All pages scraped.")
}
