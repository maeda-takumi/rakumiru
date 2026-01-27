<?php

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function now(): string {
    return date('Y-m-d H:i:s');
}
