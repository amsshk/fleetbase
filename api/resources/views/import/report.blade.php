<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Report</title>
</head>
<body>
    <h1>Import Report</h1>
    <p><strong>Type:</strong> {{ $report['type'] ?? '-' }}</p>
    <p><strong>Model:</strong> {{ $report['model_class'] ?? '-' }}</p>
    <p><strong>Total rows:</strong> {{ $report['total_rows'] ?? 0 }}</p>
    <p><strong>Successful:</strong> {{ $report['successful'] ?? 0 }}</p>
    <p><strong>Failed:</strong> {{ $report['failed'] ?? 0 }}</p>

    @if(!empty($report['errors']))
        <h2>Errors</h2>
        <pre>{{ json_encode($report['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif
</body>
</html>
