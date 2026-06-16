<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>You've been invited</title>
    <style>
        body { font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .meta { color: #666; margin-bottom: 1.5rem; }
        .cta { display: inline-block; margin-top: 1rem; padding: 10px 20px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-size: 0.875rem; }
        .footer { margin-top: 2rem; font-size: 0.75rem; color: #999; }
    </style>
</head>
<body>
    <h1>You've been invited</h1>
    <p class="meta">{{ $invitation->inviter?->full_name ?? 'An administrator' }} has invited you to join.</p>

    <p>Click the link below to set up your account. This invitation expires in 7 days.</p>

    <a href="{{ route('invitations.show', $invitation->token) }}" class="cta">Accept Invitation</a>

    <div class="footer">
        <p>If you were not expecting this invitation, you can safely ignore this email.</p>
        <p>This link expires on {{ $invitation->created_at->addDays(7)->format('F j, Y') }}.</p>
    </div>
</body>
</html>
