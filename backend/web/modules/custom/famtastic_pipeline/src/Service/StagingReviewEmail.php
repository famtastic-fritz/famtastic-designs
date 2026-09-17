<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Pure presentation for customer_staging_review_ready/v1; never sends mail. */
final class StagingReviewEmail {

  public static function render(string $subject, string $body, string $logoUrl, bool $localPreview = FALSE): string {
    // The terminal, system-authored destination is the only actionable link.
    if (!preg_match('~\n\nReview your staging site:\n(https://[^\s]+)\s*$~D', $body, $match)) {
      throw new \InvalidArgumentException('staging_review_destination_missing');
    }
    $url = $match[1];
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
      || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.famtasticinc\.com$/D', $parts['host'] ?? '')
      || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
      || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '/') !== '/') {
      throw new \InvalidArgumentException('staging_review_destination_invalid');
    }
    // Local assets are permitted only by the explicit non-sending preview API.
    if (!($localPreview && $logoUrl === './assets/famtastic-designs-logo-v1.png')
      && !preg_match('~^https://(?:www\.)?famtasticdesigns\.com/[a-zA-Z0-9/_-]+\.png$~D', $logoUrl)) {
      throw new \InvalidArgumentException('staging_review_hosted_logo_required');
    }
    $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = $escape($subject);
    $logo = $escape($logoUrl);
    $cta = $escape($url);
    $destinationLabel = $escape($parts['host']);
    $message = substr($body, 0, -strlen($match[0]));
    $paragraphs = '';
    foreach (preg_split('/\R{2,}/', trim($message)) ?: [] as $paragraph) {
      $safe = $escape($paragraph);
      $safe = str_replace('The Signal Room', '<strong>The Signal Room</strong>', $safe);
      $paragraphs .= '<p style="margin:0 0 20px;">' . nl2br($safe, FALSE) . '</p>';
    }
    return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title>{$subject}</title>
<style>
@media only screen and (max-width:640px){.watermark{width:180px!important;max-width:100%!important}}
@media only screen and (max-width:640px){.shell{width:100%!important}.outer{padding:0!important}.stack{display:block!important;width:100%!important;box-sizing:border-box!important;text-align:center!important}.brand{margin:0 auto!important;width:300px!important;max-width:100%!important}.slogan{padding:20px 0 0!important;border:0!important}.paper{padding:30px 24px!important}.headline{font-size:30px!important}.service{width:50%!important;display:inline-block!important;box-sizing:border-box!important;padding:14px 4px!important}.signature{padding:10px 0!important}.footer-logo{margin:0 auto 20px!important}.cta{font-size:13px!important;padding:18px 20px!important}}
</style></head>
<body style="margin:0;padding:0;background:#070907;color:#fff;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{$subject}. No payment is due at this review stage.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#070907"><tr><td class="outer" align="center" style="padding:24px 10px;">
<!--[if mso]><table role="presentation" width="620"><tr><td><![endif]-->
<table role="presentation" class="shell" width="620" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#070907;background-image:linear-gradient(155deg,rgba(124,252,0,.14),transparent 25%,transparent 78%,rgba(124,252,0,.12));">
<tr><td align="center" style="padding:14px 12px;border-bottom:1px solid #7cfc00;font-size:9px;letter-spacing:2px;line-height:1.6;color:#e6e8e2;">WEBSITES &nbsp; | &nbsp; BRANDING &nbsp; | &nbsp; AUTOMATION &nbsp; | &nbsp; <span style="white-space:nowrap;">E-COMMERCE</span></td></tr>
<tr><td style="padding:25px 20px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td class="stack" width="310" style="vertical-align:middle;"><img class="brand" src="{$logo}" width="300" alt="FAMtastic Designs" style="display:block;width:300px;max-width:100%;height:auto;color:#fff;font-size:24px;border:0;"></td>
<td class="stack slogan" style="padding-left:18px;border-left:1px solid #65705b;vertical-align:middle;"><div style="font-family:'Trebuchet MS',Arial,sans-serif;font-size:19px;font-style:italic;font-weight:900;line-height:1.2;color:#fff;">More than a website…</div><div style="font-family:'Arial Black',Arial,sans-serif;font-style:italic;font-size:38px;font-weight:900;letter-spacing:-2px;line-height:1.2;color:#7cfc00;">A FUTURE.</div><div style="margin-top:12px;font-size:8px;letter-spacing:1px;line-height:1.6;">DREAM · BUILD · AUTOMATE · GROW · REPEAT</div></td>
</tr></table></td></tr>
<tr><td style="padding:0 10px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f7f7f4" style="background:#f7f7f4;border-radius:14px;"><tr><td class="paper" style="padding:32px 34px 26px;color:#252925;">
<div style="font-size:9px;letter-spacing:3px;line-height:1.8;color:#60675d;">FEARLESS IDEAS. BEAUTIFUL SOLUTIONS. REAL RESULTS.</div>
<div style="width:34px;border-top:2px solid #7cfc00;margin:16px 0 26px;"></div>
<div style="font-size:10px;font-weight:bold;letter-spacing:2px;color:#52613d;margin-bottom:12px;">YOUR WEBSITE / STAGING REVIEW</div>
<h1 class="headline" style="font-size:34px;line-height:1.12;letter-spacing:-1.1px;margin:0 0 25px;color:#111710;">Your selected direction<br>is taking shape.</h1>
<div style="font-size:16px;line-height:1.65;color:#252925;">{$paragraphs}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding-top:2px;"><img class="watermark" src="{$logo}" width="270" alt="" role="presentation" style="display:block;width:270px;max-width:100%;height:auto;opacity:.07;border:0;"></td><td align="right" style="font-size:8px;letter-spacing:2px;color:#636b60;line-height:1.8;">CREATE<br>BUILD<br>AUTOMATE<br>GROW<br>REPEAT</td></tr></table>
</td></tr></table></td></tr>
<tr><td align="center" style="padding:25px 16px 18px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="#7cfc00" style="border-radius:999px;background:#7cfc00;box-shadow:0 0 24px rgba(124,252,0,.35);mso-padding-alt:18px 28px;"><a class="cta" href="{$cta}" style="display:inline-block;padding:18px 28px;border:1px solid #7cfc00;border-radius:999px;background:#7cfc00;color:#070907;text-decoration:none;font-size:15px;font-weight:800;line-height:20px;">REVIEW YOUR STAGING SITE &nbsp; →</a></td></tr></table>
<p style="margin:12px 0 0;font-size:11px;line-height:1.5;color:#c6cfbd;">{$destinationLabel}</p>
</td></tr>
<tr><td style="padding:0 25px 21px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td class="stack signature" style="color:#7cfc00;font-family:Georgia,serif;font-style:italic;font-size:17px;">Same Energy. Different Message.</td><td class="stack signature" align="right" style="color:#7cfc00;font-family:Georgia,serif;font-style:italic;font-size:19px;">Always FAMtastic.</td></tr></table></td></tr>
<tr><td style="padding:20px 10px;border-top:1px solid #7cfc00;border-bottom:1px solid #7cfc00;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td class="service" width="25%" align="center" style="font-size:11px;line-height:1.5;color:#fff;"><span style="font-size:22px;color:#7cfc00;">▣</span><br><strong>WEBSITES</strong><br>THAT WORK</td>
<td class="service" width="25%" align="center" style="font-size:11px;line-height:1.5;color:#fff;"><span style="font-size:22px;color:#7cfc00;">✦</span><br><strong>BRANDING</strong><br>THAT POPS</td>
<td class="service" width="25%" align="center" style="font-size:11px;line-height:1.5;color:#fff;"><span style="font-size:22px;color:#7cfc00;">⚙</span><br><strong>AUTOMATION</strong><br>THAT SAVES TIME</td>
<td class="service" width="25%" align="center" style="font-size:11px;line-height:1.5;color:#fff;"><span style="font-size:22px;color:#7cfc00;">↗</span><br><strong>REAL RESULTS</strong><br>THAT MATTER</td>
</tr></table></td></tr>
<tr><td style="padding:24px 24px 15px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td class="stack" width="260"><img class="footer-logo" src="{$logo}" width="235" alt="FAMtastic Designs" style="display:block;width:235px;max-width:100%;height:auto;border:0;color:#fff;"></td><td class="stack" align="right" style="font-size:9px;letter-spacing:1.2px;line-height:2;color:#e1e5dc;">FEARLESS DEVIATION<br>APPLYING MASTERY<br>MANIFESTING EXTRAORDINARY</td></tr></table>
<p style="font-family:Georgia,serif;font-style:italic;text-align:center;font-size:13px;line-height:1.7;color:#e1e5dc;margin:18px 0;">We design the process, engineer the intelligence, and build the experience.</p>
<div style="border-top:2px solid #7cfc00;padding-top:18px;text-align:center;font-size:10px;line-height:1.8;letter-spacing:.7px;color:#c6cfbd;">FAMTASTICDESIGNS.COM<br>1729 NW St. Lucie West Blvd #1181<br>Port Saint Lucie, FL 34986</div>
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table></body></html>
HTML;
  }
}
