<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 60px 56px; }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        color: #232429;
        font-size: 12px;
        line-height: 1.6;
    }
    .header {
        width: 100%;
        border-bottom: 3px solid #1d5fae;
        padding-bottom: 14px;
        margin-bottom: 28px;
    }
    .header table { width: 100%; border-collapse: collapse; }
    .header img { width: 56px; height: auto; }
    .school-name {
        font-size: 16px;
        font-weight: bold;
        color: #14355c;
        margin: 0 0 2px 0;
    }
    .school-meta {
        font-size: 10px;
        color: #5b5f66;
        margin: 0;
    }
    .title {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #1d5fae;
        text-transform: uppercase;
        margin: 0 0 24px 0;
    }
    .date {
        text-align: right;
        color: #5b5f66;
        margin-bottom: 18px;
    }
    p { margin: 0 0 12px 0; }
    .details {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0 20px 0;
        border: 1px solid #cfd3d9;
    }
    .details td {
        padding: 8px 12px;
        border-bottom: 1px solid #e6e8eb;
        font-size: 11px;
    }
    .details tr:last-child td { border-bottom: none; }
    .details td.label {
        color: #5b5f66;
        width: 40%;
    }
    .details td.value {
        color: #232429;
        font-weight: bold;
    }
    .credentials {
        background: #fdf6e0;
        border: 1px solid #f0b400;
        border-radius: 4px;
        padding: 14px 16px;
        margin: 18px 0;
    }
    .credentials p { margin: 0 0 4px 0; }
    .credentials .cred-label { color: #5b5f66; }
    .credentials .cred-value {
        font-weight: bold;
        font-size: 13px;
        color: #14355c;
    }
    .signature {
        margin-top: 40px;
    }
    .signature .org {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #cfd3d9;
        font-size: 11px;
        color: #5b5f66;
    }
    .footer {
        margin-top: 36px;
        padding-top: 10px;
        border-top: 1px solid #e6e8eb;
        font-size: 9px;
        color: #9aa0a8;
        text-align: center;
    }
</style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width: 64px;">
                    @if($logoBase64)
                        <img src="data:image/png;base64,{{ $logoBase64 }}" alt="">
                    @endif
                </td>
                <td>
                    <p class="school-name">{{ $school['name'] }}</p>
                    <p class="school-meta">{{ $school['address'] }}</p>
                    @if($school['phone'])
                        <p class="school-meta">{{ $school['phone'] }}{{ $school['email'] ? ' &middot; '.$school['email'] : '' }}</p>
                    @elseif($school['email'])
                        <p class="school-meta">{{ $school['email'] }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p class="title">Letter of Admission</p>
    <p class="date">{{ $issuedAt }}</p>

    <p>Dear {{ $student->full_name }}{{ $student->guardian?->full_name ? ' and '.$student->guardian->full_name : '' }},</p>

    <p>
        Congratulations! We are pleased to inform you that your application for admission to
        {{ $school['name'] }} has been reviewed and approved. We warmly welcome you to our school
        community and look forward to supporting your education.
    </p>

    <table class="details">
        <tr>
            <td class="label">Admission number</td>
            <td class="value">{{ $admissionNo }}</td>
        </tr>
        <tr>
            <td class="label">Full name</td>
            <td class="value">{{ $student->full_name }}</td>
        </tr>
        <tr>
            <td class="label">Date of birth</td>
            <td class="value">{{ $student->dob?->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Class</td>
            <td class="value">{{ $student->schoolClass ? $student->schoolClass->name.' '.$student->schoolClass->arm : 'To be assigned' }}</td>
        </tr>
    </table>

    <p>
        An account has been created so you can complete registration and access your dashboard online.
        Use the credentials below to sign in for the first time — you will be asked to set your own
        password immediately after logging in.
    </p>

    <div class="credentials">
        <p><span class="cred-label">Login link:</span> <span class="cred-value">{{ $loginUrl }}</span></p>
        <p><span class="cred-label">Username:</span> <span class="cred-value">{{ $admissionNo }}</span></p>
        <p><span class="cred-label">Temporary password:</span> <span class="cred-value">{{ $temporaryPassword }}</span></p>
    </div>

    <p>
        Please keep this letter for your records. If you have any questions about registration,
        fees, or your class placement, contact the Admissions Office using the details above.
    </p>

    <p>We look forward to welcoming you to {{ $school['name'] }}.</p>

    <div class="signature">
        <p>Sincerely,</p>
        <div class="org">Admissions Office &middot; {{ $school['name'] }}</div>
    </div>

    <div class="footer">
        This letter was generated automatically by the {{ $school['name'] }} school management system.
    </div>
</body>
</html>
