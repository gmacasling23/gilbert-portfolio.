<?php

function normalizePhilippineMobileNumber(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '' || !preg_match('/^[0-9+().\-\s]+$/', $phone)) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '0063')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '63')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    if (!preg_match('/^9\d{9}$/', $digits)) {
        return null;
    }

    return '+63 ' . substr($digits, 0, 3) . ' '
        . substr($digits, 3, 3) . ' ' . substr($digits, 6, 4);
}
