<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cheque Return Memo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .bank-name { font-size: 18px; font-weight: bold; }
        .memo-title { font-size: 14px; font-weight: bold; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background-color: #e0e0e0; padding: 6px; border: 1px solid #999; text-align: left; }
        td { padding: 6px; border: 1px solid #999; }
        .footer { margin-top: 40px; font-size: 11px; border-top: 1px solid #999; padding-top: 10px; }
        .signature-block { margin-top: 60px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="bank-name">{{ $bank_name }}</div>
        <div>{{ $return->originalInstrument?->branch_code ?? '' }}</div>
        <div class="memo-title">CHEQUE RETURN MEMO</div>
        <div>Date: {{ $date }}</div>
    </div>

    <p>To: The Account Holder / Presenting Bank</p>
    <p>We regret to inform you that the following cheque has been returned unpaid for the reason stated below:</p>

    <table>
        <tr><th>Cheque Number</th><td>{{ $return->originalInstrument?->cheque_number }}</td><th>Cheque Date</th><td>{{ $return->originalInstrument?->instrument_date }}</td></tr>
        <tr><th>Amount (INR)</th><td>{{ number_format($return->amount, 2) }}</td><th>Payee Name</th><td>{{ $return->originalInstrument?->payee_name }}</td></tr>
        <tr><th>Drawer Bank</th><td>{{ $return->originalInstrument?->bank_sort_code }}</td><th>Account Number</th><td>{{ $return->originalInstrument?->account_number }}</td></tr>
        <tr><th>Return Reason Code</th><td>{{ $return->return_reason_code }}</td><th>Return Date</th><td>{{ $return->return_date }}</td></tr>
        <tr><th colspan="4">Return Reason Description</th></tr>
        <tr><td colspan="4" style="font-weight:bold; color:red;">{{ $return->return_reason_description }}</td></tr>
    </table>

    <p>Please take note and arrange for the necessary action. In case of queries, please contact your nearest {{ $bank_name }} branch.</p>

    <div class="signature-block">
        <p>___________________________</p>
        <p>Authorized Signatory</p>
        <p>{{ $bank_name }}</p>
    </div>

    <div class="footer">
        <p><strong>Note:</strong> This is a computer-generated return memo issued under the Cheque Truncation System (CTS) National Grid operated by Indian Overseas Bank. Reference: GEM/2026/B/7367951.</p>
        <p>For grievances, contact: cts.support@iob.in | Toll Free: 1800-425-4422</p>
    </div>
</body>
</html>
