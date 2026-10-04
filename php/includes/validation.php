<?php
// php/includes/validation.php

function required_field_errors(array $data, array $requiredFields): array
{
    $errors = [];
    foreach ($requiredFields as $field) {
        $value = trim((string)($data[$field] ?? ''));
        if ($value === '') {
            $errors[] = "Lauks '{$field}' ir obligāts.";
        }
    }
    return $errors;
}

function max_length_errors(array $data, array $fieldLimits): array
{
    $errors = [];
    foreach ($fieldLimits as $field => $limit) {
        $value = (string)($data[$field] ?? '');
        if (mb_strlen($value) > $limit) {
            $errors[] = "Lauks '{$field}' nedrīkst pārsniegt {$limit} rakstzīmes.";
        }
    }
    return $errors;
}

/** A text field of the posted form; '' when missing or not a string (e.g. name[]=… sent instead). */
function post_string(string $key, bool $trim = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}
