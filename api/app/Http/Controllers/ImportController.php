<?php

namespace App\Http\Controllers;

use App\Support\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ImportController extends Controller
{
    public function __construct(protected ImportService $importService)
    {
    }

    public function index(): View
    {
        return view('import.index', [
            'types' => $this->importService->availableTypes(),
            'modelConfig' => config('import.models', []),
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'types' => $this->importService->availableTypes(),
            'models' => config('import.models', []),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $payload = $this->validateRequest($request);

        try {
            $rows = $this->resolveRows($request, $payload);
            $typeConfig = $this->importService->getTypeConfig($payload['type']);
            $required = $typeConfig['required'] ?? [];

            $errors = [];

            foreach ($rows as $index => $row) {
                $rowErrors = $this->importService->validateRow($row, $required);

                if (!empty($rowErrors)) {
                    $errors[] = [
                        'row' => $index + 1,
                        'errors' => $rowErrors,
                    ];
                }
            }

            return response()->json([
                'type' => $payload['type'],
                'total_rows' => count($rows),
                'preview_rows' => array_slice($rows, 0, 10),
                'errors' => $errors,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $payload = $this->validateRequest($request);

        try {
            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('import/uploads');
                $absolutePath = Storage::disk('local')->path($path);

                $report = $this->importService->importFromFile(
                    $payload['type'],
                    $absolutePath,
                    [
                        'dry_run' => (bool) ($payload['dry_run'] ?? false),
                        'rollback_on_error' => (bool) ($payload['rollback_on_error'] ?? false),
                        'write_report' => true,
                    ]
                );
            } else {
                $report = $this->importService->importRows(
                    $payload['type'],
                    $payload['rows'] ?? [],
                    [
                        'dry_run' => (bool) ($payload['dry_run'] ?? false),
                        'rollback_on_error' => (bool) ($payload['rollback_on_error'] ?? false),
                        'write_report' => true,
                    ]
                );
            }

            return response()->json($report, $report['failed'] > 0 ? 422 : 200);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    protected function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', $this->importService->availableTypes())],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx'],
            'rows' => ['nullable', 'array'],
            'rows.*' => ['array'],
            'dry_run' => ['nullable', 'boolean'],
            'rollback_on_error' => ['nullable', 'boolean'],
        ]);

        if (!$request->hasFile('file') && empty($validated['rows'])) {
            throw ValidationException::withMessages([
                'file' => 'Either file upload or rows payload is required.',
            ]);
        }

        return $validated;
    }

    protected function resolveRows(Request $request, array $payload): array
    {
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('import/uploads');
            $absolutePath = Storage::disk('local')->path($path);

            return $this->importService->parseFile($absolutePath);
        }

        return $payload['rows'] ?? [];
    }
}
