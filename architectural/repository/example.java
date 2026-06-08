// Scenario: blog/CMS article management.
// The BlogService retrieves and publishes articles — it should not contain
// SQL queries or know anything about the persistence layer.

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;

// Domain object — pure Java, no JPA/Hibernate annotations
class Article {
    public final int       id;
    public final String    title;
    public final String    authorId;
    public final String    status;   // "draft" | "published" | "archived"
    public final LocalDate publishedAt;

    Article(int id, String title, String authorId, String status, LocalDate publishedAt) {
        this.id          = id;
        this.title       = title;
        this.authorId    = authorId;
        this.status      = status;
        this.publishedAt = publishedAt;
    }
}

// The Repository interface — speaks in domain language, not table/column language
interface ArticleRepository {
    Optional<Article> findById(int id);
    List<Article>     findByAuthor(String authorId);
    List<Article>     findPublished();
    void              save(Article article);
    void              delete(int id);
}

// Concrete implementation: JDBC — simulates SQL queries
class JdbcArticleRepository implements ArticleRepository {
    public Optional<Article> findById(int id) {
        System.out.println("[JdbcArticleRepository] SELECT * FROM articles WHERE id = " + id);
        return Optional.of(new Article(id, "Design Patterns in Practice", "author_1", "published", LocalDate.of(2024, 3, 10)));
    }

    public List<Article> findByAuthor(String authorId) {
        System.out.println("[JdbcArticleRepository] SELECT * FROM articles WHERE author_id = '" + authorId + "'");
        return List.of(
            new Article(1, "Design Patterns in Practice", authorId, "published", LocalDate.of(2024, 3, 10)),
            new Article(2, "Clean Architecture Tips",     authorId, "draft",     null)
        );
    }

    public List<Article> findPublished() {
        System.out.println("[JdbcArticleRepository] SELECT * FROM articles WHERE status = 'published' ORDER BY published_at DESC");
        return List.of(
            new Article(1, "Design Patterns in Practice", "author_1", "published", LocalDate.of(2024, 3, 10)),
            new Article(3, "Understanding the JVM",       "author_2", "published", LocalDate.of(2024, 2, 5))
        );
    }

    public void save(Article article) {
        System.out.println("[JdbcArticleRepository] INSERT/UPDATE article '" + article.title + "'");
    }

    public void delete(int id) {
        System.out.println("[JdbcArticleRepository] DELETE FROM articles WHERE id = " + id);
    }
}

// matiz: the Specification pattern avoids repository method explosion.
// Instead of findByStatusAndAuthorAndDateRange(), you pass a Specification object
// that encapsulates the filtering criteria. The repository applies it generically.
// This keeps the interface stable as query needs grow.
class ArticleSpecification {
    public final String  status;
    public final String  authorId;
    public final boolean publishedOnly;

    ArticleSpecification(String status, String authorId, boolean publishedOnly) {
        this.status        = status;
        this.authorId      = authorId;
        this.publishedOnly = publishedOnly;
    }
}

interface SpecificationArticleRepository extends ArticleRepository {
    List<Article> findBySpec(ArticleSpecification spec);
}

class JdbcSpecArticleRepository extends JdbcArticleRepository implements SpecificationArticleRepository {
    public List<Article> findBySpec(ArticleSpecification spec) {
        StringBuilder query = new StringBuilder("[JdbcSpecArticleRepository] SELECT * FROM articles WHERE 1=1");
        if (spec.status   != null) query.append(" AND status = '").append(spec.status).append("'");
        if (spec.authorId != null) query.append(" AND author_id = '").append(spec.authorId).append("'");
        if (spec.publishedOnly)    query.append(" AND published_at IS NOT NULL");
        System.out.println(query);
        return List.of(new Article(1, "Filtered Article", spec.authorId, spec.status, LocalDate.now()));
    }
}

// The Service — depends only on ArticleRepository, zero SQL knowledge
class BlogService {
    private final SpecificationArticleRepository repository;

    BlogService(SpecificationArticleRepository repository) {
        this.repository = repository;
    }

    public void showPublishedArticles() {
        System.out.println("\n[BlogService] Loading published articles...");
        repository.findPublished().forEach(a ->
            System.out.println("[BlogService]   - \"" + a.title + "\" by " + a.authorId)
        );
    }

    public void showAuthorDrafts(String authorId) {
        System.out.println("\n[BlogService] Loading drafts for author " + authorId + "...");
        var spec = new ArticleSpecification("draft", authorId, false);
        repository.findBySpec(spec).forEach(a ->
            System.out.println("[BlogService]   Draft: \"" + a.title + "\"")
        );
    }
}

class Main {
    public static void main(String[] args) {
        BlogService service = new BlogService(new JdbcSpecArticleRepository());
        service.showPublishedArticles();
        service.showAuthorDrafts("author_1");
    }
}
