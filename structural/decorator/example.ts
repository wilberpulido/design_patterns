/**
 * DECORATOR PATTERN - TypeScript
 * Scenario: Content publishing pipeline — sanitization, markdown rendering,
 * and read-time estimation applied as decorators to a core article renderer.
 */

interface Article {
    title: string;
    body: string;
}

interface RenderedArticle {
    title: string;
    body: string;
    readTime?: string; // only present when ReadTimeDecorator is in the chain
}

// The base contract — every renderer and decorator must implement render().
// An interface instead of a throwing base class: a missing render() is now a compile error.
interface ArticleRenderer {
    render(article: Article): RenderedArticle;
}

// Core renderer: returns the article as-is, with no processing.
// It doesn't know about markdown, sanitization, or read time.
class RawRenderer implements ArticleRenderer {
    render(article: Article): RenderedArticle {
        console.log(`[RawRenderer] Rendering article: "${article.title}"`);
        return { title: article.title, body: article.body };
    }
}

// Base decorator: holds a reference to the next renderer in the chain.
// abstract: it IS-A renderer (so decorators stack) but can't be instantiated on its own.
abstract class RendererDecorator implements ArticleRenderer {
    constructor(protected readonly wrapped: ArticleRenderer) {}

    abstract render(article: Article): RenderedArticle;
}

class SanitizationDecorator extends RendererDecorator {
    render(article: Article): RenderedArticle {
        console.log('[SanitizationDecorator] Stripping dangerous HTML tags...');
        const sanitized: Article = {
            ...article,
            // Remove <script> tags and inline event handlers — XSS prevention
            body: article.body
                .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
                .replace(/\son\w+="[^"]*"/gi, '')
        };
        const removed = article.body.length - sanitized.body.length;
        console.log(`[SanitizationDecorator] Removed ${removed} unsafe characters.`);
        return this.wrapped.render(sanitized);
    }
}

class MarkdownDecorator extends RendererDecorator {
    render(article: Article): RenderedArticle {
        console.log('[MarkdownDecorator] Parsing markdown syntax...');
        const rendered: Article = {
            ...article,
            // Simulated markdown conversion — production would use a full parser (marked, remark)
            body: article.body
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/^# (.+)$/m, '<h1>$1</h1>')
                .replace(/^## (.+)$/m, '<h2>$1</h2>')
        };
        console.log('[MarkdownDecorator] Markdown converted to HTML.');
        return this.wrapped.render(rendered);
    }
}

class ReadTimeDecorator extends RendererDecorator {
    render(article: Article): RenderedArticle {
        // Average reading speed: ~200 words per minute
        const wordCount = article.body.split(/\s+/).filter(Boolean).length;
        const minutes = Math.ceil(wordCount / 200);
        console.log(`[ReadTimeDecorator] Word count: ${wordCount} → estimated read time: ${minutes} min.`);
        const result = this.wrapped.render(article);
        // Appends metadata to the result — does not modify the article going into the chain
        return { ...result, readTime: `${minutes} min read` };
    }
}

// matiz: JavaScript also supports decorators as higher-order functions (HOFs).
// HOF decorators are simpler when the object has a single method or is a plain function.
// Class-based decorators are better when the interface has multiple methods or requires state.
// Here we show both styles: class-based above, HOF-based below for caching.
function withCaching(renderer: ArticleRenderer): ArticleRenderer {
    const cache = new Map<string, RenderedArticle>();
    return {
        render(article: Article): RenderedArticle {
            const key = article.title;
            if (cache.has(key)) {
                console.log(`[CachingDecorator] Cache HIT for "${key}" — skipping full pipeline.`);
                // `!`: has() was just checked, but TypeScript can't link has() to get().
                return cache.get(key)!;
            }
            console.log(`[CachingDecorator] Cache MISS for "${key}" — running pipeline.`);
            const result = renderer.render(article);
            cache.set(key, result);
            console.log(`[CachingDecorator] Result cached.`);
            return result;
        }
    };
}

// Stack (outer to inner): Caching → ReadTime → Sanitization → Markdown → Raw
// Caching is outermost: on a cache hit, none of the inner decorators run at all.
const pipeline = withCaching(
    new ReadTimeDecorator(
        new SanitizationDecorator(
            new MarkdownDecorator(
                new RawRenderer()
            )
        )
    )
);

console.log('=== Decorator Pattern Demo — Content Publishing Pipeline (TypeScript) ===\n');

const article: Article = {
    title: 'Getting Started with Node.js',
    body: '# Introduction\n\n' +
          'This is a **beginner** guide. <script>alert("xss")</script>\n\n' +
          '## What you will learn\n\n' +
          'Build your first server with **Express** and understand the event loop today.'
};

console.log('-- First render (full pipeline) --');
const result = pipeline.render(article);
console.log('\nFinal output:');
console.log(JSON.stringify(result, null, 2));

console.log('\n-- Second render (same article — cached) --');
const cached = pipeline.render(article);
console.log('\nCached output:');
console.log(JSON.stringify(cached, null, 2));
