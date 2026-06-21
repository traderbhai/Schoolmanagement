<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $notice->title ?? 'Official Notice' }}</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#dc6f00;padding:30px 40px;">
        <p style="margin:0 0 4px;font-size:13px;color:#fde68a;text-transform:uppercase;letter-spacing:1px;">Official Notice</p>
        <h1 style="margin:0;color:#ffffff;font-size:22px;">{{ $notice->title ?? 'Notice title pending' }}</h1>
      </td></tr>
      <tr><td style="padding:40px;">
        <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;">Published on {{ $notice->publish_date ? \Carbon\Carbon::parse($notice->publish_date)->format('d M Y') : 'Publish date pending' }}</p>
        <div style="font-size:15px;color:#374151;line-height:1.7;margin-top:16px;">
          {!! nl2br(e($notice->content ?? 'Notice content pending.')) !!}
        </div>
      </td></tr>
      <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">&copy; {{ date('Y') }} College Management System. All rights reserved.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
