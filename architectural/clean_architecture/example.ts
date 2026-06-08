// Clean Architecture — Personal Finance Tracker (TypeScript)
// Run: ts-node example.ts
//
// Four concentric circles — the Dependency Rule applies strictly:
// Entities ← Use Cases ← Interface Adapters ← Frameworks & Drivers
// Dependencies point inward ONLY.

// ─────────────────────────────────────────────
// RING 1: ENTITIES — enterprise-wide business rules
// ─────────────────────────────────────────────

type ExpenseCategory = "food" | "transport" | "housing" | "entertainment" | "health" | "other";

class Budget {
  private _spent: number = 0;

  constructor(
    public readonly id: string,
    public readonly month: string, // "YYYY-MM"
    public readonly category: ExpenseCategory,
    public readonly limitAmount: number
  ) {
    if (limitAmount <= 0) {
      throw new Error(`Budget '${id}' must have a positive limit.`);
    }
  }

  // Business rule: spending cannot exceed the budget limit
  addExpense(amount: number): void {
    if (amount <= 0) throw new Error("Expense amount must be positive.");
    if (this._spent + amount > this.limitAmount) {
      throw new Error(
        `Budget '${this.id}' (${this.category}) would exceed limit: ` +
        `${this._spent + amount} > ${this.limitAmount}`
      );
    }
    this._spent += amount;
    console.log(`[Budget] '${this.id}' — spent ${this._spent}/${this.limitAmount} in ${this.category}`);
  }

  // matiz: remaining() is a derived entity value — it belongs here, not in a use case.
  // Any code that knows about a Budget can compute this without extra orchestration.
  remaining(): number { return this.limitAmount - this._spent; }

  get spent(): number { return this._spent; }
}

class Expense {
  constructor(
    public readonly id:          string,
    public readonly budgetId:    string,
    public readonly description: string,
    public readonly amount:      number,
    public readonly date:        string
  ) {}
}

// ─────────────────────────────────────────────
// RING 2: USE CASES — application-specific business rules
// ─────────────────────────────────────────────

// Boundary data structures — typed, immutable DTOs
interface CreateBudgetInput  { month: string; category: ExpenseCategory; limitAmount: number; }
interface CreateBudgetOutput { budgetId: string; category: string; limit: number; }

interface RecordExpenseInput  { budgetId: string; description: string; amount: number; date: string; }
interface RecordExpenseOutput { expenseId: string; description: string; amount: number; remaining: number; }

// Output ports — Presenters implement these
interface CreateBudgetOutputPort {
  presentSuccess(output: CreateBudgetOutput): void;
  presentError(message: string): void;
}

interface RecordExpenseOutputPort {
  presentSuccess(output: RecordExpenseOutput): void;
  presentError(message: string): void;
}

// Input ports — Interactors implement these
interface CreateBudgetInputPort  { execute(input: CreateBudgetInput): void; }
interface RecordExpenseInputPort { execute(input: RecordExpenseInput): void; }

// Gateways (defined in ring 2, implemented in ring 3/4)
interface BudgetGateway {
  nextId(): string;
  save(budget: Budget): Promise<void>;
  findById(id: string): Promise<Budget | null>;
}

interface ExpenseGateway {
  nextId(): string;
  save(expense: Expense): Promise<void>;
}

// Interactors
class CreateBudgetInteractor implements CreateBudgetInputPort {
  constructor(
    private readonly budgets:   BudgetGateway,
    private readonly presenter: CreateBudgetOutputPort
  ) {}

  execute(input: CreateBudgetInput): void {
    console.log(`[CreateBudgetInteractor] Creating ${input.category} budget for ${input.month}`);

    let budget: Budget;
    try {
      budget = new Budget(this.budgets.nextId(), input.month, input.category, input.limitAmount);
    } catch (e: any) {
      this.presenter.presentError(e.message);
      return;
    }

    this.budgets.save(budget);
    // matiz: the interactor pushes to the output port — never returns a value.
    // "Use case as a pump" — data flows in through the input port, out through the output port.
    this.presenter.presentSuccess({
      budgetId:  budget.id,
      category:  budget.category,
      limit:     budget.limitAmount,
    });
  }
}

class RecordExpenseInteractor implements RecordExpenseInputPort {
  constructor(
    private readonly budgets:   BudgetGateway,
    private readonly expenses:  ExpenseGateway,
    private readonly presenter: RecordExpenseOutputPort
  ) {}

  execute(input: RecordExpenseInput): void {
    console.log(`[RecordExpenseInteractor] Recording '${input.description}' — $${input.amount}`);

    const budget = this.budgets.findById(input.budgetId) as unknown as Budget | null;
    if (!budget) {
      this.presenter.presentError(`Budget '${input.budgetId}' not found.`);
      return;
    }

    try {
      budget.addExpense(input.amount); // entity enforces the limit rule
    } catch (e: any) {
      this.presenter.presentError(e.message);
      return;
    }

    const expense = new Expense(
      this.expenses.nextId(),
      input.budgetId,
      input.description,
      input.amount,
      input.date
    );

    this.budgets.save(budget);
    this.expenses.save(expense);

    this.presenter.presentSuccess({
      expenseId:   expense.id,
      description: expense.description,
      amount:      expense.amount,
      remaining:   budget.remaining(),
    });
  }
}

// ─────────────────────────────────────────────
// RING 3: INTERFACE ADAPTERS — Gateways, Presenters, Controllers
// ─────────────────────────────────────────────

// Gateways
class InMemoryBudgetGateway implements BudgetGateway {
  private store = new Map<string, Budget>();
  private seq   = 1;

  nextId(): string { return `bud-${this.seq++}`; }

  async save(budget: Budget): Promise<void> {
    console.log(`[InMemoryBudgetGateway] Saved '${budget.id}' — remaining: ${budget.remaining()}`);
    this.store.set(budget.id, budget);
  }

  async findById(id: string): Promise<Budget | null> {
    return this.store.get(id) ?? null;
  }
}

class InMemoryExpenseGateway implements ExpenseGateway {
  private seq = 1;

  nextId(): string { return `exp-${this.seq++}`; }

  async save(expense: Expense): Promise<void> {
    console.log(`[InMemoryExpenseGateway] Saved '${expense.id}' — ${expense.description} $${expense.amount}`);
  }
}

// Presenters — shape output data into view models for the delivery mechanism
class JsonBudgetPresenter implements CreateBudgetOutputPort {
  viewModel: object | null = null;

  presentSuccess(output: CreateBudgetOutput): void {
    this.viewModel = { ...output, status: "created" };
    console.log(`[JsonBudgetPresenter] ✓ ViewModel: ${JSON.stringify(this.viewModel)}`);
  }

  presentError(message: string): void {
    this.viewModel = { error: message };
    console.log(`[JsonBudgetPresenter] ✗ ViewModel: ${JSON.stringify(this.viewModel)}`);
  }
}

class JsonExpensePresenter implements RecordExpenseOutputPort {
  viewModel: object | null = null;

  presentSuccess(output: RecordExpenseOutput): void {
    this.viewModel = { ...output, status: "recorded" };
    console.log(`[JsonExpensePresenter] ✓ ViewModel: ${JSON.stringify(this.viewModel)}`);
  }

  presentError(message: string): void {
    this.viewModel = { error: message };
    console.log(`[JsonExpensePresenter] ✗ ViewModel: ${JSON.stringify(this.viewModel)}`);
  }
}

// Controllers — translate HTTP input into use-case InputData
class BudgetController {
  constructor(
    private readonly createPort:      CreateBudgetInputPort,
    private readonly budgetPresenter: JsonBudgetPresenter
  ) {}

  postBudget(body: CreateBudgetInput): void {
    console.log(`[BudgetController] POST /budgets  category='${body.category}'`);
    this.createPort.execute(body);
    const status = (this.budgetPresenter.viewModel as any)?.error ? 422 : 201;
    console.log(`[BudgetController] HTTP ${status} → ${JSON.stringify(this.budgetPresenter.viewModel)}`);
  }
}

class ExpenseController {
  constructor(private readonly recordPort: RecordExpenseInputPort) {}

  postExpense(body: RecordExpenseInput): void {
    console.log(`[ExpenseController] POST /expenses  description='${body.description}'`);
    this.recordPort.execute(body);
  }
}

// ─────────────────────────────────────────────
// RING 4: FRAMEWORKS & DRIVERS — Composition Root
// ─────────────────────────────────────────────

function main() {
  console.log("=== Clean Architecture — Personal Finance Tracker (TypeScript) ===\n");

  const budgetGateway  = new InMemoryBudgetGateway();
  const expenseGateway = new InMemoryExpenseGateway();

  const budgetPresenter  = new JsonBudgetPresenter();
  const expensePresenter = new JsonExpensePresenter();

  const createBudgetInteractor = new CreateBudgetInteractor(budgetGateway, budgetPresenter);
  const recordExpenseInteractor = new RecordExpenseInteractor(budgetGateway, expenseGateway, expensePresenter);

  const budgetCtrl  = new BudgetController(createBudgetInteractor, budgetPresenter);
  const expenseCtrl = new ExpenseController(recordExpenseInteractor);

  console.log("--- User creates a food budget for June ---");
  budgetCtrl.postBudget({ month: "2026-06", category: "food", limitAmount: 300 });

  const budgetId = (budgetPresenter.viewModel as any)?.budgetId as string;

  console.log("\n--- User records a grocery expense ---");
  expenseCtrl.postExpense({ budgetId, description: "Weekly groceries", amount: 85, date: "2026-06-08" });

  console.log("\n--- User records another expense ---");
  expenseCtrl.postExpense({ budgetId, description: "Restaurant dinner", amount: 60, date: "2026-06-09" });

  console.log("\n--- User tries to overspend (entity rule) ---");
  expenseCtrl.postExpense({ budgetId, description: "Catering event", amount: 200, date: "2026-06-10" });

  console.log("\n--- User tries to create a budget with zero limit (entity rule) ---");
  budgetCtrl.postBudget({ month: "2026-06", category: "transport", limitAmount: 0 });
}

main();
