// Scenario: hospital appointment scheduling system.
// A patient books an appointment with a doctor — the system checks
// availability, confirms the appointment, and notifies the patient.

import java.time.LocalDateTime;
import java.util.Optional;
import java.util.UUID;

// ============================================================
// DOMAIN LAYER — business rules, entities, no external deps
// ============================================================

class Patient {
    public final String id;
    public final String name;
    public final String email;
    Patient(String id, String name, String email) { this.id = id; this.name = name; this.email = email; }
}

class Doctor {
    public final String id;
    public final String name;
    public final String specialty;
    Doctor(String id, String name, String specialty) { this.id = id; this.name = name; this.specialty = specialty; }
}

enum AppointmentStatus { REQUESTED, CONFIRMED, CANCELLED }

class Appointment {
    public final String            id;
    public final Patient           patient;
    public final Doctor            doctor;
    public final LocalDateTime     scheduledAt;
    private      AppointmentStatus status = AppointmentStatus.REQUESTED;

    Appointment(String id, Patient patient, Doctor doctor, LocalDateTime scheduledAt) {
        this.id          = id;
        this.patient     = patient;
        this.doctor      = doctor;
        this.scheduledAt = scheduledAt;
    }

    public void confirm() {
        // Domain rule: only REQUESTED appointments can be confirmed
        if (status != AppointmentStatus.REQUESTED) {
            throw new IllegalStateException("Only REQUESTED appointments can be confirmed.");
        }
        status = AppointmentStatus.CONFIRMED;
        System.out.println("[Appointment] " + id.substring(0, 8) +
            " confirmed — " + patient.name + " with Dr. " + doctor.name +
            " at " + scheduledAt);
    }

    public void cancel() {
        if (status == AppointmentStatus.CANCELLED) {
            throw new IllegalStateException("Already cancelled.");
        }
        status = AppointmentStatus.CANCELLED;
        System.out.println("[Appointment] " + id.substring(0, 8) + " cancelled.");
    }

    public AppointmentStatus getStatus() { return status; }
}

// ============================================================
// INFRASTRUCTURE LAYER — DB and notification gateway
// ============================================================

interface AppointmentRepository {
    void                  save(Appointment appointment);
    Optional<Appointment> findById(String id);
}

interface PatientRepository {
    Optional<Patient> findById(String id);
}

interface DoctorRepository {
    Optional<Doctor> findById(String id);
    boolean          isAvailable(String doctorId, LocalDateTime at);
}

interface NotificationGateway {
    void sendConfirmation(Patient patient, Appointment appointment);
}

class JdbcAppointmentRepository implements AppointmentRepository {
    public void save(Appointment a) {
        System.out.println("[JdbcAppointmentRepository] INSERT/UPDATE appointment '" +
            a.id.substring(0, 8) + "' status='" + a.getStatus() + "'");
    }
    public Optional<Appointment> findById(String id) {
        System.out.println("[JdbcAppointmentRepository] SELECT * FROM appointments WHERE id = '" + id.substring(0, 8) + "'");
        return Optional.empty();
    }
}

class JdbcPatientRepository implements PatientRepository {
    public Optional<Patient> findById(String id) {
        System.out.println("[JdbcPatientRepository] SELECT * FROM patients WHERE id = '" + id + "'");
        return Optional.of(new Patient(id, "Laura Sánchez", "laura@hospital.org"));
    }
}

class JdbcDoctorRepository implements DoctorRepository {
    public Optional<Doctor> findById(String id) {
        System.out.println("[JdbcDoctorRepository] SELECT * FROM doctors WHERE id = '" + id + "'");
        return Optional.of(new Doctor(id, "Martínez", "Cardiology"));
    }
    public boolean isAvailable(String doctorId, LocalDateTime at) {
        System.out.println("[JdbcDoctorRepository] Checking availability for doctor '" + doctorId + "' at " + at);
        return true;
    }
}

class EmailNotificationGateway implements NotificationGateway {
    public void sendConfirmation(Patient patient, Appointment appointment) {
        System.out.println("[EmailNotificationGateway] Sending confirmation to " + patient.email +
            " — Dr. " + appointment.doctor.name + " at " + appointment.scheduledAt);
    }
}

// ============================================================
// APPLICATION LAYER — DTOs and use case orchestration
// ============================================================

// matiz: DTOs (Data Transfer Objects) travel across layer boundaries.
// The controller sends a BookAppointmentRequest (raw HTTP data).
// The application maps it to domain objects and returns an AppointmentSummary
// (safe for the presentation layer). Domain objects never leak upward.
class BookAppointmentRequest {
    public final String        patientId;
    public final String        doctorId;
    public final LocalDateTime scheduledAt;
    BookAppointmentRequest(String patientId, String doctorId, LocalDateTime scheduledAt) {
        this.patientId   = patientId;
        this.doctorId    = doctorId;
        this.scheduledAt = scheduledAt;
    }
}

class AppointmentSummary {
    public final String        appointmentId;
    public final String        patientName;
    public final String        doctorName;
    public final LocalDateTime scheduledAt;
    public final String        status;
    AppointmentSummary(String appointmentId, String patientName, String doctorName, LocalDateTime scheduledAt, String status) {
        this.appointmentId = appointmentId;
        this.patientName   = patientName;
        this.doctorName    = doctorName;
        this.scheduledAt   = scheduledAt;
        this.status        = status;
    }
}

class AppointmentApplicationService {
    private final AppointmentRepository appointments;
    private final PatientRepository     patients;
    private final DoctorRepository      doctors;
    private final NotificationGateway   notifications;

    AppointmentApplicationService(
        AppointmentRepository appointments,
        PatientRepository     patients,
        DoctorRepository      doctors,
        NotificationGateway   notifications
    ) {
        this.appointments  = appointments;
        this.patients      = patients;
        this.doctors       = doctors;
        this.notifications = notifications;
    }

    public AppointmentSummary bookAppointment(BookAppointmentRequest req) {
        System.out.println("\n[AppointmentApplicationService] Starting BookAppointment use case...");

        Patient patient = patients.findById(req.patientId)
            .orElseThrow(() -> new IllegalArgumentException("Patient not found."));

        Doctor doctor = doctors.findById(req.doctorId)
            .orElseThrow(() -> new IllegalArgumentException("Doctor not found."));

        if (!doctors.isAvailable(req.doctorId, req.scheduledAt)) {
            throw new IllegalStateException("Doctor is not available at that time.");
        }

        Appointment appointment = new Appointment(
            UUID.randomUUID().toString(), patient, doctor, req.scheduledAt
        );

        appointment.confirm();              // domain enforces the state transition
        appointments.save(appointment);
        notifications.sendConfirmation(patient, appointment);

        System.out.println("[AppointmentApplicationService] BookAppointment use case completed.");
        return new AppointmentSummary(
            appointment.id,
            patient.name,
            "Dr. " + doctor.name,
            appointment.scheduledAt,
            appointment.getStatus().toString()
        );
    }
}

// ============================================================
// PRESENTATION LAYER — HTTP controller
// ============================================================

class AppointmentController {
    private final AppointmentApplicationService service;

    AppointmentController(AppointmentApplicationService service) { this.service = service; }

    public void book(String patientId, String doctorId, LocalDateTime at) {
        System.out.println("\n[AppointmentController] POST /appointments");
        AppointmentSummary summary = service.bookAppointment(
            new BookAppointmentRequest(patientId, doctorId, at)
        );
        System.out.println("[AppointmentController] 201 Created — appointment " +
            summary.appointmentId.substring(0, 8) +
            " | " + summary.patientName +
            " with " + summary.doctorName +
            " | status: " + summary.status);
    }
}

// ============================================================
// COMPOSITION ROOT
// ============================================================

class Main {
    public static void main(String[] args) {
        AppointmentController controller = new AppointmentController(
            new AppointmentApplicationService(
                new JdbcAppointmentRepository(),
                new JdbcPatientRepository(),
                new JdbcDoctorRepository(),
                new EmailNotificationGateway()
            )
        );

        controller.book("patient_1", "doctor_42", LocalDateTime.of(2026, 6, 15, 10, 30));
    }
}
