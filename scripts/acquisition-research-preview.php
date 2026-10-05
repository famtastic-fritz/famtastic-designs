<?php
declare(strict_types=1);
// Representative review only; no Drupal bootstrap, recipient, queue or transport.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$service=$root.'/backend/web/modules/custom/famtastic_pipeline/src/Service/';
foreach(['BrandedEmail','AcquisitionSampleArtifacts','AcquisitionSampleGuard','AcquisitionSampleSequenceService','AcquisitionSampleEmail'] as $class) require_once $service.$class.'.php';
$data=json_decode(file_get_contents($root.'/marketing/campaigns/acquisition-199/messages.json'),true,512,JSON_THROW_ON_ERROR);
$draft=array_values(array_filter($data['messages'],fn(array $d):bool=>$d['id']==='mobile_detailing_d0'))[0];
$business='Schwarzbär Global Mobile Car Detailing';
array_unshift($draft['paragraphs'],'Your publicly posted mobile detailing and restoration offering prompted one question: would a clear vehicle-and-location quote request help you review complex jobs before confirming them? This is a discovery question, not a finding about your current process.');
$draft['subject']='Two illustrative directions for Schwarzbär Global';
$qr=$root.'/marketing/campaigns/acquisition-199/assets/connect-qr.png';
$result=\Drupal\famtastic_pipeline\Service\AcquisitionSampleEmail::compile($draft,['business_name'=>$business],str_repeat('ab',32),str_repeat('cd',24),hash_file('sha256',$qr),'1729 NW St. Lucie West Blvd #1181, Port Saint Lucie, FL 34986');
$html=$result['html'];
$html=str_replace('https://famtasticdesigns.com/samples/'.str_repeat('ab',32),'https://example.invalid/research-invitation-not-bound',$html);
$html=str_replace('https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/'.str_repeat('cd',24),'https://example.invalid/research-unsubscribe-not-bound',$html);
$html=str_replace(\Drupal\famtastic_pipeline\Service\BrandedEmail::LOGO_URL,'../../../marketing/campaigns/acquisition-199/assets/famtastic-designs-logo-v1.png',$html);
foreach($result['attachments'] as $attachment){$path=$attachment['purpose']==='digital-card'?'marketing/campaigns/acquisition-199/assets/connect-qr.png':$attachment['source_path'];$html=str_replace('cid:'.$attachment['cid'],'../../../'.$path,$html);}
$note='<div role="note" style="padding:18px;background:#fff4c2;color:#302400;font:14px/1.6 Arial">UNSENT RESEARCH REVIEW · business name is advertiser-presented; independent owner/contact unknown. No recipient is selected. Source restrictions prevent unsolicited contact using Craigslist information. Invitation/unsubscribe deliberately unbound. <a href="measurement-prospect-research.md">Research card and factual limits</a></div>';
$html=preg_replace('/(<body[^>]*>)/','$1'.$note,$html,1);
$out=$root.'/docs/research/acquisition-199/representative-mobile-detailing.html';file_put_contents($out,$html);
$body=str_replace(['https://famtasticdesigns.com/samples/'.str_repeat('ab',32),'https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/'.str_repeat('cd',24)],['https://example.invalid/research-invitation-not-bound','https://example.invalid/research-unsubscribe-not-bound'],$result['body']);
file_put_contents(dirname($out).'/representative-mobile-detailing.txt',"UNSENT REVIEW; no eligible recipient/contact; see measurement-prospect-research.md\n\n".$body);
foreach (['detailing_precision','detailing_route_ready'] as $recipe) {
  $template=file_get_contents($root.'/marketing/campaigns/acquisition-199/templates/'.$recipe.'.html');
  $preview=\Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard::render($template,['business_name'=>$business,'locality'=>'Miami (advertised)']);
  $preview=str_replace('/brand/famtastic-designs-logo-v1.png','../../../marketing/campaigns/acquisition-199/assets/famtastic-designs-logo-v1.png',$preview);
  file_put_contents(dirname($out).'/representative-'.$recipe.'.html',$preview);
}
echo "Representative HTML/plain research draft rendered through native compiler; no account, provider or recipient.\n";
