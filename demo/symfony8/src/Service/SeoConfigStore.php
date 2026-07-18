<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function is_array;
use function is_string;

/**
 * Read/write demo nowo_seo_kit YAML (pages, slug_routes, slugs).
 */
final class SeoConfigStore
{
    private string $configFile;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        $this->configFile = $this->kernel->getProjectDir() . '/config/packages/nowo_seo_kit.yaml';
    }

    /**
     * @return array<string, mixed>
     */
    public function root(): array
    {
        $parsed = Yaml::parseFile($this->configFile);
        if (!is_array($parsed) || !is_array($parsed['nowo_seo_kit'] ?? null)) {
            throw new RuntimeException('Invalid nowo_seo_kit.yaml');
        }

        return $parsed['nowo_seo_kit'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function pages(): array
    {
        $pages = $this->root()['pages'] ?? [];

        return is_array($pages) ? $pages : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function slugRoutes(): array
    {
        $routes = $this->root()['slug_routes'] ?? [];

        return is_array($routes) ? $routes : [];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function slugs(): array
    {
        $slugs = $this->root()['slugs'] ?? [];

        return is_array($slugs) ? $slugs : [];
    }

    /**
     * @param array<string, mixed> $page
     */
    public function savePage(string $route, array $page): void
    {
        $this->mutate(static function (array &$root) use ($route, $page): void {
            if (!isset($root['pages']) || !is_array($root['pages'])) {
                $root['pages'] = [];
            }
            $root['pages'][$route] = $page;
        });
    }

    public function deletePage(string $route): void
    {
        $this->mutate(static function (array &$root) use ($route): void {
            unset($root['pages'][$route]);
        });
    }

    /**
     * @param array<string, mixed> $slugRoute
     */
    public function saveSlugRoute(string $route, array $slugRoute): void
    {
        $this->mutate(static function (array &$root) use ($route, $slugRoute): void {
            if (!isset($root['slug_routes']) || !is_array($root['slug_routes'])) {
                $root['slug_routes'] = [];
            }
            $root['slug_routes'][$route] = $slugRoute;
        });
    }

    public function deleteSlugRoute(string $route): void
    {
        $this->mutate(static function (array &$root) use ($route): void {
            unset($root['slug_routes'][$route]);
            unset($root['slugs'][$route]);
        });
    }

    /**
     * @param array<string, mixed> $slugCfg
     */
    public function saveSlug(string $route, string $slug, array $slugCfg): void
    {
        $this->mutate(static function (array &$root) use ($route, $slug, $slugCfg): void {
            if (!isset($root['slugs']) || !is_array($root['slugs'])) {
                $root['slugs'] = [];
            }
            if (!isset($root['slugs'][$route]) || !is_array($root['slugs'][$route])) {
                $root['slugs'][$route] = [];
            }
            $root['slugs'][$route][$slug] = $slugCfg;
        });
    }

    public function deleteSlug(string $route, string $slug): void
    {
        $this->mutate(static function (array &$root) use ($route, $slug): void {
            unset($root['slugs'][$route][$slug]);
            if (isset($root['slugs'][$route]) && $root['slugs'][$route] === []) {
                unset($root['slugs'][$route]);
            }
        });
    }

    public function configPath(): string
    {
        return $this->configFile;
    }

    /**
     * @param callable(array<string, mixed>): void $mutator
     */
    private function mutate(callable $mutator): void
    {
        $parsed = Yaml::parseFile($this->configFile);
        if (!is_array($parsed) || !is_array($parsed['nowo_seo_kit'] ?? null)) {
            throw new RuntimeException('Invalid nowo_seo_kit.yaml');
        }

        /** @var array<string, mixed> $root */
        $root = $parsed['nowo_seo_kit'];
        $mutator($root);
        $parsed['nowo_seo_kit'] = $root;

        $yaml = Yaml::dump($parsed, 10, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        if (!is_string($yaml) || $yaml === '') {
            throw new RuntimeException('Failed to dump nowo_seo_kit.yaml');
        }

        if (file_put_contents($this->configFile, $yaml) === false) {
            throw new RuntimeException('Failed to write nowo_seo_kit.yaml');
        }

        // Do not wipe var/cache mid-request (breaks redirects). In APP_ENV=dev
        // Symfony tracks this YAML as a FileResource and rebuilds on the next hit.
    }
}
