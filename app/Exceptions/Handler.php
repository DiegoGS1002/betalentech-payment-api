<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class Handler
{
    /**
     * Handle validation exceptions with Portuguese messages.
     */
    public static function handleValidationException(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $errors[$field] = array_map(function ($message) use ($field) {
                return self::translateValidationMessage($message, $field);
            }, $messages);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro de validação',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Translate validation messages to Portuguese.
     */
    protected static function translateValidationMessage(string $message, string $field): string
    {
        $fieldName = self::getFieldName($field);

        // Map of English patterns to Portuguese translations
        $translations = [
            // Required
            ['pattern' => '/The .* field is required\./', 'translation' => "O campo {$fieldName} é obrigatório."],
            ['pattern' => '/The .* field must be present\./', 'translation' => "O campo {$fieldName} deve estar presente."],

            // Email
            ['pattern' => '/The .* field must be a valid email address\./', 'translation' => "O campo {$fieldName} deve ser um e-mail válido."],
            ['pattern' => '/The .* must be a valid email address\./', 'translation' => "O campo {$fieldName} deve ser um e-mail válido."],

            // String
            ['pattern' => '/The .* field must be a string\./', 'translation' => "O campo {$fieldName} deve ser um texto."],
            ['pattern' => '/The .* must be a string\./', 'translation' => "O campo {$fieldName} deve ser um texto."],

            // Integer
            ['pattern' => '/The .* field must be an integer\./', 'translation' => "O campo {$fieldName} deve ser um número inteiro."],
            ['pattern' => '/The .* must be an integer\./', 'translation' => "O campo {$fieldName} deve ser um número inteiro."],

            // Numeric
            ['pattern' => '/The .* field must be a number\./', 'translation' => "O campo {$fieldName} deve ser um número."],
            ['pattern' => '/The .* must be a number\./', 'translation' => "O campo {$fieldName} deve ser um número."],

            // Min (string length)
            ['pattern' => '/The .* field must be at least (\d+) characters\./', 'translation' => "O campo {$fieldName} deve ter no mínimo $1 caracteres."],
            ['pattern' => '/The .* must be at least (\d+) characters\./', 'translation' => "O campo {$fieldName} deve ter no mínimo $1 caracteres."],

            // Min (numeric)
            ['pattern' => '/The .* field must be at least (\d+)\./', 'translation' => "O campo {$fieldName} deve ser no mínimo $1."],
            ['pattern' => '/The .* must be at least (\d+)\./', 'translation' => "O campo {$fieldName} deve ser no mínimo $1."],

            // Max (string length)
            ['pattern' => '/The .* field must not be greater than (\d+) characters\./', 'translation' => "O campo {$fieldName} não pode ter mais de $1 caracteres."],
            ['pattern' => '/The .* may not be greater than (\d+) characters\./', 'translation' => "O campo {$fieldName} não pode ter mais de $1 caracteres."],

            // Max (numeric)
            ['pattern' => '/The .* field must not be greater than (\d+)\./', 'translation' => "O campo {$fieldName} não pode ser maior que $1."],
            ['pattern' => '/The .* may not be greater than (\d+)\./', 'translation' => "O campo {$fieldName} não pode ser maior que $1."],

            // Size
            ['pattern' => '/The .* field must be (\d+) characters\./', 'translation' => "O campo {$fieldName} deve ter exatamente $1 caracteres."],
            ['pattern' => '/The .* must be (\d+) characters\./', 'translation' => "O campo {$fieldName} deve ter exatamente $1 caracteres."],

            // Unique
            ['pattern' => '/The .* has already been taken\./', 'translation' => "O {$fieldName} informado já está em uso."],

            // Exists
            ['pattern' => '/The selected .* is invalid\./', 'translation' => "O {$fieldName} selecionado é inválido ou não existe."],

            // Array
            ['pattern' => '/The .* field must be an array\./', 'translation' => "O campo {$fieldName} deve ser uma lista."],
            ['pattern' => '/The .* must be an array\./', 'translation' => "O campo {$fieldName} deve ser uma lista."],

            // Array min items
            ['pattern' => '/The .* field must have at least (\d+) items\./', 'translation' => "O campo {$fieldName} deve ter no mínimo $1 item(s)."],
            ['pattern' => '/The .* must have at least (\d+) items\./', 'translation' => "O campo {$fieldName} deve ter no mínimo $1 item(s)."],

            // Confirmed
            ['pattern' => '/The .* field confirmation does not match\./', 'translation' => "A confirmação do campo {$fieldName} não corresponde."],
            ['pattern' => '/The .* confirmation does not match\./', 'translation' => "A confirmação do campo {$fieldName} não corresponde."],

            // Date
            ['pattern' => '/The .* field must be a valid date\./', 'translation' => "O campo {$fieldName} deve ser uma data válida."],
            ['pattern' => '/The .* is not a valid date\./', 'translation' => "O campo {$fieldName} deve ser uma data válida."],

            // Boolean
            ['pattern' => '/The .* field must be true or false\./', 'translation' => "O campo {$fieldName} deve ser verdadeiro ou falso."],

            // Credentials
            ['pattern' => '/The provided credentials are incorrect\./', 'translation' => "As credenciais fornecidas estão incorretas."],
            ['pattern' => '/These credentials do not match our records\./', 'translation' => "As credenciais informadas não correspondem aos nossos registros."],

            // In (invalid value)
            ['pattern' => '/The selected .* is invalid\./', 'translation' => "O valor selecionado para {$fieldName} é inválido."],

            // Regex
            ['pattern' => '/The .* field format is invalid\./', 'translation' => "O formato do campo {$fieldName} é inválido."],
            ['pattern' => '/The .* format is invalid\./', 'translation' => "O formato do campo {$fieldName} é inválido."],

            // Digits
            ['pattern' => '/The .* field must be (\d+) digits\./', 'translation' => "O campo {$fieldName} deve ter exatamente $1 dígitos."],
            ['pattern' => '/The .* must be (\d+) digits\./', 'translation' => "O campo {$fieldName} deve ter exatamente $1 dígitos."],

            // Digits between
            ['pattern' => '/The .* field must be between (\d+) and (\d+) digits\./', 'translation' => "O campo {$fieldName} deve ter entre $1 e $2 dígitos."],
            ['pattern' => '/The .* must be between (\d+) and (\d+) digits\./', 'translation' => "O campo {$fieldName} deve ter entre $1 e $2 dígitos."],
        ];

        foreach ($translations as $item) {
            if (preg_match($item['pattern'], $message, $matches)) {
                // Replace $1, $2, etc. with captured groups
                $result = $item['translation'];
                for ($i = 1; $i < count($matches); $i++) {
                    $result = str_replace('$' . $i, $matches[$i], $result);
                }
                return $result;
            }
        }

        // If no translation found, return original message
        return $message;
    }

    /**
     * Get friendly field name in Portuguese.
     */
    protected static function getFieldName(string $field): string
    {
        $fieldNames = [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'password_confirmation' => 'confirmação de senha',
            'role' => 'perfil',
            'amount' => 'valor',
            'priority' => 'prioridade',
            'is_active' => 'status ativo',
            'cardNumber' => 'número do cartão',
            'cvv' => 'CVV',
            'products' => 'produtos',
            'products.*.product_id' => 'ID do produto',
            'products.*.quantity' => 'quantidade',
            'product_id' => 'ID do produto',
            'quantity' => 'quantidade',
            'client_id' => 'ID do cliente',
            'gateway_id' => 'ID do gateway',
            'external_id' => 'ID externo',
            'status' => 'status',
            'card_last_numbers' => 'últimos números do cartão',
            'transaction_id' => 'ID da transação',
            'token' => 'token',
        ];

        // Handle nested fields like products.0.product_id
        $cleanField = preg_replace('/\.\d+\./', '.*.', $field);

        return $fieldNames[$cleanField] ?? $fieldNames[$field] ?? $field;
    }

    /**
     * Handle model not found exception.
     */
    public static function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        $modelNames = [
            'App\\Models\\User' => 'Usuário',
            'App\\Models\\Product' => 'Produto',
            'App\\Models\\Client' => 'Cliente',
            'App\\Models\\Gateway' => 'Gateway',
            'App\\Models\\Transaction' => 'Transação',
            'App\\Models\\TransactionProduct' => 'Produto da transação',
        ];

        $model = $e->getModel();
        $modelName = $modelNames[$model] ?? 'Recurso';

        return response()->json([
            'success' => false,
            'message' => "{$modelName} não encontrado",
            'error' => "O {$modelName} solicitado não foi encontrado no sistema.",
        ], 404);
    }
}

