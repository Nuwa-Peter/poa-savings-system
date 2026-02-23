<?php
require_once 'includes/StatementPDF.php';
if (class_exists('StatementPDF')) {
    echo "✅ StatementPDF loaded.\n";
} else {
    echo "❌ StatementPDF NOT loaded.\n";
}
?>
