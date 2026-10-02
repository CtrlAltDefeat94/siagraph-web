<?php

function v2_db_error_message(mysqli $db): string
{
    $message = $db->error;
    return $message !== '' ? $message : 'Database operation failed.';
}
