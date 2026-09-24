<?php require 'api/config.php'; $db = getDB(); print_r($db->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC)); print_r($db->query('DESCRIBE students')->fetchAll(PDO::FETCH_ASSOC)); ?>
