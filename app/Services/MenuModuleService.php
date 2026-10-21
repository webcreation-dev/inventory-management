<?php

namespace App\Services;

class MenuModuleService
{
    private ?array $modules = null;

    public function enabled(string $key): bool
    {
        $this->modules ??= app(ModuleUsageService::class)->all();
        return (bool) ($this->modules[$key] ?? true);
    }

    public function any(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->enabled($key)) return true;
        }
        return false;
    }

    public function forPath(string $path): ?string
    {
        $path = trim($path, '/');
        $matches = [];
        foreach (config('menu_modules', []) as $key => $definition) {
            foreach ($definition['prefixes'] as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    $matches[strlen($prefix)] = $key;
                }
            }
        }
        if ($matches === []) return null;
        krsort($matches);
        return reset($matches);
    }
}
