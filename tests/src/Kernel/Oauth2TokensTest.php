<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Token;
use Drupal\sql_sanitize_extended\Stage\EntityTypeEmptier;
use Drupal\sql_sanitize_extended\Stage\ReferenceReassigner;

final class Oauth2TokensTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'file', 'image', 'serialization', 'options', 'consumers', 'simple_oauth'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_token');
    $this->installConfig(['consumers', 'simple_oauth']);
  }

  public function testTokensEmptied(): void {
    $consumer = Consumer::create(['label' => 'Consumer of deleted user', 'client_id' => 'consumerOfDeletedUser', 'user_id' => $this->createUser('deletedOwner')->id()]);
    $consumer->save();
    Oauth2Token::create(['bundle' => 'access_token', 'auth_user_id' => $this->createUser('deletedTokenHolder')->id(), 'client' => $consumer->id(), 'value' => 'secret', 'expire' => time() + 100])->save();

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame(0, $this->rowCount('oauth2_token'));
  }

  public function testConsumerOwnedByDeletedUserIsReassignedToAnonymous(): void {
    $consumer = Consumer::create(['label' => 'Consumer of deleted user', 'client_id' => 'consumerOfDeletedUser', 'user_id' => $this->createUser('deletedOwner')->id()]);
    $consumer->save();

    $this->sanitize(stageClasses: [ReferenceReassigner::class]);

    $this->assertSame(self::ANONYMOUS_UID, (int) Consumer::load($consumer->id())->get('user_id')->target_id);
  }

}
