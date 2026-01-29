<?php
// Ensure TCPDF is available
require_once __DIR__ . '/../vendor/autoload.php';

class StatementPDF extends TCPDF {
    private $userName;
    private $accountNumber;
    private $statementPeriod;
    private $generatedDate;
    private $reportType = 'ACCOUNT STATEMENT';

    public function setUserDetails($name, $acc, $period, $generated, $type = 'ACCOUNT STATEMENT') {
        $this->userName = $name;
        $this->accountNumber = $acc;
        $this->statementPeriod = $period;
        $this->generatedDate = $generated;
        $this->reportType = $type;
    }

    public function Header() {
        // Set Font
        $this->SetFont('helvetica', '', 10);

        // Logo on its own line
        $this->Image('assets/images/poa_light.png', '', 8, 30, 0, 'PNG', '', 'T', false, 300, 'C');
        $this->Ln(18); // Add space after the logo

        // Center Company Name
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'POA Savings and Credit Society', 0, 1, 'C');

        // Center Document Title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 12, $this->reportType, 0, 1, 'C');
        $this->Ln(5);

        // Account Information Section
        $this->SetFont('helvetica', '', 9);
        $html = '
<table border="0" cellpadding="2" cellspacing="0" width="100%">
    <tr>
        <td width="50%" align="left"><b>' . ($this->accountNumber ? 'Account Name:' : 'Report For:') . '</b> ' . $this->userName . ($this->accountNumber ? '<br><b>Account Number:</b> ' . $this->accountNumber : '') . '</td>
        <td width="50%" align="right"><b>' . ($this->statementPeriod ? 'Period:' : 'Generated At:') . '</b> ' . ($this->statementPeriod ?: $this->generatedDate) . ($this->statementPeriod ? '<br><b>Generated Date:</b> ' . $this->generatedDate : '') . '</td>
    </tr>
</table>';
        $this->writeHTML($html, true, false, true, false, '');
        $this->Line(15, $this->GetY() + 2, $this->getPageWidth() - 15, $this->GetY() + 2);
    }

    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false) {
        parent::AddPage($orientation, $format, $keepmargins, $tocpage);
        $this->addWatermark();
    }

    private function addWatermark() {
        $bMargin = $this->getBreakMargin();
        $auto_page_break = $this->AutoPageBreak;
        $this->SetAutoPageBreak(false, 0);
        $this->SetAlpha(0.1);
        $this->Image('assets/images/poa_light.png', 50, 100, 110, 0, 'PNG', '', 'C', true, 300, 'C', false, false, 0);
        $this->SetAlpha(1);
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        $this->setPageMark();
    }

    public function Footer() {
        $this->SetY(-18);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Generated on: ' . $this->generatedDate, 0, 0, 'L');
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'R');
    }

    public function FancyTable($header, $data, $w, $openingBalance = null) {
        $this->SetFillColor(229, 231, 235);
        $this->SetTextColor(0);
        $this->SetDrawColor(209, 213, 219);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetLineWidth(0.2);

        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();

        $this->SetFont('helvetica', '', 9);
        $fill = false;
        $balance = $openingBalance;

        if (empty($data)) {
            $this->Cell(array_sum($w), 15, 'No records found', 'LRB', 0, 'C', $fill);
            $this->Ln();
        } else {
            foreach($data as $row) {
                $this->SetFillColor(248, 249, 250);

                // If it's a statement with debit/credit
                if ($openingBalance !== null) {
                    $debit = is_numeric($row['debit']) ? $row['debit'] : 0;
                    $credit = is_numeric($row['credit']) ? $row['credit'] : 0;
                    $balance += $credit - $debit;

                    $this->Cell($w[0], 9, date('Y-m-d', strtotime($row['date'])), 'LR', 0, 'L', $fill);
                    $this->Cell($w[1], 9, htmlspecialchars($row['type']), 'R', 0, 'L', $fill);
                    $this->Cell($w[2], 9, ($debit > 0) ? number_format($debit, 2) : '-', 'R', 0, 'R', $fill);
                    $this->Cell($w[3], 9, ($credit > 0) ? number_format($credit, 2) : '-', 'R', 0, 'R', $fill);
                    $this->Cell($w[4], 9, number_format($balance, 2), 'R', 0, 'R', $fill);
                } else {
                    // Generic table
                    $col = 0;
                    foreach ($row as $val) {
                        $align = (is_numeric($val) && $col > 0) ? 'R' : 'L';
                        $display_val = is_numeric($val) ? number_format($val, 2) : $val;
                        $this->Cell($w[$col], 9, $display_val, 'LR', 0, $align, $fill);
                        $col++;
                    }
                }
                $this->Ln();
                $fill = !$fill;
            }
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}
