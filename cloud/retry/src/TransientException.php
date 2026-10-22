<?php

declare(strict_types=1);

namespace DesignPatterns\Cloud\Retry;

// ─── Exceptions ───────────────────────────────────────────────────────────────
// Distinguishing exception types is what allows the executor to decide
// whether to retry or abort immediately.

class TransientException extends \RuntimeException {}  // temporary — worth retrying
