<?php
$f = 'e:/xampp/htdocs/eportal/modules/e-curriculum/assets/js/curriculum.js';
$c = file_get_contents($f);
$c = str_replace('this.escape(', 'this.escapeHtml(', $c);
file_put_contents($f, $c);
echo "Fixed!";
