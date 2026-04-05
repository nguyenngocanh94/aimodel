<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use Illuminate\Support\Facades\Validator;

class ConfigValidator
{
    /**
     * Validate a node config against its template's rules.
     *
     * @return array{valid: bool, errors: array<string, array<string>>}
     */
    public function validate(string $nodeType, array $config, NodeTemplateRegistry $registry): array
    {
        $template = $registry->get($nodeType);

        if ($template === null) {
            return [
                'valid' => false,
                'errors' => ['_type' => ["Unknown node type: {$nodeType}"]],
            ];
        }

        $rules = $template->configRules();
        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }
}
