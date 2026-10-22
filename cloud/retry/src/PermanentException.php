<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\Retry;

// ─── Exceptions ───────────────────────────────────────────────────────────────
// Distinguishing exception types is what allows the executor to decide
// whether to retry or abort immediately.

class PermanentException extends \RuntimeException {}  // permanent — retrying won't help
