<?php

declare(strict_types=1);

namespace Drupal\sanitizer_plain_uid_entity\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Holds a user id in a plain integer field, which no reference finder knows.
 */
#[ContentEntityType(
  id: 'sanitizer_plain_uid_record',
  label: new TranslatableMarkup('Plain uid record'),
  entity_keys: ['id' => 'id'],
  base_table: 'sanitizer_plain_uid_record',
)]
final class PlainUidRecord extends ContentEntityBase {

  /**
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('integer')->setLabel(new TranslatableMarkup('User id'));
    return $fields;
  }

}
