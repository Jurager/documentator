<?php

namespace Jurager\Documentator\Formats;

abstract class AbstractFormat implements ResponseFormat
{
    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function schemas(): array;

    abstract protected function successSchema(): string;

    abstract protected function errorSchema(): string;

    public function responses(): array
    {
        $success = ['$ref' => '#/components/schemas/' . $this->successSchema()];
        $error = ['$ref' => '#/components/schemas/' . $this->errorSchema()];

        return [
            'Success' => $this->wrapSchema('Успешный ответ', $success),
            'Created' => $this->wrapSchema('Ресурс создан', $success),
            'BadRequest' => $this->wrapSchema('Некорректный запрос', $error),
            'Unauthorized' => $this->wrapSchema('Не авторизован', $error),
            'NotFound' => $this->wrapSchema('Ресурс не найден', $error),
            'ValidationError' => $this->wrapSchema('Ошибка валидации', $error),
            'NoContent' => ['description' => 'Ресурс удален'],
        ];
    }

    protected function wrapSchema(string $description, array $schema): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }
}
