<?php
namespace JEALER\G3\Core\Admin;

final class Section {

    /** @var Field[] */
    private array $fields = [];

    public function __construct(
        private readonly string $id,
        private readonly string $state,
        private readonly string $title = ''
    ) {
    }

    public static function make(string $id, string $state, string $title = ''): self
    {
        return new self($id, $state, $title);
    }

    public function input(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'input', $description);
    }

    public function number(string $id, string $title, string $description = ''): self
    {
        $field = Field::make($id, $title)->type('input')->inputType('number')->description($description);
        return $this->add($field);
    }

    public function textarea(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'textarea', $description);
    }

    public function image(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'image', $description);
    }

    public function switch(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'switch', $description);
    }

    /** @param array<string, string> $options */
    public function select(string $id, string $title, array $options, string $description = ''): self
    {
        return $this->add(Field::make($id, $title)->type('select')->options($options)->description($description));
    }

    public function readonlyUrl(string $id, string $title, string $fallback = ''): self
    {
        $field = Field::make($id, $title)->type('readonly-url')->value($fallback);
        return $this->add($field);
    }

    public function html(string $id, string $title, string $html): self
    {
        return $this->add(Field::make($id, $title)->html($html));
    }

    public function callback(string $id, string $title, callable $callback): self
    {
        return $this->add(Field::make($id, $title)->type('callback')->callback($callback));
    }

    public function rowClass(string $rowClass): self
    {
        $last = array_key_last($this->fields);
        if ($last !== null) {
            $this->fields[$last]->rowClass($rowClass);
        }
        return $this;
    }

    public function add(Field $field): self
    {
        $this->fields[] = $field;
        return $this;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    private function field(string $id, string $title, string $type, string $description): self
    {
        return $this->add(Field::make($id, $title)->type($type)->description($description));
    }
}
