// Scenario: Real-Time Collaborative Document Editing
//
// A shared document (subject) notifies multiple collaborators
// when its content changes: a cursor tracker, an auto-save service,
// and a conflict detector. Each reacts independently.

// ─── Subject ──────────────────────────────────────────────────────────────────
// SharedDocument is the subject. It holds content and notifies observers
// whenever a user makes a change. It knows nothing about how observers react.
class SharedDocument {
    #content;
    #observers = new Map(); // eventType → Set of observers

    constructor(initialContent = '') {
        this.#content = initialContent;
    }

    // matiz: using event types (not a single generic "update") lets observers
    // subscribe to only the events they care about — a targeted Observer variant.
    // This avoids observers receiving irrelevant events and filtering internally.
    on(eventType, observer) {
        if (!this.#observers.has(eventType)) {
            this.#observers.set(eventType, new Set());
        }
        this.#observers.get(eventType).add(observer);
        console.log(`[SharedDocument] Observer subscribed to "${eventType}": ${observer.constructor.name}`);
    }

    off(eventType, observer) {
        this.#observers.get(eventType)?.delete(observer);
        console.log(`[SharedDocument] Observer removed from "${eventType}": ${observer.constructor.name}`);
    }

    // Emit notifies only observers registered for this specific event type.
    #emit(eventType, payload) {
        const subs = this.#observers.get(eventType);
        if (!subs || subs.size === 0) return;

        console.log(`[SharedDocument] Emitting "${eventType}" to ${subs.size} observer(s)...`);
        for (const observer of subs) {
            observer.onEvent(eventType, payload);
        }
    }

    // ── Public actions that trigger notifications ──────────────────────────────

    applyEdit(userId, position, text) {
        const before = this.#content;
        this.#content =
            this.#content.slice(0, position) + text + this.#content.slice(position);

        console.log(`\n[SharedDocument] User "${userId}" inserted "${text}" at pos ${position}`);

        this.#emit('edit', { userId, position, text, before, after: this.#content });
    }

    moveCursor(userId, position) {
        console.log(`\n[SharedDocument] User "${userId}" moved cursor to ${position}`);
        this.#emit('cursor', { userId, position });
    }

    getContent() {
        return this.#content;
    }
}

// ─── Concrete Observers ───────────────────────────────────────────────────────
// Each observer implements onEvent(type, payload).
// They decide which event types they care about.

class AutoSaveService {
    #pendingChanges = 0;
    #saveThreshold = 3;

    onEvent(eventType, payload) {
        if (eventType !== 'edit') return;

        this.#pendingChanges++;
        console.log(`[AutoSave] Change #${this.#pendingChanges} buffered from user "${payload.userId}"`);

        if (this.#pendingChanges >= this.#saveThreshold) {
            this.#flush();
        }
    }

    #flush() {
        console.log(`[AutoSave] Threshold reached — persisting document to storage...`);
        console.log(`[AutoSave] Document saved successfully.`);
        this.#pendingChanges = 0;
    }
}

class CursorTracker {
    // Tracks where each user's cursor is for the "user presence" feature.
    #cursors = new Map();

    onEvent(eventType, payload) {
        if (eventType === 'cursor') {
            this.#cursors.set(payload.userId, payload.position);
            console.log(`[CursorTracker] ${payload.userId} → position ${payload.position}`);
            this.#renderPresence();
        }

        if (eventType === 'edit') {
            // When a user edits, their cursor implicitly advances.
            const newPos = payload.position + payload.text.length;
            this.#cursors.set(payload.userId, newPos);
            console.log(`[CursorTracker] ${payload.userId} cursor advanced to ${newPos} after edit`);
        }
    }

    #renderPresence() {
        const presence = [...this.#cursors.entries()]
            .map(([user, pos]) => `${user}@${pos}`)
            .join(', ');
        console.log(`[CursorTracker] Active cursors: [${presence}]`);
    }
}

class ConflictDetector {
    // matiz: detecting conflicts requires memory of previous edits.
    // The observer accumulates state across events — it's not stateless.
    // This shows that observers can be stateful without complicating the subject.
    #recentEdits = [];
    #conflictWindowMs = 500;

    onEvent(eventType, payload) {
        if (eventType !== 'edit') return;

        const now = Date.now();
        // Remove edits older than the conflict window.
        this.#recentEdits = this.#recentEdits
            .filter(e => now - e.timestamp < this.#conflictWindowMs);

        // Check if another user edited the same region recently.
        const conflict = this.#recentEdits.find(
            e => e.userId !== payload.userId &&
                 Math.abs(e.position - payload.position) < 10
        );

        if (conflict) {
            console.log(`[ConflictDetector] ⚠ Potential conflict! "${payload.userId}" ` +
                        `and "${conflict.userId}" edited near position ${payload.position}`);
            this.#notifyConflict(payload.userId, conflict.userId);
        } else {
            console.log(`[ConflictDetector] No conflict detected for edit at pos ${payload.position}`);
        }

        this.#recentEdits.push({ ...payload, timestamp: now });
    }

    #notifyConflict(user1, user2) {
        console.log(`[ConflictDetector] Sending conflict warning to ${user1} and ${user2}...`);
    }
}

// ─── Main ─────────────────────────────────────────────────────────────────────
console.log('=== Real-Time Collaborative Document — Observer Pattern ===\n');

const doc = new SharedDocument('Hello ');

const autoSave        = new AutoSaveService();
const cursorTracker   = new CursorTracker();
const conflictDetector = new ConflictDetector();

// Observers subscribe to specific event types — not all events.
doc.on('edit',   autoSave);
doc.on('edit',   cursorTracker);
doc.on('edit',   conflictDetector);
doc.on('cursor', cursorTracker);

// Alice types at position 6.
doc.applyEdit('alice', 6, 'World');

// Bob moves his cursor.
doc.moveCursor('bob', 4);

// Bob edits near Alice's position — conflict detector fires.
doc.applyEdit('bob', 5, '!');

// Alice makes two more edits — auto-save threshold reached on the 3rd total edit.
doc.applyEdit('alice', 12, ' How');
doc.applyEdit('alice', 16, ' are you?');

console.log(`\n[SharedDocument] Final content: "${doc.getContent()}"`);
