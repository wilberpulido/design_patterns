package main

import (
	"fmt"
	"strings"
)

// The target interface — how our application sends emails.
// All notification logic depends on this contract.
type EmailSender interface {
	Send(to, subject, body string) error
}

// The adaptee — a third-party SendGrid-style client with an incompatible API.
// In a real project, this comes from an external package you cannot modify.
// Incompatibilities: different method name, requires API key on every call,
// expects HTML content (not plain text), returns (bool, error) instead of error.
type SendGridClient struct {
	APIKey string
}

func (s *SendGridClient) Deliver(recipient, subject, htmlContent, apiKey string) (bool, error) {
	fmt.Printf("[SendGridClient] Delivering to: %s | subject: %s | key: %s...\n",
		recipient, subject, apiKey[:6]+"***")
	return true, nil
}

func (s *SendGridClient) ValidateKey(key string) bool {
	return strings.HasPrefix(key, "SG.")
}

// The Adapter — wraps SendGridClient and implements EmailSender.
// Handles: method name translation, API key injection, plain text → HTML conversion,
// and (bool, error) → error return shape normalization.
type SendGridAdapter struct {
	// matiz: Holding a pointer to the adaptee (not a copy) matters when the adaptee
	// is stateful — e.g., it tracks a connection pool, a rate-limit counter, or a
	// request queue. Copying a stateful struct would silently create independent state.
	client *SendGridClient
}

func NewSendGridAdapter(client *SendGridClient) *SendGridAdapter {
	return &SendGridAdapter{client: client}
}

func (a *SendGridAdapter) Send(to, subject, body string) error {
	fmt.Printf("[SendGridAdapter] Send() → translating to SendGridClient.Deliver()...\n")

	if !a.client.ValidateKey(a.client.APIKey) {
		return fmt.Errorf("[SendGridAdapter] invalid API key — aborting")
	}

	// Content translation: our interface accepts plain text; SendGrid requires HTML.
	htmlBody := "<p>" + strings.ReplaceAll(body, "\n", "<br>") + "</p>"

	ok, err := a.client.Deliver(to, subject, htmlBody, a.client.APIKey)
	if err != nil {
		return fmt.Errorf("[SendGridAdapter] delivery error: %w", err)
	}
	if !ok {
		return fmt.Errorf("[SendGridAdapter] SendGrid returned failure status")
	}

	fmt.Printf("[SendGridAdapter] Email to %s delivered.\n", to)
	return nil
}

// Client code — only uses EmailSender.
// Switching to Mailgun means creating a MailgunAdapter — NotificationService stays unchanged.
type NotificationService struct {
	sender EmailSender
}

func NewNotificationService(sender EmailSender) *NotificationService {
	return &NotificationService{sender: sender}
}

func (n *NotificationService) AlertUser(email, message string) {
	fmt.Printf("\n[NotificationService] Sending alert to %s...\n", email)
	if err := n.sender.Send(email, "Account Alert", message); err != nil {
		fmt.Printf("[NotificationService] Failed: %v\n", err)
	} else {
		fmt.Println("[NotificationService] Alert sent successfully.")
	}
}

func main() {
	sgClient := &SendGridClient{APIKey: "SG.abc123xyz"}
	adapter := NewSendGridAdapter(sgClient)
	notifier := NewNotificationService(adapter)

	notifier.AlertUser(
		"admin@company.com",
		"Unusual login detected from IP 203.0.113.42.\nPlease review your recent activity.",
	)
}
