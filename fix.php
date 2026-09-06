<?php
$f = 'e:/xampp/htdocs/eportal/modules/e-curriculum/assets/js/curriculum.js';
$c = file_get_contents($f);
$c = str_replace('\`', '`', $c);
$c = str_replace('\${', '${', $c);
file_put_contents($f, $c);
echo "Fixed";
