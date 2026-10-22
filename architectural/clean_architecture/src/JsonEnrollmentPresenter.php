<?php

declare(strict_types=1);

namespace DesignPatterns\Architectural\CleanArchitecture;

// ─────────────────────────────────────────────
// INTERFACE ADAPTERS — third ring (Controllers, Presenters, Gateways)
// Convert data between use-case format and external formats (HTTP, DB)
// ─────────────────────────────────────────────

// Presenter — implements OutputPort, shapes data into the view model the controller will return
class JsonEnrollmentPresenter implements EnrollStudentOutputPort
{
    private ?array $viewModel = null;

    public function presentSuccess(EnrollStudentOutput $output): void
    {
        // matiz: the presenter converts OutputData into the format the delivery mechanism needs.
        // A different presenter could format the same OutputData as an HTML confirmation page.
        $this->viewModel = [
            'status'        => 'enrolled',
            'student'       => $output->studentName,
            'course'        => $output->courseTitle,
            'seats_taken'   => "{$output->enrollmentCount}/{$output->capacity}",
        ];
        echo "[JsonEnrollmentPresenter] Shaped success ViewModel: " . json_encode($this->viewModel) . "\n";
    }

    public function presentError(string $message): void
    {
        $this->viewModel = ['error' => $message];
        echo "[JsonEnrollmentPresenter] Shaped error ViewModel: " . json_encode($this->viewModel) . "\n";
    }

    public function getViewModel(): ?array { return $this->viewModel; }
}
