<?php

declare(strict_types=1);

namespace Ghidar\Config;

use Dotenv\Dotenv;

/**
 * Configuration manager for Ghidar application.
 * Handles loading environment variables from .env file.
 */
class Config
{
    private static bool $loaded = false;
    private static array $cache = [];

    /**
     * Initialize configuration by loading .env file.
     * Should be called once at application bootstrap.
     */
    public static function load(string $rootPath): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $rootPath . '/.env';
        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->load();
        }

        self::$loaded = true;
    }

    /**
     * Get a configuration value from environment variables.
     *
     * @param string $key Configuration key
     * @param string|null $default Default value if key is not found
     * @return string|null Configuration value or default
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * Get configuration value as integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    /**
     * Get configuration value as boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get array from comma-separated configuration value.
     *
     * @return array<int>
     */
    public static function getIntArray(string $key, array $default = []): array
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return array_map('intval', array_filter(explode(',', $value)));
    }

    /**
     * Clear configuration cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

