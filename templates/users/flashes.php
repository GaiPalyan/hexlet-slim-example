<?php
if (!empty($flash)) {
    echo "<ul>";
    foreach ($flash as $messages) {
        foreach ($messages as $message) {
            echo "<li>{$message}</li>";
        }
    }
    echo "</ul>";
}