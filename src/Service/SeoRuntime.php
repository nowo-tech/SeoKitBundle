<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

/**
 * Request-scoped override bag set from controllers (wins over YAML / attributes).
 */
final class SeoRuntime
{
    /** @var array<string, mixed> */
    private array $overrides = [];

    /** @var array<string, mixed> */
    private array $variables = [];

    /**
     * @param array<string, mixed> $overrides Keys: title, description, robots, canonical, keywords, author, open_graph, twitter, noindex, ...
     */
    public function set(array $overrides): void
    {
        $this->overrides = array_replace($this->overrides, $overrides);
    }

    /**
     * Template variables for `{title}`, `{slug}`, etc.
     *
     * @param array<string, scalar|null> $variables
     */
    public function setVariables(array $variables): void
    {
        $this->variables = array_replace($this->variables, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function clear(): void
    {
        $this->overrides = [];
        $this->variables = [];
    }
}
