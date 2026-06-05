<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Exam Results</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#7c3aed;padding:30px 40px;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;">Exam Results Available</h1>
      </td></tr>
      <tr><td style="padding:40px;">
        <p style="margin:0 0 16px;font-size:16px;color:#374151;">Dear <strong>{{ $student->user->name }}</strong>,</p>
        <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">Your exam results for <strong>{{ $semesterName }}</strong> have been published.</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:24px;">
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;width:40%;">Semester</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;font-weight:bold;">{{ $semesterName }}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">SGPA</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;font-weight:bold;border-top:1px solid #e5e7eb;">{{ number_format($sgpa, 2) }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Overall Result</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:bold;border-top:1px solid #e5e7eb;color:{{ $overallResult === 'Pass' ? '#059669' : '#dc2626' }};">{{ $overallResult }}</td>
          </tr>
        </table>
        <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">Log in to the portal to view your detailed subject-wise results.</p>
        <a href="{{ url('/login') }}" style="display:inline-block;background:#7c3aed;color:#ffffff;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:15px;font-weight:bold;">View Detailed Results</a>
      </td></tr>
      <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">&copy; {{ date('Y') }} College Management System. All rights reserved.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
