<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|null $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' USD';
}

function number_value(float|int|null $value, int $decimals = 2): string
{
    return number_format((float) $value, $decimals, ',', ' ');
}

function active_nav(string $current, string $page): string
{
    return $current === $page ? 'active' : '';
}

