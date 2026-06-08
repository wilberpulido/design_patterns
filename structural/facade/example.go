package main

import (
	"fmt"
	"strings"
)

// Scenario: CI/CD deployment pipeline.
// Releasing a new version requires running tests, building a Docker image,
// pushing it to the registry, updating Kubernetes, and notifying the team.
// The ReleaseManager should not orchestrate all of this step by step.

// Subsystem: runs the automated test suite
type TestRunner struct{}

func (t *TestRunner) Run(projectName string) bool {
	fmt.Printf("[TestRunner] Running test suite for %s...\n", projectName)
	fmt.Println("[TestRunner] All 142 tests passed.")
	return true
}

// Subsystem: builds and tags the Docker image
type DockerBuilder struct{}

func (d *DockerBuilder) Build(projectName, version string) string {
	image := fmt.Sprintf("registry.internal/%s:%s", projectName, version)
	fmt.Printf("[DockerBuilder] Building image %s...\n", image)
	fmt.Println("[DockerBuilder] Image built successfully.")
	return image
}

// Subsystem: pushes the image to the container registry
type RegistryPusher struct{}

func (r *RegistryPusher) Push(image string) error {
	fmt.Printf("[RegistryPusher] Pushing %s to registry...\n", image)
	fmt.Println("[RegistryPusher] Push complete.")
	return nil
}

// Subsystem: updates the Kubernetes deployment to the new image
type KubernetesDeployer struct{}

func (k *KubernetesDeployer) Deploy(service, image string) error {
	fmt.Printf("[KubernetesDeployer] Updating deployment '%s' → %s...\n", service, image)
	fmt.Println("[KubernetesDeployer] Rollout complete. All pods healthy.")
	return nil
}

// Subsystem: sends a message to a Slack channel
type SlackNotifier struct{}

func (s *SlackNotifier) Notify(channel, message string) {
	fmt.Printf("[SlackNotifier] → #%s: %s\n", channel, message)
}

// DeploymentResult aggregates the outcome of the pipeline
type DeploymentResult struct {
	Success bool
	Image   string
	Errors  []string
}

// The Facade — one Deploy() call coordinates the full CI/CD pipeline.
// The client never touches individual subsystems.
type DeploymentFacade struct {
	tests    *TestRunner
	builder  *DockerBuilder
	registry *RegistryPusher
	k8s      *KubernetesDeployer
	slack    *SlackNotifier
}

func NewDeploymentFacade() *DeploymentFacade {
	return &DeploymentFacade{
		tests:    &TestRunner{},
		builder:  &DockerBuilder{},
		registry: &RegistryPusher{},
		k8s:      &KubernetesDeployer{},
		slack:    &SlackNotifier{},
	}
}

func (d *DeploymentFacade) Deploy(projectName, version, k8sService, slackChannel string) DeploymentResult {
	fmt.Printf("\n[DeploymentFacade] Starting deployment of %s@%s...\n", projectName, version)

	result := DeploymentResult{}

	// matiz: instead of stopping at the first failure, the facade collects all errors
	// before returning. The operator gets a full picture of what went wrong rather than
	// a single error that hides other problems underneath.
	// This is only appropriate when steps are independent — here, we still skip deploy
	// if tests fail, since deploying broken code would be worse than knowing all issues.
	if ok := d.tests.Run(projectName); !ok {
		result.Errors = append(result.Errors, "test suite failed")
		d.slack.Notify(slackChannel, fmt.Sprintf("Deployment of %s@%s aborted: tests failed", projectName, version))
		result.Success = false
		return result
	}

	image := d.builder.Build(projectName, version)
	result.Image = image

	if err := d.registry.Push(image); err != nil {
		result.Errors = append(result.Errors, fmt.Sprintf("registry push failed: %v", err))
	}

	if err := d.k8s.Deploy(k8sService, image); err != nil {
		result.Errors = append(result.Errors, fmt.Sprintf("k8s deploy failed: %v", err))
	}

	if len(result.Errors) > 0 {
		msg := fmt.Sprintf("Deployment of %s@%s FAILED: %s", projectName, version, strings.Join(result.Errors, ", "))
		d.slack.Notify(slackChannel, msg)
		result.Success = false
	} else {
		d.slack.Notify(slackChannel, fmt.Sprintf("✓ %s@%s deployed successfully → %s", projectName, version, image))
		result.Success = true
	}

	return result
}

// Client — a release manager script. Triggers the full pipeline with one call.
type ReleaseManager struct {
	deployer *DeploymentFacade
}

func (r *ReleaseManager) ReleaseVersion(project, version string) {
	fmt.Printf("[ReleaseManager] Initiating release %s for project %s\n", version, project)

	result := r.deployer.Deploy(project, version, project+"-service", "deployments")

	if result.Success {
		fmt.Printf("[ReleaseManager] Release %s is live.\n", version)
	} else {
		fmt.Printf("[ReleaseManager] Release %s failed. Review errors above.\n", version)
	}
}

func main() {
	facade := NewDeploymentFacade()
	manager := &ReleaseManager{deployer: facade}
	manager.ReleaseVersion("payment-api", "v2.4.1")
}
