<?php

/**
 * function to write to log file
 * @param mixed $message The message to write to the log file
 */
function writeTolog($message)
{
    $log_file = plugin_dir_path(__FILE__) . 'log.txt';
    if (!file_exists($log_file)) {
        $file = fopen($log_file, 'w');
        fclose($file);
    }
    $current_time = date('Y-m-d H:i:s');
    $formatted_message = "[$current_time] $message" . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}
