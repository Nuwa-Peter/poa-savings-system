<?php

class CreditScoreHelper {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Calculates and updates the credit score for a specific user.
     * Score range: 300 to 850 (FICO-style)
     */
    public function updateScore($user_id) {
        $score = 500; // Starting baseline

        try {
            // 1. Savings Consistency (+ points for frequency)
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM savings WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)");
            $stmt->execute([$user_id]);
            $savings_count = $stmt->fetchColumn();
            $score += ($savings_count * 10);

            // 2. Savings Volume (+ points for large balance)
            $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM savings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $total_saved = $stmt->fetchColumn() ?: 0;
            $score += floor($total_saved / 100000) * 5;

            // 3. Loan Repayment Behavior
            $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE user_id = ? AND status IN ('approved', 'closed')");
            $stmt->execute([$user_id]);
            $loans = $stmt->fetchAll();

            foreach ($loans as $loan) {
                if ($loan['status'] === 'closed') {
                    $score += 50; // Points for completing a loan
                }

                // Check for overdue (simple logic: if balance > 0 and due_date passed)
                if ($loan['balance'] > 0 && strtotime($loan['due_date']) < time()) {
                    $score -= 100; // Major penalty for overdue
                }
            }

            // 4. Guaranteed Loans (Penalty if a loan you guaranteed is overdue)
            $stmt = $this->pdo->prepare("
                SELECT l.balance, l.due_date
                FROM loans l
                JOIN loan_guarantors lg ON l.id = lg.loan_id
                WHERE lg.guarantor_id = ? AND lg.status = 'approved' AND l.balance > 0
            ");
            $stmt->execute([$user_id]);
            $guaranteed = $stmt->fetchAll();
            foreach ($guaranteed as $g) {
                if (strtotime($g['due_date']) < time()) {
                    $score -= 20; // Small penalty for bad guarantee
                }
            }

            // Cap the score
            $score = max(300, min(850, $score));

            // Save to DB
            $update_stmt = $this->pdo->prepare("INSERT INTO member_credit_scores (user_id, score) VALUES (?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
            $update_stmt->execute([$user_id, $score]);

            return $score;

        } catch (PDOException $e) {
            return 500;
        }
    }

    public function getScore($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT score FROM member_credit_scores WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $score = $stmt->fetchColumn();
            return $score ?: 500;
        } catch (PDOException $e) {
            // Table might be missing, return baseline
            return 500;
        }
    }

    public function getScoreLabel($score) {
        if ($score >= 750) return ['label' => 'Excellent', 'color' => 'text-green-600'];
        if ($score >= 650) return ['label' => 'Good', 'color' => 'text-emerald-500'];
        if ($score >= 550) return ['label' => 'Fair', 'color' => 'text-yellow-600'];
        return ['label' => 'Poor', 'color' => 'text-red-600'];
    }
}
