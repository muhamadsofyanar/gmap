<?php
namespace App\Services;

final class TeamAccess
{
    public static function allowsController(array $actor, string $controller): bool
    {
        if (($actor['role'] ?? '') === 'admin') return $controller !== 'LicenseController';
        return ($actor['role'] ?? '') === 'user'
            && in_array($controller, ['TeamController', 'ProfileController', 'AuthController', 'InboxController'], true);
    }
}
