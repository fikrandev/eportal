<?php
/**
 * Helper to generate dynamic CSS variables for module theming
 */
function get_module_theme_css($moduleSlug, $defaultColor = '#1565C0') {
    $themeColor = $defaultColor;
    try {
        $stmtTheme = db()->prepare("SELECT color FROM modules WHERE slug = ?");
        $stmtTheme->execute([$moduleSlug]);
        if ($themeRow = $stmtTheme->fetch()) {
            if (!empty($themeRow['color'])) {
                $themeColor = $themeRow['color'];
            }
        }
    } catch (Exception $e) {}

    $darkColor = darken_hex($themeColor, 30);
    
    // Some modules might use variables named differently, but we override the standard ones
    // We add !important to ensure they override the ones in the CSS files
    return "
    <meta name=\"theme-color\" content=\"{$themeColor}\">
    <style>
        :root {
            --primary: {$themeColor} !important;
            --primary-gradient: linear-gradient(135deg, {$themeColor} 0%, {$darkColor} 100%) !important;
        }
    </style>";
}
