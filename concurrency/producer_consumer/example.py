"""
Scenario: Image Processing Pipeline

Uploaded images are queued for processing (resize, watermark, compress).
Multiple worker threads consume the queue concurrently.
"""

import threading
import queue
import time

# Sentinel value — placed in the queue to signal a worker to shut down.
# Each worker consumes exactly one STOP, so send one per worker.
STOP = object()

# ─── Data ─────────────────────────────────────────────────────────────────────

class ImageTask:
    def __init__(self, filename: str, filters: list[str]):
        self.filename = filename
        self.filters  = filters

    def __str__(self):
        return f"{self.filename} [{', '.join(self.filters)}]"

# ─── Producer ─────────────────────────────────────────────────────────────────
# The producer runs in its own thread, generating tasks and placing them
# in the shared queue. It does not know how many workers exist.

class UploadHandler(threading.Thread):
    def __init__(self, task_queue: queue.Queue, uploads: list[dict]):
        super().__init__(name="UploadHandler")
        self._queue   = task_queue
        self._uploads = uploads

    def run(self):
        for upload in self._uploads:
            task = ImageTask(upload["filename"], upload["filters"])
            print(f"[UploadHandler] Queuing: {task}  — buffer: {self._queue.qsize()}")
            self._queue.put(task)   # blocks if queue is full (backpressure)
            time.sleep(0.05)        # simulate upload rate
        print("[UploadHandler] All uploads queued.")

# ─── Consumer ─────────────────────────────────────────────────────────────────
# Each worker runs in its own thread, pulling tasks from the shared queue.
# Workers shut down gracefully when they receive the STOP sentinel.

class ImageWorker(threading.Thread):
    def __init__(self, worker_id: int, task_queue: queue.Queue):
        super().__init__(name=f"Worker-{worker_id}")
        self._id    = worker_id
        self._queue = task_queue

    def run(self):
        while True:
            task = self._queue.get()    # blocks until a task is available

            if task is STOP:
                print(f"[Worker-{self._id}] Received stop signal. Shutting down.")
                self._queue.task_done()
                break

            self._process(task)
            self._queue.task_done()

    def _process(self, task: ImageTask):
        print(f"[Worker-{self._id}] Processing: {task}")
        time.sleep(0.12)  # simulate processing time (slower than production)
        print(f"[Worker-{self._id}] Done:       {task.filename}")

# ─── Entry point ──────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=== Image Processing Pipeline — Producer-Consumer Pattern ===\n")

    # matiz: queue.Queue is thread-safe by design — it uses internal locks so
    # producers and consumers never need to synchronize access manually.
    # maxsize creates a bounded buffer: the producer blocks when full,
    # preventing memory overflow if workers are slower than the upload rate.
    buffer = queue.Queue(maxsize=4)

    uploads = [
        {"filename": "photo_001.jpg", "filters": ["resize", "sharpen"]},
        {"filename": "photo_002.jpg", "filters": ["crop", "watermark"]},
        {"filename": "photo_003.jpg", "filters": ["grayscale"]},
        {"filename": "photo_004.jpg", "filters": ["resize", "compress"]},
        {"filename": "photo_005.jpg", "filters": ["sharpen", "watermark"]},
        {"filename": "photo_006.jpg", "filters": ["crop"]},
        {"filename": "photo_007.jpg", "filters": ["resize"]},
        {"filename": "photo_008.jpg", "filters": ["compress", "watermark"]},
    ]

    NUM_WORKERS = 3

    workers  = [ImageWorker(i + 1, buffer) for i in range(NUM_WORKERS)]
    producer = UploadHandler(buffer, uploads)

    for w in workers:
        w.start()

    producer.start()
    producer.join()

    # Send one STOP per worker — each worker consumes exactly one and exits
    for _ in workers:
        buffer.put(STOP)

    for w in workers:
        w.join()

    print("\n[Main] All images processed.")
