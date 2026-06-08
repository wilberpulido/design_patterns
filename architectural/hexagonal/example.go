// Hexagonal Architecture — Smart Home Automation System (Go)
//
// Run: go run example.go
//
// Go's implicit interface satisfaction makes it a natural fit for hexagonal architecture:
// any struct that implements a port's methods automatically satisfies the interface —
// no explicit "implements" declaration needed.

package main

import "fmt"

// ─────────────────────────────────────────────
// DOMAIN — pure business structs, zero external imports
// ─────────────────────────────────────────────

type DeviceStatus string

const (
	StatusOff     DeviceStatus = "off"
	StatusOn      DeviceStatus = "on"
	StatusLocked  DeviceStatus = "locked"
	StatusUnlocked DeviceStatus = "unlocked"
)

type Device struct {
	ID       string
	Name     string
	Type     string // light | thermostat | lock
	Status   DeviceStatus
	Setting  int // brightness (0-100) or temperature (°C)
}

// Domain rule: a locked device cannot be turned on remotely (safety invariant)
func (d *Device) TurnOn() error {
	if d.Status == StatusLocked {
		return fmt.Errorf("device '%s' is locked and cannot be activated remotely", d.ID)
	}
	d.Status = StatusOn
	fmt.Printf("[Device] '%s' turned ON\n", d.ID)
	return nil
}

func (d *Device) TurnOff() {
	d.Status = StatusOff
	fmt.Printf("[Device] '%s' turned OFF\n", d.ID)
}

// matiz: AdjustSetting separates the "what" (change intensity) from the "how" (send to hardware).
// The domain object only mutates its own state; the gateway adapter sends the command to the real device.
func (d *Device) AdjustSetting(value int) error {
	if value < 0 || value > 100 {
		return fmt.Errorf("setting %d out of range [0–100]", value)
	}
	d.Setting = value
	fmt.Printf("[Device] '%s' setting adjusted to %d\n", d.ID, value)
	return nil
}

// ─────────────────────────────────────────────
// PORTS — interfaces owned by the application core
// In Go, a port is just an interface. Any type satisfying it becomes an adapter automatically.
// ─────────────────────────────────────────────

// Primary ports — how external actors drive the application
type ControlDevicePort interface {
	TurnOn(deviceID string) error
	TurnOff(deviceID string) error
	AdjustSetting(deviceID string, value int) error
}

// Secondary ports — how the application drives external systems
type DeviceRepository interface {
	FindByID(id string) (*Device, error)
	Save(d *Device) error
}

// DeviceGateway is the port for communicating with physical IoT devices (MQTT, Z-Wave, Zigbee, etc.)
type DeviceGateway interface {
	SendCommand(deviceID string, command string, payload int) error
}

type AutomationEventPublisher interface {
	Publish(event string, deviceID string) error
}

// ─────────────────────────────────────────────
// APPLICATION SERVICE — implements ControlDevicePort, depends only on secondary ports
// ─────────────────────────────────────────────

type HomeAutomationService struct {
	devices   DeviceRepository
	gateway   DeviceGateway
	publisher AutomationEventPublisher
}

// matiz: the service receives interfaces (secondary ports), not concrete types.
// At compile time Go checks that the passed value satisfies the interface —
// no runtime reflection, no DI framework needed.
func NewHomeAutomationService(
	devices DeviceRepository,
	gateway DeviceGateway,
	publisher AutomationEventPublisher,
) *HomeAutomationService {
	return &HomeAutomationService{devices: devices, gateway: gateway, publisher: publisher}
}

func (s *HomeAutomationService) TurnOn(deviceID string) error {
	fmt.Printf("[HomeAutomationService] Turning on device '%s'\n", deviceID)

	device, err := s.devices.FindByID(deviceID)
	if err != nil {
		return err
	}

	if err := device.TurnOn(); err != nil {
		return err
	}

	if err := s.gateway.SendCommand(deviceID, "POWER_ON", 0); err != nil {
		return fmt.Errorf("gateway error: %w", err)
	}

	_ = s.devices.Save(device)
	_ = s.publisher.Publish("device.turned_on", deviceID)
	return nil
}

func (s *HomeAutomationService) TurnOff(deviceID string) error {
	fmt.Printf("[HomeAutomationService] Turning off device '%s'\n", deviceID)

	device, err := s.devices.FindByID(deviceID)
	if err != nil {
		return err
	}

	device.TurnOff()

	if err := s.gateway.SendCommand(deviceID, "POWER_OFF", 0); err != nil {
		return fmt.Errorf("gateway error: %w", err)
	}

	_ = s.devices.Save(device)
	_ = s.publisher.Publish("device.turned_off", deviceID)
	return nil
}

func (s *HomeAutomationService) AdjustSetting(deviceID string, value int) error {
	fmt.Printf("[HomeAutomationService] Adjusting '%s' to %d\n", deviceID, value)

	device, err := s.devices.FindByID(deviceID)
	if err != nil {
		return err
	}

	if err := device.AdjustSetting(value); err != nil {
		return err
	}

	if err := s.gateway.SendCommand(deviceID, "SET_LEVEL", value); err != nil {
		return fmt.Errorf("gateway error: %w", err)
	}

	_ = s.devices.Save(device)
	return nil
}

// ─────────────────────────────────────────────
// SECONDARY ADAPTERS — driven side
// ─────────────────────────────────────────────

type InMemoryDeviceRepository struct {
	store map[string]*Device
}

func NewInMemoryDeviceRepository(devices ...*Device) *InMemoryDeviceRepository {
	store := make(map[string]*Device)
	for _, d := range devices {
		store[d.ID] = d
	}
	return &InMemoryDeviceRepository{store: store}
}

func (r *InMemoryDeviceRepository) FindByID(id string) (*Device, error) {
	d, ok := r.store[id]
	if !ok {
		return nil, fmt.Errorf("device '%s' not found", id)
	}
	return d, nil
}

func (r *InMemoryDeviceRepository) Save(d *Device) error {
	fmt.Printf("[InMemoryDeviceRepository] Saved '%s' — status: %s setting: %d\n",
		d.ID, d.Status, d.Setting)
	r.store[d.ID] = d
	return nil
}

// MqttDeviceGateway simulates sending commands over MQTT to real IoT hardware
type MqttDeviceGateway struct{}

func (g *MqttDeviceGateway) SendCommand(deviceID, command string, payload int) error {
	fmt.Printf("[MqttDeviceGateway] MQTT publish → topic='home/%s/cmd' payload='{cmd:%s,val:%d}'\n",
		deviceID, command, payload)
	return nil
}

type LogEventPublisher struct{}

func (p *LogEventPublisher) Publish(event, deviceID string) error {
	fmt.Printf("[LogEventPublisher] Event '%s' for device '%s'\n", event, deviceID)
	return nil
}

// ─────────────────────────────────────────────
// PRIMARY ADAPTER — driving side
// ─────────────────────────────────────────────

type SmartHomeHttpHandler struct {
	control ControlDevicePort
}

func (h *SmartHomeHttpHandler) PutDeviceStatus(deviceID, action string, value int) {
	fmt.Printf("[SmartHomeHttpHandler] PUT /devices/%s/status  action='%s' value=%d\n",
		deviceID, action, value)

	var err error
	switch action {
	case "on":
		err = h.control.TurnOn(deviceID)
	case "off":
		err = h.control.TurnOff(deviceID)
	case "adjust":
		err = h.control.AdjustSetting(deviceID, value)
	default:
		fmt.Printf("[SmartHomeHttpHandler] 400 Bad Request — unknown action '%s'\n", action)
		return
	}

	if err != nil {
		fmt.Printf("[SmartHomeHttpHandler] 422 Unprocessable — %v\n", err)
		return
	}
	fmt.Println("[SmartHomeHttpHandler] 200 OK → Command executed")
}

// ─────────────────────────────────────────────
// COMPOSITION ROOT
// ─────────────────────────────────────────────

func main() {
	fmt.Println("=== Hexagonal Architecture — Smart Home Automation (Go) ===\n")

	repo := NewInMemoryDeviceRepository(
		&Device{ID: "living-light-1", Name: "Living Room Light", Type: "light", Status: StatusOff},
		&Device{ID: "thermostat-1",   Name: "Main Thermostat",   Type: "thermostat", Status: StatusOff, Setting: 18},
	)

	gateway   := &MqttDeviceGateway{}
	publisher := &LogEventPublisher{}
	service   := NewHomeAutomationService(repo, gateway, publisher)
	handler   := &SmartHomeHttpHandler{control: service}

	fmt.Println("--- Turn on the living room light ---")
	handler.PutDeviceStatus("living-light-1", "on", 0)

	fmt.Println("\n--- Dim it to 40% brightness ---")
	handler.PutDeviceStatus("living-light-1", "adjust", 40)

	fmt.Println("\n--- Set thermostat to 22°C ---")
	handler.PutDeviceStatus("thermostat-1", "on", 0)
	handler.PutDeviceStatus("thermostat-1", "adjust", 22)

	fmt.Println("\n--- Turn off the light ---")
	handler.PutDeviceStatus("living-light-1", "off", 0)
}
