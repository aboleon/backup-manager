<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tracking;

final class MutationTable
{
    private const IDENTIFIER = '(?:`[^`]+`|"[^"]+"|\[[^\]]+\]|[a-zA-Z0-9_$-]+)(?:\s*\.\s*(?:`[^`]+`|"[^"]+"|\[[^\]]+\]|[a-zA-Z0-9_$-]+))*';

    private const STANDARD_MUTATION = '/^(?:'
        .'insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from|'
        .'truncate(?:\s+table)?|alter\s+table|create\s+table(?:\s+if\s+not\s+exists)?|'
        .'drop\s+table(?:\s+if\s+exists)?|rename\s+table'
        .')\s+('.self::IDENTIFIER.')/i';

    private const CTE_MUTATION = '/^with\b[\s\S]*?\b(?:'
        .'insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from'
        .')\s+('.self::IDENTIFIER.')/i';

    private const LOAD_DATA_MUTATION = '/^load\s+data\b[\s\S]*?\binto\s+table\s+('.self::IDENTIFIER.')/i';

    public function fromSql(string $sql): ?string
    {
        $sql = $this->normalize($sql);

        foreach ([self::STANDARD_MUTATION, self::CTE_MUTATION, self::LOAD_DATA_MUTATION] as $pattern) {
            if (preg_match($pattern, $sql, $matches) === 1) {
                return $this->unqualify($matches[1]);
            }
        }

        return null;
    }

    public function isMutation(string $sql): bool
    {
        $sql = $this->normalize($sql);

        return preg_match('/^(?:insert|replace|update|delete|truncate|alter|create|drop|rename|call|load\s+data)\b/i', $sql) === 1
            || preg_match(self::CTE_MUTATION, $sql) === 1;
    }

    private function normalize(string $sql): string
    {
        $normalized = preg_replace(
            '/\A(?:\s|--[^\r\n]*(?:\R|$)|#[^\r\n]*(?:\R|$)|\/\*[\s\S]*?\*\/)+/',
            '',
            $sql,
        );

        return ltrim($normalized ?? $sql);
    }

    private function unqualify(string $table): string
    {
        preg_match_all(
            '/`([^`]+)`|"([^"]+)"|\[([^\]]+)\]|([a-zA-Z0-9_$-]+)/',
            $table,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matches === []) {
            return trim($table, '`"[]');
        }

        $last = end($matches);

        foreach ([1, 2, 3, 4] as $index) {
            if (isset($last[$index]) && $last[$index] !== '') {
                return $last[$index];
            }
        }

        return trim($table, '`"[]');
    }
}
