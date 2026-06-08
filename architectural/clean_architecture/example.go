// Clean Architecture — E-Commerce Order Fulfillment System (Go)
//
// Run: go run example.go
//
// The Dependency Rule: source code dependencies can only point inward.
// Outer rings (adapters, DB, HTTP) import inner rings (use cases, entities).
// Inner rings never import outer rings.

package main

import (
	"fmt"
	"strings"
)

// ─────────────────────────────────────────────
// RING 1: ENTITIES — enterprise-wide business rules
// ─────────────────────────────────────────────

type OrderStatus string

const (
	OrderPending    OrderStatus = "pending"
	OrderConfirmed  OrderStatus = "confirmed"
	OrderShipped    OrderStatus = "shipped"
	OrderDelivered  OrderStatus = "delivered"
	OrderCancelled  OrderStatus = "cancelled"
)

type OrderItem struct {
	SKU      string
	Name     string
	Quantity int
	UnitPrice float64
}

func (i OrderItem) Subtotal() float64 { return float64(i.Quantity) * i.UnitPrice }

type Order struct {
	ID         string
	CustomerID string
	Items      []OrderItem
	Status     OrderStatus
}

func NewOrder(id, customerID string, items []OrderItem) (*Order, error) {
	if len(items) == 0 {
		return nil, fmt.Errorf("order '%s' must have at least one item", id)
	}
	return &Order{ID: id, CustomerID: customerID, Items: items, Status: OrderPending}, nil
}

func (o *Order) Total() float64 {
	total := 0.0
	for _, item := range o.Items {
		total += item.Subtotal()
	}
	return total
}

// Business rule: only a confirmed order can be shipped
func (o *Order) Confirm() error {
	if o.Status != OrderPending {
		return fmt.Errorf("order '%s' is '%s', cannot confirm", o.ID, o.Status)
	}
	o.Status = OrderConfirmed
	fmt.Printf("[Order] '%s' confirmed — total: $%.2f\n", o.ID, o.Total())
	return nil
}

// matiz: Ship is a domain method. It encapsulates the transition rule.
// Neither the interactor nor the adapter knows WHEN it is valid to ship —
// only the entity does. This is where business logic truly belongs.
func (o *Order) Ship() error {
	if o.Status != OrderConfirmed {
		return fmt.Errorf("order '%s' must be confirmed before shipping (current: '%s')", o.ID, o.Status)
	}
	o.Status = OrderShipped
	fmt.Printf("[Order] '%s' marked as shipped\n", o.ID)
	return nil
}

// ─────────────────────────────────────────────
// RING 2: USE CASES — application-specific business rules
// ─────────────────────────────────────────────

// Boundary data structures
type PlaceOrderInput struct {
	CustomerID string
	Items      []OrderItem
}

type PlaceOrderOutput struct {
	OrderID    string
	Status     string
	TotalUsd   float64
	ItemCount  int
}

type ShipOrderInput struct {
	OrderID string
}

type ShipOrderOutput struct {
	OrderID    string
	CustomerID string
	Status     string
	TrackingNo string
}

// Output ports — Presenters implement these; Interactors call them
type PlaceOrderOutputPort interface {
	PresentSuccess(output PlaceOrderOutput)
	PresentError(message string)
}

type ShipOrderOutputPort interface {
	PresentSuccess(output ShipOrderOutput)
	PresentError(message string)
}

// Input ports — Interactors implement these; Controllers call them
type PlaceOrderInputPort interface {
	Execute(input PlaceOrderInput)
}

type ShipOrderInputPort interface {
	Execute(input ShipOrderInput)
}

// Gateways (defined in ring 2, implemented in ring 3/4)
type OrderGateway interface {
	NextID() string
	Save(order *Order) error
	FindByID(id string) (*Order, error)
}

type ShippingGateway interface {
	CreateShipment(orderID, customerID string) (trackingNo string, err error)
}

// Interactors
type PlaceOrderInteractor struct {
	orders    OrderGateway
	presenter PlaceOrderOutputPort
}

func (i *PlaceOrderInteractor) Execute(input PlaceOrderInput) {
	fmt.Printf("[PlaceOrderInteractor] Placing order for customer '%s'\n", input.CustomerID)

	order, err := NewOrder(i.orders.NextID(), input.CustomerID, input.Items)
	if err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	if err := order.Confirm(); err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	if err := i.orders.Save(order); err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	fmt.Println("[PlaceOrderInteractor] Order persisted — pushing to presenter")
	// matiz: the interactor calls the output port to deliver results.
	// It never returns a value. The boundary crossing is explicit and typed.
	i.presenter.PresentSuccess(PlaceOrderOutput{
		OrderID:   order.ID,
		Status:    string(order.Status),
		TotalUsd:  order.Total(),
		ItemCount: len(order.Items),
	})
}

type ShipOrderInteractor struct {
	orders   OrderGateway
	shipping ShippingGateway
	presenter ShipOrderOutputPort
}

func (i *ShipOrderInteractor) Execute(input ShipOrderInput) {
	fmt.Printf("[ShipOrderInteractor] Shipping order '%s'\n", input.OrderID)

	order, err := i.orders.FindByID(input.OrderID)
	if err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	if err := order.Ship(); err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	trackingNo, err := i.shipping.CreateShipment(order.ID, order.CustomerID)
	if err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	if err := i.orders.Save(order); err != nil {
		i.presenter.PresentError(err.Error())
		return
	}

	i.presenter.PresentSuccess(ShipOrderOutput{
		OrderID:    order.ID,
		CustomerID: order.CustomerID,
		Status:     string(order.Status),
		TrackingNo: trackingNo,
	})
}

// ─────────────────────────────────────────────
// RING 3: INTERFACE ADAPTERS — Gateways, Presenters, Controllers
// ─────────────────────────────────────────────

type InMemoryOrderGateway struct {
	store map[string]*Order
	seq   int
}

func NewInMemoryOrderGateway() *InMemoryOrderGateway {
	return &InMemoryOrderGateway{store: make(map[string]*Order), seq: 1}
}

func (g *InMemoryOrderGateway) NextID() string {
	id := fmt.Sprintf("ord-%03d", g.seq)
	g.seq++
	return id
}

func (g *InMemoryOrderGateway) Save(order *Order) error {
	fmt.Printf("[InMemoryOrderGateway] Saved '%s' — status: %s\n", order.ID, order.Status)
	g.store[order.ID] = order
	return nil
}

func (g *InMemoryOrderGateway) FindByID(id string) (*Order, error) {
	order, ok := g.store[id]
	if !ok {
		return nil, fmt.Errorf("order '%s' not found", id)
	}
	return order, nil
}

type MockShippingGateway struct{ seq int }

func (g *MockShippingGateway) CreateShipment(orderID, customerID string) (string, error) {
	g.seq++
	tracking := fmt.Sprintf("TRK-%s-%03d", strings.ToUpper(orderID), g.seq)
	fmt.Printf("[MockShippingGateway] Shipment created — tracking: %s\n", tracking)
	return tracking, nil
}

// Presenters convert OutputData into view models
type ConsolePlaceOrderPresenter struct{ ViewModel map[string]interface{} }

func (p *ConsolePlaceOrderPresenter) PresentSuccess(output PlaceOrderOutput) {
	p.ViewModel = map[string]interface{}{
		"order_id":   output.OrderID,
		"status":     output.Status,
		"total_usd":  fmt.Sprintf("%.2f", output.TotalUsd),
		"item_count": output.ItemCount,
	}
	fmt.Printf("[ConsolePlaceOrderPresenter] ✓ Order placed: %v\n", p.ViewModel)
}

func (p *ConsolePlaceOrderPresenter) PresentError(message string) {
	p.ViewModel = map[string]interface{}{"error": message}
	fmt.Printf("[ConsolePlaceOrderPresenter] ✗ Error: %s\n", message)
}

type ConsoleShipOrderPresenter struct{}

func (p *ConsoleShipOrderPresenter) PresentSuccess(output ShipOrderOutput) {
	fmt.Printf("[ConsoleShipOrderPresenter] ✓ Shipped: orderID=%s tracking=%s\n",
		output.OrderID, output.TrackingNo)
}

func (p *ConsoleShipOrderPresenter) PresentError(message string) {
	fmt.Printf("[ConsoleShipOrderPresenter] ✗ Error: %s\n", message)
}

// Controllers
type OrderController struct {
	placePort    PlaceOrderInputPort
	shipPort     ShipOrderInputPort
	placePresent *ConsolePlaceOrderPresenter
}

func (c *OrderController) PostOrder(customerID string, items []OrderItem) {
	fmt.Printf("[OrderController] POST /orders  customer='%s'\n", customerID)
	c.placePort.Execute(PlaceOrderInput{CustomerID: customerID, Items: items})
}

func (c *OrderController) GetLastOrderID() string {
	vm := c.placePresent.ViewModel
	if vm == nil {
		return ""
	}
	id, _ := vm["order_id"].(string)
	return id
}

func (c *OrderController) PostShip(orderID string) {
	fmt.Printf("[OrderController] POST /orders/%s/ship\n", orderID)
	c.shipPort.Execute(ShipOrderInput{OrderID: orderID})
}

// ─────────────────────────────────────────────
// RING 4: FRAMEWORKS & DRIVERS — Composition Root
// ─────────────────────────────────────────────

func main() {
	fmt.Println("=== Clean Architecture — E-Commerce Order Fulfillment (Go) ===\n")

	orderGateway   := NewInMemoryOrderGateway()
	shippingGateway := &MockShippingGateway{}

	placePresenter := &ConsolePlaceOrderPresenter{}
	shipPresenter  := &ConsoleShipOrderPresenter{}

	placeInteractor := &PlaceOrderInteractor{orders: orderGateway, presenter: placePresenter}
	shipInteractor  := &ShipOrderInteractor{orders: orderGateway, shipping: shippingGateway, presenter: shipPresenter}

	ctrl := &OrderController{
		placePort:    placeInteractor,
		shipPort:     shipInteractor,
		placePresent: placePresenter,
	}

	fmt.Println("--- Customer places an order ---")
	ctrl.PostOrder("cust-007", []OrderItem{
		{SKU: "SKU-A1", Name: "Mechanical Keyboard", Quantity: 1, UnitPrice: 129.99},
		{SKU: "SKU-B3", Name: "USB-C Hub",           Quantity: 2, UnitPrice: 39.99},
	})

	orderID := ctrl.GetLastOrderID()

	fmt.Println("\n--- Warehouse ships the order ---")
	ctrl.PostShip(orderID)

	fmt.Println("\n--- Try to ship it again (entity rule) ---")
	ctrl.PostShip(orderID)

	fmt.Println("\n--- Try to place an empty order (entity rule) ---")
	ctrl.PostOrder("cust-008", []OrderItem{})
}
