<?php

declare(strict_types=1);

namespace Test\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates documents against the JSON:API 1.1 JSON Schema (`jsonapi-1.1.schema.json`): every document rendered by the
 * Feature tests goes through it before any comparison.
 */
final class JsonApiSchema
{
    private const ID = 'https://jsonapi.org/schemas/spec/v1.0/draft';

    /**
     * The violations of a document, each as `<JSON pointer>: <message>`; empty when the document is valid.
     *
     * @return list<string>
     */
    public static function errors(mixed $document): array
    {
        // Through JSON, as the client reads it: an empty PHP array is a list, never an object.
        $json = is_string($document) ? $document : json_encode($document, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['/: The document is not valid JSON (' . json_last_error_msg() . ').'];
        }

        $result = self::validator()->validate($decoded, self::ID);
        $error = $result->error();

        if ($error === null) {
            return [];
        }

        $errors = [];
        /** @var array<string, string> $messages one message by pointer */
        $messages = (new ErrorFormatter())->format($error, false);
        foreach ($messages as $pointer => $message) {
            $errors[] = "{$pointer}: {$message}";
        }

        return $errors;
    }

    private static ?Validator $validator = null;

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->resolver()?->registerFile(self::ID, __DIR__ . '/jsonapi-1.1.schema.json');
        }

        return self::$validator;
    }
}
