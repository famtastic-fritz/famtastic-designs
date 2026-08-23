<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\SupportIntentClassifier;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\SupportIntentClassifier
 * @group famtastic_pipeline
 */
class SupportIntentClassifierTest extends UnitTestCase {

  /**
   * @covers ::classify
   */
  public function testLabeledCorpusAccuracy(): void {
    $classifier = new SupportIntentClassifier();
    $corpus = json_decode(
      // DRUPAL_ROOT is backend/web; the corpus lives in backend/tests/fixtures.
      file_get_contents(DRUPAL_ROOT . '/../tests/fixtures/support-intent-labeled.json'),
      TRUE
    );
    $this->assertNotEmpty($corpus, 'Labeled corpus fixture must exist and parse.');

    $correct = 0;
    $mismatches = [];
    foreach ($corpus as $case) {
      $result = $classifier->classify($case['subject'], $case['body']);
      if ($result['intent'] === $case['expected_intent']) {
        $correct++;
      }
      else {
        $mismatches[] = sprintf('%s: expected %s, got %s (confidence %.2f)', $case['id'], $case['expected_intent'], $result['intent'], $result['confidence']);
      }
    }

    $accuracy = $correct / count($corpus);
    $this->assertSame([], $mismatches, 'Corpus mismatches: ' . implode('; ', $mismatches));
    $this->assertGreaterThanOrEqual(0.9, $accuracy);
  }

  /**
   * @covers ::classify
   */
  public function testEmptyMessageEscalatesAsOther(): void {
    $classifier = new SupportIntentClassifier();
    $result = $classifier->classify('', '');
    $this->assertSame('other', $result['intent']);
    $this->assertSame(0.0, $result['confidence']);
    $this->assertTrue($result['escalate']);
  }

  /**
   * @covers ::classify
   */
  public function testSingleWeakBodySignalEscalates(): void {
    $classifier = new SupportIntentClassifier();
    // One weak body keyword alone must not claim confident intent.
    $result = $classifier->classify('', 'This is fine but the gallery is slow sometimes.');
    $this->assertTrue($result['escalate']);
  }

  /**
   * @covers ::classify
   */
  public function testSubjectHitCarriesMoreWeightThanBody(): void {
    $classifier = new SupportIntentClassifier();
    $strong = $classifier->classify('Invoice question', 'Can you send it?');
    $weak = $classifier->classify('', 'Something about an invoice maybe.');
    $this->assertGreaterThan($weak['confidence'], $strong['confidence']);
    $this->assertFalse($strong['escalate']);
    $this->assertTrue($weak['escalate']);
  }

}
