<?php
namespace JEALER\G3\Core\Admin;

final class Panel {

    /** @var array<string, Section> */
    private array $tabs = [];

    /** @var array<string, Section> */
    private array $pages = [];

    private ?Section $current = null;

    private string $capability = 'manage_options';

    public function __construct(
        private readonly string $slug,
        private readonly string $title,
        private readonly string $menuTitle = ''
    )
    {
    }

    public static function make(string $slug, string $title, string $menuTitle = ''): self
    {
        return new self($slug, $title, $menuTitle);
    }

    public function capability(string $capability): self
    {
        $this->capability = $capability;
        return $this;
    }

    public function tab(string $id, string $title, string $state): self
    {
        $section         = Section::make($id, $state, $title);
        $this->tabs[$id] = $section;
        $this->current   = $section;
        return $this;
    }

    public function page(string $id, string $title, string $state): self
    {
        $page                 = Section::make($id, $state, $title);
        $this->pages[$id]     = $page;
        $this->current        = $page;
        return $this;
    }

    public function input(string $id, string $title, string $description = ''): self
    {
        $this->current()?->input($id, $title, $description);
        return $this;
    }

    public function number(string $id, string $title, string $description = ''): self
    {
        $this->current()?->number($id, $title, $description);
        return $this;
    }

    public function textarea(string $id, string $title, string $description = ''): self
    {
        $this->current()?->textarea($id, $title, $description);
        return $this;
    }

    public function image(string $id, string $title, string $description = ''): self
    {
        $this->current()?->image($id, $title, $description);
        return $this;
    }

    public function switch(string $id, string $title, string $description = ''): self
    {
        $this->current()?->switch($id, $title, $description);
        return $this;
    }

    /** @param array<string, string> $options */
    public function select(string $id, string $title, array $options, string $description = ''): self
    {
        $this->current()?->select($id, $title, $options, $description);
        return $this;
    }

    public function readonlyUrl(string $id, string $title, string $fallback = ''): self
    {
        $this->current()?->readonlyUrl($id, $title, $fallback);
        return $this;
    }

    public function html(string $id, string $title, string $html): self
    {
        $this->current()?->html($id, $title, $html);
        return $this;
    }

    public function callback(string $id, string $title, callable $callback): self
    {
        $this->current()?->callback($id, $title, $callback);
        return $this;
    }

    public function rowClass(string $rowClass): self
    {
        $this->current()?->rowClass($rowClass);
        return $this;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function menuTitle(): string
    {
        return $this->menuTitle !== '' ? $this->menuTitle : $this->title;
    }

    public function capabilityValue(): string
    {
        return $this->capability;
    }

    /**
     * @return array<string, Section>
     */
    public function tabs(): array
    {
        return $this->tabs;
    }

    /**
     * @return array<string, Section>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function hasTabs(): bool
    {
        return !empty($this->tabs);
    }

    public function tabTitles(): array
    {
        $tabs = [];
        foreach ($this->tabs as $id => $section) {
            $tabs[$id] = $section->title();
        }

        return $tabs;
    }

    private function current(): ?Section
    {
        return $this->current;
    }
}
