package main

import "fmt"

// Scenario: file storage with pluggable compression.
// The backup service stores files using different compression algorithms
// depending on file type and storage budget. Adding a new algorithm
// should not require touching the storage or backup logic.

// The Strategy interface — every compression algorithm implements this
type CompressionStrategy interface {
	Compress(filename string, sizeKb int) (string, int)
	AlgorithmName() string
}

// Concrete Strategy: Gzip — fast, widely supported, moderate ratio
type GzipStrategy struct{}

func (g *GzipStrategy) Compress(filename string, sizeKb int) (string, int) {
	compressed := int(float64(sizeKb) * 0.65)
	fmt.Printf("[GzipStrategy] Compressing %s with gzip (%dKB → %dKB)...\n", filename, sizeKb, compressed)
	return filename + ".gz", compressed
}

func (g *GzipStrategy) AlgorithmName() string { return "gzip" }

// Concrete Strategy: Zstd — better ratio than gzip, faster decompression
type ZstdStrategy struct{}

func (z *ZstdStrategy) Compress(filename string, sizeKb int) (string, int) {
	compressed := int(float64(sizeKb) * 0.45)
	fmt.Printf("[ZstdStrategy] Compressing %s with zstd (%dKB → %dKB)...\n", filename, sizeKb, compressed)
	return filename + ".zst", compressed
}

func (z *ZstdStrategy) AlgorithmName() string { return "zstd" }

// Concrete Strategy: NoCompression — for already-compressed files (jpg, mp4, zip)
type NoCompressionStrategy struct{}

func (n *NoCompressionStrategy) Compress(filename string, sizeKb int) (string, int) {
	fmt.Printf("[NoCompressionStrategy] File %s is already compressed. Skipping...\n", filename)
	return filename, sizeKb
}

func (n *NoCompressionStrategy) AlgorithmName() string { return "none" }

// The Context — stores files using whatever compression strategy is injected.
// matiz: in Go, a single-method strategy can also be expressed as a function type
// instead of an interface. Both are idiomatic; interfaces suit strategies with
// multiple methods or state, function types suit simple stateless strategies.
// Example: type CompressFn func(filename string, sizeKb int) (string, int)
// Here we use an interface because AlgorithmName() makes it multi-method.
type FileStorage struct {
	strategy CompressionStrategy
}

func NewFileStorage(strategy CompressionStrategy) *FileStorage {
	return &FileStorage{strategy: strategy}
}

func (f *FileStorage) SetStrategy(strategy CompressionStrategy) {
	fmt.Printf("[FileStorage] Switching compression algorithm to: %s\n", strategy.AlgorithmName())
	f.strategy = strategy
}

func (f *FileStorage) Store(filename string, sizeKb int) {
	fmt.Printf("\n[FileStorage] Storing file: %s (%dKB) using %s...\n", filename, sizeKb, f.strategy.AlgorithmName())
	outputFile, finalSize := f.strategy.Compress(filename, sizeKb)
	saved := sizeKb - finalSize
	fmt.Printf("[FileStorage] Stored as %s | Saved %dKB (%.0f%%)\n", outputFile, saved, float64(saved)/float64(sizeKb)*100)
}

// Client — a backup service that selects the compression strategy per file type
type BackupService struct {
	storage *FileStorage
}

func (b *BackupService) Backup(filename, fileType string, sizeKb int) {
	fmt.Printf("[BackupService] Scheduling backup for %s (type: %s)...\n", filename, fileType)

	switch fileType {
	case "text", "log", "sql":
		b.storage.SetStrategy(&ZstdStrategy{})
	case "binary", "tar":
		b.storage.SetStrategy(&GzipStrategy{})
	case "jpg", "mp4", "zip":
		b.storage.SetStrategy(&NoCompressionStrategy{})
	}

	b.storage.Store(filename, sizeKb)
}

func main() {
	storage := NewFileStorage(&GzipStrategy{})
	backup  := &BackupService{storage: storage}

	backup.Backup("database_dump.sql", "sql",    45000)
	backup.Backup("server_logs.tar",   "tar",    12000)
	backup.Backup("profile_photo.jpg", "jpg",     2400)
}
