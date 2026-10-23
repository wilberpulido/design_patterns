/**
 * Scenario: Movie Review App
 *
 * Users browse movies, read reviews, add ratings,
 * and get top-rated recommendations.
 */

// ─── Model ────────────────────────────────────────────────────────────────────
// The model holds data and enforces business rules.
// It has no knowledge of how data will be displayed.

interface Rating {
    user: string;
    score: number;
}

class Movie {
    readonly ratings: Rating[] = [];

    constructor(
        readonly id: number,
        readonly title: string,
        readonly year: number,
        readonly genre: string,
    ) {}

    addRating(user: string, score: number): void {
        // Business rule: valid score range. The controller delegates here.
        if (score < 1 || score > 10) {
            throw new Error(`Rating must be between 1 and 10, got ${score}`);
        }
        this.ratings.push({ user, score });
    }

    // `number | null`: the return type forces every caller to handle "no ratings yet".
    averageRating(): number | null {
        if (this.ratings.length === 0) return null;
        return this.ratings.reduce((sum, r) => sum + r.score, 0) / this.ratings.length;
    }
}

// matiz: MovieStore is an in-memory repository.
// The controller never talks to Movie objects directly for lookups —
// it always goes through the store. This means swapping to a real DB
// (e.g. adding async findById() that queries Postgres) only changes the store,
// not the Movie class and not the controller's interface.
class MovieStore {
    private readonly movies = new Map<number, Movie>();
    private nextId = 1;

    add(title: string, year: number, genre: string): Movie {
        const movie = new Movie(this.nextId++, title, year, genre);
        this.movies.set(movie.id, movie);
        return movie;
    }

    findById(id: number): Movie | null { return this.movies.get(id) ?? null; }
    findAll(): Movie[]                 { return [...this.movies.values()]; }

    findByGenre(genre: string): Movie[] {
        return this.findAll().filter(
            m => m.genre.toLowerCase() === genre.toLowerCase()
        );
    }

    topRated(n = 5): Movie[] {
        return this.findAll()
            .filter(m => m.averageRating() !== null)
            // `!`: the filter above already dropped the movies without ratings.
            .sort((a, b) => b.averageRating()! - a.averageRating()!)
            .slice(0, n);
    }
}

// ─── View ─────────────────────────────────────────────────────────────────────

class MovieView {
    renderList(movies: Movie[], title: string): void {
        console.log(`\n┌─ ${title} (${movies.length} movies)`);
        if (movies.length === 0) {
            console.log('│  (no movies)');
        }
        for (const m of movies) {
            const avg    = m.averageRating();
            const rating = avg !== null ? `${avg.toFixed(1)}/10` : 'no ratings';
            console.log(`│  #${m.id} [${m.year}] ${m.title} (${m.genre}) — ${rating}`);
        }
        console.log('└─');
    }

    renderMovie(movie: Movie): void {
        const avg = movie.averageRating();
        console.log(`\n┌─ Movie Detail`);
        console.log(`│  Title:   ${movie.title} (${movie.year})`);
        console.log(`│  Genre:   ${movie.genre}`);
        console.log(`│  Rating:  ${avg !== null ? avg.toFixed(1) + '/10' : 'no ratings'} (${movie.ratings.length} reviews)`);
        if (movie.ratings.length > 0) {
            console.log('│  Reviews:');
            for (const r of movie.ratings) {
                console.log(`│    ${r.user}: ${r.score}/10`);
            }
        }
        console.log('└─');
    }

    renderRatingAdded(movie: Movie, user: string, score: number): void {
        console.log(`[MovieView] ${user} rated "${movie.title}" ${score}/10`);
    }

    renderError(message: string): void {
        console.log(`[MovieView] ERROR: ${message}`);
    }
}

// ─── Controller ───────────────────────────────────────────────────────────────
// The controller handles user actions. It calls the store/model,
// catches domain errors, and decides which view method to invoke.
// It contains no formatting logic and no score-calculation logic.

class MovieController {
    constructor(
        private readonly store: MovieStore,
        private readonly view: MovieView,
    ) {}

    listAll(): void {
        console.log('[MovieController] Handling: list all movies');
        this.view.renderList(this.store.findAll(), 'All Movies');
    }

    listByGenre(genre: string): void {
        console.log(`[MovieController] Handling: list genre '${genre}'`);
        this.view.renderList(this.store.findByGenre(genre), `Genre: ${genre}`);
    }

    showDetail(id: number): void {
        console.log(`[MovieController] Handling: show movie #${id}`);
        const movie = this.store.findById(id);
        if (!movie) {
            this.view.renderError(`Movie #${id} not found.`);
            return;
        }
        this.view.renderMovie(movie);
    }

    addRating(movieId: number, user: string, score: number): void {
        console.log(`[MovieController] Handling: add rating — movie #${movieId} by ${user}`);
        const movie = this.store.findById(movieId);
        if (!movie) {
            this.view.renderError(`Movie #${movieId} not found.`);
            return;
        }
        try {
            movie.addRating(user, score); // business rule enforced by the model
            this.view.renderRatingAdded(movie, user, score);
        } catch (err) {
            // Strict mode types a caught value as `unknown`: narrow it before using .message.
            this.view.renderError(err instanceof Error ? err.message : String(err));
        }
    }

    topRated(n = 5): void {
        console.log(`[MovieController] Handling: top ${n} rated movies`);
        this.view.renderList(this.store.topRated(n), `Top ${n} Rated`);
    }
}

// ─── Entry point ──────────────────────────────────────────────────────────────

console.log('=== Movie Review App — MVC Pattern ===\n');

const store      = new MovieStore();
const view       = new MovieView();
const controller = new MovieController(store, view);

store.add('The Shawshank Redemption', 1994, 'Drama');
store.add('Inception',                2010, 'Sci-Fi');
store.add('The Dark Knight',          2008, 'Action');
store.add('Parasite',                 2019, 'Thriller');
store.add('Interstellar',             2014, 'Sci-Fi');

controller.listAll();
controller.listByGenre('sci-fi');

controller.addRating(1, 'alice',  9);
controller.addRating(1, 'bob',   10);
controller.addRating(2, 'alice',  8);
controller.addRating(2, 'carol',  9);
controller.addRating(3, 'bob',   10);
controller.addRating(3, 'alice',  9);
controller.addRating(5, 'carol',  9);
controller.addRating(1, 'dave',  15); // invalid — model rejects it

controller.showDetail(1);
controller.topRated(3);
controller.showDetail(99); // not found
