<?php
/** Form API database failure must not expose SQL, body or paths. */
if (\Drupal::database()->driver() !== 'sqlite') throw new RuntimeException('Disposable SQLite only.');
$account=\Drupal\user\Entity\User::load(1);\Drupal::service('account_switcher')->switchTo($account);
$db=\Drupal::database();$thread=$db->select('famtastic_portal_thread','t')->fields('t',['public_id'])->range(0,1)->execute()->fetchField();
$form=\Drupal\famtastic_pipeline\Form\ClientMessageReplyForm::create(\Drupal::getContainer());$state=new \Drupal\Core\Form\FormState();$array=$form->buildForm([], $state, $thread);
$state->setValue('body','RAW-SQL-SECRET-MARKER')->setValue('purpose','reply')->setTriggeringElement(['#name'=>'save']);
$db->getClientConnection()->exec("CREATE TRIGGER fixture_error_guard BEFORE INSERT ON famtastic_message_draft_revision BEGIN SELECT RAISE(ABORT, 'RAW-SQL-SECRET-MARKER'); END;");
\Drupal::messenger()->deleteAll();
try { $form->submitForm($array,$state); $messages=implode(' ',array_map('strval',\Drupal::messenger()->messagesByType('error')));if(str_contains($messages,'RAW-SQL-SECRET-MARKER') || !str_contains($messages,'could not be saved'))throw new RuntimeException('Unsafe error message');print "PASS database exception form message omits SQL/body details\n"; }
finally {$db->getClientConnection()->exec('DROP TRIGGER fixture_error_guard');\Drupal::service('account_switcher')->switchBack();}
