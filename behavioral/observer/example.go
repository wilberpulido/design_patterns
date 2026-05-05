package main

import (
	"fmt"
	"strings"
	"time"
)

// Scenario: CI/CD Pipeline Event System
//
// A deployment pipeline (subject) broadcasts lifecycle events
// (build started, tests passed, deployed, failed) to multiple subscribers:
// a Slack notifier, a metrics collector, and a rollback controller.

// ─── Event ────────────────────────────────────────────────────────────────────
// PipelineEvent carries all context about what happened in the pipeline.
// Using a struct (not multiple args) keeps the observer interface stable
// as new fields are added.
type PipelineEvent struct {
	Pipeline  string
	Stage     string // "build", "test", "deploy"
	Status    string // "started", "passed", "failed"
	Duration  time.Duration
	Timestamp time.Time
}

// ─── Observer interface ───────────────────────────────────────────────────────
// In Go, interfaces are satisfied implicitly — no "implements" keyword.
// Any type with OnPipelineEvent automatically qualifies as a PipelineObserver.
type PipelineObserver interface {
	OnPipelineEvent(event PipelineEvent)
}

// ─── Subject ──────────────────────────────────────────────────────────────────
// DeploymentPipeline is the subject. It manages observers and fires events.
// It has no knowledge of what Slack, metrics, or rollback do.
type DeploymentPipeline struct {
	name      string
	observers []PipelineObserver
}

func NewDeploymentPipeline(name string) *DeploymentPipeline {
	return &DeploymentPipeline{name: name}
}

func (p *DeploymentPipeline) Subscribe(observer PipelineObserver) {
	p.observers = append(p.observers, observer)
	fmt.Printf("[Pipeline:%s] Observer subscribed: %T\n", p.name, observer)
}

// matiz: Go has no built-in identity comparison for interfaces.
// We compare by pointer after a type assertion to find the right observer.
// This is the idiomatic Go way to implement detach without a separate ID.
func (p *DeploymentPipeline) Unsubscribe(observer PipelineObserver) {
	filtered := p.observers[:0]
	for _, o := range p.observers {
		if o != observer {
			filtered = append(filtered, o)
		}
	}
	p.observers = filtered
	fmt.Printf("[Pipeline:%s] Observer unsubscribed: %T\n", p.name, observer)
}

func (p *DeploymentPipeline) emit(stage, status string, duration time.Duration) {
	event := PipelineEvent{
		Pipeline:  p.name,
		Stage:     stage,
		Status:    status,
		Duration:  duration,
		Timestamp: time.Now(),
	}

	statusIcon := "✓"
	if status == "failed" {
		statusIcon = "✗"
	} else if status == "started" {
		statusIcon = "▶"
	}

	fmt.Printf("\n[Pipeline:%s] %s Stage=%s Status=%s (%s)\n",
		p.name, statusIcon, stage, status, duration)
	fmt.Printf("[Pipeline:%s] Notifying %d observer(s)...\n",
		p.name, len(p.observers))

	for _, o := range p.observers {
		o.OnPipelineEvent(event)
	}
}

// Public methods simulate the pipeline lifecycle.
func (p *DeploymentPipeline) StartBuild()    { p.emit("build", "started", 0) }
func (p *DeploymentPipeline) BuildPassed()   { p.emit("build", "passed", 42*time.Second) }
func (p *DeploymentPipeline) TestsPassed()   { p.emit("test", "passed", 118*time.Second) }
func (p *DeploymentPipeline) DeployFailed()  { p.emit("deploy", "failed", 15*time.Second) }
func (p *DeploymentPipeline) DeployPassed()  { p.emit("deploy", "passed", 30*time.Second) }

// ─── Concrete Observers ───────────────────────────────────────────────────────

// SlackNotifier posts messages to a channel on pipeline events.
type SlackNotifier struct {
	Channel string
}

func (s *SlackNotifier) OnPipelineEvent(event PipelineEvent) {
	emoji := map[string]string{
		"started": ":hourglass:",
		"passed":  ":white_check_mark:",
		"failed":  ":red_circle:",
	}[event.Status]

	msg := fmt.Sprintf("%s [%s] %s/%s — %s",
		emoji, event.Pipeline, event.Stage, event.Status,
		formatDuration(event.Duration))

	fmt.Printf("[SlackNotifier] Posting to #%s: %s\n", s.Channel, msg)
}

// MetricsCollector records pipeline durations for dashboards and SLAs.
type MetricsCollector struct {
	stageDurations map[string]time.Duration
}

func NewMetricsCollector() *MetricsCollector {
	return &MetricsCollector{stageDurations: make(map[string]time.Duration)}
}

func (m *MetricsCollector) OnPipelineEvent(event PipelineEvent) {
	if event.Status == "started" {
		return // Nothing to record yet for "started" events.
	}
	key := event.Stage + "/" + event.Status
	m.stageDurations[key] = event.Duration
	fmt.Printf("[MetricsCollector] Recorded %s: %s → duration=%s\n",
		event.Pipeline, key, event.Duration)
}

// RollbackController triggers rollback only on deploy failures.
// matiz: this observer filters events by stage — it ignores everything
// except deploy failures. Observers decide their own relevance;
// the subject doesn't need conditional notification logic.
type RollbackController struct {
	lastStableVersion string
}

func (r *RollbackController) OnPipelineEvent(event PipelineEvent) {
	if event.Stage == "deploy" && event.Status == "passed" {
		r.lastStableVersion = fmt.Sprintf("v%d", time.Now().Unix())
		fmt.Printf("[RollbackController] Stable version recorded: %s\n", r.lastStableVersion)
		return
	}

	if event.Stage == "deploy" && event.Status == "failed" {
		fmt.Printf("[RollbackController] ⚠ Deploy failed! Rolling back to %s...\n",
			r.lastStableVersion)
		r.executeRollback()
	}
}

func (r *RollbackController) executeRollback() {
	fmt.Printf("[RollbackController] Rollback complete. Service restored to %s.\n",
		r.lastStableVersion)
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
func formatDuration(d time.Duration) string {
	if d == 0 {
		return "—"
	}
	return d.String()
}

// ─── Main ─────────────────────────────────────────────────────────────────────
func main() {
	fmt.Println("=== CI/CD Pipeline Event System — Observer Pattern ===")
	fmt.Println(strings.Repeat("─", 55))

	pipeline := NewDeploymentPipeline("api-service")

	slack    := &SlackNotifier{Channel: "deployments"}
	metrics  := NewMetricsCollector()
	rollback := &RollbackController{lastStableVersion: "v1.4.2"}

	pipeline.Subscribe(slack)
	pipeline.Subscribe(metrics)
	pipeline.Subscribe(rollback)

	// Happy path — all stages pass.
	fmt.Println("\n── Happy path deployment ──")
	pipeline.StartBuild()
	pipeline.BuildPassed()
	pipeline.TestsPassed()
	pipeline.DeployPassed()

	// Failed deployment — rollback controller reacts, others just log.
	fmt.Println("\n── Failed deployment ──")
	pipeline.StartBuild()
	pipeline.BuildPassed()
	pipeline.DeployFailed()
}
