<?php

namespace App\Support\Ocr\Extraction;

use App\Models\FileExtractedField;
use App\Support\Ocr\ExtractedFieldPayloadMapper;

/**
 * Where a stored extracted field may go once verified: its document type's
 * schema says for schema fields; the archive item's own fields (title, date)
 * are the "record" route. A key neither knows has no route and can't be
 * accepted.
 */
final class ExtractedFieldRoute
{
    /**
     * @return array{route: ?string, target: ?string, label: array{ar: string, en: string}|null, multiple: bool}
     */
    public static function for(FileExtractedField $field): array
    {
        $definition = $field->document_type === null ? null : DocumentFieldSchema::field($field->document_type, $field->field_key);
        if ($definition !== null) {
            return ['route' => $definition->route, 'target' => $definition->target, 'label' => $definition->label, 'multiple' => $definition->isMultiple()];
        }

        if (ExtractedFieldPayloadMapper::toPayload($field->field_key, '') !== null) {
            return ['route' => FieldDefinition::ROUTE_RECORD, 'target' => "archive_item.{$field->field_key}", 'label' => null, 'multiple' => false];
        }

        return ['route' => null, 'target' => null, 'label' => null, 'multiple' => false];
    }
}
