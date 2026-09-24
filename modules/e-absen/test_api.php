<?php
require 'E:/xampp/htdocs/eportal/api/config.php';
require 'E:/xampp/htdocs/eportal/modules/e-absen/api/auth_helper.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get';
function acad_auth() { return ['role' => 'superadmin']; }
require 'E:/xampp/htdocs/eportal/modules/e-absen/api/mesin.php';
