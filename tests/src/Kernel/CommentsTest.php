<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\EntityTypeEmptier;
use Drupal\sql_sanitize_extended\Stage\ReferenceReassigner;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;

/**
 * Cases that need comments on top of users and content.
 */
final class CommentsTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'text', 'filter', 'node', 'comment'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['node', 'comment']);
    NodeType::create(['type' => 'plain_bundle', 'name' => 'plain_bundle'])->save();
  }

  public function testKeptCommentOnDeletedUserIsDeleted(): void {
    CommentType::create(['id' => 'comment_on_user', 'label' => 'Comment on user', 'target_entity_type_id' => 'user'])->save();
    FieldStorageConfig::create(['field_name' => 'comment_on_user', 'entity_type' => 'user', 'type' => 'comment', 'settings' => ['comment_type' => 'comment_on_user']])->save();
    FieldConfig::create(['field_name' => 'comment_on_user', 'entity_type' => 'user', 'bundle' => 'user'])->save();
    $deletedSubject = $this->createUser('deletedSubject');
    Comment::create([
      'entity_type' => 'user',
      'entity_id' => $deletedSubject->id(),
      'field_name' => 'comment_on_user',
      'comment_type' => 'comment_on_user',
      'uid' => self::ADMINISTRATOR_UID,
      'status' => 1,
      'subject' => 'Hello',
    ])->save();

    $this->sanitize(new Configuration(allowlistEntityTypes: ['comment']), [UserDeleter::class]);

    $this->assertSame(0, $this->rowCount('comment_field_data'));
  }

  public function testCommentsAreEmptied(): void {
    $this->createCommentOnNewNode();

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame(0, $this->rowCount('comment_field_data'));
  }

  public function testCommentStatisticsAreReset(): void {
    // Core's install hook sets this; kernel tests do not run it.
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);
    $comment = $this->createCommentOnNewNode((int) $this->createUser('deletedNodeOwner')->id());
    $this->assertSame(1, $this->rowCount('comment_entity_statistics', ['entity_id' => $comment->getCommentedEntityId(), 'comment_count' => 1]));

    $this->sanitize(stageClasses: [EntityTypeEmptier::class, ReferenceReassigner::class]);

    $statistics = $this->container->get('database')->select('comment_entity_statistics', 's')
      ->fields('s', ['comment_count', 'last_comment_uid'])
      ->condition('s.entity_id', $comment->getCommentedEntityId())
      ->execute()->fetchAssoc();
    $this->assertEquals(['comment_count' => 0, 'last_comment_uid' => 0], $statistics);
  }

  private function createCommentOnNewNode(int $nodeOwnerUid = self::ADMINISTRATOR_UID): CommentInterface {
    CommentType::create(['id' => 'comment', 'label' => 'Comment', 'target_entity_type_id' => 'node'])->save();
    FieldStorageConfig::create(['field_name' => 'comment', 'entity_type' => 'node', 'type' => 'comment', 'settings' => ['comment_type' => 'comment']])->save();
    FieldConfig::create(['field_name' => 'comment', 'entity_type' => 'node', 'bundle' => 'plain_bundle', 'settings' => []])->save();
    $author = $this->createUser('deletedCommenter');
    $node = Node::create(['type' => 'plain_bundle', 'title' => 'Content', 'uid' => $nodeOwnerUid, 'comment' => ['status' => CommentItemInterface::OPEN]]);
    $node->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comment',
      'comment_type' => 'comment',
      'uid' => $author->id(),
      'status' => 1,
      'subject' => 'Hello',
    ]);
    $comment->save();
    return $comment;
  }

}
