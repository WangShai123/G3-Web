<?php
namespace JEALER\G3\Core\Admin;

final class Field {

    private string $type = 'input';

    private string $id;

    private string $title;

    private string $description = '';

    private string $legend = '';

    private string $class = '';

    private string $inputType = 'text';

    private string $size = 'md';

    private int $imageSize = 120;

    private $callback = null;

    private string $html = '';

    private string $rowClass = '';

    private string $value = '';

    private bool $horizontal = true;

    /** @var array<string, string> */
    private array $options = [];

    public function __construct(string $id, string $title)
    {
        $this->id     = $id;
        $this->title  = $title;
        $this->legend = $title;
    }

    public static function make(string $id, string $title): self
    {
        return new self($id, $title);
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function legend(string $legend): self
    {
        $this->legend = $legend;
        return $this;
    }

    public function class(string $class): self
    {
        $this->class = $class;
        return $this;
    }

    public function inputType(string $type): self
    {
        $this->inputType = $type;
        return $this;
    }

    public function size(string $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function imageSize(int $size): self
    {
        $this->imageSize = $size;
        return $this;
    }

    public function callback(callable $callback): self
    {
        $this->callback = $callback;
        return $this;
    }

    public function html(string $html): self
    {
        $this->type = 'html';
        $this->html = $html;
        return $this;
    }

    public function value(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function horizontal(bool $horizontal): self
    {
        $this->horizontal = $horizontal;
        return $this;
    }

    public function rowClass(string $rowClass): self
    {
        $this->rowClass = $rowClass;
        return $this;
    }

    /** @param array<string, string> $options */
    public function options(array $options): self
    {
        $this->options = $options;
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

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'type'        => $this->type,
            'description' => $this->description,
            'legend'      => $this->legend,
            'class'       => $this->class,
            'rowClass'    => $this->rowClass,
            'inputType'   => $this->inputType,
            'size'        => $this->size,
            'imageSize'   => $this->imageSize,
            'options'     => $this->options,
            'callback'    => $this->callback,
            'html'        => $this->html,
            'value'       => $this->value,
            'horizontal'  => $this->horizontal,
        ];
    }
}
