// Scenario: project management task tracker.
// The TaskService manages tasks — it should not know whether data
// comes from a SQL database, a REST API, or an in-memory store.

// Domain object — plain class, no ORM dependency
class Task {
  constructor(
    public readonly id: number,
    public readonly title: string,
    public readonly assigneeId: string,
    public readonly status: "todo" | "in_progress" | "done",
    public readonly priority: "low" | "medium" | "high",
    public readonly projectId: number
  ) {}
}

// The Repository interface — domain-oriented, not database-oriented
interface TaskRepository {
  findById(id: number): Promise<Task | null>;
  findByAssignee(assigneeId: string): Promise<Task[]>;
  findByProject(projectId: number): Promise<Task[]>;
  findByStatus(status: Task["status"]): Promise<Task[]>;
  save(task: Task): Promise<void>;
  delete(id: number): Promise<void>;
}

// Concrete implementation: SQL API — simulates a database driver
class SqlTaskRepository implements TaskRepository {
  async findById(id: number): Promise<Task | null> {
    console.log(`[SqlTaskRepository] SELECT * FROM tasks WHERE id = ${id}`);
    return new Task(id, "Implement login page", "user_42", "in_progress", "high", 1);
  }

  async findByAssignee(assigneeId: string): Promise<Task[]> {
    console.log(`[SqlTaskRepository] SELECT * FROM tasks WHERE assignee_id = '${assigneeId}'`);
    return [
      new Task(1, "Implement login page", assigneeId, "in_progress", "high",   1),
      new Task(2, "Write unit tests",     assigneeId, "todo",        "medium", 1),
    ];
  }

  async findByProject(projectId: number): Promise<Task[]> {
    console.log(`[SqlTaskRepository] SELECT * FROM tasks WHERE project_id = ${projectId} ORDER BY priority`);
    return [
      new Task(1, "Implement login page", "user_42", "in_progress", "high",   projectId),
      new Task(2, "Write unit tests",     "user_42", "todo",        "medium", projectId),
      new Task(3, "Deploy to staging",    "user_7",  "todo",        "high",   projectId),
    ];
  }

  async findByStatus(status: Task["status"]): Promise<Task[]> {
    console.log(`[SqlTaskRepository] SELECT * FROM tasks WHERE status = '${status}'`);
    return [new Task(1, "Implement login page", "user_42", status, "high", 1)];
  }

  async save(task: Task): Promise<void> {
    console.log(`[SqlTaskRepository] INSERT/UPDATE task '${task.title}'`);
  }

  async delete(id: number): Promise<void> {
    console.log(`[SqlTaskRepository] DELETE FROM tasks WHERE id = ${id}`);
  }
}

// matiz: a CachingTaskRepository wraps another repository and adds transparent caching.
// The TaskService injects a TaskRepository — it has no idea caching is happening.
// This is the Decorator pattern applied to a repository: behavior added without
// modifying the original implementation or the service that uses it.
class CachingTaskRepository implements TaskRepository {
  private cache = new Map<string, Task[]>();

  constructor(private readonly inner: TaskRepository) {}

  async findByProject(projectId: number): Promise<Task[]> {
    const key = `project:${projectId}`;
    if (this.cache.has(key)) {
      console.log(`[CachingTaskRepository] Cache HIT for project ${projectId}`);
      return this.cache.get(key)!;
    }
    console.log(`[CachingTaskRepository] Cache MISS for project ${projectId} — fetching...`);
    const tasks = await this.inner.findByProject(projectId);
    this.cache.set(key, tasks);
    return tasks;
  }

  // Delegates everything else directly to the inner repository
  findById(id: number)                   { return this.inner.findById(id); }
  findByAssignee(assigneeId: string)     { return this.inner.findByAssignee(assigneeId); }
  findByStatus(status: Task["status"])   { return this.inner.findByStatus(status); }
  save(task: Task)                       { this.cache.clear(); return this.inner.save(task); }
  delete(id: number)                     { this.cache.clear(); return this.inner.delete(id); }
}

// The Service — depends only on TaskRepository, knows nothing about SQL or caching
class TaskService {
  constructor(private readonly tasks: TaskRepository) {}

  async getTaskDetails(id: number): Promise<void> {
    console.log(`\n[TaskService] Fetching task #${id}...`);
    const task = await this.tasks.findById(id);
    if (task) {
      console.log(`[TaskService] "${task.title}" | ${task.status} | priority: ${task.priority}`);
    } else {
      console.log(`[TaskService] Task not found.`);
    }
  }

  async listProjectTasks(projectId: number): Promise<void> {
    console.log(`\n[TaskService] Loading tasks for project #${projectId}...`);
    const tasks = await this.tasks.findByProject(projectId);
    tasks.forEach(t => console.log(`[TaskService]   [${t.priority}] ${t.title} → ${t.status}`));
  }
}

(async () => {
  console.log("=== Production (SQL) ===");
  const sqlRepo    = new SqlTaskRepository();
  const cachedRepo = new CachingTaskRepository(sqlRepo);
  const service    = new TaskService(cachedRepo);

  await service.getTaskDetails(1);
  await service.listProjectTasks(1);   // cache MISS — hits SQL
  await service.listProjectTasks(1);   // cache HIT — no SQL
})();
