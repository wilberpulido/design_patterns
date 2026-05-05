package main

import "fmt"

/**
 * Scenario: Restaurant Menu Manager
 *
 * Kitchen staff mark items as unavailable when they run out.
 * Diners see only available items. Multiple views show the same menu.
 */

// ─── Model ────────────────────────────────────────────────────────────────────
// The model owns the data and the rules. It does not print anything.

type Category string

const (
	Starter    Category = "starter"
	MainCourse Category = "main"
	Dessert    Category = "dessert"
	Beverage   Category = "beverage"
)

type MenuItem struct {
	ID        int
	Name      string
	Price     float64
	Category  Category
	Available bool
}

type Menu struct {
	items  []*MenuItem
	nextID int
}

func NewMenu() *Menu {
	return &Menu{nextID: 1}
}

func (m *Menu) Add(name string, price float64, category Category) {
	m.items = append(m.items, &MenuItem{
		ID:        m.nextID,
		Name:      name,
		Price:     price,
		Category:  category,
		Available: true,
	})
	m.nextID++
}

func (m *Menu) FindByID(id int) *MenuItem {
	for _, item := range m.items {
		if item.ID == id {
			return item
		}
	}
	return nil
}

func (m *Menu) FindByCategory(cat Category) []*MenuItem {
	var result []*MenuItem
	for _, item := range m.items {
		if item.Category == cat {
			result = append(result, item)
		}
	}
	return result
}

func (m *Menu) All() []*MenuItem { return m.items }

// ─── View ─────────────────────────────────────────────────────────────────────
// matiz: in Go there are no classes, so a View is a struct with methods.
// You could also use plain functions — the choice depends on whether the view
// needs configuration state (e.g. a color theme, a locale).
// A struct keeps the door open without committing to stored state yet.

type MenuView struct{}

func (v *MenuView) RenderMenu(items []*MenuItem, title string) {
	fmt.Printf("\n┌─ %s (%d items)\n", title, len(items))
	for _, item := range items {
		avail := "✓"
		if !item.Available {
			avail = "✗"
		}
		fmt.Printf("│  %s #%-3d %-26s $%6.2f  [%s]\n",
			avail, item.ID, item.Name, item.Price, item.Category)
	}
	if len(items) == 0 {
		fmt.Println("│  (no items)")
	}
	fmt.Println("└─")
}

func (v *MenuView) RenderAvailabilityChange(item *MenuItem) {
	status := "available"
	if !item.Available {
		status = "unavailable (86'd)"
	}
	fmt.Printf("[MenuView] '%s' is now %s.\n", item.Name, status)
}

func (v *MenuView) RenderError(msg string) {
	fmt.Printf("[MenuView] ERROR: %s\n", msg)
}

// ─── Controller ───────────────────────────────────────────────────────────────

type MenuController struct {
	menu *Menu
	view *MenuView
}

func NewMenuController(menu *Menu, view *MenuView) *MenuController {
	return &MenuController{menu: menu, view: view}
}

func (c *MenuController) ShowFullMenu() {
	fmt.Println("[MenuController] Handling: show full menu")
	c.view.RenderMenu(c.menu.All(), "Full Menu")
}

func (c *MenuController) ShowCategory(cat Category) {
	fmt.Printf("[MenuController] Handling: show category '%s'\n", cat)
	c.view.RenderMenu(c.menu.FindByCategory(cat), string(cat)+" items")
}

func (c *MenuController) SetAvailability(id int, available bool) {
	action := "enable"
	if !available {
		action = "86"
	}
	fmt.Printf("[MenuController] Handling: %s item #%d\n", action, id)
	item := c.menu.FindByID(id)
	if item == nil {
		c.view.RenderError(fmt.Sprintf("Item #%d not found.", id))
		return
	}
	item.Available = available
	c.view.RenderAvailabilityChange(item)
}

// ─── Entry point ──────────────────────────────────────────────────────────────

func main() {
	fmt.Println("=== Restaurant Menu Manager — MVC Pattern ===\n")

	menu := NewMenu()
	view := &MenuView{}
	ctrl := NewMenuController(menu, view)

	menu.Add("Caesar Salad",        8.50, Starter)
	menu.Add("Bruschetta",          7.00, Starter)
	menu.Add("Grilled Salmon",     24.00, MainCourse)
	menu.Add("Ribeye Steak",       38.00, MainCourse)
	menu.Add("Mushroom Risotto",   18.00, MainCourse)
	menu.Add("Tiramisu",            9.00, Dessert)
	menu.Add("Espresso",            3.50, Beverage)
	menu.Add("House Wine (glass)",  7.00, Beverage)

	ctrl.ShowFullMenu()
	ctrl.ShowCategory(MainCourse)

	// Kitchen runs out of salmon — "86" means mark as unavailable in restaurant slang
	ctrl.SetAvailability(3, false)
	ctrl.ShowCategory(MainCourse) // salmon now marked unavailable

	ctrl.SetAvailability(99, false) // item not found

	// Salmon is restocked
	ctrl.SetAvailability(3, true)
	ctrl.ShowCategory(MainCourse)
}
