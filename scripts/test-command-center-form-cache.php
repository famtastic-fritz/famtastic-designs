<?php
/** Real Drupal cached-form dependency roundtrip without submitting or sending. */
if (\Drupal::database()->driver() !== 'sqlite') throw new RuntimeException('Disposable SQLite only.');
foreach (['ClientMessageReplyForm', 'CampaignAddForm', 'OwnerWorkSummaryForm'] as $name) {
  $class = 'Drupal\\famtastic_pipeline\\Form\\' . $name;
  $form = unserialize(serialize($class::create(\Drupal::getContainer())));
  foreach ((new ReflectionClass($form))->getProperties() as $property) {
    if ($property->getDeclaringClass()->getName() === $class && !$property->isInitialized($form)) throw new RuntimeException('Cached service missing: ' . $name . ':' . $property->getName());
  }
  print 'PASS cached form services: ' . $name . "\n";
}
