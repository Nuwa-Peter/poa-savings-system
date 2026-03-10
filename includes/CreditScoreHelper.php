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
        $months_active = 1;
        $score = 500;
        try {
            // Fetch user info for time-weighted analysis
            $user_stmt = $this->pdo->prepare("SELECT created_at FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();

            if (!$user) return 500;

            $joined_date = new DateTime($user['created_at']);
            $now = new DateTime();
            $interval = $joined_date->diff($now);
            $months_active = ($interval->y * 12) + $interval->m;
            if ($months_active < 1) $months_active = 1;

            // Baseline
            $score = ($months_active < 3) ? 600 : 500;

            // Savings analysis
            $savings_stmt = $this->pdo->prepare("SELECT amount, created_at FROM savings WHERE user_id = ? ORDER BY created_at ASC");
            $savings_stmt->execute([$user_id]);
            $all_savings = $savings_stmt->fetchAll();

            $total_saved = 0;
            $deposit_months = [];
            foreach ($all_savings as $saving) {
                $total_saved += $saving['amount'];
                $month_key = date('Y-m', strtotime($saving['created_at']));
                $deposit_months[$month_key] = true;
            }

            $actual_deposits_count = count($deposit_months);
            $frequency_ratio = $actual_deposits_count / $months_active;
            $score += round($frequency_ratio * 200);

            $volume_points = floor($total_saved / 100000) * 5;
            $score += min(100, $volume_points);

            // Loan analysis
            $loan_stmt = $this->pdo->prepare("SELECT * FROM loans WHERE user_id = ? AND status IN ('approved', 'closed')");
            $loan_stmt->execute([$user_id]);
            $loans = $loan_stmt->fetchAll();

            foreach ($loans as $loan) {
                if ($loan['status'] === 'closed') $score += 50;
                if ($loan['balance'] > 0 && strtotime($loan['due_date']) < time()) $score -= 150;
            }

            // Guarantor penalty
            try {
                $guarantor_stmt = $this->pdo->prepare("
                    SELECT l.balance, l.due_date
                    FROM loans l
                    JOIN loan_guarantors lg ON l.id = lg.loan_id
                    WHERE lg.guarantor_id = ? AND lg.status = 'approved' AND l.balance > 0
                ");
                $guarantor_stmt->execute([$user_id]);
                $guaranteed = $guarantor_stmt->fetchAll();
                foreach ($guaranteed as $g) {
                    if (strtotime($g['due_date']) < time()) $score -= 30;
                }
            } catch (\Exception $e) {
                // Ignore errors related to loan_guarantors table if it's missing/broken
            }

            $score = max(300, min(850, $score));

            // Save to DB (only if table exists)
            try {
                $update_stmt = $this->pdo->prepare("INSERT INTO member_credit_scores (user_id, score) VALUES (?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
                $update_stmt->execute([$user_id, $score]);
            } catch (\Exception $e) {
                // Log silently or ignore if table doesn't exist
            }

            return $score;

        } catch (\Exception $e) {
            return $score;
        }
    }

    public function recalculateAllScores() {
        try {
            $stmt = $this->pdo->query("SELECT id FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            foreach ($users as $user_id) {
                $this->updateScore($user_id);
                $count++;
            }
            return $count;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getScore($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT score FROM member_credit_scores WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $score = $stmt->fetchColumn();
            return $score ?: 500;
        } catch (\Exception $e) {
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
