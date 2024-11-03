<?php
declare(strict_types=1);
namespace App\Service\TemplateEngine;

class TemplateVariableProxy implements \Iterator, \ArrayAccess, \Countable {
    protected $iteratorPosition = 0;

    /**
     * @param string $escapeType one of 'html' or 'raw' (no escaping)
     */
    public function __construct(protected string $name, protected mixed $value, protected string $escapeType)
    {
    }

    public static function create(string $name, mixed $value, string $escapeType): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || $value === '' || $value instanceof static) {
            return $value;
        } else {
            return new static($name, $value, $escapeType);
        }
    }

    /**
     * $templateVariable->foo = bla
     * @throws \LogicException because the variable is read-only
     */
    public function __set(string $propertyName, mixed $value): never
    {
        throw new \LogicException('template variables are read-only');
    }

    /**
     * $templateVariable->foo
     */
    public function __get(string $propertyName): mixed
    {
        if ($propertyName === 'raw') {
            return $this->raw();
        }
        return static::create($propertyName, $this->value->$propertyName, $this->escapeType);
    }

    /**
     * $templateVariable->foo(bar)
     */
    public function __call(string $name, array $arguments): mixed
    {
        return static::create($name, $this->value->$name(...$arguments), $this->escapeType);
    }

    /**
     * $templateVariable(bar)
     */
    public function __invoke(...$arguments): mixed
    {
        return static::create($this->name, call_user_func($this->value, ...$arguments), $this->escapeType);
    }

    /**
     * get the wrapped value
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * return the raw string value (not escaped)
     */
    public function raw(): string
    {
        if (is_callable($this->value)) {
            return $this->value();
        }
        if (is_array($this->value)) {
            return $this->json();
        }
        if (is_object($this->value)) {
            return var_export($this->value, true);
        }
        return (string)$this->value;
    }

    /**
     * return the json encoded value
     */
    public function json(bool $pretty = false): string
    {
        return json_encode($this->value, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    /**
     * (string)$templateVariable, echo $templateVariable, <?=$templateVariable?>
     */
    public function __toString(): string
    {
        return match($this->escapeType) {
            'raw' => $this->raw(),
            'html' => $this->escape(),
            default => throw new \Exception('escape type not implemented: ' . $this->escapeType),
        };
    }

    public function escape(): string
    {
        return TemplateEngine::htmlEscape($this->raw());
    }

    // ArrayAccess methods
    /**
     * implements \ArrayAccess::offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * implements \ArrayAccess::offsetGet
     */
    public function offsetGet($offset): mixed
    {
        $value = $this->value[$offset] ?? null;
        return static::create((string)$this->iteratorPosition, $value, $this->escapeType);
    }

    /**
     * implements \ArrayAccess::offsetSet
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException('template variables are read-only');
    }

    /**
     * implements \ArrayAccess::offsetUnset
     */
    public function offsetUnset($offset): void
    {
        throw new \LogicException('template variables are read-only');
    }

    // Iterator methods
    /**
     * implements \Iterator::current
     */
    public function current(): mixed
    {
        $value = $this->value[array_keys($this->value)[$this->iteratorPosition]];
        return static::create((string)$this->iteratorPosition, $value, $this->escapeType);
    }

    /**
     * implements \Iterator::key
     */
    public function key(): mixed
    {
        return array_keys($this->value)[$this->iteratorPosition];
    }

    /**
     * implements \Iterator::next
     */
    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    /**
     * implements \Iterator::rewind
     */
    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }

    /**
     * implements \Iterator::valid
     */
    public function valid(): bool
    {
        return $this->iteratorPosition < count($this->value);
    }

    /**
     * implements \Iterator::count
     */
    public function count(): int
    {
        return count($this->value);
    }

    // convenience functions
    /**
     * HTML table of the array content
     */
    public function htmlTable(): string
    {
        if (!$this->value) {
            return '';
        }
        if (!is_array($this->value) || !is_array_($this->value[0])) {
            throw new LogicException($this->name . ' is not a two-dimensional array');
        }

        $html = "<tr>" . array_reduce(array_keys($this->value[0]), fn($res, $key) => $res . "<th>" . TemplateEngine::htmlEscape($key) . "</th>") . "</tr>\n";
        $html .= array_reduce($this->value,
            fn($res, $row) => $res . "<tr>" . array_reduce($row,
                fn($res2, $value) => $res2 . "<td>" . TemplateEngine::htmlEscape($value) . "</td>"
            ) . "</tr>\n");
        return "<table>\n$html\n</table>\n";
    }

    /**
     * HTML list of a one-dimensional array content
     * @param string $listType 'ol' or 'ul' or 'table' or '' ('' => no list tag)
     */
    public function htmlList(string $listType = 'ol'): string
    {
        $openTag = $listType === 'table' ? '<tr><td>' : '<li>';
        $closeTag = $listType === 'table' ? '</td></tr>' : '</li>';
        $html = array_reduce($this->value, fn($res, $value) => $res . $openTag . TemplateEngine::htmlEscape($value) . $closeTag . PHP_EOL);
        return match($listType) {
            'ol', 'ul' => "<$listType>\n$html</$listType>\n",
            'table' => "<table>\n<tr><th>" . TemplateEngine::htmlEscape($this->name) . "</th></tr>\n$html</table>\n",
            '' => $html,
            default => throw new \InvalidArgumentException("invalid list type: $listType"),
        };
    }

    public function dump(): string
    {
        return '<pre>' . $this->__toString() . '</pre>';
    }

    public function implode(string $separator): string
    {
        return implode($separator, $this->value);
    }

    public function inputHidden(string $name = ''): string
    {
        return '<input type="hidden" name="' . TemplateEngine::htmlEscape($name ?: $this->name) . '" value="' . $this->escape() . '">';
    }
}
