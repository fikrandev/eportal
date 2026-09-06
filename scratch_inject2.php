<?php
$file = 'e:/xampp/htdocs/eportal/modules/e-curriculum/assets/js/curriculum.js';
$content = file_get_contents($file);

$target = "    // ==================== KETIDAKHADIRAN GURU VIEW ====================";
$newJs = file_get_contents('e:/xampp/htdocs/eportal/scratch.js');
// Ensure it ends with a comma if it's not the last method
$newJs .= ",\n";

$replacement = $newJs . "\n" . $target;

if (strpos($content, $target) !== false) {
    $content = str_replace($target, $replacement, $content);
    file_put_contents($file, $content);
    echo "Successfully injected JS.";
} else {
    echo "Failed to find target.";
}
