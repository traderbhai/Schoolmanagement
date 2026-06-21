<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Welcome</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#1a56db;padding:30px 40px;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;">Welcome to College Management System</h1>
      </td></tr>
      <tr><td style="padding:40px;">
        <p style="margin:0 0 16px;font-size:16px;color:#374151;">Dear <strong>{{ $student->user?->name ?? 'Student' }}</strong>,</p>
        <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">Your student account has been created. Here are your details:</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:24px;">
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;width:40%;">Enrollment Number</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;font-weight:bold;">{{ $student->enrollment_number ?? 'Enrollment number pending' }}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Login Email</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">{{ $student->user?->email ?? 'Login email pending' }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Password</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">Use the password set by your admin</td>
          </tr>
        </table>
        <p style="margin:0;font-size:14px;color:#6b7280;">If you have any questions, please contact the administration office.</p>
      </td></tr>
      <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">&copy; {{ date('Y') }} College Management System. All rights reserved.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
