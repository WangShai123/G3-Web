<?php
namespace JEALER\G3\Core\Admin;

final class Section {

    /** @var Field[] */
    private array $fields = [];

    private string $optionName = '';

    private array $optionDefaults = [];

    private bool $optionAutoload = true;

    public function __construct(
        private readonly string $id,
        private readonly string $title = ''
    ) {
    }

    public static function make(string $id, string $title = ''): self
    {
        return new self($id, $title);
    }

    public function option(string $name, array $defaults = [], bool $autoload = true): self
    {
        $this->optionName     = $name;
        $this->optionDefaults = $defaults;
        $this->optionAutoload = $autoload;
        return $this;
    }

    public function input(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'input', $description);
    }

    public function password(string $id, string $title, string $description = ''): self
    {
        $field = Field::make($id, $title)->type('input')->inputType('password')->description($description);
        return $this->add($field);
    }

    public function number(string $id, string $title, string $description = ''): self
    {
        $field = Field::make($id, $title)->type('input')->inputType('number')->description($description);
        return $this->add($field);
    }

    public function time(string $id, string $title, string $description = ''): self
    {
        $field = Field::make($id, $title)->type('input')->inputType('time')->description($description);
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

    public function file(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'file', $description);
    }

    public function switch(string $id, string $title, string $description = ''): self
    {
        return $this->field($id, $title, 'switch', $description);
    }

    /** @param array<string, string> $options */
    public function checkbox(string $id, string $title, array $options, string $description = '', bool $horizontal = true): self
    {
        return $this->add(
            Field::make($id, $title)
                ->type('checkbox')
                ->options($options)
                ->description($description)
                ->horizontal($horizontal)
        );
    }

    /** @param array<string, string> $options */
    public function radio(string $id, string $title, array $options, string $description = '', bool $horizontal = true): self
    {
        return $this->add(
            Field::make($id, $title)
                ->type('radio')
                ->options($options)
                ->description($description)
                ->horizontal($horizontal)
        );
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

    public function hasOption(): bool
    {
        return $this->optionName !== '';
    }

    public function optionName(): string
    {
        return $this->optionName;
    }

    public function optionDefaults(): array
    {
        return $this->optionDefaults;
    }

    public function optionAutoload(): bool
    {
        return $this->optionAutoload;
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
