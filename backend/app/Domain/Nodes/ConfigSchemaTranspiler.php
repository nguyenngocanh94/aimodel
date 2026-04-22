<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

/**
 * Transpiles Laravel validator rules (as returned by NodeTemplate::configRules())
 * and a defaults array into a JSON Schema Draft-07 object (PHP array).
 *
 * Algorithm: flat-grouping (not a full trie).
 *   1. Scan all rule keys; group dot-notation keys by their top-level segment.
 *   2. Build a tree: each node has '_rules' (own rules) and '_children' (sub-keys).
 *   3. Walk the tree recursively to emit JSON Schema objects.
 *   4. Any top-level key with children is forced to type "object" regardless of
 *      its own declared type.
 */
final class ConfigSchemaTranspiler
{
    /**
     * @param  array<string, string|array<int, string>> $rules   Laravel validator rules
     * @param  array<string, mixed>                     $defaults Default config values
     * @return array<string, mixed>                              JSON Schema Draft-07 object
     */
    public function transpile(array $rules, array $defaults): array
    {
        $tree = $this->buildTree($rules);
        ['properties' => $properties, 'required' => $required] = $this->walkTree($tree, $defaults);

        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    // ── Tree builder ──────────────────────────────────────────────────────

    /**
     * Build a flat-grouping tree from dot-notation rule keys.
     *
     * Each entry is: [ '_rules' => [...], '_children' => [ subKey => entry ] ]
     *
     * @param  array<string, string|array<int, string>> $rules
     * @return array<string, array{_rules: list<string>, _children: array}>
     */
    private function buildTree(array $rules): array
    {
        $tree = [];

        foreach ($rules as $key => $ruleValue) {
            $normalized = $this->normalizeRules($ruleValue);
            $segments = explode('.', $key);
            $top = $segments[0];

            if (!isset($tree[$top])) {
                $tree[$top] = ['_rules' => [], '_children' => []];
            }

            if (count($segments) === 1) {
                // Own rules for this key
                $tree[$top]['_rules'] = $normalized;
            } else {
                // Dot-notation child: strip top segment and re-key
                $childKey = implode('.', array_slice($segments, 1));
                $tree[$top]['_children'][$childKey] = $normalized;
            }
        }

        return $tree;
    }

    // ── Tree walker ───────────────────────────────────────────────────────

    /**
     * Walk the tree and produce `properties` + `required` arrays.
     *
     * @param  array<string, array{_rules: list<string>, _children: array}> $tree
     * @param  array<string, mixed> $defaults
     * @return array{properties: array<string, mixed>, required: list<string>}
     */
    private function walkTree(array $tree, array $defaults): array
    {
        $properties = [];
        $required = [];

        foreach ($tree as $key => $node) {
            $ownRules = $node['_rules'];
            $childRules = $node['_children']; // [ 'sub.key' => [rule list], ... ]
            $hasChildren = !empty($childRules);

            if ($hasChildren) {
                // This node must be an object; recurse
                $childDefaults = is_array($defaults[$key] ?? null) ? $defaults[$key] : [];
                $nested = $this->transpileChildren($childRules, $childDefaults);

                $properties[$key] = $nested;

                // The parent key itself is only required when its own rules say so
                if (in_array('required', $ownRules, true)) {
                    $required[] = $key;
                }
            } else {
                // Leaf node
                $schema = $this->buildLeafSchema($ownRules, $defaults[$key] ?? null);
                $properties[$key] = $schema;

                if (in_array('required', $ownRules, true)) {
                    $required[] = $key;
                }
            }
        }

        return ['properties' => $properties, 'required' => $required];
    }

    /**
     * Recurse into children (already stripped of the parent prefix).
     * Re-uses transpile logic but without the $schema envelope.
     *
     * @param  array<string, list<string>> $childRules  sub-key => rule list
     * @param  array<string, mixed>        $childDefaults
     * @return array<string, mixed> JSON Schema object node
     */
    private function transpileChildren(array $childRules, array $childDefaults): array
    {
        // Turn childRules back into the shape buildTree expects
        $rulesFormatted = [];
        foreach ($childRules as $subKey => $ruleList) {
            $rulesFormatted[$subKey] = $ruleList;
        }

        $subTree = $this->buildTree($rulesFormatted);
        ['properties' => $properties, 'required' => $required] = $this->walkTree($subTree, $childDefaults);

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    // ── Leaf schema builder ───────────────────────────────────────────────

    /**
     * Build a JSON Schema property node for a leaf (no dot-notation children).
     *
     * @param  list<string> $rules
     * @param  mixed        $default
     * @return array<string, mixed>
     */
    private function buildLeafSchema(array $rules, mixed $default): array
    {
        $schema = [];

        // Detect base type
        $baseType = $this->detectBaseType($rules);
        $nullable = in_array('nullable', $rules, true);

        if ($baseType !== null) {
            if ($nullable) {
                $schema['type'] = [$baseType, 'null'];
            } else {
                $schema['type'] = $baseType;
            }
        }

        // enum via in:a,b,c
        $enum = $this->extractEnum($rules);
        if ($enum !== null) {
            $schema['enum'] = $enum;
        }

        // min / max — depends on base type
        $min = $this->extractConstraintValue($rules, 'min');
        $max = $this->extractConstraintValue($rules, 'max');

        if ($baseType === 'string') {
            if ($min !== null) {
                $schema['minLength'] = $min;
            }
            if ($max !== null) {
                $schema['maxLength'] = $max;
            }
        } elseif (in_array($baseType, ['integer', 'number'], true)) {
            if ($min !== null) {
                $schema['minimum'] = $min;
            }
            if ($max !== null) {
                $schema['maximum'] = $max;
            }
        }

        // default value
        if ($default !== null) {
            $schema['default'] = $default;
        }

        return $schema;
    }

    // ── Rule helpers ──────────────────────────────────────────────────────

    /**
     * Normalise a rule value to a list of strings.
     *
     * Laravel allows both pipe-separated strings and arrays:
     *   'required|string|min:5'  →  ['required', 'string', 'min:5']
     *   ['required', 'string']   →  ['required', 'string']
     *
     * @param  string|array<int, string> $ruleValue
     * @return list<string>
     */
    private function normalizeRules(mixed $ruleValue): array
    {
        if (is_string($ruleValue)) {
            return explode('|', $ruleValue);
        }

        /** @var list<string> */
        return array_values(array_map('strval', (array) $ruleValue));
    }

    /**
     * @param  list<string> $rules
     */
    private function detectBaseType(array $rules): ?string
    {
        $typeMap = [
            'string' => 'string',
            'integer' => 'integer',
            'numeric' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
        ];

        foreach ($rules as $rule) {
            if (isset($typeMap[$rule])) {
                return $typeMap[$rule];
            }
        }

        return null;
    }

    /**
     * Extract enum values from an `in:a,b,c` rule.
     *
     * @param  list<string> $rules
     * @return list<string>|null
     */
    private function extractEnum(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            }
        }
        return null;
    }

    /**
     * Extract the numeric value from a `min:N` or `max:N` rule.
     *
     * @param  list<string> $rules
     */
    private function extractConstraintValue(array $rules, string $constraint): int|float|null
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, $constraint . ':')) {
                $raw = substr($rule, strlen($constraint) + 1);
                return str_contains($raw, '.') ? (float) $raw : (int) $raw;
            }
        }
        return null;
    }
}
