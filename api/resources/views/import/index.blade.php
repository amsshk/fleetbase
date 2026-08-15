<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleetbase Import Helper</title>
</head>
<body>
    <h1>Fleetbase Data Import Helper</h1>

    <p>Use full model namespaces (for example <code>Fleetbase\FleetbaseCore\Models\Company</code>) when using tinker or custom scripts.</p>

    <h2>Required fields</h2>
    @foreach($modelConfig as $type => $definition)
        <h3>{{ ucfirst($type) }}</h3>
        <p><strong>Required:</strong> {{ implode(', ', $definition['required'] ?? []) }}</p>
        <p><strong>Optional:</strong> {{ implode(', ', $definition['optional'] ?? []) }}</p>
        <p><strong>Template headers (CSV/XLSX):</strong> {{ implode(', ', array_unique(array_merge($definition['required'] ?? [], $definition['optional'] ?? []))) }}</p>
    @endforeach

    <h2>Preview import file</h2>
    <form method="POST" action="/api/import/preview" enctype="multipart/form-data">
        @csrf
        <label for="type">Type</label>
        <select name="type" id="type">
            @foreach($types as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <br>
        <label for="file">CSV/XLSX file</label>
        <input id="file" type="file" name="file" required>
        <br>
        <button type="submit">Preview</button>
    </form>

    <h2>Import</h2>
    <form method="POST" action="/api/import/run" enctype="multipart/form-data">
        @csrf
        <label for="import-type">Type</label>
        <select name="type" id="import-type">
            @foreach($types as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <br>
        <label for="import-file">CSV/XLSX file</label>
        <input id="import-file" type="file" name="file" required>
        <br>
        <label>
            <input type="checkbox" name="dry_run" value="1"> Dry run only
        </label>
        <br>
        <label>
            <input type="checkbox" name="rollback_on_error" value="1"> Rollback on first error
        </label>
        <br>
        <button type="submit">Run Import</button>
    </form>

    <h2>Troubleshooting</h2>
    <ul>
        <li>If tinker says <code>Class "Company" not found</code>, use the full namespace instead of short class names.</li>
        <li>Run <code>php artisan database:check-data --json</code> to verify model loading and existing record counts.</li>
        <li>Run <code>php artisan import:data company /absolute/path/to/companies.csv --dry-run</code> before real imports.</li>
    </ul>
</body>
</html>
