<!doctype html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#0A0E1A;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;">
<tr><td style="background:#0A0E1A;color:#E8F4FD;padding:22px 28px;font-size:20px;font-weight:bold;">ATT&amp;CK Radar <span style="color:#00D4FF;font-size:13px;font-weight:normal;">par CyberAfrik</span></td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6;">
<p style="margin:0 0 16px;">Bonjour,</p>
<p style="margin:0 0 16px;">Votre plan de détection est prêt. Téléchargez le classeur Excel avec le bouton ci-dessous.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:15px;">
<tr><td style="padding:4px 16px 4px 0;color:#55607a;">Couverture aujourd'hui</td><td style="font-weight:bold;">{{ $summary['pct_on'] }} %</td></tr>
<tr><td style="padding:4px 16px 4px 0;color:#55607a;">Avec les sources prévues</td><td style="font-weight:bold;">{{ $summary['pct_planned'] }} %</td></tr>
<tr><td style="padding:4px 16px 4px 0;color:#55607a;">Règles applicables</td><td style="font-weight:bold;">{{ $summary['rules'] }}</td></tr>
<tr><td style="padding:4px 16px 4px 0;color:#55607a;">Angles morts</td><td style="font-weight:bold;">{{ $summary['gaps'] }}</td></tr>
<tr><td style="padding:4px 16px 4px 0;color:#55607a;">Langage des requêtes</td><td style="font-weight:bold;">{{ $summary['language'] }}</td></tr>
</table>
<p style="margin:0 0 14px;"><a href="{{ $xlsxUrl }}" style="display:inline-block;background:#00D4FF;color:#04121A;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:8px;">Télécharger le classeur Excel</a></p>
<p style="margin:0 0 22px;font-size:14px;"><a href="{{ $planUrl }}" style="color:#0077A3;">Rouvrir mon plan en ligne</a> &nbsp;·&nbsp; <a href="{{ $layerUrl }}" style="color:#0077A3;">Calque ATT&amp;CK Navigator</a></p>
<p style="margin:0 0 8px;color:#55607a;font-size:13px;">Gardez ces liens pour vous : toute personne qui les possède peut consulter ce plan. Le classeur contient des signatures d'attaque en clair, il est possible que votre antivirus le signale.</p>
<p style="margin:0;color:#55607a;font-size:13px;">Les requêtes sont converties automatiquement à partir de règles Sigma. Testez-les dans votre environnement avant de les mettre en production.</p>
</td></tr>
<tr><td style="padding:18px 28px;background:#f3f5f9;color:#55607a;font-size:12px;line-height:1.5;">
CyberAfrik SARL, Cotonou, Bénin. Vous recevez ce message parce que cette adresse a été saisie sur radar.cyber-afrik.com pour recevoir un export.
@if ($unsubscribeUrl)
<br><a href="{{ $unsubscribeUrl }}" style="color:#55607a;">Ne plus recevoir les actualités de CyberAfrik</a>
@endif
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
