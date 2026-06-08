// Scenario: mortgage application processing.
// Approving a mortgage requires coordinating credit checks, property appraisal,
// document verification, risk assessment, and loan pricing — each a separate subsystem.
// The LoanOfficerPortal should not need to orchestrate all of this itself.

// Subsystem: retrieves the applicant's credit score from a bureau
class CreditScoreChecker {
    public int check(String applicantId) {
        System.out.println("[CreditScoreChecker] Fetching credit score for " + applicantId + "...");
        return 720; // simulated score
    }
}

// Subsystem: performs an independent valuation of the property
class PropertyAppraiser {
    public double appraise(String propertyAddress) {
        System.out.println("[PropertyAppraiser] Appraising property at: " + propertyAddress + "...");
        return 350_000.0; // simulated appraisal value in USD
    }
}

// Subsystem: verifies identity and income documents
class DocumentVerifier {
    public boolean verify(String applicantId) {
        System.out.println("[DocumentVerifier] Verifying documents for " + applicantId + "...");
        return true;
    }
}

// Subsystem: calculates risk level based on loan-to-value ratio and credit score
class RiskAssessor {
    public String assess(int creditScore, double loanAmount, double propertyValue) {
        double ltv = loanAmount / propertyValue;
        System.out.printf("[RiskAssessor] LTV: %.1f%% | Credit score: %d%n", ltv * 100, creditScore);
        return (ltv < 0.80 && creditScore > 700) ? "LOW" : "MEDIUM";
    }
}

// Subsystem: determines the interest rate based on risk level
class LoanPricer {
    public double calculateRate(String riskLevel) {
        double rate = riskLevel.equals("LOW") ? 3.75 : 4.50;
        System.out.printf("[LoanPricer] Risk: %s → interest rate: %.2f%%%n", riskLevel, rate);
        return rate;
    }
}

// Result object returned by the Facade — a clean summary, not raw subsystem data
class ApprovalResult {
    public final boolean approved;
    public final String  applicantId;
    public final double  interestRate;
    public final String  riskLevel;

    ApprovalResult(boolean approved, String applicantId, double interestRate, String riskLevel) {
        this.approved     = approved;
        this.applicantId  = applicantId;
        this.interestRate = interestRate;
        this.riskLevel    = riskLevel;
    }
}

// The Facade — one method coordinates the entire mortgage assessment pipeline.
// The loan officer only calls processApplication() and gets a clean result.
class MortgageApplicationFacade {
    private final CreditScoreChecker creditChecker = new CreditScoreChecker();
    private final PropertyAppraiser  appraiser     = new PropertyAppraiser();
    private final DocumentVerifier   docVerifier   = new DocumentVerifier();
    private final RiskAssessor       riskAssessor  = new RiskAssessor();
    private final LoanPricer         pricer        = new LoanPricer();

    public ApprovalResult processApplication(String applicantId, String propertyAddress, double loanAmount) {
        System.out.println("\n[MortgageApplicationFacade] Processing application for " + applicantId + "...");

        // Documents must be verified first — no point running other checks if incomplete
        boolean docsOk = docVerifier.verify(applicantId);
        if (!docsOk) {
            System.out.println("[MortgageApplicationFacade] Rejected: documents incomplete.");
            return new ApprovalResult(false, applicantId, 0, "N/A");
        }

        int    creditScore    = creditChecker.check(applicantId);
        double propertyValue  = appraiser.appraise(propertyAddress);
        String riskLevel      = riskAssessor.assess(creditScore, loanAmount, propertyValue);

        // matiz: compensation logic — if a step fails mid-process, the facade
        // can undo previous steps internally before returning an error.
        // Here, a low credit score cancels the appraisal order that was already placed,
        // keeping subsystems consistent without the client knowing it happened.
        // This is related to the Saga pattern for distributed transactions.
        if (creditScore < 580) {
            System.out.println("[MortgageApplicationFacade] Credit score too low. Cancelling appraisal order...");
            System.out.println("[MortgageApplicationFacade] Application rejected.");
            return new ApprovalResult(false, applicantId, 0, riskLevel);
        }

        double rate = pricer.calculateRate(riskLevel);

        System.out.printf("[MortgageApplicationFacade] APPROVED — Rate: %.2f%% | Risk: %s%n", rate, riskLevel);
        return new ApprovalResult(true, applicantId, rate, riskLevel);
    }
}

// Client — a loan officer's portal. Submits applications without knowing the pipeline.
class LoanOfficerPortal {
    private final MortgageApplicationFacade facade = new MortgageApplicationFacade();

    public void submitApplication(String applicantId, String property, double loanAmount) {
        System.out.println("[LoanOfficerPortal] Submitting mortgage application for " + applicantId);
        ApprovalResult result = facade.processApplication(applicantId, property, loanAmount);

        if (result.approved) {
            System.out.printf("[LoanOfficerPortal] Approved at %.2f%% (%s risk)%n",
                result.interestRate, result.riskLevel);
        } else {
            System.out.println("[LoanOfficerPortal] Application was not approved.");
        }
    }
}

class Main {
    public static void main(String[] args) {
        LoanOfficerPortal portal = new LoanOfficerPortal();
        portal.submitApplication("applicant_001", "123 Maple Street", 280_000.0);
    }
}
