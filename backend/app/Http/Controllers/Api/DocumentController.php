<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use App\Support\DocumentAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams the printable FOD documents (FR-O5) as PDF.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function show(Request $request, string $type, int $id): Response
    {
        abort_if($request->user() === null, 401);

        abort_unless(DocumentAccess::isKnown($type), 404, 'Jenis dokumen tidak dikenal.');

        abort_unless(
            DocumentAccess::allows($request->user(), $type),
            403,
            'Anda tidak memiliki akses ke dokumen ini.',
        );

        $document = $this->documents->render($type, $id);

        return response($document['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
