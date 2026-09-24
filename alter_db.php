<?php require 'api/config.php'; $db = db(); $db->exec('ALTER TABLE users ADD COLUMN no_hp VARCHAR(30) NULL AFTER email'); echo 'Schema updated.'; ?>
